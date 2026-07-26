<?php
/**
 * Dashboard and network-admin notice for detected currency-switcher conflicts.
 *
 * @package UniversalMulticurrency
 */

declare(strict_types=1);

namespace UMC\Diagnostics;

/**
 * Renders an advisory admin notice when {@see ConflictDetector} finds one or
 * more conflicting switchers. Detection runs lazily at {@see self::render()}
 * time, not at registration.
 *
 * Dismissal UI and persistence are added in a later milestone; this surface is
 * intentionally non-dismissible for now.
 */
final class ConflictNotice {

	/**
	 * Screens that show a notice when the highest confidence is HIGH.
	 *
	 * @var array<int, string>
	 */
	private const HIGH_SCREENS = array(
		'dashboard',
		'plugins',
		'woocommerce_page_wc-settings',
		'update-core',
	);

	/**
	 * Screens that show a notice when the highest confidence is MEDIUM.
	 *
	 * @var array<int, string>
	 */
	private const MEDIUM_SCREENS = array(
		'plugins',
		'woocommerce_page_wc-settings',
	);

	/**
	 * Stable message section identifiers exposed through the view model.
	 *
	 * @var array<int, string>
	 */
	private const MESSAGE_KEYS = array(
		'detected',
		'why_unsafe',
		'symptoms',
		'resolution',
		'disclaimer',
	);

	/**
	 * Conflict detector supplying memoized findings.
	 *
	 * @var ConflictDetector
	 */
	private ConflictDetector $detector;

	/**
	 * Request-scoped flag set when a plugin was deactivated this request.
	 *
	 * @var bool
	 */
	private bool $suppress = false;

	/**
	 * Binds the notice to a conflict detector.
	 *
	 * @param ConflictDetector $detector Memoized conflict detector.
	 */
	public function __construct( ConflictDetector $detector ) {
		$this->detector = $detector;
	}

	/**
	 * Registers admin notice hooks.
	 */
	public function register(): void {
		\add_action( 'admin_notices', array( $this, 'render' ), 10 );
		\add_action( 'network_admin_notices', array( $this, 'render_network' ), 10 );
		\add_action( 'deactivated_plugin', array( $this, 'suppress' ), 10 );
	}

	/**
	 * Suppresses the notice for the remainder of this request.
	 *
	 * Symbol evidence can outlive a deactivation until the next request; this
	 * avoids a stale warning on the deactivation confirmation screen.
	 */
	public function suppress(): void {
		$this->suppress = true;
	}

	/**
	 * Renders the site-admin notice when scoped and permitted.
	 */
	public function render(): void {
		if ( $this->suppress || ! \current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$screen_id = $this->current_screen_id();
		$view      = $this->view_model( $this->detector->findings(), $screen_id, false );

		if ( null === $view ) {
			return;
		}

		$this->render_view( $view );
	}

	/**
	 * Renders the network-admin notice when scoped and permitted.
	 */
	public function render_network(): void {
		if ( $this->suppress || ! \current_user_can( 'manage_network_plugins' ) ) {
			return;
		}

		$screen_id = $this->current_screen_id();
		$view      = $this->view_model( $this->detector->findings(), $screen_id, true );

		if ( null === $view ) {
			return;
		}

		$this->render_view( $view );
	}

	/**
	 * Builds the notice view model for the current screen and findings.
	 *
	 * Pure with respect to WordPress globals aside from the optional filter at
	 * the end, which only runs when a notice would be shown.
	 *
	 * @param array<int, Finding> $findings   Scored findings from the detector.
	 * @param string              $screen_id  Current admin screen id.
	 * @param bool                $is_network Whether the notice renders in network admin.
	 *
	 * @return array<string, mixed>|null Null when no notice should render.
	 */
	public function view_model( array $findings, string $screen_id, bool $is_network = false ): ?array {
		if ( array() === $findings ) {
			return null;
		}

		$highest = self::highest_confidence( $findings );

		if ( ! Confidence::at_least( $highest, Confidence::MEDIUM ) ) {
			return null;
		}

		if ( ! self::should_show_on_screen( $highest, $screen_id ) ) {
			return null;
		}

		$labels = array();

		foreach ( $findings as $finding ) {
			if ( $finding instanceof Finding ) {
				$labels[] = $finding->label();
			}
		}

		if ( array() === $labels ) {
			return null;
		}

		$view = array(
			'notice_class' => self::notice_class_for_confidence( $highest ),
			'confidence'   => $highest,
			'labels'       => $labels,
			'is_network'   => $is_network,
			'messages'     => array_fill_keys( self::MESSAGE_KEYS, true ),
			'settings_url' => 'admin.php?page=wc-settings&tab=umc',
		);

		if ( \function_exists( 'apply_filters' ) ) {
			/**
			 * Filters the conflict notice view model before it is rendered.
			 *
			 * @since 0.6.0
			 *
			 * @param array<string, mixed>  $view       View model.
			 * @param array<int, Finding>   $findings   Scored findings.
			 * @param string                $screen_id  Current admin screen id.
			 * @param bool                  $is_network Whether the notice renders in network admin.
			 */
			$view = (array) \apply_filters(
				'umc_conflict_notice_view_model',
				$view,
				$findings,
				$screen_id,
				$is_network
			);
		}

		return $view;
	}

	/**
	 * Whether a notice at `$confidence` should appear on `$screen_id`.
	 *
	 * @param string $confidence Highest finding confidence.
	 * @param string $screen_id  Admin screen id.
	 */
	public static function should_show_on_screen( string $confidence, string $screen_id ): bool {
		if ( Confidence::HIGH === $confidence ) {
			return in_array( $screen_id, self::HIGH_SCREENS, true );
		}

		if ( Confidence::MEDIUM === $confidence ) {
			return in_array( $screen_id, self::MEDIUM_SCREENS, true );
		}

		return false;
	}

	/**
	 * Maps a confidence level to the WordPress admin notice class string.
	 *
	 * @param string $confidence Confidence level.
	 */
	public static function notice_class_for_confidence( string $confidence ): string {
		if ( Confidence::HIGH === $confidence ) {
			return 'notice notice-error';
		}

		return 'notice notice-warning';
	}

	/**
	 * Returns the highest confidence among the given findings.
	 *
	 * @param array<int, Finding> $findings Scored findings.
	 */
	public static function highest_confidence( array $findings ): string {
		$highest = Confidence::NONE;

		foreach ( $findings as $finding ) {
			if ( ! $finding instanceof Finding ) {
				continue;
			}

			if ( Confidence::RANK[ $finding->confidence() ] > Confidence::RANK[ $highest ] ) {
				$highest = $finding->confidence();
			}
		}

		return $highest;
	}

	/**
	 * Reads the current admin screen id when available.
	 */
	private function current_screen_id(): string {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return '';
		}

		$screen = get_current_screen();

		if ( ! $screen instanceof \WP_Screen ) {
			return '';
		}

		return (string) $screen->id;
	}

