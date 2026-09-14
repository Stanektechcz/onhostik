# OpenTofu root module for the ONhost control plane (blueprint §32). Provider-agnostic skeleton: the module
# interfaces are fixed, the backing modules are selected per environment (var.platform = "hetzner" | "onprem").
terraform {
  required_version = ">= 1.8"
  backend "s3" {}   # state in the ONhost object storage bucket; configured per environment via -backend-config
}

variable "environment" { type = string }                       # dev | staging | production
variable "platform"    { type = string  default = "onprem" }
variable "region"      { type = string  default = "cz1" }
variable "app_image"   { type = string }                       # registry.onhost.internal/onhost/app:<tag>
variable "relay_image" { type = string }
variable "domain"      { type = string  default = "onhost.cz" }

module "database" {
  source      = "./modules/postgres"
  environment = var.environment
  ha          = var.environment == "production"
  version     = "16"
  backups     = { retention_days = 35, pitr = true }
}

module "cache" {
  source      = "./modules/redis"
  environment = var.environment
  ha          = var.environment == "production"
}

module "object_storage" {
  source  = "./modules/object-storage"
  buckets = ["onhost-exports", "onhost-invoices", "onhost-backups-meta"]
  lock    = { "onhost-invoices" = "compliance" }   # WORM: issued documents are immutable
}

module "control_plane" {
  source        = "./modules/app"
  environment   = var.environment
  image         = var.app_image
  relay_image   = var.relay_image
  replicas      = var.environment == "production" ? 3 : 1
  database_url  = module.database.url
  redis_url     = module.cache.url
  secrets_ref   = "env://"
  env = {
    APP_URL                  = "https://${var.environment == "production" ? "" : "${var.environment}."}${var.domain}"
    ONHOST_PORTAL_URL        = "https://${var.environment == "production" ? "" : "${var.environment}."}${var.domain}"
    ONHOST_UI_DEMO           = "false"
    QUEUE_CONNECTION         = "redis"
    CACHE_STORE              = "redis"
    SESSION_DRIVER           = "redis"
    ONHOST_WHITELABEL_CNAME  = "panel.${var.domain}"
  }
}

module "dns" {
  source  = "./modules/dns"
  zone    = var.domain
  records = {
    "@"      = { type = "A",     value = module.control_plane.lb_ipv4 }
    "@"      = { type = "AAAA",  value = module.control_plane.lb_ipv6 }
    "panel"  = { type = "CNAME", value = "${var.domain}." }
    "status" = { type = "CNAME", value = "${var.domain}." }
    "relay"  = { type = "A",     value = module.control_plane.relay_ipv4 }
  }
}

output "app_url"      { value = module.control_plane.url }
output "database_url" { value = module.database.url  sensitive = true }
