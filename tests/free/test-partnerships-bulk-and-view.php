<?php
/**
 * Partnerships: bulk moderation, and a detail view that shows no IP hash
 * and one primary button.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Modules\Links\Partnership_Admin;
use WBAM\Modules\Links\Partnership_Manager;
use WP_UnitTestCase;

class Test_Partnerships_Bulk_And_View extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
	}

	public function tear_down(): void {
		unset( $_GET['page'], $_GET['partnership_ids'], $_GET['bulk_action'], $_GET['_wpnonce'], $_REQUEST['_wpnonce'], $_GET['view'] );
		parent::tear_down();
	}

	private function inquiry( string $name ): int {
		$p = Partnership_Manager::get_instance()->create(
			array(
				'name'             => $name,
				'email'            => strtolower( $name ) . '@example.com',
				'website_url'      => 'https://example.com/' . $name,
				'partnership_type' => 'paid_link',
				'message'          => 'Hello',
			)
		);
		return (int) ( is_object( $p ) ? $p->id : $p );
	}

	public function test_bulk_accept_accepts_every_selected_inquiry(): void {
		$ids = array( $this->inquiry( 'Ann' ), $this->inquiry( 'Bob' ) );

		$_GET['page']            = 'wbam-partnerships';
		$_GET['bulk_action']     = 'accept';
		$_GET['partnership_ids'] = array_map( 'strval', $ids );
		$_GET['_wpnonce']        = wp_create_nonce( 'bulk-partnerships' );
		$_REQUEST['_wpnonce']    = $_GET['_wpnonce'];

		add_filter(
			'wp_redirect',
			static function () {
				throw new \RuntimeException( 'redirected' );
			}
		);
		try {
			Partnership_Admin::get_instance()->handle_actions();
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirected', $e->getMessage() );
		}

		foreach ( $ids as $id ) {
			$this->assertSame( 'accepted', Partnership_Manager::get_instance()->get( $id )->status );
		}
	}

	public function test_detail_view_hides_the_ip_hash_and_has_one_primary_button(): void {
		$id = $this->inquiry( 'Cy' );

		$_GET['view'] = (string) $id;
		ob_start();
		Partnership_Admin::get_instance()->render_page();
		$html = (string) ob_get_clean();

		$this->assertDoesNotMatchRegularExpression( '/[a-f0-9]{64}/', $html );
		$this->assertSame( 1, substr_count( $html, 'button-primary' ) );
		$this->assertStringNotContainsString( 'style="background', $html );
	}
}
