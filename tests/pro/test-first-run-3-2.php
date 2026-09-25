<?php
/**
 * First-run go-live checklist (10335669762): demo123 password rotation,
 * the classifieds page following its module on/off, the ad-label default
 * on a fresh install, and the sample sidebar widget actually landing in
 * a sidebar.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Installer;
use WBAM_Pro\Core\Settings_Helper;

class Test_First_Run_3_2 extends Pro_Test_Case {

	/**
	 * Demo advertisers were created with the published password 'demo123!'.
	 * The 4.3.1 upgrade must rotate any account still using it, and must
	 * leave an unrelated user's password alone.
	 */
	public function test_demo123_password_is_rotated_on_upgrade(): void {
		$demo_id = wp_insert_user(
			array(
				'user_login' => 'techstartup',
				'user_pass'  => 'demo123!',
				'user_email' => 'techstartup-rotation@example.com',
			)
		);
		$this->assertIsInt( $demo_id );

		$other_id = wp_insert_user(
			array(
				'user_login' => 'techstartup-other-password',
				'user_pass'  => 'a-real-password-123',
				'user_email' => 'not-demo@example.com',
			)
		);
		$this->assertIsInt( $other_id );

		$demo_before = get_userdata( $demo_id );
		$this->assertTrue( wp_check_password( 'demo123!', $demo_before->user_pass, $demo_id ) );

		$method = new \ReflectionMethod( Installer::class, 'upgrade_to_4_3_1' );
		$method->setAccessible( true );
		$method->invoke( null );

		$demo_after = get_userdata( $demo_id );
		$this->assertFalse(
			wp_check_password( 'demo123!', $demo_after->user_pass, $demo_id ),
			'demo123! must be rotated to a random password.'
		);

		$other_after = get_userdata( $other_id );
		$this->assertTrue(
			wp_check_password( 'a-real-password-123', $other_after->user_pass, $other_id ),
			'A user not on demo123! must not be touched.'
		);
	}

	/**
	 * Switching the classifieds module off drafts the listings page (and
	 * flags it as auto-drafted); switching it back on republishes only the
	 * page this sync drafted.
	 */
	public function test_sync_classifieds_page_follows_module_toggle(): void {
		$enabled                = (array) Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );

		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Classifieds',
				'post_status' => 'publish',
			)
		);
		update_option( 'wbam_page_classifieds', $page_id );

		// Toggle off - the update_option_wbam_pro_settings hook fires sync_classifieds_page().
		$enabled['classifieds'] = false;
		Settings_Helper::update( 'enabled_modules', $enabled );

		$drafted = get_post( $page_id );
		$this->assertSame( 'draft', $drafted->post_status );
		$this->assertEquals( 1, get_post_meta( $page_id, '_wbam_auto_drafted', true ) );

		// Toggle back on - restores only because we drafted it.
		$enabled['classifieds'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );

		$restored = get_post( $page_id );
		$this->assertSame( 'publish', $restored->post_status );
		$this->assertSame( '', get_post_meta( $page_id, '_wbam_auto_drafted', true ) );
	}

	/**
	 * A fresh install with no saved wbam_settings option must still label
	 * ads "Advertisement" rather than showing nothing.
	 */
	public function test_ad_label_defaults_to_advertisement_with_no_saved_settings(): void {
		delete_option( 'wbam_settings' );

		$label = \WBAM\Core\Settings_Helper::get( 'ad_label', __( 'Advertisement', 'wb-ads-rotator-with-split-test' ) );

		$this->assertSame( 'Advertisement', $label );
	}

	/**
	 * The wizard's widget-placement sample ad is invisible unless a WB Ad
	 * widget actually exists in a sidebar. create_sample_ads() must place
	 * one in the first registered sidebar.
	 */
	public function test_sample_widget_ad_is_placed_in_first_sidebar(): void {
		global $wp_registered_sidebars;
		$previous_sidebars = $wp_registered_sidebars;
		$wp_registered_sidebars = array(
			'sidebar-1' => array( 'id' => 'sidebar-1', 'name' => 'Primary Sidebar' ),
		);

		delete_option( 'widget_wbam_ad_widget' );
		delete_option( 'sidebars_widgets' );

		try {
			$wizard = new \WBAM\Admin\Setup_Wizard();
			$method = new \ReflectionMethod( $wizard, 'create_sample_ads' );
			$method->setAccessible( true );
			$method->invoke( $wizard, array( 'sidebar_widget' ) );

			$sidebars_widgets = wp_get_sidebars_widgets();
			$this->assertNotEmpty( $sidebars_widgets['sidebar-1'] ?? array() );

			$widget_id = reset( $sidebars_widgets['sidebar-1'] );
			$this->assertStringStartsWith( 'wbam_ad_widget-', $widget_id );

			$number    = (int) str_replace( 'wbam_ad_widget-', '', $widget_id );
			$instances = get_option( 'widget_wbam_ad_widget', array() );
			$this->assertArrayHasKey( $number, $instances );
			$this->assertNotEmpty( $instances[ $number ]['ad_id'] );
		} finally {
			$wp_registered_sidebars = $previous_sidebars;
		}
	}
}
