<?php
/**
 * A cloaked-link click stores who and from where, and crawlers do not count.
 *
 * Card 10365882213: the /go redirect inserted only link_id and clicked_at, so
 * unique clicks and referrers had nothing to read, and link-preview bots and
 * HEAD probes raised the click count. Card 10365882066: rel="sponsored" is
 * ticked for a new link.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Modules\Links\Link_Manager;

class Test_Link_Click_Recording extends \WP_UnitTestCase {

	private Link_Manager $manager;

	private int $link_id = 0;

	public function set_up(): void {
		parent::set_up();
		$this->manager = Link_Manager::get_instance();
		$this->link_id = (int) $this->manager->create(
			array(
				'name'            => 'Click recording',
				'destination_url' => 'https://example.com/dest',
			)
		);
	}

	public function tear_down(): void {
		unset( $_SERVER['HTTP_USER_AGENT'], $_SERVER['HTTP_REFERER'], $_SERVER['REQUEST_METHOD'] );
		parent::tear_down();
	}

	private function click_rows(): array {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wbam_link_clicks WHERE link_id = %d", $this->link_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public function test_browser_click_stores_visitor_hash_and_referrer(): void {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Chrome/120';
		$_SERVER['HTTP_REFERER']    = 'http://example.org/post-x';
		$_SERVER['REQUEST_METHOD']  = 'GET';

		$this->manager->increment_clicks( $this->link_id );

		$rows = $this->click_rows();
		$this->assertCount( 1, $rows );
		$this->assertSame( 64, strlen( $rows[0]->visitor_hash ) );
		$this->assertSame( 'http://example.org/post-x', $rows[0]->referrer );
	}

	public function test_bot_and_head_requests_are_not_counted(): void {
		$_SERVER['REQUEST_METHOD']  = 'GET';
		$_SERVER['HTTP_USER_AGENT'] = 'Googlebot/2.1';
		$this->manager->increment_clicks( $this->link_id );

		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Chrome/120';
		$_SERVER['REQUEST_METHOD']  = 'HEAD';
		$this->manager->increment_clicks( $this->link_id );

		$this->assertCount( 0, $this->click_rows() );
		$this->assertSame( 0, (int) $this->manager->get( $this->link_id )->click_count );
	}

	public function test_new_link_defaults_to_sponsored(): void {
		$this->assertTrue( (bool) $this->manager->get( $this->link_id )->sponsored );
	}
}
