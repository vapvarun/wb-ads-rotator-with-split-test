<?php
/**
 * A Featured upgrade charges what the Promote screen quotes and records that
 * same amount as the listing's featured fee. Featured is one-time only
 * (card 10343726590, owner decision): the charge is a single fixed-period
 * payment, not divided across billing cycles.
 *
 * can_afford_featured() read a wallet_balance property Advertiser does not
 * have, so every paid upgrade was refused.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Tests\Helpers\Factory;
use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;

class Test_Classified_Featured_Upgrade_Fee extends Pro_Test_Case {

	public function test_one_time_upgrade_charges_the_total_and_records_the_fee(): void {
		// Rows left by earlier runs survive the rollback (lazy DDL commits the
		// test transaction) and collide on the UNIQUE post_id of a reused post ID.
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}wbam_classifieds" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test isolation.

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );

		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		Factory::topup_user( $user, 10000 );

		$manager    = Classified_Manager::get_instance();
		$classified = $manager->create(
			array(
				'title'         => 'Featured probe',
				'description'   => 'Priced at 9.99 itself.',
				'advertiser_id' => $advertiser->id,
				'price'         => 9.99,
			)
		);
		$this->assertNotWPError( $classified );
		$manager->update( (int) $classified->id, array( 'status' => 'active' ) );
		$classified = $manager->get( (int) $classified->id );

		$before = (float) Credits_Bridge::get_balance( $advertiser->id );
		$result = $classified->upgrade_to_featured( 3, 30.0, true );

		$this->assertTrue( $result, is_wp_error( $result ) ? $result->get_error_message() : '' );
		$this->assertSame( 30.0, round( $before - (float) Credits_Bridge::get_balance( $advertiser->id ), 2 ) );
		$this->assertSame( 30.0, (float) $manager->get( (int) $classified->id )->featured_fee, 'The featured record keeps the full one-time charge, not a per-cycle fraction of it.' );
	}
}
