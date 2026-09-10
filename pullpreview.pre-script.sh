#!/usr/bin/env sh

set -eu

if ! command -v envsubst >/dev/null 2>&1; then
  echo "envsubst is required but was not found in PATH" >&2
  exit 1
fi

: "${PULLPREVIEW_PUBLIC_DNS:?PULLPREVIEW_PUBLIC_DNS is required}"

envsubst '${PULLPREVIEW_PUBLIC_DNS}' < pullpreview.traefik.template.yml > pullpreview.traefik.yml

echo "Rendered pullpreview.traefik.yml for ${PULLPREVIEW_PUBLIC_DNS}"
