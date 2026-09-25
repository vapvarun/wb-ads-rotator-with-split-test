<?php
/**
 * Every call-to-action in a customer email opens a real page.
 *
 * 'Edit Your Ad', 'View Results', 'Add Funds' and the weekly report button
 * pointed at hardcoded /advertiser-portal/... slugs that no install has, and
 * 'Go to Your Portal' on the approval email opened wp-login.php. Links now
 * come from the configured advertiser dashboard page, and fall back to the
 * home page (never a 404) when that page is not set up.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Campaigns\Campaign;
use WBAM_Pro\Modules\Notifications\Email_Notifications;

class Test_Email_Links_Resolve extends Pro_Test_Case {

	private array $sent = array();
	private object $advertiser;
	private int $ad_id;

	public function set_up(): void {
		parent::set_up();

		$user             = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		$this->ad_id      = (int) self::factory()->post->create(
			array(
				'post_type'   => 'wbam-ad',
				'post_author' => $user,
				'post_title'  => 'Links ad',
			)
		);

		$this->sent = array();
		add_filter( 'pre_wp_mail', array( $this, 'capture' ), 10, 2 );
		add_filter( 'wbam_campaign_has_linked_ads', '__return_true' );
	}

	public function tear_down(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'capture' ), 10 );
		remove_filter( 'wbam_campaign_has_linked_ads', '__return_true' );
		delete_option( 'wbam_page_advertiser_dashboard' );
		parent::tear_down();
	}

	public function capture( $short_circuit, $atts ) {
		$this->sent[] = $atts;
		return true;
	}

	private function send_all(): void {
		$notifications = Email_Notifications::get_instance();
		$advertiser    = $this->advertiser;
		$ad            = get_post( $this->ad_id );
		$submission    = new class( $advertiser, $ad ) {
			public $id = 7;
			public $status = 'changes_requested';
			private $advertiser;
			private $ad;
			public function __construct( $advertiser, $ad ) {
				$this->advertiser = $advertiser;
				$this->ad         = $ad;
			}
			public function get_advertiser() {
				return $this->advertiser;
			}
			public function get_ad() {
				return $this->ad;
			}
		};

		$campaign                = new Campaign();
		$campaign->id            = 99;
		$campaign->advertiser_id = (int) $advertiser->id;
		$campaign->name          = 'Links campaign';
		$campaign->budget        = 10;
		$campaign->spent         = 4;

		$notifications->send_advertiser_approved( $advertiser );
		$notifications->send_ad_changes_requested( $submission, 'Tighten the headline.' );
		$notifications->send_campaign_completed( $campaign );
		$notifications->send_campaign_budget_low( $campaign );
	}

	private function hrefs(): array {
		$hrefs = array();
		foreach ( $this->sent as $mail ) {
			preg_match_all( '/href="([^"]*)"/', $mail['message'], $m );
			$hrefs = array_merge( $hrefs, $m[1] );
		}
		return $hrefs;
	}

	public function test_ctas_open_the_configured_dashboard_page(): void {
		$page = (int) self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'Advertiser Dashboard',
				'post_content' => '[wbam_advertiser_dashboard]',
			)
		);
		update_option( 'wbam_page_advertiser_dashboard', $page );
		$dashboard = get_permalink( $page );

		$this->send_all();
		$this->assertCount( 4, $this->sent );

		$hrefs = implode( "\n", $this->hrefs() );
		$this->assertStringNotContainsString( '/advertiser-portal/', $hrefs );
		$this->assertStringNotContainsString( 'wp-login.php', $hrefs, 'Approval CTA opens the portal, not the login screen.' );

		$this->assertStringContainsString( 'href="' . $dashboard . '"', $this->sent[0]['message'], 'Go to Your Portal' );
		$this->assertStringContainsString( esc_url( add_query_arg( array( 'tab' => 'ads', 'action' => 'edit', 'ad_id' => $this->ad_id ), $dashboard ) ), $this->sent[1]['message'], 'Edit Your Ad' );
		$this->assertStringContainsString( esc_url( add_query_arg( 'tab', 'campaigns', $dashboard ) ), $this->sent[2]['message'], 'View Results' );
		$this->assertStringContainsString( esc_url( add_query_arg( 'tab', 'wallet', $dashboard ) ), $this->sent[3]['message'], 'Add Funds' );
	}

	public function test_without_a_dashboard_page_links_fall_back_to_the_home_page(): void {
		global $wpdb;
		// No page carries the dashboard shortcode or slug in this test.
		$wpdb->query( "UPDATE {$wpdb->posts} SET post_status = 'draft' WHERE post_type = 'page'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		delete_option( 'wbam_page_advertiser_dashboard' );
		$this->assertSame( '', wbam_get_portal_url() );

		$this->send_all();

		foreach ( $this->hrefs() as $href ) {
			if ( 0 === strpos( $href, 'mailto:' ) ) {
				continue;
			}
			$this->assertStringStartsWith( home_url(), $href, 'Every link is absolute and on this site: ' . $href );
			$this->assertStringNotContainsString( '/advertiser-portal/', $href );
		}
	}
}
