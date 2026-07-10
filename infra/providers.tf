terraform {
  required_version = ">= 1.6.0"

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 6.0"
    }
    kubernetes = {
      source  = "hashicorp/kubernetes"
      version = "~> 2.31"
    }
    helm = {
      source  = "hashicorp/helm"
      version = "~> 2.15"
    }
  }
}

provider "aws" {
  region = var.aws_region
}

data "aws_availability_zones" "available" {
  state = "available"
}

provider "kubernetes" {
  host                   = var.deploy_base_resources ? data.aws_eks_cluster.this[0].endpoint : null
  cluster_ca_certificate = var.deploy_base_resources ? base64decode(data.aws_eks_cluster.this[0].certificate_authority[0].data) : null
  token                  = var.deploy_base_resources ? data.aws_eks_cluster_auth.this[0].token : null
}

provider "helm" {
  kubernetes {
    host                   = var.deploy_base_resources ? data.aws_eks_cluster.this[0].endpoint : null
    cluster_ca_certificate = var.deploy_base_resources ? base64decode(data.aws_eks_cluster.this[0].certificate_authority[0].data) : null
    token                  = var.deploy_base_resources ? data.aws_eks_cluster_auth.this[0].token : null
  }
}