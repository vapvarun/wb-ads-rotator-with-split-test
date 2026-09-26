<?php
/**
 * QA-reject regression tests for Basecamp card 10335670368.
 *
 * Reject 3 (custom fields): the checkbox/multiselect browse filter compared
 * a serialized array with `meta_value = %s` and always returned zero rows;
 * a scoped field's category_ids must round-trip through the save handler.
 *
 * Reject 4 (radius search): Geolocation_Manager::find_nearby() must exclude
 * listings with no coordinates and listings that are not active, and the
 * new geocode_address() helper (the seam the wizard's location save should
 * also call) must resolve a typed place or return a clear error.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;
use WBAM_Pro\Modules\CustomFields\Custom_Field_Manager;
use WBAM_Pro\Modules\Geolocation\Geolocation_Manager;

/**
 * @group pro
 * @group classifieds
 */
class Test_Classifieds_Browse_Rejects extends Pro_Test_Case {

	public function set_up(): void {
		parent::set_up();

		$enabled                 = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds']  = true;
		$enabled['custom_fields'] = true;
		$enabled['geolocation']  = true;
		Settings_Helper::update( 'enabled_modules', $enabled );

		$classifieds                     = get_option( 'wbam_pro_classifieds_settings', array() );
		$classifieds['require_approval'] = false;
		update_option( 'wbam_pro_classifieds_settings', $classifieds );
	}

	/**
	 * Create an active classified for the given (new) advertiser.
	 *
	 * @param array $overrides Data overrides passed to Classified_Manager::create().
	 * @return object Classified.
	 */
	private function make_active_classified( array $overrides = array() ): object {
		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );

		$classified = Classified_Manager::get_instance()->create(
			array_merge(
				array(
					'title'         => 'Browse reject test listing',
					'description'   => 'Test.',
					'advertiser_id' => $advertiser->id,
				),
				$overrides
			)
		);
		$this->assertNotWPError( $classified );
		$this->assertSame( 'active', $classified->status );

