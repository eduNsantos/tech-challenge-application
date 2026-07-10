variable "aws_region" {
  description = "Região da AWS"
  type        = string
  default     = "us-east-1"
}

variable "project_name" {
  description = "Nome do projeto para prefixo de recursos"
  type        = string
}

variable "environment" {
  description = "Ambiente (dev, hml, prod)"
  type        = string
}

variable "vpc_cidr" {
  description = "CIDR base da VPC"
  type        = string
  default     = "10.0.0.0/16"
}

variable "cluster_version" {
  description = "Versao do Kubernetes no EKS"
  type        = string
  default     = "1.30"
}

variable "node_instance_types" {
  description = "Tipos de instancia dos nodes"
  type        = list(string)
  default     = ["t3.medium"]
}

variable "node_group_min_size" {
  description = "Quantidade minima de nodes"
  type        = number
  default     = 1
}

variable "node_group_max_size" {
  description = "Quantidade maxima de nodes"
  type        = number
  default     = 2
}

variable "node_group_desired_size" {
  description = "Quantidade desejada de nodes"
  type        = number
  default     = 1
}

variable "deploy_base_resources" {
  description = "Se true, aplica namespace, banco de dados e metrics-server no cluster existente"
  type        = bool
  default     = false
}

variable "deploy_k8s_mysql" {
  description = "Se true, aplica MySQL dentro do Kubernetes"
  type        = bool
  default     = false
}

variable "create_rds_mysql" {
  description = "Se true, cria instancia MySQL no Amazon RDS"
  type        = bool
  default     = true
}

variable "rds_instance_class" {
  description = "Classe da instancia RDS MySQL"
  type        = string
  default     = "db.t3.micro"
}

variable "rds_engine_version" {
  description = "Versao do engine MySQL no RDS"
  type        = string
  default     = "8.0.36"
}

variable "rds_allocated_storage" {
  description = "Tamanho inicial do storage do RDS (GiB)"
  type        = number
  default     = 20
}

variable "rds_backup_retention_days" {
  description = "Retencao de backup automatico em dias"
  type        = number
  default     = 7
}

variable "rds_skip_final_snapshot" {
  description = "Pula snapshot final ao destruir o RDS"
  type        = bool
  default     = true
}

variable "rds_deletion_protection" {
  description = "Protecao contra exclusao da instancia RDS"
  type        = bool
  default     = false
}

variable "rds_db_name" {
  description = "Nome do banco inicial no RDS"
  type        = string
  default     = "techchallenge"
}

variable "rds_username" {
  description = "Usuario administrador do MySQL no RDS"
  type        = string
  default     = "techchallenge"
}

variable "mysql_password" {
  description = "Senha do usuario do banco MySQL"
  type        = string
  sensitive   = true
}

variable "metrics_server_chart_version" {
  description = "Versao do chart do metrics-server"
  type        = string
  default     = "3.12.2"
}

variable "tags" {
  description = "Tags adicionais para recursos AWS"
  type        = map(string)
  default     = {}
}