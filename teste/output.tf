output "vpc_id" {
  value = aws_vpc.postech.id
}

output "subnet_a_id" {
  value = aws_subnet.subnet-a.id
}

output "subnet_b_id" {
  value = aws_subnet.subnet-a.id
}

output "eks_cluster_id" {
  value = aws_eks_cluster.main.id
}