<?php
/**
 * The weekly performance report sent the first 100 opted-in advertisers
 * and dropped the rest, every week. A full batch now queues the next one
 * after the last advertiser id it handled, until every subscriber is done.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Notifications\Email_Notifications;

class Test_Weekly_Report_Batches extends Pro_Test_Case {

	public function test_full_batch_queues_the_rest(): void {
		$subscribers = static function () {
			return array( 103, 101, 102 );
		};
		$batch_size  = static function () {
			return 2;
		};
		$counts      = array();
		$count_batch = static function ( $count ) use ( &$counts ) {
			$counts[] = $count;
		};
		add_filter( 'wbam_pro_weekly_report_subscribers', $subscribers );
		add_filter( 'wbam_pro_weekly_report_batch_size', $batch_size );
		add_action( 'wbam_pro_weekly_reports_batch_complete', $count_batch );

		$notifications = Email_Notifications::get_instance();
		$notifications->send_weekly_reports_batch();

		$this->assertNotFalse( wp_next_scheduled( 'wbam_pro_do_send_weekly_reports', array( 102 ) ), 'Advertisers after the first batch must be queued.' );

		$notifications->send_weekly_reports_batch( 102 );

		$this->assertSame( array( 2, 1 ), $counts );
		$this->assertFalse( wp_next_scheduled( 'wbam_pro_do_send_weekly_reports', array( 103 ) ), 'The last batch queues nothing.' );

		remove_filter( 'wbam_pro_weekly_report_subscribers', $subscribers );
		remove_filter( 'wbam_pro_weekly_report_batch_size', $batch_size );
		remove_action( 'wbam_pro_weekly_reports_batch_complete', $count_batch );
		wp_unschedule_hook( 'wbam_pro_do_send_weekly_reports' );
	}
}
