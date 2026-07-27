#!/usr/bin/env bash
#
# Generates or verifies languages/universal-multicurrency.pot from plugin source.
#
# Usage:
#   bin/make-pot.sh          Regenerate the committed POT file
#   bin/make-pot.sh --check  Exit 1 when the committed POT is stale
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
POT="$ROOT/languages/universal-multicurrency.pot"
DOMAIN="universal-multicurrency"
MODE="${1:-}"

mkdir -p "$ROOT/languages"

normalize_pot() {
	local file="$1"

	# Drop wall-clock-dependent headers so diffs are deterministic.
	sed -i \
		-e '/^"POT-Creation-Date:/d' \
		-e '/^"PO-Revision-Date:/d' \
		-e '/^"X-Generator:/d' \
		"$file"
}

run_make_pot() {
	local dest="$1"

	if command -v wp >/dev/null 2>&1; then
		if wp i18n make-pot "$ROOT" "$dest" \
			--domain="$DOMAIN" \
			--exclude=vendor,tests,docs,dist,docs/plans \
			--slug=universal-multicurrency \
			--headers='{"Report-Msgid-Bugs-To":"https://github.com/magpern/universal-multicurrency/issues"}'; then
			return
		fi
	fi

	docker run --rm -u "$(id -u):$(id -g)" \
		-v "$ROOT:/app" -w /app \
		wordpress:cli-php8.1 \
		wp i18n make-pot /app "/app/${dest#$ROOT/}" \
			--domain="$DOMAIN" \
			--exclude=vendor,tests,docs,dist,docs/plans \
			--slug=universal-multicurrency \
			--headers='{"Report-Msgid-Bugs-To":"https://github.com/magpern/universal-multicurrency/issues"}'
}

if [[ "$MODE" == "--check" ]]; then
	if [[ ! -f "$POT" ]]; then
		echo "Missing $POT — run: composer make-pot" >&2
		exit 1
	fi

	TMP_GEN="$ROOT/languages/.pot-check.tmp"
	TMP_EXP="$(mktemp)"
	trap 'rm -f "$TMP_GEN" "$TMP_EXP"' EXIT

	cp "$POT" "$TMP_EXP"
	run_make_pot "$TMP_GEN"
	normalize_pot "$TMP_GEN"
	normalize_pot "$TMP_EXP"

	if ! diff -u "$TMP_EXP" "$TMP_GEN" >/dev/null; then
		echo "POT file is stale. Run: composer make-pot" >&2
		diff -u "$TMP_EXP" "$TMP_GEN" || true
		exit 1
	fi

	exit 0
fi

run_make_pot "$POT"
normalize_pot "$POT"
echo "$POT"
