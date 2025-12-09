<?php
/**
 * Help & Documentation Admin Page
 *
 * @package WB_Ad_Manager
 * @since   2.2.0
 */

namespace WBAM\Admin;

/**
 * Help_Docs class.
 */
class Help_Docs {

	/**
	 * Instance.
	 *
	 * @var Help_Docs
	 */
	private static $instance = null;

	/**
	 * Is PRO active.
	 *
	 * @var bool
	 */
	private $is_pro_active = false;

	/**
	 * Get instance.
	 *
	 * @return Help_Docs
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
		$this->is_pro_active = defined( 'WBAM_PRO_VERSION' );
		add_action( 'admin_menu', array( $this, 'add_menu' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Add admin menu.
	 */
	public function add_menu() {
		add_submenu_page(
			'edit.php?post_type=wbam-ad',
			__( 'Help & Docs', 'wb-ads-rotator-with-split-test' ),
			__( 'Help & Docs', 'wb-ads-rotator-with-split-test' ),
			'manage_options',
			'wbam-help',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue styles.
	 *
	 * @param string $hook Page hook.
	 */
	public function enqueue_styles( $hook ) {
		if ( 'wbam-ad_page_wbam-help' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'wbam-help-docs',
			WBAM_URL . 'assets/css/help-docs.css',
			array(),
			WBAM_VERSION
		);
	}

	/**
	 * Render page.
	 */
	public function render_page() {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'getting-started';
		?>
		<div class="wrap wbam-help-wrap">
			<h1><?php esc_html_e( 'Help & Documentation', 'wb-ads-rotator-with-split-test' ); ?></h1>

			<nav class="nav-tab-wrapper wbam-nav-tabs">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-help&tab=getting-started' ) ); ?>"
				   class="nav-tab <?php echo 'getting-started' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Getting Started', 'wb-ads-rotator-with-split-test' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-help&tab=features' ) ); ?>"
				   class="nav-tab <?php echo 'features' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Features', 'wb-ads-rotator-with-split-test' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-help&tab=links' ) ); ?>"
				   class="nav-tab <?php echo 'links' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Link Management', 'wb-ads-rotator-with-split-test' ); ?>
				</a>
				<?php if ( $this->is_pro_active ) : ?>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-help&tab=pro-features' ) ); ?>"
				   class="nav-tab <?php echo 'pro-features' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'PRO Features', 'wb-ads-rotator-with-split-test' ); ?>
				</a>
				<?php endif; ?>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-help&tab=faq' ) ); ?>"
				   class="nav-tab <?php echo 'faq' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'FAQ', 'wb-ads-rotator-with-split-test' ); ?>
				</a>
			</nav>

			<div class="wbam-help-content">
				<?php
				switch ( $active_tab ) {
					case 'features':
						$this->render_features_tab();
						break;
					case 'links':
						$this->render_links_tab();
						break;
					case 'pro-features':
						$this->render_pro_features_tab();
						break;
					case 'faq':
						$this->render_faq_tab();
						break;
					default:
						$this->render_getting_started_tab();
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Getting Started tab.
	 */
	private function render_getting_started_tab() {
		?>
		<div class="wbam-help-section">
			<h2><?php esc_html_e( 'Welcome to WB Ad Manager', 'wb-ads-rotator-with-split-test' ); ?></h2>
			<p><?php esc_html_e( 'WB Ad Manager is a powerful advertising and link management solution for WordPress.', 'wb-ads-rotator-with-split-test' ); ?></p>

			<div class="wbam-quick-start">
				<h3><?php esc_html_e( 'Quick Start Guide', 'wb-ads-rotator-with-split-test' ); ?></h3>

				<div class="wbam-step">
					<span class="wbam-step-number">1</span>
					<div class="wbam-step-content">
						<h4><?php esc_html_e( 'Create Your First Ad', 'wb-ads-rotator-with-split-test' ); ?></h4>
						<p><?php esc_html_e( 'Go to WB Ad Manager → Add New to create your first advertisement.', 'wb-ads-rotator-with-split-test' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wbam-ad' ) ); ?>" class="button button-primary">
							<?php esc_html_e( 'Create Ad', 'wb-ads-rotator-with-split-test' ); ?>
						</a>
					</div>
				</div>

				<div class="wbam-step">
					<span class="wbam-step-number">2</span>
					<div class="wbam-step-content">
						<h4><?php esc_html_e( 'Choose Ad Type', 'wb-ads-rotator-with-split-test' ); ?></h4>
						<p><?php esc_html_e( 'Select from Image, HTML/JavaScript, AdSense, or Video ad types.', 'wb-ads-rotator-with-split-test' ); ?></p>
					</div>
				</div>

				<div class="wbam-step">
					<span class="wbam-step-number">3</span>
					<div class="wbam-step-content">
						<h4><?php esc_html_e( 'Set Display Options', 'wb-ads-rotator-with-split-test' ); ?></h4>
						<p><?php esc_html_e( 'Configure where and when your ad should appear using placements and display rules.', 'wb-ads-rotator-with-split-test' ); ?></p>
					</div>
				</div>

				<div class="wbam-step">
					<span class="wbam-step-number">4</span>
					<div class="wbam-step-content">
						<h4><?php esc_html_e( 'Manage Links', 'wb-ads-rotator-with-split-test' ); ?></h4>
						<p><?php esc_html_e( 'Create cloaked affiliate links for better tracking and cleaner URLs.', 'wb-ads-rotator-with-split-test' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-links' ) ); ?>" class="button">
							<?php esc_html_e( 'Manage Links', 'wb-ads-rotator-with-split-test' ); ?>
						</a>
					</div>
				</div>
			</div>

			<div class="wbam-support-box">
				<h3><?php esc_html_e( 'Need Help?', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<p><?php esc_html_e( 'If you have questions or need support, please reach out to us.', 'wb-ads-rotator-with-split-test' ); ?></p>
				<a href="https://wbcomdesigns.com/contact/" target="_blank" class="button">
					<?php esc_html_e( 'Contact Support', 'wb-ads-rotator-with-split-test' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Features tab.
	 */
	private function render_features_tab() {
		$version_label = $this->is_pro_active ? __( 'PRO', 'wb-ads-rotator-with-split-test' ) : __( 'FREE', 'wb-ads-rotator-with-split-test' );
		?>
		<div class="wbam-help-section">
			<?php /* translators: %s: version label (FREE or PRO) */ ?>
			<h2><?php printf( esc_html__( 'Features (%s Version)', 'wb-ads-rotator-with-split-test' ), esc_html( $version_label ) ); ?></h2>

			<div class="wbam-features-grid">
				<div class="wbam-feature-card">
					<span class="dashicons dashicons-format-image"></span>
					<h4><?php esc_html_e( 'Multiple Ad Types', 'wb-ads-rotator-with-split-test' ); ?></h4>
					<p><?php esc_html_e( 'Support for Image, HTML/JS, AdSense, and Video ads.', 'wb-ads-rotator-with-split-test' ); ?></p>
				</div>

				<div class="wbam-feature-card">
					<span class="dashicons dashicons-location"></span>
					<h4><?php esc_html_e( 'Flexible Placements', 'wb-ads-rotator-with-split-test' ); ?></h4>
					<p><?php esc_html_e( 'Display ads before/after content, in sidebars, or custom locations.', 'wb-ads-rotator-with-split-test' ); ?></p>
				</div>

				<div class="wbam-feature-card">
					<span class="dashicons dashicons-admin-links"></span>
					<h4><?php esc_html_e( 'Link Cloaking', 'wb-ads-rotator-with-split-test' ); ?></h4>
					<p><?php esc_html_e( 'Create clean, branded URLs for your affiliate links.', 'wb-ads-rotator-with-split-test' ); ?></p>
				</div>

				<div class="wbam-feature-card">
					<span class="dashicons dashicons-visibility"></span>
					<h4><?php esc_html_e( 'Display Rules', 'wb-ads-rotator-with-split-test' ); ?></h4>
					<p><?php esc_html_e( 'Show or hide ads based on pages, categories, or user roles.', 'wb-ads-rotator-with-split-test' ); ?></p>
				</div>

				<div class="wbam-feature-card">
					<span class="dashicons dashicons-smartphone"></span>
					<h4><?php esc_html_e( 'Device Targeting', 'wb-ads-rotator-with-split-test' ); ?></h4>
					<p><?php esc_html_e( 'Target desktop, tablet, or mobile devices.', 'wb-ads-rotator-with-split-test' ); ?></p>
				</div>

				<div class="wbam-feature-card">
					<span class="dashicons dashicons-chart-bar"></span>
					<h4><?php esc_html_e( 'Basic Click Tracking', 'wb-ads-rotator-with-split-test' ); ?></h4>
					<p><?php esc_html_e( 'Track click counts for your links.', 'wb-ads-rotator-with-split-test' ); ?></p>
				</div>
			</div>

			<?php if ( ! $this->is_pro_active ) : ?>
			<div class="wbam-upgrade-cta">
				<h3><?php esc_html_e( 'Want More Features?', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<p><?php esc_html_e( 'Upgrade to PRO for advanced analytics, auto-linking, advertisers management, and more!', 'wb-ads-rotator-with-split-test' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-upgrade' ) ); ?>" class="button button-primary button-hero">
					<?php esc_html_e( 'View PRO Features', 'wb-ads-rotator-with-split-test' ); ?>
				</a>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render Links tab.
	 */
	private function render_links_tab() {
		?>
		<div class="wbam-help-section">
			<h2><?php esc_html_e( 'Link Management', 'wb-ads-rotator-with-split-test' ); ?></h2>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Creating Links', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<ol>
					<li><?php esc_html_e( 'Go to WB Ad Manager → Links', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Click "Add New Link"', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Enter a name and destination URL', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Customize the slug for your cloaked URL', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Save the link', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ol>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Link Types', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<table class="wbam-doc-table">
					<tr>
						<th><?php esc_html_e( 'Type', 'wb-ads-rotator-with-split-test' ); ?></th>
						<th><?php esc_html_e( 'Use Case', 'wb-ads-rotator-with-split-test' ); ?></th>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Affiliate', 'wb-ads-rotator-with-split-test' ); ?></strong></td>
						<td><?php esc_html_e( 'For affiliate marketing links (Amazon, etc.)', 'wb-ads-rotator-with-split-test' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'External', 'wb-ads-rotator-with-split-test' ); ?></strong></td>
						<td><?php esc_html_e( 'Regular external links you want to track', 'wb-ads-rotator-with-split-test' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Internal', 'wb-ads-rotator-with-split-test' ); ?></strong></td>
						<td><?php esc_html_e( 'Links to your own site pages', 'wb-ads-rotator-with-split-test' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Guest Post', 'wb-ads-rotator-with-split-test' ); ?></strong></td>
						<td><?php esc_html_e( 'Sponsored or guest post links', 'wb-ads-rotator-with-split-test' ); ?></td>
					</tr>
				</table>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Using Cloaked URLs', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<p><?php esc_html_e( 'After creating a link, you can use it in two ways:', 'wb-ads-rotator-with-split-test' ); ?></p>

				<h4><?php esc_html_e( '1. Copy the Cloaked URL', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<p><?php esc_html_e( 'Click the copy button next to any link to copy its cloaked URL:', 'wb-ads-rotator-with-split-test' ); ?></p>
				<code>https://yoursite.com/go/your-link-slug</code>

				<h4><?php esc_html_e( '2. Use Shortcode', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<p><?php esc_html_e( 'Use shortcodes in your posts/pages:', 'wb-ads-rotator-with-split-test' ); ?></p>
				<code>[wbam_link id="1"]</code> <?php esc_html_e( 'or', 'wb-ads-rotator-with-split-test' ); ?> <code>[wbam_link slug="your-link-slug"]</code>
			</div>

			<?php if ( $this->is_pro_active ) : ?>
			<div class="wbam-doc-section wbam-pro-section">
				<h3><span class="wbam-pro-badge">PRO</span> <?php esc_html_e( 'Auto-Linking Keywords', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<p><?php esc_html_e( 'With PRO, you can automatically link keywords in your content:', 'wb-ads-rotator-with-split-test' ); ?></p>
				<ol>
					<li><?php esc_html_e( 'Go to Link Management → Keywords', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Add keywords for any link', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Set max replacements per keyword', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Keywords are automatically linked in your content', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ol>
			</div>
			<?php else : ?>
			<div class="wbam-upgrade-cta">
				<h3><?php esc_html_e( 'Auto-Link Keywords with PRO', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<p><?php esc_html_e( 'Automatically replace keywords in your posts with your affiliate links. Set it once, and it works forever!', 'wb-ads-rotator-with-split-test' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-upgrade' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Learn More', 'wb-ads-rotator-with-split-test' ); ?>
				</a>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render PRO Features tab (only shown when PRO is active).
	 */
	private function render_pro_features_tab() {
		if ( ! $this->is_pro_active ) {
			return;
		}
		?>
		<div class="wbam-help-section">
			<h2><?php esc_html_e( 'PRO Features Guide', 'wb-ads-rotator-with-split-test' ); ?></h2>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Advertiser Management', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<p><?php esc_html_e( 'Manage advertisers who can submit and purchase ad space on your site.', 'wb-ads-rotator-with-split-test' ); ?></p>
				<ul>
					<li><?php esc_html_e( 'Advertisers can register and submit ads', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Approval workflow for submitted ads', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Advertiser dashboard with statistics', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Campaigns & Packages', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<p><?php esc_html_e( 'Create advertising packages and manage campaigns.', 'wb-ads-rotator-with-split-test' ); ?></p>
				<ul>
					<li><?php esc_html_e( 'Define pricing packages (daily, weekly, monthly)', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Campaign scheduling with start/end dates', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Budget and impression limits', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Advanced Analytics', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<p><?php esc_html_e( 'Detailed tracking for ads and links.', 'wb-ads-rotator-with-split-test' ); ?></p>
				<ul>
					<li><?php esc_html_e( 'Impressions and clicks tracking', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Click-through rate (CTR) reporting', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Geographic and device breakdown', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Revenue tracking', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Keyword Auto-Linking', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<p><?php esc_html_e( 'Automatically convert keywords to affiliate links.', 'wb-ads-rotator-with-split-test' ); ?></p>
				<ol>
					<li><?php esc_html_e( 'Create a link with destination URL', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Add keywords to the link', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Set maximum replacements per post', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Keywords are automatically linked site-wide', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ol>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Link Health Checker', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<p><?php esc_html_e( 'Monitor your links for issues.', 'wb-ads-rotator-with-split-test' ); ?></p>
				<ul>
					<li><?php esc_html_e( 'Detect broken links (404 errors)', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Find redirect chains', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Identify slow-loading links', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Automatic or manual health checks', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'CSV Import', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<p><?php esc_html_e( 'Bulk import links and keywords from CSV files.', 'wb-ads-rotator-with-split-test' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-link-import' ) ); ?>" class="button">
					<?php esc_html_e( 'Go to Import', 'wb-ads-rotator-with-split-test' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Render FAQ tab.
	 */
	private function render_faq_tab() {
		?>
		<div class="wbam-help-section">
			<h2><?php esc_html_e( 'Frequently Asked Questions', 'wb-ads-rotator-with-split-test' ); ?></h2>

			<div class="wbam-faq-item">
				<h4><?php esc_html_e( 'How do I display an ad in my sidebar?', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<p><?php esc_html_e( 'Go to Appearance → Widgets and add the "WB Ad Manager" widget to your sidebar. Select the ad you want to display.', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>

			<div class="wbam-faq-item">
				<h4><?php esc_html_e( 'Can I display different ads on different pages?', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<p><?php esc_html_e( 'Yes! Use the Display Rules metabox when editing an ad. You can show ads only on specific pages, categories, or post types.', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>

			<div class="wbam-faq-item">
				<h4><?php esc_html_e( 'How do cloaked links work?', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<p><?php esc_html_e( 'Cloaked links redirect visitors through your domain (e.g., yoursite.com/go/amazon) to the destination URL. This makes links cleaner and allows click tracking.', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>

			<div class="wbam-faq-item">
				<h4><?php esc_html_e( 'Why are my cloaked links showing 404?', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<p><?php esc_html_e( 'Go to Settings → Permalinks and click "Save Changes" to flush the rewrite rules. This should fix the issue.', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>

			<div class="wbam-faq-item">
				<h4><?php esc_html_e( 'What redirect type should I use?', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<ul>
					<li><strong>307</strong> - <?php esc_html_e( 'Temporary redirect (recommended for affiliate links)', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><strong>302</strong> - <?php esc_html_e( 'Found/Temporary redirect', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><strong>301</strong> - <?php esc_html_e( 'Permanent redirect (use for permanent URL changes)', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<?php if ( ! $this->is_pro_active ) : ?>
			<div class="wbam-faq-item">
				<h4><?php esc_html_e( 'How do I automatically link keywords?', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<p><?php esc_html_e( 'Auto-linking keywords is a PRO feature. Upgrade to automatically replace keywords in your content with affiliate links.', 'wb-ads-rotator-with-split-test' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-upgrade' ) ); ?>" class="button button-small">
					<?php esc_html_e( 'Upgrade to PRO', 'wb-ads-rotator-with-split-test' ); ?>
				</a>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
