# Cross-repo CI/CD Trigger + RDS Sharing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the shared-RDS login flow between `tech-challenge-application`/`tech-challenge-kubernetes` and `tech-challenge-lambda-functions`, then wire the app's CI/CD to trigger the lambda's (new) CI/CD.

**Architecture:** Two independent fixes unblock the shared RDS (wrong Lambda `db_identifier` default; missing migrate step in the EKS deploy workflow). Then a new CI/CD workflow is added to `tech-challenge-lambda-functions` (tests + `terraform apply`, backed by a bootstrapped S3+DynamoDB remote state), triggered via `repository_dispatch` from the tail of `tech-challenge-application`'s `deploy-minikube.yml`.

**Tech Stack:** GitHub Actions, Terraform (AWS provider), Node.js 24 (Lambda), `yq`, `peter-evans/repository-dispatch@v3`, `aws-actions/configure-aws-credentials@v4`.

**Spec:** `docs/superpowers/specs/2026-09-08-cross-repo-cicd-rds-sharing-design.md` (this repo)

## Global Constraints

- Repos involved, all siblings under `/Applications/XAMPP/xamppfiles/htdocs/pos/`: `tech-challenge-application`, `tech-challenge-lambda-functions`, `tech-challenge-kubernetes`, `tech-challenge-database` (read-only for this plan).
- Real RDS identifier is `techchallenge-rds` (confirmed in `tech-challenge-database/rds.tf` and in the endpoint already used by `tech-challenge-kubernetes/k8s/01-config/configmap.yaml`) — every task that references it must use this exact string, not `tech-challenge-db`.
- New GitHub Actions secrets referenced by this plan must be created by the user in the respective repo's settings before that workflow can run for real; the tasks below only add the workflow code that expects them.
- `terraform validate` calls in this plan follow the existing convention from `tech-challenge-application/.github/workflows/laravel-ci.yml`: `terraform init -backend=false && terraform validate`.

---

### Task 1: Fix wrong RDS identifier default in tech-challenge-lambda-functions

**Files:**
- Modify: `/Applications/XAMPP/xamppfiles/htdocs/pos/tech-challenge-lambda-functions/variables.tf:7-11`

**Interfaces:**
- Produces: `var.db_identifier` now defaults to the real RDS identifier `"techchallenge-rds"`, consumed by `data.aws_db_instance.main` in `data.tf`.

- [ ] **Step 1: Make the change**

In `variables.tf`, change:

```hcl
variable "db_identifier" {
  description = "Identifier fixo da instância RDS no repositório tech-challenge-database"
  type        = string
  default     = "tech-challenge-db"
}
```

to:

```hcl
variable "db_identifier" {
  description = "Identifier fixo da instância RDS no repositório tech-challenge-database"
  type        = string
  default     = "techchallenge-rds"
}
```

- [ ] **Step 2: Validate Terraform syntax**

Run (from `tech-challenge-lambda-functions/`):
```bash
terraform init -backend=false && terraform validate
```
Expected: `Success! The configuration is valid.`

- [ ] **Step 3: Commit**

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/pos/tech-challenge-lambda-functions
git add variables.tf
git commit -m "fix: correct default RDS identifier to match tech-challenge-database (techchallenge-rds)"
```

---

### Task 2: Run migrations during EKS deploy in tech-challenge-kubernetes

**Files:**
- Modify: `/Applications/XAMPP/xamppfiles/htdocs/pos/tech-challenge-kubernetes/.github/workflows/deploy-eks.yml`

**Interfaces:**
- Consumes: `steps.image.outputs.tag` (already set earlier in the same job by the existing "Set image tag" step), `k8s/02-app/migrate-job.yaml` (existing manifest, hardcodes `image: ghcr.io/edunsantos/tech-challenge-application:latest`).
- Produces: a completed `Job/app-migrate` in namespace `postech` before the app deployment is rolled out.

- [ ] **Step 1: Add the migrate step**

Insert a new step between "Create app secret from GitHub repository secrets" and "Update deployment image" in `deploy-eks.yml`:

```yaml
      - name: Run database migrations
        run: |
          kubectl delete job app-migrate -n "$NAMESPACE" --ignore-not-found
          yq eval '.spec.template.spec.containers[0].image = "'"${APP_IMAGE}:${{ steps.image.outputs.tag }}"'"' \
            -i k8s/02-app/migrate-job.yaml
          kubectl apply -f k8s/02-app/migrate-job.yaml
          kubectl wait --for=condition=complete job/app-migrate -n "$NAMESPACE" --timeout=180s
