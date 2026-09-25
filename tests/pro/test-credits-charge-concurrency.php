<?php
/**
 * Concurrent charges cannot both pass the balance check.
 *
 * charge() read the balance and inserted the deduction with nothing in
 * between, so two requests charging one account at once both saw the old
 * balance and both went through. Billing_Manager's SELECT ... FOR UPDATE ran
 * outside a transaction, so it locked nothing either: the cron and an admin
 * "bill now" could each bill the same unbilled spend.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Tests\Helpers\Factory;
use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Core\Revenue_Ledger;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Campaigns\Campaign_Manager;
use WBAM_Pro\Modules\Wallet\Billing_Manager;

class Test_Credits_Charge_Concurrency extends Pro_Test_Case {

	private function make_advertiser( int $minor ): array {
		$user_id = self::factory()->user->create();
		Factory::topup_user( $user_id, $minor );

		return array( (int) Advertiser_Manager::get_instance()->get_or_create( $user_id )->id, $user_id );
	}

	/**
	 * Another request holding this account's charge lock makes a charge wait,
	 * then refuse, instead of racing it.
	 */
	public function test_charge_waits_for_a_charge_already_running_on_the_account(): void {
		global $wpdb;

		list( $advertiser_id, $user_id ) = $this->make_advertiser( 10000 );

		// A second connection plays the other request, mid-charge.
		$other = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$this->assertSame( '1', (string) $other->get_var( $other->prepare( 'SELECT GET_LOCK(%s, 0)', $wpdb->prefix . 'wbam_credits_' . $user_id ) ) );

		$result = Credits_Bridge::charge( $advertiser_id, 10, 1, 'Race probe', false, Revenue_Ledger::SOURCE_AD_PACKAGE );

		$other->query( $other->prepare( 'SELECT RELEASE_LOCK(%s)', $wpdb->prefix . 'wbam_credits_' . $user_id ) );
		$other->close();

		$this->assertWPError( $result );
		$this->assertSame( 'wbam_credits_busy', $result->get_error_code() );
		$this->assertSame( 100.0, (float) Credits_Bridge::get_balance( $advertiser_id ) );
	}

	/**
	 * Two billers working from the same campaign read bill it once.
	 */
	public function test_the_same_unbilled_spend_is_billed_once(): void {
		global $wpdb;

		list( $advertiser_id ) = $this->make_advertiser( 100000 );
		$wpdb->insert(
			$wpdb->prefix . 'wbam_campaigns',
			array(
				'advertiser_id'  => $advertiser_id,
				'name'           => 'Double bill probe',
				'status'         => 'active',
				'pricing_model'  => 'cpm',
				'price_per_unit' => 5.0,
				'budget'         => 0,
				'spent'          => 120.0,
				'billed_amount'  => 0,
			)
		);
		$campaign = Campaign_Manager::get_instance()->get( (int) $wpdb->insert_id );

		// Both billers loaded the campaign before either billed it.
		Billing_Manager::get_instance()->process_campaign_billing( clone $campaign );
		Billing_Manager::get_instance()->process_campaign_billing( clone $campaign );

		$this->assertSame( 880.0, round( (float) Credits_Bridge::get_balance( $advertiser_id ), 2 ), '120 of spend was billed twice.' );
	}
}
