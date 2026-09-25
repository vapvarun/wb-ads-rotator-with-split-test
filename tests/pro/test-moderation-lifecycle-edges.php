<?php
/**
 * Regression guards for Basecamp card 10340186779 "[Pro] Moderation
 * lifecycle edges: orphans, suspend, re-moderation, REST paths" - the
 * status paths around the 3.2.0 moderation loop that skipped a notice, a
 * guard or a side effect.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\AdSubmissions\Ad_Submission_Manager;
use WBAM_Pro\Modules\Campaigns\Campaign_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;

class Test_Moderation_Lifecycle_Edges extends Pro_Test_Case {

	private int $user;
	private object $advertiser;

	public function set_up(): void {
		parent::set_up();

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		$enabled['campaigns']   = true;
		$enabled['messaging']   = true;
		Settings_Helper::update( 'enabled_modules', $enabled );

		$this->user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $this->user );
		Advertiser_Manager::get_instance()->update_status( (int) $this->advertiser->id, 'active' );
		$this->advertiser = Advertiser_Manager::get_instance()->get( (int) $this->advertiser->id );
		\Wbcom\Credits\Credits::topup( 'wbam-pro', $this->user, 100000, 'seed' );
	}

	private function balance(): int {
		return (int) \Wbcom\Credits\Credits::get_balance( 'wbam-pro', $this->user );
	}

	/**
	 * A pending submission with a $49 flat package that requires review.
	 */
	private function submit_flat_package_ad( string $title ): object {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'wbam_packages',
			array(
				'name'              => $title . ' package',
				'price'             => 49.00,
				'pricing_model'     => 'flat',
				'requires_approval' => 1,
				'status'            => 'active',
				'created_at'        => current_time( 'mysql' ),
			)
		);

		$submission = Ad_Submission_Manager::get_instance()->submit_ad(
			$this->advertiser->id,
			array(
				'title'     => $title,
				'ad_type'   => 'image',
				'image_url' => 'https://example.com/x.png',
				'link_url'  => 'https://example.com',
			),
			(int) $wpdb->insert_id
		);
		$this->assertNotWPError( $submission );

		return $submission;
	}

	// ---------------------------------------------------------------------
	// Ad submissions.
	// ---------------------------------------------------------------------

	/**
	 * Steps "Replaying an ad reject re-sends the email" and "Replayed ad
	 * reject also overwrites admin_notes and writes a second audit row".
	 */
	public function test_replayed_reject_is_refused_and_keeps_the_first_reason(): void {
		$manager    = Ad_Submission_Manager::get_instance();
		$submission = $this->submit_flat_package_ad( 'Replay reject ad' );

		$fired = 0;
		add_action(
			'wbam_pro_ad_submission_rejected',
			function () use ( &$fired ) {
				++$fired;
			}
		);

		$this->assertTrue( $manager->reject( (int) $submission->id, 'first reason' ) );
		$this->assertWPError( $manager->reject( (int) $submission->id, 'replayed reason' ), 'A second reject on a rejected submission must be refused.' );

		$this->assertSame( 1, $fired, 'The "not approved" email hook must fire once.' );
		$this->assertSame( 'first reason', $manager->get( (int) $submission->id )->admin_notes );
	}

	/**
	 * Reject on an approved submission is the takedown path and must keep
	 * working behind the new guard.
	 */
	public function test_reject_still_takes_down_an_approved_submission(): void {
		$manager    = Ad_Submission_Manager::get_instance();
		$submission = $this->submit_flat_package_ad( 'Takedown ad' );
		$this->assertTrue( $manager->approve( (int) $submission->id ) );

		$this->assertTrue( $manager->reject( (int) $submission->id, 'takedown' ) );
		$this->assertSame( 'rejected', $manager->get( (int) $submission->id )->status );
	}

	/**
	 * Step "Advertiser deletes a pending ad: the submission stays pending
	 * and its Starter Campaign shows Pending $49.00".
	 */
	public function test_deleting_a_pending_ad_cancels_its_submission_and_campaign(): void {
		$submission = $this->submit_flat_package_ad( 'Deleted pending ad' );
		$this->assertNotEmpty( $submission->campaign_id );

		wp_delete_post( (int) $submission->ad_id, true );

		$this->assertSame( 'cancelled', Ad_Submission_Manager::get_instance()->get( (int) $submission->id )->status );
		$this->assertSame( 'cancelled', Campaign_Manager::get_instance()->get( (int) $submission->campaign_id )->status );
	}

	/**
	 * Same step, money side: a live CPM campaign's reservation comes back
	 * when its ad is deleted.
	 */
	public function test_deleting_a_live_cpm_ad_returns_the_reserved_budget(): void {
		global $wpdb;
		$submission = $this->submit_flat_package_ad( 'Deleted cpm ad' );

		$campaign = Campaign_Manager::get_instance()->create(
			array(
				'advertiser_id'  => $this->advertiser->id,
				'ad_id'          => (int) $submission->ad_id,
				'name'           => 'CPM reservation',
				'pricing_model'  => 'cpm',
				'price_per_unit' => 1.0,
				'budget'         => 25.0,
				'status'         => 'draft',
			)
		);
		$this->assertNotWPError( $campaign );
		$wpdb->update( $wpdb->prefix . 'wbam_ad_submissions', array( 'campaign_id' => (int) $campaign->id ), array( 'id' => (int) $submission->id ) );

		$before = $this->balance();
		$this->assertTrue( Campaign_Manager::get_instance()->activate( (int) $campaign->id ) );
		$this->assertSame( $before - 2500, $this->balance(), 'Activation reserves the budget.' );

		wp_delete_post( (int) $submission->ad_id, true );

		$this->assertSame( $before, $this->balance(), 'Deleting the ad must release the reservation.' );
	}

	/**
	 * Step "An advertiser editing a live approved ad skips re-moderation".
	 */
	public function test_editing_a_live_ad_sends_it_back_to_review_without_a_second_charge(): void {
		$manager    = Ad_Submission_Manager::get_instance();
		$submission = $this->submit_flat_package_ad( 'Live edit ad' );
		$before     = $this->balance();

		$this->assertTrue( $manager->approve( (int) $submission->id ) );
		$this->assertSame( $before - 4900, $this->balance() );

		$this->assertTrue(
			$manager->send_back_to_review(
				(int) $submission->id,
				array(
					'title'   => 'Swapped creative',
					'ad_type' => 'image',
				)
			)
		);

		$this->assertSame( 'pending', $manager->get( (int) $submission->id )->status );
		$this->assertSame( 'pending', get_post_status( (int) $submission->ad_id ), 'The edited ad must leave the air until reviewed.' );
		$this->assertSame( '0', get_post_meta( (int) $submission->ad_id, '_wbam_enabled', true ) );

		$this->assertTrue( $manager->approve( (int) $submission->id ) );
		$this->assertSame( $before - 4900, $this->balance(), 'Re-approving an edited ad must not charge the package again.' );
		$this->assertSame( 'publish', get_post_status( (int) $submission->ad_id ) );
	}

	/**
	 * Package set up without review: an edit stays live, same rule as a new
	 * submission.
	 */
	public function test_editing_an_auto_approved_package_ad_stays_live(): void {
		global $wpdb;
		$manager    = Ad_Submission_Manager::get_instance();
		$submission = $this->submit_flat_package_ad( 'Auto package edit ad' );
		$wpdb->update( $wpdb->prefix . 'wbam_packages', array( 'requires_approval' => 0 ), array( 'id' => (int) $submission->package_id ) );
		$this->assertTrue( $manager->approve( (int) $submission->id ) );

		$this->assertFalse(
			$manager->send_back_to_review(
				(int) $submission->id,
				array(
					'title'   => 'Edited',
					'ad_type' => 'image',
				)
			)
		);
		$this->assertSame( 'approved', $manager->get( (int) $submission->id )->status );
	}

	/**
	 * Step "Editor Publish on a pending portal ad with a short wallet shows
	 * 'Post submitted.' while the ad silently stays pending".
	 */
	public function test_failed_editor_publish_replaces_post_submitted_with_the_reason(): void {
		$submission = $this->submit_flat_package_ad( 'Short wallet publish ad' );
		$admin      = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		// Drain the wallet so approve() cannot charge the package.
		\Wbcom\Credits\Credits::adjust( 'wbam-pro', $this->user, -1 * $this->balance(), 'drain' );
		self::flush_credits_balance_cache();

		wp_update_post(
			array(
				'ID'          => (int) $submission->ad_id,
				'post_status' => 'publish',
			)
		);
		$this->assertSame( 'pending', get_post_status( (int) $submission->ad_id ) );

		$location = apply_filters( 'redirect_post_location', admin_url( 'post.php?post=' . $submission->ad_id . '&action=edit&message=8' ), (int) $submission->ad_id );
		$this->assertStringNotContainsString( 'message=8', $location, '"Post submitted." must not be shown for a Publish that failed.' );
		$this->assertStringContainsString( 'wbam_publish_failed=1', $location );

		$_GET['wbam_publish_failed'] = '1';
		ob_start();
		Ad_Submission_Manager::get_instance()->render_publish_failed_notice();
		$notice = (string) ob_get_clean();
		unset( $_GET['wbam_publish_failed'] );

		$this->assertStringContainsString( 'Not published', $notice );
		$this->assertStringContainsString( 'charge credits', $notice, 'The notice must carry the approval failure reason.' );
	}

	/**
	 * Step "Dead code: render_edit_ad_form()/process_ad_update() have zero
	 * callers; hook wbam_ad_submission_changes_requested has no listener".
	 */
	public function test_dead_edit_form_and_duplicate_hook_are_gone(): void {
		$this->assertFalse( method_exists( \WBAM_Pro\Modules\Advertisers\Advertiser_Shortcodes::class, 'process_ad_update' ) );
		$this->assertFalse( method_exists( \WBAM_Pro\Modules\Advertisers\Advertiser_Shortcodes::class, 'render_edit_ad_form' ) );

		$source = (string) file_get_contents( WBAM_PRO_PATH . 'includes/Modules/AdSubmissions/class-ad-submission-manager.php' );
		$this->assertStringNotContainsString( "do_action( 'wbam_ad_submission_changes_requested'", $source );
	}

	// ---------------------------------------------------------------------
	// Advertisers.
	// ---------------------------------------------------------------------

	/**
	 * Step "Suspending or banning an advertiser does not pause their
	 * campaigns or disable their ads".
	 */
	public function test_suspending_an_advertiser_pauses_campaigns_and_switches_ads_off(): void {
		$ad_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'wbam-ad',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $ad_id, '_wbam_advertiser_id', (int) $this->advertiser->id );
		update_post_meta( $ad_id, '_wbam_enabled', '1' );

		$campaign = Campaign_Manager::get_instance()->create(
			array(
				'advertiser_id' => $this->advertiser->id,
				'ad_id'         => $ad_id,
				'name'          => 'Suspend me',
				'pricing_model' => 'flat',
				'status'        => 'draft',
			)
		);
		$this->assertNotWPError( $campaign );
		$this->assertTrue( Campaign_Manager::get_instance()->activate( (int) $campaign->id ) );

		$this->assertTrue( Advertiser_Manager::get_instance()->update_status( (int) $this->advertiser->id, 'suspended' ) );

		$this->assertSame( 'paused', Campaign_Manager::get_instance()->get( (int) $campaign->id )->status );
		$this->assertSame( '0', get_post_meta( $ad_id, '_wbam_enabled', true ), 'A suspended advertiser\'s ads must stop serving.' );
		$this->assertSame( 'paused', get_post_meta( $ad_id, '_wbam_status', true ) );
	}

	/**
	 * Same step: the portal Resume button must not undo the suspension.
	 */
	public function test_suspended_advertiser_cannot_resume_ads_from_the_portal(): void {
		$source = (string) file_get_contents( WBAM_PRO_PATH . 'includes/Modules/AdSubmissions/class-ad-submission-shortcodes.php' );
		$start  = strpos( $source, 'public function handle_toggle_ad_status()' );
		$body   = substr( $source, $start, strpos( $source, 'public function handle_delete_ad()' ) - $start );
		$this->assertStringContainsString( '! $advertiser->can_sell()', $body );
	}

	/**
	 * Step "Setting an advertiser back to Pending keeps the wbam_advertiser
	 * role added by Approve".
	 */
	public function test_back_to_pending_removes_the_advertiser_role(): void {
		$this->assertContains( 'wbam_advertiser', get_userdata( $this->user )->roles );

		Advertiser_Manager::get_instance()->update_status( (int) $this->advertiser->id, 'pending' );

		clean_user_cache( $this->user );
		$this->assertNotContains( 'wbam_advertiser', get_userdata( $this->user )->roles );
	}

	/**
	 * Step "REST GET advertiser/profile and POST /campaigns call
	 * get_or_create(): any logged-in subscriber becomes an advertiser".
	 */
	public function test_rest_profile_and_campaign_create_do_not_enrol_a_subscriber(): void {
		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = false;
		Settings_Helper::update( 'enabled_modules', $enabled );

		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		do_action( 'rest_api_init' );

		global $wpdb;
		$visitor = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		// User IDs are reused after rollback; drop a row an earlier test's
		// DDL-committed transaction may have left for this ID.
		$wpdb->delete( $wpdb->prefix . 'wbam_advertisers', array( 'user_id' => $visitor ) );
		wp_set_current_user( $visitor );

		$profile = rest_do_request( new \WP_REST_Request( 'GET', '/wbam-pro/v1/advertiser/profile' ) );
		$this->assertSame( 404, $profile->get_status() );

		$request = new \WP_REST_Request( 'POST', '/wbam-pro/v1/campaigns' );
		$request->set_param( 'name', 'Sneaky' );
		$created = rest_do_request( $request );
		$this->assertSame( 403, $created->get_status() );

		$this->assertNull( Advertiser_Manager::get_instance()->get_by_user( $visitor ), 'Neither call may create an advertiser record.' );
	}

	/**
	 * Step "user_register auto-create runs before registration's
	 * create(status=...), so the chosen status and company/website/phone are
	 * ignored when auto_create_advertisers is on".
	 */
	public function test_registration_keeps_its_own_profile_fields_with_auto_create_on(): void {
		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = false;
		Settings_Helper::update( 'enabled_modules', $enabled );
		Settings_Helper::update( 'auto_create_advertisers', true );
		Settings_Helper::update( 'auto_approve_advertisers', false );

		$_POST = array(
			'reg_username' => 'edge_registrant',
			'reg_email'    => 'edge_registrant@example.com',
			'reg_company'  => 'Edge Co',
			'reg_website'  => 'https://edge.example.com',
			'reg_phone'    => '555-0100',
		);
		// Reused user ID: clear a leftover row before any profile is made.
		add_action(
			'user_register',
			static function ( $user_id ) {
				global $wpdb;
				$wpdb->delete( $wpdb->prefix . 'wbam_advertisers', array( 'user_id' => $user_id ) );
			},
			1
		);
		$shortcodes = ( new \ReflectionClass( \WBAM_Pro\Modules\Advertisers\Advertiser_Shortcodes::class ) )->newInstanceWithoutConstructor();
		$method     = new \ReflectionMethod( $shortcodes, 'process_advertiser_registration' );
		$user_id    = $method->invoke( $shortcodes );
		$_POST      = array();

		$this->assertIsInt( $user_id );
		$profile = Advertiser_Manager::get_instance()->get_by_user( $user_id );
		$this->assertSame( 'Edge Co', $profile->company_name );
		$this->assertSame( '555-0100', $profile->phone );
		$this->assertSame( 'pending', $profile->status );
	}

	/**
	 * Step "Decline asks no reason": the reason reaches the application
	 * status email hook, and Approve on an advertiser says so.
	 */
	public function test_decline_reason_reaches_the_applicant_and_approve_names_the_advertiser(): void {
		$applicant = Advertiser_Manager::get_instance()->get_or_create( (int) self::factory()->user->create() );
		Advertiser_Manager::get_instance()->update_status( (int) $applicant->id, 'pending' );

		$reason = null;
		add_action(
			'wbam_advertiser_rejected',
			function ( $advertiser, $why ) use ( &$reason ) {
				$reason = $why;
			},
			10,
			2
		);
		Advertiser_Manager::get_instance()->update_status( (int) $applicant->id, 'member', 'Website does not match the company.' );
		$this->assertSame( 'Website does not match the company.', $reason );

		$source = (string) file_get_contents( WBAM_PRO_PATH . 'includes/Core/class-pro-admin.php' );
		$this->assertStringContainsString( "'decline_form' === \$action", $source );
		$this->assertStringContainsString( "\$redirect_args['message'] = 'advertiser_approved';", $source );
	}

	// ---------------------------------------------------------------------
	// Classifieds.
	// ---------------------------------------------------------------------

	private function set_require_approval( bool $on ): void {
		$settings                     = get_option( 'wbam_pro_classifieds_settings', array() );
		$settings['require_approval'] = $on;
		update_option( 'wbam_pro_classifieds_settings', $settings );
	}

	private function listing( string $title, bool $approve = true ): object {
		$this->set_require_approval( true );
		$term       = wp_insert_term( 'Edges ' . wp_generate_password( 6, false ), Classified_Manager::TAXONOMY_CATEGORY );
		$classified = Classified_Manager::get_instance()->submit(
			$this->advertiser,
			array(
				'title'      => $title,
				'categories' => array( (int) $term['term_id'] ),
			)
		);
		$this->assertNotWPError( $classified );
		if ( $approve ) {
			$this->assertTrue( Classified_Manager::get_instance()->approve( (int) $classified->id ) );
		}
		return Classified_Manager::get_instance()->get( (int) $classified->id );
	}

	private function mail_subjects(): \ArrayObject {
		$subjects = new \ArrayObject();
		add_filter(
			'pre_wp_mail',
			function ( $short, $atts ) use ( $subjects ) {
				$subjects[] = $atts['subject'];
				return true;
			},
			10,
			2
		);
		return $subjects;
	}

	/**
	 * Steps "An active classified has no single Reject row action; bulk
	 * Reject on it reports 0 Classifieds rejected" and "meta-box status
	 * change active->rejected or rejected->active sends no email".
	 */
	public function test_a_live_listing_can_be_rejected_and_reinstated_with_notices(): void {
		$manager    = Classified_Manager::get_instance();
		$classified = $this->listing( 'Takedown listing' );
		$this->assertSame( 'active', $classified->status );

		$rejected = 0;
		$approved = 0;
		add_action(
			'wbam_classified_rejected',
			function () use ( &$rejected ) {
				++$rejected;
			}
		);
		add_action(
			'wbam_classified_approved',
			function () use ( &$approved ) {
				++$approved;
			}
		);

		$this->assertTrue( $manager->moderate( (int) $classified->id, 'rejected', 'Counterfeit item' ) );
		$this->assertSame( 'rejected', $manager->get( (int) $classified->id )->status );
		$this->assertSame( 'draft', get_post_status( (int) $classified->post_id ), 'A rejected live listing must leave the site.' );
		$this->assertSame( 1, $rejected, 'The seller gets the rejection email.' );
		$this->assertWPError( $manager->reject( (int) $classified->id, 'again' ), 'A replayed reject stays refused.' );

		$this->assertTrue( $manager->moderate( (int) $classified->id, 'active' ) );
		$this->assertSame( 'active', $manager->get( (int) $classified->id )->status );
		$this->assertSame( 1, $approved, 'Reinstating tells the seller it is live.' );

		$this->assertNull( $manager->moderate( (int) $classified->id, 'sold' ), 'Non-moderation changes are left to the caller.' );

		$meta_box = (string) file_get_contents( WBAM_PRO_PATH . 'includes/Admin/class-classified-meta-box.php' );
		$this->assertStringContainsString( '$manager->moderate(', $meta_box );
		$list = (string) file_get_contents( WBAM_PRO_PATH . 'includes/Admin/class-classifieds-list-table.php' );
		$this->assertSame( 2, substr_count( $list, "\$actions['reject_classified']" ), 'Pending and active rows both offer Reject.' );
	}

	/**
	 * Step "Wording": a bulk action that processed nothing is an error with
	 * the reason, and the label follows the count.
	 */
	public function test_bulk_result_is_an_error_when_nothing_was_processed(): void {
		$method = new \ReflectionMethod( \WBAM_Pro\Core\Pro_Admin::class, 'bulk_result_args' );
		$this->assertSame( array( 'error' => 'bulk_failed' ), $method->invoke( null, 'bulk_reject_classified', 0, 'Only pending or active classifieds can be rejected.' ) );
		$this->assertSame(
			array(
				'message' => 'bulk_reject_classified',
				'count'   => 1,
			),
			$method->invoke( null, 'bulk_reject_classified', 1, '' )
		);

		$source = (string) file_get_contents( WBAM_PRO_PATH . 'includes/Core/class-pro-admin.php' );
		$this->assertStringContainsString( "get_classifieds_label( 1 === \$count ? 'singular' : 'plural' )", $source );
		$this->assertStringContainsString( 'confirmBulkDelete', $source );
	}

	/**
	 * Step "REST seller update never moves a rejected listing to pending;
	 * REST mark-sold skips the active-only check and wbam_classified_sold".
	 */
	public function test_rest_seller_update_resubmits_and_mark_sold_uses_the_manager(): void {
		$manager = Classified_Manager::get_instance();
		$api     = new \WBAM_Pro\Modules\Classifieds\Classified_API();
		wp_set_current_user( $this->user );

		$rejected = $this->listing( 'REST rejected listing', false );
		$manager->reject( (int) $rejected->id, 'Fix the photos' );

		$resubmitted = 0;
		add_action(
			'wbam_classified_resubmitted',
			function () use ( &$resubmitted ) {
				++$resubmitted;
			}
		);

		$request = new \WP_REST_Request( 'PUT' );
		$request->set_param( 'id', (int) $rejected->id );
		$request->set_param( 'title', 'Fixed photos' );
		$this->assertFalse( is_wp_error( $api->update_classified( $request ) ) );
		$this->assertSame( 'pending', $manager->get( (int) $rejected->id )->status );
		$this->assertSame( 1, $resubmitted );

		$pending = new \WP_REST_Request( 'POST' );
		$pending->set_param( 'id', (int) $rejected->id );
		$this->assertWPError( $api->mark_as_sold( $pending ), 'Only an active listing can be sold.' );

		$sold_fired = 0;
		add_action(
			'wbam_classified_sold',
			function () use ( &$sold_fired ) {
				++$sold_fired;
			}
		);
		$live = $this->listing( 'REST sold listing' );
		$sold = new \WP_REST_Request( 'POST' );
		$sold->set_param( 'id', (int) $live->id );
		$this->assertFalse( is_wp_error( $api->mark_as_sold( $sold ) ) );
		$this->assertSame( 'sold', $manager->get( (int) $live->id )->status );
		$this->assertSame( 1, $sold_fired );
	}

	/**
	 * Step "An advertiser editing a live approved listing skips
	 * re-moderation".
	 */
	public function test_seller_edit_of_a_live_listing_goes_back_to_review_when_review_is_on(): void {
		$manager    = Classified_Manager::get_instance();
		$classified = $this->listing( 'Live listing edit' );
		$subjects   = $this->mail_subjects();

		$updated = $manager->update_by_seller( (int) $classified->id, array( 'title' => 'Swapped title' ) );

		$this->assertSame( 'pending', $updated->status );
		$this->assertSame( 'pending', get_post_status( (int) $classified->post_id ) );
		$this->assertNotEmpty( preg_grep( '/requires review/', (array) $subjects ), 'The admin is told it needs review.' );
		$this->assertEquals( 1, $updated->get_meta( 'followers_notified' ), 'Followers were told at first approval; re-approval must not tell them again.' );

		$this->set_require_approval( false );
		$other = Classified_Manager::get_instance()->submit(
			$this->advertiser,
			array(
				'title'      => 'No review site listing',
				'categories' => array( (int) wp_insert_term( 'Edges nr ' . wp_generate_password( 6, false ), Classified_Manager::TAXONOMY_CATEGORY )['term_id'] ),
			)
		);
		$this->assertSame( 'active', $manager->update_by_seller( (int) $other->id, array( 'title' => 'Edited' ) )->status, 'Without review an edit stays live.' );
	}

	/**
	 * Step "Admin receives 'New classified listing requires review' for
	 * listings that auto-publish".
	 */
	public function test_auto_published_listing_sends_no_requires_review_email(): void {
		$this->set_require_approval( false );
		$subjects = $this->mail_subjects();
		$term     = wp_insert_term( 'Edges auto ' . wp_generate_password( 6, false ), Classified_Manager::TAXONOMY_CATEGORY );

		$classified = Classified_Manager::get_instance()->submit(
			$this->advertiser,
			array(
				'title'      => 'Auto published listing',
				'categories' => array( (int) $term['term_id'] ),
			)
		);

		$this->assertSame( 'active', $classified->status );
		$this->assertEmpty( preg_grep( '/requires review/', (array) $subjects ), 'Emails: ' . implode( ' | ', (array) $subjects ) );
	}

	/**
	 * Step "A seller renewing an expired listing into moderation sends no
	 * 'requires review' email to the admin".
	 */
	public function test_renewal_into_review_emails_the_admin(): void {
		$manager    = Classified_Manager::get_instance();
		$classified = $this->listing( 'Renew into review' );
		$manager->expire( (int) $classified->id );
		$subjects = $this->mail_subjects();

		$days = $manager->renew_by_seller( (int) $classified->id, (int) $this->advertiser->id );

		$this->assertNotWPError( $days );
		$this->assertSame( 'pending', $manager->get( (int) $classified->id )->status );
		$this->assertNotEmpty( preg_grep( '/requires review/', (array) $subjects ), 'Emails: ' . implode( ' | ', (array) $subjects ) );
	}

	/**
	 * Step "Deleting a listing leaves orphan rows in
	 * wbam_classified_locations and wbam_message_threads/messages".
	 */
	public function test_deleting_a_listing_removes_its_location_and_conversations(): void {
		global $wpdb;
		foreach ( array( true, false ) as $force ) {
			$classified = $this->listing( 'Orphan listing ' . (int) $force );
			$id         = (int) $classified->id;

			$wpdb->insert(
				$wpdb->prefix . 'wbam_classified_locations',
				array(
					'classified_id' => $id,
					'city'          => 'Pune',
				)
			);
			$thread_id = \WBAM_Pro\Modules\Messaging\Message_Manager::get_instance()->get_or_create_thread( (int) self::factory()->user->create(), $this->user, $id );
			$this->assertIsInt( $thread_id );
			$wpdb->insert(
				$wpdb->prefix . 'wbam_messages',
				array(
					'thread_id' => $thread_id,
					'sender_id' => $this->user,
					'content'   => 'Still available?',
				)
			);

			$this->assertTrue( Classified_Manager::get_instance()->delete( $id, $force ) );

			$this->assertSame( '0', $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wbam_classified_locations WHERE classified_id = %d", $id ) ) );
			$this->assertSame( '0', $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wbam_message_threads WHERE classified_id = %d", $id ) ) );
			$this->assertSame( '0', $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wbam_messages WHERE thread_id = %d", $thread_id ) ) );
		}
	}

	/**
	 * Step "Classified::save() nulls any past expires_at, so an expired
	 * listing loses its end date".
	 */
	public function test_saving_an_expired_listing_keeps_its_end_date(): void {
		$classified             = $this->listing( 'Expired keeps date' );
		$classified->expires_at = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
		$classified->status     = 'expired';
		$classified->save();

		$this->assertNotEmpty( Classified_Manager::get_instance()->get( (int) $classified->id )->expires_at );
	}
}
