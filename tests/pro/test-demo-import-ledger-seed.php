<?php
/**
 * Demo importer seeds the Credits SDK ledger, not the legacy column.
 *
 * Regression guard for Basecamp card 10163569637: the importer wrote
 * only the legacy wbam_advertisers.balance column (its transaction
 * seeder targeted a table removed in the SDK migration and always
 * skipped), so demo advertisers were promised wallet balances the
 * portal showed as 0 credits with no history. create_transactions()
 * now seeds the SDK ledger — the source of truth every wallet surface
 * reads — netting to the promised balance, idempotently.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

class Test_Demo_Import_Ledger_Seed extends Pro_Test_Case {

	private function seed_via_generator( int $advertiser_id, float $promised_balance ): void {
		if ( ! defined( 'WBAM_DEMO_DATA_INCLUDED' ) ) {
			define( 'WBAM_DEMO_DATA_INCLUDED', true );
		}
		require_once WBAM_PRO_PATH . 'demo-data-setup.php';

		$generator = new \WBAM_Demo_Data_Generator();

		$advertisers = new \ReflectionProperty( \WBAM_Demo_Data_Generator::class, 'advertisers' );
		$advertisers->setAccessible( true );
		$advertisers->setValue( $generator, array( 0 => array( 'balance' => $promised_balance ) ) );

		$ids = new \ReflectionProperty( \WBAM_Demo_Data_Generator::class, 'advertiser_ids' );
		$ids->setAccessible( true );
		$ids->setValue( $generator, array( 0 => $advertiser_id ) );

		// Credits are seeded only onto accounts the importer created.
		$register = new \ReflectionMethod( \WBAM_Demo_Data_Generator::class, 'register_demo_id' );
		$register->invoke( $generator, 'advertisers', $advertiser_id );
		$register->invoke( $generator, 'users', Credits_Bridge::get_user_id( $advertiser_id ) );

		$method = new \ReflectionMethod( \WBAM_Demo_Data_Generator::class, 'create_transactions' );
		$method->setAccessible( true );
		ob_start();
		$method->invoke( $generator );
		ob_end_clean();
	}

	public function test_seed_lands_the_promised_balance_in_the_sdk_ledger(): void {
		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );

		$this->seed_via_generator( (int) $advertiser->id, 500.0 );

		$this->assertSame(
			500.0,
			(float) Credits_Bridge::get_balance( (int) $advertiser->id ),
			'The wallet reads the SDK ledger, so the promised demo balance must live there.'
		);
	}

	public function test_no_credits_for_an_account_the_importer_did_not_create(): void {
		if ( ! defined( 'WBAM_DEMO_DATA_INCLUDED' ) ) {
			define( 'WBAM_DEMO_DATA_INCLUDED', true );
		}
		require_once WBAM_PRO_PATH . 'demo-data-setup.php';

		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );

		$generator = new \WBAM_Demo_Data_Generator();
		foreach ( array( 'advertisers' => array( 0 => array( 'balance' => 500.0 ) ), 'advertiser_ids' => array( 0 => (int) $advertiser->id ) ) as $prop => $value ) {
			$ref = new \ReflectionProperty( \WBAM_Demo_Data_Generator::class, $prop );
			$ref->setValue( $generator, $value );
		}
		$method = new \ReflectionMethod( \WBAM_Demo_Data_Generator::class, 'create_transactions' );
		ob_start();
		$method->invoke( $generator );
		ob_end_clean();

		$this->assertSame( 0.0, (float) Credits_Bridge::get_balance( (int) $advertiser->id ), 'A reused real account must not be handed demo credits.' );
	}

	public function test_reseed_does_not_double_the_balance(): void {
		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );

		$this->seed_via_generator( (int) $advertiser->id, 500.0 );
		$this->seed_via_generator( (int) $advertiser->id, 500.0 );

		$this->assertSame( 500.0, (float) Credits_Bridge::get_balance( (int) $advertiser->id ), 'Re-import must not stack another top-up on an advertiser with ledger history.' );
	}
}
