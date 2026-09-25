<?php
/**
 * Cents survive every admin money input.
 *
 * Advertiser_Manager::adjust_balance() and the REST balance update cast to
 * (int), so adjusting by 2.50 moved 2.00; the Adjust Balance screen used
 * absint(); min_balance_to_post was saved with absint(); a package's
 * per-unit rate was rounded to 2 decimals though the column holds 4.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Core\Pro_Admin;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Packages\Package_Manager;

class Test_Money_Cents_Survive extends Pro_Test_Case {

	private object $advertiser;

	public function set_up(): void {
		parent::set_up();

		$user             = (int) self::factory()->user->create();
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function tear_down(): void {
		$_POST = array();
		remove_all_filters( 'wp_redirect' );
		parent::tear_down();
	}

	public function test_manager_adjust_keeps_cents(): void {
		$this->assertTrue( Advertiser_Manager::get_instance()->adjust_balance( $this->advertiser->id, 2.50, 'cents' ) );
		$this->assertSame( 2.5, (float) Credits_Bridge::get_balance( $this->advertiser->id ) );
	}

	public function test_rest_balance_update_keeps_cents(): void {
		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		do_action( 'rest_api_init' );

		$request = new \WP_REST_Request( 'POST', '/wbam-pro/v1/admin/advertisers/' . $this->advertiser->id );
		$request->set_param( 'balance', 2.5 );
		$response = rest_do_request( $request );

		$this->assertSame( 2.5, (float) Credits_Bridge::get_balance( $this->advertiser->id ) );
		$this->assertSame( 2.5, $response->get_data()['balance'] );
	}

	/**
	 * Run a Pro_Admin form handler that ends in wp_safe_redirect() + exit.
	 */
	private function run_redirecting_handler( string $method ): void {
		add_filter(
			'wp_redirect',
			static function () {
				throw new \RuntimeException( 'redirected' );
			}
		);

		// The handlers only need $this; skip the constructor's hook wiring.
		$admin  = ( new \ReflectionClass( Pro_Admin::class ) )->newInstanceWithoutConstructor();
		$method = new \ReflectionMethod( $admin, $method );
		try {
			$method->invoke( $admin );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirected', $e->getMessage() );
		}
	}

	public function test_adjust_balance_screen_keeps_cents(): void {
		$_POST    = array(
			'action'          => 'adjust_balance',
			'advertiser_id'   => (string) $this->advertiser->id,
			'adjustment_type' => 'credit',
			'amount'          => '2.50',
			'_wpnonce'        => wp_create_nonce( 'wbam_adjust_balance_' . $this->advertiser->id ),
		);
		$_REQUEST = $_POST;

		$this->run_redirecting_handler( 'handle_advertiser_actions' );

		$this->assertSame( 2.5, (float) Credits_Bridge::get_balance( $this->advertiser->id ) );
	}

	public function test_minimum_balance_to_post_keeps_cents(): void {
		$_POST    = array(
			'wbam_save_classifieds_settings' => '1',
			'wbam_min_balance_to_post'       => '5.50',
			'wbam_submission_form_type'      => 'wizard',
			'_wpnonce'                       => wp_create_nonce( 'wbam_classifieds_settings' ),
		);
		$_REQUEST = $_POST;

		$admin = ( new \ReflectionClass( Pro_Admin::class ) )->newInstanceWithoutConstructor();
		ob_start();
		( new \ReflectionMethod( $admin, 'render_classifieds_settings' ) )->invoke( $admin );
		ob_end_clean();

		$this->assertSame( 5.5, (float) get_option( 'wbam_pro_settings' )['min_balance_to_post'] );
	}

	public function test_adjusted_advertiser_screen_confirms_the_update(): void {
		$_GET['message'] = 'balance_updated';

		$admin = ( new \ReflectionClass( Pro_Admin::class ) )->newInstanceWithoutConstructor();
		ob_start();
		( new \ReflectionMethod( $admin, 'render_advertiser_details' ) )->invoke( $admin, (int) $this->advertiser->id );
		$html = ob_get_clean();
		unset( $_GET['message'] );

		$this->assertStringContainsString( 'Balance updated.', $html );
	}

	public function test_package_rate_keeps_four_decimals(): void {
		$_POST    = array(
			'action'         => 'save_package',
			'name'           => 'Micro CPC',
			'pricing_model'  => 'cpc',
			'price_per_unit' => '0.0125',
			'clicks_limit'   => '1000',
			'status'         => 'active',
			'_wpnonce'       => wp_create_nonce( 'wbam_save_package' ),
		);
		$_REQUEST = $_POST;

		$this->run_redirecting_handler( 'handle_package_actions' );

		global $wpdb;
		$rate = $wpdb->get_var( $wpdb->prepare( "SELECT price_per_unit FROM {$wpdb->prefix}wbam_packages WHERE name = %s ORDER BY id DESC LIMIT 1", 'Micro CPC' ) ); // phpcs:ignore WordPress.DB
		$this->assertSame( 0.0125, (float) $rate );
	}
}
