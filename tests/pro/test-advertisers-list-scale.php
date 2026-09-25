<?php
/**
 * Admin Advertisers list and Add Advertiser at scale.
 *
 * Each row ran a balance query, a total-spent query, a post count and a
 * user load; the name opened the WordPress profile instead of the
 * advertiser; the company repeated the name; the Members view and the
 * status badge used different labels; Add Advertiser printed every user on
 * the site into one select and defaulted a hand-added advertiser to Pending.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Admin\Advertisers_List_Table;
use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Core\Pro_Admin;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

class Test_Advertisers_List_Scale extends Pro_Test_Case {

	public function set_up(): void {
		parent::set_up();
		require_once WBAM_PRO_PATH . 'includes/Admin/class-advertisers-list-table.php';
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'toplevel_page_wbam-advertisers' );
	}

	public function tear_down(): void {
		set_current_screen( 'front' );
		parent::tear_down();
	}

	private function advertiser( string $name, string $company = '' ): object {
		$user       = (int) self::factory()->user->create( array( 'display_name' => $name ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		Advertiser_Manager::get_instance()->update( $advertiser->id, array( 'company_name' => $company ) );
		Credits_Bridge::topup( $advertiser->id, 10, 'Grant' );
		return Advertiser_Manager::get_instance()->get( $advertiser->id );
	}

	private function render(): array {
		global $wpdb;
		$table  = new Advertisers_List_Table();
		$before = $wpdb->num_queries;
		$table->prepare_items();
		foreach ( $table->items as $item ) {
			$table->column_advertiser( $item );
			$table->column_balance( $item );
			$table->column_total_spent( $item );
			$table->column_ads_count( $item );
		}
		return array( $table, $wpdb->num_queries - $before );
	}

	public function test_page_query_count_does_not_grow_with_rows(): void {
		foreach ( range( 1, 2 ) as $i ) {
			$this->advertiser( "Small {$i}" );
		}
		list( , $small ) = $this->render();

		foreach ( range( 1, 10 ) as $i ) {
			$this->advertiser( "Big {$i}" );
		}
		list( $table, $big ) = $this->render();

		$this->assertCount( 12, $table->items );
		$this->assertSame( $small, $big, 'Balance, spent, ad count and user must load per page, not per row.' );
		$this->assertStringContainsString( '10', wp_strip_all_tags( $table->column_balance( $table->items[0] ) ) );
	}

	public function test_name_opens_the_advertiser_and_company_is_not_repeated(): void {
		$same  = $this->advertiser( 'Acme', 'Acme' );
		$other = $this->advertiser( 'Jane', 'Globex' );
		list( $table ) = $this->render();

		foreach ( $table->items as $item ) {
			$html = $table->column_advertiser( $item );
			$this->assertMatchesRegularExpression( '/<a class="row-title" href="[^"]*action=view[^"]*advertiser_id=' . $item->id . '/', $html );
			$this->assertStringNotContainsString( 'user-edit.php', $html );
			if ( (int) $item->id === (int) $same->id ) {
				$this->assertStringNotContainsString( 'wbam-party-company', $html );
			} else {
				$this->assertStringContainsString( 'Globex', $html );
			}
		}
		$this->assertNotSame( $same->id, $other->id );
	}

	public function test_member_view_uses_the_status_label(): void {
		$advertiser = $this->advertiser( 'Member' );
		Advertiser_Manager::get_instance()->update( $advertiser->id, array( 'status' => 'member' ) );

		$table = new Advertisers_List_Table();
		$views = ( new \ReflectionMethod( $table, 'get_views' ) )->invoke( $table );

		$this->assertStringContainsString( 'Member (no ads)', $views['member'] );
	}

	public function test_add_form_lists_a_bounded_set_and_defaults_to_active(): void {
		self::factory()->user->create_many( 55 );

		$admin  = new Pro_Admin();
		$method = new \ReflectionMethod( $admin, 'render_advertiser_form' );
		ob_start();
		$method->invoke( $admin, 0 );
		$html = (string) ob_get_clean();

		preg_match( '/<select name="user_id".*?<\/select>/s', $html, $select );
		$this->assertLessThanOrEqual( 51, substr_count( $select[0], '<option' ) );
		$this->assertStringContainsString( 'id="wbam-user-search"', $html );
		$this->assertMatchesRegularExpression( '/<option value="active"\s+selected/', $html );
	}
}