```

The full step order in the `deploy` job becomes: Checkout → Set image tag → Configure AWS credentials → Update kubeconfig for EKS → Create GHCR pull secret if needed → Apply non-secret Kubernetes config → Create app secret from GitHub repository secrets → **Run database migrations (new)** → Update deployment image → Trigger rollout → Rollout status → Show pods.

- [ ] **Step 2: Validate YAML syntax**

Run (from `tech-challenge-kubernetes/`):
```bash
yq eval . .github/workflows/deploy-eks.yml > /dev/null && echo "valid"
```
Expected: `valid`

- [ ] **Step 3: Commit**

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/pos/tech-challenge-kubernetes
git add .github/workflows/deploy-eks.yml
git commit -m "feat: run app-migrate job before rollout in EKS deploy

The rollout-restart step (added earlier) only recreates pods with the
new image; it never ran php artisan migrate, so tables likely never
existed on the shared RDS the login Lambda depends on."
```

---

### Task 3 (MANUAL — not agent-executed): Bootstrap Terraform remote backend

This step provisions real AWS resources and needs AWS credentials the agentic worker does not have. **A human runs this once**, then continues to Task 4.

- [ ] **Step 1: Create the S3 bucket and DynamoDB lock table**

```bash
aws s3api create-bucket --bucket techchallenge-tfstate --region us-east-1
aws s3api put-bucket-versioning --bucket techchallenge-tfstate \
  --versioning-configuration Status=Enabled
aws s3api put-bucket-encryption --bucket techchallenge-tfstate \
  --server-side-encryption-configuration '{"Rules":[{"ApplyServerSideEncryptionByDefault":{"SSEAlgorithm":"AES256"}}]}'

aws dynamodb create-table --table-name techchallenge-tf-locks \
  --attribute-definitions AttributeName=LockID,AttributeType=S \
  --key-schema AttributeName=LockID,KeyType=HASH \
  --billing-mode PAY_PER_REQUEST --region us-east-1
```

- [ ] **Step 2: Confirm both resources exist**

```bash
aws s3api head-bucket --bucket techchallenge-tfstate && echo "bucket ok"
aws dynamodb describe-table --table-name techchallenge-tf-locks --query "Table.TableStatus"
```
Expected: `bucket ok` and `"ACTIVE"`.

---

### Task 4: Add S3 backend to tech-challenge-lambda-functions

**Depends on:** Task 3 (bucket/table must exist).

**Files:**
- Modify: `/Applications/XAMPP/xamppfiles/htdocs/pos/tech-challenge-lambda-functions/providers.tf`

