<?php
/**
 * Derives human-facing rate status labels.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Rates;

use UMC\Settings;

/**
 * Pure status derivation for admin badges and Site Health.
 */
final class RateStatusEvaluator {

	public const LABEL_OK = 'ok';

	public const LABEL_STALE = 'stale';

	public const LABEL_FAILED = 'failed';

	public const LABEL_NEVER = 'never';

	public function __construct(
		private Settings $settings,
		private ExchangeRateStore $store
	) {
	}

	public function label_for_currency( string $code ): string {
		$code = strtoupper( $code );

		if ( Settings::RATE_MODE_AUTOMATIC !== $this->settings->get_effective_rate_mode( $code ) ) {
			return self::LABEL_OK;
		}

		$config = $this->settings->get_currency_config( $code );
		$status = $this->store->get_operational_status( $code );
		$max_age = (int) ( $this->settings->get()['rate_max_age_hours'] ?? Settings::DEFAULT_RATE_MAX_AGE_HOURS );

		if ( RateUpdateState::STATUS_NEVER === $status->last_status() && '' === (string) ( $config['provider_rate'] ?? '' ) ) {
			return self::LABEL_NEVER;
		}

		if ( RateUpdateState::STATUS_FAILED === $status->last_status() ) {
			return self::LABEL_FAILED;
		}

		$updated_at = (int) ( $config['rate_updated_at'] ?? 0 );

		if ( $updated_at <= 0 ) {
			return self::LABEL_NEVER;
		}

		$age_hours = ( time() - $updated_at ) / 3600;

		if ( $age_hours > $max_age ) {
			return self::LABEL_STALE;
		}

		return self::LABEL_OK;
	}

	public function display_label( string $label ): string {
		return match ( $label ) {
			self::LABEL_STALE  => __( 'Stale', 'universal-multicurrency' ),
			self::LABEL_FAILED => __( 'Failed', 'universal-multicurrency' ),
			self::LABEL_NEVER  => __( 'Never', 'universal-multicurrency' ),
			default            => __( 'OK', 'universal-multicurrency' ),
		};
	}
}
