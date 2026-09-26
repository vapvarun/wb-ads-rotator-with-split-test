<?php
/**
 * Help & Documentation Admin Page
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
			array( 'wbam-admin-tokens' ),
			WBAM_VERSION
		);
	}

	/**
	 * Render page.
	 */
	public function render_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab switcher; no state mutation.
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'getting-started';
		?>
		<div class="wrap wbam-admin wbam-help-wrap">
			<?php
			\WBAM\Admin\UX::page_header(
				array(
					'title' => __( 'Help & Documentation', 'wb-ads-rotator-with-split-test' ),
					'desc'  => __( 'Guides and answers for setting up and running ads.', 'wb-ads-rotator-with-split-test' ),
				)
			);
			?>

			<nav class="nav-tab-wrapper wbam-nav-tabs">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-help&tab=getting-started' ) ); ?>"
					class="nav-tab <?php echo 'getting-started' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Getting Started', 'wb-ads-rotator-with-split-test' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-help&tab=features' ) ); ?>"
					class="nav-tab <?php echo 'features' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Features', 'wb-ads-rotator-with-split-test' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-help&tab=pro-features' ) ); ?>"
					class="nav-tab <?php echo 'pro-features' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php
					echo $this->is_pro_active
						? esc_html__( 'PRO Features', 'wb-ads-rotator-with-split-test' )
						: esc_html__( 'What\'s in PRO', 'wb-ads-rotator-with-split-test' );
					?>
				</a>
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
		$ads_url      = admin_url( 'edit.php?post_type=wbam-ad' );
		$add_ad_url   = admin_url( 'post-new.php?post_type=wbam-ad' );
		$settings_url = admin_url( 'edit.php?post_type=wbam-ad&page=wbam-settings' );
		$links_url    = admin_url( 'admin.php?page=wbam-links' );
		$wizard_url   = admin_url( 'index.php?page=wbam-setup' );
		$tools_url    = admin_url( 'edit.php?post_type=wbam-ad&page=wbam-tools' );
		?>
		<div class="wbam-help-section">
			<?php if ( ! $this->is_pro_active ) : ?>
				<div class="wbam-doc-section">
					<h3><?php esc_html_e( 'If you just installed the plugin', 'wb-ads-rotator-with-split-test' ); ?></h3>
					<p>
						<?php
						printf(
							/* translators: 1: opening anchor tag to the setup wizard, 2: closing anchor tag */
							esc_html__( 'Run the %1$sSetup Wizard%2$s. It takes under a minute and seeds three sample ads (header banner, sidebar code, in-content promo) so you can see how placements work. Remove the samples any time from Tools.', 'wb-ads-rotator-with-split-test' ),
							'<a href="' . esc_url( $wizard_url ) . '">',
							'</a>'
						);
						?>
					</p>
				</div>

				<div class="wbam-doc-section">
					<h3><?php esc_html_e( 'Publish your first ad', 'wb-ads-rotator-with-split-test' ); ?></h3>
					<ol>
						<li>
							<?php
							printf(
								/* translators: 1: opening anchor tag, 2: closing anchor tag */
								esc_html__( 'Go to %1$sAdd New Ad%2$s and give it a title (visitors never see this).', 'wb-ads-rotator-with-split-test' ),
								'<a href="' . esc_url( $add_ad_url ) . '">',
								'</a>'
							);
							?>
						</li>
						<li><?php esc_html_e( 'Pick an ad type (Image, Rich Content, HTML/JS Code, Google AdSense, or Email Capture) and fill in its content.', 'wb-ads-rotator-with-split-test' ); ?></li>
						<li><?php esc_html_e( 'Check one or more placements in the Placements box (header, footer, after paragraph X, sidebar widget, popup, sticky bar, etc.).', 'wb-ads-rotator-with-split-test' ); ?></li>
						<li><?php esc_html_e( 'Set Priority 1 to 10 in the Ad Status box. When several ads share a placement, higher-priority ads are shown more often.', 'wb-ads-rotator-with-split-test' ); ?></li>
						<li><?php esc_html_e( 'Publish. The ad starts appearing immediately in every placement you selected.', 'wb-ads-rotator-with-split-test' ); ?></li>
					</ol>
				</div>

				<div class="wbam-doc-section">
					<h3><?php esc_html_e( 'Where things live', 'wb-ads-rotator-with-split-test' ); ?></h3>
					<ul>
						<li><strong><a href="<?php echo esc_url( $ads_url ); ?>"><?php esc_html_e( 'All Ads', 'wb-ads-rotator-with-split-test' ); ?></a></strong>: <?php esc_html_e( 'the ad list with impression and click counts, plus status filters.', 'wb-ads-rotator-with-split-test' ); ?></li>
						<li><strong><a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Settings', 'wb-ads-rotator-with-split-test' ); ?></a></strong>: <?php esc_html_e( 'global display options, AdSense publisher ID, geo-provider, privacy toggles.', 'wb-ads-rotator-with-split-test' ); ?></li>
						<li><strong><a href="<?php echo esc_url( $links_url ); ?>"><?php esc_html_e( 'Links', 'wb-ads-rotator-with-split-test' ); ?></a></strong>: <?php esc_html_e( 'cloaked affiliate URLs and the [wbam_partnership_inquiry] admin queue.', 'wb-ads-rotator-with-split-test' ); ?></li>
					</ul>
				</div>

				<?php if ( ! \WBAM\Modules\Jetonomy\Jetonomy_Module::is_jetonomy_active() ) : ?>
					<div class="wbam-doc-section">
						<h3><?php esc_html_e( 'Integrations', 'wb-ads-rotator-with-split-test' ); ?></h3>
						<p>
							<?php
							printf(
								/* translators: 1: opening link to Jetonomy store page, 2: closing link tag */
								esc_html__( 'Running a community forum? Install %1$sJetonomy%2$s to unlock seven more placement positions (sidebar, topic, and reply injection points).', 'wb-ads-rotator-with-split-test' ),
								'<a href="https://store.wbcomdesigns.com/jetonomy/" target="_blank" rel="noopener noreferrer">',
								'</a>'
							);
							?>
						</p>
					</div>
				<?php endif; ?>
			<?php else : ?>
				<div class="wbam-doc-section">
					<h3><?php esc_html_e( 'Publish your first ad', 'wb-ads-rotator-with-split-test' ); ?></h3>
					<ol>
						<li>
							<?php
							printf(
								/* translators: 1: opening anchor tag, 2: closing anchor tag */
								esc_html__( 'Go to %1$sAdd New Ad%2$s. Pick an ad type and fill in the content.', 'wb-ads-rotator-with-split-test' ),
								'<a href="' . esc_url( $add_ad_url ) . '">',
								'</a>'
							);
							?>
						</li>
						<li><?php esc_html_e( 'Assign placements and priority, then publish.', 'wb-ads-rotator-with-split-test' ); ?></li>
					</ol>
				</div>

				<div class="wbam-doc-section">
					<h3><?php esc_html_e( 'Pro-only admin areas', 'wb-ads-rotator-with-split-test' ); ?></h3>
					<ol>
						<li>
							<?php
							printf(
								/* translators: 1: opening anchor tag, 2: closing anchor tag */
								esc_html__( 'Seed demo data from %1$sTools%2$s. Creates sample ads, classifieds, advertisers, and 30 days of analytics so you can explore every Pro screen with real numbers. The itemized "Remove" button wipes them when you are done.', 'wb-ads-rotator-with-split-test' ),
								'<a href="' . esc_url( $tools_url ) . '">',
								'</a>'
							);
							?>
						</li>
						<li>
							<?php
							printf(
								/* translators: 1: opening anchor tag, 2: closing anchor tag */
								esc_html__( 'Turn modules on or off in %1$sSettings > Advertising%2$s (Classifieds, Campaigns, Wallet, A/B Testing, etc.). Each module adds its own submenu under WB Ad Manager.', 'wb-ads-rotator-with-split-test' ),
								'<a href="' . esc_url( \WBAM\Core\Admin_Links::settings( 'advertising' ) ) . '">',
								'</a>'
							);
							?>
						</li>
					</ol>
				</div>
			<?php endif; ?>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Need help?', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<p>
					<?php
					printf(
						/* translators: 1: opening anchor tag for WP.org forum, 2: closing tag, 3: opening anchor tag for Wbcom contact, 4: closing tag */
						esc_html__( 'Free users: %1$sWordPress.org support forum%2$s. Pro customers: %3$sopen a priority ticket%4$s with Wbcom Designs.', 'wb-ads-rotator-with-split-test' ),
						'<a href="https://wordpress.org/support/plugin/wb-ads-rotator-with-split-test/" target="_blank" rel="noopener">',
						'</a>',
						'<a href="https://wbcomdesigns.com/contact/" target="_blank" rel="noopener">',
						'</a>'
					);
					?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Features tab.
	 */
	private function render_features_tab() {
		?>
		<div class="wbam-help-section">
			<?php
			// Plain "Features" - "Features (PRO Version)" read as the same
			// thing as the separate "PRO Features" tab next to it, when this
			// tab actually covers every feature on the site, Free or Pro.
			?>
			<h2><?php esc_html_e( 'Features', 'wb-ads-rotator-with-split-test' ); ?></h2>

			<?php
			// Counts the live registry (Free's 5 built-in types, plus Pro's
			// Video Ad when Pro is active) instead of a number that drifts
			// out of sync the next time a type is added.
			$ad_type_count = count( \WBAM\Modules\Placements\Placement_Engine::get_instance()->get_ad_types() );
			?>
			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Ad Management', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<ul>
					<li>
						<strong>
							<?php
							/* translators: %d: number of ad types available */
							printf( esc_html( _n( '%d ad type:', '%d ad types:', $ad_type_count, 'wb-ads-rotator-with-split-test' ) ), (int) $ad_type_count );
							?>
						</strong>
						<?php if ( $this->is_pro_active ) : ?>
							<?php esc_html_e( 'Image, Rich Content (HTML editor), HTML/JS Code, Google AdSense, Email Capture (inline subscribe form), and Video (Pro).', 'wb-ads-rotator-with-split-test' ); ?>
						<?php else : ?>
							<?php esc_html_e( 'Image, Rich Content (HTML editor), HTML/JS Code, Google AdSense, and Email Capture (inline subscribe form).', 'wb-ads-rotator-with-split-test' ); ?>
						<?php endif; ?>
					</li>
					<li><strong><?php esc_html_e( 'Weighted rotation:', 'wb-ads-rotator-with-split-test' ); ?></strong> <?php esc_html_e( 'Assign each ad a 1-10 priority slider; higher priorities win more often in the same placement.', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><strong><?php esc_html_e( 'A/B comparison box:', 'wb-ads-rotator-with-split-test' ); ?></strong> <?php esc_html_e( 'Side-by-side impressions / clicks / CTR across ads sharing a placement, with an automatic "winner" badge at 100+ impressions.', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><strong><?php esc_html_e( 'Frequency control:', 'wb-ads-rotator-with-split-test' ); ?></strong> <?php esc_html_e( 'Per-ad session impression cap + global max-ads-per-page, plus lazy loading for below-the-fold ads.', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Placements. 16+ positions', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<p><?php esc_html_e( 'Each ad can be assigned to as many placements as you want, across standard WordPress and three community plugins.', 'wb-ads-rotator-with-split-test' ); ?></p>
				<ul>
					<li><strong><?php esc_html_e( 'Page positions:', 'wb-ads-rotator-with-split-test' ); ?></strong> <?php esc_html_e( 'Header, footer, before / after content, after paragraph X, before / after archive loop.', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><strong><?php esc_html_e( 'Overlays:', 'wb-ads-rotator-with-split-test' ); ?></strong> <?php esc_html_e( 'Popup / modal (delay, scroll %, or exit-intent triggers) and sticky bar (4 positions: top-bar, bottom-bar, bottom-right, bottom-left).', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><strong><?php esc_html_e( 'Widgets & shortcode:', 'wb-ads-rotator-with-split-test' ); ?></strong> <?php esc_html_e( 'Sidebar widget plus a [wbam_ad id="123"] shortcode for exact placement control.', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><strong><?php esc_html_e( 'BuddyPress:', 'wb-ads-rotator-with-split-test' ); ?></strong> <?php esc_html_e( 'Activity stream + 4 directory positions (before / after members and groups).', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><strong><?php esc_html_e( 'bbPress:', 'wb-ads-rotator-with-split-test' ); ?></strong> <?php esc_html_e( '7 positions including between-replies with a configurable frequency (every N replies, repeating or once).', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><strong><?php esc_html_e( 'Jetonomy:', 'wb-ads-rotator-with-split-test' ); ?></strong> <?php esc_html_e( '7 positions: sidebar top / bottom / after-about, after topic body, before / between / after replies.', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Targeting & Scheduling', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<ul>
					<li><strong><?php esc_html_e( 'Visitor targeting:', 'wb-ads-rotator-with-split-test' ); ?></strong> <?php esc_html_e( 'Device (desktop / tablet / mobile), user status (logged in / out), and user role (Administrator, Editor, Author, Subscriber, or any custom role).', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><strong><?php esc_html_e( 'Content targeting:', 'wb-ads-rotator-with-split-test' ); ?></strong> <?php esc_html_e( 'Include / exclude by specific post, page, category, tag, post type, or page template.', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><strong><?php esc_html_e( 'Geo targeting:', 'wb-ads-rotator-with-split-test' ); ?></strong> <?php esc_html_e( 'Country-level targeting via ip-api.com, ipinfo.io, or ipapi.co with automatic provider fallback.', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><strong><?php esc_html_e( 'Scheduling:', 'wb-ads-rotator-with-split-test' ); ?></strong> <?php esc_html_e( 'Start / end dates, specific days of the week, and time-of-day ranges (using your WordPress timezone).', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Link Management', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<p><?php esc_html_e( 'Turn long affiliate URLs into short, trackable links on your own domain. Redirects are 307 by default, cloaked through /go/slug, and every click is counted.', 'wb-ads-rotator-with-split-test' ); ?></p>
				<ul>
					<li><strong><?php esc_html_e( 'Cloaked URLs + click tracking:', 'wb-ads-rotator-with-split-test' ); ?></strong> <?php esc_html_e( 'yoursite.com/go/book → destination, with per-link click counts in the list.', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><strong><?php esc_html_e( 'Categories & filtering:', 'wb-ads-rotator-with-split-test' ); ?></strong> <?php esc_html_e( 'Group links (e.g. "Amazon", "Software deals"), filter the list table, or render a category list with [wbam_links category="slug"].', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><strong><?php esc_html_e( 'SEO controls:', 'wb-ads-rotator-with-split-test' ); ?></strong> <?php esc_html_e( 'rel="nofollow" and rel="sponsored" toggles per link; 301 / 302 / 307 redirect type per link.', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><strong><?php esc_html_e( 'Expiration dates:', 'wb-ads-rotator-with-split-test' ); ?></strong> <?php esc_html_e( 'Optional expiry so time-limited offers stop redirecting automatically.', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><strong><?php esc_html_e( 'Link Partnerships:', 'wb-ads-rotator-with-split-test' ); ?></strong> <?php esc_html_e( 'Drop [wbam_partnership_inquiry] on any page to accept paid-link / exchange / sponsored-post inquiries with accept-reject workflow and auto emails.', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Privacy & GDPR', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<ul>
					<li><strong><?php esc_html_e( 'IP anonymization:', 'wb-ads-rotator-with-split-test' ); ?></strong> <?php esc_html_e( 'Raw IPs are hashed before storage in the analytics table.', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><strong><?php esc_html_e( 'Consent-gated AdSense:', 'wb-ads-rotator-with-split-test' ); ?></strong> <?php esc_html_e( 'Opt-in flag that delays AdSense script loading until a cookie consent plugin signals consent.', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><strong><?php esc_html_e( 'Opt-in delete on uninstall:', 'wb-ads-rotator-with-split-test' ); ?></strong> <?php esc_html_e( 'Plugin preserves your data by default; enable the setting for a full wipe when you uninstall.', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Developer API', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<ul>
					<li><strong><?php esc_html_e( 'REST API:', 'wb-ads-rotator-with-split-test' ); ?></strong> <?php esc_html_e( 'Endpoints under /wp-json/wbam/v1/ covering ads, analytics, links, partnerships, settings, and email captures.', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><strong><?php esc_html_e( 'Hooks & filters:', 'wb-ads-rotator-with-split-test' ); ?></strong> <?php esc_html_e( '100+ action and filter hooks on every write operation, with custom ad-type and placement extension points.', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><strong><?php esc_html_e( 'Abilities API (WP 6.9+):', 'wb-ads-rotator-with-split-test' ); ?></strong> <?php esc_html_e( '15 named abilities for AI and headless consumers of the ads data.', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><strong><?php esc_html_e( 'i18n ready:', 'wb-ads-rotator-with-split-test' ); ?></strong> <?php esc_html_e( 'POT file ships in /languages; every user-facing string is translatable.', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<?php if ( ! $this->is_pro_active ) : ?>
			<div class="wbam-upgrade-cta">
				<h3><?php esc_html_e( 'Unlock PRO', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<p><?php esc_html_e( 'Turn your site into an ad marketplace. Pro adds an advertiser portal, wallet & payments, classifieds, campaigns with budgets, advanced analytics, and more. Keep all the Free features. Add revenue on top.', 'wb-ads-rotator-with-split-test' ); ?></p>
				<p>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-help&tab=pro-features' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'See PRO Features', 'wb-ads-rotator-with-split-test' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-upgrade' ) ); ?>" class="button">
						<?php esc_html_e( 'Full Free vs PRO Comparison', 'wb-ads-rotator-with-split-test' ); ?>
					</a>
				</p>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render PRO Features tab (teaser when Pro isn't active, full guide when it is).
	 */
	private function render_pro_features_tab() {
		if ( ! $this->is_pro_active ) {
			$this->render_pro_teaser();
			return;
		}
		?>
		<div class="wbam-help-section">
			<h2><?php esc_html_e( 'PRO Features Guide', 'wb-ads-rotator-with-split-test' ); ?></h2>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Classifieds Marketplace', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<p><?php esc_html_e( 'A full-featured classifieds system for your site.', 'wb-ads-rotator-with-split-test' ); ?></p>
				<ul>
					<li><?php esc_html_e( 'Users post and browse classified listings with images', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Category and location filters with sidebar search', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Listing upgrades: Featured, Highlighted, Urgent, Bump', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Inquiry system for buyer-seller communication', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Reviews and ratings for sellers', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Custom fields builder for category-specific data', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Advertiser Portal', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<p><?php esc_html_e( 'A self-service dashboard for advertisers with 14 tabs.', 'wb-ads-rotator-with-split-test' ); ?></p>
				<ul>
					<li><?php esc_html_e( 'Overview, My Ads, Campaigns, Classifieds, Inquiries', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Favorites, Following, Messages, Link Partnerships', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Wallet (credit balance and transaction history)', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Membership plans, Analytics, Share of Voice, Profile', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Credits & Wallet System', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<p><?php esc_html_e( 'Built-in credit system advertisers use to pay for ads and listings.', 'wb-ads-rotator-with-split-test' ); ?></p>
				<ul>
					<li><?php esc_html_e( 'Advertisers purchase credits to pay for ads and listings', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Supports WooCommerce, Stripe, PayPal, and manual payments', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Hold > Deduct > Refund lifecycle for safe billing', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Full transaction ledger with audit trail', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Campaigns & Packages', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Create ad packages with pricing, duration, and impression limits', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Flat, CPM, and CPC pricing models', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Campaign scheduling with start/end dates and budget caps', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Budget-aware pacing and per-advertiser session caps', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Advanced Analytics', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Impressions, clicks, and CTR tracking with daily aggregation', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Geographic and device breakdown', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Revenue dashboard and billing-proof ledger', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Slot inventory view (AdSense-style capacity overview)', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Share of Voice analysis per advertiser', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'A/B Testing & Rotation', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Test ad variations with traffic splitting', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Fair rotation engine with equal-share distribution', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Frequency caps per advertiser and campaign', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Membership Plans', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Create subscription plans with listing limits and billing cycles', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Monthly, quarterly, and yearly billing', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Automatic renewal and expiration notifications', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Link Management (PRO)', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Auto-linking keywords in your content', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Link health checker (broken links, redirects)', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Link Scanner. Find monetization opportunities in existing content', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'CSV bulk import for links and keywords', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the "What's in PRO" teaser (shown on free installs).
	 *
	 * Mirrors the same categories as the Upgrade page so free users get
	 * a clear overview without clicking away.
	 *
	 * @return void
	 */
	private function render_pro_teaser() {
		?>
		<div class="wbam-help-section wbam-pro-teaser">
			<h2><?php esc_html_e( 'What\'s in WB Ad Manager PRO', 'wb-ads-rotator-with-split-test' ); ?></h2>
			<p class="wbam-pro-teaser-intro">
				<?php esc_html_e( 'PRO keeps everything you have in the Free plugin and adds a full monetization layer. Advertiser portal, wallet, campaigns, classifieds, and revenue analytics. Here\'s what you get when you upgrade.', 'wb-ads-rotator-with-split-test' ); ?>
			</p>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Advertiser Portal', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<p><?php esc_html_e( 'Let advertisers sign up, submit, and manage their own ads. You review & approve.', 'wb-ads-rotator-with-split-test' ); ?></p>
				<ul>
					<li><?php esc_html_e( 'Advertiser registration and dedicated dashboard (14 tabs: Overview, My Ads, Campaigns, Classifieds, Inquiries, Favorites, Following, Messages, Link Partnerships, Wallet, Membership, Analytics, Share of Voice, Profile)', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Self-service ad submission with admin review queue', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Per-advertiser caps and share-of-voice reporting', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Wallet, Credits & Payments', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Prepaid credit wallet per advertiser (hold → deduct → refund lifecycle)', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'WooCommerce, Stripe, PayPal, and manual top-up integrations', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Full transaction ledger with audit trail and CSV export', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'CPM, CPC, and flat-rate billing models', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Campaigns & Packages', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Ad packages with price, duration, and impression limits', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Campaign scheduling with start / end dates and budget caps', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Budget-aware pacing and per-advertiser session caps', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Membership plans (monthly / quarterly / yearly) with listing limits and auto-renewal', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Classifieds Marketplace', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Full classified listings system with image galleries', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Category + location taxonomies with sidebar search', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Paid upgrades: Featured, Highlighted, Urgent, Bump to top', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Multiple price types: fixed, negotiable, free', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Buyer inquiry system, favorites / saved listings, seller profiles with reviews and ratings', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Custom fields builder for category-specific data', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Advanced Analytics & A/B Testing', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Daily impression & click aggregation with time-series reports', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'CTR and revenue reports, geo + device breakdowns, CSV export', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'A/B testing with statistical significance and traffic splitting', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Slot inventory view. AdSense-style capacity overview', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Share of Voice analysis per advertiser', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Advanced Link Management', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Keyword auto-linking. Automatically link mentions of your keywords', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Link Scanner. Find monetization opportunities in existing posts', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Broken-link detection and redirect management', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'CSV bulk import for links and keywords', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Advanced link analytics (referrer, device, country)', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-doc-section">
				<h3><?php esc_html_e( 'Community & Developer Extras', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Enhanced BuddyPress integration. Seller profiles in member directory, activity stream for listings, following/favorites system', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Admin audit logs of every ad / credit / campaign action', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Ad review queue with approval workflow', 'wb-ads-rotator-with-split-test' ); ?></li>
					<li><?php esc_html_e( 'Priority support from Wbcom Designs', 'wb-ads-rotator-with-split-test' ); ?></li>
				</ul>
			</div>

			<div class="wbam-upgrade-cta">
				<h3><?php esc_html_e( 'Ready to turn your site into a revenue engine?', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<p><?php esc_html_e( 'See the full side-by-side comparison and pick your plan.', 'wb-ads-rotator-with-split-test' ); ?></p>
				<p>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-upgrade' ) ); ?>" class="button button-primary button-hero">
						<?php esc_html_e( 'Full Free vs PRO Comparison', 'wb-ads-rotator-with-split-test' ); ?>
					</a>
					<a href="https://wbcomdesigns.com/downloads/wb-ad-manager-pro/" target="_blank" rel="noopener" class="button button-hero">
						<?php esc_html_e( 'View Pricing on wbcomdesigns.com', 'wb-ads-rotator-with-split-test' ); ?>
					</a>
				</p>
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

			<h3><?php esc_html_e( 'Ads', 'wb-ads-rotator-with-split-test' ); ?></h3>

			<div class="wbam-faq-item">
				<h4><?php esc_html_e( 'How do I display an ad?', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<p><?php esc_html_e( 'Create an ad under WB Ad Manager > Add New, select a placement (header, footer, sidebar, before/after content), and publish. The ad appears automatically in that position on your site.', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>

			<div class="wbam-faq-item">
				<h4><?php esc_html_e( 'Can I show different ads on different pages?', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<p><?php esc_html_e( 'Yes. Use the Display Rules box when editing an ad. You can target specific pages, categories, post types, or user roles.', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>

			<div class="wbam-faq-item">
				<h4><?php esc_html_e( 'What ad types are supported?', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<p><?php esc_html_e( 'Five ad types: Image (with click URL and alt text), Rich Content (HTML-editor), HTML/JavaScript Code, Google AdSense, and Email Capture (inline newsletter form).', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>

			<div class="wbam-faq-item">
				<h4><?php esc_html_e( 'How does the Email Capture ad type work?', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<p><?php esc_html_e( 'Email Capture renders an inline subscribe form anywhere you assign it as a placement. You control the headline, description, button text, colours, and success message. Submissions fire the wbam_email_captured action so you can forward them to Mailchimp, ConvertKit, or any webhook. There\'s no external service tie-in.', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>

			<div class="wbam-faq-item">
				<h4><?php esc_html_e( 'Can I accept partnership / paid link inquiries from my site?', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<p><?php esc_html_e( 'Yes. Drop [wbam_partnership_inquiry] on any page. Visitors fill out a structured form (partnership type, budget, target page, anchor text). Inquiries appear under WB Ad Manager → Link Partnerships with accept / reject workflow and automatic email notifications.', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>

			<h3><?php esc_html_e( 'Links', 'wb-ads-rotator-with-split-test' ); ?></h3>

			<div class="wbam-faq-item">
				<h4><?php esc_html_e( 'How do cloaked links work?', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<p><?php esc_html_e( 'Cloaked links redirect visitors through your domain (e.g., yoursite.com/go/amazon) to the destination URL. This makes links cleaner and enables click tracking.', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>

			<div class="wbam-faq-item">
				<h4><?php esc_html_e( 'My cloaked links show a 404 page. How do I fix this?', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<p><?php esc_html_e( 'Go to Settings > Permalinks and click "Save Changes" to flush the rewrite rules. This resolves the issue.', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>

			<?php if ( $this->is_pro_active ) : ?>
			<h3><?php esc_html_e( 'Classifieds', 'wb-ads-rotator-with-split-test' ); ?></h3>

			<div class="wbam-faq-item">
				<h4><?php esc_html_e( 'How do users post classifieds?', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<p><?php esc_html_e( 'Users visit the Advertiser Dashboard page and use the Classifieds tab to submit listings. The multi-step wizard guides them through title, description, images, category, price, and contact info. Listings can require admin approval before going live.', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>

			<div class="wbam-faq-item">
				<h4><?php esc_html_e( 'How does the credit/wallet system work?', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<p><?php esc_html_e( 'Advertisers purchase credits which are used to pay for ad submissions, classified listings, and upgrades. Credits are held when a listing is submitted and deducted when approved (or refunded if rejected). Configure payment methods in Settings > Credits.', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>

			<div class="wbam-faq-item">
				<h4><?php esc_html_e( 'Where is the Advertiser Dashboard?', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<p>
					<?php esc_html_e( 'The dashboard is created automatically on plugin activation. You can find or reassign it in', 'wb-ads-rotator-with-split-test' ); ?>
					<a href="<?php echo esc_url( \WBAM\Core\Admin_Links::settings( 'advertising' ) ); ?>"><?php esc_html_e( 'Settings > Advertising > Pages', 'wb-ads-rotator-with-split-test' ); ?></a>.
				</p>
			</div>

			<h3><?php esc_html_e( 'Demo Data', 'wb-ads-rotator-with-split-test' ); ?></h3>

			<div class="wbam-faq-item">
				<h4><?php esc_html_e( 'How do I import demo data?', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<p>
					<?php esc_html_e( 'Go to', 'wb-ads-rotator-with-split-test' ); ?>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-tools' ) ); ?>"><?php esc_html_e( 'WB Ad Manager > Tools', 'wb-ads-rotator-with-split-test' ); ?></a>
					<?php esc_html_e( 'and click "Import Demo Data". This creates sample ads, classifieds, advertisers, campaigns, and 30 days of analytics.', 'wb-ads-rotator-with-split-test' ); ?>
				</p>
			</div>

			<div class="wbam-faq-item">
				<h4><?php esc_html_e( 'How do I remove demo data?', 'wb-ads-rotator-with-split-test' ); ?></h4>
				<p><?php esc_html_e( 'After importing, the Tools page shows a "Remove All Demo Data" button with an itemized list of what will be deleted. Your real content is never touched. Every item is verified against the demo flag before removal.', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>
			<?php endif; ?>

			<div class="wbam-support-box" style="margin-top: 30px;">
				<h3><?php esc_html_e( 'Still have questions?', 'wb-ads-rotator-with-split-test' ); ?></h3>
				<a href="https://wbcomdesigns.com/contact/" target="_blank" class="button">
					<?php esc_html_e( 'Contact Support', 'wb-ads-rotator-with-split-test' ); ?>
				</a>
			</div>
		</div>
		<?php
	}
}
