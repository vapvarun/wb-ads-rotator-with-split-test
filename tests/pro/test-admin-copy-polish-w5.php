<?php
/**
 * Owner-approved admin copy polish and two accessible names (card 10344005566).
 *
 * - Settings > Classifieds printed the developer filter name to owners.
 * - The "first paid ad is live" step on All Ads linked back to All Ads.
 * - Campaign delete said only "Item deleted." - now it says the ad moved to
 *   Draft and whether any unspent budget was returned.
 * - A package ran "30 days" in admin but "1 Month" on the site.
 * - Browse's Posted Within select and the distance radius slider had no
 *   accessible name.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Admin\Campaigns_List_Table;
use WBAM_Pro\Core\Next_Step_Banner;
use WBAM_Pro\Core\Pro_Admin;
use WBAM_Pro\Core\Pro_Plugin;
use WBAM_Pro\Modules\Packages\Package;

class Test_Admin_Copy_Polish_W5 extends Pro_Test_Case {

	/**
	 * Snapshot of $_GET.
	 *
	 * @var array
	 */
	private $get_snapshot;

	public function set_up(): void {
		parent::set_up();
		$this->get_snapshot = $_GET;
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function tear_down(): void {
		$_GET = $this->get_snapshot;
		delete_option( Pro_Plugin::SAMPLES_RETIRED_OPTION );
		set_current_screen( 'front' );
		parent::tear_down();
	}

	public function test_classifieds_settings_do_not_show_a_developer_filter_name(): void {
		$method = new \ReflectionMethod( Pro_Admin::class, 'render_classifieds_settings' );
		ob_start();
		$method->invoke( new Pro_Admin() );
		$html = ob_get_clean();

		$this->assertStringNotContainsString( 'wbam_pro_featured_expiry_warning_days', $html );
	}

	public function test_samples_retired_step_does_not_link_to_the_screen_it_shows_on(): void {
		update_option( Pro_Plugin::SAMPLES_RETIRED_OPTION, 2 );
		delete_option( 'wbam_pro_demo_data_ids' );

		$step = Next_Step_Banner::resolve_next_step();

		$this->assertSame( 'samples-retired', $step['slug'] );
		$this->assertNotSame( admin_url( 'edit.php?post_type=wbam-ad' ), $step['cta_url'] );
		$this->assertStringContainsString( 'wbam_enabled_filter=disabled', $step['cta_url'] );
	}

	public function test_campaign_delete_says_what_happens_to_the_ad_and_the_money(): void {
		set_current_screen( 'toplevel_page_wbam-campaigns' );
		$table = new Campaigns_List_Table();
		$base  = array(
			'id'     => 7,
			'name'   => 'Delete copy',
			'status' => 'active',
			'ad_id'  => 0,
		);

		$flat = $table->column_name( (object) ( $base + array( 'pricing_model' => 'flat', 'budget' => 50 ) ) );
		$cpm  = $table->column_name( (object) ( $base + array( 'pricing_model' => 'cpm', 'budget' => 50 ) ) );

		$this->assertStringContainsString( 'Nothing is refunded', $flat );
		$this->assertStringContainsString( 'unspent budget', $cpm );
		$this->assertStringContainsString( 'data-wbam-confirm-text=', $flat, 'The confirm button names the action, not "Yes, proceed".' );

		$_GET['message'] = 'campaign_deleted';
		$method          = new \ReflectionMethod( Pro_Admin::class, 'display_admin_notices' );
		ob_start();
		$method->invoke( new Pro_Admin() );
		$this->assertStringContainsString( 'moved to Draft', ob_get_clean() );
	}

	public function test_package_duration_reads_in_days_everywhere(): void {
		$package = new Package( (object) array( 'duration_days' => 30 ) );
		$this->assertSame( '30 days', $package->get_duration_label() );
	}

	public function test_posted_within_and_radius_have_accessible_names(): void {
		$search  = file_get_contents( WBAM_PRO_PATH . 'templates/classifieds/search-form.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$sidebar = file_get_contents( WBAM_PRO_PATH . 'templates/classifieds/sidebar-filters.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$geo     = file_get_contents( WBAM_PRO_PATH . 'includes/Modules/Geolocation/class-geolocation-manager.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		$this->assertMatchesRegularExpression( '/<label for="wbam-posted-within">.*\n\s*<select name="posted_within" id="wbam-posted-within"/', $search );
		$this->assertMatchesRegularExpression( '/<select name="posted_within"[^>]*aria-label=/', $sidebar );
		$this->assertStringContainsString( '<label for="wbam-geo-filter-radius">', $geo );
	}
}
