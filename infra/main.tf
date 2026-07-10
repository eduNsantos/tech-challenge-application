locals {
  name = "${var.project_name}-${var.environment}"

  common_tags = merge(
    {
      Project     = var.project_name
      Environment = var.environment
      ManagedBy   = "terraform"
    },
    var.tags
  )
}

module "vpc" {
  source  = "terraform-aws-modules/vpc/aws"
  version = "~> 5.0"

  name = local.name
  cidr = var.vpc_cidr

  azs             = slice(data.aws_availability_zones.available.names, 0, 2)
  private_subnets = ["10.0.1.0/24", "10.0.2.0/24"]
  public_subnets  = ["10.0.11.0/24", "10.0.12.0/24"]

  enable_nat_gateway = true
  single_nat_gateway = true

  public_subnet_tags = {
    "kubernetes.io/role/elb" = "1"
  }

  private_subnet_tags = {
    "kubernetes.io/role/internal-elb" = "1"
  }

  tags = local.common_tags
}

module "eks" {
  source  = "terraform-aws-modules/eks/aws"
  version = "~> 20.0"

  cluster_name    = local.name
  cluster_version = var.cluster_version

  cluster_endpoint_public_access           = true
  enable_cluster_creator_admin_permissions = true

  vpc_id     = module.vpc.vpc_id
  subnet_ids = module.vpc.private_subnets

  eks_managed_node_group_defaults = {
    instance_types = var.node_instance_types
    disk_size      = 30
  }

  eks_managed_node_groups = {
    default = {
      min_size      = var.node_group_min_size
      max_size      = var.node_group_max_size
      desired_size  = var.node_group_desired_size
      capacity_type = "ON_DEMAND"
    }
  }

  cluster_addons = {
    coredns            = {}
    kube-proxy         = {}
    vpc-cni            = {}
    aws-ebs-csi-driver = {}
  }

  tags = local.common_tags
}

resource "null_resource" "deploy_k8s_manifests" {
  count = var.apply_k8s_manifests ? 1 : 0

  triggers = {
    cluster_name = module.eks.cluster_name
    manifests_sha = sha1(join("", [
      for file in fileset("${path.module}/../k8s", "**/*.yaml") : filesha1("${path.module}/../k8s/${file}")
    ]))
  }

  provisioner "local-exec" {
    interpreter = ["/bin/bash", "-c"]
    command     = <<-EOT
      set -euo pipefail

      aws eks update-kubeconfig \
        --name ${module.eks.cluster_name} \
        --region ${var.aws_region}

      kubectl apply -f ${path.module}/../k8s/00-namespaces/
      kubectl apply -f ${path.module}/../k8s/01-config/

      kubectl apply -f ${path.module}/../k8s/02-app/mysql-pvc.yaml
      kubectl apply -f ${path.module}/../k8s/02-app/mysql-deployment.yaml
      kubectl apply -f ${path.module}/../k8s/02-app/mysql-service.yaml

      kubectl apply -f ${path.module}/../k8s/02-app/app-deployment.yaml
      kubectl apply -f ${path.module}/../k8s/02-app/app-service.yaml
      kubectl apply -f ${path.module}/../k8s/02-app/app-hpa.yaml

      kubectl apply -f ${path.module}/../k8s/02-app/swagger-deployment.yaml
      kubectl apply -f ${path.module}/../k8s/02-app/swagger-service.yaml
    EOT
  }

  depends_on = [module.eks]
}