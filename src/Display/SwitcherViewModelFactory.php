<?php
/**
 * Builds switcher view models from currency context and settings.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Display;

use UMC\Currency;
use UMC\Currency\CurrencyMetadataProvider;
use UMC\CurrencyContext;

/**
 * Maps selectable currencies and settings into a render-ready view model.
 */
final class SwitcherViewModelFactory {

	/**
	 * Monotonic counter for unique switcher instance ids.
	 *
	 * @var int
	 */
	private static int $instance_counter = 0;

	/**
	 * Request-scoped currency facade.
	 *
	 * @var CurrencyContext
	 */
	private CurrencyContext $context;

	/**
	 * Currency metadata provider for symbols and names.
	 *
	 * @var CurrencyMetadataProvider
	 */
	private CurrencyMetadataProvider $metadata;

	/**
	 * Display settings repository.
	 *
	 * @var SwitcherSettingsRepository
	 */
	private SwitcherSettingsRepository $settings_repository;

	/**
	 * Binds the factory to currency context, metadata, and settings.
	 *
	 * @param CurrencyContext            $context             Request currency facade.
	 * @param CurrencyMetadataProvider   $metadata            Currency metadata provider.
	 * @param SwitcherSettingsRepository $settings_repository Display settings repository.
	 */
	public function __construct(
		CurrencyContext $context,
		CurrencyMetadataProvider $metadata,
		SwitcherSettingsRepository $settings_repository
	) {
		$this->context             = $context;
		$this->metadata            = $metadata;
		$this->settings_repository = $settings_repository;
	}

	/**
	 * Resets the instance counter (tests only).
	 */
	public static function reset_instance_counter(): void {
		self::$instance_counter = 0;
	}

	/**
	 * Creates a view model when rendering is possible.
	 *
	 * @param SwitcherSettings|null $settings_override Optional settings override.
	 * @param bool                  $preview           Whether preview mode is active.
	 */
	public function create( ?SwitcherSettings $settings_override = null, bool $preview = false ): ?SwitcherViewModel {
		$settings = $settings_override ?? $this->settings_repository->get();

		if ( ! $settings->is_enabled() && ! $preview ) {
			return null;
		}

		$currencies = $this->selectable_currencies();

		if ( count( $currencies ) < 2 ) {
			return null;
		}

		$active_code = $this->context->get_active_code();
		$symbols     = array();

		foreach ( $currencies as $currency ) {
			$symbols[] = $this->resolve_symbol( $currency );
		}

		$option_factory = new SwitcherOptionFactory(
			$settings,
			SwitcherElementComposer::duplicate_symbol_map( $symbols )
		);

		$options      = array();
		$active_model = null;

		foreach ( $this->ordered_currencies( $currencies, $settings, $active_code ) as $currency ) {
			$code   = $currency->code();
			$symbol = $this->resolve_symbol( $currency );
			$name   = $this->resolve_name( $code );
			$option = $option_factory->create(
				$code,
				$symbol,
				$name,
				$this->switch_url( $code, $preview ),
				$active_code === $code
			);

			$options[] = $option;

			if ( $option->is_active() ) {
				$active_model = $option;
			}
		}

		if ( null === $active_model && array() !== $options ) {
			$active_model = $options[0];
		}

		++self::$instance_counter;

		return new SwitcherViewModel(
			(string) self::$instance_counter,
			$settings,
			$options,
			$active_model,
			$preview
		);
	}

	/**
	 * Builds a preview-only view model using sample currencies when needed.
	 *
	 * @param SwitcherSettings|null $settings_override Optional settings override.
	 */
	public function create_for_admin_preview( ?SwitcherSettings $settings_override = null ): SwitcherViewModel {
		$settings = $settings_override ?? $this->settings_repository->get();
		$model    = $this->create( $settings, true );

		if ( null !== $model ) {
			return $model;
		}

		$option_factory = new SwitcherOptionFactory( $settings );
		$options        = array();
		$samples        = array(
			array( 'EUR', '€', 'Euro' ),
			array( 'SEK', 'kr', 'Swedish krona' ),
			array( 'USD', '$', 'US Dollar' ),
		);

		foreach ( $samples as $sample_index => $sample ) {
			$options[] = $option_factory->create(
				$sample[0],
				$sample[1],
				$sample[2],
				'#',
				0 === $sample_index
			);
		}

		++self::$instance_counter;

		return new SwitcherViewModel(
			(string) self::$instance_counter,
			$settings,
			$options,
			$options[0],
			true
		);
	}

	/**
	 * Returns selectable currencies resolved from the request context.
	 *
	 * @return array<int, Currency>
	 */
	private function selectable_currencies(): array {
		$currencies = array();

		foreach ( $this->context->get_selectable_codes() as $code ) {
			$currency = $this->context->get_currency( $code );

			if ( $currency instanceof Currency ) {
				$currencies[] = $currency;
			}
		}

		return $currencies;
	}

	/**
	 * Orders selectable currencies with the active code first when configured.
	 *
	 * @param array<int, Currency> $currencies  Selectable currencies.
	 * @param SwitcherSettings     $settings    Display settings.
	 * @param string               $active_code Active currency code.
	 * @return array<int, Currency>
	 */
	private function ordered_currencies( array $currencies, SwitcherSettings $settings, string $active_code ): array {
		if ( ! $settings->active_first() ) {
			return $currencies;
		}

		$active = array();
		$rest   = array();

		foreach ( $currencies as $currency ) {
			if ( $currency->code() === $active_code ) {
				$active[] = $currency;
				continue;
			}

			$rest[] = $currency;
		}

		return array_merge( $active, $rest );
	}

	/**
	 * Resolves the display symbol for one currency.
	 *
	 * @param Currency $currency Currency value object.
	 */
	private function resolve_symbol( Currency $currency ): string {
		$symbol = $currency->symbol();

		if ( '' !== $symbol ) {
			return $symbol;
		}

		$metadata = $this->metadata->get( $currency->code() );

		if ( null !== $metadata ) {
			return $metadata->symbol();
		}

		return $currency->code();
	}

	/**
	 * Resolves the human-readable currency name for one code.
	 *
	 * @param string $code Currency code.
	 */
	private function resolve_name( string $code ): string {
		$metadata = $this->metadata->get( $code );

		return null !== $metadata ? $metadata->name() : $code;
	}

	/**
	 * Builds the switch URL for one currency option.
	 *
	 * @param string $code    Currency code.
	 * @param bool   $preview Whether preview mode is active.
	 */
	private function switch_url( string $code, bool $preview ): string {
		if ( $preview ) {
			return '#';
		}

		return add_query_arg( CurrencyContext::QUERY_VAR, $code );
	}
}
