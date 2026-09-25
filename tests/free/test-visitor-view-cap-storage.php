<?php
/**
 * The per-visitor daily view cap lives in one cookie, not in wp_options.
 *
 * Every rendered ad used to write two wbam_freq_* transient rows per
 * visitor per day, even ads with no cap, and the cookie meant to carry the
 * count never parsed because its JSON was HTML-escaped (Basecamp card
 * 10342824516).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Modules\Targeting\Frequency_Manager;
use WBAM\Tests\Helpers\Factory;
use WP_UnitTestCase;

class Test_Visitor_View_Cap_Storage extends WP_UnitTestCase {

	public function tear_down(): void {
		unset( $_COOKIE[ Frequency_Manager::COOKIE_NAME ] );
		parent::tear_down();
	}

	public function test_only_capped_ads_are_counted_and_only_in_the_cookie(): void {
		global $wpdb;

		$uncapped = Factory::make_ad();
		$capped   = Factory::make_ad();
		update_post_meta( $capped, '_wbam_session_limit', 1 );

		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
		unset( $_COOKIE[ Frequency_Manager::COOKIE_NAME ] );

		$options = static function () use ( $wpdb ): int {
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '%wbam\\_freq\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		};
		$before = $options();

		$manager = new Frequency_Manager();
		$manager->on_ad_output( '<div>ad</div>', $uncapped );
		$manager->on_ad_output( '<div>ad</div>', $capped );

		ob_start();
		$manager->set_view_cookie();
		$footer = (string) ob_get_clean();

		$this->assertSame( $before, $options(), 'A view must not write option rows.' );

		// Next request: the browser sends back what the footer script set.
		$this->assertSame( 1, preg_match( '/wbam_ad_views=([^;"]+)/', $footer, $match ), 'The footer must set the view cookie.' );
		$_COOKIE[ Frequency_Manager::COOKIE_NAME ] = rawurldecode( $match[1] );

		$next = new Frequency_Manager();
		$this->assertSame( 0, $next->get_ad_views( $uncapped ), 'An ad with no cap is not counted.' );
		$this->assertSame( 1, $next->get_ad_views( $capped ) );
		$this->assertFalse( $next->can_show_ad( $capped ), 'A capped ad stops once the visitor reached its daily limit.' );
	}
}
