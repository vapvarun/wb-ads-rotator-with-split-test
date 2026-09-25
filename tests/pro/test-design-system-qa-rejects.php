<?php
/**
 * QA rejects on card 10339874920 ([Pro][Free] Admin presentability: one
 * design system across admin):
 *
 *  - The classified bulk-reject summary said "N submissions selected" and
 *    the submission bulk-reject summary said "N listings selected" — the
 *    two labels were swapped in class-pro-admin.php.
 *  - Advertiser add/edit had no action bar and no Cancel link, though the
 *    card comment listed them as covered.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Pro_Admin;
use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\AdSubmissions\Ad_Submission_Manager;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;

class Test_Design_System_Qa_Rejects extends Pro_Test_Case {

	private int $user;
	private object $advertiser;

	public function set_up(): void {
		parent::set_up();

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );

		$this->user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $this->user );
		Advertiser_Manager::get_instance()->update_status( (int) $this->advertiser->id, 'active' );
		$this->advertiser = Advertiser_Manager::get_instance()->get( (int) $this->advertiser->id );
	}

	private function render_private( string $method, array $args = array() ): string {
		$reflection = new \ReflectionMethod( Pro_Admin::class, $method );
		$reflection->setAccessible( true );
		ob_start();
		$reflection->invokeArgs( new Pro_Admin(), $args );
		return ob_get_clean();
	}

	/**
	 * Bulk-rejecting listings must say "listing(s)", not "submission(s)" —
	 * class-pro-admin.php ~3016 had it backwards.
	 */
	public function test_classified_bulk_reject_summary_says_listings_not_submissions(): void {
		$classified = Classified_Manager::get_instance()->create(
			array(
				'title'         => 'Swap-label listing',
				'description'   => 'Test.',
				'advertiser_id' => $this->advertiser->id,
				'status'        => 'pending',
			)
		);
		$this->assertNotWPError( $classified );

		$output = $this->render_private( 'render_classified_bulk_reject_form', array( array( (int) $classified->id ) ) );

		$this->assertStringContainsString( '1 listing selected', $output );
		$this->assertStringNotContainsString( 'submission selected', $output );
	}

	/**
	 * Bulk-rejecting ad submissions must say "submission(s)", not
	 * "listing(s)" — class-pro-admin.php ~2868 had it backwards.
	 */
	public function test_submission_bulk_reject_summary_says_submissions_not_listings(): void {
		\Wbcom\Credits\Credits::topup( 'wbam-pro', $this->user, 100000, 'seed' );

		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'wbam_packages',
			array(
				'name'          => 'Swap Label Package',
				'price'         => 10.00,
				'pricing_model' => 'flat',
				'status'        => 'active',
				'created_at'    => current_time( 'mysql' ),
			)
		);
		$package_id = (int) $wpdb->insert_id;

		$submission = Ad_Submission_Manager::get_instance()->submit_ad(
			$this->advertiser->id,
			array(
				'title'     => 'Swap-label ad',
				'ad_type'   => 'image',
				'image_url' => 'https://example.com/x.png',
				'link_url'  => 'https://example.com',
			),
			$package_id
		);
		$this->assertNotWPError( $submission );

		$output = $this->render_private( 'render_submission_bulk_reject_form', array( array( (int) $submission->id ) ) );

		$this->assertStringContainsString( '1 submission selected', $output );
		$this->assertStringNotContainsString( 'listing selected', $output );
	}

	/**
	 * Advertiser add/edit must have the shared action bar (submit + Cancel),
	 * like every other action screen in the design system.
	 */
	public function test_advertiser_add_form_has_action_bar_and_cancel(): void {
		$output = $this->render_private( 'render_advertiser_form' );

		$this->assertStringContainsString( 'wbam-action-bar', $output );
		$this->assertStringContainsString( 'wbam-action-bar__cancel', $output );
		$this->assertStringContainsString( 'Add Advertiser', $output );
	}

	public function test_advertiser_edit_form_has_action_bar_and_cancel(): void {
		$output = $this->render_private( 'render_advertiser_form', array( (int) $this->advertiser->id ) );

		$this->assertStringContainsString( 'wbam-action-bar', $output );
		$this->assertStringContainsString( 'wbam-action-bar__cancel', $output );
		$this->assertStringContainsString( 'Update Advertiser', $output );
	}
}
