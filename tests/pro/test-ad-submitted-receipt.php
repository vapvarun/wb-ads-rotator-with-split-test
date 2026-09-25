<?php
/**
 * An advertiser whose ad waits for review gets a receipt, and the email
 * templates no code sends are gone.
 *
 * templates/emails/ad-submitted.php (the advertiser's "submitted for
 * review" receipt) was never sent, so the Emails tab's "Ad Submitted"
 * toggle was on and the advertiser heard nothing after submitting.
 * advertiser-low-balance.php was superseded by wallet-low-balance.php, and
 * receipt.php expected the Transaction model removed in 1.5.0 and would
 * fatal on real data.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\AdSubmissions\Ad_Submission_Manager;

class Test_Ad_Submitted_Receipt extends Pro_Test_Case {

	public function test_pending_submission_sends_the_advertiser_a_receipt(): void {
		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		Advertiser_Manager::get_instance()->update_status( (int) $advertiser->id, 'active' );
		\Wbcom\Credits\Credits::topup( 'wbam-pro', $user, 100000, 'seed' );
		wp_set_current_user( $user );

		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'wbam_packages',
			array(
				'name'              => 'Reviewed Flat',
				'price'             => 10.00,
				'pricing_model'     => 'flat',
				'status'            => 'active',
				'requires_approval' => 1,
				'duration_days'     => 30,
				'created_at'        => current_time( 'mysql' ),
			)
		);
		$package_id = (int) $wpdb->insert_id;

		$sent = array();
		add_filter(
			'pre_wp_mail',
			function ( $short, $atts ) use ( &$sent ) {
				$sent[] = $atts;
				return true;
			},
			10,
			2
		);

		$submission = Ad_Submission_Manager::get_instance()->submit_ad(
			(int) $advertiser->id,
			array(
				'title'   => 'Receipt ad',
				'ad_type' => 'code',
				'ad_code' => '<script>console.log(1)</script>',
			),
			$package_id
		);
		$this->assertNotWPError( $submission );
		$this->assertSame( 'pending', $submission->status );

		$receipts = array_values( array_filter( $sent, static fn( $m ) => get_user_by( 'id', $user )->user_email === $m['to'] ) );
		$this->assertCount( 1, $receipts, 'The advertiser gets one receipt.' );
		$this->assertStringContainsString( 'Receipt ad', $receipts[0]['message'] );
		$this->assertStringContainsString( 'Pending Review', $receipts[0]['message'] );
	}

	public function test_templates_nothing_sends_are_removed(): void {
		$this->assertFileDoesNotExist( WBAM_PRO_PATH . 'templates/emails/advertiser-low-balance.php' );
		$this->assertFileDoesNotExist( WBAM_PRO_PATH . 'templates/emails/receipt.php' );
	}
}
