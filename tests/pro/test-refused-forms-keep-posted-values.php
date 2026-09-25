<?php
/**
 * New QA step on card 10339874920: a refused Campaign or Package save must
 * re-render its form with the values the owner just typed, not an empty (new)
 * or stale (edit) form. Campaigns re-renders in the same request
 * (handle_campaign_form_save() returns instead of redirecting on refusal, so
 * $_POST is still live); Packages always redirects even on refusal
 * (handle_package_actions()), so its posted data has to survive the
 * round trip via a one-shot transient.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Pro_Admin;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

class Test_Refused_Forms_Keep_Posted_Values extends Pro_Test_Case {

	private function render_private( Pro_Admin $admin, string $method, array $args = array() ): string {
		$reflection = new \ReflectionMethod( Pro_Admin::class, $method );
		$reflection->setAccessible( true );
		ob_start();
		$reflection->invokeArgs( $admin, $args );
		return ob_get_clean();
	}

	public function tear_down(): void {
		$_POST    = array();
		$_REQUEST = array();
		parent::tear_down();
	}

	/**
	 * A new campaign with a budget but a zero rate is refused
	 * (Pro_Admin::handle_campaign_form_save() — "rate is zero, so nothing
	 * would ever stop it"). Refused saves return instead of redirecting, so
	 * $_POST is still populated when render_campaign_form() runs right
	 * after in the same request.
	 */
	public function test_refused_campaign_form_rerenders_with_posted_values(): void {
		$user       = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST = array(
			'wbam_save_campaign'  => '1',
			'wbam_campaign_nonce' => wp_create_nonce( 'wbam_save_campaign' ),
			'advertiser_id'       => (string) $advertiser->id,
			'name'                => 'Typed Campaign Name QA',
			'pricing_model'       => 'cpm',
			'price_per_unit'      => '0',
			'budget'              => '50',
			'status'              => 'draft',
		);
		$_REQUEST = $_POST;

		$admin = new Pro_Admin();
		$this->render_private( $admin, 'handle_campaign_form_save' ); // Refused: prints a notice, returns (no redirect).
		$html = $this->render_private( $admin, 'render_campaign_form', array( 0 ) );

		$this->assertStringContainsString( 'Typed Campaign Name QA', $html );
	}

	/**
	 * A new metered package with a zero rate and no cap is refused
	 * (Package::metered_prepay_problem()). Unlike campaigns,
	 * handle_package_actions() always redirects, even on refusal, so the
	 * posted name only survives via the refill transient.
	 */
	public function test_refused_package_form_rerenders_with_posted_values(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST = array(
			'action'         => 'save_package',
			'_wpnonce'       => wp_create_nonce( 'wbam_save_package' ),
			'name'           => 'Typed Package Name QA',
			'pricing_model'  => 'cpm',
			'price_per_unit' => '0',
			'status'         => 'active',
		);
		$_REQUEST = $_POST;

		$admin        = new Pro_Admin();
		$redirected   = false;
		$catch_redirect = function () use ( &$redirected ) {
			$redirected = true;
			throw new \RuntimeException( 'redirected' );
		};
		add_filter( 'wp_redirect', $catch_redirect );
		$handle = new \ReflectionMethod( Pro_Admin::class, 'handle_package_actions' );
		$handle->setAccessible( true );
		try {
			$handle->invoke( $admin ); // No output on this path; ob_start() isn't needed and would leak past the throw below.
		} catch ( \RuntimeException $e ) {
			// Expected: the refusal redirects back to the form.
		}
		remove_filter( 'wp_redirect', $catch_redirect );
		$this->assertTrue( $redirected, 'Expected the refused package save to redirect.' );

		$_POST    = array();
		$_REQUEST = array();

		$html = $this->render_private( $admin, 'render_package_form', array( 0 ) );

		$this->assertStringContainsString( 'Typed Package Name QA', $html );
		$this->assertStringContainsString( 'Add New Package', $html, 'A refused *new* package must still say Add, not Edit.' );
	}
}
