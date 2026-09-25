<?php
/**
 * New QA step on card 10339874920: the submission reject/request-changes
 * summary showed the ad title and status but no advertiser line, unlike
 * every other action-screen summary — reviewing a submission without first
 * opening its detail view left the admin guessing whose ad they were about
 * to reject.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Pro_Admin;
use WBAM_Pro\Modules\AdSubmissions\Ad_Submission_Manager;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

class Test_Submission_Action_Form_Advertiser_Line extends Pro_Test_Case {

	public function test_reject_form_shows_the_advertiser(): void {
		$user       = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		Advertiser_Manager::get_instance()->update_status( (int) $advertiser->id, 'active' );
		Advertiser_Manager::get_instance()->update(
			$advertiser->id,
			array( 'company_name' => 'Advertiser Line QA Co' )
		);
		$advertiser = Advertiser_Manager::get_instance()->get( (int) $advertiser->id );

		\Wbcom\Credits\Credits::topup( 'wbam-pro', $user, 100000, 'seed' );

		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'wbam_packages',
			array(
				'name'          => 'Advertiser Line Package',
				'price'         => 10.00,
				'pricing_model' => 'flat',
				'status'        => 'active',
				'created_at'    => current_time( 'mysql' ),
			)
		);
		$package_id = (int) $wpdb->insert_id;

		$submission = Ad_Submission_Manager::get_instance()->submit_ad(
			$advertiser->id,
			array(
				'title'     => 'Advertiser Line Ad',
				'ad_type'   => 'image',
				'image_url' => 'https://example.com/x.png',
				'link_url'  => 'https://example.com',
			),
			$package_id
		);
		$this->assertNotWPError( $submission );

		$reflection = new \ReflectionMethod( Pro_Admin::class, 'render_submission_action_form' );
		$reflection->setAccessible( true );
		ob_start();
		$reflection->invoke( new Pro_Admin(), $submission->id, 'reject' );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Advertiser Line QA Co', $html );
	}
}
