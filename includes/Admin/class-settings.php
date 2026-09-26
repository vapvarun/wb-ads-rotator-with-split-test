<?php
/**
 * Settings Class
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Admin;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
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
		// Off so an upgrade does not shift existing impression counts.
		'viewable_impressions'     => false,
		'disable_on_post_types'    => array(),
		'max_ads_per_page'         => 10,     // Sensible limit to prevent ad overload.
		// Geolocation (owner decision 8, 3.2.0): off by default on a fresh
		// install - no visitor IP is looked up, let alone sent to a third
		// party, until the owner opts in and picks a provider. Installer
		// stamps geo_enabled = true on upgrade from a pre-3.2.0 DB version
		// so an existing site's ad geo rules and PRO country analytics keep
		// working; see Installer::maybe_set_geo_enabled_default(). Empty
		// provider string means "not chosen yet" - deliberately not
		// defaulted to a provider so the owner makes an explicit pick.
		'geo_enabled'              => false,
		'geo_primary_provider'     => '',
		'geo_ipinfo_key'           => '',
		'geo_maxmind_db_path'      => '',
		'adsense_publisher_id'     => '',
		'adsense_auto_ads'         => false,
		'require_consent_adsense'  => false,  // Require consent before loading AdSense.
		'anonymize_ip'             => true,   // Anonymize IP addresses in stored data.
		'delete_data_on_uninstall' => false,
		// Link cloaking. Read at runtime by Link_Cloaker but previously had no
		// UI (the wbam_settings_tabs/_fields filter framework was never applied),
		// so the cloak prefix and inactive-link behaviour were unconfigurable.
		'link_cloak_prefix'        => 'go',
		'link_inactive_action'     => '404',
		'link_inactive_url'        => '',
		// Placement gates. Empty array means ALL placements — never "none".
		// See Settings_Helper::enabled_placements().
		'enabled_placements'       => array(),
		'advertiser_placements'    => array(),
	);

	/**
	 * Initialize.
	 */
	public function init() {
		// Use priority 25 so Settings appears under PRO's Settings section header (priority 20) when PRO is active.
		add_action( 'admin_menu', array( $this, 'add_menu' ), 25 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_notices', array( $this, 'maybe_render_legacy_geo_notice' ) );
	}

	/**
	 * Sitewide notice recommending a switch off a legacy geo provider.
	 *
	 * Owner decision 8: an install upgraded from before 3.2.0 keeps its
	 * existing provider working (Installer::maybe_set_geo_enabled_default()),
	 * but ip-api.com/ipapi.co are no longer offered as a new choice. Shown
	 * on any WB Ad Manager admin screen — not just the Settings page — so an
	 * owner who never opens Settings still sees the recommendation. WP core
	 * `is-dismissible` hides it for the current page view; it reappears on
	 * the next screen until the provider is actually switched, which is
	 * deliberate for a standing configuration recommendation rather than a
	 * one-time announcement.
	 *
	 * @since 3.2.0
	 */
	public function maybe_render_legacy_geo_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Shows only on the WB Ad Manager Dashboard (All Ads - the plugin's
		// own overview/landing screen) and Settings > Location (owner
		// decision, admin polish audit item 3a: "notices only where they
		// act") - never on every WBAM screen at once. Settings > Location
		// gets its own copy inline, inside the Geo Targeting card (see
		// render_geo_section()), so this global hook only needs to cover
		// the Dashboard - showing it here too would repeat it on that one
		// page (the QA reject this fixes).
		// Match the All Ads screen by id: every WBAM submenu page lives under
		// edit.php?post_type=wbam-ad, so post_type alone matches all of them.
		$screen = get_current_screen();
		if ( ! $screen || 'edit-wbam-ad' !== $screen->id ) {
			return;
		}

		$notice = $this->get_legacy_geo_provider_notice();
		if ( ! $notice ) {
			return;
		}

		// Geolocation now lives in its own top-level Location section rather
		// than a sub-nav pill inside "Ad Display", so a plain section link is
		// enough — no anchor/pill-activation hand-off needed any more.
		$settings_url = \WBAM\Core\Admin_Links::settings( 'location' );
		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<?php echo wp_kses_post( $notice ); ?>
				<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Open Location settings', 'wb-ads-rotator-with-split-test' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Add submenu page.
	 *
	 * `wbam-settings` is the ONE Settings screen for both plugins: a left
	 * sidebar of sections (General, Ad Display, Classifieds, Credits, ...).
	 * Free supplies its own display settings as the "Ad Display" section;
	 * PRO maps its settings tabs into the same sidebar via the
	 * `wbam_settings_sections` filter instead of registering its own
	 * "Settings" submenu. See render_page() / get_sections().
	 *
	 * @since 3.2.0 Was two separate screens (Free "Ad Display" +/or PRO
	 *              "Settings"); merged into one sidebar screen.
	 */
	public function add_menu() {
		$title = __( 'Settings', 'wb-ads-rotator-with-split-test' );

		add_submenu_page(
			'edit.php?post_type=wbam-ad',
			$title,
			$title,
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

		// Features Section - which parts of the plugin this site uses.
		//
		// PRO lists these same modules on its Modules tab alongside its own, so
		// the site owner has one Modules screen rather than one per plugin.
		// This section is therefore only rendered when PRO is absent. The
		// stored value lives here either way, so the toggle keeps working if
		// PRO is later deactivated.
		if ( ! defined( 'WBAM_PRO_VERSION' ) ) {
			$this->register_feature_settings();
		}

		// General Section.
		$this->register_general_settings();
	}

	/**
	 * Register the Features section (FREE-only installs).
	 *
	 * @since 2.9.2
	 */
	private function register_feature_settings() {
		add_settings_section(
			'wbam_features',
			__( 'Features', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_features_section' ),
			'wbam-settings'
		);

		add_settings_field(
			'module_links',
			__( 'Link Manager', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_module_field' ),
			'wbam-settings',
			'wbam_features',
			array(
				'id'          => 'links',
				'label'       => __( 'Enable the Link Manager', 'wb-ads-rotator-with-split-test' ),
				'description' => __( 'Adds the Links menu for managing outbound and affiliate links, link categories and partnership inquiries. Turn this off if you only run ads - your existing link data is kept and reappears if you switch it back on.', 'wb-ads-rotator-with-split-test' ),
			)
		);
	}

	/**
	 * Register every settings section other than Features.
	 *
	 * @since 2.9.2
	 */
	private function register_general_settings() {
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
				'label_for'   => 'wbam_setting_max_ads_per_page',
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
				'label_for'   => 'wbam_setting_ad_label',
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
				'label_for' => 'wbam_setting_ad_label_position',
				'id'        => 'ad_label_position',
				'options'   => array(
					'above' => __( 'Above Ad', 'wb-ads-rotator-with-split-test' ),
					'below' => __( 'Below Ad', 'wb-ads-rotator-with-split-test' ),
				),
			)
		);

		// "Custom Container Class" (container_class) is plug-and-play (owner
		// decision, card 10343726590): no settings field any more. A site
		// that already saved a class keeps using it — see
		// `wbam_ad_container_class` in Placement_Engine::render_placement(),
		// whose default is this site's already-stored value. A developer who
		// wants a class on a fresh install uses that filter instead.

		// "Count Impressions When Seen" (viewable_impressions) is plug-and-play
		// (owner decision, card 10343706274): no settings field any more. A
		// site that already had this on keeps counting this way — see
		// `wbam_viewable_impressions` in Frontend::defers_impression(), whose
		// default is this site's already-stored value. A developer who wants
		// it on a fresh install uses that filter instead.

		// Placements Section. The matrix is section-level content, not a
		// settings field - it renders from render_placements_section() below
		// so it gets the full content width instead of being squeezed into
		// the Settings API's <td> next to a <th> label column. See
		// render_placements_section() for why.
		add_settings_section(
			'wbam_placements',
			__( 'Placements', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_placements_section' ),
			'wbam-settings'
		);

		add_settings_field(
			'format_matching',
			__( 'Format Matching', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_checkbox_field' ),
			'wbam-settings',
			'wbam_placements',
			array(
				'id'          => 'format_matching',
				'default'     => \WBAM\Core\Settings_Helper::format_matching_enabled(),
				'description' => __( 'Only show an ad in placements that accept its size format, so oversize creatives never break the layout.', 'wb-ads-rotator-with-split-test' ),
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
			'geo_enabled',
			__( 'Geolocation', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_checkbox_field' ),
			'wbam-settings',
			'wbam_geo',
			array(
				'label_for'   => 'wbam_setting_geo_enabled',
				'id'          => 'geo_enabled',
				'description' => __( "Allow country/region lookup for each visitor's IP address.", 'wb-ads-rotator-with-split-test' ),
			)
		);

		add_settings_field(
			'geo_primary_provider',
			__( 'Provider', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_geo_provider_field' ),
			'wbam-settings',
			'wbam_geo',
			array(
				'id' => 'geo_primary_provider',
			)
		);

		add_settings_field(
			'geo_maxmind_db_path',
			__( 'MaxMind Database Path', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_text_field' ),
			'wbam-settings',
			'wbam_geo',
			array(
				'label_for'   => 'wbam_setting_geo_maxmind_db_path',
				'id'          => 'geo_maxmind_db_path',
				'placeholder' => __( '/absolute/path/to/GeoLite2-Country.mmdb', 'wb-ads-rotator-with-split-test' ),
				'description' => __( 'Absolute server path to a GeoLite2 (or GeoIP2) .mmdb file you downloaded from your own MaxMind account. Nothing is sent anywhere for this provider.', 'wb-ads-rotator-with-split-test' ),
				'wrapper'     => 'wbam-geo-provider-field wbam-geo-provider-maxmind',
			)
		);

		add_settings_field(
			'geo_ipinfo_key',
			__( 'ipinfo.io API Key', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_text_field' ),
			'wbam-settings',
			'wbam_geo',
			array(
				'label_for'   => 'wbam_setting_geo_ipinfo_key',
				'id'          => 'geo_ipinfo_key',
				'placeholder' => __( 'Enter your ipinfo.io API key', 'wb-ads-rotator-with-split-test' ),
				'description' => __( 'Required. Get a free key from ipinfo.io (50K requests/month). The visitor\'s IP is sent to ipinfo.io over HTTPS to resolve it.', 'wb-ads-rotator-with-split-test' ),
				'wrapper'     => 'wbam-geo-provider-field wbam-geo-provider-ipinfo',
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
				'label_for'   => 'wbam_setting_adsense_publisher_id',
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

		// Grouped with AdSense (not a separate Privacy card) since consent
		// only gates AdSense's own script load — see sanitize_settings(),
		// unchanged: still `anonymize_ip`'s neighbour there, only where it
		// renders moved.
		add_settings_field(
			'require_consent_adsense',
			__( 'Require Consent for AdSense', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_checkbox_field' ),
			'wbam-settings',
			'wbam_adsense',
			array(
				'id'          => 'require_consent_adsense',
				'description' => __( 'Only load AdSense scripts after user consent. Works with Cookie Notice, CookieYes, Complianz, and other consent plugins.', 'wb-ads-rotator-with-split-test' ),
			)
		);

		// Privacy & Data section (shared with PRO's GDPR/analytics card when
		// PRO is active — see Pro_Admin::map_settings_sections()).
		add_settings_section(
			'wbam_privacy',
			__( 'Privacy & GDPR', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_privacy_section' ),
			'wbam-settings'
		);

		// Owner decision (3.2.0): with PRO active, PRO's own
		// `wbam_pro_settings[gdpr_anonymize_ip]` is the ONE Anonymize IP
		// switch shown (Privacy & Data section) — this field is not
		// registered at all in that case, so it never renders anywhere.
		// FREE's stored `anonymize_ip` value is left exactly as-is: no
		// contract hidden input for it means no settings write ever touches
		// it (see sanitize_settings()'s array_intersect_key() merge), and a
		// FREE-only site keeps rendering and saving this switch normally.
		if ( ! defined( 'WBAM_PRO_VERSION' ) ) {
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
		}

		// Danger Zone section (formerly "Advanced" - the only field here is
		// destructive, so the card is styled and ordered as one). Rendered
		// last on the Privacy & Data section, after every other card
		// (owner decision, admin polish audit PV1) - see
		// render_settings_page_sections()'s `wbam-card--danger` class and
		// render_privacy_page()'s render order.
		add_settings_section(
			'wbam_advanced',
			__( 'Danger Zone', 'wb-ads-rotator-with-split-test' ),
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

		// Link Cloaking Section. These three keys are read at runtime by
		// Link_Cloaker; they used to be defined in a wbam_settings_fields filter
		// that nothing ever rendered, so they were unconfigurable.
		add_settings_section(
			'wbam_links',
			__( 'Link Cloaking', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_links_section' ),
			'wbam-settings'
		);

		add_settings_field(
			'link_cloak_prefix',
			__( 'Link URL Prefix', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_text_field' ),
			'wbam-settings',
			'wbam_links',
			array(
				'label_for'   => 'wbam_setting_link_cloak_prefix',
				'id'          => 'link_cloak_prefix',
				'placeholder' => 'go',
				/* translators: %s: example cloaked URL */
				'description' => sprintf( __( 'The path segment for your cloaked links, e.g. %s. Changing this updates the URL every cloaked link uses.', 'wb-ads-rotator-with-split-test' ), home_url( '/go/your-link' ) ),
			)
		);

		add_settings_field(
			'link_inactive_action',
			__( 'Inactive Link Action', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_select_field' ),
			'wbam-settings',
			'wbam_links',
			array(
				'label_for'   => 'wbam_setting_link_inactive_action',
				'id'          => 'link_inactive_action',
				'options'     => array(
					'404'    => __( 'Show 404 page', 'wb-ads-rotator-with-split-test' ),
					'home'   => __( 'Redirect to homepage', 'wb-ads-rotator-with-split-test' ),
					'custom' => __( 'Redirect to custom URL', 'wb-ads-rotator-with-split-test' ),
				),
				'description' => __( 'What happens when an inactive or expired cloaked link is accessed.', 'wb-ads-rotator-with-split-test' ),
			)
		);

		add_settings_field(
			'link_inactive_url',
			__( 'Inactive Link URL', 'wb-ads-rotator-with-split-test' ),
			array( $this, 'render_text_field' ),
			'wbam-settings',
			'wbam_links',
			array(
				'label_for'   => 'wbam_setting_link_inactive_url',
				'id'          => 'link_inactive_url',
				'placeholder' => 'https://example.com/gone',
				'description' => __( 'Used only when the action above is "Redirect to custom URL".', 'wb-ads-rotator-with-split-test' ),
			)
		);
	}

	/**
	 * Render the Link Cloaking section intro.
	 */
	public function render_links_section() {
		echo '<p>' . esc_html__( 'Cloaked links send visitors through your own site (e.g. yoursite.com/go/deal) before redirecting to the real destination, so the long affiliate or tracking URL never shows in your content. Control how that URL looks and how inactive links behave.', 'wb-ads-rotator-with-split-test' ) . '</p>';
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
	 * @param mixed  $default_value Default value.
	 * @return mixed
	 */
	public function get( $key, $default_value = null ) {
		$settings = $this->get_settings();
		if ( isset( $settings[ $key ] ) ) {
			return $settings[ $key ];
		}
		return null !== $default_value ? $default_value : ( isset( $this->defaults[ $key ] ) ? $this->defaults[ $key ] : null );
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Input settings.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$input = is_array( $input ) ? $input : array();

		// Field contract. A checkbox the admin cleared posts nothing, which
		// looks exactly like a key this write never meant to touch. The form
		// renders one `_fields[]` entry per such control (see
		// render_field_contract()), so a named-but-absent key is a deliberate
		// "off" and is written as empty. A write with no contract - PRO's
		// Modules save via Settings_Helper::update(), REST, WP-CLI - leaves
		// every key it does not carry at its stored value.
		$contract = isset( $input['_fields'] ) && is_array( $input['_fields'] )
			? array_filter( array_map( 'sanitize_key', $input['_fields'] ) )
			: array();
		unset( $input['_fields'] );
		foreach ( $contract as $contract_key ) {
			if ( ! array_key_exists( $contract_key, $input ) ) {
				$input[ $contract_key ] = '';
			}
		}

		$sanitized = array();

		// Module toggles. An unchecked box posts nothing; the contract above
		// turns that into an empty value, which resolves every module to false.
		$sanitized['modules'] = array();
		$posted_modules       = isset( $input['modules'] ) && is_array( $input['modules'] ) ? $input['modules'] : array();
		foreach ( array_keys( \WBAM\Core\Settings_Helper::module_defaults() ) as $module_slug ) {
			$sanitized['modules'][ $module_slug ] = ! empty( $posted_modules[ $module_slug ] );
		}

		$sanitized['disable_ads_logged_in'] = ! empty( $input['disable_ads_logged_in'] );
		$sanitized['disable_ads_admin']     = ! empty( $input['disable_ads_admin'] );
		$sanitized['ad_label']              = sanitize_text_field( $input['ad_label'] ?? '' );
		$sanitized['ad_label_position']     = in_array( $input['ad_label_position'] ?? '', array( 'above', 'below' ), true ) ? $input['ad_label_position'] : 'above';
		$sanitized['container_class']       = sanitize_html_class( $input['container_class'] ?? '' );
		$sanitized['viewable_impressions']  = ! empty( $input['viewable_impressions'] );
		$sanitized['max_ads_per_page']      = absint( $input['max_ads_per_page'] ?? 0 );

		// Link cloaking. sanitize_title keeps the prefix rewrite-safe (matches
		// Link_Cloaker::get_cloak_prefix); default back to 'go' if emptied.
		$cloak_prefix                      = sanitize_title( $input['link_cloak_prefix'] ?? '' );
		$sanitized['link_cloak_prefix']    = '' !== $cloak_prefix ? $cloak_prefix : 'go';
		$sanitized['link_inactive_action'] = in_array( $input['link_inactive_action'] ?? '', array( '404', 'home', 'custom' ), true ) ? $input['link_inactive_action'] : '404';
		$sanitized['link_inactive_url']    = esc_url_raw( $input['link_inactive_url'] ?? '' );

		if ( ! empty( $input['disable_on_post_types'] ) && is_array( $input['disable_on_post_types'] ) ) {
			$sanitized['disable_on_post_types'] = array_map( 'sanitize_key', $input['disable_on_post_types'] );
		} else {
			$sanitized['disable_on_post_types'] = array();
		}

		// Geolocation settings (owner decision 8, 3.2.0). geo_primary_provider
		// is declared via the _fields[] contract because a radio group can
		// legitimately post nothing (no provider chosen yet) - an absent
		// value keeps whatever was already stored instead of forcing a
		// default onto a save that never touched this section. 'ip-api' and
		// 'ipapi-co' stay valid for sanitization only so a pre-3.2.0 site's
		// existing value round-trips; they are not offered on the form.
		$sanitized['geo_enabled'] = ! empty( $input['geo_enabled'] );

		$valid_providers = array( 'maxmind', 'ipinfo', 'ip-api', 'ipapi-co' );
		if ( array_key_exists( 'geo_primary_provider', $input ) ) {
			$geo_provider                      = sanitize_key( $input['geo_primary_provider'] );
			$sanitized['geo_primary_provider'] = in_array( $geo_provider, $valid_providers, true ) ? $geo_provider : $this->get( 'geo_primary_provider', '' );
		} else {
			$sanitized['geo_primary_provider'] = $this->get( 'geo_primary_provider', '' );
		}
		$sanitized['geo_ipinfo_key']      = sanitize_text_field( $input['geo_ipinfo_key'] ?? '' );
		$sanitized['geo_maxmind_db_path'] = sanitize_text_field( $input['geo_maxmind_db_path'] ?? '' );

		// AdSense settings.
		$sanitized['adsense_publisher_id'] = sanitize_text_field( $input['adsense_publisher_id'] ?? '' );
		$sanitized['adsense_auto_ads']     = ! empty( $input['adsense_auto_ads'] );

		$sanitized['format_matching'] = ! empty( $input['format_matching'] );

		// Privacy settings.
		$sanitized['require_consent_adsense'] = ! empty( $input['require_consent_adsense'] );
		$sanitized['anonymize_ip']            = ! empty( $input['anonymize_ip'] );

		// Advanced settings.
		$sanitized['delete_data_on_uninstall'] = ! empty( $input['delete_data_on_uninstall'] );

		// Only keys this write carries replace the stored value (stored plus
		// defaults), so a partial update cannot reset unrelated settings -
		// e.g. the first Modules save on a fresh install blanking ad_label.
		$sanitized = array_merge( $this->get_settings(), array_intersect_key( $sanitized, $input ) );

		// Placement gates resolve their own absent/present rules.
		return array_merge( $sanitized, $this->sanitize_placement_gates( $input ) );
	}

	/**
	 * Resolve both placement gates from a settings POST.
	 *
	 * The stored value has three states (all / none / explicit list) and a
	 * checkbox column has one (the ticks that happened to be on). The
	 * transport-only hidden fields the matrix emits close that gap. See
	 * Settings_Helper::GATE_NONE for the canonical encoding; this method is
	 * the only writer of it.
	 *
	 * Decision table:
	 *
	 *  | `placement_gates_submitted` | ticks           | stored               |
	 *  |-----------------------------|-----------------|----------------------|
	 *  | absent                      | (irrelevant)    | previous value, kept |
	 *  | present                     | every row       | array()  = ALL       |
	 *  | present                     | none            | array( GATE_NONE )   |
	 *  | present                     | some            | exactly those        |
	 *
	 * Row 1 is what stops another settings tab, or a programmatic
	 * `update_option()`, from clobbering gates it never rendered. Row 2 is
	 * why a save cannot freeze "all" into a snapshot of the placements
	 * registered at that instant — a later integration's slots must stay
	 * open, since the admin never chose to close them.
	 *
	 * Known limitation: once the gate is an explicit list (row 4), a slot
	 * registered afterwards IS closed until an admin ticks it. That is
	 * inherent to an allowlist, and it is the state an admin opted into.
	 *
	 * The Advertisers column carries its own flag on the same pattern,
	 * `placement_gates_adv_submitted`, because free hides that column
	 * outright: a screen that never drew the question must land on row 1 for
	 * the advertiser gate while the Site gate still resolves normally from
	 * rows 2-4. One flag for the whole matrix cannot express that.
	 *
	 * @since 2.11.0
	 * @param array<string,mixed> $input Raw settings POST.
	 * @return array<string,string[]> `enabled_placements` and `advertiser_placements`.
	 */
	private function sanitize_placement_gates( $input ) {
		$stored      = get_option( self::OPTION_NAME, array() );
		$stored      = is_array( $stored ) ? $stored : array();
		$stored_site = self::sanitize_placement_ids( $stored['enabled_placements'] ?? array() );
		$stored_adv  = self::sanitize_placement_ids( $stored['advertiser_placements'] ?? array() );

		if ( empty( $input['placement_gates_submitted'] ) ) {
			// No matrix in this write. Two cases, and they are NOT the same.
			//
			// A caller that names a gate key explicitly - the REST settings
			// route, WP-CLI, a migration - is unambiguous: it passed the array
			// it wants stored, so honour it verbatim. The form's "unticked
			// everything" ambiguity that GATE_NONE exists to solve cannot arise
			// here, because a programmatic caller CAN send an empty array and
			// mean the documented "all".
			//
			// A write that mentions neither key is some other settings form or
			// an unrelated update_option(); preserve what is stored so it
			// cannot clobber the gates as a side effect.
			$writes_site = array_key_exists( 'enabled_placements', $input );
			$writes_adv  = array_key_exists( 'advertiser_placements', $input );

			if ( ! $writes_site && ! $writes_adv ) {
				return array(
					'enabled_placements'    => $stored_site,
					'advertiser_placements' => $stored_adv,
				);
			}

			$site = $writes_site
				? self::sanitize_placement_ids( $input['enabled_placements'] )
				: $stored_site;
			$adv  = $writes_adv
				? self::sanitize_placement_ids( $input['advertiser_placements'] )
				: $stored_adv;

			return array(
				'enabled_placements'    => $site,
				'advertiser_placements' => self::intersect_advertiser_gate( $adv, $site ),
			);
		}

		$offered = self::sanitize_placement_ids(
			explode( ',', isset( $input['placement_gates_offered'] ) && is_string( $input['placement_gates_offered'] ) ? $input['placement_gates_offered'] : '' )
		);

		$site = self::resolve_gate(
			self::sanitize_placement_ids( $input['enabled_placements'] ?? array() ),
			$offered,
			$stored_site
		);

		// The Advertisers column only offers rows whose Site box is ticked —
		// the rest render disabled, and a disabled checkbox posts nothing.
		// Narrowing the offered set the same way is what enforces
		// "advertiser ⊆ site" at write time. Settings_Helper enforces it
		// again on read, because a crafted POST is not the only way a bad
		// pair could reach the option.
		$sellable = empty( $site ) ? $offered : array_values( array_intersect( $offered, $site ) );

		// Free hides the Advertisers column entirely - selling slots needs the
		// Pro portal. The column therefore has to say it was on screen, because
		// "no sell boxes ticked" and "no sell boxes drawn" arrive as the same
		// empty POST. Without this an admin who saves Placements while Pro is
		// deactivated would see resolve_gate() read zero-of-N ticked as a
		// deliberate "none" and store GATE_NONE, so nothing would be sellable
		// when Pro came back. An empty offered set makes resolve_gate() return
		// the stored gate untouched, which is what a screen that never asked
		// the question should do.
		if ( empty( $input['placement_gates_adv_submitted'] ) ) {
			$sellable = array();
		}

		$advertiser = self::resolve_gate(
			self::sanitize_placement_ids( $input['advertiser_placements'] ?? array() ),
			$sellable,
			$stored_adv
		);

		return array(
			'enabled_placements'    => $site,
			'advertiser_placements' => $advertiser,
		);
	}

	/**
	 * Enforce "advertiser subset of site" on a programmatic write.
	 *
	 * The matrix path gets this for free by narrowing the offered set, but a
	 * REST or WP-CLI caller can name any pair it likes, so the relationship
	 * has to be imposed here too. Settings_Helper enforces it a third time on
	 * read - a crafted write is not the only way a bad pair could land.
	 *
	 * @since 2.11.0
	 * @param string[] $advertiser Sanitized advertiser gate.
	 * @param string[] $site       Sanitized site gate.
	 * @return string[] Advertiser gate, never wider than the site gate.
	 */
	private static function intersect_advertiser_gate( array $advertiser, array $site ) {
		$none = \WBAM\Core\Settings_Helper::GATE_NONE;

		// Site closed entirely: nothing can be sellable.
		if ( in_array( $none, $site, true ) ) {
			return array( $none );
		}

		// Either side means "all", or the advertiser gate is an explicit
		// "none" - all three pass through untouched.
		if ( empty( $advertiser ) || empty( $site ) || in_array( $none, $advertiser, true ) ) {
			return $advertiser;
		}

		$intersected = array_values( array_intersect( $advertiser, $site ) );

		// An advertiser list that shares nothing with the site list is a
		// closed gate, not an accidental "all".
		return empty( $intersected ) ? array( $none ) : $intersected;
	}

	/**
	 * Encode one gate from a posted tick list.
	 *
	 * Stored IDs the matrix did NOT offer are carried through unchanged.
	 * A slot can be registered on the front end only, or belong to an
	 * integration that is deactivated right now; the admin was never shown
	 * it, so a save must not silently close it.
	 *
	 * @since 2.11.0
	 * @param string[] $ticked  Sanitized IDs the admin ticked.
	 * @param string[] $offered Sanitized IDs the matrix drew a row for.
	 * @param string[] $stored  Currently stored value of this gate.
	 * @return string[] Encoded gate: array() means all, array( GATE_NONE ) means none.
	 */
	private static function resolve_gate( array $ticked, array $offered, array $stored ) {
		if ( empty( $offered ) ) {
			// Nothing was on offer, so nothing was decided.
			return $stored;
		}

		$ticked = array_values( array_intersect( $ticked, $offered ) );

		// Empty whenever the stored gate is "all" or "none".
		$unseen = array_values( array_diff( $stored, $offered, array( \WBAM\Core\Settings_Helper::GATE_NONE ) ) );

		if ( ! array_diff( $offered, $ticked ) ) {
			// Every offered row ticked: "all", not a frozen allowlist.
			return array();
		}

		if ( empty( $ticked ) && empty( $unseen ) ) {
			return array( \WBAM\Core\Settings_Helper::GATE_NONE );
		}

		return array_values( array_unique( array_merge( $ticked, $unseen ) ) );
	}

	/**
	 * Sanitize an arbitrary list into placement IDs.
	 *
	 * `strlen` rather than the default array_filter() callback so a slug
	 * that sanitizes to "0" survives; non-scalars are dropped rather than
	 * cast, since a crafted POST can nest arrays anywhere.
	 *
	 * @since 2.11.0
	 * @param mixed $ids Raw list.
	 * @return string[]
	 */
	private static function sanitize_placement_ids( $ids ) {
		$out = array();

		foreach ( (array) $ids as $id ) {
			if ( ! is_scalar( $id ) ) {
				continue;
			}

			$id = sanitize_key( (string) $id );
			if ( '' === $id ) {
				continue;
			}

			$out[] = $id;
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Render settings page: page chrome + left sidebar nav + active section body.
	 *
	 * @since 3.2.0 Rebuilt around get_sections() / UX::settings_nav() — was a
	 *              single flat do_settings_sections() call.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Standard WP Settings API pattern, nonce verified by options.php.
		if ( isset( $_GET['settings-updated'] ) ) {
			add_settings_error( 'wbam_messages', 'wbam_message', __( 'Settings saved.', 'wb-ads-rotator-with-split-test' ), 'updated' );
		}

		$sections = $this->get_sections();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only section selector, no state change.
		$requested = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';

		// Email Captures is its own submenu now (card 10343706274), not a
		// section of this screen — a genuine HTTP redirect, not just an
		// alias resolved for the nav highlight, since the content is not
		// rendered here at all any more. Only `deleted`/`paged` are ever
		// added by a caller of the old `?section=email-captures` URL
		// (see Email_Captures' own redirects, now built via
		// Admin_Links::email_captures() directly) — never the whole
		// $_GET, which would carry this page's own `page`/`section` args
		// straight into the redirect target.
		if ( 'email-captures' === $requested ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only redirect, values re-sanitized below.
			$carry = array_intersect_key( wp_unslash( $_GET ), array_flip( array( 'deleted', 'paged' ) ) );
			wp_safe_redirect( \WBAM\Core\Admin_Links::email_captures( array_map( 'absint', $carry ) ) );
			exit;
		}

		// Old section slugs (pre-3.2.0 layout, or PRO's retired horizontal
		// tabs) that now render somewhere else — merged into another
		// section's body (License) or simply renamed (Ad Display,
		// Geolocation, Advertising). Map those here so both the nav
		// highlight and the body agree on which section is "current".
		//
		// @param array<string,string> $aliases Old slug => current slug.
		$aliases = (array) apply_filters(
			'wbam_settings_section_aliases',
			array(
				'ad-display' => 'ads-display',
			)
		);

		$current = isset( $aliases[ $requested ] ) ? $aliases[ $requested ] : $requested;

		if ( ! isset( $sections[ $current ] ) ) {
			$keys    = array_keys( $sections );
			$current = isset( $keys[0] ) ? $keys[0] : 'ads-display';
		}

		$icons  = self::nav_icons();
		$groups = self::nav_groups();

		$nav_items = array();
		foreach ( $sections as $slug => $section ) {
			$nav_items[ $slug ] = array(
				'label' => $section['label'],
				'url'   => \WBAM\Core\Admin_Links::settings( $slug ),
				'icon'  => isset( $icons[ $slug ] ) ? $icons[ $slug ] : 'circle',
				'group' => isset( $groups[ $slug ] ) ? $groups[ $slug ] : '',
			);
		}
		?>
		<div class="wrap wbam-admin wbam-settings-page wbam-settings-wrap">
			<?php
			\WBAM\Admin\UX::page_header(
				array(
					'title' => __( 'Settings', 'wb-ads-rotator-with-split-test' ),
					// Billing, notifications and modules only exist once Pro
					// is active - don't describe screens a Free-only site
					// doesn't have.
					'desc'  => defined( 'WBAM_PRO_VERSION' )
						? __( 'Ad display, billing, notifications and modules for this site.', 'wb-ads-rotator-with-split-test' )
						: __( 'Ad display and general settings for this site.', 'wb-ads-rotator-with-split-test' ),
				)
			);
			settings_errors( 'wbam_messages' );
			?>

			<div class="wbam-settings-layout">
				<?php \WBAM\Admin\UX::settings_nav( $nav_items, $current ); ?>
				<div class="wbam-settings-content">
					<?php
					if ( isset( $sections[ $current ]['render'] ) && is_callable( $sections[ $current ]['render'] ) ) {
						call_user_func( $sections[ $current ]['render'] );
					}
					?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Sections this plugin contributes to the one Settings screen on its own.
	 *
	 * 'general' (the Features card, 16 module switches) is FREE-only —
	 * PRO's General section already covers the same ground (Site Mode,
	 * Modules, Currency, Pages) once PRO is active, so FREE steps aside
	 * rather than rendering a second "General" entry — see
	 * `Pro_Admin::map_settings_sections()`.
	 *
	 * 'links', 'location' and 'privacy' are always registered here, and PRO
	 * appends its own card(s) into the same section when active (auto-linking
	 * onto Links, classified maps onto Location, GDPR/analytics onto
	 * Privacy) — again see `map_settings_sections()`.
	 *
	 * @since 3.2.0
	 * @return array<string,array{label:string,render:callable}>
	 */
	private function default_sections() {
		$sections = array();

		if ( ! defined( 'WBAM_PRO_VERSION' ) ) {
			$sections['general'] = array(
				'label'  => __( 'General', 'wb-ads-rotator-with-split-test' ),
				'render' => array( $this, 'render_general_page' ),
			);
		}

		$sections['ads-display'] = array(
			'label'  => __( 'Ads & Display', 'wb-ads-rotator-with-split-test' ),
			'render' => array( $this, 'render_ads_display_page' ),
		);

		$sections['links'] = array(
			'label'  => __( 'Links', 'wb-ads-rotator-with-split-test' ),
			'render' => array( $this, 'render_links_page' ),
		);

		$sections['location'] = array(
			'label'  => __( 'Location', 'wb-ads-rotator-with-split-test' ),
			'render' => array( $this, 'render_location_page' ),
		);

		$sections['privacy'] = array(
			'label'  => __( 'Privacy & Data', 'wb-ads-rotator-with-split-test' ),
			'render' => array( $this, 'render_privacy_page' ),
		);

		$sections['tools'] = array(
			'label'  => __( 'Tools & License', 'wb-ads-rotator-with-split-test' ),
			'render' => array( $this, 'render_tools_section' ),
		);

		return $sections;
	}

	/**
	 * The full, ordered sidebar section list for the Settings screen.
	 *
	 * PRO maps its own settings tabs (general, advertisers-billing,
	 * classifieds, credits, emails, license, ...) into this same list via the
	 * filter — see `WBAM_Pro\Core\Pro_Admin::map_settings_sections()`.
	 *
	 * @since 3.2.0
	 * @return array<string,array{label:string,render:callable}>
	 */
	private function get_sections() {
		/**
		 * Filter the sidebar sections on the one Settings screen.
		 *
		 * @since 3.2.0
		 * @param array<string,array{label:string,render:callable}> $sections Ordered section map.
		 */
		return (array) apply_filters( 'wbam_settings_sections', $this->default_sections() );
	}

	/**
	 * One Lucide icon per known section slug, for the settings rail.
	 *
	 * A slug this map does not know (a 3rd-party section added via the
	 * `wbam_settings_sections` filter) falls back to a plain circle in
	 * render_page() rather than rendering with no icon at all.
	 *
	 * @since 3.2.0
	 * @return array<string,string>
	 */
	private static function nav_icons() {
		return array(
			'general'             => 'settings',
			'ads-display'         => 'monitor',
			'advertisers-billing' => 'briefcase',
			'credits'             => 'ticket',
			'classifieds'         => 'tag',
			'links'               => 'link',
			'location'            => 'map-pin',
			'privacy'             => 'shield',
			'emails'              => 'mail',
			'tools'               => 'wrench',
		);
	}

	/**
	 * Group heading each known section slug renders under on the settings
	 * rail. A slug this map does not know renders ungrouped (ordered last,
	 * no heading above it) rather than being dropped.
	 *
	 * @since 3.2.0
	 * @return array<string,string>
	 */
	private static function nav_groups() {
		return array(
			'general'             => __( 'Setup', 'wb-ads-rotator-with-split-test' ),
			'ads-display'         => __( 'Setup', 'wb-ads-rotator-with-split-test' ),
			'advertisers-billing' => __( 'Money', 'wb-ads-rotator-with-split-test' ),
			'credits'             => __( 'Money', 'wb-ads-rotator-with-split-test' ),
			'classifieds'         => __( 'Content', 'wb-ads-rotator-with-split-test' ),
			'links'               => __( 'Content', 'wb-ads-rotator-with-split-test' ),
			'location'            => __( 'Content', 'wb-ads-rotator-with-split-test' ),
			'privacy'             => __( 'System', 'wb-ads-rotator-with-split-test' ),
			'emails'              => __( 'System', 'wb-ads-rotator-with-split-test' ),
			'tools'               => __( 'System', 'wb-ads-rotator-with-split-test' ),
		);
	}

	/**
	 * Render the "General" section (FREE-only): the Features card. See
	 * default_sections() — PRO's own General section covers this same
	 * ground once PRO is active, so this never double-renders.
	 *
	 * @since 3.2.0
	 */
	public function render_general_page() {
		?>
		<form action="options.php" method="post" class="wbam-settings-form">
			<?php
			settings_fields( 'wbam_settings_group' );
			$this->render_settings_page_sections( array( 'wbam_features' ) );
			submit_button();
			?>
		</form>
		<?php
	}

	/**
	 * Render the "Ads & Display" section: who sees ads, label/wrapper,
	 * placements + format matching, and AdSense. PRO appends its Ad
	 * Visibility by Role/Member Type and Ad Rotation cards via
	 * `wbam_settings_ads_display_content` when active — see
	 * `Pro_Admin::render_ads_display_content_card()`.
	 *
	 * One form and one `sanitize_settings()` round-trip for every FREE card
	 * here — see render_settings_page_sections().
	 *
	 * @since 3.2.0
	 */
	public function render_ads_display_page() {
		$ids = array( 'wbam_general', 'wbam_display', 'wbam_placements', 'wbam_adsense' );
		\WBAM\Admin\UX::page_jump_nav( $this->get_jump_nav_items( $ids ) );
		?>
		<form action="options.php" method="post" class="wbam-settings-form" id="wbam-ads-display-form">
			<?php
			settings_fields( 'wbam_settings_group' );
			$this->render_settings_page_sections( $ids );
			/**
			 * Fires inside the Ads & Display section's one `<form>`, after
			 * FREE's own cards and before the single Save button (card
			 * 10343706274: one form, one Save per section) - PRO hooks its Ad
			 * Visibility by Role/Member Type card and (when the rotation
			 * module is active) its Ad Rotation card here. PRO's fields post
			 * through this same `options.php` submission because
			 * `wbam_pro_settings` is also registered under this page's
			 * `wbam_settings_group` (see Pro_Admin::register_settings()) -
			 * its own sanitizer runs unchanged, only the physical form is
			 * shared.
			 *
			 * @since 3.2.0
			 */
			do_action( 'wbam_settings_ads_display_content' );
			submit_button();
			?>
		</form>
		<?php
	}

	/**
	 * Render the "Links" section: this plugin's cloaking settings. PRO
	 * appends its own auto-linking/scanner card via `wbam_settings_links_content`
	 * when active — see `Pro_Admin::map_settings_sections()`.
	 *
	 * @since 3.2.0
	 */
	public function render_links_page() {
		?>
		<form action="options.php" method="post" class="wbam-settings-form">
			<?php
			settings_fields( 'wbam_settings_group' );
			$this->render_settings_page_sections( array( 'wbam_links' ) );
			/**
			 * Fires inside the Links section's one `<form>`, after cloaking
			 * settings and before the single Save button (card 10343706274:
			 * one form, one Save per section).
			 *
			 * @since 3.2.0
			 */
			do_action( 'wbam_settings_links_content' );
			submit_button();
			?>
		</form>
		<?php
	}

	/**
	 * Render the "Location" section: visitor geolocation for ad targeting.
	 * PRO appends its own classified-maps card via `wbam_settings_location_content`
	 * when active — see `Pro_Admin::map_settings_sections()`.
	 *
	 * @since 3.2.0
	 */
	public function render_location_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- options.php verifies this page's own nonce; PRO's card (hooked below) checks its own $saving-gated fields itself.
		$saving = isset( $_POST['option_page'] ) && 'wbam_settings_group' === wp_unslash( $_POST['option_page'] ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'wbam_settings_group-options' );
		?>
		<form action="options.php" method="post" class="wbam-settings-form">
			<?php
			settings_fields( 'wbam_settings_group' );
			$this->render_settings_page_sections( array( 'wbam_geo' ) );
			/**
			 * Fires inside the Location section's one `<form>`, after
			 * visitor geolocation and before the single Save button (card
			 * 10343706274: one form, one Save per section). PRO's
			 * classified-maps card writes its own option
			 * (`wbam_pro_geolocation_settings`) directly - it cannot share
			 * this page's native `wbam_settings_group` Settings API
			 * processing the way `wbam_pro_settings` does elsewhere, so it
			 * is instead gated on the `$saving` flag this same submission
			 * already verified.
			 *
			 * @since 3.2.0
			 * @param bool $saving Whether this exact request is a verified
			 *                     save of this page's form.
			 */
			do_action( 'wbam_settings_location_content', $saving );
			submit_button();
			?>
		</form>
		<?php
	}

	/**
	 * Render the "Privacy & Data" section: this plugin's Anonymize IP switch
	 * (FREE-only sites — see register_general_settings()) and Delete Data on
	 * Uninstall. PRO appends its own analytics/GDPR card via
	 * `wbam_settings_privacy_content` when active — see
	 * `Pro_Admin::map_settings_sections()`.
	 *
	 * @since 3.2.0
	 */
	public function render_privacy_page() {
		// 'wbam_advanced' (Danger Zone: Delete Data on Uninstall) always
		// renders LAST, after PRO's analytics/GDPR card - destructive and
		// rarely used, so it never sits above the settings someone actually
		// came here to change (PV1, admin polish audit).
		$ids = defined( 'WBAM_PRO_VERSION' ) ? array() : array( 'wbam_privacy' );
		?>
		<form action="options.php" method="post" class="wbam-settings-form">
			<?php
			settings_fields( 'wbam_settings_group' );
			$this->render_settings_page_sections( $ids );
			/**
			 * Fires inside the Privacy & Data section's one `<form>`, after
			 * FREE's own cards and before the single Save button (card
			 * 10343706274: one form, one Save per section). PRO's
			 * analytics/GDPR card posts through this same `options.php`
			 * submission because `wbam_pro_settings` is also registered
			 * under this page's `wbam_settings_group`.
			 *
			 * @since 3.2.0
			 */
			do_action( 'wbam_settings_privacy_content' );
			$this->render_settings_page_sections( array( 'wbam_advanced' ) );
			submit_button();
			?>
		</form>
		<?php
	}

	/**
	 * Render a whitelisted subset of the registered `wbam-settings` page's
	 * WP Settings API sections, each wrapped in its own always-visible
	 * `.wbam-card` — no tabs, no JS-toggled subsections. Every one of these
	 * top-level settings pages posts through the same `<form>`/
	 * `settings_fields( 'wbam_settings_group' )`/`sanitize_settings()`
	 * round-trip; `$ids` only decides which registered fields show up on
	 * which page.
	 *
	 * @since 3.2.0 Replaces render_ad_display_subsections() — the sub-nav
	 *              pills it drove are gone now that each former pill is its
	 *              own top-level section.
	 * @param string[] $ids WP Settings API section ids to render, e.g.
	 *                       array( 'wbam_general', 'wbam_display' ).
	 * @return void
	 */
	/**
	 * Titles of the given WP Settings API section ids, in registration
	 * order, for building an "On this page" jump row (UX::page_jump_nav())
	 * before render_settings_page_sections() renders the matching cards —
	 * the same `wbam-jump-{id}` anchors that method writes onto each card.
	 *
	 * @since 3.2.0
	 * @param string[] $ids WP Settings API section ids.
	 * @return array<string,string> Map of `wbam-jump-{id}` => title, only
	 *                              for ids that are actually registered.
	 */
	private function get_jump_nav_items( array $ids ) {
		global $wp_settings_sections;

		$items = array();
		if ( empty( $wp_settings_sections['wbam-settings'] ) ) {
			return $items;
		}

		foreach ( (array) $wp_settings_sections['wbam-settings'] as $section ) {
			if ( in_array( $section['id'], $ids, true ) && $section['title'] ) {
				$items[ 'wbam-jump-' . $section['id'] ] = $section['title'];
			}
		}

		return $items;
	}

	private function render_settings_page_sections( array $ids ) {
		global $wp_settings_sections, $wp_settings_fields;

		if ( empty( $wp_settings_sections['wbam-settings'] ) ) {
			return;
		}

		foreach ( (array) $wp_settings_sections['wbam-settings'] as $section ) {
			if ( ! in_array( $section['id'], $ids, true ) ) {
				continue;
			}

			// Danger Zone (Delete Data on Uninstall) is the one card styled
			// as a warning - destructive, rarely used, ordered last (PV1).
			$card_class = 'wbam_advanced' === $section['id'] ? 'wbam-card wbam-card--danger' : 'wbam-card';
			echo '<div class="' . esc_attr( $card_class ) . '" id="' . esc_attr( 'wbam-jump-' . $section['id'] ) . '">';
			if ( $section['title'] ) {
				echo '<h2>' . esc_html( $section['title'] ) . '</h2>';
			}
			if ( $section['callback'] ) {
				call_user_func( $section['callback'], $section );
			}
			if ( isset( $wp_settings_fields['wbam-settings'][ $section['id'] ] ) ) {
				echo '<table class="form-table" role="presentation">';
				do_settings_fields( 'wbam-settings', $section['id'] );
				echo '</table>';
			}
			echo '</div>';
		}
	}

	/**
	 * Render the "Tools & License" section: demo-data/maintenance utilities
	 * and license activation (PRO, via the `wbam_settings_tools_content`
	 * action). The old `wbam-pro-settings&tab=license` URL redirects here —
	 * see `Pro_Admin::legacy_settings_tab_map()`. Email Captures moved to
	 * its own submenu (card 10343706274); the old `?section=email-captures`
	 * URL redirects there — see render_page().
	 *
	 * @since 3.2.0
	 */
	public function render_tools_section() {
		if ( has_action( 'wbam_settings_tools_content' ) ) {
			echo '<div class="wbam-card">';
			/**
			 * Fires inside the Tools section.
			 *
			 * @since 3.2.0
			 */
			do_action( 'wbam_settings_tools_content' );
			echo '</div>';
		}
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
	 * Placements section description, plus the placement matrix itself.
	 *
	 * The matrix is rendered here rather than via add_settings_field()
	 * because add_settings_field() wraps its output in the Settings API's
	 * form-table <td>, next to a <th> label column that eats ~500px. A
	 * 4-column matrix squeezed into what's left forces a horizontal
	 * scrollbar even on a wide desktop screen. Rendering it from the
	 * section callback instead - which fires before that form-table opens -
	 * gives it the full content width and no label column.
	 */
	public function render_placements_section(): void {
		// The Advertisers column (letting advertisers buy a slot) only exists
		// once Pro's advertiser portal is active - Free-only sites just pick
		// which slots this site itself uses.
		$copy = defined( 'WBAM_PRO_VERSION' )
			? __( 'Choose which slots this site uses, and which of those advertisers may buy. Unticking Site stops ads rendering in that slot. Unticking Advertisers only removes it from the advertiser portal - creatives already assigned keep running.', 'wb-ads-rotator-with-split-test' )
			: __( 'Choose which slots this site uses. Unticking a slot stops ads rendering there.', 'wb-ads-rotator-with-split-test' );

		echo '<p>' . esc_html( $copy ) . '</p>';

		/**
		 * Fires after the Placements intro copy, before the matrix table.
		 *
		 * PRO's rotation module hooks this to explain the "Ads shown" column
		 * it adds to the matrix (see Placement_Settings::render_table()'s
		 * `wbam_placement_matrix_head`/`wbam_placement_matrix_cell` hooks).
		 *
		 * @since 3.2.0
		 */
		do_action( 'wbam_placement_matrix_intro' );

		\WBAM\Admin\Placement_Settings::render_table();
	}

	/**
	 * Render geo section.
	 *
	 * @since 3.2.0 Rewritten for owner decision 8 - geolocation is an
	 *              explicit opt-in, so the intro leads with what turning it
	 *              on actually does before the toggle below it.
	 */
	public function render_geo_section() {
		echo '<p>' . esc_html__( 'Off by default. No visitor IP address is looked up - or sent anywhere - until you turn this on and choose a provider below.', 'wb-ads-rotator-with-split-test' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Turning this on lets ads use country/region rules and lets analytics show visitor country. A local MaxMind database keeps everything on your server; the HTTPS API option sends the visitor\'s IP address to that provider using your own key.', 'wb-ads-rotator-with-split-test' ) . '</p>';

		$legacy_notice = $this->get_legacy_geo_provider_notice();
		if ( $legacy_notice ) {
			echo '<div class="notice notice-warning inline"><p>' . wp_kses_post( $legacy_notice ) . '</p></div>';
		}
	}

	/**
	 * Legacy-provider notice shown inline in the Geo Targeting section.
	 *
	 * Sites upgrading from before 3.2.0 keep working on whichever provider
	 * they already had (Installer::maybe_set_geo_enabled_default()), but
	 * ip-api.com is plain HTTP with a non-commercial-use license and
	 * ipapi.co has no owner key - neither is a choice this settings screen
	 * offers going forward. Recommend a switch without breaking them.
	 *
	 * @since 3.2.0
	 * @return string Empty string when nothing to warn about.
	 */
	private function get_legacy_geo_provider_notice() {
		$provider = $this->get( 'geo_primary_provider', '' );
		$legacy   = $this->get_legacy_geo_providers();

		if ( ! isset( $legacy[ $provider ] ) ) {
			return '';
		}

		return sprintf(
			/* translators: %s: legacy provider display name, e.g. "ip-api.com". */
			esc_html__( 'This site is still using %s from before geolocation required an explicit provider choice. It keeps working, but we recommend switching to the local MaxMind database or the HTTPS API option below.', 'wb-ads-rotator-with-split-test' ),
			'<strong>' . esc_html( $legacy[ $provider ] ) . '</strong>'
		);
	}

	/**
	 * Provider values a pre-3.2.0 install may still have stored, keyed by
	 * display name. Not offered as a choice on this screen; kept only so an
	 * upgraded site's existing value still resolves to something readable.
	 *
	 * @since 3.2.0
	 * @return array<string,string>
	 */
	private function get_legacy_geo_providers() {
		return array(
			'ip-api'   => 'ip-api.com',
			'ipapi-co' => 'ipapi.co',
		);
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
	 * Render advanced (Danger Zone) section.
	 */
	public function render_advanced_section() {
		echo '<p>' . esc_html__( 'Destructive and rarely-used. Double-check before saving.', 'wb-ads-rotator-with-split-test' ) . '</p>';
	}

	/**
	 * Render geo provider field.
	 *
	 * Owner decision 8 (3.2.0): exactly two choices going forward - a local
	 * MaxMind database (nothing leaves the site) or an HTTPS API using the
	 * owner's own key (ipinfo.io). A pre-3.2.0 install that still has a
	 * legacy value stored (ip-api / ipapi-co) gets that value listed too,
	 * pre-selected, so the form never silently switches its provider out
	 * from under it on save - see get_legacy_geo_provider_notice() for the
	 * matching admin notice recommending a switch.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_geo_provider_field( $args ) {
		$id    = $args['id'];
		$value = $this->get( $id, '' );

		$providers = array(
			'maxmind' => array(
				'name' => __( 'MaxMind database (local file)', 'wb-ads-rotator-with-split-test' ),
				'note' => __( 'Nothing leaves your site.', 'wb-ads-rotator-with-split-test' ),
			),
			'ipinfo'  => array(
				'name' => __( 'HTTPS API (ipinfo.io)', 'wb-ads-rotator-with-split-test' ),
				'note' => __( 'Visitor IP sent to ipinfo.io over HTTPS using your own key.', 'wb-ads-rotator-with-split-test' ),
			),
		);

		$legacy = $this->get_legacy_geo_providers();
		if ( isset( $legacy[ $value ] ) ) {
			$providers[ $value ] = array(
				/* translators: %s: legacy provider display name, e.g. "ip-api.com". */
				'name' => sprintf( __( '%s (legacy - not offered for new setups)', 'wb-ads-rotator-with-split-test' ), $legacy[ $value ] ),
				'note' => __( 'Already in use on this site from before this choice existed.', 'wb-ads-rotator-with-split-test' ),
			);
		}

		$this->render_field_contract( $id );
		?>
		<fieldset class="wbam-geo-provider-picker">
			<?php foreach ( $providers as $key => $provider ) : ?>
				<label class="wbam-geo-provider-option">
					<input type="radio" class="wbam-geo-provider-radio" name="<?php echo esc_attr( self::OPTION_NAME . '[' . $id . ']' ); ?>" value="<?php echo esc_attr( $key ); ?>" <?php checked( $value, $key ); ?> />
					<strong><?php echo esc_html( $provider['name'] ); ?></strong>
					<span class="description"><?php echo esc_html( $provider['note'] ); ?></span>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<?php
	}

	/**
	 * Render the Features section intro.
	 */
	public function render_features_section() {
		echo '<p>' . esc_html__( 'Switch off anything this site does not use. Turning a feature off only hides its admin menu - no data is deleted.', 'wb-ads-rotator-with-split-test' ) . '</p>';
	}

	/**
	 * Render a module on/off checkbox.
	 *
	 * Modules default to enabled, so an absent stored value must read as on.
	 * That differs from render_checkbox_field(), which defaults to off.
	 *
	 * @since 2.9.2
	 * @param array $args Field arguments. Expects 'id', 'label', 'description'.
	 */
	public function render_module_field( $args ) {
		$slug    = $args['id'];
		$enabled = \WBAM\Core\Settings_Helper::is_module_enabled( $slug );
		$this->render_field_contract( 'modules' );
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME . '[modules][' . $slug . ']' ); ?>" value="1" <?php checked( $enabled ); ?> />
			<?php echo esc_html( $args['label'] ?? '' ); ?>
		</label>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Declare a field whose control posts nothing when cleared.
	 *
	 * sanitize_settings() reads these `_fields[]` entries to tell "the admin
	 * unchecked it" (named here, absent from the POST: save empty) from "this
	 * write never drew it" (not named: keep the stored value). Never stored.
	 *
	 * @since 3.2.0
	 * @param string $key Setting key.
	 * @return void
	 */
	private function render_field_contract( $key ) {
		echo '<input type="hidden" name="' . esc_attr( self::OPTION_NAME . '[_fields][]' ) . '" value="' . esc_attr( $key ) . '" />';
	}

	/**
	 * Render checkbox field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_checkbox_field( $args ) {
		$settings = $this->get_settings();
		$id       = $args['id'];
		$value    = isset( $settings[ $id ] ) ? $settings[ $id ] : ( $args['default'] ?? false );
		$this->render_field_contract( $id );
		?>
		<label>
			<input type="checkbox" id="wbam_setting_<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( self::OPTION_NAME . '[' . $id . ']' ); ?>" value="1" <?php checked( $value ); ?> />
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
		// Optional wrapper class so JS can show/hide a field row (e.g. per
		// selected geo provider) without an inline style attribute.
		$wrapper = $args['wrapper'] ?? '';
		if ( $wrapper ) {
			echo '<div class="' . esc_attr( $wrapper ) . '">';
		}
		?>
		<input type="text" id="wbam_setting_<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( self::OPTION_NAME . '[' . $id . ']' ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" class="regular-text" />
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
		if ( $wrapper ) {
			echo '</div>';
		}
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
		<input type="number" id="wbam_setting_<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( self::OPTION_NAME . '[' . $id . ']' ); ?>" value="<?php echo esc_attr( $value ); ?>" min="0" class="small-text" />
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
		<select id="wbam_setting_<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( self::OPTION_NAME . '[' . $id . ']' ); ?>">
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
		$this->render_field_contract( $id );
		?>
		<fieldset>
			<?php foreach ( $post_types as $post_type ) : ?>
				<?php
				if ( 'wbam-ad' === $post_type->name ) {
					continue;}
				?>
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
