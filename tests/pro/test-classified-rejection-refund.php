<?php
/**
 * Classified rejection refunds what was actually charged.
 *
 * Regression guard for Basecamp card 10143226336: rejecting a paid
 * classified left the advertiser's wallet untouched. reject() skipped
 * every listing whose listing_type was "standard" — but package
 * listings ARE standard-type and paid — and the refund helper
 * re-derived prices from two hardcoded settings instead of the ledger,
 * so amounts drifted whenever the owner edited prices after purchase
 * and upgrades were never refunded. The refund now nets the ledger
 * rows tied to the classified: exact, package-agnostic, idempotent.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Tests\Helpers\Factory;
use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Core\Revenue_Ledger;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;

class Test_Classified_Rejection_Refund extends Pro_Test_Case {

	public function set_up(): void {
		parent::set_up();

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );
	}

	/**
	 * @return array{user:int, advertiser:object, classified:object}
	 */
	private function make_charged_pending_classified( float $charge_amount ): array {
		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		Factory::topup_user( $user, 100000 );

		$classified = Classified_Manager::get_instance()->create(
			array(
				'title'         => 'Refund regression listing',
				'description'   => 'Paid via a listing package.',
				'advertiser_id' => $advertiser->id,
				'status'        => 'pending',
			)
		);
		$this->assertNotWPError( $classified );

		if ( $charge_amount > 0 ) {
			$charge = Credits_Bridge::charge( $advertiser->id, $charge_amount, (int) $classified->id, 'Classified listing: Standard package', false, Revenue_Ledger::SOURCE_CLASSIFIED_LISTING );
			$this->assertNotWPError( $charge );
		}

		return array(
			'user'       => $user,
			'advertiser' => $advertiser,
			'classified' => $classified,
		);
	}

	public function test_rejecting_a_paid_standard_listing_refunds_the_charge(): void {
		$ctx    = $this->make_charged_pending_classified( 5 );
		$before = \Wbcom\Credits\Credits::get_balance( 'wbam-pro', $ctx['user'] );

		$result = Classified_Manager::get_instance()->reject( (int) $ctx['classified']->id, 'regression' );

		$this->assertTrue( $result );
		$this->assertSame(
			$before + 500,
			\Wbcom\Credits\Credits::get_balance( 'wbam-pro', $ctx['user'] ),
			'The $5 package charge must come back in full - standard listings are paid listings.'
		);
	}

	public function test_refund_covers_upgrades_charged_to_the_same_listing(): void {
		$ctx = $this->make_charged_pending_classified( 5 );
		Credits_Bridge::charge( $ctx['advertiser']->id, 3, (int) $ctx['classified']->id, 'Classified listing upgrade: featured', false, Revenue_Ledger::SOURCE_CLASSIFIED_UPGRADE );
		$before = \Wbcom\Credits\Credits::get_balance( 'wbam-pro', $ctx['user'] );

		Classified_Manager::get_instance()->reject( (int) $ctx['classified']->id, 'regression' );

		$this->assertSame(
			$before + 800,
			\Wbcom\Credits\Credits::get_balance( 'wbam-pro', $ctx['user'] ),
			'Package + upgrade were both charged to this listing, so both must be refunded.'
		);
	}

	public function test_unpaid_listing_gets_no_refund(): void {
		$ctx    = $this->make_charged_pending_classified( 0 );
		$before = \Wbcom\Credits\Credits::get_balance( 'wbam-pro', $ctx['user'] );

		Classified_Manager::get_instance()->reject( (int) $ctx['classified']->id, 'regression' );

		$this->assertSame( $before, \Wbcom\Credits\Credits::get_balance( 'wbam-pro', $ctx['user'] ) );
	}

	public function test_refund_is_idempotent_once_settled(): void {
		$ctx = $this->make_charged_pending_classified( 5 );
		$manager = Classified_Manager::get_instance();
		$manager->reject( (int) $ctx['classified']->id, 'regression' );
		$settled = \Wbcom\Credits\Credits::get_balance( 'wbam-pro', $ctx['user'] );

		$refund = new \ReflectionMethod( Classified_Manager::class, 'process_rejection_refund' );
		$refund->setAccessible( true );
		$second = $refund->invoke( $manager, $manager->get( (int) $ctx['classified']->id ) );

		$this->assertFalse( $second, 'A settled listing must not refund twice.' );
		$this->assertSame( $settled, \Wbcom\Credits\Credits::get_balance( 'wbam-pro', $ctx['user'] ) );
	}
}