	/**
	 * Renders an escaped notice from a view model.
	 *
	 * @param array<string, mixed> $view View model from {@see self::view_model()}.
	 */
	private function render_view( array $view ): void {
		$labels       = isset( $view['labels'] ) && is_array( $view['labels'] ) ? $view['labels'] : array();
		$notice_class = isset( $view['notice_class'] ) ? (string) $view['notice_class'] : 'notice notice-warning';
		$is_network   = ! empty( $view['is_network'] );
		$settings_url = \admin_url( isset( $view['settings_url'] ) ? (string) $view['settings_url'] : 'admin.php?page=wc-settings&tab=umc' );
		$plugin_list  = $this->format_plugin_list( $labels );

		?>
		<div class="<?php echo \esc_attr( $notice_class ); ?> umc-conflict-notice">
			<p>
				<strong>
					<?php
					\printf(
						/* translators: %s: detected third-party currency switcher plugin name(s). */
						\esc_html__( 'Another currency switcher is active. Universal Multicurrency has detected %s, which also converts WooCommerce prices.', 'universal-multicurrency' ),
						\esc_html( $plugin_list )
					);
					?>
				</strong>
			</p>
			<p>
				<strong><?php \esc_html_e( 'Why this is unsafe.', 'universal-multicurrency' ); ?></strong>
				<?php
				echo ' ';
				\esc_html_e(
					'Two plugins converting the same price multiply their rates. WooCommerce reads a product\'s price once, and each switcher converts whatever the previous one returned. Which runs first depends on hook priority, so the result can differ between the catalogue, the cart, and the order that is finally saved.',
					'universal-multicurrency'
				);
				?>
			</p>
			<p>
				<strong><?php \esc_html_e( 'What you may see.', 'universal-multicurrency' ); ?></strong>
				<?php
				echo ' ';
				\esc_html_e(
					'Prices that differ between the shop page and the cart; totals that change on reload; orders stored with a currency or rate the shopper never saw; a payment gateway charging in a different currency to the order; refunds that do not reconcile against the original total.',
					'universal-multicurrency'
				);
				?>
			</p>
			<p>
				<strong><?php \esc_html_e( 'How to resolve.', 'universal-multicurrency' ); ?></strong>
				<?php
				echo ' ';
				\esc_html_e(
					'Keep one. Deactivate the other switcher, then clear your object cache and WooCommerce transients. Existing orders are unaffected — each stores its own currency and exchange rate permanently.',
					'universal-multicurrency'
				);

				if ( $is_network ) {
					echo ' ';
					\esc_html_e( 'Contact your network administrator if you cannot deactivate plugins yourself.', 'universal-multicurrency' );
				}
				?>
			</p>
			<p><?php \esc_html_e( 'Universal Multicurrency will not change, disable, or deactivate any other plugin.', 'universal-multicurrency' ); ?></p>
			<p>
				<a href="<?php echo \esc_url( $settings_url ); ?>">
					<?php \esc_html_e( 'Review multicurrency settings', 'universal-multicurrency' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Formats plugin labels for the headline sentence.
	 *
	 * @param array<int, string> $labels Plugin display labels.
	 */
	private function format_plugin_list( array $labels ): string {
		$labels = array_values(
			array_filter(
				$labels,
				static function ( $label ): bool {
					return is_string( $label ) && '' !== $label;
				}
			)
		);

		if ( array() === $labels ) {
			return '';
		}

		if ( 1 === count( $labels ) ) {
			return $labels[0];
		}

		if ( 2 === count( $labels ) ) {
			return $labels[0] . ' and ' . $labels[1];
		}

		$last = array_pop( $labels );

		return implode( ', ', $labels ) . ', and ' . $last;
	}
}
