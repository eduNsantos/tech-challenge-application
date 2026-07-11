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
