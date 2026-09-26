<?php
/**
 * Setup Wizard Class
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Admin;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Setup Wizard class.
 */
class Setup_Wizard {

	/**
	 * Current step.
	 *
	 * @var string
	 */
	private $step = '';

	/**
	 * Steps.
	 *
	 * @var array
	 */
	private $steps = array();

	/**
	 * Initialize.
	 */
	public function init() {
		if ( apply_filters( 'wbam_enable_setup_wizard', true ) ) {
			add_action( 'admin_menu', array( $this, 'add_wizard_page' ) );
			add_action( 'admin_init', array( $this, 'setup_wizard' ) );
			add_action( 'admin_notices', array( $this, 'show_setup_notice' ) );
			add_action( 'wp_ajax_wbam_dismiss_setup', array( $this, 'dismiss_setup' ) );
		}
	}

	/**
	 * Add wizard page (hidden from menu).
	 */
	public function add_wizard_page() {
		add_dashboard_page( '', '', 'manage_options', 'wbam-setup', '' );

		// Registered so index.php?page=wbam-setup resolves, then taken out of
		// the Dashboard submenu, where it showed as an item with no text.
		remove_submenu_page( 'index.php', 'wbam-setup' );
	}

	/**
	 * Whether first-run setup should be considered finished.
	 *
	 * PRO takes over the first-run flow on its own activation and records
	 * completion under `wbam_pro_setup_complete`, never touching this
	 * plugin's `wbam_setup_complete`. Without treating the PRO flag as
	 * sufficient the free setup nag can never clear on a PRO site, because
	 * the free wizard is never reached to set its own flag.
	 *
	 * @since 3.1.1
	 * @return bool
	 */
	public static function is_setup_complete() {
		return (bool) get_option( 'wbam_setup_complete' )
			|| (bool) get_option( 'wbam_setup_dismissed' )
			|| (bool) get_option( 'wbam_pro_setup_complete' );
	}

	/**
	 * Whether Pro is active and still has its own first-run wizard left to
	 * run. While true, Free defers to Pro instead of showing its own
	 * competing wizard - one guided setup, not two.
	 *
	 * @since 3.2.0
	 * @return bool
	 */
	public static function pro_wizard_pending() {
		return defined( 'WBAM_PRO_VERSION' ) && ! get_option( 'wbam_pro_setup_complete' );
	}

