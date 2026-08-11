<?php
/**
 * Builds currency admin view models from settings and services.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Admin\ViewModel;

use UMC\Currency;
use UMC\Currency\CurrencyMetadataProvider;
use UMC\Rates\ExchangeRateStore;
use UMC\Rates\RateResolver;
use UMC\Rates\RateStatusEvaluator;
use UMC\Settings;

/**
 * Maps persisted settings into presentation-only view models.
 */
final class CurrencyViewModelFactory {

	/**
	 * Binds the factory to settings, services, and metadata.
	 *
	 * @param Settings                 $settings Merchant settings store.
	 * @param Currency                 $base     Store base currency.
	 * @param ExchangeRateStore        $store    Rate persistence boundary.
	 * @param CurrencyMetadataProvider $metadata Currency metadata provider.
	 */
	public function __construct(
		private Settings $settings,
		private Currency $base,
		private ExchangeRateStore $store,
		private CurrencyMetadataProvider $metadata
	) {
	}

	/**
	 * Builds the currencies section view model.
	 *
	 * @param string $open_editor Currency code whose editor should open.
	 */
	public function overview( string $open_editor = '' ): CurrencyOverviewViewModel {
		$open_editor = strtoupper( trim( $open_editor ) );
		$evaluator   = new RateStatusEvaluator( $this->settings, $this->store );
		$rows        = array( $this->base_row() );
		$editors     = array();
		$index       = 0;

		$configured = $this->settings->get_currencies();
		unset( $configured[ $this->base->code() ] );

		foreach ( $configured as $code => $config ) {
			$code      = (string) $code;
			$rows[]    = $this->configured_row( $code, $config, $evaluator );
			$editors[] = $this->editor_row( $index, $code, $config, $open_editor === $code );
			++$index;
		}

		return new CurrencyOverviewViewModel(
			$rows,
			$editors,
			$this->add_options( array_keys( $configured ) ),
			$this->add_currency_url(),
			$open_editor
		);
	}

	/**
	 * Builds the base currency overview row.
	 */
	private function base_row(): CurrencyViewModel {
		$metadata = $this->metadata->get( $this->base->code() );
		$name     = null !== $metadata ? $metadata->name() : $this->base->code();
		$symbol   = '' !== $this->base->symbol() ? $this->base->symbol() : ( $metadata?->symbol() ?? $this->base->code() );

		return new CurrencyViewModel(
			true,
			true,
			$this->base->code(),
			$name,
			$symbol,
			__( 'Base currency', 'universal-multicurrency' ),
			'—',
			'',
			'',
			__( 'Base currency', 'universal-multicurrency' ),
			'base',
			'—',
			'',
			'',
			'',
			'',
			'',
			false,
			admin_url( 'admin.php?page=wc-settings&tab=general' )
		);
	}

	/**
	 * Builds one configured currency overview row.
	 *
	 * @param string               $code Currency code.
	 * @param array<string, mixed> $config Stored configuration.
	 * @param RateStatusEvaluator  $evaluator Status evaluator.
	 */
	private function configured_row( string $code, array $config, RateStatusEvaluator $evaluator ): CurrencyViewModel {
		$metadata        = $this->metadata->get( $code );
		$name            = null !== $metadata ? $metadata->name() : $code;
		$symbol          = $this->effective_symbol( $config, $metadata );
		$enabled         = ! empty( $config['enabled'] );
		$mode            = $this->settings->get_effective_rate_mode( $code );
		$stored_mode     = isset( $config['rate_mode'] ) ? (string) $config['rate_mode'] : '';
		$manual          = (string) ( $config['manual_rate'] ?? ( $config['rate'] ?? '' ) );
		$provider        = (string) ( $config['provider_rate'] ?? '' );
		$adjustment      = Settings::enforce_adjustment_range(
			Settings::normalize_adjustment( $config['merchant_adjustment'] ?? '0' )
		);
		$effective       = RateResolver::effective_rate( $mode, $manual, $provider, $adjustment );
		$derivation      = $this->effective_rate_source( $mode, $adjustment );
		$status          = $this->status_presentation( $code, $enabled, $mode, $evaluator );
		$updated_at      = (int) ( $config['rate_updated_at'] ?? 0 );
		$updated_label   = $updated_at > 0 ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $updated_at ) : '—';
		$can_update_rate = Settings::RATE_MODE_AUTOMATIC === $mode;
		$update_rate_url = $can_update_rate ? $this->update_rate_url( $code ) : '';
		$toggle_to       = $enabled ? '0' : '1';

