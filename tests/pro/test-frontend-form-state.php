<?php
/**
 * Frontend form-state regressions (card 10340188014).
 *
 * - The report endpoint rejects a reason that is not in
 *   Report::get_reason_labels() instead of filing it as "other".
 * - The review endpoint says whether the review went live, so the page
 *   stops claiming "No published reviews yet." when moderation is off.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Shortcodes\Ajax_Handler;
use WBAM_Pro\Modules\Reviews\Review_Manager;

/**
 * @group pro
 */
class Test_Frontend_Form_State extends Pro_Test_Case {

	public function set_up(): void {
		parent::set_up();
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wbam_pro_rate_limit_max', '__return_zero' );
		add_filter(
			'wp_die_ajax_handler',
			static function () {
				return static function () {
					throw new \RuntimeException( 'wp_die' );
				};
			}
		);
	}

	public function tear_down(): void {
		remove_all_filters( 'wp_doing_ajax' );
		remove_all_filters( 'wbam_pro_rate_limit_max' );
		remove_all_filters( 'wp_die_ajax_handler' );
		$_POST = array();
		parent::tear_down();
	}

	/**
	 * Run an AJAX callback and decode its JSON response.
	 *
	 * @param callable $callback Handler.
	 * @return array
	 */
	private function call( callable $callback ): array {
		ob_start();
		try {
			$callback();
		} catch ( \RuntimeException $e ) {
			unset( $e );
		}
		return (array) json_decode( (string) ob_get_clean(), true );
	}

	private function report( string $reason ): array {
		$_POST = array(
			'nonce'         => wp_create_nonce( 'wbam_classified' ),
			'classified_id' => 999999,
			'reason'        => $reason,
			'name'          => 'Guest',
			'email'         => 'guest@example.org',
		);
		return $this->call( array( new Ajax_Handler(), 'report_classified' ) );
	}

	public function test_report_rejects_unlisted_reason(): void {
		$response = $this->report( 'not-a-reason' );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Please select a reason for your report.', $response['data']['message'] );
	}

	public function test_report_accepts_listed_reason(): void {
		$response = $this->report( 'spam' );

		// Passes the reason check and stops at the (missing) listing instead.
		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'not found', $response['data']['message'] );
	}

	/**
	 * @dataProvider moderation_provider
	 */
	public function test_review_response_says_whether_it_was_published( bool $moderation ): void {
		Settings_Helper::update( 'review_moderation', $moderation );

		$seller     = (int) self::factory()->user->create();
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $seller );
		wp_set_current_user( (int) self::factory()->user->create() );

		$_POST = array(
			'nonce'         => wp_create_nonce( 'wbam_review' ),
			'advertiser_id' => $advertiser->id,
			'rating'        => 5,
		);
		$_REQUEST = $_POST;

		$response = $this->call( array( Review_Manager::get_instance(), 'ajax_submit_review' ) );
		$_REQUEST = array();

		$this->assertTrue( $response['success'], wp_json_encode( $response ) );
		$this->assertSame( ! $moderation, $response['data']['published'] );
	}

	public function moderation_provider(): array {
		return array(
			'moderation on'  => array( true ),
			'moderation off' => array( false ),
		);
	}
}
