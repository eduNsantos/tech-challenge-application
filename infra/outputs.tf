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

output "namespace" {
  description = "Namespace onde a stack foi provisionada"
  value       = kubernetes_namespace_v1.postech.metadata[0].name
}

output "app_deployment_name" {
  description = "Nome do Deployment da aplicação"
  value       = kubernetes_deployment_v1.app.metadata[0].name
}

output "app_image" {
  description = "Imagem efetivamente implantada (repositório:tag)"
  value       = "${var.image_repository}:${var.image_tag}"
}

output "migrate_job_name" {
  description = "Nome do Job de migration executado neste apply"
  value       = kubernetes_job_v1.migrate.metadata[0].name
}

output "hpa_name" {
  description = "Nome do HorizontalPodAutoscaler da aplicação"
  value       = kubernetes_horizontal_pod_autoscaler_v2.app.metadata[0].name
}
