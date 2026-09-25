<?php
/**
 * Admin Campaigns list at scale: distinct package campaign names, the name
 * opens the campaign, every non-empty status has a view, search reaches the
 * advertiser, a page of advertisers loads in one query, and a flat campaign
 * does not read "Spent 0".
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Admin\Campaigns_List_Table;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Campaigns\Campaign_Manager;
use WBAM_Pro\Modules\Packages\Package_Manager;

class Test_Campaigns_List_Scale extends Pro_Test_Case {

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'toplevel_page_wbam-campaigns' );
	}

	public function tear_down(): void {
		unset( $_GET['s'], $_GET['status'] );
		set_current_screen( 'front' );
		parent::tear_down();
	}

	private function advertiser( string $company ): int {
		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		Advertiser_Manager::get_instance()->update( $advertiser->id, array( 'company_name' => $company ) );
		return (int) $advertiser->id;
	}

	private function package(): object {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'wbam_packages',
			array(
				'name'              => 'Starter',
				'price'             => 49.00,
				'pricing_model'     => 'flat',
				'duration_days'     => 30,
				'requires_approval' => 1,
				'status'            => 'active',
				'created_at'        => current_time( 'mysql' ),
			)
		);
		return Package_Manager::get_instance()->get( (int) $wpdb->insert_id );
	}

	private function campaign( int $advertiser_id, array $data = array() ): int {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'wbam_campaigns',
			array_merge(
				array(
					'advertiser_id' => $advertiser_id,
					'name'          => 'Campaign ' . wp_rand(),
					'status'        => 'active',
					'pricing_model' => 'cpm',
					'budget'        => 100,
					'created_at'    => current_time( 'mysql' ),
				),
				$data
			)
		);
		return (int) $wpdb->insert_id;
	}

	private function render(): array {
		global $wpdb;
		$table  = new Campaigns_List_Table();
		$before = $wpdb->num_queries;
		$table->prepare_items();
		foreach ( $table->items as $item ) {
			$table->column_advertiser( $item );
			$table->column_name( $item );
		}
		return array( $table, $wpdb->num_queries - $before );
	}

	public function test_package_campaigns_are_named_after_their_ad(): void {
		$package = $this->package();
		$ad_id   = self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_title' => 'Summer Sale' ) );

		$campaign = Campaign_Manager::get_instance()->create_from_package( $this->advertiser( 'Acme' ), $ad_id, $package );

		$this->assertSame( 'Summer Sale - Starter', $campaign->name );
	}

	public function test_name_links_to_the_campaign(): void {
		$id = $this->campaign( $this->advertiser( 'Acme' ) );
		list( $table ) = $this->render();

		$this->assertMatchesRegularExpression( '/<a[^>]+campaign_id=' . $id . '[^>]*>[^<]*Campaign/', $table->column_name( $table->items[0] ) );
	}

	public function test_page_query_count_does_not_grow_with_advertisers(): void {
		foreach ( range( 1, 2 ) as $i ) {
			$this->campaign( $this->advertiser( "Small {$i}" ) );
		}
		list( , $small ) = $this->render();

		foreach ( range( 1, 10 ) as $i ) {
			$this->campaign( $this->advertiser( "Big {$i}" ) );
		}
		list( $table, $big ) = $this->render();

		$this->assertCount( 12, $table->items );
		$this->assertSame( $small, $big, 'The advertiser column must not query per row.' );
	}

	public function test_search_matches_the_advertiser_company(): void {
		$this->campaign( $this->advertiser( 'Acme Widgets' ) );
		$this->campaign( $this->advertiser( 'Globex' ) );

		$_GET['s'] = 'globex';
		list( $table ) = $this->render();

		$this->assertCount( 1, $table->items );
		$this->assertSame( 1, $table->get_pagination_arg( 'total_items' ) );
	}

	public function test_cancelled_and_draft_have_views(): void {
		$advertiser = $this->advertiser( 'Acme' );
		$this->campaign( $advertiser, array( 'status' => 'cancelled' ) );
		$this->campaign( $advertiser, array( 'status' => 'draft' ) );

		$table = new Campaigns_List_Table();
		$views = ( new \ReflectionMethod( $table, 'get_views' ) )->invoke( $table );

		$this->assertArrayHasKey( 'cancelled', $views );
		$this->assertArrayHasKey( 'draft', $views );
	}

	public function test_flat_campaign_spend_reads_flat_fee(): void {
		$table = new Campaigns_List_Table();
		$html  = $table->column_spent( (object) array( 'pricing_model' => 'flat', 'spent' => 0, 'budget' => 49 ) );

		$this->assertStringContainsString( 'Flat fee', $html );
	}
}
