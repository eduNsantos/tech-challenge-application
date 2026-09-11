# Cross-repo CI/CD trigger + shared RDS wiring — design

## Contexto

O sistema POS é composto por 4 repositórios:

- `tech-challenge-database` — Terraform: VPC + RDS MySQL (`identifier = "techchallenge-rds"`).
- `tech-challenge-lambda-functions` — Terraform + Lambda Node.js 24: login serverless (CPF/e-mail), lê a RDS do repo `database` via `data source`.
- `tech-challenge-kubernetes` — Terraform (EKS) + manifests k8s + `.github/workflows/deploy-eks.yml`. É o alvo de deploy de **produção real**, acionado hoje via `repository_dispatch` disparado por `build-ghcr.yml` deste repositório.
- `tech-challenge-application` (este repo) — Laravel 13 DDD. `infra/` local gerencia um deploy **Minikube** self-hosted, com seu próprio MySQL em cluster — ambiente isolado, não relacionado à RDS compartilhada.

Este spec cobre dois pontos de um plano maior de integração entre esses repositórios (memória `cicd-cross-repo-integration` no sistema de memória do assistente).

## Ponto 1 — Disparo de CI/CD do Lambda após o pipeline da aplicação

**Objetivo:** depois que `tech-challenge-application/.github/workflows/deploy-minikube.yml` concluir com sucesso, disparar o CI/CD (novo) do `tech-challenge-lambda-functions`.

### Componentes

**`tech-challenge-application`**
- `deploy-minikube.yml`: novo step final no job `deploy`, após "Show access URLs", usando `peter-evans/repository-dispatch@v3` → repo `edunsantos/tech-challenge-lambda-functions`, `event-type: deploy-lambda-auth`, payload `{sha, ref}`.
- Novo secret `LAMBDA_REPO_TOKEN` (PAT com escopo `repo`), mesmo padrão do `K8S_REPO_TOKEN` já usado em `build-ghcr.yml`.

**`tech-challenge-lambda-functions`**
- Novo `.github/workflows/deploy.yml`: trigger `repository_dispatch` (`deploy-lambda-auth`) + `workflow_dispatch` manual.
  - Job `test`: `cd src && npm ci && npm test`.
  - Job `deploy` (needs: test): `cd src && npm ci`; `terraform init`; `terraform apply -auto-approve` com `-var` alimentados por secrets do repo (`db_name`, `db_user`, `db_password`, `jwt_secret`); credenciais AWS via `aws-actions/configure-aws-credentials@v4`.