	/**
	 * Show setup notice.
	 */
	public function show_setup_notice() {
		if ( self::is_setup_complete() ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'wbam-setup' === $screen->id ) {
			return;
		}
		// One wizard: when Pro is active and hasn't finished its own first
		// run, send the admin straight there instead of into Free's wizard.
		$wizard_url = self::pro_wizard_pending()
			? admin_url( 'admin.php?page=wbam-setup-wizard' )
			: admin_url( 'index.php?page=wbam-setup' );
		?>
		<div class="notice notice-info wbam-setup-notice is-dismissible" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wbam_dismiss_setup' ) ); ?>">
			<p>
				<strong><?php esc_html_e( 'Welcome to WB Ad Manager!', 'wb-ads-rotator-with-split-test' ); ?></strong>
				<?php esc_html_e( 'Get started quickly with our setup wizard to create sample ads and configure basic settings.', 'wb-ads-rotator-with-split-test' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( $wizard_url ); ?>" class="button button-primary">
					<?php esc_html_e( 'Run Setup Wizard', 'wb-ads-rotator-with-split-test' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad' ) ); ?>" class="button">
					<?php esc_html_e( 'Skip Setup', 'wb-ads-rotator-with-split-test' ); ?>
				</a>
			</p>
		</div>
		<script>
		jQuery(function($) {
			$('.wbam-setup-notice').on('click', '.notice-dismiss', function() {
				$.post(ajaxurl, {
					action: 'wbam_dismiss_setup',
					nonce: $('.wbam-setup-notice').data('nonce')
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Dismiss setup notice.
	 */
	public function dismiss_setup() {
		check_ajax_referer( 'wbam_dismiss_setup', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'wb-ads-rotator-with-split-test' ) ) );
		}

		update_option( 'wbam_setup_dismissed', true );
		wp_send_json_success();
	}

	/**
	 * Setup wizard handler.
	 */
	public function setup_wizard() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['page'] ) || 'wbam-setup' !== $_GET['page'] ) {
			return;
		}

		// The wizard renders its own standalone page and exits from admin_init,
		// which runs BEFORE wp-admin/admin.php resolves the capability attached
		// to add_dashboard_page(). Core's gate therefore never runs for us, and
		// the page check above matches on any admin file — profile.php?page=wbam-setup
		// included, which subscribers can load. Without this check any logged-in
		// user could render the wizard, obtain a valid wbam_setup_sample nonce
		// and POST save_step to create sample ads. Authorize here or not at all.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// One wizard, not two competing ones. When Pro is active and its own
		// wizard hasn't finished, Pro owns first-run end to end (its step 1
		// picks the site mode Free has no notion of) - hand off instead of
		// rendering Free's own Welcome/Sample/Ready flow underneath it.
		if ( self::pro_wizard_pending() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=wbam-setup-wizard' ) );
			exit;
		}

		// Start output buffering early to allow redirects.
		ob_start();

		$this->steps = array(
			'welcome' => array(
				'name'    => __( 'Welcome', 'wb-ads-rotator-with-split-test' ),
				'view'    => array( $this, 'step_welcome' ),
				'handler' => '',
			),
			'sample'  => array(
				'name'    => __( 'Sample Ads', 'wb-ads-rotator-with-split-test' ),
				'view'    => array( $this, 'step_sample' ),
				'handler' => array( $this, 'step_sample_save' ),
			),
			'ready'   => array(
				'name'    => __( 'Ready', 'wb-ads-rotator-with-split-test' ),
				'view'    => array( $this, 'step_ready' ),
				'handler' => '',
			),
		);

		/**
		 * Filter the setup wizard steps.
		 *
		 * Allows developers to add, remove, or modify wizard steps.
		 *
		 * @since 2.3.0
		 * @param array $steps Array of wizard steps.
		 */
		$this->steps = apply_filters( 'wbam_setup_wizard_steps', $this->steps );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->step = isset( $_GET['step'] ) ? sanitize_key( $_GET['step'] ) : current( array_keys( $this->steps ) );

		// Handle save with nonce verification.
		if ( isset( $_POST['save_step'] ) && isset( $this->steps[ $this->step ]['handler'] ) && is_callable( $this->steps[ $this->step ]['handler'] ) ) {
			if ( ! isset( $_POST['wbam_setup_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wbam_setup_nonce'] ) ), 'wbam_setup_sample' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'wb-ads-rotator-with-split-test' ) );
			}
			call_user_func( $this->steps[ $this->step ]['handler'] );
		}

		// Enqueue styles. The wizard is a standalone full-screen page, but it
		// shares the one canonical --wbam-* token palette so its colours,
		// spacing and buttons match the rest of the plugin. setup-wizard.css
		// declares only the two tokens unique to the wizard and inherits the
		// base palette from admin-tokens.css.
		if ( ! wp_style_is( 'wbam-admin-tokens', 'registered' ) ) {
			wp_register_style(
				'wbam-admin-tokens',
				wbam_asset_url( 'css/admin-tokens.css' ),
				array(),
				WBAM_VERSION
			);
		}
		wp_enqueue_style( 'wbam-setup', wbam_asset_url( 'css/setup-wizard.css' ), array( 'wbam-admin-tokens' ), WBAM_VERSION );

		// Clean buffer and start fresh for page output.
		ob_end_clean();
		ob_start();
		$this->header();
		$this->steps_nav();
		$this->content();
		$this->footer();
		exit;
	}

