<?php
/**
 * The portal shows a balance, Add Funds and the Balance tab only when there
 * is money to use (owner 2026-09-27, card 10344408245): an active
 * advertiser, a seller with a listing while a paid upgrade is on, or anyone
 * with money in the ledger. A buyer is not greeted with "post your first".
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Tests\Helpers\Factory;
use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Advertisers\Dashboard_Tabs;

class Test_Portal_Balance_Only_With_Money extends Pro_Test_Case {

	public function set_up(): void {
		$this->snapshot_options( array( 'wbam_pro_settings', 'wbam_pro_classifieds_settings' ) );
		parent::set_up();

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		$enabled['wallet']      = true;
		Settings_Helper::update( 'enabled_modules', $enabled );

		// Everyone may post, as on the site the card was walked on.
		Settings_Helper::update_module( 'classifieds', 'allow_user_posting', 1 );
		Settings_Helper::update_module( 'classifieds', 'user_posting_roles', array( 'subscriber' ) );
		Settings_Helper::update_module( 'classifieds', 'featured_price', 5 );
	}

	public function tear_down(): void {
		unset( $_GET['tab'] );
		parent::tear_down();
	}

	private function member(): object {
		$user_id = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		return Advertiser_Manager::get_instance()->get_or_create_member( $user_id );
	}

	private function listing( int $advertiser_id ): void {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'wbam_classifieds',
			array(
				'post_id'       => self::factory()->post->create( array( 'post_type' => 'wbam-classified' ) ),
				'advertiser_id' => $advertiser_id,
				'status'        => 'active',
			)
		);
	}

	private function portal( object $member, string $tab = '' ): string {
		wp_set_current_user( (int) $member->user_id );
		if ( $tab ) {
			$_GET['tab'] = $tab;
		}
		return do_shortcode( '[wbam_advertiser_dashboard]' );
	}

	private function nav( string $html ): string {
		$nav = substr( $html, (int) strpos( $html, 'id="wbam-portal-nav-list"' ) );
		return substr( $nav, 0, (int) strpos( $nav, '</nav>' ) );
	}

	public function test_a_buyer_sees_no_balance_and_no_post_your_first(): void {
		$buyer = $this->member();
		update_user_meta( (int) $buyer->user_id, '_wbam_favorite_classifieds', array( 123 ) );

		$html = $this->portal( $buyer );

		$this->assertFalse( Dashboard_Tabs::shows_balance( $buyer ) );
		$this->assertStringNotContainsString( 'wbam-sidebar-balance', $html );
		$this->assertStringNotContainsString( 'tab=wallet', $this->nav( $html ) );
		$this->assertStringNotContainsString( 'Welcome! Post your first', $html );
	}

	public function test_a_brand_new_member_is_still_greeted(): void {
		$html = $this->portal( $this->member() );

		$this->assertStringNotContainsString( 'wbam-sidebar-balance', $html );
		$this->assertStringContainsString( 'Welcome! Post your first', $html );
	}

	public function test_a_seller_with_a_listing_and_a_paid_upgrade_sees_the_balance(): void {
		$seller = $this->member();
		$this->listing( (int) $seller->id );

		$html = $this->portal( $seller );

		$this->assertStringContainsString( 'wbam-sidebar-balance', $html );
		$this->assertStringContainsString( 'tab=wallet', $this->nav( $html ) );
	}

	public function test_a_seller_on_a_site_with_free_upgrades_sees_no_balance(): void {
		foreach ( array( 'featured', 'highlighted', 'urgent', 'top', 'bump' ) as $upgrade ) {
			Settings_Helper::update_module( 'classifieds', $upgrade . '_price', 0 );
		}
		$seller = $this->member();
		$this->listing( (int) $seller->id );

		$this->assertFalse( Dashboard_Tabs::shows_balance( $seller ) );
	}

	public function test_money_in_the_ledger_is_never_hidden(): void {
		$buyer = $this->member();
		Factory::topup_user( (int) $buyer->user_id, 500 );

		$this->assertTrue( Dashboard_Tabs::shows_balance( $buyer ) );
	}

	public function test_the_balance_tab_still_opens_from_a_top_up_link(): void {
		$html = $this->portal( $this->member(), 'wallet' );

		$this->assertStringNotContainsString( 'not available', $html, 'Membership "Top up" links must land.' );
	}
}
