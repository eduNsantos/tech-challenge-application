variable "kubeconfig_path" {
  description = "Caminho do kubeconfig local"
  type        = string
  default     = "~/.kube/config"
}

variable "kube_context" {
  description = "Context do kubeconfig a ser usado (o minikube cria/usa o context 'minikube' por padrão)"
  type        = string
  default     = "minikube"
}

variable "deploy_metrics_server" {
  description = "Se true, instala o metrics-server via Helm (necessário para o HPA funcionar)"
  type        = bool
  default     = true
}

variable "metrics_server_chart_version" {
  description = "Versao do chart do metrics-server"
  type        = string
  default     = "3.12.2"
}

variable "namespace" {
  description = "Namespace onde toda a aplicação é provisionada"
  type        = string
  default     = "postech"
}

variable "image_repository" {
  description = "Repositório da imagem da aplicação (sem a tag)"
  type        = string
  default     = "ghcr.io/viniciussalvarenga/tech-challenge"
}

variable "image_tag" {
  description = "Tag da imagem a ser implantada. Usar uma tag única por build (ex.: sha-<7 chars>) garante que o Deployment e o Job de migration sejam recriados a cada apply."
  type        = string
  default     = "latest"
}

variable "rollout_id" {
  description = "Identificador de rollout para forcar atualizacao de app/migrate mesmo com image_tag fixa (ex.: latest)."
  type        = string
  default     = ""
}

variable "app_key" {
  description = "APP_KEY do Laravel (gerado via 'php artisan key:generate --show')"
  type        = string
  sensitive   = true
}

variable "db_host" {
  description = "Host/endpoint do banco (RDS do repositório tech-challenge-database, identifier \"tech-challenge-db\"). Só existe depois que aquela RDS for aplicada por lá; até lá, não há valor válido para esta variável."
  type        = string
}

variable "db_port" {
  description = "Porta do banco de dados"
  type        = string
  default     = "3306"
}

variable "db_database" {
  description = "Nome do banco de dados. Deve ser igual ao valor de 'db_name' usado no repositório tech-challenge-database."
  type        = string
}

variable "db_username" {
  description = "Usuário do banco de dados. Deve ser igual ao valor de 'db_user' usado no repositório tech-challenge-database."
  type        = string
}

variable "db_password" {
  description = "Senha do usuário de banco da aplicação (DB_PASSWORD). Deve ser igual ao valor de 'db_password' usado no repositório tech-challenge-database — é o mesmo usuário/senha que o Lambda de autenticação (tech-challenge-lambda-functions) também vai usar para consultar a RDS."
  type        = string
  sensitive   = true
}

variable "jwt_secret" {
  description = "Segredo usado para assinar os JWTs (gerado via 'php artisan jwt:secret --show')"
  type        = string
  sensitive   = true
}

variable "mail_username" {
  description = "Usuário SMTP para envio de e-mails"
  type        = string
  sensitive   = true
  default     = ""
}

variable "mail_password" {
  description = "Senha/app-password SMTP para envio de e-mails"
  type        = string
  sensitive   = true
  default     = ""
}

variable "ghcr_username" {
  description = "Usuário do GitHub Container Registry usado para pull da imagem"
  type        = string
  sensitive   = true
}

variable "ghcr_token" {
  description = "Token (PAT) com escopo read:packages para pull da imagem no GHCR"
  type        = string
  sensitive   = true
}

variable "ghcr_email" {
  description = "E-mail associado ao token do GHCR (obrigatório pelo formato dockerconfigjson, não é validado)"
  type        = string
  default     = "deploy@example.com"
}