		return $classified;
	}

	// ------------------------------------------------------------------
	// Reject 3(b): checkbox/multiselect filter query.
	// ------------------------------------------------------------------

	public function test_checkbox_filter_matches_serialized_multi_value(): void {
		$field_id = Custom_Field_Manager::get_instance()->save_field(
			array(
				'field_key'   => 'boxed',
				'field_label' => 'Boxed',
				'field_type'  => 'checkbox',
				'options'     => array(
					array(
						'value' => 'Yes',
						'label' => 'Yes',
					),
					array(
						'value' => 'No',
						'label' => 'No',
					),
				),
				'searchable'  => 1,
			)
		);
		$this->assertIsInt( $field_id );

		$boxed_yes = $this->make_active_classified( array( 'title' => 'Boxed item' ) );
		$boxed_yes->update_meta( 'boxed', array( 'Yes' ) );

		$boxed_no = $this->make_active_classified( array( 'title' => 'Unboxed item' ) );
		$boxed_no->update_meta( 'boxed', array( 'No' ) );

		$result = Classified_Manager::get_instance()->get_active(
			array(
				'custom_fields' => array( 'boxed' => array( 'Yes' ) ),
			)
		);

		$ids = wp_list_pluck( $result['items'], 'id' );
		$this->assertContains( $boxed_yes->id, $ids, 'A serialized single-value checkbox match must be found via LIKE, not an exact meta_value comparison.' );
		$this->assertNotContains( $boxed_no->id, $ids );
	}

	public function test_text_filter_keeps_exact_match(): void {
		Custom_Field_Manager::get_instance()->save_field(
			array(
				'field_key'   => 'material',
				'field_label' => 'Material',
				'field_type'  => 'text',
				'searchable'  => 1,
			)
		);

		$oak = $this->make_active_classified( array( 'title' => 'Oak table' ) );
		$oak->update_meta( 'material', 'Oak' );

		$pine = $this->make_active_classified( array( 'title' => 'Pine table' ) );
		$pine->update_meta( 'material', 'Pine' );

		$result = Classified_Manager::get_instance()->get_active(
			array(
				'custom_fields' => array( 'material' => 'Oak' ),
			)
		);

		$ids = wp_list_pluck( $result['items'], 'id' );
		$this->assertContains( $oak->id, $ids );
		$this->assertNotContains( $pine->id, $ids, 'A plain text field must not become a substring match — "Oak" should not also match "Oakley".' );
	}

	// ------------------------------------------------------------------
	// Reject 3(a): category_ids persists through the save handler.
	// ------------------------------------------------------------------

	public function test_category_ids_persist_and_scope_field_lookup(): void {
		$term = wp_insert_term( 'QA Furniture', Classified_Manager::TAXONOMY_CATEGORY );
		$this->assertIsArray( $term );
		$term_id = (int) $term['term_id'];

		$field_id = Custom_Field_Manager::get_instance()->save_field(
			array(
				'field_key'    => 'wood_type',
				'field_label'  => 'Wood Type',
				'field_type'   => 'text',
				'category_ids' => array( $term_id ),
			)
		);
		$this->assertIsInt( $field_id );

		$field = Custom_Field_Manager::get_instance()->get_field( $field_id );
		$this->assertSame( array( $term_id ), json_decode( $field->category_ids, true ), 'category_ids sent on save must round-trip unchanged.' );

		$scoped = Custom_Field_Manager::get_instance()->get_fields( array( 'category_id' => $term_id ) );
		$this->assertContains( $field_id, array_map( 'intval', wp_list_pluck( $scoped, 'id' ) ) );

		$other_category = Custom_Field_Manager::get_instance()->get_fields( array( 'category_id' => $term_id + 999 ) );
		$this->assertNotContains( $field_id, array_map( 'intval', wp_list_pluck( $other_category, 'id' ) ), 'A field scoped to one category must not apply to an unrelated one.' );
	}

	// ------------------------------------------------------------------
	// Reject 4: find_nearby excludes no-coordinate and non-active rows.
	// ------------------------------------------------------------------

	public function test_find_nearby_excludes_no_coords_and_inactive(): void {
		$geo = Geolocation_Manager::get_instance();

		// Springfield-ish coordinates; the "near" listings sit ~1km away.
		$origin_lat = 39.781;
		$origin_lng = -89.650;

		$near_active = $this->make_active_classified( array( 'title' => 'Near + active' ) );
		$saved       = $geo->save_location( $near_active->id, array(
			'latitude'  => $origin_lat + 0.005,
			'longitude' => $origin_lng + 0.005,
		) );
		$this->assertNotWPError( $saved );

		$near_inactive = $this->make_active_classified( array( 'title' => 'Near + sold' ) );
		$near_inactive->status = 'sold';
		$near_inactive->save();
		$geo->save_location( $near_inactive->id, array(
			'latitude'  => $origin_lat + 0.006,
			'longitude' => $origin_lng + 0.006,
		) );

		// Active but no coordinates saved at all.
		$this->make_active_classified( array( 'title' => 'Active, no location' ) );

		$results = $geo->find_nearby( $origin_lat, $origin_lng, 25 );
		$ids     = array_map( 'intval', wp_list_pluck( $results, 'classified_id' ) );

		$this->assertContains( $near_active->id, $ids );
		$this->assertNotContains( $near_inactive->id, $ids, 'A non-active listing must not surface in a radius search.' );
		$this->assertCount( 1, $ids, 'Listings without a saved location must never appear regardless of radius.' );
	}

	/**
	 * The IDs feed a WHERE IN on the browse query, so a dense area returns
	 * the nearest N only, not every match in the radius.
	 */
	public function test_find_nearby_returns_the_nearest_up_to_the_limit(): void {
		$geo = Geolocation_Manager::get_instance();
		$ids = array();
		foreach ( array( 0.001, 0.002, 0.003 ) as $offset ) {
			$classified = $this->make_active_classified( array( 'title' => 'Listing ' . $offset ) );
			$geo->save_location(
				$classified->id,
				array(
					'latitude'  => 39.781 + $offset,
					'longitude' => -89.650,
				)
			);
			$ids[] = (int) $classified->id;
		}

		$found = array_map( 'intval', wp_list_pluck( $geo->find_nearby( 39.781, -89.650, 25, 2 ), 'classified_id' ) );

		$this->assertSame( array( $ids[0], $ids[1] ), $found );
	}

	// ------------------------------------------------------------------
	// Reject 4: server-side geocode helper (mocked Nominatim).
	// ------------------------------------------------------------------

	public function test_geocode_address_resolves_from_mocked_nominatim(): void {
		add_filter( 'wbam_pro_allow_geocoding', '__return_true' ); // Owner opted in.
		add_filter( 'pre_http_request', array( $this, 'mock_nominatim_found' ), 10, 3 );

		$result = Geolocation_Manager::get_instance()->geocode_address( 'Springfield, IL' );

		remove_filter( 'pre_http_request', array( $this, 'mock_nominatim_found' ), 10 );

		$this->assertIsArray( $result );
		$this->assertEqualsWithDelta( 39.7817, $result['lat'], 0.001 );
		$this->assertEqualsWithDelta( -89.6501, $result['lng'], 0.001 );
	}

	public function test_geocode_address_returns_error_when_not_found(): void {
		add_filter( 'wbam_pro_allow_geocoding', '__return_true' ); // Owner opted in.
		add_filter( 'pre_http_request', array( $this, 'mock_nominatim_empty' ), 10, 3 );

		$result = Geolocation_Manager::get_instance()->geocode_address( 'Nowhereville Qaxyz' );

		remove_filter( 'pre_http_request', array( $this, 'mock_nominatim_empty' ), 10 );

		$this->assertWPError( $result );
	}

	public function test_geocode_address_rejects_empty_input(): void {
		$result = Geolocation_Manager::get_instance()->geocode_address( '   ' );
		$this->assertWPError( $result );
	}

	/**
	 * pre_http_request callback returning a single Nominatim match.
	 */
	public function mock_nominatim_found( $preempt, $args, $url ) {
		return array(
			'body'     => wp_json_encode( array( array( 'lat' => '39.7817', 'lon' => '-89.6501' ) ) ),
			'response' => array( 'code' => 200 ),
			'headers'  => array(),
		);
	}

	/**
	 * pre_http_request callback returning no Nominatim matches.
	 */
	public function mock_nominatim_empty( $preempt, $args, $url ) {
		return array(
			'body'     => '[]',
			'response' => array( 'code' => 200 ),
			'headers'  => array(),
		);
	}
}
