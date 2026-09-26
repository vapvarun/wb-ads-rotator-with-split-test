<?php
/**
 * Dashboard tabs by role (Basecamp 10342786531, QA reject after the P0):
 * - a pending display-ad applicant sees only what they can use (no Wallet,
 *   no Membership) until approved;
 * - a seller who has only RECEIVED messages is Selling, not Buying;
 * - `wbam_pro_dashboard_tabs` lets a site change the final tab list;
 * - old ?tab=messages / ?tab=inquiries links open the Inbox.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Advertisers\Dashboard_Tabs;

class Test_Dashboard_Tabs_By_Role extends Pro_Test_Case {

	private $classifieds_settings;

	public function set_up(): void {
		parent::set_up();
		$this->classifieds_settings     = get_option( 'wbam_pro_classifieds_settings' );
		$settings                       = (array) $this->classifieds_settings;
		$settings['allow_user_posting'] = 0;
		update_option( 'wbam_pro_classifieds_settings', $settings );
	}

	public function tear_down(): void {
		update_option( 'wbam_pro_classifieds_settings', $this->classifieds_settings );
		unset( $_GET['tab'] );
		parent::tear_down();
	}

	/**
	 * The tab slugs in the rendered portal nav.
	 */
	private function nav_tabs( int $user_id ): array {
		wp_set_current_user( $user_id );
		$html = do_shortcode( '[wbam_advertiser_dashboard]' );
		$nav  = substr( $html, (int) strpos( $html, 'id="wbam-portal-nav-list"' ) );
		$nav  = substr( $nav, 0, (int) strpos( $nav, '</nav>' ) );
		preg_match_all( '/[?&](?:amp;|#038;)?tab=([a-z-]+)/', $nav, $m );
		return $m[1];
	}

	private function make_listing( int $advertiser_id ): int {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'wbam_classifieds',
			array(
				'post_id'       => self::factory()->post->create( array( 'post_type' => 'wbam-classified' ) ),
				'advertiser_id' => $advertiser_id,
				'status'        => 'active',
			)
		);
		return (int) $wpdb->insert_id;
	}

	private function make_thread( int $user_a, int $user_b, int $classified_id ): void {
		global $wpdb;
		$ids = array( $user_a, $user_b );
		sort( $ids );
		$wpdb->insert(
			$wpdb->prefix . 'wbam_message_threads',
			array(
				'participant_a' => $ids[0],
				'participant_b' => $ids[1],
				'classified_id' => $classified_id,
				'status'        => 'active',
			)
		);
	}

	public function test_a_pending_applicant_sees_no_wallet_or_membership(): void {
		$user_id = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		Advertiser_Manager::get_instance()->create( $user_id, array( 'status' => 'pending' ) );

		$tabs = $this->nav_tabs( $user_id );

		$this->assertContains( 'overview', $tabs );
		$this->assertNotContains( 'wallet', $tabs );
		$this->assertNotContains( 'membership', $tabs );
	}

	public function test_a_seller_who_only_received_messages_is_selling_not_buying(): void {
		$manager   = Advertiser_Manager::get_instance();
		$seller_id = (int) self::factory()->user->create();
		$buyer_id  = (int) self::factory()->user->create();
		$seller    = $manager->get_or_create_member( $seller_id );
		$buyer     = $manager->get_or_create_member( $buyer_id );
		$this->make_thread( $buyer_id, $seller_id, $this->make_listing( (int) $seller->id ) );

		$seller_groups = Dashboard_Tabs::derive_groups( $seller, $seller_id );
		$buyer_groups  = Dashboard_Tabs::derive_groups( $buyer, $buyer_id );

		$this->assertTrue( $seller_groups['selling'], 'The seller side of a listing thread is Selling.' );
		$this->assertFalse( $seller_groups['buying'], 'Receiving a message does not make a seller a buyer.' );
		$this->assertTrue( $buyer_groups['buying'], 'The member who wrote about someone else\'s listing is Buying.' );
		$this->assertFalse( $buyer_groups['selling'] );
	}

	public function test_the_dashboard_tabs_filter_sets_the_final_list(): void {
		$user_id = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		Advertiser_Manager::get_instance()->get_or_create_member( $user_id );

		$seen = null;
		$cb   = function ( $tabs, $groups, $advertiser ) use ( &$seen ) {
			$seen = array( $groups, $advertiser );
			return array_values( array_diff( $tabs, array( 'profile' ) ) );
		};
		add_filter( 'wbam_pro_dashboard_tabs', $cb, 10, 3 );
		$tabs = $this->nav_tabs( $user_id );
		remove_filter( 'wbam_pro_dashboard_tabs', $cb, 10 );

		$this->assertNotContains( 'profile', $tabs );
		$this->assertArrayHasKey( 'selling', $seen[0] );
		$this->assertSame( $user_id, (int) $seen[1]->user_id );
	}

	public function test_old_messages_and_inquiries_links_open_the_inbox(): void {
		$user_id = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		Advertiser_Manager::get_instance()->get_or_create_member( $user_id );
		update_user_meta( $user_id, '_wbam_favorite_classifieds', array( 123 ) );
		wp_set_current_user( $user_id );

		foreach ( array( 'messages', 'inquiries' ) as $old ) {
			$_GET['tab'] = $old;
			$html        = do_shortcode( '[wbam_advertiser_dashboard]' );
			$this->assertStringNotContainsString( 'Tab not available', $html, "?tab={$old} must open the Inbox." );
		}
	}
}
