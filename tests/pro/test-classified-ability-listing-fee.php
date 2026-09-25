<?php
/**
 * The create-classified ability charges the listing fee.
 *
 * It called Classified_Manager::create() directly, the low-level insert, so
 * a listing posted through the Abilities API (MCP, AI agents, REST
 * /wp-abilities) paid no package fee and skipped the credit gate and plan
 * limits that the portal and REST apply through submit().
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Pro_Abilities;
use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;

class Test_Classified_Ability_Listing_Fee extends Pro_Test_Case {

	public function test_ability_listing_pays_the_package_fee(): void {
		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );

		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		Advertiser_Manager::get_instance()->update_status( (int) $advertiser->id, 'active' );
		\Wbcom\Credits\Credits::topup( 'wbam-pro', $user, 10000, 'seed' );
		wp_set_current_user( $user );

		$term = wp_insert_term( 'Ability ' . wp_generate_password( 6, false ), Classified_Manager::TAXONOMY_CATEGORY );

		$result = ( new Pro_Abilities() )->execute_create_classified(
			array(
				'title'           => 'Posted by an agent',
				'description'     => 'Listing created through the ability.',
				'categories'      => array( (int) $term['term_id'] ),
				'listing_package' => 1, // Default "Standard": 5.00.
			)
		);

		$this->assertNotWPError( $result );
		$this->assertSame( 9500, (int) \Wbcom\Credits\Credits::get_balance( 'wbam-pro', $user ), 'The Standard package fee is charged.' );
		$this->assertSame( 'pending', $result['status'], 'Moderation still applies.' );

		wp_set_current_user( 0 );
	}
}
