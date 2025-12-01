<?php
/**
 * Setup Wizard Class
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Admin;

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
	}

	/**
	 * Show setup notice.
	 */
	public function show_setup_notice() {
		if ( get_option( 'wbam_setup_complete' ) || get_option( 'wbam_setup_dismissed' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'wbam-setup' === $screen->id ) {
			return;
		}
		?>
		<div class="notice notice-info wbam-setup-notice is-dismissible" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wbam_dismiss_setup' ) ); ?>">
			<p>
				<strong><?php esc_html_e( 'Welcome to WB Ad Manager!', 'wb-ad-manager' ); ?></strong>
				<?php esc_html_e( 'Get started quickly with our setup wizard to create sample ads and configure basic settings.', 'wb-ad-manager' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'index.php?page=wbam-setup' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Run Setup Wizard', 'wb-ad-manager' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad' ) ); ?>" class="button">
					<?php esc_html_e( 'Skip Setup', 'wb-ad-manager' ); ?>
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

		$this->steps = array(
			'welcome' => array(
				'name'    => __( 'Welcome', 'wb-ad-manager' ),
				'view'    => array( $this, 'step_welcome' ),
				'handler' => '',
			),
			'sample'  => array(
				'name'    => __( 'Sample Ads', 'wb-ad-manager' ),
				'view'    => array( $this, 'step_sample' ),
				'handler' => array( $this, 'step_sample_save' ),
			),
			'ready'   => array(
				'name'    => __( 'Ready', 'wb-ad-manager' ),
				'view'    => array( $this, 'step_ready' ),
				'handler' => '',
			),
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->step = isset( $_GET['step'] ) ? sanitize_key( $_GET['step'] ) : current( array_keys( $this->steps ) );

		// Handle save.
		if ( ! empty( $_POST['save_step'] ) && isset( $this->steps[ $this->step ]['handler'] ) ) {
			call_user_func( $this->steps[ $this->step ]['handler'] );
		}

		// Enqueue styles.
		wp_enqueue_style( 'wbam-setup', WBAM_URL . 'assets/css/setup-wizard.css', array(), WBAM_VERSION );

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
			<title><?php esc_html_e( 'WB Ad Manager Setup', 'wb-ad-manager' ); ?></title>
			<?php wp_print_styles( 'wbam-setup' ); ?>
			<?php do_action( 'admin_print_styles' ); ?>
		</head>
		<body class="wbam-setup wp-core-ui">
			<div class="wbam-setup-wrapper">
				<h1 class="wbam-setup-logo">
					<span class="dashicons dashicons-megaphone"></span>
					<?php esc_html_e( 'WB Ad Manager', 'wb-ad-manager' ); ?>
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
					<?php esc_html_e( 'Exit Setup Wizard', 'wb-ad-manager' ); ?>
				</a>
			</div>
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
			<h2><?php esc_html_e( 'Welcome to WB Ad Manager!', 'wb-ad-manager' ); ?></h2>
			<p><?php esc_html_e( 'Thank you for installing WB Ad Manager. This quick setup wizard will help you get started by:', 'wb-ad-manager' ); ?></p>
			<ul class="wbam-setup-features">
				<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Creating sample ads to demonstrate features', 'wb-ad-manager' ); ?></li>
				<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Setting up different ad placements', 'wb-ad-manager' ); ?></li>
				<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Getting you ready to manage your own ads', 'wb-ad-manager' ); ?></li>
			</ul>
			<p class="wbam-setup-note">
				<?php esc_html_e( 'This wizard is optional. You can skip it and create ads manually anytime.', 'wb-ad-manager' ); ?>
			</p>
			<p class="wbam-setup-actions">
				<a href="<?php echo esc_url( $this->get_next_step_link() ); ?>" class="button button-primary button-large">
					<?php esc_html_e( "Let's Go!", 'wb-ad-manager' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad' ) ); ?>" class="button button-large">
					<?php esc_html_e( 'Skip Setup', 'wb-ad-manager' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Step: Sample Ads.
	 */
	public function step_sample() {
		?>
		<div class="wbam-setup-step-content">
			<h2><?php esc_html_e( 'Create Sample Ads', 'wb-ad-manager' ); ?></h2>
			<p><?php esc_html_e( 'Select which sample ads you would like to create. These will help you understand how different ad types and placements work.', 'wb-ad-manager' ); ?></p>

			<form method="post">
				<?php wp_nonce_field( 'wbam_setup_sample', 'wbam_setup_nonce' ); ?>

				<div class="wbam-sample-ads">
					<label class="wbam-sample-ad-option">
						<input type="checkbox" name="sample_ads[]" value="header_banner" checked />
						<span class="wbam-sample-ad-info">
							<strong><?php esc_html_e( 'Header Banner', 'wb-ad-manager' ); ?></strong>
							<span><?php esc_html_e( 'Image ad displayed in the header area', 'wb-ad-manager' ); ?></span>
						</span>
					</label>

					<label class="wbam-sample-ad-option">
						<input type="checkbox" name="sample_ads[]" value="sidebar_widget" checked />
						<span class="wbam-sample-ad-info">
							<strong><?php esc_html_e( 'Sidebar Widget Ad', 'wb-ad-manager' ); ?></strong>
							<span><?php esc_html_e( 'Code ad for sidebar widget placement', 'wb-ad-manager' ); ?></span>
						</span>
					</label>

					<label class="wbam-sample-ad-option">
						<input type="checkbox" name="sample_ads[]" value="content_promo" checked />
						<span class="wbam-sample-ad-info">
							<strong><?php esc_html_e( 'In-Content Promotion', 'wb-ad-manager' ); ?></strong>
							<span><?php esc_html_e( 'Rich content ad after paragraph 2', 'wb-ad-manager' ); ?></span>
						</span>
					</label>
				</div>

				<p class="wbam-setup-actions">
					<button type="submit" name="save_step" class="button button-primary button-large">
						<?php esc_html_e( 'Create Sample Ads', 'wb-ad-manager' ); ?>
					</button>
					<a href="<?php echo esc_url( $this->get_next_step_link() ); ?>" class="button button-large">
						<?php esc_html_e( 'Skip This Step', 'wb-ad-manager' ); ?>
					</a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Step: Sample Ads - Save.
	 */
	public function step_sample_save() {
		check_admin_referer( 'wbam_setup_sample', 'wbam_setup_nonce' );

		$sample_ads = isset( $_POST['sample_ads'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['sample_ads'] ) ) : array();

		if ( ! empty( $sample_ads ) ) {
			$this->create_sample_ads( $sample_ads );
		}

		wp_safe_redirect( $this->get_next_step_link() );
		exit;
	}

	/**
	 * Create sample ads.
	 *
	 * @param array $ads_to_create List of sample ads to create.
	 */
	private function create_sample_ads( $ads_to_create ) {
		$sample_ads = array(
			'header_banner'  => array(
				'title'      => __( 'Sample Header Banner', 'wb-ad-manager' ),
				'type'       => 'image',
				'placements' => array( 'header' ),
				'data'       => array(
					'type'      => 'image',
					'image_url' => 'https://via.placeholder.com/728x90/4a90d9/ffffff?text=Header+Banner+Ad',
					'link_url'  => '#',
					'alt_text'  => __( 'Sample Header Banner', 'wb-ad-manager' ),
					'new_tab'   => true,
				),
			),
			'sidebar_widget' => array(
				'title'      => __( 'Sample Sidebar Ad', 'wb-ad-manager' ),
				'type'       => 'code',
				'placements' => array( 'widget' ),
				'data'       => array(
					'type' => 'code',
					'code' => '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 8px; text-align: center; color: #fff;">
	<h4 style="margin: 0 0 10px; font-size: 18px;">' . esc_html__( 'Advertise Here!', 'wb-ad-manager' ) . '</h4>
	<p style="margin: 0 0 15px; font-size: 14px;">' . esc_html__( 'This is a sample sidebar ad using custom HTML code.', 'wb-ad-manager' ) . '</p>
	<a href="#" style="display: inline-block; background: #fff; color: #667eea; padding: 8px 20px; border-radius: 4px; text-decoration: none; font-weight: bold;">' . esc_html__( 'Learn More', 'wb-ad-manager' ) . '</a>
</div>',
				),
			),
			'content_promo'  => array(
				'title'      => __( 'Sample In-Content Promo', 'wb-ad-manager' ),
				'type'       => 'rich_content',
				'placements' => array( 'after_paragraph' ),
				'data'       => array(
					'type'            => 'rich_content',
					'content'         => '<div style="background: #f8f9fa; border-left: 4px solid #28a745; padding: 15px 20px; margin: 20px 0; border-radius: 4px;">
	<strong style="color: #28a745;">💡 ' . esc_html__( 'Pro Tip:', 'wb-ad-manager' ) . '</strong>
	<p style="margin: 10px 0 0;">' . esc_html__( 'This is a sample in-content promotion. It appears after paragraph 2 in your posts. Great for newsletter signups, related content, or special offers!', 'wb-ad-manager' ) . '</p>
</div>',
					'after_paragraph' => 2,
				),
			),
		);

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
			}
		}
	}

	/**
	 * Step: Ready.
	 */
	public function step_ready() {
		update_option( 'wbam_setup_complete', true );
		?>
		<div class="wbam-setup-step-content wbam-setup-ready">
			<span class="dashicons dashicons-yes-alt"></span>
			<h2><?php esc_html_e( "You're All Set!", 'wb-ad-manager' ); ?></h2>
			<p><?php esc_html_e( 'WB Ad Manager is ready to use. Here are some next steps:', 'wb-ad-manager' ); ?></p>

			<div class="wbam-setup-next-steps">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad' ) ); ?>" class="wbam-next-step">
					<span class="dashicons dashicons-admin-post"></span>
					<strong><?php esc_html_e( 'View Your Ads', 'wb-ad-manager' ); ?></strong>
					<span><?php esc_html_e( 'See and manage all your ads', 'wb-ad-manager' ); ?></span>
				</a>

				<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wbam-ad' ) ); ?>" class="wbam-next-step">
					<span class="dashicons dashicons-plus-alt"></span>
					<strong><?php esc_html_e( 'Create New Ad', 'wb-ad-manager' ); ?></strong>
					<span><?php esc_html_e( 'Add your own custom ads', 'wb-ad-manager' ); ?></span>
				</a>

				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-settings' ) ); ?>" class="wbam-next-step">
					<span class="dashicons dashicons-admin-settings"></span>
					<strong><?php esc_html_e( 'Settings', 'wb-ad-manager' ); ?></strong>
					<span><?php esc_html_e( 'Configure plugin options', 'wb-ad-manager' ); ?></span>
				</a>
			</div>

			<p class="wbam-setup-actions">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad' ) ); ?>" class="button button-primary button-large">
					<?php esc_html_e( 'Go to Ads Dashboard', 'wb-ad-manager' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
}
