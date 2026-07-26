#!/usr/bin/env bash
#
# Provisions a WordPress core install with WooCommerce for the integration
# test suite, and symlinks this plugin into its plugins directory.
#
# Env overrides:
#   WP_CORE_DIR  target directory (default: tests/tmp/wordpress)
#   WP_VERSION   WordPress version (default: latest)
#   WC_VERSION   WooCommerce version (default: latest)
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
CORE_DIR="${WP_CORE_DIR:-$ROOT/tests/tmp/wordpress}"
WP_VERSION="${WP_VERSION:-auto}"
WC_VERSION="${WC_VERSION:-latest}"

# "auto" pins the core download to the installed wp-phpunit version so the
# test library and core never drift apart; falls back to latest.
if [ "$WP_VERSION" = "auto" ]; then
    WP_VERSION="latest"
    if command -v php >/dev/null 2>&1 && [ -f "$ROOT/vendor/composer/installed.json" ]; then
        detected="$(php -r '
            $data = json_decode( file_get_contents( $argv[1] ), true );
            foreach ( ( $data["packages"] ?? $data ) as $pkg ) {
                if ( "wp-phpunit/wp-phpunit" === ( $pkg["name"] ?? "" ) ) {
                    echo ltrim( $pkg["version"], "v" );
                }
            }
        ' "$ROOT/vendor/composer/installed.json")"
        if [ -n "$detected" ]; then
            WP_VERSION="$detected"
        fi
    fi
fi

mkdir -p "$CORE_DIR"

if [ ! -f "$CORE_DIR/wp-settings.php" ]; then
    if [ "$WP_VERSION" = "latest" ]; then
        wp_url="https://wordpress.org/latest.tar.gz"
    else
        wp_url="https://wordpress.org/wordpress-${WP_VERSION}.tar.gz"
    fi
    echo "Downloading WordPress (${WP_VERSION})..."
    curl -fsSL "$wp_url" | tar -xz -C "$CORE_DIR" --strip-components=1
fi

PLUGINS_DIR="$CORE_DIR/wp-content/plugins"
mkdir -p "$PLUGINS_DIR"

if [ ! -d "$PLUGINS_DIR/woocommerce" ]; then
    if [ "$WC_VERSION" = "latest" ]; then
        wc_url="https://downloads.wordpress.org/plugin/woocommerce.zip"
    else
        wc_url="https://downloads.wordpress.org/plugin/woocommerce.${WC_VERSION}.zip"
    fi
    echo "Downloading WooCommerce (${WC_VERSION})..."
    tmpzip="$(mktemp)"
    curl -fsSL -o "$tmpzip" "$wc_url"
    if command -v unzip >/dev/null 2>&1; then
        unzip -q "$tmpzip" -d "$PLUGINS_DIR"
    else
        python3 -m zipfile -e "$tmpzip" "$PLUGINS_DIR"
    fi
    rm -f "$tmpzip"
fi

ln -sfn "$ROOT" "$PLUGINS_DIR/universal-multicurrency"

umc_write_test_fixtures() {
    if [ "${UMC_TEST_FIXTURES:-1}" = "0" ]; then
        return 0
    fi

    local fixture_a="$PLUGINS_DIR/umc-fixture-switcher-a/umc-fixture-switcher-a.php"
    local fixture_b="$PLUGINS_DIR/umc-fixture-switcher-b/umc-fixture-switcher-b.php"
    local fixture_inert="$PLUGINS_DIR/umc-fixture-inert/umc-fixture-inert.php"

    mkdir -p "$(dirname "$fixture_a")" "$(dirname "$fixture_b")" "$(dirname "$fixture_inert")"

    cat >"$fixture_a" <<'PHP'
<?php
/**
 * Plugin Name: UMC Fixture Switcher A
 * Description: Integration-test fixture simulating a full conflicting switcher signature set.
 * Version: 0.0.0
 */
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class UMC_Fixture_Switcher_A {}

define( 'UMC_FIXTURE_SWITCHER_A_VERSION', '0.0.0' );

function umc_fixture_switcher_a_symbol(): void {}

add_shortcode( 'umc_fixture_switcher_a', static function (): string {
	return '';
} );

add_filter( 'umc_fixture_switcher_a_hook', static function ( $value ) {
	return $value;
} );
PHP

    cat >"$fixture_b" <<'PHP'
<?php
/**
 * Plugin Name: UMC Fixture Switcher B
 * Description: Integration-test fixture with plugin-path evidence only.
 * Version: 0.0.0
 */
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
PHP

    cat >"$fixture_inert" <<'PHP'
<?php
/**
 * Plugin Name: UMC Fixture Inert
 * Description: Negative-control fixture that must never match a detector.
 * Version: 0.0.0
 */
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
PHP
}

umc_write_test_fixtures

echo "WordPress core ready: $CORE_DIR"
