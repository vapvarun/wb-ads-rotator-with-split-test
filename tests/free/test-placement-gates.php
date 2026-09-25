<?php
/**
 * Placement gate resolution: site + advertiser allowlists.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Core\Settings_Helper;
use WP_UnitTestCase;

class Test_Placement_Gates extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( 'wbam_settings' );
		parent::tear_down();
	}

	public function test_empty_site_gate_means_all(): void {
		update_option( 'wbam_settings', array( 'enabled_placements' => array() ) );
		$this->assertSame( array(), Settings_Helper::enabled_placements() );
	}

	public function test_missing_key_means_all(): void {
		update_option( 'wbam_settings', array() );
		$this->assertSame( array(), Settings_Helper::enabled_placements() );
	}

	public function test_site_gate_returns_sanitized_ids(): void {
		update_option(
			'wbam_settings',
			array( 'enabled_placements' => array( 'header', 'Foo Bar', 'footer' ) )
		);
		$this->assertSame(
			array( 'header', 'foobar', 'footer' ),
			Settings_Helper::enabled_placements()
		);
	}

	public function test_advertiser_gate_is_intersected_with_site_gate(): void {
		update_option(
			'wbam_settings',
			array(
				'enabled_placements'    => array( 'header', 'footer' ),
				'advertiser_placements' => array( 'header', 'popup' ),
			)
		);
		// 'popup' is not in the site gate, so it cannot be sellable.
		$this->assertSame( array( 'header' ), Settings_Helper::advertiser_placements() );
	}

	public function test_empty_advertiser_gate_falls_back_to_site_gate(): void {
		update_option(
			'wbam_settings',
			array(
				'enabled_placements'    => array( 'header', 'footer' ),
				'advertiser_placements' => array(),
			)
		);
		$this->assertSame( array( 'header', 'footer' ), Settings_Helper::advertiser_placements() );
	}

	public function test_site_gate_filter_overrides_option(): void {
		update_option( 'wbam_settings', array( 'enabled_placements' => array( 'header' ) ) );
		add_filter( 'wbam_enabled_placements', static fn() => array( 'footer' ) );
		$this->assertSame( array( 'footer' ), Settings_Helper::enabled_placements() );
		remove_all_filters( 'wbam_enabled_placements' );
	}

	public function test_selectable_placements_respects_site_gate(): void {
		$engine = \WBAM\Modules\Placements\Placement_Engine::get_instance();

		update_option( 'wbam_settings', array( 'enabled_placements' => array( 'header' ) ) );
		$ids = array_keys( $engine->get_selectable_placements() );

		$this->assertContains( 'header', $ids );
		$this->assertNotContains( 'footer', $ids );
	}

	public function test_selectable_placements_empty_gate_returns_all_visible(): void {
		$engine = \WBAM\Modules\Placements\Placement_Engine::get_instance();

		update_option( 'wbam_settings', array( 'enabled_placements' => array() ) );
		$ids = array_keys( $engine->get_selectable_placements() );

		$this->assertContains( 'header', $ids );
		$this->assertContains( 'footer', $ids );
	}

	public function test_selectable_placements_excludes_non_selector_placements(): void {
		$engine = \WBAM\Modules\Placements\Placement_Engine::get_instance();

		update_option( 'wbam_settings', array() );
		$ids = array_keys( $engine->get_selectable_placements() );

		// Shortcode_Placement::show_in_selector() returns false — it is
		// used manually via [wbam_ad], never assigned to an ad.
		$this->assertNotContains( 'shortcode', $ids );
	}

	/**
	 * Build a matrix POST the way Placement_Settings::render_table() would.
	 *
	 * @param string[] $offered   Rows the matrix drew.
	 * @param string[] $site      Site boxes ticked.
	 * @param string[]|null $adv  Advertiser boxes ticked. Null = same as site.
	 * @return array
	 */
	private function matrix_post( array $offered, array $site, ?array $adv = null ): array {
		// The matrix as Pro draws it, Advertisers column included; free hides
		// that column and omits the flag (see sanitize_placement_gates()).
		$post = array(
			'placement_gates_submitted'     => '1',
			'placement_gates_adv_submitted' => '1',
			'placement_gates_offered'       => implode( ',', $offered ),
		);

		// An unticked checkbox posts nothing, so an empty tick list means
		// the key is absent entirely — that is the whole reason the two
		// hidden fields above exist.
		if ( ! empty( $site ) ) {
			$post['enabled_placements'] = $site;
		}

		$adv = null === $adv ? $site : $adv;
		if ( ! empty( $adv ) ) {
			$post['advertiser_placements'] = $adv;
		}

		return $post;
	}

	public function test_sanitizer_preserves_placement_gates(): void {
		$settings = new \WBAM\Admin\Settings();

		$out = $settings->sanitize_settings(
			$this->matrix_post(
				array( 'header', 'footer', 'popup' ),
				array( 'header', 'footer' ),
				array( 'header' )
			)
		);

		$this->assertSame( array( 'header', 'footer' ), $out['enabled_placements'] );
		$this->assertSame( array( 'header' ), $out['advertiser_placements'] );
	}

	public function test_sanitizer_intersects_advertiser_with_site(): void {
		$settings = new \WBAM\Admin\Settings();

		// A crafted POST claiming a slot the site gate does not allow.
		$out = $settings->sanitize_settings(
			$this->matrix_post(
				array( 'header', 'footer', 'popup' ),
				array( 'header' ),
				array( 'header', 'popup' )
			)
		);

		$this->assertSame( array( 'header' ), $out['enabled_placements'] );

		// 'popup' is dropped at write time because it is not sellable, which
		// leaves every sellable row ticked - so the gate stores "all", and
		// "all" for advertisers resolves to the site list. Assert on that
		// resolved value; the raw encoding is an implementation detail.
		update_option( 'wbam_settings', $out );
		$this->assertSame( array( 'header' ), Settings_Helper::advertiser_placements() );
	}

	public function test_sanitizer_defaults_gates_to_empty_arrays(): void {
		$settings = new \WBAM\Admin\Settings();
		$out      = $settings->sanitize_settings( array() );

		$this->assertSame( array(), $out['enabled_placements'] );
		$this->assertSame( array(), $out['advertiser_placements'] );
	}

	/**
	 * CRITICAL 2 regression.
	 *
	 * Every box ticked must store "all" (array()), never a snapshot of the
	 * placements that happened to be registered at save time. Otherwise one
	 * unrelated Settings save silently closes every slot a companion plugin
	 * registers afterwards.
	 */
	public function test_sanitizer_stores_all_when_every_row_is_ticked(): void {
		$settings = new \WBAM\Admin\Settings();
		$offered  = array( 'header', 'footer', 'popup', 'sticky' );

		$out = $settings->sanitize_settings( $this->matrix_post( $offered, $offered ) );

		$this->assertSame( array(), $out['enabled_placements'], 'A fully-ticked matrix must store "all", not a frozen allowlist.' );
		$this->assertSame( array(), $out['advertiser_placements'] );

		// And "all" really does stay open for a placement registered later.
		update_option( 'wbam_settings', $out );
		$this->assertTrue( Settings_Helper::is_placement_open( 'a_slot_registered_after_that_save' ) );
	}

	/**
	 * CRITICAL 1 regression.
	 *
	 * Unticking every box must store an explicit "none" and actually stop
	 * delivery. Storing array() would mean "all" and re-open everything the
	 * admin just clicked through a confirm to close.
	 */
	public function test_sanitizer_stores_explicit_none_when_nothing_is_ticked(): void {
		$ad_id = self::factory()->post->create( array( 'post_type' => 'wbam-ad' ) );
		update_post_meta( $ad_id, '_wbam_enabled', '1' );
		update_post_meta( $ad_id, '_wbam_placements', array( 'footer' ) );

		$engine = \WBAM\Modules\Placements\Placement_Engine::get_instance();
		wp_cache_flush();
		$this->assertNotEmpty( $engine->get_ads_for_placement( 'footer' ), 'Precondition: the slot is open.' );

		$settings = new \WBAM\Admin\Settings();
		$out      = $settings->sanitize_settings(
			$this->matrix_post( array( 'header', 'footer', 'popup' ), array() )
		);

		$this->assertSame( array( Settings_Helper::GATE_NONE ), $out['enabled_placements'] );

		update_option( 'wbam_settings', $out );
		wp_cache_flush();

		$this->assertNotSame( array(), Settings_Helper::enabled_placements(), 'Stored "none" must not read back as "all".' );
		// Nothing is sellable when nothing is open, whatever the advertiser
		// gate stores - the site gate wins.
		$this->assertSame( array( Settings_Helper::GATE_NONE ), Settings_Helper::advertiser_placements() );
		$this->assertFalse( Settings_Helper::is_placement_open( 'footer' ) );
		$this->assertSame( array(), $engine->get_ads_for_placement( 'footer' ), 'A closed site gate must stop delivery.' );
		$this->assertSame( array(), $engine->get_selectable_placements() );
	}

	/**
	 * Unticking every Advertisers box must not fall back to the site list.
	 */
	public function test_advertiser_gate_stores_explicit_none(): void {
		$settings = new \WBAM\Admin\Settings();

		$out = $settings->sanitize_settings(
			$this->matrix_post( array( 'header', 'footer' ), array( 'header' ), array() )
		);

		$this->assertSame( array( 'header' ), $out['enabled_placements'] );
		$this->assertSame( array( Settings_Helper::GATE_NONE ), $out['advertiser_placements'] );

		update_option( 'wbam_settings', $out );
		$this->assertSame( array( Settings_Helper::GATE_NONE ), Settings_Helper::advertiser_placements() );
	}

	/**
	 * A save that never rendered the matrix must not rewrite the gates.
	 */
	public function test_settings_save_without_the_matrix_leaves_gates_alone(): void {
		update_option(
			'wbam_settings',
			array(
				'enabled_placements'    => array( 'header' ),
				'advertiser_placements' => array( 'header' ),
			)
		);

		$settings = new \WBAM\Admin\Settings();
		$out      = $settings->sanitize_settings( array( 'ad_label' => 'Sponsored' ) );

		$this->assertSame( array( 'header' ), $out['enabled_placements'] );
		$this->assertSame( array( 'header' ), $out['advertiser_placements'] );
	}

	/**
	 * A slot the matrix never drew a row for keeps its stored state.
	 */
	public function test_gate_carries_over_placements_the_matrix_never_offered(): void {
		update_option(
			'wbam_settings',
			array( 'enabled_placements' => array( 'header', 'frontend_only_slot' ) )
		);

		$settings = new \WBAM\Admin\Settings();
		$out      = $settings->sanitize_settings(
			$this->matrix_post( array( 'header', 'footer' ), array( 'header' ) )
		);

		$this->assertContains( 'header', $out['enabled_placements'] );
		$this->assertContains( 'frontend_only_slot', $out['enabled_placements'], 'A slot the UI never offered must not be closed by a save.' );
		$this->assertNotContains( 'footer', $out['enabled_placements'] );
	}

	/**
	 * IMPORTANT 3 regression.
	 *
	 * Closing a slot must not destroy the ads already assigned to it.
	 */
	public function test_ad_save_keeps_assignments_the_form_did_not_offer(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$ad_id = self::factory()->post->create( array( 'post_type' => 'wbam-ad' ) );
		update_post_meta( $ad_id, '_wbam_placements', array( 'popup', 'footer' ) );

		// The admin closes 'popup' at site level, so the metabox stops
		// drawing a checkbox for it.
		update_option( 'wbam_settings', array( 'enabled_placements' => array( 'footer', 'header' ) ) );

		$original = $_POST;
		$_POST    = array(
			'wbam_nonce'      => wp_create_nonce( 'wbam_save_ad' ),
			'wbam_placements' => array( 'footer' ),
		);

		\WBAM\Admin\Admin::get_instance()->save_meta( $ad_id, get_post( $ad_id ) );

		$_POST = $original;

		$saved = get_post_meta( $ad_id, '_wbam_placements', true );

		$this->assertContains( 'footer', (array) $saved );
		$this->assertContains( 'popup', (array) $saved, 'A closed slot must keep its assignment across an unrelated ad edit.' );
	}

	public function test_closed_placement_returns_no_ads(): void {
		$ad_id = self::factory()->post->create( array( 'post_type' => 'wbam-ad' ) );
		update_post_meta( $ad_id, '_wbam_enabled', '1' );
		update_post_meta( $ad_id, '_wbam_placements', array( 'footer' ) );

		$engine = \WBAM\Modules\Placements\Placement_Engine::get_instance();

		// Open: the ad is deliverable.
		update_option( 'wbam_settings', array( 'enabled_placements' => array( 'footer' ) ) );
		wp_cache_flush();
		$this->assertNotEmpty( $engine->get_ads_for_placement( 'footer' ) );

		// Closed: delivery stops.
		update_option( 'wbam_settings', array( 'enabled_placements' => array( 'header' ) ) );
		wp_cache_flush();
		$this->assertSame( array(), $engine->get_ads_for_placement( 'footer' ) );
	}

	public function test_ad_counts_are_grouped_per_placement(): void {
		$a = self::factory()->post->create( array( 'post_type' => 'wbam-ad' ) );
		update_post_meta( $a, '_wbam_enabled', '1' );
		update_post_meta( $a, '_wbam_placements', array( 'header', 'footer' ) );

		$b = self::factory()->post->create( array( 'post_type' => 'wbam-ad' ) );
		update_post_meta( $b, '_wbam_enabled', '1' );
		update_post_meta( $b, '_wbam_placements', array( 'header' ) );

		// Disabled ads must not be counted.
		$c = self::factory()->post->create( array( 'post_type' => 'wbam-ad' ) );
		update_post_meta( $c, '_wbam_enabled', '0' );
		update_post_meta( $c, '_wbam_placements', array( 'header' ) );

		\WBAM\Admin\Placement_Settings::clear_count_cache();
		$counts = \WBAM\Admin\Placement_Settings::get_ad_counts();

		$this->assertSame( 2, $counts['header'] ?? 0 );
		$this->assertSame( 1, $counts['footer'] ?? 0 );
		$this->assertSame( 0, $counts['popup'] ?? 0 );
	}

	public function test_rest_placement_types_respects_site_gate(): void {
		update_option( 'wbam_settings', array( 'enabled_placements' => array( 'header' ) ) );

		$api      = new \WBAM\API\Ads_API();
		$response = $api->get_placement_types( new \WP_REST_Request( 'GET', '/wbam/v1/placement-types' ) );
		$ids      = wp_list_pluck( $response->get_data()['placements'], 'id' );

		$this->assertContains( 'header', $ids );
		$this->assertNotContains( 'footer', $ids, 'A closed slot must not be listed over REST.' );
		$this->assertNotContains( 'shortcode', $ids, 'A non-selector placement must not be listed over REST.' );
	}

	public function test_abilities_list_placements_respects_site_gate(): void {
		update_option( 'wbam_settings', array( 'enabled_placements' => array( 'header' ) ) );

		$abilities = new \WBAM\Core\Abilities();
		$result    = $abilities->execute_list_placements( array() );
		// execute_list_placements() returns a flat list (its output_schema
		// declares type=array of objects, not a {placements:[]} wrapper).
		$ids       = wp_list_pluck( $result, 'id' );

		$this->assertContains( 'header', $ids );
		$this->assertNotContains( 'footer', $ids, 'A closed slot must not be listed over the Abilities API.' );
	}

	/**
	 * Regression: a programmatic write (REST settings route, WP-CLI, a
	 * migration) names the gate key explicitly and carries no matrix
	 * transport marker. Gating every gate-write on that marker made
	 * PUT /wbam/v1/settings silently discard the value while still
	 * reporting success — saved but not applied.
	 */
	public function test_programmatic_write_without_matrix_marker_is_honoured(): void {
		update_option( 'wbam_settings', array( 'enabled_placements' => array( 'footer' ) ) );

		$settings = new \WBAM\Admin\Settings();
		$out      = $settings->sanitize_settings( array( 'enabled_placements' => array( 'header' ) ) );

		$this->assertSame( array( 'header' ), $out['enabled_placements'] );
	}

	public function test_write_naming_neither_gate_preserves_both(): void {
		update_option(
			'wbam_settings',
			array(
				'enabled_placements'    => array( 'header' ),
				'advertiser_placements' => array( 'header' ),
			)
		);

		$settings = new \WBAM\Admin\Settings();
		$out      = $settings->sanitize_settings( array( 'ad_label' => 'Sponsored' ) );

		$this->assertSame( array( 'header' ), $out['enabled_placements'] );
		$this->assertSame( array( 'header' ), $out['advertiser_placements'] );
	}

	public function test_programmatic_advertiser_gate_cannot_exceed_site_gate(): void {
		$settings = new \WBAM\Admin\Settings();
		$out      = $settings->sanitize_settings(
			array(
				'enabled_placements'    => array( 'header' ),
				'advertiser_placements' => array( 'header', 'popup' ),
			)
		);

		$this->assertSame( array( 'header' ), $out['advertiser_placements'] );
	}
}
