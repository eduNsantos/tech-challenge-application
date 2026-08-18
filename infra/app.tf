resource "kubernetes_deployment_v1" "app" {
  metadata {
    name      = "postech-app"
    namespace = kubernetes_namespace_v1.postech.metadata[0].name
    labels    = { app = "postech-app" }
  }

  spec {
    replicas = 2

    selector {
      match_labels = { app = "postech-app" }
    }

    template {
      metadata {
        labels = { app = "postech-app" }
        annotations = {
          "postech.dev/rollout-id" = var.rollout_id
        }
      }

      spec {
        image_pull_secrets {
          name = kubernetes_secret_v1.ghcr_secret.metadata[0].name
        }

        container {
          name              = "app"
          image             = "${var.image_repository}:${var.image_tag}"
          image_pull_policy = "Always"
          command           = ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

          port {
            container_port = 8000
          }

          env_from {
            config_map_ref {
              name = kubernetes_config_map_v1.app_config.metadata[0].name
            }
          }
          env_from {
            secret_ref {
              name = kubernetes_secret_v1.app_secret.metadata[0].name
            }
          }

          liveness_probe {
            http_get {
              path = "/up"
              port = 8000
            }
            initial_delay_seconds = 30
            period_seconds         = 15
            timeout_seconds        = 5
            failure_threshold      = 3
          }

          readiness_probe {
            http_get {
              path = "/up"
              port = 8000
            }
            initial_delay_seconds = 15
            period_seconds         = 10
            timeout_seconds        = 3
            failure_threshold      = 3
          }

          resources {
            requests = {
              cpu    = "250m"
              memory = "256Mi"
            }
            limits = {
              cpu    = "500m"
              memory = "512Mi"
            }
          }
        }
      }
    }
  }

  # Garante que a app só sobe depois que as migrations daquela mesma tag
  # rodaram com sucesso.
  depends_on = [kubernetes_job_v1.migrate]
}

resource "kubernetes_service_v1" "app" {
  metadata {
    name      = "postech-app"
    namespace = kubernetes_namespace_v1.postech.metadata[0].name
    labels    = { app = "postech-app" }
  }

  # No Minikube, um Service LoadBalancer só recebe IP externo enquanto o
  # 'minikube tunnel' está rodando à parte. Sem isso, o Terraform ficaria
  # preso esperando um IP que nunca chega, até estourar o timeout de criação.
  wait_for_load_balancer = false

  spec {
    type     = "LoadBalancer"
    selector = { app = "postech-app" }

    port {
      name        = "http"
      port        = 8080
      target_port = 8000
      protocol    = "TCP"
    }
  }
}

resource "kubernetes_horizontal_pod_autoscaler_v2" "app" {
  metadata {
    name      = "postech-app-hpa"
    namespace = kubernetes_namespace_v1.postech.metadata[0].name
  }

  spec {
    scale_target_ref {
      api_version = "apps/v1"
      kind        = "Deployment"
      name        = kubernetes_deployment_v1.app.metadata[0].name
    }

    min_replicas = 2
    max_replicas = 10

    metric {
      type = "Resource"
      resource {
        name = "cpu"
        target {
          type                = "Utilization"
          average_utilization = 70
        }
      }
    }

    metric {
      type = "Resource"
      resource {
        name = "memory"
        target {
          type                = "Utilization"
          average_utilization = 80
        }
      }
    }

    behavior {
      scale_up {
        stabilization_window_seconds = 60
        select_policy = "Max"
        policy {
          type           = "Pods"
          value          = 2
          period_seconds = 60
        }
      }
      scale_down {
        stabilization_window_seconds = 300
        select_policy = "Max"
        policy {
          type           = "Pods"
          value          = 1
          period_seconds = 120
        }
      }
    }
  }

  depends_on = [helm_release.metrics_server]
}
