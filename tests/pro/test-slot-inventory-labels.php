<?php
/**
 * Slot Inventory: accepted formats read as real names, and the per-slot
 * "top" list is honestly labelled as ads, not advertisers (10339876480 step 11).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Analytics\Inventory_Dashboard;

require_once WBAM_PRO_PATH . 'includes/Modules/Analytics/class-inventory-dashboard.php';

/**
 * @group pro
 * @group reports
 */
class Test_Slot_Inventory_Labels extends Pro_Test_Case {

	private function format_label( string $slug ): string {
		$method = new \ReflectionMethod( Inventory_Dashboard::class, 'format_label' );
		$method->setAccessible( true );
		return (string) $method->invoke( null, $slug );
	}

	public function test_known_format_slug_reads_as_its_taxonomy_label(): void {
		$this->assertSame( 'Leaderboard (728x90)', $this->format_label( 'leaderboard' ) );
	}

	public function test_unknown_format_slug_falls_back_to_a_title_cased_guess(): void {
		$this->assertSame( 'Made Up Format', $this->format_label( 'made-up-format' ) );
	}

	public function test_overview_tab_shows_format_names_and_labels_ads_as_ads(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$ad_id = self::factory()->post->create(
			array(
				'post_type'  => 'wbam-ad',
				'post_title' => 'Slot Inventory Test Ad',
			)
		);

		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'wbam_analytics',
			array(
				'ad_id'      => $ad_id,
				'event_type' => 'impression',
				'placement'  => 'header',
				'created_at' => current_time( 'mysql' ),
			)
		);

		$method = new \ReflectionMethod( Inventory_Dashboard::class, 'render_overview_tab' );
		$method->setAccessible( true );
		ob_start();
		$method->invoke( new Inventory_Dashboard() );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Leaderboard (728x90)', $html, 'Accepted formats should read as names, not the raw "leaderboard" slug.' );
		$this->assertStringContainsString( 'Top ads (share of voice)', $html, 'The list holds ad creatives, not advertiser accounts, and should say so.' );
		$this->assertStringNotContainsString( 'Top advertisers (share of voice)', $html );
		$this->assertStringContainsString( 'Slot Inventory Test Ad', $html );
	}
}
