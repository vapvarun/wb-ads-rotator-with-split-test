<?php
/**
 * Basecamp card 10342784279 step 4: a direct Stripe/PayPal checkout that
 * has not credited the balance yet (webhook not landed, buyer has not
 * claimed on return) must show as pending in the portal wallet - not as
 * credited, and not as missing.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use Wbcom\Credits\Gateways\Pending_Checkouts;
use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

class Test_Credits_Pending_Gateway_Checkout extends Pro_Test_Case {

	private object $advertiser;

	public function set_up(): void {
		parent::set_up();

		$user             = (int) self::factory()->user->create();
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
	}

	public function tear_down(): void {
		Pending_Checkouts::reset_for_tests( Credits_Bridge::SLUG );
		parent::tear_down();
	}

	public function test_pending_stripe_checkout_shows_in_wallet(): void {
		Pending_Checkouts::put(
			Credits_Bridge::SLUG,
			'cs_test_abc123',
			array(
				'gateway'     => 'stripe',
				'user_id'     => $this->advertiser->user_id,
				'credits'     => 25,
				'price_cents' => 2500,
				'currency'    => 'USD',
			)
		);

		$pending = Credits_Bridge::get_pending_purchases( $this->advertiser->id );

		$this->assertCount( 1, $pending );
		$this->assertSame( 25.0, $pending[0]['amount'] );
		$this->assertStringContainsString( 'ABC123', $pending[0]['order_number'] );
	}

	public function test_credited_checkout_does_not_show_as_pending(): void {
		Pending_Checkouts::put(
			Credits_Bridge::SLUG,
			'cs_test_done',
			array(
				'gateway'     => 'stripe',
				'user_id'     => $this->advertiser->user_id,
				'credits'     => 10,
				'price_cents' => 1000,
				'currency'    => 'USD',
			)
		);
		// Crediting the checkout forgets the pending entry (mirrors what
		// Abstract_Gateway::process_checkout_completed() does once it has
		// written the ledger row) - the row must then vanish from "pending",
		// not linger and double-count against the now-credited balance.
		Pending_Checkouts::forget( Credits_Bridge::SLUG, 'cs_test_done' );

		$this->assertSame( array(), Credits_Bridge::get_pending_purchases( $this->advertiser->id ) );
	}

	public function test_another_advertisers_pending_checkout_is_not_shown(): void {
		$other_user        = (int) self::factory()->user->create();
		$other_advertiser  = Advertiser_Manager::get_instance()->get_or_create( $other_user );

		Pending_Checkouts::put(
			Credits_Bridge::SLUG,
			'cs_test_other',
			array(
				'gateway'     => 'paypal',
				'user_id'     => $other_advertiser->user_id,
				'credits'     => 40,
				'price_cents' => 4000,
				'currency'    => 'USD',
			)
		);

		$this->assertSame( array(), Credits_Bridge::get_pending_purchases( $this->advertiser->id ) );
	}
}
