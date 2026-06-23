variable "CACHE_KEY" {}

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

    # BuildKit cache: persist across runs via GitHub Actions cache and
    # also try to reuse layers from the previously pushed base image.
    cache-from = [
        "type=gha,scope=${CACHE_KEY}-app",
        # Registry cache to speed up multi-run, multi-runner
        "type=registry,ref=cybexwebdev/transmorpher:${CACHE_KEY}-app",
        "type=registry,ref=cybexwebdev/transmorpher:cache-${CACHE_KEY}-app",
    ]
    cache-to = [
        "type=gha,mode=max,scope=${CACHE_KEY}-app",
        # Persist intermediate layers in the registry so they can be reused across runners
        # and events (faster cold-starts than GHA cache alone).
        "type=registry,ref=cybexwebdev/transmorpher:cache-${CACHE_KEY}-app",
    ]
}

target "transcoder" {
    inherits = ["docker-metadata-action"]
    context = "."
    dockerfile = "docker/prod/Dockerfile"
    target = "transcoder"
    tags = [for TAG in target.docker-metadata-action.tags : "${TAG}-transcoder"]

    # BuildKit cache: persist across runs via GitHub Actions cache and
    # also try to reuse layers from the previously pushed base image.
    cache-from = [
        "type=gha,scope=${CACHE_KEY}-transcoder",
        # Registry cache to speed up multi-run, multi-runner
        "type=registry,ref=cybexwebdev/transmorpher:${CACHE_KEY}-transcoder",
        "type=registry,ref=cybexwebdev/transmorpher:cache-${CACHE_KEY}-transcoder",
    ]
    cache-to = [
        "type=gha,mode=max,scope=${CACHE_KEY}-transcoder",
        # Persist intermediate layers in the registry so they can be reused across runners
        # and events (faster cold-starts than GHA cache alone).
        "type=registry,ref=cybexwebdev/transmorpher:cache-${CACHE_KEY}-transcoder",
    ]
}
