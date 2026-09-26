<?php
/**
 * Listing packages no longer include Featured (card 10343726490, owner
 * decision). A package sets only duration and price; Featured is bought
 * separately, as the one Featured upgrade. A package still carrying the
 * old 'featured' flag (a stale filter, or an un-migrated option) must not
 * grant it any more - the server ignores the flag entirely.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Installer;
use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;

class Test_Classified_Packages_No_Featured extends Pro_Test_Case {

	public function set_up(): void {
		parent::set_up();

		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}wbam_classifieds" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test isolation.

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );
	}

	private function category(): int {
		$term = wp_insert_term( 'No-Featured ' . wp_generate_password( 6, false ), Classified_Manager::TAXONOMY_CATEGORY );
		return (int) $term['term_id'];
	}

	public function tear_down(): void {
		delete_option( 'wbam_pro_settings' );
		delete_option( 'wbam_pro_featured_packages_removed' );
		parent::tear_down();
	}

	/**
	 * A package that still carries 'featured' => true (an old filter, or a
	 * not-yet-migrated option) must not feature the listing. Only the
	 * Featured upgrade (selected in $input['upgrades']) can.
	 */
	public function test_a_package_marked_featured_no_longer_features_the_listing(): void {
		Settings_Helper::update(
			'classified_packages',
			array(
				array(
					'name'     => 'Legacy Premium',
					'duration' => 90,
					'price'    => 0,
					'featured' => true,
				),
			)
		);

		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );

		$manager    = Classified_Manager::get_instance();
		$classified = $manager->submit(
			$advertiser,
			array(
				'title'           => 'Featured-flag probe',
				'description'     => 'Package says featured; server must ignore it.',
				'categories'      => array( $this->category() ),
				'listing_package' => 0,
			)
		);

		$this->assertNotWPError( $classified, is_wp_error( $classified ) ? $classified->get_error_message() : '' );
		$this->assertSame( 'standard', $classified->listing_type, "A package's 'featured' flag no longer grants Featured status." );
	}

	/** Reflects card 10343726490 step 3: the shared default has no 'featured' key at all any more. */
	public function test_default_listing_packages_have_no_featured_key(): void {
		foreach ( Classified_Manager::default_listing_packages() as $package ) {
			$this->assertArrayNotHasKey( 'featured', $package );
		}
	}

	/**
	 * Upgrade 4.3.15: an existing site's stored packages keep their price and
	 * duration, but the 'featured' key is stripped, and the package's name is
	 * recorded so the one-time admin notice can name it.
	 */
	public function test_upgrade_to_4_3_15_strips_featured_and_records_the_name(): void {
		delete_option( 'wbam_pro_featured_packages_removed' );
		update_option(
			'wbam_pro_settings',
			array(
				'classified_packages' => array(
					array(
						'name'     => 'Standard',
						'duration' => 60,
						'price'    => 5,
						'featured' => false,
					),
					array(
						'name'     => 'Premium',
						'duration' => 90,
						'price'    => 15,
						'featured' => true,
					),
				),
			)
		);

		$method = new \ReflectionMethod( Installer::class, 'upgrade_to_4_3_15' );
		$method->setAccessible( true );
		$method->invoke( null );

		$settings = get_option( 'wbam_pro_settings' );
		foreach ( $settings['classified_packages'] as $package ) {
			$this->assertArrayNotHasKey( 'featured', $package );
		}
		$this->assertSame( 90, $settings['classified_packages'][1]['duration'], 'Duration is untouched.' );
		$this->assertSame( 15, $settings['classified_packages'][1]['price'], 'Price is untouched.' );

		$this->assertSame( array( 'Premium' ), get_option( 'wbam_pro_featured_packages_removed' ), 'Only the package that actually had featured=true is named.' );
	}

	/** No stored packages (the common case: the site never customized them) - nothing to migrate, no notice. */
	public function test_upgrade_to_4_3_15_is_a_no_op_when_no_packages_are_stored(): void {
		delete_option( 'wbam_pro_settings' );
		delete_option( 'wbam_pro_featured_packages_removed' );

		$method = new \ReflectionMethod( Installer::class, 'upgrade_to_4_3_15' );
		$method->setAccessible( true );
		$method->invoke( null );

		$this->assertFalse( get_option( 'wbam_pro_featured_packages_removed' ) );
	}
}
