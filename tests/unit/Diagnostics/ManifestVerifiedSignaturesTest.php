<?php
/**
 * Records the distributed-source evidence for every built-in signature.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use UMC\Diagnostics\DetectorManifest;
use UMC\Diagnostics\SignatureKind;

/**
 * Each expected row ties a manifest needle to a verifiable source reference.
 * A mismatch here means U1 was not discharged for that signature.
 */
final class ManifestVerifiedSignaturesTest extends TestCase {

	/**
	 * @return array<string, array<string, string>>
	 */
	public static function verified_signatures(): array {
		return array(
			'woocs'       => array(
				'plugin_path:woocommerce-currency-switcher/index.php' => 'wordpress.org/plugin/woocommerce-currency-switcher — bootstrap file index.php (verified FOX/WOOCS 1.5.0 on dev install)',
				'class:WOOCS'                      => 'classes/woocs.php — final class WOOCS (FOX/WOOCS 1.5.0)',
				'constant:WOOCS_VERSION'           => 'index.php — define( WOOCS_VERSION ) (FOX/WOOCS 1.5.0)',
				'function:woocs_validate_currency' => 'index.php — function woocs_validate_currency() (FOX/WOOCS 1.5.0)',
				'shortcode:woocs'                  => 'classes/woocs.php — add_shortcode( woocs ) (FOX/WOOCS 1.5.0)',
				'hook:woocs_convert_price'         => 'classes/woocs.php — add_filter( woocs_convert_price ) (FOX/WOOCS 1.5.0)',
			),
			'curcy'       => array(
				'plugin_path:woo-multi-currency/woo-multi-currency.php' => 'wordpress.org/plugin/woo-multi-currency v2.2.15 — woo-multi-currency.php header',
				'class:WOOMULTI_CURRENCY_F'            => 'woo-multi-currency.php — class WOOMULTI_CURRENCY_F (v2.2.15)',
				'constant:WOOMULTI_CURRENCY_F_VERSION' => 'woo-multi-currency.php — define( WOOMULTI_CURRENCY_F_VERSION ) (v2.2.15)',
				'function:wmc_get_price'               => 'includes/functions.php — function wmc_get_price() (v2.2.15)',
				'shortcode:woo_multi_currency'         => 'frontend/shortcode.php — add_shortcode( woo_multi_currency ) (v2.2.15)',
				'hook:wmc_change_raw_price'            => 'plugins/change_price_3rd_plugin.php — add_filter( wmc_change_raw_price ) (v2.2.15)',
			),
			'wcml'        => array(
				'plugin_path:woocommerce-multilingual/wpml-woocommerce.php' => 'wordpress.org/plugin/woocommerce-multilingual v5.5.6 — wpml-woocommerce.php header',
				'class:WCML_Multi_Currency'     => 'inc/currencies/class-wcml-multi-currency.php (v5.5.6)',
				'constant:WCML_VERSION'         => 'wpml-woocommerce.php — define( WCML_VERSION ) (v5.5.6)',
				'function:wcml_convert_price'   => 'inc/wcml-core-functions.php — function wcml_convert_price() (v5.5.6)',
				'shortcode:currency_switcher'   => 'inc/currencies/currency-switcher/class-wcml-currency-switcher.php — add_shortcode( currency_switcher ) (v5.5.6)',
				'hook:wcml_get_client_currency' => 'inc/currencies/class-wcml-multi-currency.php — add_filter( wcml_get_client_currency ) (v5.5.6)',
			),
			'yaycurrency' => array(
				'plugin_path:yaycurrency/yay-currency.php' => 'wordpress.org/plugin/yaycurrency v3.3.4 — yay-currency.php header',
				'class:Yay_Currency\\Initialize'           => 'includes/Initialize.php — class Initialize in Yay_Currency namespace (v3.3.4)',
				'constant:YAY_CURRENCY_VERSION'            => 'yay-currency.php — define( YAY_CURRENCY_VERSION ) (v3.3.4)',
				'shortcode:yaycurrency-switcher'           => 'includes/Engine/FEPages/Shortcodes.php — add_shortcode( yaycurrency-switcher ) (v3.3.4)',
				'hook:yay_currency_convert_price'          => 'includes/Engine/Hooks.php — add_filter( yay_currency_convert_price ) (v3.3.4)',
			),
		);
	}

	public function test_every_built_in_detector_has_verified_signature_coverage(): void {
		$manifest = DetectorManifest::manifest();
		$verified = self::verified_signatures();
		$expected = array_keys( $verified );
		$actual   = array_keys( $manifest );

		sort( $expected );
		sort( $actual );

		$this->assertSame( $expected, $actual );

		foreach ( $manifest as $id => $row ) {
			$this->assertArrayHasKey( $id, $verified, "Missing verified signature map for detector '{$id}'." );

			$keys = array();

			foreach ( $row['signatures'] as $signature ) {
				$key = $signature['kind'] . ':' . $signature['needle'];
				$this->assertArrayHasKey(
					$key,
					$verified[ $id ],
					"Detector '{$id}' signature '{$key}' has no recorded verification source."
				);
				$keys[] = $key;
			}

			$this->assertSame(
				array_keys( $verified[ $id ] ),
				$keys,
				"Detector '{$id}' manifest signatures must match the verified map exactly."
			);
		}
	}

	public function test_manifest_uses_only_admissible_signature_kinds(): void {
		foreach ( DetectorManifest::manifest() as $id => $row ) {
			foreach ( $row['signatures'] as $signature ) {
				$this->assertTrue(
					SignatureKind::is_valid( $signature['kind'] ),
					"Detector '{$id}' uses unknown kind '{$signature['kind']}'."
				);
			}
		}
	}
}
