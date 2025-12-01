<?php
/**
 * Settings Class
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Admin;

use WBAM\Core\Singleton;

/**
 * Settings class.
 */
class Settings {

	use Singleton;

	/**
	 * Option name.
	 */
	const OPTION_NAME = 'wbam_settings';

	/**
	 * Default settings.
	 *
	 * @var array
	 */
	private $defaults = array(
		'disable_ads_logged_in'  => false,
		'disable_ads_admin'      => true,
		'ad_label'               => '',
		'ad_label_position'      => 'above',
		'container_class'        => '',
		'disable_on_post_types'  => array(),
		'min_content_length'     => 0,
		'max_ads_per_page'       => 0,
		'cache_ads'              => false,
		'lazy_load'              => false,
		'geo_primary_provider'   => 'ip-api',
		'geo_ipinfo_key'         => '',
		'adsense_publisher_id'   => '',
		'adsense_auto_ads'       => false,
	);

	/**
	 * Initialize.
	 */
	public function init() {
		// Use priority 25 so Settings appears under PRO's Settings section header (priority 20) when PRO is active.
		add_action( 'admin_menu', array( $this, 'add_menu' ), 25 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add submenu page.
	 */
	public function add_menu() {
		// When PRO is active, rename to "Ad Settings" to avoid confusion with PRO's "Settings".
		$pro_active = defined( 'WBAM_PRO_VERSION' );
		$menu_title = $pro_active ? __( 'Ad Settings', 'wb-ad-manager' ) : __( 'Settings', 'wb-ad-manager' );

		add_submenu_page(
			'edit.php?post_type=wbam-ad',
			__( 'Ad Settings', 'wb-ad-manager' ),
			$menu_title,
			'manage_options',
			'wbam-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting(
			'wbam_settings_group',
			self::OPTION_NAME,
			array( $this, 'sanitize_settings' )
		);

		// General Section.
		add_settings_section(
			'wbam_general',
			__( 'General Settings', 'wb-ad-manager' ),
			array( $this, 'render_general_section' ),
			'wbam-settings'
		);

		add_settings_field(
			'disable_ads_logged_in',
			__( 'Disable for Logged-in Users', 'wb-ad-manager' ),
			array( $this, 'render_checkbox_field' ),
			'wbam-settings',
			'wbam_general',
			array(
				'id'          => 'disable_ads_logged_in',
				'description' => __( 'Hide ads for logged-in users.', 'wb-ad-manager' ),
			)
		);

		add_settings_field(
			'disable_ads_admin',
			__( 'Disable for Admins', 'wb-ad-manager' ),
			array( $this, 'render_checkbox_field' ),
			'wbam-settings',
			'wbam_general',
			array(
				'id'          => 'disable_ads_admin',
				'description' => __( 'Hide ads for administrators.', 'wb-ad-manager' ),
			)
		);

		add_settings_field(
			'min_content_length',
			__( 'Minimum Content Length', 'wb-ad-manager' ),
			array( $this, 'render_number_field' ),
			'wbam-settings',
			'wbam_general',
			array(
				'id'          => 'min_content_length',
				'description' => __( 'Minimum characters required to show paragraph ads. Set 0 to disable.', 'wb-ad-manager' ),
			)
		);

		add_settings_field(
			'disable_on_post_types',
			__( 'Disable on Post Types', 'wb-ad-manager' ),
			array( $this, 'render_post_types_field' ),
			'wbam-settings',
			'wbam_general',
			array(
				'id'          => 'disable_on_post_types',
				'description' => __( 'Select post types where ads should be disabled.', 'wb-ad-manager' ),
			)
		);

		add_settings_field(
			'max_ads_per_page',
			__( 'Maximum Ads Per Page', 'wb-ad-manager' ),
			array( $this, 'render_number_field' ),
			'wbam-settings',
			'wbam_general',
			array(
				'id'          => 'max_ads_per_page',
				'description' => __( 'Maximum number of ads to show per page. Set 0 for unlimited.', 'wb-ad-manager' ),
			)
		);

		// Display Section.
		add_settings_section(
			'wbam_display',
			__( 'Display Settings', 'wb-ad-manager' ),
			array( $this, 'render_display_section' ),
			'wbam-settings'
		);

		add_settings_field(
			'ad_label',
			__( 'Ad Label Text', 'wb-ad-manager' ),
			array( $this, 'render_text_field' ),
			'wbam-settings',
			'wbam_display',
			array(
				'id'          => 'ad_label',
				'placeholder' => __( 'e.g., Advertisement', 'wb-ad-manager' ),
				'description' => __( 'Optional label to display above/below ads.', 'wb-ad-manager' ),
			)
		);

		add_settings_field(
			'ad_label_position',
			__( 'Label Position', 'wb-ad-manager' ),
			array( $this, 'render_select_field' ),
			'wbam-settings',
			'wbam_display',
			array(
				'id'      => 'ad_label_position',
				'options' => array(
					'above' => __( 'Above Ad', 'wb-ad-manager' ),
					'below' => __( 'Below Ad', 'wb-ad-manager' ),
				),
			)
		);

		add_settings_field(
			'container_class',
			__( 'Custom Container Class', 'wb-ad-manager' ),
			array( $this, 'render_text_field' ),
			'wbam-settings',
			'wbam_display',
			array(
				'id'          => 'container_class',
				'placeholder' => __( 'e.g., my-ad-wrapper', 'wb-ad-manager' ),
				'description' => __( 'Additional CSS class for ad containers.', 'wb-ad-manager' ),
			)
		);

		// Performance Section.
		add_settings_section(
			'wbam_performance',
			__( 'Performance', 'wb-ad-manager' ),
			array( $this, 'render_performance_section' ),
			'wbam-settings'
		);

		add_settings_field(
			'lazy_load',
			__( 'Lazy Load Ads', 'wb-ad-manager' ),
			array( $this, 'render_checkbox_field' ),
			'wbam-settings',
			'wbam_performance',
			array(
				'id'          => 'lazy_load',
				'description' => __( 'Load ads only when they come into viewport.', 'wb-ad-manager' ),
			)
		);

		add_settings_field(
			'cache_ads',
			__( 'Cache Ad Queries', 'wb-ad-manager' ),
			array( $this, 'render_checkbox_field' ),
			'wbam-settings',
			'wbam_performance',
			array(
				'id'          => 'cache_ads',
				'description' => __( 'Cache ad queries for better performance (uses transients).', 'wb-ad-manager' ),
			)
		);

		// Geo Targeting Section.
		add_settings_section(
			'wbam_geo',
			__( 'Geo Targeting', 'wb-ad-manager' ),
			array( $this, 'render_geo_section' ),
			'wbam-settings'
		);

		add_settings_field(
			'geo_primary_provider',
			__( 'Primary Provider', 'wb-ad-manager' ),
			array( $this, 'render_geo_provider_field' ),
			'wbam-settings',
			'wbam_geo',
			array(
				'id' => 'geo_primary_provider',
			)
		);

		add_settings_field(
			'geo_ipinfo_key',
			__( 'ipinfo.io API Key', 'wb-ad-manager' ),
			array( $this, 'render_text_field' ),
			'wbam-settings',
			'wbam_geo',
			array(
				'id'          => 'geo_ipinfo_key',
				'placeholder' => __( 'Enter API key (optional)', 'wb-ad-manager' ),
				'description' => __( 'Get a free API key from ipinfo.io for 50K requests/month.', 'wb-ad-manager' ),
			)
		);

		// AdSense Section.
		add_settings_section(
			'wbam_adsense',
			__( 'Google AdSense', 'wb-ad-manager' ),
			array( $this, 'render_adsense_section' ),
			'wbam-settings'
		);

		add_settings_field(
			'adsense_publisher_id',
			__( 'Publisher ID', 'wb-ad-manager' ),
			array( $this, 'render_text_field' ),
			'wbam-settings',
			'wbam_adsense',
			array(
				'id'          => 'adsense_publisher_id',
				'placeholder' => 'ca-pub-1234567890123456',
				'description' => __( 'Your AdSense Publisher ID (e.g., ca-pub-1234567890123456). Used as default for all AdSense ads.', 'wb-ad-manager' ),
			)
		);

		add_settings_field(
			'adsense_auto_ads',
			__( 'Auto Ads', 'wb-ad-manager' ),
			array( $this, 'render_checkbox_field' ),
			'wbam-settings',
			'wbam_adsense',
			array(
				'id'          => 'adsense_auto_ads',
				'description' => __( 'Enable AdSense Auto Ads on your site. Google will automatically place ads.', 'wb-ad-manager' ),
			)
		);
	}

	/**
	 * Get settings.
	 *
	 * @return array
	 */
	public function get_settings() {
		$settings = get_option( self::OPTION_NAME, array() );
		return wp_parse_args( $settings, $this->defaults );
	}

	/**
	 * Get single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		$settings = $this->get_settings();
		if ( isset( $settings[ $key ] ) ) {
			return $settings[ $key ];
		}
		return $default !== null ? $default : ( isset( $this->defaults[ $key ] ) ? $this->defaults[ $key ] : null );
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Input settings.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		$sanitized['disable_ads_logged_in'] = ! empty( $input['disable_ads_logged_in'] );
		$sanitized['disable_ads_admin']     = ! empty( $input['disable_ads_admin'] );
		$sanitized['ad_label']              = sanitize_text_field( $input['ad_label'] ?? '' );
		$sanitized['ad_label_position']     = in_array( $input['ad_label_position'] ?? '', array( 'above', 'below' ), true ) ? $input['ad_label_position'] : 'above';
		$sanitized['container_class']       = sanitize_html_class( $input['container_class'] ?? '' );
		$sanitized['min_content_length']    = absint( $input['min_content_length'] ?? 0 );
		$sanitized['max_ads_per_page']      = absint( $input['max_ads_per_page'] ?? 0 );
		$sanitized['cache_ads']             = ! empty( $input['cache_ads'] );
		$sanitized['lazy_load']             = ! empty( $input['lazy_load'] );

		if ( ! empty( $input['disable_on_post_types'] ) && is_array( $input['disable_on_post_types'] ) ) {
			$sanitized['disable_on_post_types'] = array_map( 'sanitize_key', $input['disable_on_post_types'] );
		} else {
			$sanitized['disable_on_post_types'] = array();
		}

		// Geo targeting settings.
		$valid_providers                     = array( 'ip-api', 'ipinfo', 'ipapi-co' );
		$geo_provider                        = isset( $input['geo_primary_provider'] ) ? sanitize_key( $input['geo_primary_provider'] ) : 'ip-api';
		$sanitized['geo_primary_provider']   = in_array( $geo_provider, $valid_providers, true ) ? $geo_provider : 'ip-api';
		$sanitized['geo_ipinfo_key']         = sanitize_text_field( $input['geo_ipinfo_key'] ?? '' );

		// AdSense settings.
		$sanitized['adsense_publisher_id']   = sanitize_text_field( $input['adsense_publisher_id'] ?? '' );
		$sanitized['adsense_auto_ads']       = ! empty( $input['adsense_auto_ads'] );

		return $sanitized;
	}

	/**
	 * Render settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Standard WP Settings API pattern, nonce verified by options.php.
		if ( isset( $_GET['settings-updated'] ) ) {
			add_settings_error( 'wbam_messages', 'wbam_message', __( 'Settings saved.', 'wb-ad-manager' ), 'updated' );
		}
		?>
		<div class="wrap wbam-settings-wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php settings_errors( 'wbam_messages' ); ?>

			<div class="wbam-settings-container">
				<form action="options.php" method="post" class="wbam-settings-form">
					<?php
					settings_fields( 'wbam_settings_group' );
					do_settings_sections( 'wbam-settings' );
					submit_button( __( 'Save Settings', 'wb-ad-manager' ) );
					?>
				</form>

				<div class="wbam-settings-sidebar">
					<div class="wbam-sidebar-box">
						<h3><?php esc_html_e( 'Quick Links', 'wb-ad-manager' ); ?></h3>
						<ul>
							<li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad' ) ); ?>"><?php esc_html_e( 'All Ads', 'wb-ad-manager' ); ?></a></li>
							<li><a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wbam-ad' ) ); ?>"><?php esc_html_e( 'Add New Ad', 'wb-ad-manager' ); ?></a></li>
						</ul>
					</div>

					<div class="wbam-sidebar-box">
						<h3><?php esc_html_e( 'Shortcodes', 'wb-ad-manager' ); ?></h3>
						<p><code>[wbam_ad id="123"]</code></p>
						<p class="description"><?php esc_html_e( 'Display a single ad by ID.', 'wb-ad-manager' ); ?></p>
						<p><code>[wbam_ads ids="1,2,3"]</code></p>
						<p class="description"><?php esc_html_e( 'Display multiple ads.', 'wb-ad-manager' ); ?></p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render general section.
	 */
	public function render_general_section() {
		echo '<p>' . esc_html__( 'Configure general ad display settings.', 'wb-ad-manager' ) . '</p>';
	}

	/**
	 * Render display section.
	 */
	public function render_display_section() {
		echo '<p>' . esc_html__( 'Customize how ads appear on your site.', 'wb-ad-manager' ) . '</p>';
	}

	/**
	 * Render performance section.
	 */
	public function render_performance_section() {
		echo '<p>' . esc_html__( 'Optimize ad loading performance.', 'wb-ad-manager' ) . '</p>';
	}

	/**
	 * Render geo section.
	 */
	public function render_geo_section() {
		echo '<p>' . esc_html__( 'Configure IP geolocation providers for geo-targeting. The system will try providers in order until one succeeds.', 'wb-ad-manager' ) . '</p>';
	}

	/**
	 * Render AdSense section.
	 */
	public function render_adsense_section() {
		echo '<p>' . esc_html__( 'Configure Google AdSense integration. The AdSense script will only be loaded once, even with multiple ad units on a page.', 'wb-ad-manager' ) . '</p>';
	}

	/**
	 * Render geo provider field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_geo_provider_field( $args ) {
		$settings = $this->get_settings();
		$id       = $args['id'];
		$value    = isset( $settings[ $id ] ) ? $settings[ $id ] : 'ip-api';

		$providers = array(
			'ip-api'   => array(
				'name'  => 'ip-api.com',
				'limit' => __( '45 requests/minute, no API key', 'wb-ad-manager' ),
			),
			'ipinfo'   => array(
				'name'  => 'ipinfo.io',
				'limit' => __( '50K requests/month, API key optional', 'wb-ad-manager' ),
			),
			'ipapi-co' => array(
				'name'  => 'ipapi.co',
				'limit' => __( '1K requests/day, no API key', 'wb-ad-manager' ),
			),
		);
		?>
		<fieldset>
			<?php foreach ( $providers as $key => $provider ) : ?>
				<label style="display: block; margin-bottom: 8px;">
					<input type="radio" name="<?php echo esc_attr( self::OPTION_NAME . '[' . $id . ']' ); ?>" value="<?php echo esc_attr( $key ); ?>" <?php checked( $value, $key ); ?> />
					<strong><?php echo esc_html( $provider['name'] ); ?></strong>
					<span class="description" style="margin-left: 5px;">(<?php echo esc_html( $provider['limit'] ); ?>)</span>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<p class="description" style="margin-top: 10px;">
			<?php esc_html_e( 'If the primary provider fails, the system will automatically try the next provider.', 'wb-ad-manager' ); ?>
		</p>
		<?php
	}

	/**
	 * Render checkbox field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_checkbox_field( $args ) {
		$settings = $this->get_settings();
		$id       = $args['id'];
		$value    = isset( $settings[ $id ] ) ? $settings[ $id ] : false;
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME . '[' . $id . ']' ); ?>" value="1" <?php checked( $value ); ?> />
			<?php echo esc_html( $args['description'] ?? '' ); ?>
		</label>
		<?php
	}

	/**
	 * Render text field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_text_field( $args ) {
		$settings    = $this->get_settings();
		$id          = $args['id'];
		$value       = isset( $settings[ $id ] ) ? $settings[ $id ] : '';
		$placeholder = $args['placeholder'] ?? '';
		?>
		<input type="text" name="<?php echo esc_attr( self::OPTION_NAME . '[' . $id . ']' ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" class="regular-text" />
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render number field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_number_field( $args ) {
		$settings = $this->get_settings();
		$id       = $args['id'];
		$value    = isset( $settings[ $id ] ) ? $settings[ $id ] : 0;
		?>
		<input type="number" name="<?php echo esc_attr( self::OPTION_NAME . '[' . $id . ']' ); ?>" value="<?php echo esc_attr( $value ); ?>" min="0" class="small-text" />
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render select field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_select_field( $args ) {
		$settings = $this->get_settings();
		$id       = $args['id'];
		$value    = isset( $settings[ $id ] ) ? $settings[ $id ] : '';
		$options  = $args['options'] ?? array();
		?>
		<select name="<?php echo esc_attr( self::OPTION_NAME . '[' . $id . ']' ); ?>">
			<?php foreach ( $options as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render post types field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_post_types_field( $args ) {
		$settings   = $this->get_settings();
		$id         = $args['id'];
		$value      = isset( $settings[ $id ] ) ? (array) $settings[ $id ] : array();
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		?>
		<fieldset>
			<?php foreach ( $post_types as $post_type ) : ?>
				<?php if ( 'wbam-ad' === $post_type->name ) continue; ?>
				<label style="display: block; margin-bottom: 5px;">
					<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME . '[' . $id . '][]' ); ?>" value="<?php echo esc_attr( $post_type->name ); ?>" <?php checked( in_array( $post_type->name, $value, true ) ); ?> />
					<?php echo esc_html( $post_type->labels->name ); ?>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}
}
