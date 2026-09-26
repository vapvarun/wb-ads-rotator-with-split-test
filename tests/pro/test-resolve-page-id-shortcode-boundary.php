<?php
/**
 * wbam_pro_resolve_page_id()'s shortcode lookup must not treat one
 * shortcode tag as a prefix match for another. On a fresh install only
 * the auto-created Advertiser Dashboard page exists ([wbam_advertiser_dashboard]);
 * the Advertise page is opt-in only. A bare '%[wbam_advertise%' LIKE pattern
 * matched the dashboard page's shortcode too - "wbam_advertise" is a
 * literal prefix of "wbam_advertiser_dashboard" - and silently wrote the
 * dashboard's page ID into wbam_page_advertise the first time Settings >
 * General rendered. Card 10343787048.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

class Test_Resolve_Page_Id_Shortcode_Boundary extends Pro_Test_Case {

	public function tear_down(): void {
		delete_option( 'wbam_page_advertise' );
		parent::tear_down();
	}

	public function test_advertise_shortcode_does_not_match_advertiser_dashboard_page(): void {
		self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Advertiser Dashboard',
				'post_name'    => 'advertiser-dashboard',
				'post_content' => '<!-- wp:shortcode -->[wbam_advertiser_dashboard]<!-- /wp:shortcode -->',
			)
		);

		$found = wbam_pro_resolve_page_id( 'wbam_page_advertise', array( 'wbam_advertise' ), 'advertise' );

		$this->assertSame( 0, $found, 'The Advertiser Dashboard page must not resolve as the Advertise page.' );
		$this->assertSame( 0, (int) get_option( 'wbam_page_advertise', 0 ), 'Nothing should have been written back either.' );
	}

	public function test_advertise_shortcode_still_matches_its_own_page(): void {
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Advertise with us',
				'post_name'    => 'advertise',
				'post_content' => '<!-- wp:shortcode -->[wbam_advertise]<!-- /wp:shortcode -->',
			)
		);

		$found = wbam_pro_resolve_page_id( 'wbam_page_advertise', array( 'wbam_advertise' ), 'advertise' );

		$this->assertSame( $page_id, $found );
	}
}
