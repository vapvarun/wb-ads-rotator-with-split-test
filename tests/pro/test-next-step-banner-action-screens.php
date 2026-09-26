<?php
/**
 * Next_Step_Banner must not render on an admin "action screen" — a
 * single-purpose form (reject, request changes, adjust balance, …). Part
 * of the admin design-system project: the banner shows only on the plugin
 * overview and on its step's target list screen.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Next_Step_Banner;

/**
 * @group pro
 * @group next-step-banner
 */
class Test_Next_Step_Banner_Action_Screens extends Pro_Test_Case {

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		// All Ads (edit.php?post_type=wbam-ad) - the actual target of the
		// "create-first-ad" step a fresh, empty install resolves to (owner
		// decision, admin polish audit item 3a: the banner only shows on
		// the Dashboard and the one screen its own button opens).
		set_current_screen( 'edit-wbam-ad' );
	}

	public function tear_down(): void {
		unset( $_GET['action'] );
		set_current_screen( 'front' );
		parent::tear_down();
	}

	/**
	 * Sanity check: on the plain list screen (no action param) the banner
	 * does render, given the fresh test install has zero ads. Proves the
	 * action-screen assertions below aren't passing vacuously.
	 */
	public function test_banner_renders_on_the_plain_list_screen(): void {
		unset( $_GET['action'] );

		ob_start();
		Next_Step_Banner::maybe_render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'wbam-next-step-banner', $output );
	}

	/**
	 * Off its own step's target screen (Classifieds, say), the banner does
	 * not render even with no action param - only the Dashboard and the
	 * step's own target screen show it (item 3a).
	 */
	public function test_banner_is_hidden_on_a_screen_that_is_not_the_steps_target(): void {
		unset( $_GET['action'] );
		set_current_screen( 'wbam-ad_page_wbam-classifieds' );
		// Every WBAM submenu lives under edit.php?post_type=wbam-ad, so on a
		// real request its screen carries post_type=wbam-ad too.
		get_current_screen()->post_type = 'wbam-ad';

		ob_start();
		Next_Step_Banner::maybe_render();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/**
	 * @dataProvider provider_action_screen_values
	 */
	public function test_banner_is_hidden_on_action_screens( string $action ): void {
		$_GET['action'] = $action;

		ob_start();
		Next_Step_Banner::maybe_render();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	public function provider_action_screen_values(): array {
		return array(
			'single reject form'  => array( 'reject_form' ),
			'bulk reject form'    => array( 'bulk_reject_form' ),
			'request changes'     => array( 'changes_form' ),
			'adjust balance'      => array( 'adjust_balance' ),
			'view (pre-existing)' => array( 'view' ),
			'edit (pre-existing)' => array( 'edit' ),
			'add (pre-existing)'  => array( 'add' ),
			'new (Campaigns/A-B)' => array( 'new' ),
		);
	}

	/**
	 * Add New Ad (post-new.php?post_type=wbam-ad) never carries an `action`
	 * query arg — core marks it via WP_Screen::$action = 'add' instead. The
	 * banner must key off that too, not just $_GET['action'].
	 */
	public function test_banner_is_hidden_on_add_new_ad_screen(): void {
		unset( $_GET['action'] );
		set_current_screen( 'post-new.php' );
		get_current_screen()->post_type = 'wbam-ad';

		ob_start();
		Next_Step_Banner::maybe_render();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}
}
