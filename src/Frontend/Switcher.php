<?php
/**
 * Currency switcher renderer and shortcode.
 *
 * @package UniversalMulticurrency
 */

declare( strict_types=1 );

namespace UMC\Frontend;

use UMC\Currency;
use UMC\CurrencyContext;

/**
 * The single reusable currency-switcher renderer.
 *
 * `render()` returns escaped HTML and is the only place switcher markup is
 * produced, so the shortcode and any future block/widget/Elementor wrappers are
 * thin adapters over it. It offers only currencies the visitor can actually
 * switch to (the selectable set), marks the active one, and links carry
 * `rel="nofollow"`. The switch itself is a `?currency=` request handled by
 * {@see \UMC\CurrencySwitcher}.
 */
final class Switcher {

	/**
	 * Request-scoped currency facade.
	 *
	 * @var CurrencyContext
	 */
	private CurrencyContext $context;

	/**
	 * Binds the renderer to the currency context.
	 *
	 * @param CurrencyContext $context Request-scoped currency facade.
	 */
	public function __construct( CurrencyContext $context ) {
		$this->context = $context;
	}

	/**
	 * Registers the `[umc_switcher]` shortcode.
	 */
	public function register(): void {
		add_shortcode( 'umc_switcher', array( $this, 'shortcode' ) );
	}

	/**
	 * Shortcode adapter for `render()`.
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 */
	public function shortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'layout'      => 'select',
				'show_symbol' => 'yes',
				'show_code'   => 'yes',
			),
			$atts,
			'umc_switcher'
		);

		return $this->render(
			array(
				'layout'      => $atts['layout'],
				'show_symbol' => 'yes' === $atts['show_symbol'],
				'show_code'   => 'yes' === $atts['show_code'],
			)
		);
	}

	/**
	 * Renders the switcher as an escaped HTML string.
	 *
	 * Returns an empty string when there is nothing to switch to (fewer than
	 * two selectable currencies).
	 *
	 * @param array<string, mixed> $args layout (select|links), show_symbol, show_code.
	 */
	public function render( array $args = array() ): string {
		$args = wp_parse_args(
			$args,
			array(
				'layout'      => 'select',
				'show_symbol' => true,
				'show_code'   => true,
			)
		);

		$currencies = $this->selectable_currencies();

		if ( count( $currencies ) < 2 ) {
			return '';
		}

		$active = $this->context->get_active_code();

		return 'links' === $args['layout']
			? $this->render_links( $currencies, $active, $args )
			: $this->render_select( $currencies, $active, $args );
	}

	/**
	 * The selectable currencies as Currency objects.
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
	 * Renders the dropdown layout.
	 *
	 * @param array<int, Currency> $currencies Selectable currencies.
	 * @param string               $active     Active currency code.
	 * @param array<string, mixed> $args       Render args.
	 */
	private function render_select( array $currencies, string $active, array $args ): string {
		$options = '';

		foreach ( $currencies as $currency ) {
			$options .= sprintf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_url( $this->switch_url( $currency->code() ) ),
				selected( $active, $currency->code(), false ),
				esc_html( $this->label( $currency, $args ) )
			);
		}

		return sprintf(
			'<div class="umc-switcher umc-switcher--select"><label class="screen-reader-text" for="umc-switcher-select">%1$s</label><select id="umc-switcher-select" class="umc-switcher__select" onchange="if(this.value){window.location.href=this.value;}">%2$s</select><noscript>%3$s</noscript></div>',
			esc_html__( 'Currency', 'universal-multicurrency' ),
			$options,
			$this->render_links( $currencies, $active, $args )
		);
	}

	/**
	 * Renders the links layout.
	 *
	 * @param array<int, Currency> $currencies Selectable currencies.
	 * @param string               $active     Active currency code.
	 * @param array<string, mixed> $args       Render args.
	 */
	private function render_links( array $currencies, string $active, array $args ): string {
		$items = '';

		foreach ( $currencies as $currency ) {
			$is_active = $active === $currency->code();
			$items    .= sprintf(
				'<li class="umc-switcher__item%1$s"><a rel="nofollow" href="%2$s"%3$s>%4$s</a></li>',
				$is_active ? ' is-active' : '',
				esc_url( $this->switch_url( $currency->code() ) ),
				$is_active ? ' aria-current="true"' : '',
				esc_html( $this->label( $currency, $args ) )
			);
		}

		return '<ul class="umc-switcher umc-switcher--links">' . $items . '</ul>';
	}

	/**
	 * The URL that switches to a currency, preserving the current query.
	 *
	 * @param string $code Currency code.
	 */
	private function switch_url( string $code ): string {
		return add_query_arg( CurrencyContext::QUERY_VAR, $code );
	}

	/**
	 * Builds a currency's display label from the render args.
	 *
	 * @param Currency             $currency Currency to label.
	 * @param array<string, mixed> $args     Render args.
	 */
	private function label( Currency $currency, array $args ): string {
		$parts = array();

		if ( $args['show_symbol'] && '' !== $currency->symbol() ) {
			$parts[] = $currency->symbol();
		}

		if ( $args['show_code'] || array() === $parts ) {
			$parts[] = $currency->code();
		}

		return implode( ' ', $parts );
	}
}