		return new CurrencyViewModel(
			false,
			$enabled,
			$code,
			$name,
			$symbol,
			$this->mode_label( $stored_mode, $mode ),
			null === $effective ? '—' : $effective,
			$derivation,
			$this->adjustment_label( $mode, $adjustment ),
			$status['label'],
			$status['class'],
			$updated_label,
			'umc-editor-' . strtolower( $code ),
			$this->toggle_url( $code, $toggle_to ),
			$enabled ? __( 'Disable', 'universal-multicurrency' ) : __( 'Enable', 'universal-multicurrency' ),
			$this->remove_url( $code ),
			$update_rate_url,
			$can_update_rate,
			''
		);
	}

	/**
	 * Builds one expandable editor row.
	 *
	 * @param int                  $index POST array index.
	 * @param string               $code  Currency code.
	 * @param array<string, mixed> $config Stored configuration.
	 * @param bool                 $is_open Whether the editor should render expanded.
	 */
	private function editor_row( int $index, string $code, array $config, bool $is_open ): CurrencyEditorViewModel {
		$metadata    = $this->metadata->get( $code );
		$name        = null !== $metadata ? $metadata->name() : $code;
		$mode        = $this->settings->get_effective_rate_mode( $code );
		$stored_mode = isset( $config['rate_mode'] ) ? (string) $config['rate_mode'] : '';
		$manual      = (string) ( $config['manual_rate'] ?? ( $config['rate'] ?? '' ) );
		$provider    = (string) ( $config['provider_rate'] ?? '' );
		$adjustment  = Settings::enforce_adjustment_range(
			Settings::normalize_adjustment( $config['merchant_adjustment'] ?? '0' )
		);
		$effective   = RateResolver::effective_rate( $mode, $manual, $provider, $adjustment );

		return new CurrencyEditorViewModel(
			$index,
			$code,
			$name,
			! empty( $config['enabled'] ),
			(string) ( $config['symbol'] ?? '' ),
			(string) ( $config['position'] ?? Currency::DEFAULT_POSITION ),
			(int) ( $config['decimals'] ?? Currency::DEFAULT_DECIMALS ),
			$stored_mode,
			$manual,
			$provider,
			$adjustment,
			null === $effective ? '—' : $effective,
			$this->effective_rate_source( $mode, $adjustment ),
			Settings::RATE_MODE_MANUAL === $mode,
			Settings::RATE_MODE_AUTOMATIC === $mode,
			Settings::RATE_MODE_AUTOMATIC === $mode ? $this->update_rate_url( $code ) : '',
			$is_open
		);
	}

	/**
	 * Builds add-currency select options.
	 *
	 * @param array<int, string> $configured_codes Already configured non-base codes.
	 * @return array<string, string>
	 */
	private function add_options( array $configured_codes ): array {
		$blocked                        = array_fill_keys( array_map( 'strtoupper', $configured_codes ), true );
		$blocked[ $this->base->code() ] = true;

		$options = array();

		foreach ( $this->metadata->all() as $code => $meta ) {
			if ( isset( $blocked[ $code ] ) ) {
				continue;
			}

			$options[ $code ] = $meta->option_label();
		}

		asort( $options, SORT_NATURAL | SORT_FLAG_CASE );

		return $options;
	}

	/**
	 * Resolves the effective symbol for one currency row.
	 *
	 * @param array<string, mixed>                $config   Row configuration.
	 * @param \UMC\Currency\CurrencyMetadata|null $metadata Currency metadata.
	 */
	private function effective_symbol( array $config, ?\UMC\Currency\CurrencyMetadata $metadata ): string {
		$override = isset( $config['symbol'] ) ? trim( (string) $config['symbol'] ) : '';

		if ( '' !== $override ) {
			return $override;
		}

		return $metadata?->symbol() ?? '';
	}

	/**
	 * Builds the mode column label.
	 *
	 * @param string $stored_mode Per-currency override, empty to inherit global.
	 * @param string $effective   Effective rate mode.
	 */
	private function mode_label( string $stored_mode, string $effective ): string {
		if ( '' === $stored_mode ) {
			return sprintf(
				/* translators: %s: inherited global rate mode label */
				__( 'Inherit (%s)', 'universal-multicurrency' ),
				Settings::RATE_MODE_AUTOMATIC === $effective
					? __( 'Automatic', 'universal-multicurrency' )
					: __( 'Manual', 'universal-multicurrency' )
			);
		}

		return Settings::RATE_MODE_AUTOMATIC === $stored_mode
			? __( 'Automatic', 'universal-multicurrency' )
			: __( 'Manual', 'universal-multicurrency' );
	}

	/**
	 * Builds the effective-rate derivation label.
	 *
	 * @param string $mode       Effective rate mode.
	 * @param string $adjustment Normalized adjustment percentage.
	 */
	private function effective_rate_source( string $mode, string $adjustment ): string {
		if ( Settings::RATE_MODE_MANUAL === $mode ) {
			return __( 'Manual', 'universal-multicurrency' );
		}

		$provider = (string) ( $this->settings->get()['rate_provider'] ?? Settings::DEFAULT_RATE_PROVIDER );
		$label    = '' !== $provider ? ucfirst( $provider ) : 'Frankfurter';

		if ( '0' === $adjustment || '0.00' === $adjustment ) {
			return sprintf(
				/* translators: %s: provider label, e.g. Frankfurter */
				__( 'Automatic — %s', 'universal-multicurrency' ),
				$label
			);
		}

		$numeric = (float) $adjustment;
		$sign    = $numeric > 0 ? '+' : '';

		return sprintf(
			/* translators: 1: provider label, 2: signed adjustment percentage, e.g. +2 */
			__( 'Automatic — %1$s (%2$s%%)', 'universal-multicurrency' ),
			$label,
			$sign . rtrim( rtrim( number_format( $numeric, 2, '.', '' ), '0' ), '.' )
		);
	}

	/**
	 * Builds the adjustment column label.
	 *
	 * @param string $mode       Effective rate mode.
	 * @param string $adjustment Normalized adjustment percentage.
	 */
	private function adjustment_label( string $mode, string $adjustment ): string {
		if ( Settings::RATE_MODE_MANUAL === $mode || '0' === $adjustment || '0.00' === $adjustment ) {
			return '';
		}

		$numeric = (float) $adjustment;
		$sign    = $numeric > 0 ? '+' : '';

		return $sign . rtrim( rtrim( number_format( $numeric, 2, '.', '' ), '0' ), '.' ) . '%';
	}

	/**
	 * Builds the status badge presentation for one currency row.
	 *
	 * @param string              $code      Currency code.
	 * @param bool                $enabled   Whether the currency is enabled.
	 * @param string              $mode      Effective rate mode.
	 * @param RateStatusEvaluator $evaluator Status evaluator.
	 * @return array{label: string, class: string}
	 */
	private function status_presentation( string $code, bool $enabled, string $mode, RateStatusEvaluator $evaluator ): array {
		if ( ! $enabled ) {
			return array(
				'label' => __( 'Disabled', 'universal-multicurrency' ),
				'class' => 'disabled',
			);
		}

		if ( Settings::RATE_MODE_MANUAL === $mode ) {
			return array(
				'label' => __( 'Manual', 'universal-multicurrency' ),
				'class' => 'manual',
			);
		}

		$label = $evaluator->label_for_currency( $code );

		return match ( $label ) {
			RateStatusEvaluator::LABEL_AGING  => array(
				'label' => __( 'Aging', 'universal-multicurrency' ),
				'class' => 'aging',
			),
			RateStatusEvaluator::LABEL_STALE  => array(
				'label' => __( 'Stale', 'universal-multicurrency' ),
				'class' => 'stale',
			),
			RateStatusEvaluator::LABEL_FAILED,
			RateStatusEvaluator::LABEL_NEVER  => array(
				'label' => __( 'Unavailable', 'universal-multicurrency' ),
				'class' => 'unavailable',
			),
			default => array(
				'label' => __( 'Current', 'universal-multicurrency' ),
				'class' => 'current',
			),
		};
	}

	/**
	 * Builds the add-currency admin-post URL.
	 */
	private function add_currency_url(): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=umc_currency_add' ),
			'umc_currency_add'
		);
	}

	/**
	 * Builds the enable/disable admin-post URL.
	 *
	 * @param string $code  Currency code.
	 * @param string $state Target enabled state.
	 */
	private function toggle_url( string $code, string $state ): string {
		return wp_nonce_url(
			admin_url(
				'admin-post.php?action=umc_currency_toggle&code=' . rawurlencode( $code ) . '&state=' . rawurlencode( $state )
			),
			'umc_currency_toggle'
		);
	}

	/**
	 * Builds the remove-currency admin-post URL.
	 *
	 * @param string $code Currency code.
	 */
	private function remove_url( string $code ): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=umc_currency_remove&code=' . rawurlencode( $code ) ),
			'umc_currency_remove'
		);
	}

	/**
	 * Builds the single-currency rate update URL.
	 *
	 * @param string $code Currency code.
	 */
	private function update_rate_url( string $code ): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=umc_update_rates&scope=single&code=' . rawurlencode( $code ) ),
			'umc_update_rates'
		);
	}
}
