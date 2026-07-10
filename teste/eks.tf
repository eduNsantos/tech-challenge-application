resource "aws_eks_cluster" "main" {
  name = "main"

  access_config {
    authentication_mode = "API"
  }

  vpc_config {
    subnet_ids = [
      aws_subnet.subnet-a.id,
      aws_subnet.subnet-b.id
    ]
  }

  role_arn = aws_iam_role.cluster.arn
  version  = "1.35"

  depends_on = [
    aws_iam_role_policy_attachment.cluster_AmazonEKSClusterPolicy,
  ]
}

resource "aws_iam_role" "cluster" {
  name = "eks-cluster-example"
  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Action = [
          "sts:AssumeRole",
          "sts:TagSession"
        ]
        Effect = "Allow"
        Principal = {
          Service = "eks.amazonaws.com"
        }
      },
    ]
  })
}

resource "aws_iam_role_policy_attachment" "cluster_AmazonEKSClusterPolicy" {
  policy_arn = "arn:aws:iam::aws:policy/AmazonEKSClusterPolicy"
  role       = aws_iam_role.cluster.name
}