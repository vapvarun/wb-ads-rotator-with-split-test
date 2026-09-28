<?php
/**
 * Submission: a package's allowed formats are enforced server-side, not just
 * by the portal form's JS (QA card: package allowed formats not enforced).
 *
 * Gated behind the same Format Matching flag as render time, so a site that
 * has not opted in keeps accepting what it accepted.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\AdSubmissions\Ad_Submission_Manager;

class Test_Ad_Submission_Package_Formats extends Pro_Test_Case {

	private object $advertiser;
	private int $package_id;

	public function set_up(): void {
		parent::set_up();

		$user             = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		Advertiser_Manager::get_instance()->update_status( (int) $this->advertiser->id, 'active' );
		$this->advertiser = Advertiser_Manager::get_instance()->get( (int) $this->advertiser->id );

		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'wbam_packages',
			array(
				'name'            => 'Leaderboard only',
				'price'           => 0.00,
				'pricing_model'   => 'flat',
				'status'          => 'active',
				'allowed_formats' => maybe_serialize( array( 'leaderboard' ) ),
				'created_at'      => current_time( 'mysql' ),
			)
		);
		$this->package_id = (int) $wpdb->insert_id;
	}

	public function tear_down(): void {
		delete_option( 'wbam_settings' );
		parent::tear_down();
	}

	private function submit( array $extra ) {
		return Ad_Submission_Manager::get_instance()->submit_ad(
			$this->advertiser->id,
			array_merge(
				array(
					'title'      => 'Format ad',
					'ad_type'    => 'rich-content',
					'content'    => '<p>x</p>',
					'placements' => array( 'content' ),
				),
				$extra
			),
			$this->package_id
		);
	}

	public function test_a_format_the_package_does_not_allow_is_refused_when_matching_is_on(): void {
		Settings_Helper::update( 'format_matching', true );

		$result = $this->submit(
			array(
				'ad_format' => 'medium-rectangle',
				'ad_width'  => 300,
				'ad_height' => 250,
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'wbam_format_not_in_package', $result->get_error_code() );
	}

	public function test_a_creative_with_no_size_counts_as_responsive_and_is_refused_too(): void {
		Settings_Helper::update( 'format_matching', true );

		$this->assertSame( 'wbam_format_not_in_package', $this->submit( array() )->get_error_code() );
	}

	public function test_an_allowed_format_is_accepted(): void {
		Settings_Helper::update( 'format_matching', true );

		$result = $this->submit(
			array(
				'ad_format' => 'leaderboard',
				'ad_width'  => 728,
				'ad_height' => 90,
			)
		);

		$this->assertNotWPError( $result );
	}

	public function test_nothing_changes_until_format_matching_is_turned_on(): void {
		Settings_Helper::update( 'format_matching', false );

		$this->assertNotWPError( $this->submit( array() ) );
	}
}
