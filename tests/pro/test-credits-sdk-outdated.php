<?php
/**
 * With an older Credits SDK loaded (another plugin's copy won), every
 * money path stops, both directions: charges, refunds and top-ups. A
 * refund used to go through while charges were blocked (card 10344652556).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Core\Revenue_Ledger;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

class Test_Credits_Sdk_Outdated extends Pro_Test_Case {

	private function force_ready( ?bool $ready ): void {
		$prop = new \ReflectionProperty( Credits_Bridge::class, 'sdk_ready' );
		$prop->setAccessible( true );
		$prop->setValue( null, $ready );
	}

	public function tear_down(): void {
		$this->force_ready( null );
		parent::tear_down();
	}

	public function test_every_money_path_refuses_when_the_sdk_is_outdated(): void {
		$user       = (int) self::factory()->user->create();
		$advertiser = (int) Advertiser_Manager::get_instance()->get_or_create( $user )->id;
		$this->force_ready( false );

		foreach ( array(
			'charge' => Credits_Bridge::charge( $advertiser, 1, 0, 'x', false, Revenue_Ledger::SOURCE_AD_PACKAGE ),
			'credit' => Credits_Bridge::credit( $advertiser, 1, 0, 'x', Revenue_Ledger::SOURCE_AD_PACKAGE ),
			'topup'  => Credits_Bridge::topup( $advertiser, 1, 'x' ),
			'adjust' => Credits_Bridge::adjust( $advertiser, 1, 'x' ),
		) as $path => $result ) {
			$this->assertWPError( $result, $path );
			$this->assertSame( 'wbam_credits_sdk_outdated', $result->get_error_code(), $path );
		}
		$this->force_ready( null );
		$this->assertSame( 0.0, (float) Credits_Bridge::get_balance( $advertiser ), 'Nothing reached the ledger.' );
	}

	public function test_the_bundled_sdk_is_ready(): void {
		$this->force_ready( null );
		$this->assertTrue( Credits_Bridge::sdk_money_ready() );
	}
}
