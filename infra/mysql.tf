resource "kubernetes_persistent_volume_v1" "mysql" {
  metadata {
    name = "mysql-pv"
  }

  spec {
    capacity = {
      storage = "5Gi"
    }
    access_modes                     = ["ReadWriteOnce"]
    persistent_volume_reclaim_policy = "Retain"
    storage_class_name               = "standard"

    persistent_volume_source {
      host_path {
        path = "/tmp/postech-mysql"
        type = "DirectoryOrCreate"
      }
    }
  }
}

resource "kubernetes_persistent_volume_claim_v1" "mysql" {
  metadata {
    name      = "mysql-pvc"
    namespace = kubernetes_namespace_v1.postech.metadata[0].name
  }

  spec {
    access_modes = ["ReadWriteOnce"]

    resources {
      requests = {
        storage = "5Gi"
      }
    }

    volume_name = kubernetes_persistent_volume_v1.mysql.metadata[0].name
  }
}

resource "kubernetes_deployment_v1" "mysql" {
  metadata {
    name      = "mysql"
    namespace = kubernetes_namespace_v1.postech.metadata[0].name
    labels    = { app = "mysql" }
  }

  spec {
    replicas = 1

    selector {
      match_labels = { app = "mysql" }
    }

    strategy {
      type = "Recreate"
    }

    template {
      metadata {
        labels = { app = "mysql" }
      }

      spec {
        container {
          name  = "mysql"
          image = "mysql:8.0"

          port {
            container_port = 3306
          }

          env {
            name  = "MYSQL_ROOT_PASSWORD"
            value = var.mysql_root_password
          }

          env {
            name = "MYSQL_DATABASE"
            value_from {
              config_map_key_ref {
                name = kubernetes_config_map_v1.app_config.metadata[0].name
                key  = "DB_DATABASE"
              }
            }
          }

          env {
            name = "MYSQL_USER"
            value_from {
              config_map_key_ref {
                name = kubernetes_config_map_v1.app_config.metadata[0].name
                key  = "DB_USERNAME"
              }
            }
          }

          env {
            name = "MYSQL_PASSWORD"
            value_from {
              secret_key_ref {
                name = kubernetes_secret_v1.app_secret.metadata[0].name
                key  = "DB_PASSWORD"
              }
            }
          }

          volume_mount {
            name       = "mysql-data"
            mount_path = "/var/lib/mysql"
          }

          liveness_probe {
            exec {
              command = ["mysqladmin", "ping", "-h", "localhost"]
            }
            initial_delay_seconds = 30
            period_seconds         = 10
            timeout_seconds        = 5
          }

          readiness_probe {
            exec {
              command = ["mysqladmin", "ping", "-h", "localhost"]
            }
            initial_delay_seconds = 15
            period_seconds         = 5
            timeout_seconds        = 3
          }

          resources {
            requests = {
              cpu    = "250m"
              memory = "512Mi"
            }
            limits = {
              cpu    = "500m"
              memory = "1Gi"
            }
          }
        }

        volume {
          name = "mysql-data"
          persistent_volume_claim {
            claim_name = kubernetes_persistent_volume_claim_v1.mysql.metadata[0].name
          }
        }
      }
    }
  }
}

resource "kubernetes_service_v1" "mysql" {
  metadata {
    name      = "mysql"
    namespace = kubernetes_namespace_v1.postech.metadata[0].name
    labels    = { app = "mysql" }
  }

  spec {
    type     = "ClusterIP"
    selector = { app = "mysql" }

    port {
      port        = 3306
      target_port = 3306
      protocol    = "TCP"
    }
  }
}
