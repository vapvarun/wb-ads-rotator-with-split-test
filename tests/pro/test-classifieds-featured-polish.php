<?php
/**
 * Classifieds polish (card 10344005566): the listing edit screen has one
 * Featured control (no second Listing Type select, no unexplained
 * "Premium"), the listing-live email does not upsell featuring to a
 * listing that is already featured and links to Promote, and the renew
 * reminder links straight to Promote.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Admin\Classified_Meta_Box;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;
use WBAM_Pro\Modules\Notifications\Email_Notifications;

class Test_Classifieds_Featured_Polish extends Pro_Test_Case {

	private object $advertiser;
	private object $classified;
	private array $post_snapshot;

	public function set_up(): void {
		parent::set_up();
		$this->post_snapshot = $_POST;

		$user             = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );

		$term       = wp_insert_term( 'Polish ' . wp_generate_password( 6, false ), Classified_Manager::TAXONOMY_CATEGORY );
		$classified = Classified_Manager::get_instance()->submit(
			$this->advertiser,
			array(
				'title'      => 'Blue chair',
				'categories' => array( (int) $term['term_id'] ),
			)
		);
		$this->assertNotWPError( $classified );
		$this->classified = Classified_Manager::get_instance()->get( (int) $classified->id );

		$dashboard = (int) self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '[wbam_advertiser_dashboard]',
			)
		);
		update_option( 'wbam_page_advertiser_dashboard', $dashboard );

		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function tear_down(): void {
		$_POST = $this->post_snapshot;
		delete_option( 'wbam_page_advertiser_dashboard' );
		parent::tear_down();
	}

	private function email( string $template ): string {
		return Email_Notifications::get_template(
			$template,
			array(
				'user'       => get_user_by( 'id', $this->advertiser->user_id ),
				'advertiser' => $this->advertiser,
				'classified' => $this->classified,
				'days_left'  => 3,
				'renew_url'  => 'https://example.org/renew',
			)
		);
	}

	public function test_edit_screen_has_one_featured_control(): void {
		ob_start();
		Classified_Meta_Box::get_instance()->render_meta_box( get_post( $this->classified->post_id ) );
		$html = ob_get_clean();

		$this->assertStringNotContainsString( 'wbam_listing_type', $html );
		$this->assertStringNotContainsString( 'Premium', $html );
	}

	public function test_unticking_featured_unfeatures_a_premium_listing(): void {
		$this->classified->listing_type = 'premium';
		$this->classified->save();

		$_POST = array( 'wbam_classified_nonce' => wp_create_nonce( 'wbam_classified_meta_box' ) );
		Classified_Meta_Box::get_instance()->save_meta_box( $this->classified->post_id, get_post( $this->classified->post_id ) );

		$this->assertSame( 'standard', Classified_Manager::get_instance()->get( (int) $this->classified->id )->listing_type );
	}

	public function test_live_email_does_not_upsell_featuring_to_a_featured_listing(): void {
		$this->classified->listing_type = 'featured';

		$html = $this->email( 'classified-approved' );

		$this->assertStringNotContainsString( 'Consider featuring', $html );
		$this->assertStringNotContainsString( 'Feature your listing', $html );
	}

	public function test_live_email_upgrade_link_opens_promote_for_this_listing(): void {
		$html = html_entity_decode( $this->email( 'classified-approved' ) );

		$this->assertStringContainsString( 'action=promote', $html );
		$this->assertStringContainsString( 'classified_id=' . $this->classified->id, $html );
	}

	public function test_renew_reminder_links_to_promote(): void {
		$html = html_entity_decode( $this->email( 'classified-expiring' ) );

		$this->assertStringContainsString( 'action=promote', $html );
		$this->assertStringContainsString( 'classified_id=' . $this->classified->id, $html );
	}
}
