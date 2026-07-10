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

variable "apply_k8s_manifests" {
  description = "Aplica os manifests em k8s/ automaticamente apos criar o cluster"
  type        = bool
  default     = false
}

variable "tags" {
  description = "Tags adicionais para recursos AWS"
  type        = map(string)
  default     = {}
}