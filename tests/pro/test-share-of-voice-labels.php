<?php
/**
 * Share of Voice speaks the advertiser's language.
 *
 * The placement table and Rotation Log showed raw slugs ("after_paragraph"),
 * ISO dates, "(1 ads)" for a count of advertisers, and "Ad #89" for a deleted
 * ad. The REST responses now carry display labels the screen and the CSV use.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

/**
 * @group pro
 * @group reports
 */
class Test_Share_Of_Voice_Labels extends Pro_Test_Case {

	public function test_rotation_responses_carry_display_labels(): void {
		global $wpdb;

		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$adv     = \WBAM_Pro\Modules\Advertisers\Advertiser_Manager::get_instance()->create( $user_id, array( 'status' => 'active' ) );
		$today   = current_time( 'Y-m-d' );

		$wpdb->insert(
			$wpdb->prefix . 'wbam_rotation_stats',
			array(
				'placement'     => 'after_paragraph',
				'ad_id'         => 987654,
				'advertiser_id' => (int) $adv->id,
				'date'          => $today,
				'impressions'   => 4,
				'clicks'        => 1,
			)
		);

		wp_set_current_user( $user_id );

		$request = new \WP_REST_Request( 'GET', '/wbam-pro/v1/rotation/log' );
		$request->set_param( 'date_range', 'week' );
		$log = rest_do_request( $request )->get_data();

		$this->assertSame( 'Deleted ad', $log['log'][0]['ad_name'] );
		$this->assertSame( wbam_pro_get_placement_label( 'after_paragraph' ), $log['log'][0]['placement_label'] );
		$this->assertNotSame( 'after_paragraph', $log['log'][0]['placement_label'] );
		$this->assertSame( date_i18n( get_option( 'date_format' ), strtotime( $today ) ), $log['log'][0]['date_label'] );

		$request = new \WP_REST_Request( 'GET', '/wbam-pro/v1/rotation/share' );
		$request->set_param( 'date_range', 'week' );
		$share = rest_do_request( $request )->get_data();

		$this->assertSame( '1 advertiser', $share['placements'][0]['competitors_label'] );
	}
}