	/**
	 * Wizard header.
	 */
	private function header() {
		set_current_screen();
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta name="viewport" content="width=device-width" />
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
			<title><?php esc_html_e( 'WB Ad Manager Setup', 'wb-ads-rotator-with-split-test' ); ?></title>
			<?php
			wp_enqueue_style( 'dashicons' );
			wp_enqueue_style( 'buttons' );
			// The wizard renders standalone HTML and never calls wp_head(), so
			// icons drawn with wbam_icon() (the megaphone logo, the Welcome
			// checkmarks) had no Lucide stylesheet or script and showed as empty
			// space. Print the Lucide style here; the script is printed in the
			// footer so it can hydrate the <i data-lucide> placeholders.
			if ( function_exists( 'wbam_register_lucide' ) ) {
				wbam_register_lucide();
			}
			// Tokens first so wbam-setup inherits them by cascade.
			wp_print_styles( array( 'dashicons', 'buttons', 'wbam-admin-tokens', 'wbam-lucide', 'wbam-setup' ) );
			?>
		</head>
		<body class="wbam-setup wp-core-ui">
			<div class="wbam-setup-wrapper">
				<h1 class="wbam-setup-logo">
					<?php echo wbam_icon( 'megaphone', array( 'size' => 'lg' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?>
					<?php esc_html_e( 'WB Ad Manager', 'wb-ads-rotator-with-split-test' ); ?>
				</h1>
		<?php
	}

	/**
	 * Steps navigation.
	 */
	private function steps_nav() {
		$step_keys = array_keys( $this->steps );
		?>
		<ol class="wbam-setup-steps">
			<?php
			foreach ( $this->steps as $key => $step ) :
				$is_completed = array_search( $this->step, $step_keys, true ) > array_search( $key, $step_keys, true );
				$is_current   = $this->step === $key;
				$class        = '';

				if ( $is_current ) {
					$class = 'active';
				} elseif ( $is_completed ) {
					$class = 'done';
				}
				?>
				<li class="<?php echo esc_attr( $class ); ?>">
					<?php echo esc_html( $step['name'] ); ?>
				</li>
			<?php endforeach; ?>
		</ol>
		<?php
	}

	/**
	 * Step content.
	 */
	private function content() {
		?>
		<div class="wbam-setup-content">
			<?php
			if ( isset( $this->steps[ $this->step ]['view'] ) ) {
				call_user_func( $this->steps[ $this->step ]['view'] );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Wizard footer.
	 */
	private function footer() {
		?>
			</div>
			<div class="wbam-setup-footer">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad' ) ); ?>">
					<?php esc_html_e( 'Exit Setup Wizard', 'wb-ads-rotator-with-split-test' ); ?>
				</a>
			</div>
			<?php
			// Print the Lucide script (with its DOMContentLoaded hydration) so
			// the <i data-lucide> icons above become real SVGs. The wizard skips
			// wp_footer(), so this must be explicit.
			wp_print_scripts( array( 'wbam-lucide' ) );
			?>
		</body>
		</html>
		<?php
	}

	/**
	 * Get next step URL.
	 *
	 * @return string
	 */
	private function get_next_step_link() {
		$step_keys = array_keys( $this->steps );
		$step_idx  = array_search( $this->step, $step_keys, true );
		$next_step = isset( $step_keys[ $step_idx + 1 ] ) ? $step_keys[ $step_idx + 1 ] : '';

		return add_query_arg( 'step', $next_step, admin_url( 'index.php?page=wbam-setup' ) );
	}

	/**
	 * Step: Welcome.
	 */
	public function step_welcome() {
		?>
		<div class="wbam-setup-step-content">
			<h2><?php esc_html_e( 'Welcome to WB Ad Manager!', 'wb-ads-rotator-with-split-test' ); ?></h2>
			<p><?php esc_html_e( 'Thank you for installing WB Ad Manager. This quick setup wizard will help you get started by:', 'wb-ads-rotator-with-split-test' ); ?></p>
			<ul class="wbam-setup-features">
				<li><?php echo wbam_icon( 'check', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?> <?php esc_html_e( 'Creating sample ads to demonstrate features', 'wb-ads-rotator-with-split-test' ); ?></li>
				<li><?php echo wbam_icon( 'check', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?> <?php esc_html_e( 'Setting up different ad placements', 'wb-ads-rotator-with-split-test' ); ?></li>
				<li><?php echo wbam_icon( 'check', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?> <?php esc_html_e( 'Getting you ready to manage your own ads', 'wb-ads-rotator-with-split-test' ); ?></li>
			</ul>
			<p class="wbam-setup-note">
				<?php esc_html_e( 'This wizard is optional. You can skip it and create ads manually anytime.', 'wb-ads-rotator-with-split-test' ); ?>
			</p>
			<p class="wbam-setup-actions">
				<a href="<?php echo esc_url( $this->get_next_step_link() ); ?>" class="button button-primary button-large">
					<?php esc_html_e( "Let's Go!", 'wb-ads-rotator-with-split-test' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad' ) ); ?>" class="button button-large">
					<?php esc_html_e( 'Skip Setup', 'wb-ads-rotator-with-split-test' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Step: Sample Ads.
	 */
	public function step_sample() {
		/**
		 * Filter available sample ad options in setup wizard.
		 *
		 * @since 2.3.0
		 * @param array $options Array of sample ad options.
		 */
		$sample_options = apply_filters(
			'wbam_setup_wizard_sample_options',
			array(
				'header_banner'  => array(
					'label'       => __( 'Header Banner', 'wb-ads-rotator-with-split-test' ),
					'description' => __( 'Image ad displayed in the header area', 'wb-ads-rotator-with-split-test' ),
					'checked'     => true,
				),
				'sidebar_widget' => array(
					'label'       => __( 'Sidebar Widget Ad', 'wb-ads-rotator-with-split-test' ),
					'description' => wp_is_block_theme()
						? __( 'Text ad for a sidebar. Your theme has no widget areas: show it with the [wbam_ad] shortcode.', 'wb-ads-rotator-with-split-test' )
						: __( 'Text ad, added to your first sidebar as a widget', 'wb-ads-rotator-with-split-test' ),
					'checked'     => true,
				),
				'content_promo'  => array(
					'label'       => __( 'In-Content Promotion', 'wb-ads-rotator-with-split-test' ),
					'description' => __( 'Rich content ad after paragraph 2', 'wb-ads-rotator-with-split-test' ),
					'checked'     => true,
				),
			)
		);

		/**
		 * Fires before the sample ads step content.
		 *
		 * @since 2.3.0
		 */
		do_action( 'wbam_setup_wizard_sample_before' );
		?>
		<div class="wbam-setup-step-content">
			<h2><?php esc_html_e( 'Create Sample Ads', 'wb-ads-rotator-with-split-test' ); ?></h2>
			<p><?php esc_html_e( 'Select which sample ads you would like to create. These will help you understand how different ad types and placements work.', 'wb-ads-rotator-with-split-test' ); ?></p>

			<form method="post">
				<?php wp_nonce_field( 'wbam_setup_sample', 'wbam_setup_nonce' ); ?>

				<?php
				/**
				 * Fires before the sample ads form fields.
				 *
				 * @since 2.3.0
				 */
				do_action( 'wbam_setup_wizard_sample_form_before' );
				?>

				<div class="wbam-sample-ads">
					<?php foreach ( $sample_options as $key => $option ) : ?>
						<label class="wbam-sample-ad-option">
							<input type="checkbox" name="sample_ads[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $option['checked'] ); ?> />
							<span class="wbam-sample-ad-info">
								<strong><?php echo esc_html( $option['label'] ); ?></strong>
								<span><?php echo esc_html( $option['description'] ); ?></span>
							</span>
						</label>
					<?php endforeach; ?>
				</div>

				<?php
				/**
				 * Fires after the sample ads form fields.
				 *
				 * @since 2.3.0
				 */
				do_action( 'wbam_setup_wizard_sample_form_after' );
				?>

				<p class="wbam-setup-actions">
					<button type="submit" name="save_step" value="1" class="button button-primary button-large">
						<?php esc_html_e( 'Create Sample Ads', 'wb-ads-rotator-with-split-test' ); ?>
					</button>
					<a href="<?php echo esc_url( $this->get_next_step_link() ); ?>" class="button button-large">
						<?php esc_html_e( 'Skip This Step', 'wb-ads-rotator-with-split-test' ); ?>
					</a>
				</p>
			</form>
		</div>
		<?php

		/**
		 * Fires after the sample ads step content.
		 *
		 * @since 2.3.0
		 */
		do_action( 'wbam_setup_wizard_sample_after' );
	}

	/**
	 * Step: Sample Ads - Save.
	 */
	public function step_sample_save() {
		// This creates ad posts. It is a public method reachable through the
		// wbam_setup_wizard_steps filter, so it authorizes itself rather than
		// trusting setup_wizard() to have done it.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Verify nonce.
		if ( ! isset( $_POST['wbam_setup_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wbam_setup_nonce'] ) ), 'wbam_setup_sample' ) ) {
			return;
		}

		$sample_ads = isset( $_POST['sample_ads'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['sample_ads'] ) ) : array();

		/**
		 * Fires before creating sample ads.
		 *
		 * @since 2.3.0
		 * @param array $sample_ads Selected sample ad keys.
		 */
		do_action( 'wbam_setup_wizard_sample_save_before', $sample_ads );

		if ( ! empty( $sample_ads ) ) {
			$this->create_sample_ads( $sample_ads );
		}

		/**
		 * Fires after creating sample ads.
		 *
		 * @since 2.3.0
		 * @param array $sample_ads Selected sample ad keys.
		 */
		do_action( 'wbam_setup_wizard_sample_save_after', $sample_ads );

		// Clean output buffer before redirect.
		if ( ob_get_level() ) {
			ob_end_clean();
		}

		wp_safe_redirect( $this->get_next_step_link() );
		exit;
	}

	/**
	 * Add a WB Ad widget showing the given ad to the first registered
	 * sidebar. No-op on themes without classic sidebars (block themes).
	 *
	 * @param int $ad_id Ad post ID.
	 */
	private function place_sample_widget( $ad_id ) {
		global $wp_registered_sidebars;

		$sidebar_ids = array_diff( array_keys( (array) $wp_registered_sidebars ), array( 'wp_inactive_widgets' ) );
		if ( empty( $sidebar_ids ) ) {
			return;
		}
		$sidebar_id = reset( $sidebar_ids );

		$instances = get_option( 'widget_wbam_ad_widget', array() );
		$instances = is_array( $instances ) ? $instances : array();
		$numbers   = array_filter( array_keys( $instances ), 'is_int' );
		$number    = $numbers ? max( $numbers ) + 1 : 2;

		$instances[ $number ]      = array(
			'title' => '',
			'ad_id' => $ad_id,
		);
		$instances['_multiwidget'] = 1;
		update_option( 'widget_wbam_ad_widget', $instances );

		$sidebars                = wp_get_sidebars_widgets();
		$sidebars[ $sidebar_id ] = isset( $sidebars[ $sidebar_id ] ) ? (array) $sidebars[ $sidebar_id ] : array();
		array_unshift( $sidebars[ $sidebar_id ], 'wbam_ad_widget-' . $number );
		wp_set_sidebars_widgets( $sidebars );
	}

	/**
	 * Create sample ads.
	 *
	 * @param array $ads_to_create List of sample ads to create.
	 */
	private function create_sample_ads( $ads_to_create ) {
		// Visitor-facing house promos: themed through the plugin's tokens (no
		// inline colours, so dark mode works), a bundled image instead of a
		// hot-linked one, and links that go somewhere real.
		$contact_url = home_url( '/' );
		$sample_ads  = array(
			'header_banner'  => array(
				'title'      => __( 'Sample Header Banner', 'wb-ads-rotator-with-split-test' ),
				'type'       => 'image',
				'placements' => array( 'header' ),
				'size'       => array( 728, 90 ),
				'data'       => array(
					'type'      => 'image',
					'image_url' => WBAM_URL . 'assets/images/sample-leaderboard.svg',
					'link_url'  => $contact_url,
					'alt_text'  => __( 'Advertise here. Put your brand in front of our readers.', 'wb-ads-rotator-with-split-test' ),
					'target'    => '_self',
				),
			),
			'sidebar_widget' => array(
				'title'      => __( 'Sample Sidebar Ad', 'wb-ads-rotator-with-split-test' ),
				'type'       => 'rich-content',
				'placements' => array( 'widget' ),
				'data'       => array(
					'type'    => 'rich-content',
					'content' => '<p><strong>' . esc_html__( 'Advertise here', 'wb-ads-rotator-with-split-test' ) . '</strong></p>'
						. '<p>' . esc_html__( 'Reach our readers with a spot in this sidebar.', 'wb-ads-rotator-with-split-test' ) . '</p>'
						. '<p><a href="' . esc_url( $contact_url ) . '">' . esc_html__( 'Get in touch', 'wb-ads-rotator-with-split-test' ) . '</a></p>',
				),
			),
			'content_promo'  => array(
				'title'      => __( 'Sample In-Content Promo', 'wb-ads-rotator-with-split-test' ),
				'type'       => 'rich-content',
				'placements' => array( 'after_paragraph' ),
				'data'       => array(
					'type'            => 'rich-content',
					'content'         => '<p><strong>' . esc_html__( 'Your message could be here', 'wb-ads-rotator-with-split-test' ) . '</strong></p>'
						. '<p>' . esc_html__( 'Readers see this spot in the middle of our most-read posts.', 'wb-ads-rotator-with-split-test' ) . ' <a href="' . esc_url( $contact_url ) . '">' . esc_html__( 'Advertise with us', 'wb-ads-rotator-with-split-test' ) . '</a></p>',
					'after_paragraph' => 2,
				),
			),
		);

		/**
		 * Filter the sample ads definitions.
		 *
		 * Allows developers to modify, add, or remove sample ad configurations.
		 *
		 * @since 2.3.0
		 * @param array $sample_ads     Array of sample ad definitions.
		 * @param array $ads_to_create  Keys of ads to be created.
		 */
		$sample_ads = apply_filters( 'wbam_setup_wizard_sample_ads', $sample_ads, $ads_to_create );

		foreach ( $ads_to_create as $ad_key ) {
			if ( ! isset( $sample_ads[ $ad_key ] ) ) {
				continue;
			}

			$ad = $sample_ads[ $ad_key ];

			// Create the ad post.
			$post_id = wp_insert_post(
				array(
					'post_title'  => $ad['title'],
					'post_type'   => 'wbam-ad',
					'post_status' => 'publish',
				)
			);

			if ( $post_id && ! is_wp_error( $post_id ) ) {
				// Set ad data.
				update_post_meta( $post_id, '_wbam_ad_data', $ad['data'] );
				update_post_meta( $post_id, '_wbam_placements', $ad['placements'] );
				update_post_meta( $post_id, '_wbam_enabled', '1' );
				update_post_meta( $post_id, '_wbam_priority', 5 );
				update_post_meta( $post_id, '_wbam_sample_ad', true );
				if ( isset( $ad['size'] ) ) {
					update_post_meta( $post_id, '_wbam_ad_format', \WBAM\Core\Ad_Formats::detect_by_dimensions( (int) $ad['size'][0], (int) $ad['size'][1] ) );
					update_post_meta( $post_id, '_wbam_ad_width', (int) $ad['size'][0] );
					update_post_meta( $post_id, '_wbam_ad_height', (int) $ad['size'][1] );
				}

				// Phase K: demo marker meta for safe one-click cleanup.
				// The Demo_Data_Cleaner double-checks this meta flag before
				// deletion so we never delete content the admin repurposed.
				update_post_meta( $post_id, '_wbam_is_demo', 1 );

				// Phase K: track the created ID on the option so the
				// cleanup action knows exactly which rows it owns.
				self::track_demo_id( 'ads', (int) $post_id );

				// A widget-placement ad only shows through a widget in a
				// sidebar, so the sample was invisible. Put it in one.
				if ( in_array( 'widget', (array) $ad['placements'], true ) ) {
					$this->place_sample_widget( (int) $post_id );
				}

				/**
				 * Fires after a sample ad is created.
				 *
				 * @since 2.3.0
				 * @param int    $post_id Post ID of the created ad.
				 * @param string $ad_key  Sample ad key.
				 * @param array  $ad      Sample ad configuration.
				 */
				do_action( 'wbam_setup_wizard_sample_ad_created', $post_id, $ad_key, $ad );
			}
		}
	}

	/**
	 * Append a newly-seeded demo row ID to the tracking option.
	 *
	 * Idempotent — re-running the wizard won't double-count. Exposed as
	 * a public static helper so other seeding paths (and the migration
	 * backfill in Installer) can feed the same registry.
	 *
	 * @since 2.8.0
	 *
	 * @param string $type Bucket key: 'ads', 'pages', 'links'.
	 * @param int    $id   ID of the created row.
	 */
	public static function track_demo_id( $type, $id ) {
		$id = (int) $id;
		if ( $id <= 0 ) {
			return;
		}

		$type    = sanitize_key( (string) $type );
		$allowed = array( 'ads', 'pages', 'links' );
		if ( ! in_array( $type, $allowed, true ) ) {
			return;
		}

		$registry = get_option( 'wbam_demo_data_ids', array() );
		if ( ! is_array( $registry ) ) {
			$registry = array();
		}

		foreach ( $allowed as $bucket ) {
			if ( ! isset( $registry[ $bucket ] ) || ! is_array( $registry[ $bucket ] ) ) {
				$registry[ $bucket ] = array();
			}
		}

		if ( ! in_array( $id, $registry[ $type ], true ) ) {
			$registry[ $type ][] = $id;
			update_option( 'wbam_demo_data_ids', $registry, false );
		}
	}

	/**
	 * Step: Ready.
	 */
	public function step_ready() {
		update_option( 'wbam_setup_complete', true );

		/**
		 * Fires when setup wizard is completed.
		 *
		 * @since 2.3.0
		 */
		do_action( 'wbam_setup_wizard_complete' );

		/**
		 * Filter the next steps shown on the ready screen.
		 *
		 * @since 2.3.0
		 * @param array $next_steps Array of next step configurations.
		 */
		$next_steps = apply_filters(
			'wbam_setup_wizard_next_steps',
			array(
				'view_ads' => array(
					'url'         => admin_url( 'edit.php?post_type=wbam-ad' ),
					'icon'        => 'file-text',
					'title'       => __( 'View Your Ads', 'wb-ads-rotator-with-split-test' ),
					'description' => __( 'See and manage all your ads', 'wb-ads-rotator-with-split-test' ),
				),
				'create'   => array(
					'url'         => admin_url( 'post-new.php?post_type=wbam-ad' ),
					'icon'        => 'plus-circle',
					'title'       => __( 'Create New Ad', 'wb-ads-rotator-with-split-test' ),
					'description' => __( 'Add your own custom ads', 'wb-ads-rotator-with-split-test' ),
				),
				'settings' => array(
					'url'         => admin_url( 'edit.php?post_type=wbam-ad&page=wbam-settings' ),
					'icon'        => 'settings-2',
					'title'       => __( 'Settings', 'wb-ads-rotator-with-split-test' ),
					'description' => __( 'Configure plugin options', 'wb-ads-rotator-with-split-test' ),
				),
			)
		);

		/**
		 * Fires before the ready step content.
		 *
		 * @since 2.3.0
		 */
		do_action( 'wbam_setup_wizard_ready_before' );
		?>
		<div class="wbam-setup-step-content wbam-setup-ready">
			<?php echo wbam_icon( 'check-circle', array( 'size' => 'xl' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?>
			<h2><?php esc_html_e( "You're All Set!", 'wb-ads-rotator-with-split-test' ); ?></h2>
			<p><?php esc_html_e( 'WB Ad Manager is ready to use. Here are some next steps:', 'wb-ads-rotator-with-split-test' ); ?></p>

			<div class="wbam-setup-next-steps">
				<?php foreach ( $next_steps as $step ) : ?>
					<a href="<?php echo esc_url( $step['url'] ); ?>" class="wbam-next-step">
						<?php echo wbam_icon( $step['icon'], array( 'size' => 'lg' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?>
						<strong><?php echo esc_html( $step['title'] ); ?></strong>
						<span><?php echo esc_html( $step['description'] ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>

			<?php
			/**
			 * Fires after the next steps links, before the dashboard button.
			 *
			 * @since 2.3.0
			 */
			do_action( 'wbam_setup_wizard_ready_after_steps' );
			?>

			<?php
			// Same button the Tools section renders — only shows itself
			// when sample data actually exists to remove. Same namespace
			// (WBAM\Admin), no `use` needed.
			Demo_Data_Cleaner::render_clear_button( __( 'Remove sample ads', 'wb-ads-rotator-with-split-test' ) );
			?>

			<p class="wbam-setup-actions">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad' ) ); ?>" class="button button-primary button-large">
					<?php esc_html_e( 'Go to Ads Dashboard', 'wb-ads-rotator-with-split-test' ); ?>
				</a>
			</p>
		</div>
		<?php

		/**
		 * Fires after the ready step content.
		 *
		 * @since 2.3.0
		 */
		do_action( 'wbam_setup_wizard_ready_after' );
	}
}
