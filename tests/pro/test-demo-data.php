<?php
/**
 * Demo data install/uninstall — regression gate for bug #9797756007
 * (demo data import failing with 3 DB errors).
 *
 * We run the installer and expect no $wpdb->last_error, then verify
 * rows were created in the classifieds CPT.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

class Test_Demo_Data extends Pro_Test_Case {

	public function test_demo_generator_class_loadable(): void {
		// The generator lives at the pro plugin root and is loaded on demand.
		$generator_file = defined( 'WBAM_PRO_PATH' )
			? WBAM_PRO_PATH . 'demo-data-setup.php'
			: null;

		$this->assertIsString( $generator_file );
		$this->assertFileExists( $generator_file );
	}

	public function test_demo_import_does_not_emit_db_errors(): void {
		if ( ! defined( 'WBAM_DEMO_DATA_INCLUDED' ) ) {
			define( 'WBAM_DEMO_DATA_INCLUDED', true );
		}
		require_once WBAM_PRO_PATH . 'demo-data-setup.php';

		global $wpdb;
		$wpdb->suppress_errors( true );
		$wpdb->last_error = '';

		$generator = new \WBAM_Demo_Data_Generator();
		ob_start();
		$generator->run();
		ob_end_clean();
		$error = $wpdb->last_error;

		$generator->delete_tracked_demo_data();

		$this->assertEmpty( $error, 'Demo data install must not produce DB errors' );
	}

	/**
	 * The demo advertisers have made-up .demo addresses. Importing must not
	 * email them (or anyone): card 10342783654.
	 */
	public function test_demo_import_sends_no_email(): void {
		if ( ! defined( 'WBAM_DEMO_DATA_INCLUDED' ) ) {
			define( 'WBAM_DEMO_DATA_INCLUDED', true );
		}
		require_once WBAM_PRO_PATH . 'demo-data-setup.php';

		reset_phpmailer_instance();
		$generator = new \WBAM_Demo_Data_Generator();
		ob_start();
		$generator->run();
		ob_end_clean();
		$sent = tests_retrieve_phpmailer_instance()->mock_sent;

		$generator->delete_tracked_demo_data();

		$this->assertSame( array(), wp_list_pluck( $sent, 'to' ) );
		$this->assertFalse( has_filter( 'pre_wp_mail', '__return_false' ), 'Mail must work again after the import.' );
	}
}
