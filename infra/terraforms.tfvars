project_name = "tech-challenge"
environment  = "dev"
aws_region   = "us-east-1"

cluster_version         = "1.30"
node_instance_types     = ["t3.small"]
node_group_min_size     = 1
node_group_max_size     = 2
node_group_desired_size = 1
apply_k8s_manifests     = false