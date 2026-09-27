<?php
/**
 * The combined CPM + CPC model is gone: CPM, CPC or Flat only. Stored
 * packages and campaigns move to CPM with a one-time review notice
 * (owner decision 2026-10-03, card 10344382158).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Enums\Pricing_Model;

class Test_No_Combined_Pricing_Model extends Pro_Test_Case {

	public function set_up(): void {
		$this->snapshot_options( array( 'wbam_pro_settings' ) );
		parent::set_up();
		delete_option( 'wbam_pro_combined_pricing_moved' );
	}

	public function tear_down(): void {
		// A step in these tests commits (MySQL ends the test's own
		// transaction), so remove what they insert or it piles up across runs.
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}wbam_packages WHERE name IN ( 'Combo package', 'Big Banner' )" ); // phpcs:ignore WordPress.DB
		$wpdb->query( "DELETE FROM {$wpdb->prefix}wbam_campaigns WHERE name IN ( 'Combo campaign', 'Click campaign' )" ); // phpcs:ignore WordPress.DB
		parent::tear_down();
	}

	public function test_only_three_models_are_offered(): void {
		$this->assertSame( array( 'flat', 'cpm', 'cpc' ), Pricing_Model::ALL );
		$this->assertArrayNotHasKey( 'cpm_cpc', Pricing_Model::labels() );
		$this->assertSame( 'cpm', Pricing_Model::sanitize( 'cpm_cpc' ), 'A stray stored value reads as CPM.' );
	}

	public function test_the_upgrade_moves_combined_rows_to_cpm_and_names_them(): void {
		global $wpdb;
		$advertiser = \WBAM_Pro\Modules\Advertisers\Advertiser_Manager::get_instance()->get_or_create_member( (int) self::factory()->user->create() );
		$wpdb->insert(
			$wpdb->prefix . 'wbam_packages',
			array(
				'name'          => 'Combo package',
				'pricing_model' => 'cpm_cpc',
				'status'        => 'active',
			)
		);
		$wpdb->insert(
			$wpdb->prefix . 'wbam_campaigns',
			array(
				'advertiser_id' => (int) $advertiser->id,
				'name'          => 'Combo campaign',
				'pricing_model' => 'cpm_cpc',
				'status'        => 'active',
			)
		);
		$wpdb->insert(
			$wpdb->prefix . 'wbam_campaigns',
			array(
				'advertiser_id' => (int) $advertiser->id,
				'name'          => 'Click campaign',
				'pricing_model' => 'cpc',
				'status'        => 'active',
			)
		);
		$settings                          = (array) get_option( 'wbam_pro_settings', array() );
		$settings['default_pricing_model'] = 'cpm_cpc';
		update_option( 'wbam_pro_settings', $settings );

		( new \ReflectionMethod( \WBAM_Pro\Core\Installer::class, 'upgrade_to_4_3_21' ) )->invoke( null );

		$left = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wbam_packages WHERE pricing_model = 'cpm_cpc'" ) // phpcs:ignore WordPress.DB
			+ (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wbam_campaigns WHERE pricing_model = 'cpm_cpc'" ); // phpcs:ignore WordPress.DB
		$this->assertSame( 0, $left );
		$this->assertSame( 'cpc', $wpdb->get_var( "SELECT pricing_model FROM {$wpdb->prefix}wbam_campaigns WHERE name = 'Click campaign'" ) ); // phpcs:ignore WordPress.DB
		$this->assertSame( array( 'Combo package', 'Combo campaign' ), get_option( 'wbam_pro_combined_pricing_moved' ) );
		$this->assertSame( 'cpm', get_option( 'wbam_pro_settings' )['default_pricing_model'] );
	}

	public function test_nothing_to_move_leaves_no_notice(): void {
		( new \ReflectionMethod( \WBAM_Pro\Core\Installer::class, 'upgrade_to_4_3_21' ) )->invoke( null );

		$this->assertFalse( get_option( 'wbam_pro_combined_pricing_moved' ) );
	}

	public function test_the_review_notice_names_them_on_plugin_screens(): void {
		update_option( 'wbam_pro_combined_pricing_moved', array( 'Combo package' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'toplevel_page_wbam-packages' );

		ob_start();
		( new \WBAM_Pro\Core\Pro_Admin() )->show_upgrade_review_notices();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'combined CPM + CPC pricing model is gone', $html );
		$this->assertStringContainsString( 'Combo package', $html );
		$this->assertStringContainsString( 'wbam_dismiss_review_notice=wbam_pro_combined_pricing_moved', $html );
	}
}
