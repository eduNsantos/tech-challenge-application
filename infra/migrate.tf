resource "kubernetes_job_v1" "migrate" {
  metadata {
    name      = "app-migrate-${var.image_tag}"
    namespace = kubernetes_namespace_v1.postech.metadata[0].name
    labels = {
      app       = "postech-app"
      component = "migrate"
    }
  }

  spec {
    backoff_limit             = 3
    ttl_seconds_after_finished = 300

    template {
      metadata {
        labels = {
          app       = "postech-app"
          component = "migrate"
        }
      }

      spec {
        restart_policy = "OnFailure"

        image_pull_secrets {
          name = kubernetes_secret_v1.ghcr_secret.metadata[0].name
        }

        init_container {
          name    = "wait-for-mysql"
          image   = "busybox:1.36"
          command = ["sh", "-c", <<-EOT
            until nc -z mysql 3306; do
              echo "aguardando MySQL...";
              sleep 3;
            done;
            echo "MySQL disponivel."
          EOT
          ]
        }

        container {
          name              = "migrate"
          image             = "${var.image_repository}:${var.image_tag}"
          image_pull_policy = "Always"
          command           = ["php", "artisan", "migrate", "--force"]

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

          resources {
            requests = {
              cpu    = "100m"
              memory = "256Mi"
            }
            limits = {
              cpu    = "200m"
              memory = "512Mi"
            }
          }
        }
      }
    }
  }

  wait_for_completion = true

  timeouts {
    create = "4m"
  }

  depends_on = [kubernetes_deployment_v1.mysql, kubernetes_service_v1.mysql]
}
