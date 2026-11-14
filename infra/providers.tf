terraform {
  required_version = ">= 1.10.0"

  backend "s3" {
    bucket       = "techchallenge-tfstate-477478162709-eks"
    key          = "application/terraform.tfstate"
    region       = "us-east-1"
    use_lockfile = true
  }

  required_providers {
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

provider "kubernetes" {
  config_path    = var.kubeconfig_path
  config_context = var.kube_context
}

provider "helm" {
  kubernetes {
    config_path    = var.kubeconfig_path
    config_context = var.kube_context
  }
}
