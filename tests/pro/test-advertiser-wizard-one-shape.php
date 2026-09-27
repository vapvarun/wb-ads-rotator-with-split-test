<?php
/**
 * Advertiser wizard (owner decision 10, card 10344383315): the ad's shape
 * is picked first so Header + Popup cannot be ticked together, a package
 * the balance cannot cover says so on its card, and Free's sample ads
 * link to the published Advertise page.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Admin\Setup_Wizard;
use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Core\Template_Loader;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Packages\Package_Manager;

class Test_Advertiser_Wizard_One_Shape extends Pro_Test_Case {

	private object $advertiser;

	public function set_up(): void {
		$this->snapshot_options( array( 'wbam_pro_settings', 'wbam_credits_payment_method', 'wbam_page_advertise' ) );
		parent::set_up();

		$enabled             = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['packages'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );
		update_option( 'wbam_credits_payment_method', 'manual' );

		$user             = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
	}

	private function render_form(): string {
		return (string) Template_Loader::load_template(
			'portal/ad-form',
			array(
				'advertiser' => $this->advertiser,
				'ad_id'      => 0,
				'is_edit'    => false,
			),
			true
		);
	}

	public function test_placements_are_picked_within_one_shape(): void {
		$html = $this->render_form();

		$this->assertStringContainsString( 'id="wbam-shape-picker"', $html );
		$this->assertMatchesRegularExpression( "/name=\"wbam_ad_shape\" value=\"banner\"\s+checked/", $html );
		$this->assertMatchesRegularExpression( '/data-placement-id="header" data-shapes="banner"/', $html );
		$this->assertMatchesRegularExpression( '/data-placement-id="popup" data-shapes="box"/', $html );
	}

	public function test_a_package_the_balance_cannot_cover_says_short_by(): void {
		Package_Manager::get_instance()->create(
			array(
				'name'   => 'Big Banner',
				'price'  => 49.0,
				'status' => 'active',
			)
		);

		$this->assertMatchesRegularExpression( '/Short by \D*49\.00/', $this->render_form() );
	}

	public function test_samples_link_to_the_published_advertise_page(): void {
		$page = (int) self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		update_option( 'wbam_page_advertise', $page );

		$this->assertSame( get_permalink( $page ), Setup_Wizard::sample_ad_link( '' ) );

		wp_update_post( array( 'ID' => $page, 'post_status' => 'draft' ) );
		delete_option( 'wbam_page_advertise' );
		$this->assertSame( '', Setup_Wizard::sample_ad_link( '' ), 'No published page, no link.' );
	}
}
