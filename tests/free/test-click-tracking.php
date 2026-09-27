<?php
/**
 * Click tracking - regression gate for bug #9788819224
 * (views/clicks not tracking on approved ads).
 *
 * This used to fire do_action( 'wbam_track_click', ... ) and skip when no
 * listener was found, with the message "fix the plugin, not the test". There is
 * no such action to fix. `wbam_track_click` is the name of an admin-ajax
 * action - registered as wp_ajax_wbam_track_click / wp_ajax_nopriv_ - so
 * has_action() could never be true and the gate skipped on every run. A gate
 * that always skips protects nothing.
 *
 * Rewritten against Frontend::record_analytics(), the method the AJAX handler
 * calls to persist. Driving the handler itself is not viable here: it answers
 * with wp_send_json_*, which writes to stdout and terminates, and this suite
 * does not run the ajax group. The persistence contract is what the original
 * test was reaching for anyway.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WP_UnitTestCase;
use WBAM\Tests\Helpers\Factory;

class Test_Click_Tracking extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		// A real visitor: bots and admins are not counted (card 10344382999).
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/537.36 Chrome/126.0 Safari/537.36';
	}

	public function tear_down(): void {
		unset( $_SERVER['HTTP_USER_AGENT'] );
		parent::tear_down();
	}

	public function test_bots_and_admins_are_not_counted(): void {
		$ad_id = Factory::make_ad();

		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';
		$this->assertFalse( $this->frontend()->record_analytics( $ad_id, 'impression', 'header' ) );

		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh) Chrome/126.0 Safari/537.36';
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertFalse( $this->frontend()->record_analytics( $ad_id, 'impression', 'header' ) );

		add_filter( 'wbam_count_analytics_event', '__return_true' );
		$this->assertNotFalse( $this->frontend()->record_analytics( $ad_id, 'impression', 'header' ), 'A site can count its own admins.' );
		remove_filter( 'wbam_count_analytics_event', '__return_true' );
	}

	private function frontend(): \WBAM\Frontend\Frontend {
		return new \WBAM\Frontend\Frontend();
	}

	public function test_click_produces_analytics_row(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'wbam_analytics';

		$ad_id  = Factory::make_ad();
		$before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$this->frontend()->record_analytics( $ad_id, 'click', 'before_content' );

		$after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$this->assertGreaterThan( $before, $after, 'Click tracking must persist a row.' );
	}

	/**
	 * A row that does not carry the ad, the event type and the placement cannot
	 * answer "which ad was clicked, where" - the whole point of the table.
	 * Counting rows alone would pass on a row full of nulls.
	 */
	public function test_click_row_records_ad_event_and_placement(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'wbam_analytics';

		$ad_id = Factory::make_ad();
		$this->frontend()->record_analytics( $ad_id, 'click', 'sidebar' );

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE ad_id = %d ORDER BY id DESC LIMIT 1", $ad_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$this->assertNotNull( $row, 'No analytics row was written for the clicked ad.' );
		$this->assertSame( 'click', $row->event_type );
		$this->assertSame( 'sidebar', $row->placement );
	}

	/**
	 * Impressions and clicks share the table and are told apart by event_type
	 * alone. If that column stopped being written correctly every report built
	 * on it would silently conflate the two.
	 */
	public function test_impression_and_click_are_distinguishable(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'wbam_analytics';

		$ad_id = Factory::make_ad();
		$this->frontend()->record_analytics( $ad_id, 'impression', 'footer' );
		$this->frontend()->record_analytics( $ad_id, 'click', 'footer' );

		$clicks = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE ad_id = %d AND event_type = 'click'", $ad_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		$impressions = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE ad_id = %d AND event_type = 'impression'", $ad_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$this->assertSame( 1, $clicks, 'Exactly one click should be recorded.' );
		$this->assertSame( 1, $impressions, 'Exactly one impression should be recorded.' );
	}
}
