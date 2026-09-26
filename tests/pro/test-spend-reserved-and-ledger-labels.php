<?php
/**
 * One "Total Spent": credits used net of refunds, with budget held on running
 * campaigns shown separately as Reserved (owner decision 2026-09-26). Ledger
 * rows keep their cents, say what the money was (Paid top-up / Complimentary
 * credit), and read in site time.
 *
 * QA repro (card 10340185077): Overview and the admin list read $13.00 while
 * the wallet's campaign spend read $1.50; "Payment received offline" sat under
 * SPENT; a $20.55 top-up read +$20.00; the row time was the MySQL clock.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Core\Revenue_Ledger;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Campaigns\Campaign_Manager;
use WBAM_Pro\Core\Settings_Helper;

class Test_Spend_Reserved_And_Ledger_Labels extends Pro_Test_Case {

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
	}

	public function test_total_spent_excludes_held_reserve(): void {
		global $wpdb;

		Advertiser_Manager::get_instance()->adjust_balance( $this->advertiser->id, 20.55, 'Bank transfer', Revenue_Ledger::SOURCE_OFFLINE_PAYMENT );

		$campaign = Campaign_Manager::get_instance()->create(
			array(
				'advertiser_id'  => $this->advertiser->id,
				'name'           => 'Reserve probe',
				'pricing_model'  => 'cpc',
				'price_per_unit' => 0.5,
				'budget'         => 10.0,
				'status'         => 'draft',
			)
		);
		$this->assertNotWPError( Campaign_Manager::get_instance()->update_status( (int) $campaign->id, 'active' ) );
		$wpdb->update( $wpdb->prefix . 'wbam_campaigns', array( 'spent' => 1.5 ), array( 'id' => (int) $campaign->id ) );

		$this->assertSame( 1.5, Credits_Bridge::get_total_spent( $this->advertiser->id ) );
		$this->assertSame( 8.5, Credits_Bridge::get_reserved( $this->advertiser->id ) );

		$wallets = Credits_Bridge::wallet_totals( array( $this->user ) );
		$this->assertSame( 1.5, $wallets[ $this->user ]['spent'], 'The admin list reads the same figure.' );
		$this->assertSame( 8.5, $wallets[ $this->user ]['reserved'] );

		$advertiser = $this->advertiser;
		ob_start();
		include WBAM_PRO_PATH . 'templates/portal/tabs/wallet.php';
		$html = ob_get_clean();

		$this->assertStringNotContainsString( 'wbam-campaign-spend__name">Payment received offline', $html, 'A top-up is not spending.' );
		$this->assertMatchesRegularExpression( '/Total spent<\/th>\s*<td class="wbam-num"><strong>\$1\.50/', $html );
	}

	public function test_ledger_rows_keep_cents_say_what_they_are_and_read_site_time(): void {
		Advertiser_Manager::get_instance()->adjust_balance( $this->advertiser->id, 20.55, 'Bank transfer', Revenue_Ledger::SOURCE_OFFLINE_PAYMENT );
		Advertiser_Manager::get_instance()->adjust_balance( $this->advertiser->id, 5, 'Welcome gift', Revenue_Ledger::SOURCE_COMPLIMENTARY_CREDIT );

		$rows = array();
		foreach ( Credits_Bridge::get_ledger( $this->advertiser->id, 10, 0 ) as $row ) {
			$rows[ $row->get_note() ] = $row;
		}
		$this->assertCount( 2, $rows );
		$paid = $rows['Bank transfer'];
		$gift = $rows['Welcome gift'];

		$this->assertSame( '+$20.55', $paid->get_formatted_amount() );
		$this->assertSame( 'Paid top-up', $paid->get_type_label() );
		$this->assertSame( 'Complimentary credit', $gift->get_type_label() );
		$this->assertSame( Revenue_Ledger::ledger_timestamp( $paid->get_created_at() ), $paid->get_timestamp() );

		$listed = Credits_Bridge::query_ledger( array( 'user_id' => $this->user, 'search' => 'Bank transfer' ) );
		$this->assertSame( 'Paid top-up', ( new \WBAM_Pro\Core\Ledger_Row( $listed[0] ) )->get_type_label(), 'The admin Transactions screen names it the same way.' );
	}
}
