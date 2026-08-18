# Pendências — migração do banco para a RDS do `tech-challenge-database`

Este documento lista o que ainda falta para o ambiente Kubernetes (Minikube,
`infra/` + `.github/workflows/deploy-minikube.yml`) ficar de fato apontando
para a RDS compartilhada, usada também pelo Lambda de autenticação
(`tech-challenge-lambda-functions`). O código deste repositório já foi
ajustado (branch `chore/point-rds-repo-db`) para consumir `DB_HOST` /
`DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` como variáveis do
Terraform — o que falta abaixo é configuração manual e coordenação entre
repositórios, não código.

## 1. `tech-challenge-database` — aplicar a RDS

- [ ] Rodar `terraform apply` no repositório `tech-challenge-database` para
      criar de fato a instância `aws_db_instance.rds` (identifier
      `tech-challenge-db`). Hoje só existe o `.tf` — a instância ainda não
      existe na AWS.
- [ ] Depois do apply, obter `address`/`endpoint`/`port` da instância (ex.:
      `aws rds describe-db-instances --db-instance-identifier tech-challenge-db`,
      já que `output.tf` daquele repositório só expõe `database_instance_id`,
      não o endpoint).

## 2. Segredos do GitHub Actions (`tech-challenge-application`)

Nenhum destes valores é versionado em nenhum dos dois repositórios (ficam em
`.tfvars`, sempre no `.gitignore`). Cadastrar manualmente em **Settings →
Secrets and variables → Actions** deste repositório, assim que os valores do
passo 1 existirem:

- [ ] `DB_HOST` — endpoint da RDS obtido no passo 1.
- [ ] `DB_DATABASE` — mesmo valor de `db_name` usado no `tech-challenge-database`.
- [ ] `DB_USERNAME` — mesmo valor de `db_user` usado no `tech-challenge-database`.
- [ ] `DB_PASSWORD` — mesmo valor de `db_password` usado no `tech-challenge-database`
      (é a mesma credencial que o Lambda de autenticação vai usar para
      consultar a tabela `users`).

`DB_PORT` não precisa de secret — tem default `3306` em `infra/variables.tf`,
só sobrescrever se a RDS usar outra porta.

## 3. Conectividade de rede (cross-repo, fora do código deste repositório)

A RDS é criada com `publicly_accessible = false`. O runner self-hosted que
executa o `deploy-minikube.yml` (e, portanto, os pods do Minikube) precisa
alcançar a porta 3306 dela. Isso depende do lado do `tech-challenge-database`:

- [ ] Confirmar que o runner self-hosted está na mesma VPC (ou tem rota/peering)
      da VPC `main` onde a RDS é criada.
- [ ] Confirmar que o Security Group `rds` libera ingress na porta 3306 para a
      origem do runner. Hoje `rds.tf` já libera todo o CIDR da VPC via
      `aws_vpc_security_group_ingress_rule.rds_mysql_from_vpc` (regra
      temporária) — validar se isso é suficiente ou se precisa de uma regra
      específica para o runner/Minikube.
- [ ] Esta mesma checagem de rede é necessária para o Lambda de autenticão
      (já pedida separadamente em
      `tech-challenge-lambda-functions/REQUEST-TO-DATABASE-REPO.md`) — os dois
      pedidos podem ser resolvidos juntos.

## 4. Primeira execução do pipeline apontando pra RDS

- [ ] Depois dos passos 1–3, rodar (ou deixar o push em `main` disparar)
      `deploy-minikube.yml` e confirmar nos logs que o Job de migration
      (`kubernetes_job_v1.migrate`, comando `php artisan migrate --force`)
      terminou com sucesso contra a RDS — isso é o que cria `users` e as
      demais tabelas lá.
- [ ] Validar manualmente (`SELECT 1 FROM users LIMIT 1` via `mysql` client,
      ou um `GET /auth/me` autenticado) que a aplicação está lendo/gravando
      na RDS, e não em um dos bancos antigos.
- [ ] Confirmar com o time do Lambda que ele consegue autenticar contra os
      mesmos usuários depois da migration rodar.

## 5. Desenvolvimento local — sem mudança

`docker-compose.yml` (MySQL local, porta 3308) continua usando o container
`db` — não foi alterado e não precisa apontar para a RDS. O escopo desta
migração é só o ambiente de deploy via Minikube/Terraform
(`infra/` + `deploy-minikube.yml`), que é o que hoje se aproxima de um
ambiente de homologação/produção.
