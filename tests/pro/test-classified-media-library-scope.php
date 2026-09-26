<?php
/**
 * wp_enqueue_media() (a heavy JS/CSS bundle for the WP media library UI)
 * only loads where a logged-in user actually uploads - the single-page
 * submit/edit form. Anonymous visitors browsing/searching/viewing a
 * listing must never get it. Owner decision, card 10342761510.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Shortcodes;

class Test_Classified_Media_Library_Scope extends Pro_Test_Case {

	private object $advertiser;
	private object $classified;

	public function set_up(): void {
		parent::set_up();

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );
		$classifieds                     = get_option( 'wbam_pro_classifieds_settings', array() );
		$classifieds['require_approval'] = false;
		update_option( 'wbam_pro_classifieds_settings', $classifieds );

		$user             = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		$this->classified = Classified_Manager::get_instance()->create(
			array(
				'title'          => 'Media scope listing',
				'description'    => 'Anon visitor must not load the media library.',
				'advertiser_id'  => $this->advertiser->id,
				'status'         => 'active',
				'contact_method' => 'form',
			)
		);
		$this->assertNotWPError( $this->classified );
	}

	public function tear_down(): void {
		wp_dequeue_script( 'media-editor' );
		wp_deregister_script( 'media-editor' );
		parent::tear_down();
	}

	public function test_anonymous_visitor_viewing_a_listing_does_not_load_the_media_library(): void {
		// wp_scripts() is process-global; another test in this run may have
		// already enqueued the media library and never dequeued it. Start
		// from a known "not yet on" state rather than depending on order.
		wp_dequeue_script( 'media-editor' );

		wp_set_current_user( 0 );

		$this->go_to( get_permalink( $this->classified->post_id ) );
		$GLOBALS['wp_query']->the_post();

		Classified_Shortcodes::get_instance()->single_classified_content( '' );

		$this->assertFalse( in_array( 'media-editor', wp_scripts()->queue, true ), 'A read-only visitor must never trigger the media library.' );
	}

	public function test_logged_in_user_submitting_a_listing_gets_the_media_library(): void {
		wp_set_current_user( $this->advertiser->user_id );

		$classifieds                          = get_option( 'wbam_pro_classifieds_settings', array() );
		$classifieds['submission_form_type']  = 'single';
		update_option( 'wbam_pro_classifieds_settings', $classifieds );

		Classified_Shortcodes::get_instance()->render_submit_form( array() );

		$this->assertTrue( wp_script_is( 'media-editor', 'enqueued' ), 'The submit form must load the media library for its image-upload field.' );
	}
}
