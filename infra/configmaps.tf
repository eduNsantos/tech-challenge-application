resource "kubernetes_config_map_v1" "app_config" {
  metadata {
    name      = "app-config"
    namespace = kubernetes_namespace_v1.postech.metadata[0].name
  }

  data = {
    APP_NAME               = "POS Tech"
    APP_ENV                = "production"
    APP_DEBUG               = "false"
    APP_URL                 = "http://localhost"
    APP_LOCALE              = "pt_BR"
    APP_FALLBACK_LOCALE     = "en"
    APP_FAKER_LOCALE        = "pt_BR"
    APP_MAINTENANCE_DRIVER  = "file"
    DB_CONNECTION           = "mysql"
    DB_HOST                 = var.db_host
    DB_PORT                 = var.db_port
    DB_DATABASE             = var.db_database
    DB_USERNAME             = var.db_username
    QUEUE_CONNECTION        = "database"
    CACHE_STORE             = "database"
    MAIL_MAILER             = "log"
    MAIL_HOST               = "smtp.gmail.com"
    MAIL_PORT               = "587"
    MAIL_FROM_ADDRESS       = "evdwsoat15@gmail.com"
    MAIL_FROM_NAME          = "POS Tech"
  }
}

# Lê o openapi.yaml da raiz do projeto. Ajuste o caminho caso a estrutura de
# pastas seja diferente (por padrão, assume infra/ na raiz do repo e
# openapi.yaml um nível acima, em ../openapi.yaml).
resource "kubernetes_config_map_v1" "openapi_spec" {
  metadata {
    name      = "openapi-spec"
    namespace = kubernetes_namespace_v1.postech.metadata[0].name
  }

  data = {
    "openapi.yaml" = file("${path.module}/../openapi.yaml")
  }
}
