<?php
/**
 * First run: the wizard's "Ready to sell" rate item and the Next-step banner
 * for closed advertiser sign-up.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Next_Step_Banner;
use WBAM_Pro\Modules\Packages\Package_Manager;

class Test_First_Run_Ready_To_Sell extends Pro_Test_Case {

	/**
	 * Packages and advertisers live in dbDelta tables that survive the
	 * per-test rollback; start each test from empty ones.
	 */
	public function set_up(): void {
		parent::set_up();

		global $wpdb;
		foreach ( array( 'wbam_packages', 'wbam_advertisers' ) as $name ) {
			$table = $wpdb->prefix . $name;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- test isolation on a known table name.
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
				$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}
		delete_option( 'wbam_pro_demo_data_ids' );
		self::factory()->post->create( array( 'post_type' => 'wbam-ad' ) );
	}

	private function render_wizard_done_step(): string {
		ob_start();
		if ( function_exists( 'wbam_setup_wizard_render_step_2' ) ) {
			wbam_setup_wizard_render_step_2();
		} else {
			// The template declares its step renderers, so it can load once.
			$current_step = 2;
			$settings     = array();
			include WBAM_PRO_PATH . 'templates/admin/setup-wizard.php';
		}
		return (string) ob_get_clean();
	}

	private function rate_item_html( string $html ): string {
		$this->assertMatchesRegularExpression( '#<li class="is-(done|todo)">(?:(?!</li>).)*A package has a price advertisers can pay#s', $html, 'The checklist carries a rate item.' );
		preg_match( '#<li class="is-(done|todo)">((?:(?!</li>).)*A package has a price advertisers can pay)#s', $html, $m );
		return $m[1];
	}

	public function test_rate_item_fails_with_only_a_zero_price_package(): void {
		Package_Manager::get_instance()->create(
			array(
				'name'  => 'Free listing',
				'price' => 0,
			)
		);

		$this->assertSame( 'todo', $this->rate_item_html( $this->render_wizard_done_step() ) );
	}

	public function test_rate_item_passes_with_a_priced_package(): void {
		Package_Manager::get_instance()->create(
			array(
				'name'          => 'Sidebar month',
				'price'         => 49,
				'pricing_model' => 'flat',
			)
		);

		$this->assertSame( 'done', $this->rate_item_html( $this->render_wizard_done_step() ) );
	}

	public function test_banner_flags_closed_signup_when_there_are_no_advertisers(): void {
		Package_Manager::get_instance()->create(
			array(
				'name'  => 'Sidebar month',
				'price' => 49,
			)
		);
		update_option( 'users_can_register', 0 );

		$step = Next_Step_Banner::resolve_next_step();
		$this->assertNotNull( $step );
		$this->assertSame( 'open-advertiser-signup', $step['slug'] );
		$this->assertSame( admin_url( 'options-general.php#users_can_register' ), $step['cta_url'] );

		update_option( 'users_can_register', 1 );
		$this->assertSame( 'invite-advertisers', Next_Step_Banner::resolve_next_step()['slug'] );
	}
}
