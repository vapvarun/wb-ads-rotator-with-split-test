<?php
/**
 * A disabled module's frontend surfaces must not render or load assets.
 * The /seller/{slug} route, [wbam_seller_profile] and
 * [wbam_advertiser_wallet] previously ignored the Classifieds/Wallet
 * module switches entirely. Owner decision, card 10342761510.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Pro_Plugin;
use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Advertisers\Advertiser_Shortcodes;

class Test_Classifieds_Wallet_Module_Gates extends Pro_Test_Case {

	private object $advertiser;

	public function set_up(): void {
		parent::set_up();

		$user             = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
	}

	public function tear_down(): void {
		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		$enabled['wallet']      = true;
		Settings_Helper::update( 'enabled_modules', $enabled );
		wp_dequeue_style( 'wbam-pro-classified' );
		wp_dequeue_script( 'wbam-pro-classified' );
		parent::tear_down();
	}

	private function disable( string $module ): void {
		$enabled            = Settings_Helper::get( 'enabled_modules', array() );
		$enabled[ $module ] = false;
		Settings_Helper::update( 'enabled_modules', $enabled );
	}

	public function test_seller_route_404s_when_classifieds_module_is_off(): void {
		$this->disable( 'classifieds' );

		// wp_scripts()/wp_styles() are process-global; another test in this
		// run may have already registered (but not queued) this handle -
		// check direct queue membership, not registration, which is the
		// only thing this handler's early return actually prevents.
		wp_dequeue_style( 'wbam-pro-classified' );

		set_query_var( 'wbam_seller_slug', $this->advertiser->get_slug() );
		global $wp_query;
		$wp_query->is_404 = false;

		Pro_Plugin::get_instance()->handle_seller_profile_request();

		$this->assertTrue( is_404(), 'A disabled Classifieds module must 404 the seller route.' );
		$this->assertFalse( in_array( 'wbam-pro-classified', wp_styles()->queue, true ), 'The 404 branch must return before enqueueing any classified asset.' );
	}

	public function test_seller_profile_shortcode_renders_nothing_when_classifieds_module_is_off(): void {
		$this->disable( 'classifieds' );

		$html = ( new Advertiser_Shortcodes() )->render_seller_profile( array( 'id' => $this->advertiser->id ) );

		$this->assertSame( '', $html );
	}

	public function test_advertiser_wallet_shortcode_renders_nothing_when_wallet_module_is_off(): void {
		$this->disable( 'wallet' );
		wp_set_current_user( $this->advertiser->user_id );

		$html = ( new Advertiser_Shortcodes() )->render_wallet( array() );

		$this->assertSame( '', $html );
	}
}
