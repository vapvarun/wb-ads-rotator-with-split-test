<?php
/**
 * Ad Folders screen regression tests.
 *
 * Guards the folder-scoped query building and the GROUP BY rail counts that
 * back the Ad Folders admin screen (Pro `Admin\Ad_Folders_Page`). The screen's
 * quick actions reuse `_wbam_enabled` meta and Campaign_Manager::update_status,
 * both covered by their own suites — what this file protects is the mapping
 * from a folder selection to the exact set of ads it acts on.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Admin\Ad_Folders_Page;
use WP_Query;

/**
 * @group pro
 * @group folders
 */
class Test_Ad_Folders_Page extends Pro_Test_Case {

	/**
	 * Call a private method on the page singleton.
	 *
	 * @param string $method Method name.
	 * @param array  $args   Arguments.
	 * @return mixed
	 */
	private function call_private( $method, $args = array() ) {
		$page       = Ad_Folders_Page::get_instance();
		$reflection = new \ReflectionMethod( Ad_Folders_Page::class, $method );
		$reflection->setAccessible( true );

		return $reflection->invokeArgs( $page, $args );
	}

	/**
	 * Create a published ad, optionally linked and tagged.
	 *
	 * @param int    $advertiser_id Advertiser link (0 = none).
	 * @param int    $campaign_id   Campaign link (0 = none).
	 * @param string $tag           Ad tag slug ('' = none).
	 * @return int Post ID.
	 */
	private function make_ad( $advertiser_id = 0, $campaign_id = 0, $tag = '' ) {
		$ad_id = self::factory()->post->create(
			array(
				'post_type'   => 'wbam-ad',
				'post_status' => 'publish',
			)
		);

		if ( $advertiser_id ) {
			update_post_meta( $ad_id, '_wbam_advertiser_id', $advertiser_id );
		}
		if ( $campaign_id ) {
			update_post_meta( $ad_id, '_wbam_campaign_id', $campaign_id );
		}
		if ( '' !== $tag ) {
			wp_set_object_terms( $ad_id, $tag, 'wbam_ad_tag' );
		}

		return $ad_id;
	}

	/**
	 * Selection template with no folder open.
	 *
	 * @param array $overrides Selection overrides.
	 * @return array
	 */
	private function selection( $overrides = array() ) {
		return array_merge(
			array(
				'adv'   => 0,
				'camp'  => 0,
				'tag'   => '',
				'house' => false,
			),
			$overrides
		);
	}

	/**
	 * An advertiser folder must return exactly that advertiser's ads.
	 */
	public function test_advertiser_folder_query_scopes_to_advertiser() {
		$mine  = $this->make_ad( 77 );
		$other = $this->make_ad( 88 );
		$none  = $this->make_ad();

		$args  = $this->call_private( 'build_query_args', array( $this->selection( array( 'adv' => 77 ) ), array( 'fields' => 'ids', 'no_found_rows' => true ) ) );
		$found = ( new WP_Query( $args ) )->posts;

		$this->assertContains( $mine, $found );
		$this->assertNotContains( $other, $found );
		$this->assertNotContains( $none, $found );
	}

	/**
	 * A campaign folder must scope by campaign link, not advertiser link.
	 */
	public function test_campaign_folder_query_scopes_to_campaign() {
		$in_campaign  = $this->make_ad( 77, 501 );
		$same_adv     = $this->make_ad( 77, 502 );
		$no_campaign  = $this->make_ad( 77 );

		$args  = $this->call_private( 'build_query_args', array( $this->selection( array( 'adv' => 77, 'camp' => 501 ) ), array( 'fields' => 'ids', 'no_found_rows' => true ) ) );
		$found = ( new WP_Query( $args ) )->posts;

		$this->assertSame( array( $in_campaign ), array_values( array_map( 'intval', $found ) ) );
		$this->assertNotContains( $same_adv, $found );
		$this->assertNotContains( $no_campaign, $found );
	}

