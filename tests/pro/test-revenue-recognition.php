<?php
/**
 * Credits used — a closed (completed) reserved campaign's credits used equal
 * what the campaign actually spent, never campaigns.spent added on top of
 * the reservation. (Revenue itself is counted at top-up: see
 * test-revenue-at-topup.php.)
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Core\Revenue_Ledger;
use WBAM_Pro\Core\Revenue_Query;
use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\AdSubmissions\Ad_Submission_Manager;
use WBAM_Pro\Modules\Campaigns\Campaign_Manager;

class Test_Revenue_Recognition extends Pro_Test_Case {

	private int $user;
	private object $advertiser;

	public function set_up(): void {
		parent::set_up();

		$enabled              = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['campaigns'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );

		$this->user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $this->user );
		Advertiser_Manager::get_instance()->update_status( (int) $this->advertiser->id, 'active' );
		$this->advertiser = Advertiser_Manager::get_instance()->get( (int) $this->advertiser->id );
		\Wbcom\Credits\Credits::topup( 'wbam-pro', $this->user, 500000, 'seed' );

		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . 'wbam_revenue' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test isolation.
	}

	private function net_for_advertiser(): float {
		$totals = Revenue_Query::totals( gmdate( 'Y-m-d', strtotime( '-1 day' ) ), gmdate( 'Y-m-d', strtotime( '+1 day' ) ), (int) $this->advertiser->id );
		return (float) $totals['credits_used'];
	}

	/**
	 * Reserve 1000, deliver 300 worth, complete the campaign -> the 700
	 * unused reservation comes back, net revenue settles at exactly what was
	 * spent (300) — never campaigns.spent stacked on top of the reservation.
	 */
	public function test_reserve_1000_spend_300_complete_nets_300(): void {
		global $wpdb;

		$campaign = Campaign_Manager::get_instance()->create(
			array(
				'advertiser_id'  => $this->advertiser->id,
				'name'           => 'Revenue recognition probe',
				'pricing_model'  => 'cpm',
				'price_per_unit' => 5.0,
				'budget'         => 1000.0,
				'status'         => 'draft',
			)
		);
		$this->assertNotWPError( $campaign );

		$activated = Campaign_Manager::get_instance()->update_status( (int) $campaign->id, 'active' );
		$this->assertNotWPError( $activated, 'Activation reservation failed.' );

		// Simulate delivery: the campaign has spent 300 of its 1000 reservation.
		$wpdb->update(
			$wpdb->prefix . 'wbam_campaigns',
			array( 'spent' => 300.0 ),
			array( 'id' => (int) $campaign->id ),
			array( '%f' ),
			array( '%d' )
		);

		$completed = Campaign_Manager::get_instance()->update_status( (int) $campaign->id, 'completed' );
		$this->assertNotWPError( $completed, 'Completion refund failed.' );

		$this->assertSame( 300.0, $this->net_for_advertiser() );
	}

	/**
	 * A tick charge (campaign_spend) reversed by its own compensating credit
	 * (same source, same amount) must net exactly zero.
	 */
	public function test_tick_charge_and_reversal_credit_net_zero(): void {
		$before = $this->net_for_advertiser();

		$charge = Credits_Bridge::charge( $this->advertiser->id, 42.50, 555, 'Unlimited-budget tick', false, Revenue_Ledger::SOURCE_CAMPAIGN_SPEND );
		$this->assertNotWPError( $charge );

		$reversal = Credits_Bridge::credit( $this->advertiser->id, 42.50, 555, 'Reversal: billing row failed to update', Revenue_Ledger::SOURCE_CAMPAIGN_SPEND );
		$this->assertNotWPError( $reversal );

		$this->assertSame( $before, $this->net_for_advertiser() );
	}

	/**
	 * A package charged on approval, then rejected and refunded in full,
	 * must leave net revenue exactly where it started.
	 */
	public function test_package_charged_then_rejected_and_refunded_nets_zero(): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'wbam_packages',
			array(
				'name'          => 'Revenue recognition package',
				'price'         => 49.00,
				'pricing_model' => 'flat',
				'status'        => 'active',
				'created_at'    => current_time( 'mysql' ),
			)
		);
		$package_id = (int) $wpdb->insert_id;

		$before = $this->net_for_advertiser();

		$submission = Ad_Submission_Manager::get_instance()->submit_ad(
			$this->advertiser->id,
			array(
				'title'     => 'Revenue recognition ad',
				'ad_type'   => 'image',
				'image_url' => 'https://example.com/x.png',
				'link_url'  => 'https://example.com',
			),
			$package_id
		);
		$this->assertNotWPError( $submission );

		$charge = Credits_Bridge::charge( $this->advertiser->id, 49.00, (int) $submission->ad_id, 'Package purchase: Revenue recognition package', false, Revenue_Ledger::SOURCE_AD_PACKAGE );
		$this->assertNotWPError( $charge );

		$this->assertSame( $before + 49.0, $this->net_for_advertiser() );

		$rejected = Ad_Submission_Manager::get_instance()->reject( (int) $submission->id, 'revenue recognition guard' );
		$this->assertTrue( $rejected );

		$this->assertSame( $before, $this->net_for_advertiser(), 'A rejected, refunded submission must not leave any net revenue behind.' );
	}
}
