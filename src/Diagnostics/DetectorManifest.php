<?php
/**
 * The one file permitted to name a third-party plugin.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Diagnostics;

/**
 * Pure data — no control flow, no probes, no WordPress calls. Every needle
 * below was verified against that plugin's distributed source before this
 * commit (see `tests/unit/Diagnostics/ManifestVerifiedSignaturesTest.php`
 * and the commit message evidence table).
 */
final class DetectorManifest {

	/**
	 * The built-in detector manifest, in raw (unsanitised) form.
	 *
	 * @return array<string, array{label: string, signatures: array<int, array{kind: string, needle: string, weight?: int}>}>
	 */
	public static function manifest(): array {
		return array(
			'woocs'       => array(
				'label'      => 'FOX - Currency Switcher Professional for WooCommerce',
				'signatures' => array(
					array(
						'kind'   => SignatureKind::PLUGIN_PATH,
						'needle' => 'woocommerce-currency-switcher/index.php',
					),
					array(
						'kind'   => SignatureKind::CLASS_NAME,
						'needle' => 'WOOCS',
					),
					array(
						'kind'   => SignatureKind::CONSTANT,
						'needle' => 'WOOCS_VERSION',
					),
					array(
						'kind'   => SignatureKind::FUNCTION,
						'needle' => 'woocs_validate_currency',
					),
					array(
						'kind'   => SignatureKind::SHORTCODE,
						'needle' => 'woocs',
					),
					array(
						'kind'   => SignatureKind::HOOK,
						'needle' => 'woocs_convert_price',
					),
				),
			),
			'curcy'       => array(
				'label'      => 'CURCY - Multi Currency for WooCommerce',
				'signatures' => array(
					array(
						'kind'   => SignatureKind::PLUGIN_PATH,
						'needle' => 'woo-multi-currency/woo-multi-currency.php',
					),
					array(
						'kind'   => SignatureKind::CLASS_NAME,
						'needle' => 'WOOMULTI_CURRENCY_F',
					),
					array(
						'kind'   => SignatureKind::CONSTANT,
						'needle' => 'WOOMULTI_CURRENCY_F_VERSION',
					),
					array(
						'kind'   => SignatureKind::FUNCTION,
						'needle' => 'wmc_get_price',
					),
					array(
						'kind'   => SignatureKind::SHORTCODE,
						'needle' => 'woo_multi_currency',
					),
					array(
						'kind'   => SignatureKind::HOOK,
						'needle' => 'wmc_change_raw_price',
					),
				),
			),
			'wcml'        => array(
				'label'      => 'WPML Multilingual & Multicurrency for WooCommerce',
				'signatures' => array(
					array(
						'kind'   => SignatureKind::PLUGIN_PATH,
						'needle' => 'woocommerce-multilingual/wpml-woocommerce.php',
					),
					array(
						'kind'   => SignatureKind::CLASS_NAME,
						'needle' => 'WCML_Multi_Currency',
					),
					array(
						'kind'   => SignatureKind::CONSTANT,
						'needle' => 'WCML_VERSION',
					),
					array(
						'kind'   => SignatureKind::FUNCTION,
						'needle' => 'wcml_convert_price',
					),
					array(
						'kind'   => SignatureKind::SHORTCODE,
						'needle' => 'currency_switcher',
					),
					array(
						'kind'   => SignatureKind::HOOK,
						'needle' => 'wcml_get_client_currency',
					),
				),
			),
			'yaycurrency' => array(
				'label'      => 'YayCurrency',
				'signatures' => array(
					array(
						'kind'   => SignatureKind::PLUGIN_PATH,
						'needle' => 'yaycurrency/yay-currency.php',
					),
					array(
						'kind'   => SignatureKind::CLASS_NAME,
						'needle' => 'Yay_Currency\\Initialize',
					),
					array(
						'kind'   => SignatureKind::CONSTANT,
						'needle' => 'YAY_CURRENCY_VERSION',
					),
					array(
						'kind'   => SignatureKind::SHORTCODE,
						'needle' => 'yaycurrency-switcher',
					),
					array(
						'kind'   => SignatureKind::HOOK,
						'needle' => 'yay_currency_convert_price',
					),
				),
			),
		);
	}
}