	/**
	 * The house folder is exactly the ads with no advertiser link — absent,
	 * zero, or empty meta all count as unassigned.
	 */
	public function test_house_folder_catches_unlinked_ads_only() {
		$linked = $this->make_ad( 77 );
		$absent = $this->make_ad();
		$zero   = $this->make_ad();
		update_post_meta( $zero, '_wbam_advertiser_id', '0' );
		$empty = $this->make_ad();
		update_post_meta( $empty, '_wbam_advertiser_id', '' );

		$args  = $this->call_private( 'build_query_args', array( $this->selection( array( 'house' => true ) ), array( 'fields' => 'ids', 'no_found_rows' => true, 'posts_per_page' => 100 ) ) );
		$found = array_map( 'intval', ( new WP_Query( $args ) )->posts );

		$this->assertNotContains( $linked, $found );
		$this->assertContains( $absent, $found );
		$this->assertContains( $zero, $found );
		$this->assertContains( $empty, $found );

		// The rail count must agree with the query.
		$this->assertSame( 3, $this->call_private( 'get_house_count' ) );
	}

	/**
	 * A tag folder filters by wbam_ad_tag and composes with advertiser scope.
	 */
	public function test_tag_folder_query_and_composition() {
		$tagged_mine  = $this->make_ad( 77, 0, 'sidebar' );
		$tagged_other = $this->make_ad( 88, 0, 'sidebar' );
		$untagged     = $this->make_ad( 77 );

		$args  = $this->call_private( 'build_query_args', array( $this->selection( array( 'tag' => 'sidebar' ) ), array( 'fields' => 'ids', 'no_found_rows' => true ) ) );
		$found = array_map( 'intval', ( new WP_Query( $args ) )->posts );
		$this->assertEqualsCanonicalizing( array( $tagged_mine, $tagged_other ), $found );

		$args  = $this->call_private( 'build_query_args', array( $this->selection( array( 'adv' => 77, 'tag' => 'sidebar' ) ), array( 'fields' => 'ids', 'no_found_rows' => true ) ) );
		$found = array_map( 'intval', ( new WP_Query( $args ) )->posts );
		$this->assertSame( array( $tagged_mine ), $found );
		$this->assertNotContains( $untagged, $found );
	}

	/**
	 * Rail counts come from one grouped query and only count published ads.
	 */
	public function test_advertiser_and_campaign_rail_counts() {
		$this->make_ad( 77, 501 );
		$this->make_ad( 77, 501 );
		$this->make_ad( 77, 502 );
		$this->make_ad( 88 );

		// A trashed ad never counts (drafts and pending ads do: folders list
		// unpublished submissions on purpose, see LISTED_STATUSES).
		$trashed = $this->make_ad( 77 );
		wp_trash_post( $trashed );

		$adv_counts = $this->call_private( 'get_advertiser_ad_counts' );
		$this->assertSame( 3, $adv_counts[77] );
		$this->assertSame( 1, $adv_counts[88] );

		$camp_counts = $this->call_private( 'get_campaign_ad_counts', array( array( 501, 502 ) ) );
		$this->assertSame( 2, $camp_counts[501] );
		$this->assertSame( 1, $camp_counts[502] );
	}

	/**
	 * With the Campaigns module off the screen degrades to advertiser/tag
	 * browsing: a campaign selection in the URL must collapse to no filter
	 * instead of silently filtering by a folder the UI no longer shows.
	 */
	public function test_campaign_selection_collapses_when_module_off() {
		$enabled = \WBAM_Pro\Core\Settings_Helper::get( 'enabled_modules', array() );

		$_GET['adv']  = '77';
		$_GET['camp'] = '501';

		try {
			\WBAM_Pro\Core\Settings_Helper::update( 'enabled_modules', array_merge( $enabled, array( 'campaigns' => true ) ) );
			$selection = $this->call_private( 'get_selection' );
			$this->assertSame( 501, $selection['camp'], 'Campaign folder honoured while module is on.' );

			\WBAM_Pro\Core\Settings_Helper::update( 'enabled_modules', array_merge( $enabled, array( 'campaigns' => false ) ) );
			$selection = $this->call_private( 'get_selection' );
			$this->assertSame( 0, $selection['camp'], 'Campaign folder collapses while module is off.' );
			$this->assertSame( 77, $selection['adv'], 'Advertiser folder survives the collapse.' );
		} finally {
			\WBAM_Pro\Core\Settings_Helper::update( 'enabled_modules', $enabled );
			unset( $_GET['adv'], $_GET['camp'] );
		}
	}

