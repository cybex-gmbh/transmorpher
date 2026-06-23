variable "CACHE_TAG" {}

group "default" {
    targets = ["app", "transcoder"]
}

target "docker-metadata-action" {}

target "app" {
    inherits = ["docker-metadata-action"]
    context = "."
    dockerfile = "docker/prod/Dockerfile"
    target = "app"
    tags = [for TAG in target.docker-metadata-action.tags : "${TAG}-app"]
}

target "transcoder" {
    inherits = ["docker-metadata-action"]
    context = "."
    dockerfile = "docker/prod/Dockerfile"
    target = "transcoder"
    tags = [for TAG in target.docker-metadata-action.tags : "${TAG}-transcoder"]
}
