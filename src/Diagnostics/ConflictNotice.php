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
 * time, not at registration. Dismissal is persisted per user via
 * {@see NoticeDismissal} except on screens where a critical warning must stay
 * visible.
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
	 * Per-user dismissal store for the dashboard notice.
	 *
	 * @var NoticeDismissal|null
	 */
	private ?NoticeDismissal $dismissal;

	/**
	 * Request-scoped flag set when a plugin was deactivated this request.
	 *
	 * @var bool
	 */
	private bool $suppress = false;

	/**
	 * Binds the notice to a conflict detector and optional dismissal store.
	 *
	 * @param ConflictDetector     $detector   Memoized conflict detector.
	 * @param NoticeDismissal|null $dismissal  Dismissal persistence, if wired.
	 */
	public function __construct( ConflictDetector $detector, ?NoticeDismissal $dismissal = null ) {
		$this->detector  = $detector;
		$this->dismissal = $dismissal;
	}

	/**
	 * Registers admin notice hooks.
	 */
	public function register(): void {
		\add_action( 'admin_notices', array( $this, 'render' ), 10 );
		\add_action( 'network_admin_notices', array( $this, 'render_network' ), 10 );
		\add_action( 'deactivated_plugin', array( $this, 'suppress' ), 10 );
		\add_action( 'woocommerce_admin_field_umc_conflict', array( $this, 'render_settings_field' ), 10, 1 );
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

		$fingerprint = $this->detector->fingerprint();
		$dismissible = self::is_dismissible( (string) $view['confidence'], $screen_id );

		if ( $dismissible && null !== $this->dismissal && $this->dismissal->is_dismissed( $fingerprint ) ) {
			return;
		}

		$view['dismissible'] = $dismissible;
		$view['fingerprint'] = $fingerprint;

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

		$fingerprint = $this->detector->fingerprint();
		$dismissible = self::is_dismissible( (string) $view['confidence'], $screen_id );

		if ( $dismissible && null !== $this->dismissal && $this->dismissal->is_dismissed( $fingerprint ) ) {
			return;
		}

		$view['dismissible'] = $dismissible;
		$view['fingerprint'] = $fingerprint;

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

		return $view;
	}

	/**
	 * Builds the settings-tab view model from scored findings.
	 *
	 * Unlike the dashboard surface, LOW findings render here as a plain
	 * description line and dismissal never applies.
	 *
	 * @param array<int, Finding> $findings              Scored findings from the detector.
	 * @param bool                $can_activate_plugins Whether the viewer may deactivate plugins.
	 *
	 * @return array<string, mixed>|null Null when there is nothing to report.
	 */
	public function settings_view_model( array $findings, bool $can_activate_plugins ): ?array {
		if ( array() === $findings ) {
			return null;
		}

		$labels       = array();
		$finding_rows = array();

		foreach ( $findings as $finding ) {
			if ( ! $finding instanceof Finding ) {
				continue;
			}

			$labels[] = $finding->label();

			$evidence = array();

			foreach ( $finding->matched() as $signature ) {
				$evidence[] = array(
					'kind'   => $signature->kind(),
					'needle' => $signature->needle(),
				);
			}

			$finding_rows[] = array(
				'id'         => $finding->id(),
				'label'      => $finding->label(),
				'confidence' => $finding->confidence(),
				'evidence'   => $evidence,
			);
		}

		if ( array() === $finding_rows ) {
			return null;
		}

		$highest = self::highest_confidence( $findings );

		$view = array(
			'surface'              => 'settings',
			'confidence'           => $highest,
			'notice_class'         => self::settings_notice_class( $highest ),
			'render_mode'          => Confidence::LOW === $highest ? 'description' : 'notice',
			'findings'             => $finding_rows,
			'labels'               => $labels,
			'can_activate_plugins' => $can_activate_plugins,
			'messages'             => array_fill_keys( self::MESSAGE_KEYS, true ),
		);

		/**
		 * Filters the settings-tab conflict view model before it is rendered.
		 *
		 * @since 0.6.0
		 *
		 * @param array<string, mixed> $view                 View model.
		 * @param array<int, Finding>  $findings             Scored findings.
		 * @param bool                   $can_activate_plugins Whether the viewer may deactivate plugins.
		 */
		$view = (array) \apply_filters(
			'umc_conflict_settings_view_model',
			$view,
			$findings,
			$can_activate_plugins
		);

		return $view;
	}

	/**
	 * Renders the custom WooCommerce settings field on the Multicurrency tab.
	 *
	 * WooCommerce already gates the page on `manage_woocommerce`; this surface
	 * does not double-gate read access and is never dismissible.
	 *
	 * @param array<string, mixed> $value Field definition from {@see \UMC\Admin\SettingsPage::get_settings()}.
	 */
	public function render_settings_field( array $value ): void {
		unset( $value );

		if ( $this->suppress ) {
			return;
		}

		$view = $this->settings_view_model(
			$this->detector->findings(),
			\current_user_can( 'activate_plugins' )
		);

		if ( null === $view ) {
			return;
		}

		$this->render_settings_view( $view );
	}

	/**
	 * Maps a confidence level to the inline notice class on the settings tab.
	 *
	 * @param string $confidence Confidence level.
	 */
	public static function settings_notice_class( string $confidence ): string {
		if ( Confidence::HIGH === $confidence ) {
			return 'notice notice-error inline';
		}

		if ( Confidence::MEDIUM === $confidence ) {
			return 'notice notice-warning inline';
		}

		return '';
	}

	/**
	 * Builds a human-readable evidence phrase for one matched signature.
	 *
	 * Only matched needles are ever disclosed; values are never read or shown.
	 *
	 * @param string $kind   Signature kind {@see SignatureKind}.
	 * @param string $needle Matched identifier.
	 */
	public static function evidence_phrase( string $kind, string $needle ): string {
		switch ( $kind ) {
			case SignatureKind::PLUGIN_PATH:
				return sprintf(
					/* translators: %s: plugin bootstrap path relative to wp-content/plugins. */
					__( 'the plugin "%s" is active', 'universal-multicurrency' ),
					$needle
				);
			case SignatureKind::CLASS_NAME:
				return sprintf(
					/* translators: %s: PHP class name detected at runtime. */
					__( 'the class "%s" exists', 'universal-multicurrency' ),
					$needle
				);
			case SignatureKind::FUNCTION:
				return sprintf(
					/* translators: %s: PHP function name detected at runtime. */
					__( 'the function "%s" exists', 'universal-multicurrency' ),
					$needle
				);
			case SignatureKind::CONSTANT:
				return sprintf(
					/* translators: %s: PHP constant name detected at runtime. */
					__( 'the constant "%s" is defined', 'universal-multicurrency' ),
					$needle
				);
			case SignatureKind::SHORTCODE:
				return sprintf(
					/* translators: %s: WordPress shortcode tag. */
					__( 'the shortcode "%s" is registered', 'universal-multicurrency' ),
					$needle
				);
			case SignatureKind::HOOK:
				return sprintf(
					/* translators: %s: WordPress hook name. */
					__( 'the hook "%s" is registered', 'universal-multicurrency' ),
					$needle
				);
			default:
				return '';
		}
	}

	/**
	 * Joins matched-signature phrases for one finding into a settings-tab sentence.
	 *
	 * @param array<int, array{kind: string, needle: string}> $evidence Matched signature rows.
	 */
	public static function format_evidence_sentence( array $evidence ): string {
		$phrases = array();

		foreach ( $evidence as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$kind   = isset( $row['kind'] ) ? (string) $row['kind'] : '';
			$needle = isset( $row['needle'] ) ? (string) $row['needle'] : '';

			if ( '' === $kind || '' === $needle ) {
				continue;
			}

			$phrase = self::evidence_phrase( $kind, $needle );

			if ( '' !== $phrase ) {
				$phrases[] = $phrase;
			}
		}

		if ( array() === $phrases ) {
			return '';
		}

		if ( 1 === count( $phrases ) ) {
			return $phrases[0];
		}

		$last = array_pop( $phrases );

		return implode( '; ', $phrases ) . '; ' . __( 'and', 'universal-multicurrency' ) . ' ' . $last;
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
	 * Whether the dashboard notice may be dismissed on `$screen_id`.
	 *
	 * HIGH conflicts stay visible on `plugins.php` and the multicurrency settings
	 * tab; every other scoped screen allows dismissal.
	 *
	 * @param string $confidence Highest finding confidence.
	 * @param string $screen_id  Admin screen id.
	 */
	public static function is_dismissible( string $confidence, string $screen_id ): bool {
		if ( 'woocommerce_page_wc-settings' === $screen_id ) {
			return false;
		}

		if ( 'plugins' === $screen_id && Confidence::HIGH === $confidence ) {
			return false;
		}

		return true;
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
		$screen = \get_current_screen();

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
		$dismissible  = ! empty( $view['dismissible'] );
		$fingerprint  = isset( $view['fingerprint'] ) ? (string) $view['fingerprint'] : '';
		$is_network   = ! empty( $view['is_network'] );
		$settings_url = $this->settings_admin_url( $view );
		$plugin_list  = $this->format_plugin_list( $labels );

		if ( $dismissible ) {
			$notice_class .= ' is-dismissible';
		}

		$dismiss_url = '';

		if ( $dismissible && '' !== $fingerprint && NoticeDismissal::is_valid_fingerprint( $fingerprint ) ) {
			$return_url  = \remove_query_arg(
				array( NoticeDismissal::QUERY_ARG, '_wpnonce' )
			);
			$dismiss_url = \wp_nonce_url(
				\add_query_arg( NoticeDismissal::QUERY_ARG, $fingerprint, $return_url ),
				'umc_dismiss_' . $fingerprint
			);
		}

		?>
		<div class="<?php echo \esc_attr( $notice_class ); ?> umc-conflict-notice"<?php echo $dismissible ? ' data-umc-conflict-dismissible="1"' : ''; ?>>
			<?php $this->render_conflict_copy( $plugin_list, $is_network, true ); ?>
			<p>
				<a href="<?php echo \esc_url( $settings_url ); ?>">
					<?php \esc_html_e( 'Review multicurrency settings', 'universal-multicurrency' ); ?>
				</a>
				<?php if ( '' !== $dismiss_url ) : ?>
					<span aria-hidden="true"> · </span>
					<a href="<?php echo \esc_url( $dismiss_url ); ?>">
						<?php \esc_html_e( 'Dismiss this notice', 'universal-multicurrency' ); ?>
					</a>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Renders the settings-tab conflict panel from a view model.
	 *
	 * @param array<string, mixed> $view View model from {@see self::settings_view_model()}.
	 */
	private function render_settings_view( array $view ): void {
		$labels               = isset( $view['labels'] ) && is_array( $view['labels'] ) ? $view['labels'] : array();
		$findings             = isset( $view['findings'] ) && is_array( $view['findings'] ) ? $view['findings'] : array();
		$render_mode          = isset( $view['render_mode'] ) ? (string) $view['render_mode'] : 'notice';
		$notice_class         = isset( $view['notice_class'] ) ? (string) $view['notice_class'] : '';
		$can_activate_plugins = ! empty( $view['can_activate_plugins'] );
		$plugin_list          = $this->format_plugin_list( $labels );
		$wrapper_class        = 'description' === $render_mode ? 'description' : $notice_class . ' umc-conflict-notice umc-conflict-notice--settings';

		?>
		<div class="<?php echo \esc_attr( $wrapper_class ); ?>">
			<?php $this->render_conflict_copy( $plugin_list, false, $can_activate_plugins ); ?>
			<?php $this->render_evidence_list( $findings ); ?>
		</div>
		<?php
	}

	/**
	 * Renders the shared four-beat copy and disclaimer.
	 *
	 * @param string $plugin_list          Formatted plugin label list.
	 * @param bool   $is_network           Whether the network-admin variant is shown.
	 * @param bool   $can_activate_plugins Whether deactivation instructions may be shown.
	 */
	private function render_conflict_copy( string $plugin_list, bool $is_network, bool $can_activate_plugins ): void {
		?>
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
			if ( $can_activate_plugins ) {
				\esc_html_e(
					'Keep one. Deactivate the other switcher, then clear your object cache and WooCommerce transients. Existing orders are unaffected — each stores its own currency and exchange rate permanently.',
					'universal-multicurrency'
				);
			} else {
				\esc_html_e(
					'Ask an administrator to deactivate the other switcher, then clear your object cache and WooCommerce transients. Existing orders are unaffected — each stores its own currency and exchange rate permanently.',
					'universal-multicurrency'
				);
			}

			if ( $is_network ) {
				echo ' ';
				\esc_html_e( 'Contact your network administrator if you cannot deactivate plugins yourself.', 'universal-multicurrency' );
			}
			?>
		</p>
		<p><?php \esc_html_e( 'Universal Multicurrency will not change, disable, or deactivate any other plugin.', 'universal-multicurrency' ); ?></p>
		<?php
	}

	/**
	 * Renders matched-signature evidence for each finding.
	 *
	 * @param array<int, array<string, mixed>> $findings Finding rows from the settings view model.
	 */
	private function render_evidence_list( array $findings ): void {
		if ( array() === $findings ) {
			return;
		}

		?>
		<div class="umc-conflict-evidence">
			<?php foreach ( $findings as $finding ) : ?>
				<?php
				if ( ! is_array( $finding ) ) {
					continue;
				}

				$label    = isset( $finding['label'] ) ? (string) $finding['label'] : '';
				$evidence = isset( $finding['evidence'] ) && is_array( $finding['evidence'] ) ? $finding['evidence'] : array();
				$sentence = self::format_evidence_sentence( $evidence );

				if ( '' === $label || '' === $sentence ) {
					continue;
				}
				?>
				<p>
					<strong><?php echo \esc_html( $label ); ?></strong>
					<?php
					echo ' — ';
					\esc_html_e( 'Detected because:', 'universal-multicurrency' );
					echo ' ';
					echo \esc_html( $sentence );
					?>
				</p>
			<?php endforeach; ?>
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
			return $labels[0] . ' ' . __( 'and', 'universal-multicurrency' ) . ' ' . $labels[1];
		}

		$last = array_pop( $labels );

		return implode( ', ', $labels ) . ', ' . __( 'and', 'universal-multicurrency' ) . ' ' . $last;
	}

	/**
	 * Builds a safe admin settings URL for conflict notices.
	 *
	 * Filters may adjust the relative admin.php path, but external URLs are
	 * rejected to prevent open redirects from view-model hooks.
	 *
	 * @param array<string, mixed> $view Notice view model.
	 */
	private function settings_admin_url( array $view ): string {
		$default = 'admin.php?page=wc-settings&tab=umc';
		$path    = isset( $view['settings_url'] ) ? (string) $view['settings_url'] : $default;

		if ( 1 !== preg_match( '/^admin\.php(?:\?[^#]*)?$/', $path ) ) {
			$path = $default;
		}

		return \admin_url( $path );
	}
}