**Interfaces:**
- Consumes: S3 bucket `techchallenge-tfstate`, DynamoDB table `techchallenge-tf-locks` (from Task 3).
- Produces: remote state at `s3://techchallenge-tfstate/lambda-auth/terraform.tfstate`, usable by any GitHub-hosted runner (no more reliance on a single machine's local state).

- [ ] **Step 1: Add the backend block**

In `providers.tf`, change:

```hcl
terraform {
  required_version = ">= 1.9.0"

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 6.0"
    }
    archive = {
      source  = "hashicorp/archive"
      version = "~> 2.4"
    }
  }
}
```

to:

```hcl
terraform {
  required_version = ">= 1.9.0"

  backend "s3" {
    bucket         = "techchallenge-tfstate"
    key            = "lambda-auth/terraform.tfstate"
    region         = "us-east-1"
    dynamodb_table = "techchallenge-tf-locks"
    encrypt        = true
  }

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 6.0"
    }
    archive = {
      source  = "hashicorp/archive"
      version = "~> 2.4"
    }
  }
}
```

- [ ] **Step 2: Migrate existing local state (if any)**

Run (from `tech-challenge-lambda-functions/`, only if a local `terraform.tfstate` already exists there from a prior manual apply):
```bash
terraform init -migrate-state
```
Answer `yes` when prompted to copy existing state to the new backend. If no local state file exists yet, run `terraform init` instead (no migration needed).

- [ ] **Step 3: Validate**

```bash
terraform validate
```
Expected: `Success! The configuration is valid.`

- [ ] **Step 4: Commit**

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/pos/tech-challenge-lambda-functions
git add providers.tf
git commit -m "feat: use S3 remote backend with DynamoDB locking for Terraform state

Local-only state can't survive an ephemeral CI runner and already
risked divergent state between whoever applied it manually."
```

---

### Task 5: Add CI/CD workflow to tech-challenge-lambda-functions

**Depends on:** Task 4 (backend must be in place before this workflow's `terraform apply` can run safely in CI).

**Files:**
- Create: `/Applications/XAMPP/xamppfiles/htdocs/pos/tech-challenge-lambda-functions/.github/workflows/deploy.yml`

**Interfaces:**
- Consumes: `repository_dispatch` event type `deploy-lambda-auth` (produced by Task 6); repo secrets `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `TF_VAR_db_name`, `TF_VAR_db_user`, `TF_VAR_db_password`, `TF_VAR_jwt_secret` (all must be created by the user in this repo's GitHub settings — not created by this task).
- Produces: on success, the Lambda/API Gateway/Secrets Manager stack applied to AWS.

- [ ] **Step 1: Create the workflow file**

```yaml
name: Test and Deploy Lambda Auth

on:
  repository_dispatch:
    types: [deploy-lambda-auth]
  workflow_dispatch: {}

permissions:
  contents: read

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Install and test
        run: |
          cd src
          npm ci
          npm test

  deploy:
    needs: test
    runs-on: ubuntu-latest
    env:
      TF_VAR_db_name: ${{ secrets.TF_VAR_db_name }}
      TF_VAR_db_user: ${{ secrets.TF_VAR_db_user }}
      TF_VAR_db_password: ${{ secrets.TF_VAR_db_password }}
      TF_VAR_jwt_secret: ${{ secrets.TF_VAR_jwt_secret }}
    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Configure AWS credentials
        uses: aws-actions/configure-aws-credentials@v4
        with:
          aws-access-key-id: ${{ secrets.AWS_ACCESS_KEY_ID }}
          aws-secret-access-key: ${{ secrets.AWS_SECRET_ACCESS_KEY }}
          aws-region: us-east-1

      - name: Install Lambda dependencies
        run: |
          cd src
          npm ci

      - name: Setup Terraform
        uses: hashicorp/setup-terraform@v3
        with:
          terraform_version: "1.9.8"

      - name: Terraform init
        run: terraform init

      - name: Terraform apply
        run: terraform apply -auto-approve
```

- [ ] **Step 2: Validate YAML syntax**

Run (from `tech-challenge-lambda-functions/`):
```bash
yq eval . .github/workflows/deploy.yml > /dev/null && echo "valid"
```
Expected: `valid`

- [ ] **Step 3: Commit**

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/pos/tech-challenge-lambda-functions
mkdir -p .github/workflows
git add .github/workflows/deploy.yml
git commit -m "feat: add test+deploy CI/CD workflow, triggered by repository_dispatch"
```

---

### Task 6: Trigger the lambda's CI/CD from tech-challenge-application

**Depends on:** Task 5 (the target workflow must already accept `repository_dispatch`).

**Files:**
- Modify: `/Applications/XAMPP/xamppfiles/htdocs/pos/tech-challenge-application/.github/workflows/deploy-minikube.yml`

**Interfaces:**
- Produces: fires `repository_dispatch` event type `deploy-lambda-auth` against `edunsantos/tech-challenge-lambda-functions`.
- Consumes: repo secret `LAMBDA_REPO_TOKEN` (PAT with `repo` scope on `tech-challenge-lambda-functions`; must be created by the user in this repo's GitHub settings — not created by this task).

- [ ] **Step 1: Add the dispatch step**

Add a new step at the end of the `deploy` job in `deploy-minikube.yml`, after the existing "Show access URLs" step:

```yaml
      - name: Trigger tech-challenge-lambda-functions CI/CD
        uses: peter-evans/repository-dispatch@v3
        with:
          token: ${{ secrets.LAMBDA_REPO_TOKEN }}
          repository: edunsantos/tech-challenge-lambda-functions
          event-type: deploy-lambda-auth
          client-payload: |
            {
              "sha": "${{ github.sha }}",
              "ref": "${{ github.ref }}"
            }
```

- [ ] **Step 2: Validate YAML syntax**

Run (from `tech-challenge-application/`):
```bash
yq eval . .github/workflows/deploy-minikube.yml > /dev/null && echo "valid"
```
Expected: `valid`

- [ ] **Step 3: Commit**

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/pos/tech-challenge-application
git add .github/workflows/deploy-minikube.yml
git commit -m "feat: trigger tech-challenge-lambda-functions CI/CD after minikube deploy

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01WsumeQ9V982puSvtvzH27p"
```

---

## Known follow-up (out of scope for this plan)

`tech-challenge-kubernetes` has `terraform.tfstate` and `terraform.tfstate.backup` tracked in git (confirmed via `git ls-files`), a plain-text secret exposure risk. Deferred per explicit user decision during brainstorming — not a task in this plan.
