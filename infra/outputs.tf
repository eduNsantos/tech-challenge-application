output "metrics_server_release_name" {
  description = "Nome do release Helm do metrics-server"
  value       = var.deploy_metrics_server ? helm_release.metrics_server[0].name : null
}

output "metrics_server_status" {
  description = "Status do release Helm do metrics-server"
  value       = var.deploy_metrics_server ? helm_release.metrics_server[0].status : null
}

output "kube_context_used" {
  description = "Context do kubeconfig usado por este apply"
  value       = var.kube_context
}
