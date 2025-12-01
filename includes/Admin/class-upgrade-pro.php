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
			__( 'Upgrade to PRO', 'wb-ad-manager' ),
			'<span style="color: #f9a825;">' . __( 'Upgrade to PRO', 'wb-ad-manager' ) . '</span>',
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
			array(),
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
				<h1><?php esc_html_e( 'Upgrade to WB Ad Manager PRO', 'wb-ad-manager' ); ?></h1>
				<p class="wbam-tagline"><?php esc_html_e( 'Unlock powerful features to maximize your advertising revenue', 'wb-ad-manager' ); ?></p>
			</div>

			<div class="wbam-comparison-section">
				<h2><?php esc_html_e( 'Compare FREE vs PRO', 'wb-ad-manager' ); ?></h2>

				<table class="wbam-comparison-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Feature', 'wb-ad-manager' ); ?></th>
							<th class="wbam-free-col"><?php esc_html_e( 'FREE', 'wb-ad-manager' ); ?></th>
							<th class="wbam-pro-col"><?php esc_html_e( 'PRO', 'wb-ad-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<!-- Ad Management -->
						<tr class="wbam-section-header">
							<td colspan="3"><?php esc_html_e( 'Ad Management', 'wb-ad-manager' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Image, HTML, AdSense, Video Ads', 'wb-ad-manager' ); ?></td>
							<td class="wbam-check"><span class="dashicons dashicons-yes-alt"></span></td>
							<td class="wbam-check"><span class="dashicons dashicons-yes-alt"></span></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Multiple Placements', 'wb-ad-manager' ); ?></td>
							<td class="wbam-check"><span class="dashicons dashicons-yes-alt"></span></td>
							<td class="wbam-check"><span class="dashicons dashicons-yes-alt"></span></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Display Rules (Pages, Categories)', 'wb-ad-manager' ); ?></td>
							<td class="wbam-check"><span class="dashicons dashicons-yes-alt"></span></td>
							<td class="wbam-check"><span class="dashicons dashicons-yes-alt"></span></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Device Targeting', 'wb-ad-manager' ); ?></td>
							<td class="wbam-check"><span class="dashicons dashicons-yes-alt"></span></td>
							<td class="wbam-check"><span class="dashicons dashicons-yes-alt"></span></td>
						</tr>

						<!-- Link Management -->
						<tr class="wbam-section-header">
							<td colspan="3"><?php esc_html_e( 'Link Management', 'wb-ad-manager' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Link Cloaking', 'wb-ad-manager' ); ?></td>
							<td class="wbam-check"><span class="dashicons dashicons-yes-alt"></span></td>
							<td class="wbam-check"><span class="dashicons dashicons-yes-alt"></span></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Basic Click Tracking', 'wb-ad-manager' ); ?></td>
							<td class="wbam-check"><span class="dashicons dashicons-yes-alt"></span></td>
							<td class="wbam-check"><span class="dashicons dashicons-yes-alt"></span></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Link Categories', 'wb-ad-manager' ); ?></td>
							<td class="wbam-check"><span class="dashicons dashicons-yes-alt"></span></td>
							<td class="wbam-check"><span class="dashicons dashicons-yes-alt"></span></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Keyword Auto-Linking', 'wb-ad-manager' ); ?></td>
							<td class="wbam-cross"><span class="dashicons dashicons-minus"></span></td>
							<td class="wbam-check"><span class="dashicons dashicons-yes-alt"></span></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Link Health Checker', 'wb-ad-manager' ); ?></td>
							<td class="wbam-cross"><span class="dashicons dashicons-minus"></span></td>
							<td class="wbam-check"><span class="dashicons dashicons-yes-alt"></span></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'CSV Import (Links + Keywords)', 'wb-ad-manager' ); ?></td>
							<td class="wbam-cross"><span class="dashicons dashicons-minus"></span></td>
							<td class="wbam-check"><span class="dashicons dashicons-yes-alt"></span></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Link Analytics (Detailed)', 'wb-ad-manager' ); ?></td>
							<td class="wbam-cross"><span class="dashicons dashicons-minus"></span></td>
							<td class="wbam-check"><span class="dashicons dashicons-yes-alt"></span></td>
						</tr>

						<!-- Monetization -->
						<tr class="wbam-section-header">
							<td colspan="3"><?php esc_html_e( 'Monetization', 'wb-ad-manager' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Advertiser Registration', 'wb-ad-manager' ); ?></td>
							<td class="wbam-cross"><span class="dashicons dashicons-minus"></span></td>
							<td class="wbam-check"><span class="dashicons dashicons-yes-alt"></span></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Ad Submission System', 'wb-ad-manager' ); ?></td>
							<td class="wbam-cross"><span class="dashicons dashicons-minus"></span></td>
							<td class="wbam-check"><span class="dashicons dashicons-yes-alt"></span></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Advertising Packages', 'wb-ad-manager' ); ?></td>
							<td class="wbam-cross"><span class="dashicons dashicons-minus"></span></td>
							<td class="wbam-check"><span class="dashicons dashicons-yes-alt"></span></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Campaign Management', 'wb-ad-manager' ); ?></td>
							<td class="wbam-cross"><span class="dashicons dashicons-minus"></span></td>
							<td class="wbam-check"><span class="dashicons dashicons-yes-alt"></span></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Payment Integration (WooCommerce, Stripe)', 'wb-ad-manager' ); ?></td>
							<td class="wbam-cross"><span class="dashicons dashicons-minus"></span></td>
							<td class="wbam-check"><span class="dashicons dashicons-yes-alt"></span></td>
						</tr>

						<!-- Analytics -->
						<tr class="wbam-section-header">
							<td colspan="3"><?php esc_html_e( 'Analytics & Reporting', 'wb-ad-manager' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Impressions & Click Tracking', 'wb-ad-manager' ); ?></td>
							<td class="wbam-cross"><span class="dashicons dashicons-minus"></span></td>
							<td class="wbam-check"><span class="dashicons dashicons-yes-alt"></span></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'CTR & Revenue Reports', 'wb-ad-manager' ); ?></td>
							<td class="wbam-cross"><span class="dashicons dashicons-minus"></span></td>
							<td class="wbam-check"><span class="dashicons dashicons-yes-alt"></span></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Geo & Device Analytics', 'wb-ad-manager' ); ?></td>
							<td class="wbam-cross"><span class="dashicons dashicons-minus"></span></td>
							<td class="wbam-check"><span class="dashicons dashicons-yes-alt"></span></td>
						</tr>

						<!-- Classifieds -->
						<tr class="wbam-section-header">
							<td colspan="3"><?php esc_html_e( 'Classifieds', 'wb-ad-manager' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Classified Listings', 'wb-ad-manager' ); ?></td>
							<td class="wbam-cross"><span class="dashicons dashicons-minus"></span></td>
							<td class="wbam-check"><span class="dashicons dashicons-yes-alt"></span></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Locations & Categories', 'wb-ad-manager' ); ?></td>
							<td class="wbam-cross"><span class="dashicons dashicons-minus"></span></td>
							<td class="wbam-check"><span class="dashicons dashicons-yes-alt"></span></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Inquiry System', 'wb-ad-manager' ); ?></td>
							<td class="wbam-cross"><span class="dashicons dashicons-minus"></span></td>
							<td class="wbam-check"><span class="dashicons dashicons-yes-alt"></span></td>
						</tr>

						<!-- Support -->
						<tr class="wbam-section-header">
							<td colspan="3"><?php esc_html_e( 'Support & Updates', 'wb-ad-manager' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Community Support', 'wb-ad-manager' ); ?></td>
							<td class="wbam-check"><span class="dashicons dashicons-yes-alt"></span></td>
							<td class="wbam-check"><span class="dashicons dashicons-yes-alt"></span></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Priority Support', 'wb-ad-manager' ); ?></td>
							<td class="wbam-cross"><span class="dashicons dashicons-minus"></span></td>
							<td class="wbam-check"><span class="dashicons dashicons-yes-alt"></span></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Regular Updates', 'wb-ad-manager' ); ?></td>
							<td class="wbam-check"><span class="dashicons dashicons-yes-alt"></span></td>
							<td class="wbam-check"><span class="dashicons dashicons-yes-alt"></span></td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="wbam-pro-highlights">
				<h2><?php esc_html_e( 'Why Upgrade to PRO?', 'wb-ad-manager' ); ?></h2>

				<div class="wbam-highlights-grid">
					<div class="wbam-highlight-card">
						<span class="dashicons dashicons-admin-links"></span>
						<h3><?php esc_html_e( 'Automatic Keyword Linking', 'wb-ad-manager' ); ?></h3>
						<p><?php esc_html_e( 'Set up keywords once, and they automatically become affiliate links across your entire site. Save hours of manual work!', 'wb-ad-manager' ); ?></p>
					</div>

					<div class="wbam-highlight-card">
						<span class="dashicons dashicons-businessperson"></span>
						<h3><?php esc_html_e( 'Sell Ad Space', 'wb-ad-manager' ); ?></h3>
						<p><?php esc_html_e( 'Let advertisers register, submit ads, and pay for placements. Turn your site into an advertising platform!', 'wb-ad-manager' ); ?></p>
					</div>

					<div class="wbam-highlight-card">
						<span class="dashicons dashicons-chart-area"></span>
						<h3><?php esc_html_e( 'Detailed Analytics', 'wb-ad-manager' ); ?></h3>
						<p><?php esc_html_e( 'Track impressions, clicks, CTR, revenue, and more. Know exactly how your ads perform.', 'wb-ad-manager' ); ?></p>
					</div>

					<div class="wbam-highlight-card">
						<span class="dashicons dashicons-heart"></span>
						<h3><?php esc_html_e( 'Link Health Monitoring', 'wb-ad-manager' ); ?></h3>
						<p><?php esc_html_e( 'Automatically detect broken links, redirect chains, and slow links before they hurt your SEO.', 'wb-ad-manager' ); ?></p>
					</div>

					<div class="wbam-highlight-card">
						<span class="dashicons dashicons-upload"></span>
						<h3><?php esc_html_e( 'Bulk Import', 'wb-ad-manager' ); ?></h3>
						<p><?php esc_html_e( 'Import hundreds of links and keywords from CSV. Perfect for large affiliate sites.', 'wb-ad-manager' ); ?></p>
					</div>

					<div class="wbam-highlight-card">
						<span class="dashicons dashicons-megaphone"></span>
						<h3><?php esc_html_e( 'Classifieds System', 'wb-ad-manager' ); ?></h3>
						<p><?php esc_html_e( 'Run a classifieds marketplace on your site with categories, locations, and inquiry system.', 'wb-ad-manager' ); ?></p>
					</div>
				</div>
			</div>

			<div class="wbam-cta-section">
				<h2><?php esc_html_e( 'Ready to Grow Your Revenue?', 'wb-ad-manager' ); ?></h2>
				<p><?php esc_html_e( 'Join thousands of website owners who use WB Ad Manager PRO to maximize their advertising income.', 'wb-ad-manager' ); ?></p>

				<div class="wbam-cta-buttons">
					<a href="https://wbcomdesigns.com/downloads/wb-ad-manager-pro/" target="_blank" class="button button-primary button-hero">
						<?php esc_html_e( 'Get PRO Now', 'wb-ad-manager' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
	}
}
