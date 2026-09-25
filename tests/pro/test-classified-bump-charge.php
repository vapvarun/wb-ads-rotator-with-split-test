<?php
/**
 * Classified bump charges the Bump Price from Settings, and only for a bump
 * that actually happens.
 *
 * The AJAX handler charged a hardcoded 2 (via wbam_classified_bump_cost)
 * while Settings showed 1, and charged before checking the listing could be
 * bumped at all.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Tests\Helpers\Factory;
use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;

class Test_Classified_Bump_Charge extends Pro_Test_Case {

	private object $advertiser;

	public function set_up(): void {
		parent::set_up();

		// Rows left by earlier runs survive the rollback (lazy DDL commits the
		// test transaction) and collide on the UNIQUE post_id of a reused post ID.
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}wbam_classifieds" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test isolation.

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );
		Settings_Helper::update_module( 'classifieds', 'bump_price', 1.25 );

		$user             = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		Factory::topup_user( $user, 1000 );
	}

	private function make_listing( string $status ): int {
		$manager    = Classified_Manager::get_instance();
		$classified = $manager->create(
			array(
				'title'         => 'Bump probe',
				'description'   => 'Bump me.',
				'advertiser_id' => $this->advertiser->id,
			)
		);
		$this->assertNotWPError( $classified );
		$manager->update( (int) $classified->id, array( 'status' => $status ) );

		return (int) $classified->id;
	}

	public function test_bump_charges_the_configured_price(): void {
		$id     = $this->make_listing( 'active' );
		$before = (float) Credits_Bridge::get_balance( $this->advertiser->id );

		$result = Classified_Manager::get_instance()->bump_by_seller( $id, (int) $this->advertiser->id );

		$this->assertTrue( $result );
		$this->assertSame( 1.25, round( $before - (float) Credits_Bridge::get_balance( $this->advertiser->id ), 2 ), 'Bump must cost the Settings price, not a hardcoded 2.' );
	}

	public function test_a_listing_that_cannot_be_bumped_is_not_charged(): void {
		$id     = $this->make_listing( 'expired' );
		$before = (float) Credits_Bridge::get_balance( $this->advertiser->id );

		$result = Classified_Manager::get_instance()->bump_by_seller( $id, (int) $this->advertiser->id );

		$this->assertWPError( $result );
		$this->assertSame( $before, (float) Credits_Bridge::get_balance( $this->advertiser->id ) );
	}
}
