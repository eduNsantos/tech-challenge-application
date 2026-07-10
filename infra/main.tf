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
  version = "~> 21.0"

  name               = local.name
  kubernetes_version = var.cluster_version

  endpoint_public_access                   = true
  enable_cluster_creator_admin_permissions = true
  enabled_log_types                        = []
  create_cloudwatch_log_group              = false

  vpc_id     = module.vpc.vpc_id
  subnet_ids = module.vpc.private_subnets

  eks_managed_node_groups = {
    default = {
      ami_type       = "AL2023_x86_64_STANDARD"
      instance_types                 = var.node_instance_types
      disk_size                      = 30
      min_size                       = var.node_group_min_size
      max_size                       = var.node_group_max_size
      desired_size                   = var.node_group_desired_size
      capacity_type                  = "ON_DEMAND"
    }
  }

  addons = {
    coredns            = {}
    kube-proxy         = {}
    vpc-cni            = {}
    aws-ebs-csi-driver = {}
  }

  tags = local.common_tags
}

resource "aws_db_subnet_group" "mysql" {
  count = var.create_rds_mysql ? 1 : 0

  name       = "${local.name}-mysql-subnets"
  subnet_ids = module.vpc.private_subnets

  tags = merge(local.common_tags, {
    Name = "${local.name}-mysql-subnets"
  })
}

resource "aws_security_group" "rds_mysql" {
  count = var.create_rds_mysql ? 1 : 0

  name        = "${local.name}-rds-mysql"
  description = "Allow MySQL access from EKS nodes"
  vpc_id      = module.vpc.vpc_id

  ingress {
    description     = "MySQL from EKS nodes"
    from_port       = 3306
    to_port         = 3306
    protocol        = "tcp"
    security_groups = [module.eks.node_security_group_id]
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }

  tags = merge(local.common_tags, {
    Name = "${local.name}-rds-mysql"
  })
}

resource "aws_db_instance" "mysql" {
  count = var.create_rds_mysql ? 1 : 0

  identifier        = "${local.name}-mysql"
  engine            = "mysql"
  engine_version    = var.rds_engine_version
  instance_class    = var.rds_instance_class
  allocated_storage = var.rds_allocated_storage
  storage_type      = "gp3"
  storage_encrypted = true

  db_name  = var.rds_db_name
  username = var.rds_username
  password = var.mysql_password
  port     = 3306

  db_subnet_group_name   = aws_db_subnet_group.mysql[0].name
  vpc_security_group_ids = [aws_security_group.rds_mysql[0].id]

  publicly_accessible     = false
  multi_az                = false
  backup_retention_period = var.rds_backup_retention_days
  deletion_protection     = var.rds_deletion_protection
  skip_final_snapshot     = var.rds_skip_final_snapshot

  tags = merge(local.common_tags, {
    Name = "${local.name}-mysql"
  })
}

data "aws_eks_cluster" "this" {
  count      = var.deploy_base_resources ? 1 : 0
  name       = module.eks.cluster_name
  depends_on = [module.eks]
}

data "aws_eks_cluster_auth" "this" {
  count      = var.deploy_base_resources ? 1 : 0
  name       = module.eks.cluster_name
  depends_on = [module.eks]
}

resource "kubernetes_manifest" "namespace" {
  count      = var.deploy_base_resources ? 1 : 0
  manifest   = yamldecode(file("${path.module}/../k8s/00-namespaces/namespace.yaml"))
  depends_on = [module.eks]
}

resource "kubernetes_manifest" "app_config" {
  count      = var.deploy_base_resources ? 1 : 0
  manifest   = yamldecode(file("${path.module}/../k8s/01-config/configmap.yaml"))
  depends_on = [kubernetes_manifest.namespace]
}

resource "kubernetes_manifest" "openapi_config" {
  count      = var.deploy_base_resources ? 1 : 0
  manifest   = yamldecode(file("${path.module}/../k8s/01-config/openapi-configmap.yaml"))
  depends_on = [kubernetes_manifest.namespace]
}


resource "helm_release" "metrics_server" {
  count      = var.deploy_base_resources ? 1 : 0
  name       = "metrics-server"
  repository = "https://kubernetes-sigs.github.io/metrics-server/"
  chart      = "metrics-server"
  version    = var.metrics_server_chart_version
  namespace  = "kube-system"

  depends_on = [module.eks]
}