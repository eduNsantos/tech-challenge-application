terraform {
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 6.0"
    }
  }
}

provider "aws" {
  region = var.aws_region
}

# Create a VPC
resource "aws_vpc" "postech" {
  cidr_block = "10.0.0.0/16"

  tags = {
    Name = "postech"
  }
}

resource "aws_subnet" "subnet-a" {
  vpc_id = aws_vpc.postech.id
  cidr_block = "10.0.1.0/24"
  availability_zone  = "us-east-2a"

  tags = {
    Name = "Subnet A"
  }
}


resource "aws_subnet" "subnet-b" {
  vpc_id = aws_vpc.postech.id
  cidr_block = "10.0.2.0/24"
  availability_zone  = "us-east-2b"

  tags = {
    Name = "Subnet B"
  }
}