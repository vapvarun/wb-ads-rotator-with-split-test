<?php
/**
 * A search/filter with no matches must not read like an empty product
 * ("No advertisers yet") when 20+ rows exist - it should say the filter
 * matched nothing and offer a way to clear it (10339876480 step 15).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Admin\Advertisers_List_Table;
use WBAM_Pro\Admin\List_Empty_States;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

require_once WBAM_PRO_PATH . 'includes/Admin/class-list-empty-states.php';

class Test_Filtered_Empty_Lists extends Pro_Test_Case {

	/**
	 * The site-wide test bootstrap sets $_SERVER['REQUEST_URI'] once; other
	 * tests rely on it staying set for the rest of the run, so this restores
	 * the original value instead of unsetting it.
	 *
	 * @var string|null
	 */
	private $original_request_uri;

	public function set_up(): void {
		parent::set_up();
		$this->original_request_uri = $_SERVER['REQUEST_URI'] ?? null;
	}

	public function tear_down(): void {
		unset( $_GET['s'], $_GET['status'], $_GET['category'], $_GET['listing_type'] );
		if ( null === $this->original_request_uri ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->original_request_uri;
		}
		parent::tear_down();
	}

	public function test_is_filtered_is_false_with_no_query_args(): void {
		$this->assertFalse( List_Empty_States::is_filtered() );
	}

	public function test_is_filtered_is_true_for_a_search_term(): void {
		$_GET['s'] = 'nothing matches this';
		$this->assertTrue( List_Empty_States::is_filtered() );
	}

	public function test_is_filtered_ignores_the_all_status(): void {
		$_GET['status'] = 'all';
		$this->assertFalse( List_Empty_States::is_filtered() );

		$_GET['status'] = 'pending';
		$this->assertTrue( List_Empty_States::is_filtered() );
	}

	public function test_advertisers_list_says_no_results_when_a_search_matches_nothing(): void {
		require_once WBAM_PRO_PATH . 'includes/Admin/class-advertisers-list-table.php';
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'toplevel_page_wbam-advertisers' );

		// 20+ real advertisers exist, but the search matches none of them.
		foreach ( range( 1, 20 ) as $i ) {
			$user = self::factory()->user->create( array( 'display_name' => "Advertiser {$i}" ) );
			Advertiser_Manager::get_instance()->get_or_create( $user );
		}
		$_GET['s']              = 'zzz-no-such-advertiser';
		$_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?page=wbam-advertisers&s=zzz-no-such-advertiser';

		$table = new Advertisers_List_Table();
		$table->prepare_items();
		$this->assertCount( 0, $table->items );

		ob_start();
		$table->no_items();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'No results for this filter', $html );
		$this->assertStringContainsString( 'Clear filters', $html );
		$this->assertStringNotContainsString( 'No advertisers yet', $html );

		set_current_screen( 'front' );
	}
}
