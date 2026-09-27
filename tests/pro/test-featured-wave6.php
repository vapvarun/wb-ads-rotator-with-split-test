<?php
/**
 * Featured, wave 6 (cards 10343726590 and 10343726490, owner decisions
 * 2026-09-27):
 *
 * - A plan's Featured credit is used first on every Featured path, Promote
 *   included; money only once none is left.
 * - An already-featured listing is told so (409) before any balance talk.
 * - Posting with only Featured books classified_featured revenue.
 * - The Featured emails say why it ended in words, never the raw slug.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Tests\Helpers\Factory;
use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Billing;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;
use WBAM_Pro\Modules\Memberships\Membership_Manager;

class Test_Featured_Wave6 extends Pro_Test_Case {

	private object $advertiser;

	private int $user_id;

	public function set_up(): void {
		// add_upgrades() commits its own transaction.
		$this->snapshot_options( array( 'wbam_pro_settings', 'wbam_pro_classifieds_settings', 'wbam_credits_payment_method' ) );
		parent::set_up();

		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}wbam_classifieds" ); // phpcs:ignore WordPress.DB -- test isolation.

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		$enabled['memberships'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );
		update_option( 'wbam_credits_payment_method', 'manual' );
		Settings_Helper::update_module( 'classifieds', 'featured_price', 5 );
		Settings_Helper::update_module( 'classifieds', 'upgrade_duration', 7 );

		$this->user_id    = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $this->user_id );
	}

	private function active_listing() {
		$manager    = Classified_Manager::get_instance();
		$classified = $manager->create(
			array(
				'title'         => 'Wave 6 probe',
				'description'   => 'Probe.',
				'advertiser_id' => $this->advertiser->id,
			)
		);
		$this->assertNotWPError( $classified );
		$manager->update( (int) $classified->id, array( 'status' => 'active' ) );
		return $manager->get( (int) $classified->id );
	}

	private function subscribe_with_featured( int $featured ): void {
		$members = Membership_Manager::get_instance();
		$members->save_plan(
			array(
				'name'          => 'Featured plan',
				'price'         => 0,
				'billing_cycle' => 'monthly',
				'max_listings'  => 10,
				'max_featured'  => $featured,
				'status'        => 'active',
			)
		);
		$plans = $members->get_plans();
		$members->subscribe( $this->advertiser->id, end( $plans )->id );
	}

	public function test_promote_uses_a_plan_credit_before_money(): void {
		$this->subscribe_with_featured( 1 );
		$classified = $this->active_listing();
		$before     = Credits_Bridge::get_balance_minor( $this->advertiser->id );

		// No balance at all: the plan credit alone pays for it.
		$this->assertTrue( $classified->upgrade_to_featured( 1, 5.0, true ) );

		$fresh = Classified_Manager::get_instance()->get( (int) $classified->id );
		$this->assertTrue( $fresh->is_featured() );
		$this->assertSame( 'plan', $fresh->featured_fee_status, 'Settled by the plan, not paid.' );
		$this->assertSame( $before, Credits_Bridge::get_balance_minor( $this->advertiser->id ), 'Nothing charged.' );
		$this->assertSame( 0, Membership_Manager::get_instance()->featured_credits_left( $this->advertiser->id ), 'The credit is spent.' );
	}

	public function test_promote_charges_once_the_plan_credits_are_used(): void {
		$this->subscribe_with_featured( 0 );
		$classified = $this->active_listing();

		$result = $classified->upgrade_to_featured( 1, 5.0, true );

		$this->assertWPError( $result, 'No credit and no balance: refused.' );
		$this->assertSame( 'insufficient_funds', $result->get_error_code() );
	}

	public function test_an_already_featured_listing_is_told_so_before_the_balance(): void {
		$classified = $this->active_listing();
		Factory::topup_user( $this->user_id, 500 );
		$this->assertTrue( $classified->upgrade_to_featured( 1, 5.0, true ) );

		// Balance is now 0: the answer must still be "already featured".
		$again = Classified_Manager::get_instance()->get( (int) $classified->id )->upgrade_to_featured( 1, 5.0, true );

		$this->assertWPError( $again );
		$this->assertSame( 'already_featured', $again->get_error_code() );
		$this->assertSame( 409, $again->get_error_data()['status'] );
	}

	public function test_the_ended_email_says_why_in_words(): void {
		$this->assertSame( 'A site administrator removed Featured.', Classified_Billing::end_reason_text( 'admin' ) );
		$this->assertStringNotContainsString( 'insufficient_funds', Classified_Billing::end_reason_text( 'insufficient_funds' ) );
		$this->assertSame( 'Granted by the site', Classified_Billing::how_paid( 0, 'admin', false ) );
		$this->assertSame( 'Included in your plan', Classified_Billing::how_paid( 0, 'plan', false ) );
		$this->assertStringStartsWith( 'Paid ', Classified_Billing::how_paid( 5, 'promote', false ) );
	}

	public function test_upgrade_names_never_show_the_raw_slug(): void {
		$this->assertSame( 'Featured', Classified_Manager::upgrade_label( 'featured' ) );
		$this->assertSame( 'Highlighted', Classified_Manager::upgrade_label( 'highlight' ) );
	}
}
