<?php
/**
 * "Total Spent" is what the advertiser was charged, net of refunds, on every
 * screen.
 *
 * The portal overview summed campaign delivery, so package, listing and plan
 * charges never showed ($147 of package charges read $0.00); the admin figure
 * summed every deduction, refunded or not, including admin debits.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Core\Revenue_Ledger;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

class Test_Advertiser_Total_Spent extends Pro_Test_Case {

	private object $advertiser;

	public function set_up(): void {
		parent::set_up();

		$user             = (int) self::factory()->user->create();
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );

		Credits_Bridge::topup( $this->advertiser->id, 500, 'Grant' );
		Credits_Bridge::charge( $this->advertiser->id, 98, 11, 'Package A', false, Revenue_Ledger::SOURCE_AD_PACKAGE );
		Credits_Bridge::charge( $this->advertiser->id, 49, 12, 'Package B', false, Revenue_Ledger::SOURCE_AD_PACKAGE );
		Credits_Bridge::charge( $this->advertiser->id, 20, 13, 'Package C', false, Revenue_Ledger::SOURCE_AD_PACKAGE );
		Credits_Bridge::credit( $this->advertiser->id, 20, 13, 'Refund for rejected ad submission', Revenue_Ledger::SOURCE_AD_PACKAGE );
		Credits_Bridge::adjust( $this->advertiser->id, -30, 'Admin debit adjustment' );
	}

	public function test_total_spent_is_charges_net_of_refunds(): void {
		$this->assertSame( 147.0, (float) Credits_Bridge::get_total_spent( $this->advertiser->id ) );
	}

	public function test_portal_overview_shows_it(): void {
		$advertiser = Advertiser_Manager::get_instance()->get( (int) $this->advertiser->id );

		ob_start();
		include WBAM_PRO_PATH . 'templates/portal/tabs/overview.php';
		$html = ob_get_clean();

		$this->assertMatchesRegularExpression( '/147\.00<\/span>\s*<span class="wbam-stat-label">Total Spent/', $html );
	}
}