	/**
	 * Bulk folder actions must refuse to run without a folder scope — the
	 * guard that keeps "disable all" from ever meaning "all ads sitewide".
	 */
	public function test_bulk_scope_guard() {
		$this->assertFalse( (bool) $this->call_private( 'has_folder_scope', array( $this->selection() ) ) );
		$this->assertTrue( (bool) $this->call_private( 'has_folder_scope', array( $this->selection( array( 'adv' => 77 ) ) ) ) );
		$this->assertTrue( (bool) $this->call_private( 'has_folder_scope', array( $this->selection( array( 'tag' => 'sidebar' ) ) ) ) );
		$this->assertTrue( (bool) $this->call_private( 'has_folder_scope', array( $this->selection( array( 'house' => true ) ) ) ) );
	}

	/**
	 * The rail holds the newest RAIL_LIMIT advertisers; an ad owned by an
	 * older one still shows its advertiser in the list, not "-".
	 */
	public function test_row_advertiser_named_beyond_the_rail_limit() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$manager = \WBAM_Pro\Modules\Advertisers\Advertiser_Manager::get_instance();
		$oldest  = $manager->get_or_create( self::factory()->user->create() );
		$manager->update( $oldest->id, array( 'company_name' => 'Oldest Co' ) );
		$GLOBALS['wpdb']->update( $GLOBALS['wpdb']->prefix . 'wbam_advertisers', array( 'created_at' => '2001-01-01 00:00:00' ), array( 'id' => $oldest->id ) );
		foreach ( range( 1, Ad_Folders_Page::RAIL_LIMIT + 1 ) as $i ) {
			$manager->get_or_create( self::factory()->user->create() );
		}
		$this->make_ad( (int) $oldest->id );

		ob_start();
		Ad_Folders_Page::get_instance()->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Oldest Co', $html );
	}

	/**
	 * An advertiser with ads must appear in the rail even when 60 newer
	 * advertisers with zero ads exist — the rail lists who has ads first (by
	 * ad count, descending), then fills any remaining slots with the newest
	 * zero-ad advertisers, instead of the old "newest RAIL_LIMIT" query that
	 * let 0-ad applicants push out everyone who actually has ads.
	 */
	public function test_rail_lists_advertisers_with_ads_before_newer_empty_ones() {
		$manager = \WBAM_Pro\Modules\Advertisers\Advertiser_Manager::get_instance();

		$with_ads = $manager->get_or_create( self::factory()->user->create() );
		$manager->update( $with_ads->id, array( 'company_name' => 'Has Ads Co' ) );
		$this->make_ad( (int) $with_ads->id );

		// 60 newer, zero-ad advertisers — more than RAIL_LIMIT (50) — created
		// after the advertiser above, so a "newest first" query would drop it.
		foreach ( range( 1, 60 ) as $i ) {
			$manager->get_or_create( self::factory()->user->create() );
		}

		$rail = $this->call_private( 'get_rail_advertisers', array( $this->call_private( 'get_advertiser_ad_counts' ) ) );

		$this->assertLessThanOrEqual( Ad_Folders_Page::RAIL_LIMIT, count( $rail ), 'The rail must stay capped at RAIL_LIMIT.' );

		$ids = wp_list_pluck( $rail, 'id' );
		$this->assertContains( (int) $with_ads->id, $ids, 'An advertiser with ads must not be pushed out of the rail by newer 0-ad advertisers.' );
		$this->assertSame( (int) $with_ads->id, (int) $ids[0], 'Advertisers with ads are listed first (by ad count, descending).' );
	}
}
