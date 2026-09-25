<?php
/**
 * The twice-daily low-balance cron dispatched a hook nothing listened to.
 * Low-balance alerts are sent once, from the debit hook
 * (Credits_Bridge::maybe_fire_low_balance), so the cron is retired and the
 * 4.3.6 upgrade clears the event left on existing sites.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Cron_Manager;
use WBAM_Pro\Core\Installer;

class Test_Low_Balance_Cron_Retired extends Pro_Test_Case {

	public function test_cron_is_not_scheduled_and_upgrade_clears_the_orphan(): void {
		wp_schedule_event( time(), 'twicedaily', 'wbam_check_low_balances' );

		$method = new \ReflectionMethod( Installer::class, 'upgrade_to_4_3_6' );
		$method->setAccessible( true );
		$method->invoke( null );
		Cron_Manager::get_instance()->schedule_all();

		$this->assertFalse( wp_next_scheduled( 'wbam_check_low_balances' ) );
		$this->assertArrayNotHasKey( 'wbam_check_low_balances', Cron_Manager::get_instance()->get_scheduled_jobs() );
		$this->assertTrue( version_compare( Installer::DB_VERSION, '4.3.6', '>=' ) );
	}
}
