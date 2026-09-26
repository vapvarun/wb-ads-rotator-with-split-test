<?php
/**
 * Campaign view page: advertiser name, pricing model label, Edit/Pause
 * actions, and submenu highlighting (10339876480 step 14).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Pro_Admin;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

/**
 * @group pro
 */
class Test_Campaign_View_Labels extends Pro_Test_Case {

	private function campaign_row( array $overrides = array() ): int {
		global $wpdb;

		// advertiser_id has an ON DELETE CASCADE foreign key to wbam_advertisers,
		// so it must point at a real row, not 0.
		if ( ! isset( $overrides['advertiser_id'] ) ) {
			$advertiser                    = Advertiser_Manager::get_instance()->get_or_create( self::factory()->user->create() );
			$overrides['advertiser_id'] = $advertiser->id;
		}

		$wpdb->insert(
			$wpdb->prefix . 'wbam_campaigns',
			array_merge(
				array(
					'name'           => 'View Test Campaign',
					'pricing_model'  => 'flat',
					'price_per_unit' => 0,
					'status'         => 'active',
				),
				$overrides
			)
		);
		$this->assertSame( '', $wpdb->last_error );
		return (int) $wpdb->insert_id;
	}

	private function render( int $campaign_id ): string {
		$method = new \ReflectionMethod( Pro_Admin::class, 'render_campaign_details' );
		$method->setAccessible( true );
		ob_start();
		$method->invoke( new Pro_Admin(), $campaign_id );
		return (string) ob_get_clean();
	}

	public function test_flat_pricing_model_reads_flat_rate_like_everywhere_else(): void {
		$id   = $this->campaign_row( array( 'pricing_model' => 'flat' ) );
		$html = $this->render( $id );

		$this->assertStringContainsString( 'Flat Rate', $html );
		$this->assertStringNotContainsString( '>Flat<', $html );
	}

	public function test_advertiser_without_a_company_name_shows_their_own_name(): void {
		$user       = self::factory()->user->create( array( 'display_name' => 'Nameless Co Owner' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create_member( $user );
		$id         = $this->campaign_row( array( 'advertiser_id' => $advertiser->id ) );

		$html = $this->render( $id );

		$this->assertStringContainsString( 'Nameless Co Owner', $html );
		$this->assertStringNotContainsString( 'View Advertiser', $html );
	}

	public function test_active_campaign_view_offers_edit_and_pause(): void {
		$id   = $this->campaign_row( array( 'status' => 'active' ) );
		$html = $this->render( $id );

		$this->assertStringContainsString( '>Edit<', $html );
		$this->assertStringContainsString( '>Pause<', $html );
		$this->assertStringNotContainsString( '>Resume<', $html );
	}

	public function test_paused_campaign_view_offers_resume_instead(): void {
		$id   = $this->campaign_row( array( 'status' => 'paused' ) );
		$html = $this->render( $id );

		$this->assertStringContainsString( '>Resume<', $html );
		$this->assertStringNotContainsString( '>Pause<', $html );
	}

	public function test_campaign_page_highlights_the_campaigns_submenu(): void {
		$_GET['page'] = 'wbam-campaigns';
		$admin        = new Pro_Admin();

		$this->assertSame( 'edit.php?post_type=wbam-ad', $admin->fix_campaign_view_parent_menu( 'index.php' ) );
		$this->assertSame( 'wbam-campaigns', $admin->fix_campaign_view_submenu_file( '' ) );

		unset( $_GET['page'] );
		$this->assertSame( 'index.php', $admin->fix_campaign_view_parent_menu( 'index.php' ) );
	}
}
