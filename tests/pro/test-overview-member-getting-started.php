<?php
/**
 * BC#10339750662 item 8: the Overview tab's "Welcome! Get started" checklist
 * was gated on $wbam_ad_ok (an approved display-ad advertiser) — a member
 * selling classifieds on a classifieds-enabled site never qualifies for that
 * branch, so a brand-new seller with zero listings got no onboarding
 * guidance at all, just an empty dashboard.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Template_Loader;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

class Test_Overview_Member_Getting_Started extends Pro_Test_Case {

	public function set_up(): void {
		parent::set_up();

		if ( ! wbam_is_classifieds_enabled() ) {
			$this->markTestSkipped( 'Classifieds module is disabled on this install; the member getting-started branch only applies when it is on.' );
		}

		// Same DDL-implicit-commit gotcha Pro_Test_Case already works around for
		// the credits ledger and membership tables: wbam_advertisers is created
		// lazily too, so the CREATE TABLE on first use commits the surrounding
		// transaction and every row after that survives this test's rollback.
		// WP's own user-ID auto-increment gets reused across tests in the same
		// run, so a leftover 'active' row from an earlier test's user can be
		// silently reattached to this test's brand-new factory user.
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'wbam_advertisers' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test isolation, known table name.
	}

	private function render_overview( $advertiser ): string {
		return (string) Template_Loader::load_template( 'portal/tabs/overview', array( 'advertiser' => $advertiser ), true );
	}

	public function test_member_with_no_listings_sees_the_getting_started_prompt(): void {
		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create_member( $user );

		$html = $this->render_overview( $advertiser );

		$this->assertStringContainsString(
			'Post your first listing',
			$html,
			'A member with zero classifieds must see onboarding guidance, not an empty dashboard.'
		);
	}

	public function test_member_with_an_existing_listing_does_not_see_the_prompt(): void {
		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create_member( $user );
		$post_id    = self::factory()->post->create( array( 'post_type' => 'wbam-classified' ) );

		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'wbam_classifieds',
			array(
				'post_id'       => $post_id,
				'advertiser_id' => $advertiser->id,
				'status'        => 'pending',
			)
		);

		$html = $this->render_overview( $advertiser );

		$this->assertStringNotContainsString(
			'Post your first listing',
			$html,
			'A member who already has a listing (even pending) is past onboarding, not "new".'
		);
	}

	public function test_active_advertiser_never_sees_the_member_prompt(): void {
		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->create( $user, array( 'status' => 'active' ) );

		$html = $this->render_overview( $advertiser );

		$this->assertStringNotContainsString(
			'Post your first listing',
			$html,
			'An approved display-ad advertiser gets the advertiser onboarding branch, not the member one.'
		);
	}
}
