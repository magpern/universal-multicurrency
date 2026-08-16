#!/usr/bin/env bash
set -euo pipefail
E2E_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$E2E_DIR"
GREP="$1"

: "${UMC_E2E_BASE_URL:?required}"
: "${UMC_E2E_ADMIN_USER:?required}"
: "${UMC_E2E_ADMIN_PASSWORD:?required}"
: "${WP_COMPOSE_DIR:?required}"

RUN_ID="${UMC_E2E_RUN_ID:-$(date +%s | tail -c 7)}"
export UMC_E2E_RUN_ID="$RUN_ID"
FIXTURES_JSON="$E2E_DIR/.fixtures.json"
export UMC_E2E_FIXTURES_JSON="$FIXTURES_JSON"

echo "== debug run ${RUN_ID}, grep: ${GREP} =="
( cd "$WP_COMPOSE_DIR" && docker compose run --rm \
    -v "$E2E_DIR/fixtures:/fixtures" \
    wpcli wp eval-file /fixtures/setup-fixtures.php "$RUN_ID" ) \
    | tail -1 > "$FIXTURES_JSON"
cat "$FIXTURES_JSON"

docker run --rm --network host \
    -v "$E2E_DIR":/app -w /app \
    -e UMC_E2E_BASE_URL -e UMC_E2E_ADMIN_USER -e UMC_E2E_ADMIN_PASSWORD \
    -e UMC_E2E_ALLOWED_HOSTS -e UMC_E2E_RUN_ID \
    -e UMC_E2E_FIXTURES_JSON=/app/.fixtures.json \
    mcr.microsoft.com/playwright:v1.55.1-noble \
    npx playwright test specs/m25-fixed-pricing-csv.spec.ts --grep "$GREP"

echo "== fixtures KEPT for debugging: run ${RUN_ID}, product ids in $FIXTURES_JSON =="
