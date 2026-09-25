<?php
/**
 * First-run wizard hand-off (card 10342783654, owner decision 3): Free's
 * wizard defers to Pro's when Pro is active and hasn't finished its own
 * first run, instead of running a second, competing wizard.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Admin\Setup_Wizard;
use WP_UnitTestCase;

class Test_First_Run_Wizard_Handoff extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( 'wbam_pro_setup_complete' );
		parent::tear_down();
	}

	public function test_pending_when_pro_not_yet_finished_its_wizard(): void {
		if ( ! defined( 'WBAM_PRO_VERSION' ) ) {
			$this->markTestSkipped( 'Pro not loaded for this run.' );
		}

		delete_option( 'wbam_pro_setup_complete' );
		$this->assertTrue( Setup_Wizard::pro_wizard_pending() );
	}

	public function test_not_pending_once_pro_wizard_is_complete(): void {
		if ( ! defined( 'WBAM_PRO_VERSION' ) ) {
			$this->markTestSkipped( 'Pro not loaded for this run.' );
		}

		update_option( 'wbam_pro_setup_complete', true );
		$this->assertFalse( Setup_Wizard::pro_wizard_pending() );
	}

	public function test_not_pending_when_pro_is_not_active(): void {
		if ( defined( 'WBAM_PRO_VERSION' ) ) {
			$this->markTestSkipped( 'Pro is loaded for this run; nothing to assert about its absence.' );
		}

		$this->assertFalse( Setup_Wizard::pro_wizard_pending() );
	}
}
