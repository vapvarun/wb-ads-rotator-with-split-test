<?php
/**
 * Regression guards for Basecamp cards 10342711384 (campaign Approve on a
 * pending ad) and 10340186779 (taken-down or unpaid ads coming back, and
 * ability edits skipping re-moderation).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Pro_Abilities;
use WBAM_Pro\Core\Pro_Admin;
use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\AdSubmissions\Ad_Submission_Manager;
use WBAM_Pro\Modules\AdSubmissions\Ad_Submission_Shortcodes;
use WBAM_Pro\Modules\Campaigns\Campaign_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;
use WBAM_Pro\Modules\Packages\Package_Manager;

class Test_Moderation_State_Changes extends Pro_Test_Case {

	private int $user;
	private object $advertiser;

	public function set_up(): void {
		parent::set_up();

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['campaigns']   = true;
		$enabled['classifieds'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );

		$this->user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $this->user );
		Advertiser_Manager::get_instance()->update_status( (int) $this->advertiser->id, 'active' );
		$this->advertiser = Advertiser_Manager::get_instance()->get( (int) $this->advertiser->id );
	}

	public function tear_down(): void {
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();
		remove_all_filters( 'wp_redirect' );
		remove_all_filters( 'wp_doing_ajax' );
		remove_all_filters( 'wp_die_ajax_handler' );
		parent::tear_down();
	}

	private function balance(): int {
		return (int) \Wbcom\Credits\Credits::get_balance( 'wbam-pro', $this->user );
	}

	/**
	 * A pending Rich Content ad on a $10 per-click package, the QA setup.
	 */
	private function submit_cpc_ad(): object {
		$package = Package_Manager::get_instance()->create(
			array(
				'name'              => 'CPC package',
				'pricing_model'     => 'cpc',
				'price_per_unit'    => 0.5,
				'clicks_limit'      => 20,
				'requires_approval' => 1,
				'status'            => 'active',
			)
		);
		$this->assertNotWPError( $package );

		$submission = Ad_Submission_Manager::get_instance()->submit_ad(
			$this->advertiser->id,
			array(
				'title'     => 'Rich content ad',
				'ad_type'   => 'rich-content',
				'content'   => '<p>Hello</p>',
				'click_url' => 'https://example.com',
			),
			(int) $package->id
		);
		$this->assertNotWPError( $submission );
		$this->assertSame( 'pending', $submission->status );
		$this->assertNotEmpty( $submission->campaign_id );

		return $submission;
	}

	private function live_cpc_ad(): object {
		\Wbcom\Credits\Credits::topup( 'wbam-pro', $this->user, 100000, 'seed' );
		$submission = $this->submit_cpc_ad();
		wp_set_current_user( 1 );
		$this->assertTrue( Ad_Submission_Manager::get_instance()->approve( (int) $submission->id ) );
		return Ad_Submission_Manager::get_instance()->get( (int) $submission->id );
	}

	/**
	 * The advertiser's portal Resume, decoded.
	 */
	private function resume( int $ad_id ): array {
		wp_set_current_user( $this->user );
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () {
				return static function () {
					throw new \RuntimeException( 'wp_die' );
				};
			}
		);
		$_POST = array(
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'ad_id'       => $ad_id,
			'action_type' => 'resume',
		);
		$_REQUEST = $_POST;
		$handler = ( new \ReflectionClass( Ad_Submission_Shortcodes::class ) )->newInstanceWithoutConstructor();
		ob_start();
		try {
			$handler->handle_toggle_ad_status();
		} catch ( \RuntimeException $e ) {
			unset( $e );
		}
		return (array) json_decode( (string) ob_get_clean(), true );
	}

	/**
	 * Run Pro_Admin::handle_campaign_actions() and return the redirect.
	 */
	private function campaign_action( array $get, array $post = array() ): string {
		wp_set_current_user( 1 );
		$_GET     = $get;
		$_POST    = $post;
		$_REQUEST = $get + $post;
		$redirect = '';
		add_filter(
			'wp_redirect',
			static function ( $location ) use ( &$redirect ) {
				$redirect = $location;
				throw new \RuntimeException( 'redirected' );
			}
		);
		$admin = ( new \ReflectionClass( Pro_Admin::class ) )->newInstanceWithoutConstructor();
		try {
			( new \ReflectionMethod( $admin, 'handle_campaign_actions' ) )->invoke( $admin );
		} catch ( \RuntimeException $e ) {
			unset( $e );
		}
		return $redirect;
	}

	// ---------------------------------------------------------------------
	// Card 10342711384: campaign Approve.
	// ---------------------------------------------------------------------

	public function test_campaign_approve_publishes_its_pending_ad(): void {
		\Wbcom\Credits\Credits::topup( 'wbam-pro', $this->user, 100000, 'seed' );
		$submission = $this->submit_cpc_ad();
		wp_set_current_user( 1 );

		$redirect = $this->campaign_action(
			array(
				'action'      => 'approve',
				'campaign_id' => (int) $submission->campaign_id,
				'_wpnonce'    => wp_create_nonce( 'wbam_campaign_approve_' . $submission->campaign_id ),
			)
		);

		$this->assertStringContainsString( 'message=approved', $redirect );
		$this->assertSame( 'active', Campaign_Manager::get_instance()->get( (int) $submission->campaign_id )->status );
		$this->assertSame( 'approved', Ad_Submission_Manager::get_instance()->get( (int) $submission->id )->status, 'The submission is approved in the same step.' );
		$this->assertSame( 'publish', get_post_status( (int) $submission->ad_id ), 'The ad is published in the same step.' );
		$this->assertSame( 100000 - 1000, $this->balance(), 'The budget is reserved once.' );
	}

	public function test_bulk_campaign_approve_publishes_its_pending_ad(): void {
		\Wbcom\Credits\Credits::topup( 'wbam-pro', $this->user, 100000, 'seed' );
		$submission = $this->submit_cpc_ad();
		wp_set_current_user( 1 );

		$this->campaign_action(
			array(),
			array(
				'action'          => 'approve',
				'campaign_ids'    => array( (int) $submission->campaign_id ),
				'wbam_bulk_nonce' => wp_create_nonce( 'wbam_bulk_campaigns' ),
			)
		);

		$this->assertSame( 'approved', Ad_Submission_Manager::get_instance()->get( (int) $submission->id )->status );
		$this->assertSame( 'publish', get_post_status( (int) $submission->ad_id ) );
	}

	public function test_unaffordable_campaign_approve_is_refused_with_the_reason(): void {
		\Wbcom\Credits\Credits::topup( 'wbam-pro', $this->user, 100000, 'seed' );
		$submission = $this->submit_cpc_ad();
		// Spent elsewhere while the ad waited for review.
		\Wbcom\Credits\Credits::adjust( 'wbam-pro', $this->user, -99500, 'spent' );
		wp_set_current_user( 1 );

		$redirect = $this->campaign_action(
			array(
				'action'      => 'approve',
				'campaign_id' => (int) $submission->campaign_id,
				'_wpnonce'    => wp_create_nonce( 'wbam_campaign_approve_' . $submission->campaign_id ),
			)
		);

		$this->assertStringContainsString( 'error=action_failed', $redirect, 'A refused Approve says so.' );
		$this->assertStringContainsString( 'reserves', (string) get_transient( 'wbam_pro_error_detail_1' ), 'The notice carries the real reason.' );
		$this->assertSame( 'pending', Campaign_Manager::get_instance()->get( (int) $submission->campaign_id )->status );
		$this->assertSame( 'pending', Ad_Submission_Manager::get_instance()->get( (int) $submission->id )->status );
		$this->assertSame( 500, $this->balance(), 'Nothing is charged.' );
	}

	// ---------------------------------------------------------------------
	// Card 10340186779: takedown and deleted campaigns stay down.
	// ---------------------------------------------------------------------

	public function test_a_taken_down_ad_cannot_be_resumed_even_after_a_suspension(): void {
		$submission = $this->live_cpc_ad();
		$this->assertTrue( Ad_Submission_Manager::get_instance()->reject( (int) $submission->id, 'Takedown' ) );

		// Suspending pauses every published ad; reinstating must not turn
		// the takedown into an ordinary pause.
		Advertiser_Manager::get_instance()->update_status( (int) $this->advertiser->id, 'suspended' );
		Advertiser_Manager::get_instance()->update_status( (int) $this->advertiser->id, 'active' );

		$this->assertSame( 'rejected', get_post_meta( (int) $submission->ad_id, '_wbam_status', true ), 'The portal keeps showing the takedown.' );

		$response = $this->resume( (int) $submission->ad_id );
		$this->assertFalse( $response['success'] );
		$this->assertSame( '0', get_post_meta( (int) $submission->ad_id, '_wbam_enabled', true ) );
	}

	public function test_deleting_a_campaign_unlinks_and_pauses_its_ad_for_good(): void {
		$submission = $this->live_cpc_ad();
		$ad_id      = (int) $submission->ad_id;

		wp_set_current_user( $this->user );
		$this->assertTrue( Campaign_Manager::get_instance()->delete( (int) $submission->campaign_id ) );

		$this->assertSame( '', get_post_meta( $ad_id, '_wbam_campaign_id', true ), 'The ad no longer points at the deleted campaign.' );
		$this->assertSame( '0', get_post_meta( $ad_id, '_wbam_enabled', true ), 'The ad stops serving.' );
		$this->assertSame( 'paused', get_post_meta( $ad_id, '_wbam_status', true ) );

		$response = $this->resume( $ad_id );
		$this->assertFalse( $response['success'], 'Nobody pays for it any more, so it cannot be resumed.' );
		$this->assertSame( '0', get_post_meta( $ad_id, '_wbam_enabled', true ) );
	}

	public function test_a_paused_live_ad_can_still_be_resumed(): void {
		$submission = $this->live_cpc_ad();
		update_post_meta( (int) $submission->ad_id, '_wbam_status', 'paused' );
		update_post_meta( (int) $submission->ad_id, '_wbam_enabled', '0' );

		$response = $this->resume( (int) $submission->ad_id );
		$this->assertTrue( $response['success'], wp_json_encode( $response ) );
		$this->assertSame( '1', get_post_meta( (int) $submission->ad_id, '_wbam_enabled', true ) );
	}

	// ---------------------------------------------------------------------
	// Card 10340186779: ability edits re-moderate.
	// ---------------------------------------------------------------------

	public function test_update_classified_ability_sends_a_live_listing_back_to_review(): void {
		$settings                     = get_option( 'wbam_pro_classifieds_settings', array() );
		$settings['require_approval'] = true;
		update_option( 'wbam_pro_classifieds_settings', $settings );

		$term       = wp_insert_term( 'State ' . wp_generate_password( 6, false ), Classified_Manager::TAXONOMY_CATEGORY );
		$classified = Classified_Manager::get_instance()->submit(
			$this->advertiser,
			array(
				'title'      => 'Live listing',
				'categories' => array( (int) $term['term_id'] ),
			)
		);
		$this->assertNotWPError( $classified );
		$this->assertTrue( Classified_Manager::get_instance()->approve( (int) $classified->id ) );

		wp_set_current_user( $this->user );
		$result = ( new Pro_Abilities() )->execute_update_classified(
			array(
				'id'    => (int) $classified->id,
				'title' => 'Swapped title',
			)
		);
		$this->assertNotWPError( $result );

		$this->assertSame( 'pending', Classified_Manager::get_instance()->get( (int) $classified->id )->status );
	}
}
