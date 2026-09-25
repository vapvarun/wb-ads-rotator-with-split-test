<?php
/**
 * Regression guards for Basecamp card 10335669355 "[P1][Pro] Moderation:
 * every status needs a way out and a notice" - the 7 QA rejects fixed in
 * 3.2.0: the auto-approve-advertisers double email, reject() leaving a
 * refunded classified's paid state (pending_upgrades + plan featured
 * credit) intact for a free re-grant on resubmit, a replayed
 * request_changes()/Approve double-charging a submission, dead bulk
 * actions on the admin Classifieds list (checkbox name mismatch) and REST
 * admin approve/reject treating WP_Error as success, a review Approve
 * link double-emailing on F5 (no PRG, no same-status guard), a declined
 * advertiser application sending no notice (and a stale Decline link
 * demoting an advertiser approved in the meantime), and publishing a
 * pending portal ad from the WP editor skipping approval and payment.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\AdSubmissions\Ad_Submission_Manager;
use WBAM_Pro\Modules\Campaigns\Campaign_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;
use WBAM_Pro\Modules\Memberships\Membership_Manager;

class Test_Moderation_Rejects_3_2 extends Pro_Test_Case {

	private int $user;
	private object $advertiser;

	public function set_up(): void {
		parent::set_up();

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		$enabled['campaigns']   = true;
		$enabled['memberships'] = true;
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

	private function category(): int {
		$term = wp_insert_term( 'Moderation ' . wp_generate_password( 6, false ), Classified_Manager::TAXONOMY_CATEGORY );
		return (int) $term['term_id'];
	}

	/**
	 * A pending submission with a $49 flat package attached, ready for
	 * approve()/reject()/request_changes() to act on.
	 */
	private function submit_flat_package_ad( string $title, float $price = 49.00 ): object {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'wbam_packages',
			array(
				'name'          => $title . ' package',
				'price'         => $price,
				'pricing_model' => 'flat',
				'status'        => 'active',
				'created_at'    => current_time( 'mysql' ),
			)
		);
		$package_id = (int) $wpdb->insert_id;

		$submission = Ad_Submission_Manager::get_instance()->submit_ad(
			$this->advertiser->id,
			array(
				'title'     => $title,
				'ad_type'   => 'image',
				'image_url' => 'https://example.com/x.png',
				'link_url'  => 'https://example.com',
			),
			$package_id
		);
		$this->assertNotWPError( $submission );

		return $submission;
	}

	/**
	 * A campaign already in a terminal ('completed') status, so
	 * Campaign_Manager::activate() always refuses the transition - a
	 * reliable, balance-independent way to make activate_ad()'s campaign
	 * branch fail on demand.
	 */
	private function terminal_campaign(): object {
		global $wpdb;

		$campaign = Campaign_Manager::get_instance()->create(
			array(
				'advertiser_id'  => $this->advertiser->id,
				'name'           => 'Terminal probe',
				'pricing_model'  => 'cpm',
				'price_per_unit' => 1.0,
				'budget'         => 10.0,
				'status'         => 'draft',
			)
		);
		$this->assertNotWPError( $campaign );
		$wpdb->update( $wpdb->prefix . 'wbam_campaigns', array( 'status' => 'completed' ), array( 'id' => (int) $campaign->id ) );

		return $campaign;
	}

	/**
	 * Reject 1 (email de-dup only - the settings-screen toggle is a
	 * pending hunk, held back while QA lane B is live-testing Settings).
	 * The apply-for-ads flow sends exactly one email when auto-approve
	 * puts the applicant straight to 'active': update_status() already
	 * fires wbam_advertiser_approved for that transition, so the explicit
	 * welcome/pending email must be skipped, not stacked on top of it.
	 */
	public function test_become_advertiser_flow_sends_one_email_on_auto_approve(): void {
		$source = (string) file_get_contents( WBAM_PRO_PATH . 'includes/Modules/Advertisers/class-advertiser-shortcodes.php' );
		$this->assertStringContainsString(
			"if ( 'active' !== \$apply_status ) {",
			$source,
			'Auto-approve already sends the approved email via wbam_advertiser_approved (fired inside update_status()); the welcome/pending email must be skipped for that case so the applicant gets exactly one email.'
		);
	}

	/**
	 * Reject 2 (money): reject() must clear pending_upgrades and return a
	 * spent plan featured credit - otherwise a later approve() on the
	 * seller's edited resubmit grants Featured for free.
	 */
	public function test_reject_clears_pending_upgrades_and_releases_featured_credit(): void {
		$members = Membership_Manager::get_instance();
		$members->save_plan(
			array(
				'name'          => 'Reject-guard featured plan',
				'price'         => 0,
				'billing_cycle' => 'monthly',
				'max_listings'  => 0,
				'max_featured'  => 1,
				'status'        => 'active',
			)
		);
		$plans = $members->get_plans();
		$plan  = end( $plans );
		$this->assertNotWPError( $members->subscribe( $this->advertiser->id, $plan->id ) );
		$this->assertSame( 1, $members->featured_credits_left( $this->advertiser->id ) );

		$classified = Classified_Manager::get_instance()->submit(
			$this->advertiser,
			array(
				'title'            => 'Featured via plan credit',
				'categories'       => array( $this->category() ),
				'listing_package'  => 0,
				'upgrades'         => array( 'featured' ),
			)
		);
		$this->assertNotWPError( $classified );
		$this->assertSame( 'pending', $classified->status );
		$this->assertSame( 0, $members->featured_credits_left( $this->advertiser->id ), 'The plan credit is spent at submit time.' );
		$this->assertNotEmpty( $classified->get_meta( 'pending_upgrades' ), 'Featured is queued, not yet applied, while pending.' );

		$result = Classified_Manager::get_instance()->reject( (int) $classified->id, 'reject guard' );
		$this->assertNotWPError( $result );

		$this->assertSame( '', $classified->get_meta( 'pending_upgrades' ), 'pending_upgrades must be cleared so a later approve() cannot grant them for free on resubmit.' );
		$this->assertSame( 1, $members->featured_credits_left( $this->advertiser->id ), 'A plan featured credit spent at submit must be returned on reject.' );
	}

	/**
	 * Reject 2 known-broken-data note: QA's classified 11 (post 84) is left
	 * untouched by this fix - reject() only guards its OWN write path,
	 * never rewrites another classified's existing meta.
	 */
	public function test_reject_only_touches_its_own_classified(): void {
		$other = Classified_Manager::get_instance()->submit(
			$this->advertiser,
			array(
				'title'      => 'Untouched sibling listing',
				'categories' => array( $this->category() ),
			)
		);
		$this->assertNotWPError( $other );
		$other->update_meta( 'pending_upgrades', array( 'featured' ) );

		$mine = Classified_Manager::get_instance()->submit(
			$this->advertiser,
			array(
				'title'      => 'This one gets rejected',
				'categories' => array( $this->category() ),
			)
		);
		$this->assertNotWPError( $mine );

		Classified_Manager::get_instance()->reject( (int) $mine->id, 'guard' );

		$this->assertSame( array( 'featured' ), $other->get_meta( 'pending_upgrades' ), "Rejecting one listing must not touch a different listing's meta." );
	}

	/**
	 * Reject 3 (money): a replayed request_changes() POST against an
	 * already-approved submission must not re-open it for a second
	 * Approve-and-charge. QA reproduced ledger #37/#38, -$49 twice.
	 */
	public function test_replayed_request_changes_cannot_reopen_an_approved_submission_for_double_charge(): void {
		$submission = $this->submit_flat_package_ad( 'Double charge guard ad' );
		$before     = $this->balance();

		$this->assertNotWPError( Ad_Submission_Manager::get_instance()->approve( (int) $submission->id ) );
		$after_first_approve = $this->balance();
		$this->assertSame( $before - 4900, $after_first_approve, 'First approve charges the $49 package once.' );

		$this->assertFalse(
			Ad_Submission_Manager::get_instance()->request_changes( (int) $submission->id, 'stale replay' ),
			'request_changes() must refuse a non-reviewable (already approved) submission.'
		);

		$second_approve = Ad_Submission_Manager::get_instance()->approve( (int) $submission->id );
		$this->assertWPError( $second_approve, 'A replayed Approve on an already-approved submission must be refused, not charged again.' );

		$this->assertSame( $after_first_approve, $this->balance(), 'Total charge across both approve attempts must be exactly one package price.' );
	}

	/**
	 * Reject 3 (money): if activate_ad()'s campaign activation fails AFTER
	 * the package charge succeeded, the charge must be refunded - an
	 * advertiser must never pay for a package whose ad never goes live.
	 */
	public function test_activate_ad_refunds_package_charge_when_campaign_activation_fails(): void {
		global $wpdb;

		$campaign   = $this->terminal_campaign();
		$submission = $this->submit_flat_package_ad( 'Activation-fail refund ad' );
		$wpdb->update( $wpdb->prefix . 'wbam_ad_submissions', array( 'campaign_id' => (int) $campaign->id ), array( 'id' => (int) $submission->id ) );

		$before   = $this->balance();
		$approved = Ad_Submission_Manager::get_instance()->approve( (int) $submission->id );

		$this->assertWPError( $approved, 'A terminal campaign cannot activate; approve() must fail.' );
		$this->assertSame( $before, $this->balance(), 'The package charge must be refunded when campaign activation fails after it.' );
	}

	/**
	 * Reject 4: REST admin_approve()/admin_reject() must not report success
	 * for a WP_Error result - WP_Error is truthy in PHP, so `! $result`
	 * silently fell through to the success response.
	 */
	public function test_rest_admin_approve_and_reject_report_errors_for_a_missing_classified(): void {
		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		do_action( 'rest_api_init' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$approve_response = rest_do_request( new \WP_REST_Request( 'POST', '/wbam-pro/v1/admin/classifieds/999999999/approve' ) );
		$this->assertTrue( $approve_response->is_error(), 'admin_approve() must surface the not_found WP_Error, not report success.' );

		$reject_response = rest_do_request( new \WP_REST_Request( 'POST', '/wbam-pro/v1/admin/classifieds/999999999/reject' ) );
		$this->assertTrue( $reject_response->is_error(), 'admin_reject() must surface the not_found WP_Error, not report success.' );
	}

	/**
	 * Reject 4: the checkboxes WP_List_Table renders are classified_ids[]
	 * (Classifieds_List_Table::column_cb()); the bulk handler used to read
	 * $_GET['classifieds'], which never matched, so every bulk action
	 * silently did nothing.
	 */
	public function test_bulk_classified_handler_reads_the_list_tables_checkbox_name(): void {
		$source = (string) file_get_contents( WBAM_PRO_PATH . 'includes/Core/class-pro-admin.php' );
		$this->assertStringContainsString( "isset( \$_GET['classified_ids'] )", $source );
	}

	/**
	 * Reject 5: F5 on an Approve-review link must not re-send both emails.
	 * Review_Manager::update_status() needs a same-status no-op guard as
	 * defense in depth alongside the admin PRG redirect.
	 */
	public function test_review_status_update_to_same_status_is_a_noop(): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'wbam_reviews',
			array(
				'advertiser_id'    => $this->advertiser->id,
				'reviewer_user_id' => $this->user,
				'rating'           => 5,
				'status'           => 'approved',
				'created_at'       => current_time( 'mysql' ),
			)
		);
		$review_id = (int) $wpdb->insert_id;

		$fired = 0;
		add_action(
			'wbam_review_status_updated',
			function () use ( &$fired ) {
				++$fired;
			}
		);

		$result = \WBAM_Pro\Modules\Reviews\Review_Manager::get_instance()->update_status( $review_id, 'approved' );

		$this->assertNotWPError( $result );
		$this->assertSame( 0, $fired, 'A same-status update (F5 replay of an approve link) must not re-fire the notification hook.' );
	}

	/**
	 * Reject 5: the approve/reject GET must be handled before any output so
	 * it can redirect (PRG) - F5 only replays the plain listing page.
	 */
	public function test_review_actions_are_handled_before_output_with_a_redirect(): void {
		$source = (string) file_get_contents( WBAM_PRO_PATH . 'includes/Core/class-pro-admin.php' );
		$this->assertStringContainsString(
			"'wbam-reviews' === \$current_page",
			$source,
			'Review approve/reject must be dispatched from handle_admin_actions() (admin_init, before output), not from inside the page render callback.'
		);

		$handler_start = strpos( $source, 'private function handle_review_actions()' );
		$this->assertNotFalse( $handler_start );
		$handler_body = substr( $source, $handler_start, 1500 );
		$this->assertStringContainsString( 'wp_safe_redirect', $handler_body );
		$this->assertStringContainsString( 'exit', $handler_body );
	}

	/**
	 * Reject 6: declining a pending application (Decline for ads, which
	 * demotes to 'member') must fire the same notice hook as suspend/ban -
	 * the applicant's welcome email promised a notification either way.
	 */
	public function test_decline_to_member_fires_the_rejected_notice_hook(): void {
		$user      = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$applicant = Advertiser_Manager::get_instance()->get_or_create( $user );
		Advertiser_Manager::get_instance()->update_status( (int) $applicant->id, 'pending' );

		$fired = 0;
		add_action(
			'wbam_advertiser_rejected',
			function () use ( &$fired ) {
				++$fired;
			}
		);

		$result = Advertiser_Manager::get_instance()->update_status( (int) $applicant->id, 'member' );

		$this->assertNotWPError( $result );
		$this->assertSame( 1, $fired, 'Declining (pending -> member) must fire wbam_advertiser_rejected, same as suspend/ban.' );
	}

	/**
	 * Reject 6: a stale/replayed Decline link - copied while an advertiser
	 * was pending, then the advertiser is separately Approved - must
	 * refuse (with an admin notice), not demote an already-active
	 * advertiser. QA repro: copy Decline while pending, Approve, open the
	 * copied link.
	 */
	public function test_decline_admin_handler_guards_current_status(): void {
		$source = (string) file_get_contents( WBAM_PRO_PATH . 'includes/Core/class-pro-admin.php' );

		$case_start = strpos( $source, "case 'decline':" );
		$this->assertNotFalse( $case_start );
		$case_body = substr( $source, $case_start, 700 );

		$this->assertStringContainsString(
			"'pending' !== \$current->status",
			$case_body,
			'A stale Decline link must be refused once the advertiser is no longer pending (e.g. approved in the meantime).'
		);
		$this->assertStringContainsString(
			"'advertiser_not_pending'",
			$case_body,
			'The refusal must surface as a distinct admin notice, not silently no-op.'
		);
	}

	/**
	 * Reject 7 (money): publishing a pending portal ad from the WP editor
	 * must go through the same approve() path as the Submissions "Approve"
	 * button - charge, activate the campaign, approve the submission.
	 */
	public function test_publishing_pending_portal_ad_from_editor_goes_through_approve(): void {
		$submission = $this->submit_flat_package_ad( 'Editor publish ad' );
		$before     = $this->balance();

		wp_update_post(
			array(
				'ID'          => (int) $submission->ad_id,
				'post_status' => 'publish',
			)
		);

		$reloaded = Ad_Submission_Manager::get_instance()->get( (int) $submission->id );
		$this->assertSame( 'approved', $reloaded->status, 'Publishing from the editor must approve the submission, not leave it Pending.' );
		$this->assertSame( $before - 4900, $this->balance(), 'Approval via the editor Publish button must still charge the package.' );
		$this->assertSame( '1', get_post_meta( (int) $submission->ad_id, '_wbam_enabled', true ) );
	}

	/**
	 * Card 10335670599 item 4: one approval, one email - also when the
	 * approval comes from the editor's Publish button.
	 */
	public function test_editor_publish_sends_one_approval_email(): void {
		$submission = $this->submit_flat_package_ad( 'Editor publish mail ad' );
		$subjects   = array();
		add_filter(
			'pre_wp_mail',
			function ( $short, $atts ) use ( &$subjects ) {
				$subjects[] = $atts['subject'];
				return true;
			},
			10,
			2
		);

		wp_update_post(
			array(
				'ID'          => (int) $submission->ad_id,
				'post_status' => 'publish',
			)
		);

		$approved = array_filter(
			$subjects,
			static function ( $subject ) {
				return false !== stripos( $subject, 'approved' );
			}
		);
		$this->assertCount( 1, $approved, 'Emails: ' . implode( ' | ', $subjects ) );
	}

	/**
	 * Reject 7 (money): if approve() fails when triggered from the editor
	 * (e.g. the campaign cannot activate), the Publish click must be
	 * reverted - not leave a live, unpaid, unreviewed ad in front of
	 * visitors - and any package charge already taken must be refunded.
	 */
	public function test_editor_publish_reverts_and_refunds_when_approve_fails(): void {
		global $wpdb;

		$campaign   = $this->terminal_campaign();
		$submission = $this->submit_flat_package_ad( 'Editor publish revert ad' );
		$wpdb->update( $wpdb->prefix . 'wbam_ad_submissions', array( 'campaign_id' => (int) $campaign->id ), array( 'id' => (int) $submission->id ) );

		$before = $this->balance();

		wp_update_post(
			array(
				'ID'          => (int) $submission->ad_id,
				'post_status' => 'publish',
			)
		);

		$this->assertSame( 'pending', get_post_status( (int) $submission->ad_id ), 'A failed approve() triggered from the editor must revert the Publish click.' );
		$reloaded = Ad_Submission_Manager::get_instance()->get( (int) $submission->id );
		$this->assertSame( 'pending', $reloaded->status );
		$this->assertSame( $before, $this->balance(), 'The package charge must be refunded, not left stuck against a never-published ad.' );
	}
}
