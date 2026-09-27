<?php
/**
 * Owner-seat wording polish (card 10344383905): settings and editor say
 * what the plugin actually does, and an ad that can never show says why.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Admin\Placement_Settings;
use WBAM\Core\Ad_Status;
use WBAM\Modules\Placements\Placement_Engine;
use WP_UnitTestCase;

class Test_Settings_Wording_Polish extends WP_UnitTestCase {

	private function ad( array $meta = array() ): int {
		$ad_id = (int) self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_status' => 'publish' ) );
		$meta += array(
			'_wbam_enabled'    => '1',
			'_wbam_placements' => array( 'header' ),
			'_wbam_ad_data'    => array(
				'type'    => 'rich-content',
				'content' => '<p>Probe</p>',
			),
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $ad_id, $key, $value );
		}
		return $ad_id;
	}

	public function tear_down(): void {
		remove_all_filters( 'wbam_show_ad_label' );
		wp_cache_delete( 'wbam_placement_ad_counts', 'wbam' );
		parent::tear_down();
	}

	public function test_specific_pages_with_nothing_picked_is_not_showing(): void {
		$status = Ad_Status::get( $this->ad( array( '_wbam_display_rules' => array( 'display_on' => 'specific' ) ) ) );

		$this->assertSame( Ad_Status::NOT_SHOWING, $status['state'] );
		$this->assertStringContainsString( 'nothing is picked', $status['reason'] );
		$this->assertSame( Ad_Status::LIVE, Ad_Status::get( $this->ad( array( '_wbam_display_rules' => array( 'display_on' => 'specific', 'page_types' => array( 'home' ) ) ) ) )['state'] );
	}

	public function test_logged_in_only_ad_on_a_site_hiding_ads_from_logged_in_users(): void {
		$settings                          = (array) get_option( 'wbam_settings', array() );
		$settings['disable_ads_logged_in'] = true;
		update_option( 'wbam_settings', $settings );

		$status = Ad_Status::get( $this->ad( array( '_wbam_visitor_conditions' => array( 'user_status' => 'logged_in' ) ) ) );

		$this->assertSame( Ad_Status::NOT_SHOWING, $status['state'] );
		$this->assertStringContainsString( 'logged-in', $status['reason'] );
	}

	public function test_auto_ads_stays_off_without_a_publisher_id(): void {
		$clean = ( new \WBAM\Admin\Settings() )->sanitize_settings(
			array(
				'adsense_auto_ads'     => 1,
				'adsense_publisher_id' => '',
			)
		);

		$this->assertFalse( $clean['adsense_auto_ads'] );
	}

	public function test_live_ads_count_skips_ads_that_cannot_show(): void {
		$this->ad();
		$this->ad( array( '_wbam_end_date' => '2020-01-01' ) );
		wp_cache_delete( 'wbam_placement_ad_counts', 'wbam' );

		$this->assertSame( 1, Placement_Settings::get_ad_counts()['header'] ?? 0 );
	}

	public function test_group_headings_are_names_not_ids(): void {
		$this->assertSame( 'BuddyPress', Placement_Engine::group_label( 'buddypress' ) );
		$this->assertSame( 'bbPress', Placement_Engine::group_label( 'bbpress' ) );
		$this->assertSame( 'Overlay', Placement_Engine::group_label( 'advanced' ) );
	}

	public function test_the_sites_own_signup_form_has_no_advertisement_label(): void {
		$ad_id = $this->ad(
			array(
				'_wbam_ad_data' => array(
					'type'     => 'email_capture',
					'headline' => 'Join',
				),
			)
		);
		$args  = array( 'skip_targeting' => true, 'allow_duplicate' => true );

		$this->assertStringNotContainsString( 'wbam-ad-label', Placement_Engine::get_instance()->render_ad( $ad_id, $args ) );

		add_filter( 'wbam_show_ad_label', '__return_true' );
		$this->assertStringContainsString( 'wbam-ad-label', Placement_Engine::get_instance()->render_ad( $ad_id, $args ) );
	}
}
