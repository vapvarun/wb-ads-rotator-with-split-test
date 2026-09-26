<?php
/**
 * Admin Classifieds list and CSV export at scale: batched advertiser/term
 * lookups so the query count does not grow per row, and the CSV export
 * downloads a real file instead of fataling on get_classifieds()'s
 * ['items', 'total'] return shape.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Admin\Classifieds_List_Table;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;

class Test_Classifieds_List_Scale extends Pro_Test_Case {

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'wbam-ad_page_wbam-classifieds' );
	}

	public function tear_down(): void {
		unset( $_GET['status'], $_GET['s'], $_GET['category'] );
		set_current_screen( 'front' );
		parent::tear_down();
	}

	private function advertiser( string $company ): int {
		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		Advertiser_Manager::get_instance()->update( $advertiser->id, array( 'company_name' => $company ) );
		return (int) $advertiser->id;
	}

	private function classified( int $advertiser_id ): int {
		global $wpdb;
		$post_id = self::factory()->post->create( array( 'post_type' => 'wbam-classified', 'post_title' => 'Item ' . wp_rand() ) );
		$wpdb->insert(
			$wpdb->prefix . 'wbam_classifieds',
			array(
				'post_id'       => $post_id,
				'advertiser_id' => $advertiser_id,
				'status'        => 'active',
				'listing_type'  => 'standard',
				'price'         => 10,
				'price_type'    => 'fixed',
				'created_at'    => current_time( 'mysql' ),
			)
		);
		return (int) $wpdb->insert_id;
	}

	private function render_query_count(): int {
		global $wpdb;
		$table  = new Classifieds_List_Table();
		$before = $wpdb->num_queries;
		$table->prepare_items();
		foreach ( $table->items as $item ) {
			$table->column_advertiser( $item );
			$table->column_title( $item );
		}
		return $wpdb->num_queries - $before;
	}

	public function test_page_query_count_does_not_grow_with_rows(): void {
		foreach ( range( 1, 2 ) as $i ) {
			$this->classified( $this->advertiser( "Small {$i}" ) );
		}
		$small = $this->render_query_count();

		foreach ( range( 1, 15 ) as $i ) {
			$this->classified( $this->advertiser( "Big {$i}" ) );
		}
		$big = $this->render_query_count();

		$this->assertLessThanOrEqual(
			$small + 2,
			$big,
			'The classifieds list must batch advertiser and term lookups, not query per row (this was ~3 queries per row).'
		);
	}

	/**
	 * handle_classifieds_export() looped `foreach ( $classifieds as $classified )`
	 * directly over get_classifieds()'s return - which is
	 * `['items' => [...], 'total' => N]`, not a list of rows - so the loop's
	 * first iteration handed $classified an array (or later, an int) and
	 * `->get_categories()` fataled. This drives the same code path
	 * (get_classifieds() -> loop -> $classified->get_categories()/get_advertiser())
	 * the export handler runs, batched exactly as it batches.
	 */
	public function test_export_loop_does_not_fatal_on_get_classifieds_shape(): void {
		$this->classified( $this->advertiser( 'Acme Widgets' ) );
		$this->classified( $this->advertiser( 'Globex' ) );

		$manager = Classified_Manager::get_instance();
		$result  = $manager->get_classifieds( array( 'limit' => 500, 'offset' => 0 ) );
		$this->assertArrayHasKey( 'items', $result );
		$this->assertIsArray( $result['items'] );

		$rows = array();
		foreach ( $result['items'] as $classified ) {
			// The exact calls the export loop makes per row; any of these
			// fatals immediately if $classified is the ['items','total']
			// array itself rather than an unwrapped row object.
			$categories = $classified->get_categories();
			$location   = $classified->get_location();
			$advertiser = $classified->get_advertiser();
			$rows[]     = array( $classified->id, $classified->get_title(), $advertiser ? $advertiser->company_name : '' );
			unset( $categories, $location );
		}

		$this->assertCount( 2, $rows );
	}
}
