<?php
/**
 * Upgrade to PRO Admin Page
 *
 * Only shown when PRO plugin is not active.
 *
 * @package WB_Ad_Manager
 * @since   2.2.0
 */

namespace WBAM\Admin;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Upgrade_Pro class.
 */
class Upgrade_Pro {

	/**
	 * Instance.
	 *
	 * @var Upgrade_Pro
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return Upgrade_Pro
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Only show when PRO is not active.
		if ( defined( 'WBAM_PRO_VERSION' ) ) {
			return;
		}

		add_action( 'admin_menu', array( $this, 'add_menu' ), 100 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Add admin menu.
	 */
	public function add_menu() {
		add_submenu_page(
			'edit.php?post_type=wbam-ad',
			__( 'Upgrade to PRO', 'wb-ads-rotator-with-split-test' ),
			'<span style="color: #f9a825;">' . __( 'Upgrade to PRO', 'wb-ads-rotator-with-split-test' ) . '</span>',
			'manage_options',
			'wbam-upgrade',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue styles.
	 *
	 * @param string $hook Page hook.
	 */
	public function enqueue_styles( $hook ) {
		if ( 'wbam-ad_page_wbam-upgrade' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'wbam-upgrade-pro',
			WBAM_URL . 'assets/css/upgrade-pro.css',
			array( 'wbam-admin-tokens' ),
			WBAM_VERSION
		);
	}

	/**
	 * Render page.
	 */
	public function render_page() {
		?>
		<div class="wrap wbam-upgrade-wrap">
			<div class="wbam-upgrade-header">
				<h1><?php esc_html_e( 'Upgrade to WB Ad Manager PRO', 'wb-ads-rotator-with-split-test' ); ?></h1>
				<p class="wbam-tagline"><?php esc_html_e( 'Unlock powerful features to maximize your advertising revenue', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>

			<div class="wbam-comparison-section">
				<h2><?php esc_html_e( 'Compare FREE vs PRO', 'wb-ads-rotator-with-split-test' ); ?></h2>

				<table class="wbam-comparison-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Feature', 'wb-ads-rotator-with-split-test' ); ?></th>
							<th class="wbam-free-col"><?php esc_html_e( 'FREE', 'wb-ads-rotator-with-split-test' ); ?></th>
							<th class="wbam-pro-col"><?php esc_html_e( 'PRO', 'wb-ads-rotator-with-split-test' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<!-- Ad Management -->
						<tr class="wbam-section-header">
							<td colspan="3"><?php esc_html_e( 'Ad Management', 'wb-ads-rotator-with-split-test' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Image, HTML/Code, AdSense, Rich Content Ads', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Email Capture Forms', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( '16+ Placements (Header, Footer, Content, Paragraph, Archive, Popup, Sticky, Widget, Comments, Shortcode)', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'BuddyPress, bbPress, & Jetonomy Integration', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Display Rules (Pages, Categories, Post Types)', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Device Targeting (Desktop, Tablet, Mobile)', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Geo-Targeting (Country, Region)', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Scheduling (Dates, Days, Times)', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'User Role Targeting', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Frequency Control (Limit Impressions)', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Ad Rotation with Weighted Priority', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>

						<!-- Link Management -->
						<tr class="wbam-section-header">
							<td colspan="3"><?php esc_html_e( 'Link Management', 'wb-ads-rotator-with-split-test' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Link Cloaking', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Click Tracking', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Link Categories', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Broken Link Detection', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Keyword Auto-Linking', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-cross"><?php echo wbam_icon( 'minus', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Link Scanner (Find Monetization Opportunities)', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-cross"><?php echo wbam_icon( 'minus', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Partnership Inquiries ([wbam_partnership_inquiry] form + accept/reject)', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'CSV Import (Links + Keywords)', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-cross"><?php echo wbam_icon( 'minus', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Advanced Link Analytics', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-cross"><?php echo wbam_icon( 'minus', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>

						<!-- Advertiser Portal -->
						<tr class="wbam-section-header">
							<td colspan="3"><?php esc_html_e( 'Advertiser Portal', 'wb-ads-rotator-with-split-test' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Advertiser Registration', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-cross"><?php echo wbam_icon( 'minus', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Advertiser Dashboard', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-cross"><?php echo wbam_icon( 'minus', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Self-Service Ad Submission', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-cross"><?php echo wbam_icon( 'minus', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Wallet & Prepaid Credits', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-cross"><?php echo wbam_icon( 'minus', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Campaign Management', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-cross"><?php echo wbam_icon( 'minus', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Advertising Packages', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-cross"><?php echo wbam_icon( 'minus', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>

						<!-- Payments -->
						<tr class="wbam-section-header">
							<td colspan="3"><?php esc_html_e( 'Payments', 'wb-ads-rotator-with-split-test' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'WooCommerce Integration', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-cross"><?php echo wbam_icon( 'minus', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Stripe Integration', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-cross"><?php echo wbam_icon( 'minus', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'PayPal Integration', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-cross"><?php echo wbam_icon( 'minus', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'CPM / CPC / Flat-Rate Billing', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-cross"><?php echo wbam_icon( 'minus', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>

						<!-- Analytics & Testing -->
						<tr class="wbam-section-header">
							<td colspan="3"><?php esc_html_e( 'Analytics & Testing', 'wb-ads-rotator-with-split-test' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Impressions & Click Tracking', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'CTR & Revenue Reports', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-cross"><?php echo wbam_icon( 'minus', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Geo & Device Analytics', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-cross"><?php echo wbam_icon( 'minus', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'A/B Testing with Statistics', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-cross"><?php echo wbam_icon( 'minus', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Share of Voice Reports', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-cross"><?php echo wbam_icon( 'minus', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Export to CSV', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-cross"><?php echo wbam_icon( 'minus', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>

						<!-- Classifieds Marketplace -->
						<tr class="wbam-section-header">
							<td colspan="3"><?php esc_html_e( 'Classifieds Marketplace', 'wb-ads-rotator-with-split-test' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Classified Listings', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-cross"><?php echo wbam_icon( 'minus', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Categories & Locations', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-cross"><?php echo wbam_icon( 'minus', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Image Galleries', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-cross"><?php echo wbam_icon( 'minus', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Buyer Inquiry System', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-cross"><?php echo wbam_icon( 'minus', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Favorites & Saved Listings', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-cross"><?php echo wbam_icon( 'minus', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Seller Profiles', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-cross"><?php echo wbam_icon( 'minus', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Paid Upgrades (Featured, Bump to Top)', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-cross"><?php echo wbam_icon( 'minus', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Multiple Price Types (Fixed, Negotiable, Free)', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-cross"><?php echo wbam_icon( 'minus', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>

						<!-- Developer & Admin -->
						<tr class="wbam-section-header">
							<td colspan="3"><?php esc_html_e( 'Developer & Admin', 'wb-ads-rotator-with-split-test' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'REST API Access', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Audit Logs', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-cross"><?php echo wbam_icon( 'minus', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Ad Review Queue', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-cross"><?php echo wbam_icon( 'minus', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>

						<!-- Support -->
						<tr class="wbam-section-header">
							<td colspan="3"><?php esc_html_e( 'Support & Updates', 'wb-ads-rotator-with-split-test' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Community Support', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Priority Support', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-cross"><?php echo wbam_icon( 'minus', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Regular Updates', 'wb-ads-rotator-with-split-test' ); ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
							<td class="wbam-check"><?php echo wbam_icon( 'check-circle', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?></td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="wbam-pro-highlights">
				<h2><?php esc_html_e( 'Why Upgrade to PRO?', 'wb-ads-rotator-with-split-test' ); ?></h2>

				<div class="wbam-highlights-grid">
					<div class="wbam-highlight-card">
						<?php echo wbam_icon( 'id-card', array( 'size' => 'lg' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?>
						<h3><?php esc_html_e( 'Advertiser Portal', 'wb-ads-rotator-with-split-test' ); ?></h3>
						<p><?php esc_html_e( 'Complete self-service dashboard for advertisers to register, submit ads, manage campaigns, and track performance.', 'wb-ads-rotator-with-split-test' ); ?></p>
					</div>

					<div class="wbam-highlight-card">
						<?php echo wbam_icon( 'shuffle', array( 'size' => 'lg' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?>
						<h3><?php esc_html_e( 'A/B Testing', 'wb-ads-rotator-with-split-test' ); ?></h3>
						<p><?php esc_html_e( 'Test ad variations automatically and let statistics determine the winner. Optimize your ad performance with data.', 'wb-ads-rotator-with-split-test' ); ?></p>
					</div>

					<div class="wbam-highlight-card">
						<?php echo wbam_icon( 'banknote', array( 'size' => 'lg' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?>
						<h3><?php esc_html_e( 'Monetize Your Site', 'wb-ads-rotator-with-split-test' ); ?></h3>
						<p><?php esc_html_e( 'Accept payments via Stripe, PayPal, or WooCommerce. Offer CPM, CPC, or flat-rate ad packages to advertisers.', 'wb-ads-rotator-with-split-test' ); ?></p>
					</div>

					<div class="wbam-highlight-card">
						<?php echo wbam_icon( 'megaphone', array( 'size' => 'lg' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?>
						<h3><?php esc_html_e( 'Classifieds Marketplace', 'wb-ads-rotator-with-split-test' ); ?></h3>
						<p><?php esc_html_e( 'Full classified listings with categories, locations, seller profiles, image galleries, and paid upgrades.', 'wb-ads-rotator-with-split-test' ); ?></p>
					</div>

					<div class="wbam-highlight-card">
						<?php echo wbam_icon( 'link', array( 'size' => 'lg' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?>
						<h3><?php esc_html_e( 'Keyword Auto-Linking', 'wb-ads-rotator-with-split-test' ); ?></h3>
						<p><?php esc_html_e( 'Set up keywords once, and they automatically become affiliate links across your entire site. Save hours!', 'wb-ads-rotator-with-split-test' ); ?></p>
					</div>

					<div class="wbam-highlight-card">
						<?php echo wbam_icon( 'area-chart', array( 'size' => 'lg' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?>
						<h3><?php esc_html_e( 'Advanced Analytics', 'wb-ads-rotator-with-split-test' ); ?></h3>
						<p><?php esc_html_e( 'Track impressions, clicks, CTR, revenue, geo data, and more. Export reports to CSV for detailed analysis.', 'wb-ads-rotator-with-split-test' ); ?></p>
					</div>
				</div>
			</div>

			<div class="wbam-cta-section">
				<h2><?php esc_html_e( 'Ready to Grow Your Revenue?', 'wb-ads-rotator-with-split-test' ); ?></h2>
				<p><?php esc_html_e( 'Join thousands of website owners who use WB Ad Manager PRO to maximize their advertising income.', 'wb-ads-rotator-with-split-test' ); ?></p>

				<div class="wbam-cta-buttons">
					<a href="https://wbcomdesigns.com/downloads/wb-ad-manager-pro/" target="_blank" class="button button-primary button-hero">
						<?php esc_html_e( 'Get PRO Now', 'wb-ads-rotator-with-split-test' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
	}
}
