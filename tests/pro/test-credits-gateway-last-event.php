<?php
/**
 * Basecamp card 10342784279 step 5: the Credits settings gateway card shows
 * when it last actually received a webhook, so an admin can tell "connected"
 * apart from "actually receiving events".
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use Wbcom\Credits\Gateways\Processed_Events;
use WBAM_Pro\Admin\Credits_Settings;
use WBAM_Pro\Core\Credits_Bridge;

class Test_Credits_Gateway_Last_Event extends Pro_Test_Case {

	private function label( string $gateway_id ): string {
		$method = new \ReflectionMethod( Credits_Settings::class, 'last_event_label' );
		$method->setAccessible( true );

		return $method->invoke( new Credits_Settings(), $gateway_id );
	}

	public function test_no_events_yet(): void {
		$this->assertSame( 'No webhook events received yet.', $this->label( 'stripe' ) );
	}

	public function test_shows_relative_time_after_a_claimed_event(): void {
		Processed_Events::maybe_create_table( Credits_Bridge::PREFIX );
		Processed_Events::claim( Credits_Bridge::SLUG, 'stripe', 'evt_test_123' );

		$this->assertStringContainsString( 'Last webhook event:', $this->label( 'stripe' ) );
		$this->assertStringContainsString( 'ago.', $this->label( 'stripe' ) );
	}

	public function test_a_different_gateways_event_does_not_count(): void {
		Processed_Events::maybe_create_table( Credits_Bridge::PREFIX );
		Processed_Events::claim( Credits_Bridge::SLUG, 'paypal', 'evt_test_456' );

		$this->assertSame( 'No webhook events received yet.', $this->label( 'stripe' ) );
	}
}
