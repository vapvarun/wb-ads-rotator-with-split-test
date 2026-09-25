<?php
/**
 * Reviews moderation list: counts on views, search, bulk approve, seller
 * name without a company, listing column.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Admin\Reviews_List_Table;
use WBAM_Pro\Core\Pro_Admin;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

class Test_Reviews_List_Table extends Pro_Test_Case {

	private int $seller;

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'toplevel_page_wbam-reviews' );
		$user         = (int) self::factory()->user->create( array( 'display_name' => 'Sam Seller' ) );
		$this->seller = (int) Advertiser_Manager::get_instance()->get_or_create( $user )->id;
	}

	public function tear_down(): void {
		unset( $_GET['s'], $_GET['status'], $_GET['action'], $_GET['review_ids'], $_GET['_wpnonce'], $_REQUEST['_wpnonce'], $_GET['page'] );
		set_current_screen( 'front' );
		parent::tear_down();
	}

	private function review( string $status, string $comment ): int {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'wbam_reviews',
			array(
				'advertiser_id'    => $this->seller,
				'reviewer_user_id' => self::factory()->user->create( array( 'display_name' => 'Rita Reviewer' ) ),
				'rating'           => 4,
				'comment'          => $comment,
				'status'           => $status,
			)
		);
		return (int) $wpdb->insert_id;
	}

	private function table(): Reviews_List_Table {
		$table = new Reviews_List_Table();
		$table->prepare_items();
		return $table;
	}

	public function test_views_count_and_seller_falls_back_to_their_name(): void {
		$this->review( 'pending', 'Great bike' );
		$this->review( 'approved', 'Fast reply' );

		$table = $this->table();
		$views = ( new \ReflectionMethod( $table, 'get_views' ) )->invoke( $table );

		$this->assertStringContainsString( '(1)', $views['pending'] );
		$this->assertStringContainsString( '(2)', $views['all'] );
		$this->assertStringContainsString( 'Sam Seller', $table->column_seller( $table->items[0] ) );
	}

	public function test_search_matches_the_comment(): void {
		$this->review( 'pending', 'Great bike' );
		$this->review( 'pending', 'Slow reply' );

		$_GET['s'] = 'bike';
		$this->assertCount( 1, $this->table()->items );
	}

	public function test_bulk_approve_approves_every_selected_review(): void {
		$ids = array( $this->review( 'pending', 'One' ), $this->review( 'pending', 'Two' ) );

		$_GET['page']       = 'wbam-reviews';
		$_GET['action']     = 'approve_review';
		$_GET['review_ids'] = array_map( 'strval', $ids );
		$_GET['_wpnonce']   = wp_create_nonce( 'bulk-reviews' );
		$_REQUEST['_wpnonce'] = $_GET['_wpnonce'];

		add_filter(
			'wp_redirect',
			static function () {
				throw new \RuntimeException( 'redirected' );
			}
		);
		try {
			( new \ReflectionMethod( Pro_Admin::class, 'handle_review_actions' ) )->invoke( new Pro_Admin() );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirected', $e->getMessage() );
		}

		$_GET['status'] = 'approved';
		$this->assertCount( 2, $this->table()->items );
	}
}
