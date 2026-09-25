<?php
/**
 * Rejection refunds net by item TYPE, not just item id.
 *
 * The credit ledger's item_id is shared by ads, campaigns, classifieds and
 * plans. Netting on user + item_id alone meant rejecting a listing also
 * "refunded" a campaign charge that happened to carry the same id.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Tests\Helpers\Factory;
use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Core\Revenue_Ledger;
use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;

class Test_Rejection_Refund_Item_Type extends Pro_Test_Case {

	public function test_rejecting_a_listing_leaves_a_same_id_campaign_charge_alone(): void {
		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );

		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		Factory::topup_user( $user, 100000 );

		$classified = Classified_Manager::get_instance()->create(
			array(
				'title'         => 'Collision listing',
				'description'   => 'Shares its id with a campaign.',
				'advertiser_id' => $advertiser->id,
				'status'        => 'pending',
			)
		);
		$this->assertNotWPError( $classified );
		$id = (int) $classified->id;

		$this->assertNotWPError( Credits_Bridge::charge( $advertiser->id, 5, $id, 'Classified listing: Standard package', false, Revenue_Ledger::SOURCE_CLASSIFIED_LISTING ) );
		$this->assertNotWPError( Credits_Bridge::charge( $advertiser->id, 200, $id, 'Campaign budget reservation', false, Revenue_Ledger::SOURCE_CAMPAIGN_RESERVE ) );

		$before = (float) Credits_Bridge::get_balance( $advertiser->id );

		$this->assertTrue( Classified_Manager::get_instance()->reject( $id, 'collision' ) );

		$this->assertSame(
			5.0,
			round( (float) Credits_Bridge::get_balance( $advertiser->id ) - $before, 2 ),
			'Only the listing charge comes back; the campaign with the same id keeps its reservation.'
		);
	}
}
