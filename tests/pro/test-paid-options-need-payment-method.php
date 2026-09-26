<?php
/**
 * With no payment method (Credits_Bridge::payment_method_status() 'none')
 * paid packages and fees are neither offered nor sold (card 10342784279).
 * QA repro: the ad wizard listed 4 packages and $49 was charged on approval;
 * the review step still showed "Buy credits".
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Template_Loader;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\AdSubmissions\Ad_Submission_Manager;
use WBAM_Pro\Modules\Memberships\Membership_Manager;
use WBAM_Pro\Modules\Packages\Package_Manager;

class Test_Paid_Options_Need_Payment_Method extends Pro_Test_Case {

	private object $advertiser;
	private int $package_id;

	public function set_up(): void {
		parent::set_up();

		delete_option( 'wbam_credits_payment_method' );
		delete_option( 'wbcom_credits_gateway_settings_wbam-pro' );

		$user             = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		Advertiser_Manager::get_instance()->update_status( (int) $this->advertiser->id, 'active' );
		$this->advertiser = Advertiser_Manager::get_instance()->get( (int) $this->advertiser->id );
		\Wbcom\Credits\Credits::topup( 'wbam-pro', $user, 100000, 'seed' );

		$package          = Package_Manager::get_instance()->create(
			array(
				'name'          => 'Paid Starter',
				'price'         => 49.0,
				'pricing_model' => 'flat',
				'status'        => 'active',
			)
		);
		$this->package_id = (int) ( is_object( $package ) ? $package->id : $package );
	}

	public function tear_down(): void {
		delete_option( 'wbam_credits_payment_method' );
		parent::tear_down();
	}

	private function submit() {
		return Ad_Submission_Manager::get_instance()->submit_ad(
			$this->advertiser->id,
			array(
				'title'     => 'Paid ad',
				'ad_type'   => 'image',
				'image_url' => 'https://example.com/x.png',
				'link_url'  => 'https://example.com',
			),
			$this->package_id
		);
	}

	public function test_paid_package_is_not_offered_or_sold(): void {
		$html = (string) Template_Loader::load_template(
			'portal/ad-form',
			array(
				'advertiser' => $this->advertiser,
				'ad_id'      => 0,
				'is_edit'    => false,
			),
			true
		);
		$this->assertStringNotContainsString( 'Paid Starter', $html );
		$this->assertStringNotContainsString( 'wbam-credit-banner__cta', $html, 'No Buy credits link when nothing can be bought.' );

		$result = $this->submit();
		$this->assertWPError( $result );
		$this->assertSame( 'wbam_no_payment_method', $result->get_error_code() );
	}

	public function test_manual_top_up_sells_it(): void {
		update_option( 'wbam_credits_payment_method', 'manual' );

		$this->assertNotWPError( $this->submit() );
	}

	public function test_paid_plan_and_bump_are_refused(): void {
		$plan_id = Membership_Manager::get_instance()->save_plan(
			array(
				'name'          => 'Pro plan',
				'price'         => 9.0,
				'billing_cycle' => 'monthly',
				'status'        => 'active',
			)
		);
		$result  = Membership_Manager::get_instance()->subscribe( $this->advertiser->id, (int) $plan_id );
		$this->assertWPError( $result );
		$this->assertSame( 'wbam_no_payment_method', $result->get_error_code() );

		$enabled                = \WBAM_Pro\Core\Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		\WBAM_Pro\Core\Settings_Helper::update( 'enabled_modules', $enabled );
		$manager    = \WBAM_Pro\Modules\Classifieds\Classified_Manager::get_instance();
		$classified = $manager->create(
			array(
				'title'         => 'Bump probe',
				'description'   => 'Bump me.',
				'advertiser_id' => $this->advertiser->id,
			)
		);
		$manager->update( (int) $classified->id, array( 'status' => 'active' ) );

		$bump = $manager->bump_by_seller( (int) $classified->id, (int) $this->advertiser->id );
		$this->assertWPError( $bump );
		$this->assertSame( 'wbam_no_payment_method', $bump->get_error_code() );
	}
}
