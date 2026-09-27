<?php
/**
 * One approval rule set and one posting limit per seller (owner decisions
 * 3 and 4, card 10344382428).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\AdSubmissions\Ad_Submission_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Shortcodes;
use WBAM_Pro\Modules\Memberships\Membership_Manager;

class Test_Approval_Rules_And_Limits extends Pro_Test_Case {

	public function set_up(): void {
		$this->snapshot_options( array( 'wbam_pro_settings' ) );
		parent::set_up();
		Settings_Helper::update( 'trust_system_enabled', true );
	}

	private function decide( bool $trusted, ?object $package, string $type ): string {
		$advertiser = new class( $trusted ) {
			private $trusted;
			public function __construct( $trusted ) {
				$this->trusted = $trusted;
			}
			public function is_trusted() {
				return $this->trusted;
			}
		};
		$method = new \ReflectionMethod( Ad_Submission_Manager::class, 'determine_approval_status' );

		return $method->invoke( Ad_Submission_Manager::get_instance(), $advertiser, $package, array( 'ad_type' => $type ) );
	}

	private function package( float $price, bool $requires_approval = true ): object {
		return (object) array(
			'price'             => $price,
			'requires_approval' => $requires_approval,
		);
	}

	public function test_a_trusted_advertisers_paid_ad_is_approved_and_a_free_one_waits(): void {
		$this->assertSame( 'approved', $this->decide( true, $this->package( 49 ), 'image' ) );
		$this->assertSame( 'pending', $this->decide( true, $this->package( 0 ), 'image' ) );
		$this->assertSame( 'pending', $this->decide( false, $this->package( 49 ), 'image' ) );
	}

	public function test_the_retired_checkbox_no_longer_turns_the_rule_off(): void {
		Settings_Helper::update( 'trust_auto_approve_paid', false );

		$this->assertSame( 'approved', $this->decide( true, $this->package( 49 ), 'image' ) );
	}

	public function test_code_ads_always_wait_even_from_a_trusted_admin_on_an_auto_approve_package(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		Settings_Helper::update( 'trust_system_enabled', false );

		$this->assertSame( 'pending', $this->decide( true, $this->package( 49, false ), 'code' ) );
		$this->assertSame( 'pending', $this->decide( true, $this->package( 49, false ), 'rich-content' ) );
		$this->assertSame( 'approved', $this->decide( false, $this->package( 0, false ), 'image' ), 'Packages with approval off skip review.' );
	}

	public function test_the_filter_can_restore_free_ad_auto_approval(): void {
		add_filter( 'wbam_pro_trusted_auto_approve', '__return_true' );

		$this->assertSame( 'approved', $this->decide( true, $this->package( 0 ), 'image' ) );

		remove_filter( 'wbam_pro_trusted_auto_approve', '__return_true' );
	}

	public function test_the_upgrade_retires_both_settings(): void {
		update_option(
			'wbam_pro_settings',
			array(
				'trust_system_enabled'     => true,
				'trust_auto_approve_paid'  => false,
				'trust_always_review_code' => true,
			)
		);

		( new \ReflectionMethod( \WBAM_Pro\Core\Installer::class, 'upgrade_to_4_3_22' ) )->invoke( null );

		$this->assertSame( array( 'trust_system_enabled' => true ), get_option( 'wbam_pro_settings' ) );
	}

	private function seller_with_active_listings( int $count ): object {
		global $wpdb;
		$advertiser = Advertiser_Manager::get_instance()->get_or_create_member( (int) self::factory()->user->create() );
		for ( $i = 0; $i < $count; $i++ ) {
			$wpdb->insert(
				$wpdb->prefix . 'wbam_classifieds',
				array(
					'post_id'       => self::factory()->post->create( array( 'post_type' => 'wbam-classified' ) ),
					'advertiser_id' => $advertiser->id,
					'status'        => 'active',
				)
			);
		}
		return $advertiser;
	}

	public function test_the_site_cap_applies_to_sellers_without_a_plan(): void {
		Settings_Helper::update( 'max_classifieds_per_advertiser', 2 );
		$seller = $this->seller_with_active_listings( 2 );

		$result = Classified_Shortcodes::get_instance()->validate_advertiser_can_post( $seller );

		$this->assertWPError( $result );
		$this->assertSame( 'quota_exceeded', $result->get_error_code() );
	}

	public function test_a_plan_member_gets_the_plan_limit_not_the_site_cap(): void {
		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['memberships'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );
		Settings_Helper::update( 'max_classifieds_per_advertiser', 2 );
		$seller  = $this->seller_with_active_listings( 2 );
		$members = Membership_Manager::get_instance();
		$members->save_plan(
			array(
				'name'          => 'Unlimited plan',
				'price'         => 0,
				'billing_cycle' => 'monthly',
				'max_listings'  => 0,
				'status'        => 'active',
			)
		);
		$plans = $members->get_plans();
		$members->subscribe( $seller->id, end( $plans )->id );

		$this->assertTrue( Classified_Shortcodes::get_instance()->validate_advertiser_can_post( $seller ), 'A subscriber is not held to the site cap.' );
		$this->assertTrue( $members->can_post_listing( $seller->id ), 'The plan says unlimited.' );
	}
}
