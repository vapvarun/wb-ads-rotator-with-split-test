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
	 * These defaults are optimized for first-time users:
	 * - Ads visible to admins so they can test immediately
	 * - Performance features enabled for better page load
	 * - Ad label set for transparency/compliance
	 *
	 * @var array
	 */
	private $defaults = array(
		'disable_ads_logged_in'    => false,
		'disable_ads_admin'        => false,  // Show ads to admins so they can test.
		'ad_label'                 => 'Advertisement',
		'ad_label_position'        => 'above',
		'container_class'          => '',
		'disable_on_post_types'    => array(),
		'min_content_length'       => 300,    // Minimum content length for paragraph ads.
		'max_ads_per_page'         => 10,     // Sensible limit to prevent ad overload.
		'cache_ads'                => true,   // Better performance.
		'lazy_load'                => true,   // Better page load times.
		'geo_primary_provider'     => 'ip-api',
		'geo_ipinfo_key'           => '',
		'adsense_publisher_id'     => '',
		'adsense_auto_ads'         => false,
		'require_consent_adsense'  => false,  // Require consent before loading AdSense.
		'anonymize_ip'             => true,   // Anonymize IP addresses in stored data.
		'delete_data_on_uninstall' => false,
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
		$menu_title = $pro_active ? __( 'Ad Settings', 'wb-ads-rotator-with-split-test' ) : __( 'Settings', 'wb-ads-rotator-with-split-test' );

		add_submenu_page(
			'edit.php?post_type=wbam-ad',
			__( 'Ad Settings', 'wb-ads-rotator-with-split-test' ),
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
			__( 'General Settings', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_general_section' ),
			'wbam-settings'
		);

		add_settings_field(
			'disable_ads_logged_in',
			__( 'Disable for Logged-in Users', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_checkbox_field' ),
			'wbam-settings',
			'wbam_general',
			array(
				'id'          => 'disable_ads_logged_in',
				'description' => __( 'Hide ads for logged-in users.', 'wb-ads-rotator-with-split-test' ),
			)
		);

		add_settings_field(
			'disable_ads_admin',
			__( 'Disable for Admins', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_checkbox_field' ),
			'wbam-settings',
			'wbam_general',
			array(
				'id'          => 'disable_ads_admin',
				'description' => __( 'Hide ads for administrators.', 'wb-ads-rotator-with-split-test' ),
			)
		);

		add_settings_field(
			'min_content_length',
			__( 'Minimum Content Length', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_number_field' ),
			'wbam-settings',
			'wbam_general',
			array(
				'id'          => 'min_content_length',
				'description' => __( 'Minimum characters required to show paragraph ads. Set 0 to disable.', 'wb-ads-rotator-with-split-test' ),
			)
		);

		add_settings_field(
			'disable_on_post_types',
			__( 'Disable on Post Types', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_post_types_field' ),
			'wbam-settings',
			'wbam_general',
			array(
				'id'          => 'disable_on_post_types',
				'description' => __( 'Select post types where ads should be disabled.', 'wb-ads-rotator-with-split-test' ),
			)
		);

		add_settings_field(
			'max_ads_per_page',
			__( 'Maximum Ads Per Page', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_number_field' ),
			'wbam-settings',
			'wbam_general',
			array(
				'id'          => 'max_ads_per_page',
				'description' => __( 'Maximum number of ads to show per page. Set 0 for unlimited.', 'wb-ads-rotator-with-split-test' ),
			)
		);

		// Display Section.
		add_settings_section(
			'wbam_display',
			__( 'Display Settings', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_display_section' ),
			'wbam-settings'
		);

		add_settings_field(
			'ad_label',
			__( 'Ad Label Text', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_text_field' ),
			'wbam-settings',
			'wbam_display',
			array(
				'id'          => 'ad_label',
				'placeholder' => __( 'e.g., Advertisement', 'wb-ads-rotator-with-split-test' ),
				'description' => __( 'Optional label to display above/below ads.', 'wb-ads-rotator-with-split-test' ),
			)
		);

		add_settings_field(
			'ad_label_position',
			__( 'Label Position', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_select_field' ),
			'wbam-settings',
			'wbam_display',
			array(
				'id'      => 'ad_label_position',
				'options' => array(
					'above' => __( 'Above Ad', 'wb-ads-rotator-with-split-test' ),
					'below' => __( 'Below Ad', 'wb-ads-rotator-with-split-test' ),
				),
			)
		);

		add_settings_field(
			'container_class',
			__( 'Custom Container Class', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_text_field' ),
			'wbam-settings',
			'wbam_display',
			array(
				'id'          => 'container_class',
				'placeholder' => __( 'e.g., my-ad-wrapper', 'wb-ads-rotator-with-split-test' ),
				'description' => __( 'Additional CSS class for ad containers.', 'wb-ads-rotator-with-split-test' ),
			)
		);

		// Performance Section.
		add_settings_section(
			'wbam_performance',
			__( 'Performance', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_performance_section' ),
			'wbam-settings'
		);

		add_settings_field(
			'lazy_load',
			__( 'Lazy Load Ads', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_checkbox_field' ),
			'wbam-settings',
			'wbam_performance',
			array(
				'id'          => 'lazy_load',
				'description' => __( 'Load ads only when they come into viewport.', 'wb-ads-rotator-with-split-test' ),
			)
		);

		add_settings_field(
			'cache_ads',
			__( 'Cache Ad Queries', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_checkbox_field' ),
			'wbam-settings',
			'wbam_performance',
			array(
				'id'          => 'cache_ads',
				'description' => __( 'Cache ad queries for better performance (uses transients).', 'wb-ads-rotator-with-split-test' ),
			)
		);

		// Geo Targeting Section.
		add_settings_section(
			'wbam_geo',
			__( 'Geo Targeting', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_geo_section' ),
			'wbam-settings'
		);

		add_settings_field(
			'geo_primary_provider',
			__( 'Primary Provider', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_geo_provider_field' ),
			'wbam-settings',
			'wbam_geo',
			array(
				'id' => 'geo_primary_provider',
			)
		);

		add_settings_field(
			'geo_ipinfo_key',
			__( 'ipinfo.io API Key', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_text_field' ),
			'wbam-settings',
			'wbam_geo',
			array(
				'id'          => 'geo_ipinfo_key',
				'placeholder' => __( 'Enter API key (optional)', 'wb-ads-rotator-with-split-test' ),
				'description' => __( 'Get a free API key from ipinfo.io for 50K requests/month.', 'wb-ads-rotator-with-split-test' ),
			)
		);

		// AdSense Section.
		add_settings_section(
			'wbam_adsense',
			__( 'Google AdSense', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_adsense_section' ),
			'wbam-settings'
		);

		add_settings_field(
			'adsense_publisher_id',
			__( 'Publisher ID', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_text_field' ),
			'wbam-settings',
			'wbam_adsense',
			array(
				'id'          => 'adsense_publisher_id',
				'placeholder' => 'ca-pub-1234567890123456',
				'description' => __( 'Your AdSense Publisher ID (e.g., ca-pub-1234567890123456). Used as default for all AdSense ads.', 'wb-ads-rotator-with-split-test' ),
			)
		);

		add_settings_field(
			'adsense_auto_ads',
			__( 'Auto Ads', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_checkbox_field' ),
			'wbam-settings',
			'wbam_adsense',
			array(
				'id'          => 'adsense_auto_ads',
				'description' => __( 'Enable AdSense Auto Ads on your site. Google will automatically place ads.', 'wb-ads-rotator-with-split-test' ),
			)
		);

		// Privacy Section.
		add_settings_section(
			'wbam_privacy',
			__( 'Privacy & GDPR', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_privacy_section' ),
			'wbam-settings'
		);

		add_settings_field(
			'require_consent_adsense',
			__( 'Require Consent for AdSense', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_checkbox_field' ),
			'wbam-settings',
			'wbam_privacy',
			array(
				'id'          => 'require_consent_adsense',
				'description' => __( 'Only load AdSense scripts after user consent. Works with Cookie Notice, CookieYes, Complianz, and other consent plugins.', 'wb-ads-rotator-with-split-test' ),
			)
		);

		add_settings_field(
			'anonymize_ip',
			__( 'Anonymize IP Addresses', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_checkbox_field' ),
			'wbam-settings',
			'wbam_privacy',
			array(
				'id'          => 'anonymize_ip',
				'description' => __( 'Store anonymized IP hashes instead of raw IP addresses. Recommended for GDPR compliance.', 'wb-ads-rotator-with-split-test' ),
			)
		);

		// Advanced Section.
		add_settings_section(
			'wbam_advanced',
			__( 'Advanced', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_advanced_section' ),
			'wbam-settings'
		);

		add_settings_field(
			'delete_data_on_uninstall',
			__( 'Delete Data on Uninstall', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_checkbox_field' ),
			'wbam-settings',
			'wbam_advanced',
			array(
				'id'          => 'delete_data_on_uninstall',
				'description' => __( 'Delete all plugin data (ads, analytics, settings) when the plugin is uninstalled.', 'wb-ads-rotator-with-split-test' ),
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

		// Privacy settings.
		$sanitized['require_consent_adsense'] = ! empty( $input['require_consent_adsense'] );
		$sanitized['anonymize_ip']            = ! empty( $input['anonymize_ip'] );

		// Advanced settings.
		$sanitized['delete_data_on_uninstall'] = ! empty( $input['delete_data_on_uninstall'] );

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
			add_settings_error( 'wbam_messages', 'wbam_message', __( 'Settings saved.', 'wb-ads-rotator-with-split-test' ), 'updated' );
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
					submit_button( __( 'Save Settings', 'wb-ads-rotator-with-split-test' ) );
					?>
				</form>

				<div class="wbam-settings-sidebar">
					<div class="wbam-sidebar-box">
						<h3><?php esc_html_e( 'Quick Links', 'wb-ads-rotator-with-split-test' ); ?></h3>
						<ul>
							<li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad' ) ); ?>"><?php esc_html_e( 'All Ads', 'wb-ads-rotator-with-split-test' ); ?></a></li>
							<li><a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wbam-ad' ) ); ?>"><?php esc_html_e( 'Add New Ad', 'wb-ads-rotator-with-split-test' ); ?></a></li>
						</ul>
					</div>

					<div class="wbam-sidebar-box">
						<h3><?php esc_html_e( 'Shortcodes', 'wb-ads-rotator-with-split-test' ); ?></h3>
						<p><code>[wbam_ad id="123"]</code></p>
						<p class="description"><?php esc_html_e( 'Display a single ad by ID.', 'wb-ads-rotator-with-split-test' ); ?></p>
						<p><code>[wbam_ads ids="1,2,3"]</code></p>
						<p class="description"><?php esc_html_e( 'Display multiple ads.', 'wb-ads-rotator-with-split-test' ); ?></p>
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
		echo '<p>' . esc_html__( 'Configure general ad display settings.', 'wb-ads-rotator-with-split-test' ) . '</p>';
	}

	/**
	 * Render display section.
	 */
	public function render_display_section() {
		echo '<p>' . esc_html__( 'Customize how ads appear on your site.', 'wb-ads-rotator-with-split-test' ) . '</p>';
	}

	/**
	 * Render performance section.
	 */
	public function render_performance_section() {
		echo '<p>' . esc_html__( 'Optimize ad loading performance.', 'wb-ads-rotator-with-split-test' ) . '</p>';
	}

	/**
	 * Render geo section.
	 */
	public function render_geo_section() {
		echo '<p>' . esc_html__( 'Configure IP geolocation providers for geo-targeting. The system will try providers in order until one succeeds.', 'wb-ads-rotator-with-split-test' ) . '</p>';
	}

	/**
	 * Render AdSense section.
	 */
	public function render_adsense_section() {
		echo '<p>' . esc_html__( 'Configure Google AdSense integration. The AdSense script will only be loaded once, even with multiple ad units on a page.', 'wb-ads-rotator-with-split-test' ) . '</p>';
	}

	/**
	 * Render privacy section.
	 */
	public function render_privacy_section() {
		echo '<p>' . esc_html__( 'Configure privacy and GDPR compliance settings. These options help ensure your site respects user privacy.', 'wb-ads-rotator-with-split-test' ) . '</p>';
	}

	/**
	 * Render advanced section.
	 */
	public function render_advanced_section() {
		echo '<p>' . esc_html__( 'Advanced plugin settings.', 'wb-ads-rotator-with-split-test' ) . '</p>';
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
				'limit' => __( '45 requests/minute, no API key', 'wb-ads-rotator-with-split-test' ),
			),
			'ipinfo'   => array(
				'name'  => 'ipinfo.io',
				'limit' => __( '50K requests/month, API key optional', 'wb-ads-rotator-with-split-test' ),
			),
			'ipapi-co' => array(
				'name'  => 'ipapi.co',
				'limit' => __( '1K requests/day, no API key', 'wb-ads-rotator-with-split-test' ),
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
			<?php esc_html_e( 'If the primary provider fails, the system will automatically try the next provider.', 'wb-ads-rotator-with-split-test' ); ?>
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
