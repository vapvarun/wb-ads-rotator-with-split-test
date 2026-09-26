<?php
/**
 * QA reject fixes on card 10335670368: the portal wizard asks for location
 * once (term + typed address, both kept), and membership caps/moderation
 * gate relisting the same way a brand-new submission is gated.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;
use WBAM_Pro\Modules\Geolocation\Geolocation_Manager;
use WBAM_Pro\Modules\Memberships\Membership_Manager;

class Test_Classifieds_Wizard_Rejects extends Pro_Test_Case {

	private int $user;
	private object $advertiser;

	public function set_up(): void {
		parent::set_up();

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		$enabled['geolocation'] = true;
		$enabled['memberships'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );

		$this->user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $this->user );
		Advertiser_Manager::get_instance()->update_status( (int) $this->advertiser->id, 'active' );
		$this->advertiser = Advertiser_Manager::get_instance()->get( (int) $this->advertiser->id );
		\Wbcom\Credits\Credits::topup( 'wbam-pro', $this->user, 100000, 'seed' );
	}

	private function category(): int {
		$term = wp_insert_term( 'Wizard ' . wp_generate_password( 6, false ), Classified_Manager::TAXONOMY_CATEGORY );
		return (int) $term['term_id'];
	}

	private function location_term(): int {
		$term = wp_insert_term( 'Springfield ' . wp_generate_password( 6, false ), Classified_Manager::TAXONOMY_LOCATION );
		return (int) $term['term_id'];
	}

	/**
	 * Reject 2: the term (browse filter) and the typed address are asked once
	 * and both kept, not one overwriting the other.
	 */
	public function test_location_term_and_typed_address_are_both_stored(): void {
		$manager  = Classified_Manager::get_instance();
		$location = $this->location_term();

		$listing = $manager->submit(
			$this->advertiser,
			array(
				'title'      => 'Sofa for sale',
				'categories' => array( $this->category() ),
				'location_id'   => $location,
				'location_text' => '742 Evergreen Terrace, Springfield',
			)
		);

		$this->assertNotWPError( $listing );
		$this->assertSame( '742 Evergreen Terrace, Springfield', $listing->location_text );
		$term = $listing->get_location();
		$this->assertNotNull( $term );
		$this->assertSame( $location, (int) $term->term_id );
	}

	/**
	 * Reject 2: a typed address with no map pick is resolved server-side via
	 * the shared Geolocation_Manager::geocode_address() helper, so the
	 * listing can still match a radius search. The HTTP call is faked so the
	 * test is deterministic and offline.
	 */
	public function test_typed_address_without_coordinates_is_geocoded_server_side(): void {
		add_filter( 'wbam_pro_allow_geocoding', '__return_true' ); // Owner opted in.
		add_filter( 'pre_http_request', array( $this, 'fake_nominatim_response' ), 10, 3 );

		$manager = Classified_Manager::get_instance();
		$listing = $manager->submit(
			$this->advertiser,
			array(
				'title'      => 'Bike for sale',
				'categories' => array( $this->category() ),
				'geo'        => array(
					'address' => '1 Main Street, Springfield',
				),
			)
		);

		remove_filter( 'pre_http_request', array( $this, 'fake_nominatim_response' ), 10 );

		$this->assertNotWPError( $listing );

		$row = Geolocation_Manager::get_instance()->get_location( $listing->id );
		$this->assertNotNull( $row, 'A typed address is always stored, with or without coordinates.' );
		$this->assertSame( '1 Main Street, Springfield', $row->formatted_address );
		$this->assertSame( 42.0887, (float) $row->latitude );
		$this->assertSame( -72.6285, (float) $row->longitude );
	}

	/**
	 * Reject 2: when the address can't be geocoded (lookup fails), the save
	 * is never blocked - the typed address is still kept, just without
	 * coordinates.
	 */
	public function test_typed_address_is_kept_when_geocoding_fails(): void {
		add_filter( 'wbam_pro_allow_geocoding', '__return_true' ); // Owner opted in.
		add_filter( 'pre_http_request', array( $this, 'fake_nominatim_failure' ), 10, 3 );

		$manager = Classified_Manager::get_instance();
		$listing = $manager->submit(
			$this->advertiser,
			array(
				'title'      => 'Bike for sale, unresolvable address',
				'categories' => array( $this->category() ),
				'geo'        => array(
					'address' => 'Nowhere in particular',
				),
			)
		);

		remove_filter( 'pre_http_request', array( $this, 'fake_nominatim_failure' ), 10 );

		$this->assertNotWPError( $listing, 'A failed geocode lookup must never block the save.' );

		$row = Geolocation_Manager::get_instance()->get_location( $listing->id );
		$this->assertNotNull( $row );
		$this->assertSame( 'Nowhere in particular', $row->formatted_address );
		$this->assertNull( $row->latitude );
	}

	/**
	 * Short-circuits wp_remote_get() with a canned Nominatim "found" response.
	 */
	public function fake_nominatim_response( $preempt, $args, $url ) {
		if ( false === strpos( (string) $url, 'nominatim.openstreetmap.org' ) ) {
			return $preempt;
		}
		return array(
			'body'     => wp_json_encode( array( array( 'lat' => '42.0887', 'lon' => '-72.6285' ) ) ),
			'response' => array( 'code' => 200 ),
		);
	}

	/**
	 * Short-circuits wp_remote_get() with an empty Nominatim "not found" response.
	 */
	public function fake_nominatim_failure( $preempt, $args, $url ) {
		if ( false === strpos( (string) $url, 'nominatim.openstreetmap.org' ) ) {
			return $preempt;
		}
		return array(
			'body'     => wp_json_encode( array() ),
			'response' => array( 'code' => 200 ),
		);
	}

	/**
	 * Reject 5: reactivate_classified skipped the plan cap - a sold listing
	 * isn't counted, so bringing it back must recheck the same limit a new
	 * submission gets.
	 */
	public function test_renew_by_seller_blocks_an_expired_listing_at_the_plan_cap(): void {
		$members = Membership_Manager::get_instance();
		$members->save_plan(
			array(
				'name'          => 'Capped plan',
				'price'         => 0,
				'billing_cycle' => 'monthly',
				'max_listings'  => 1,
				'max_featured'  => 0,
				'status'        => 'active',
			)
		);
		$plans = $members->get_plans();
		$plan  = end( $plans );
		$members->subscribe( $this->advertiser->id, $plan->id );

		$manager = Classified_Manager::get_instance();

		// One active listing fills the cap (1/1).
		$active = $manager->create(
			array(
				'title'         => 'Keeps the cap full',
				'advertiser_id' => $this->advertiser->id,
			)
		);
		$active->status = 'active';
		$active->save();

		// A second listing that has since expired - not counted while expired.
		$expired = $manager->create(
			array(
				'title'         => 'Expired listing',
				'advertiser_id' => $this->advertiser->id,
			)
		);
		$expired->status = 'expired';
		$expired->save();

		$result = $manager->renew_by_seller( $expired->id, $this->advertiser->id );

		$this->assertWPError( $result, 'Renewing back into the counted set must respect the cap.' );
		$this->assertSame( 403, $result->get_error_data()['status'] );
		$this->assertSame( 'expired', $manager->get( $expired->id )->status, 'A blocked renewal must not go live.' );
	}

	/**
	 * Reject 5: renewing an expired listing under the cap goes through the
	 * same moderation gate a new submission gets (require_approval, default
	 * on) instead of jumping straight back to active.
	 */
	public function test_renew_by_seller_sends_an_expired_listing_back_to_pending(): void {
		$manager = Classified_Manager::get_instance();

		$expired = $manager->create(
			array(
				'title'         => 'Needs re-review',
				'advertiser_id' => $this->advertiser->id,
			)
		);
		$expired->status = 'expired';
		$expired->save();

		$days = $manager->renew_by_seller( $expired->id, $this->advertiser->id );

		$this->assertNotWPError( $days );
		$this->assertSame( 'pending', $manager->get( $expired->id )->status );
	}

	/**
	 * Renewing a listing that is still active (extending its duration, not
	 * bringing a dead one back) needs neither the cap nor moderation check -
	 * it was already counted and already approved.
	 */
	public function test_renew_by_seller_active_listing_skips_cap_and_moderation(): void {
		$members = Membership_Manager::get_instance();
		$members->save_plan(
			array(
				'name'          => 'Capped plan',
				'price'         => 0,
				'billing_cycle' => 'monthly',
				'max_listings'  => 1,
				'max_featured'  => 0,
				'status'        => 'active',
			)
		);
		$plans = $members->get_plans();
		$plan  = end( $plans );
		$members->subscribe( $this->advertiser->id, $plan->id );

		$manager = Classified_Manager::get_instance();
		$active  = $manager->create(
			array(
				'title'         => 'Already live, at the cap',
				'advertiser_id' => $this->advertiser->id,
			)
		);
		$active->status = 'active';
		$active->save();

		$days = $manager->renew_by_seller( $active->id, $this->advertiser->id );

		$this->assertNotWPError( $days, 'An already-active renewal must not be blocked by its own count.' );
		$this->assertSame( 'active', $manager->get( $active->id )->status );
	}
}
