#!/usr/bin/env bash
#
# Release-acceptance orchestrator: creates disposable fixture products via
# WP-CLI (host-side; Playwright itself never touches Docker/WP-CLI -- it
# only ever drives the real browser over HTTPS), runs the Playwright suite
# against them, then removes the fixtures.
#
# Usage:
#   cd tests/e2e
#   UMC_E2E_BASE_URL=https://your-dev-site.example \
#   UMC_E2E_ADMIN_USER=... \
#   UMC_E2E_ADMIN_PASSWORD=... \
#   UMC_E2E_ALLOWED_HOSTS=your-dev-site.example \
#   WP_COMPOSE_DIR=/path/to/wordpress-compose-project \
#     bash run-acceptance.sh [--keep-fixtures] [spec-path...]
#
# spec-path defaults to every release-blocking spec (see README.md's
# "Release acceptance" section); pass one or more explicit paths (relative
# to this directory, e.g. `specs/v1-core-purchase-journey.spec.ts`) to run a
# subset: `bash run-acceptance.sh specs/v1-core-purchase-journey.spec.ts`.
#
# WP_COMPOSE_DIR must point at the docker-compose project that runs the
# target WordPress site's `wpcli` service (profile "tools"). This script
# never assumes a path baked into the repository -- the repository itself
# stays deployment-agnostic (CLAUDE.md).
set -euo pipefail

E2E_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$E2E_DIR"

: "${UMC_E2E_BASE_URL:?UMC_E2E_BASE_URL is required}"
: "${UMC_E2E_ADMIN_USER:?UMC_E2E_ADMIN_USER is required}"
: "${UMC_E2E_ADMIN_PASSWORD:?UMC_E2E_ADMIN_PASSWORD is required}"
: "${UMC_E2E_ALLOWED_HOSTS:?UMC_E2E_ALLOWED_HOSTS is required -- there is no default allowlist}"
: "${WP_COMPOSE_DIR:?WP_COMPOSE_DIR is required (path to the target docker-compose project)}"

KEEP_FIXTURES=0
if [ "${1:-}" = "--keep-fixtures" ]; then
    KEEP_FIXTURES=1
    shift
fi

SPEC_PATHS=("$@")
if [ ${#SPEC_PATHS[@]} -eq 0 ]; then
    SPEC_PATHS=(
        "specs/m25-fixed-pricing-csv.spec.ts"
        "specs/v1-core-purchase-journey.spec.ts"
        "specs/v1-blocks-journey.spec.ts"
        "specs/v1-fixed-pricing-journey.spec.ts"
    )
fi

RUN_ID="${UMC_E2E_RUN_ID:-$(date +%s | tail -c 7)}"
export UMC_E2E_RUN_ID="$RUN_ID"
FIXTURES_JSON="$E2E_DIR/.fixtures.json"
V1_FIXTURES_JSON="$E2E_DIR/.v1-fixtures.json"
export UMC_E2E_FIXTURES_JSON="$FIXTURES_JSON"
export UMC_E2E_V1_FIXTURES_JSON="$V1_FIXTURES_JSON"

echo "== Release acceptance run ${RUN_ID} against ${UMC_E2E_BASE_URL} =="
echo "== Specs: ${SPEC_PATHS[*]} =="

cleanup() {
    if [ "$KEEP_FIXTURES" -eq 1 ]; then
        echo "== --keep-fixtures set: leaving m25e2e-${RUN_ID}-*/v1e2e-${RUN_ID}-* products (and disposable pages) in place =="
        return
    fi
    echo "== Cleaning up fixtures for run ${RUN_ID} =="
    ( cd "$WP_COMPOSE_DIR" && docker compose run --rm \
        -v "$E2E_DIR/fixtures:/fixtures" \
        wpcli wp eval-file /fixtures/cleanup-fixtures.php "$RUN_ID" )
    ( cd "$WP_COMPOSE_DIR" && docker compose run --rm \
        -v "$E2E_DIR/fixtures:/fixtures" \
        wpcli wp eval-file /fixtures/cleanup-v1-fixtures.php "$RUN_ID" )
}
trap cleanup EXIT

echo "== Creating M25 fixtures =="
( cd "$WP_COMPOSE_DIR" && docker compose run --rm \
    -v "$E2E_DIR/fixtures:/fixtures" \
    wpcli wp eval-file /fixtures/setup-fixtures.php "$RUN_ID" ) \
    | tail -1 > "$FIXTURES_JSON"
cat "$FIXTURES_JSON"

echo "== Creating v1.0 journey fixtures =="
( cd "$WP_COMPOSE_DIR" && docker compose run --rm \
    -v "$E2E_DIR/fixtures:/fixtures" \
    wpcli wp eval-file /fixtures/setup-v1-fixtures.php "$RUN_ID" ) \
    | tail -1 > "$V1_FIXTURES_JSON"
cat "$V1_FIXTURES_JSON"

echo "== Installing Playwright (Docker) =="
docker run --rm -v "$E2E_DIR":/app -w /app mcr.microsoft.com/playwright:v1.55.1-noble npm install

echo "== Running release acceptance suite =="
docker run --rm --network host \
    -v "$E2E_DIR":/app -w /app \
    -e UMC_E2E_BASE_URL \
    -e UMC_E2E_ADMIN_USER \
    -e UMC_E2E_ADMIN_PASSWORD \
    -e UMC_E2E_ALLOWED_HOSTS \
    -e UMC_E2E_RUN_ID \
    -e UMC_E2E_FIXTURES_JSON=/app/.fixtures.json \
    -e UMC_E2E_V1_FIXTURES_JSON=/app/.v1-fixtures.json \
    mcr.microsoft.com/playwright:v1.55.1-noble \
    npx playwright test "${SPEC_PATHS[@]}"
