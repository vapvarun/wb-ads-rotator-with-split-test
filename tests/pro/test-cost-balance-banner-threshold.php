<?php
/**
 * The pre-submit cost/balance banner must use the shared
 * Settings_Helper::low_balance_threshold() helper (card 10343765625, step 1),
 * so wbam_pro_low_balance_threshold applies and cents survive - the old
 * absint() call on the raw option truncated 10.50 to 10 and ignored the
 * filter entirely.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

class Test_Cost_Balance_Banner_Threshold extends Pro_Test_Case {

	private object $advertiser;

	public function set_up(): void {
		parent::set_up();
		$user             = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
	}

	private function render_banner(): string {
		ob_start();
		$advertiser = $this->advertiser; // Used by the required template.
		require WBAM_PRO_PATH . 'templates/portal/partials/cost-balance-banner.php';
		return (string) ob_get_clean();
	}

	public function test_filter_overrides_the_threshold(): void {
		update_option( 'wbam_pro_settings', array_merge( (array) get_option( 'wbam_pro_settings', array() ), array( 'low_balance_threshold' => 10 ) ) );

		add_filter(
			'wbam_pro_low_balance_threshold',
			function () {
				return 25.0;
			}
		);

		$html = $this->render_banner();

		$this->assertStringContainsString( 'data-threshold="25"', $html );
	}

	public function test_threshold_keeps_cents(): void {
		update_option( 'wbam_pro_settings', array_merge( (array) get_option( 'wbam_pro_settings', array() ), array( 'low_balance_threshold' => 10.5 ) ) );

		$html = $this->render_banner();

		$this->assertStringContainsString( 'data-threshold="10.5"', $html );
	}
}
