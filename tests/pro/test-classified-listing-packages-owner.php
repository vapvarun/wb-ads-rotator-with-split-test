<?php
/**
 * Site owners edit listing packages in Settings → Classifieds
 * (cards 9757961331 / 9754444963). Defaults are a starter set only.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;

class Test_Classified_Listing_Packages_Owner extends Pro_Test_Case {

	public function set_up(): void {
		parent::set_up();

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );
	}

	public function tear_down(): void {
		Settings_Helper::delete( 'classified_packages' );
		parent::tear_down();
	}

	public function test_unsaved_site_uses_starter_packages(): void {
		Settings_Helper::delete( 'classified_packages' );

		$packages = Classified_Manager::get_listing_packages();

		$this->assertCount( 3, $packages );
		$this->assertSame( 'Free', $packages[0]['name'] );
		$this->assertSame( 30, $packages[0]['duration'] );
		$this->assertSame( 0.0, (float) $packages[0]['price'] );
	}

	public function test_owner_list_replaces_the_starter_set(): void {
		Settings_Helper::update(
			'classified_packages',
			array(
				array(
					'name'     => 'Gold',
					'duration' => 14,
					'price'    => 10,
				),
			)
		);

		$packages = Classified_Manager::get_listing_packages();

		$this->assertCount( 1, $packages );
		$this->assertSame( 'Gold', $packages[0]['name'] );
		$this->assertSame( 14, $packages[0]['duration'] );
		$this->assertSame( 10.0, (float) $packages[0]['price'] );
	}

	public function test_sanitize_drops_blank_rows_and_featured_flags(): void {
		$packages = Classified_Manager::sanitize_listing_packages(
			array(
				array(
					'name'     => '',
					'duration' => 10,
					'price'    => 1,
				),
				array(
					'name'     => ' Weekend ',
					'duration' => 7,
					'price'    => 2.5,
					'featured' => true,
				),
			)
		);

		$this->assertCount( 1, $packages );
		$this->assertSame( 'Weekend', $packages[0]['name'] );
		$this->assertArrayNotHasKey( 'featured', $packages[0] );
		$this->assertSame( 2.5, $packages[0]['price'] );
	}

	public function test_submit_charges_the_owner_package_price(): void {
		Settings_Helper::update(
			'classified_packages',
			array(
				array(
					'name'     => 'Gold',
					'duration' => 14,
					'price'    => 0,
				),
			)
		);

		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		$term       = wp_insert_term( 'Owner pkg ' . wp_generate_password( 6, false ), Classified_Manager::TAXONOMY_CATEGORY );

		$classified = Classified_Manager::get_instance()->submit(
			$advertiser,
			array(
				'title'           => 'Owner package listing',
				'description'     => 'Uses the owner Gold package.',
				'categories'      => array( (int) $term['term_id'] ),
				'listing_package' => 0,
			)
		);

		$this->assertNotWPError( $classified, is_wp_error( $classified ) ? $classified->get_error_message() : '' );
		$days = (int) round( ( strtotime( $classified->expires_at ) - time() ) / DAY_IN_SECONDS );
		$this->assertSame( 14, $days );
	}
}
