#!/usr/bin/env bash
#
# Release Candidate audit — executable, release-blocking gate (Milestone 7 Commit 8).
#
# Usage: bin/release-audit.sh
#        composer release-audit
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

FAILURES=0

section() {
    printf '\n== %s ==\n' "$1"
}

pass() {
    printf '[PASS] %s\n' "$1"
}

fail() {
    printf '[FAIL] %s\n' "$1" >&2
    FAILURES=$((FAILURES + 1))
}

run_step() {
    local title="$1"
    shift

    section "$title"

    if "$@"; then
        pass "$title"
    else
        fail "$title"
    fi
}

section 'Release audit prerequisites'
if ! command -v git >/dev/null 2>&1; then
    fail 'git is required'
    exit 1
fi
if ! command -v php >/dev/null 2>&1; then
    fail 'php is required'
    exit 1
fi
git config --global --add safe.directory "$ROOT" 2>/dev/null || true

if ! command -v wp >/dev/null 2>&1; then
    if command -v curl >/dev/null 2>&1; then
        curl -sSLo /tmp/wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
        chmod +x /tmp/wp-cli.phar
        export PATH="/tmp:${PATH}"
        ln -sf /tmp/wp-cli.phar /tmp/wp
    fi
fi

pass 'git and php available'

run_step 'PHPCS (0 errors, 0 warnings)' vendor/bin/phpcs
run_step 'Release audit unit guards' vendor/bin/phpunit -c phpunit.xml.dist --group release-audit
run_step 'Security source guards' vendor/bin/phpunit -c phpunit.xml.dist --filter SecuritySourceGuardTest
run_step 'Performance guards' vendor/bin/phpunit -c phpunit.xml.dist --filter 'PerformanceGuardTest|PerformanceBaselineTest'
run_step 'Persisted-data inventory guards' vendor/bin/phpunit -c phpunit.xml.dist --filter PersistedKeysInventoryTest
run_step 'POT drift check' composer make-pot:check
run_step 'Composer security audit' composer audit

section 'Release ZIP build'
if composer install --no-dev --no-interaction --prefer-dist --no-progress --optimize-autoloader; then
    pass 'composer install --no-dev'
else
    fail 'composer install --no-dev'
fi

ZIP_PATH=""
if ZIP_REL="$(bash bin/build-zip.sh)"; then
    pass "build-zip.sh -> ${ZIP_REL}"
    ZIP_PATH="${ROOT}/${ZIP_REL}"
else
    fail 'build-zip.sh'
fi

section 'Restore development dependencies'
if composer install --no-interaction --prefer-dist --no-progress; then
    pass 'composer install (dev restored)'
else
    fail 'composer install (dev restored)'
fi

if [ -n "$ZIP_PATH" ]; then
    section 'Release ZIP inspection'
    export UMC_RELEASE_ZIP="$ZIP_PATH"
    if php bin/inspect-release-zip.php "$ZIP_PATH"; then
        pass 'inspect-release-zip.php'
    else
        fail 'inspect-release-zip.php'
    fi

    if UMC_RELEASE_ZIP="$ZIP_PATH" vendor/bin/phpunit -c phpunit.xml.dist --filter test_release_zip_audit_passes_when_env_points_at_built_artifact; then
        pass 'ReleaseZipInspector PHPUnit gate'
    else
        fail 'ReleaseZipInspector PHPUnit gate'
    fi
fi

section 'Release audit summary'
if [ "$FAILURES" -eq 0 ]; then
    pass 'All release-blocking checks passed'
    exit 0
fi

fail "${FAILURES} release-blocking check(s) failed"
exit 1