- `providers.tf`: adicionar bloco `backend "s3" { bucket, key, region, dynamodb_table }`.
- Novos secrets: `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `TF_VAR_db_name`, `TF_VAR_db_user`, `TF_VAR_db_password`, `TF_VAR_jwt_secret`.

### Backend remoto do Terraform (bootstrap único, fora dos repos)

Nem `tech-challenge-database` nem `tech-challenge-lambda-functions` têm backend remoto — hoje o state é local, aplicado manualmente por quem estiver mexendo (já arriscado hoje, e inviável em runner efêmero de CI).

- Criar **uma vez**, fora do Terraform principal (script/CLI, não gerenciado por este mesmo state): bucket S3 versionado + criptografado (guarda o `.tfstate`), e uma tabela DynamoDB com chave primária `LockID` (string), billing pay-per-request — usada só como mutex distribuído de lock, sem outros atributos.
- Referenciar esse backend no `providers.tf` do `tech-challenge-lambda-functions` (`terraform init -migrate-state` se já houver state local aplicado).
- A mesma tabela/bucket pode ser reaproveitada futuramente pelo `tech-challenge-database`, que tem o mesmo risco de state local.

**Nomes concretos e execução:** este bootstrap precisa de credenciais AWS reais e cria recursos de custo real — **o assistente não executa isso**, o usuário roda uma vez, manualmente:

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

Bloco a adicionar em `tech-challenge-lambda-functions/providers.tf`:

```hcl
terraform {
  backend "s3" {
    bucket         = "techchallenge-tfstate"
    key            = "lambda-auth/terraform.tfstate"
    region         = "us-east-1"
    dynamodb_table = "techchallenge-tf-locks"
    encrypt        = true
  }
}
```

### Testes / validação
- Job `test` roda antes do `deploy` — falha nos testes impede o `apply`.
- Sem rollback automático no `apply` (mesmo comportamento manual já documentado no README do Lambda).

## Ponto 2 (crucial) — Fechar o compartilhamento da RDS entre app e Lambda

### O que já está correto (não mexer)
`tech-challenge-kubernetes/k8s/01-config/configmap.yaml` já aponta `DB_HOST` para a RDS real (`techchallenge-rds.ciryooyoo4xd.us-east-1.rds.amazonaws.com`), com `DB_USERNAME=root` e `DB_DATABASE=techchallenge`.

### Gaps confirmados

1. **`deploy-eks.yml` nunca aplica/roda `k8s/02-app/migrate-job.yaml`.** O workflow atual (`main`, já inclui o commit `358adf78` / "adicionado step de rollout") só faz `kubectl set image` + `kubectl rollout restart` + `kubectl rollout status` — confirmado via `grep -i migrate .github/workflows/deploy-eks.yml` (nenhum resultado). O rollout restart recria os pods com a imagem nova, mas não roda `php artisan migrate`. Ou seja, a tabela `users` (e demais) provavelmente não existe na RDS compartilhada, o que bloqueia o login do Lambda.
2. **`tech-challenge-lambda-functions/variables.tf`**: `db_identifier` tem default `"tech-challenge-db"`, mas o identifier real (confirmado em `tech-challenge-database/rds.tf` e no endpoint usado no configmap acima) é `"techchallenge-rds"`. Sem sobrescrever essa variável, `data "aws_db_instance" "main"` falha ao localizar a instância.

### Mudanças propostas

**`tech-challenge-kubernetes/.github/workflows/deploy-eks.yml`**
- Novo step, antes do rollout restart:
  1. `kubectl delete job app-migrate -n postech --ignore-not-found` (Jobs do k8s são imutáveis — precisa apagar antes de reaplicar).
  2. Parametrizar a imagem de `k8s/02-app/migrate-job.yaml` para usar a tag resolvida (`steps.image.outputs.tag`) em vez do `:latest` fixo hoje hardcoded no manifest — evita migrar com uma imagem diferente da que está sendo deployada (usar `yq` ou `sed`, seguindo o padrão já usado em `laravel-ci.yml` deste repo).
  3. `kubectl apply -f k8s/02-app/migrate-job.yaml`
  4. `kubectl wait --for=condition=complete job/app-migrate -n postech --timeout=180s`

**`tech-challenge-lambda-functions/variables.tf`**
- Corrigir default de `db_identifier` para `"techchallenge-rds"`.

### Fora de escopo (marcado para ajuste futuro)

- `tech-challenge-kubernetes` tem `terraform.tfstate` e `terraform.tfstate.backup` **versionados no git** (confirmado via `git ls-files`), apesar do `.gitignore` já excluir `*.tfstate` para arquivos novos — risco de exposição de segredos em texto plano. Ação recomendada quando for tratado: `git rm --cached` dos dois arquivos + backend remoto (reaproveitando o bucket S3/DynamoDB deste spec) + considerar rotacionar credenciais que possam ter sido expostas no histórico. **Não será feito nesta implementação** — apenas registrado como pendência conhecida.

## Sequenciamento de implementação

1. `tech-challenge-lambda-functions`: corrigir `db_identifier` (mudança isolada, sem dependências).
2. `tech-challenge-kubernetes`: adicionar step de migrate em `deploy-eks.yml`.
3. `tech-challenge-lambda-functions`: novo workflow `deploy.yml` + backend S3/DynamoDB (depende do bootstrap manual do bucket/tabela).
4. `tech-challenge-application`: novo step de `repository_dispatch` em `deploy-minikube.yml` (depende do workflow do passo 3 já existir no repo de destino).
