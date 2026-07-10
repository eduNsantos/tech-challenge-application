output "vpc_id" {
  description = "ID da VPC criada"
  value       = module.vpc.vpc_id
}

output "eks_cluster_name" {
  description = "Nome do cluster EKS"
  value       = module.eks.cluster_name
}

output "eks_cluster_endpoint" {
  description = "Endpoint do API Server do EKS"
  value       = module.eks.cluster_endpoint
}

output "configure_kubectl_command" {
  description = "Comando para configurar kubectl no cluster"
  value       = "aws eks update-kubeconfig --region ${var.aws_region} --name ${module.eks.cluster_name}"
}

output "rds_mysql_endpoint" {
  description = "Endpoint do RDS MySQL"
  value       = var.create_rds_mysql ? aws_db_instance.mysql[0].address : null
}

output "rds_mysql_port" {
  description = "Porta do RDS MySQL"
  value       = var.create_rds_mysql ? aws_db_instance.mysql[0].port : null
}

output "rds_mysql_database" {
  description = "Nome do banco inicial no RDS"
  value       = var.create_rds_mysql ? aws_db_instance.mysql[0].db_name : null
}

output "rds_mysql_username" {
  description = "Usuario administrador do RDS MySQL"
  value       = var.create_rds_mysql ? aws_db_instance.mysql[0].username : null
}