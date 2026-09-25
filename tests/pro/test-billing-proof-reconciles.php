<?php
/**
 * The advertiser billing proof adds up to what was spent.
 *
 * The proof summed analytics rows at the campaign rate: a CPC campaign with
 * 20 billed clicks ($10.00 spent) showed "35 billed events totalling $8.50",
 * counting 18 impressions it never charged for and missing clicks that were
 * billed without an analytics row (billing ignores consent; analytics does
 * not). The summary now comes from the billing counters, and the event list
 * holds only the billed event type.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Analytics\Inventory_Dashboard;
use WBAM_Pro\Modules\Campaigns\Campaign;

require_once WBAM_PRO_PATH . 'includes/Modules/Analytics/class-inventory-dashboard.php';

/**
 * @group pro
 * @group reports
 */
class Test_Billing_Proof_Reconciles extends Pro_Test_Case {

	private function campaign( string $model, float $rate, int $impressions, int $clicks, float $spent ): int {
		global $wpdb;
		$advertiser = \WBAM_Pro\Modules\Advertisers\Advertiser_Manager::get_instance()->create( self::factory()->user->create(), array( 'status' => 'active' ) );
		$wpdb->insert(
			$wpdb->prefix . 'wbam_campaigns',
			array(
				'advertiser_id'  => (int) $advertiser->id,
				'name'           => 'Proof ' . $model,
				'pricing_model'  => $model,
				'price_per_unit' => $rate,
				'impressions'    => $impressions,
				'clicks'         => $clicks,
				'spent'          => $spent,
				'status'         => 'active',
			)
		);
		$this->assertSame( '', $wpdb->last_error );
		return (int) $wpdb->insert_id;
	}

	private function event( int $campaign_id, string $type ): void {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'wbam_analytics',
			array(
				'ad_id'       => 1,
				'campaign_id' => $campaign_id,
				'event_type'  => $type,
				'placement'   => 'header',
				'created_at'  => current_time( 'mysql' ),
			)
		);
	}

	public function test_cpc_proof_sums_to_spent_and_lists_only_clicks(): void {
		// 20 clicks billed; analytics recorded 17 of them plus 18 impressions.
		$id = $this->campaign( 'cpc', 0.5, 20, 20, 10.0 );
		for ( $i = 0; $i < 17; $i++ ) {
			$this->event( $id, 'click' );
		}
		for ( $i = 0; $i < 18; $i++ ) {
			$this->event( $id, 'impression' );
		}

		$ledger = Inventory_Dashboard::get_campaign_ledger( $id, 50, 30 );
		$this->assertCount( 17, $ledger );
		$this->assertSame( array( 'click' ), array_values( array_unique( wp_list_pluck( $ledger, 'event_type' ) ) ) );

		$lines = ( new Campaign( $id ) )->get_billed_lines();
		$this->assertCount( 1, $lines );
		$this->assertSame( 20, $lines[0]['count'] );
		$this->assertEqualsWithDelta( 10.0, array_sum( wp_list_pluck( $lines, 'amount' ) ), 0.000001 );
	}

	public function test_cpm_proof_sums_to_spent_and_flat_bills_no_events(): void {
		$cpm = $this->campaign( 'cpm', 10.0, 9, 1, 0.09 );
		$this->assertEqualsWithDelta( 0.09, array_sum( wp_list_pluck( ( new Campaign( $cpm ) )->get_billed_lines(), 'amount' ) ), 0.000001 );

		$flat = $this->campaign( 'flat', 0.0, 40, 2, 0.0 );
		$this->event( $flat, 'impression' );
		$this->assertSame( array(), ( new Campaign( $flat ) )->get_billed_lines() );
		$this->assertSame( array(), Inventory_Dashboard::get_campaign_ledger( $flat, 50, 30 ) );
	}
}
