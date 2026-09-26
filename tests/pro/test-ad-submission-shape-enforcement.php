<?php
/**
 * Portal submission: every chosen slot must fit once shape matching is on
 * (owner decision 13, card 10343726460).
 *
 * validate_creative_fit() previously blocked a submission only when the
 * creative fit NONE of the selected slots — a 300x250 square ticked into
 * Header + Widget passed, because it fit Widget, and then rendered in the
 * header too. Enforcement is gated behind the same flag as render-time and
 * package-level matching, so an existing site that hasn't opted in keeps
 * the original "fits at least one" behavior.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\AdSubmissions\Ad_Submission_Manager;

class Test_Ad_Submission_Shape_Enforcement extends Pro_Test_Case {

	private int $user;
	private object $advertiser;
	private int $package_id;

	public function set_up(): void {
		parent::set_up();

		$this->user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $this->user );
		Advertiser_Manager::get_instance()->update_status( (int) $this->advertiser->id, 'active' );
		$this->advertiser = Advertiser_Manager::get_instance()->get( (int) $this->advertiser->id );

		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'wbam_packages',
			array(
				'name'          => 'Shape Enforcement Flat',
				'price'         => 0.00,
				'pricing_model' => 'flat',
				'status'        => 'active',
				'created_at'    => current_time( 'mysql' ),
			)
		);
		$this->package_id = (int) $wpdb->insert_id;
	}

	public function tear_down(): void {
		delete_option( 'wbam_settings' );
		parent::tear_down();
	}

	private function submit_square_into( array $placements ) {
		return Ad_Submission_Manager::get_instance()->submit_ad(
			$this->advertiser->id,
			array(
				'title'      => 'Square ad',
				'ad_type'    => 'image',
				'image_url'  => 'https://example.com/square.png',
				'link_url'   => 'https://example.com',
				'ad_format'  => 'custom',
				'ad_width'   => 300,
				'ad_height'  => 250,
				'placements' => $placements,
			),
			$this->package_id
		);
	}

	// 'after_paragraph' (a Box placement) is used instead of 'widget'
	// (the sidebar) here: the default WP test environment registers no
	// sidebar, so wbam_pro_validate_placement_slugs() rejects 'widget' as
	// unknown regardless of shape matching — a test-environment quirk,
	// not something this feature touches. 'after_paragraph' needs no
	// theme sidebar and is Box-shaped exactly like the sidebar is.

	public function test_mismatched_placement_refused_when_matching_enforced(): void {
		Settings_Helper::update( 'format_matching', true );

		$result = $this->submit_square_into( array( 'header', 'after_paragraph' ) );

		$this->assertWPError( $result, 'A 300x250 square must not be submittable into the header once every slot must fit.' );
		$this->assertSame( 'wbam_creative_size_mismatch', $result->get_error_code() );
	}

	public function test_all_fitting_placements_still_submit_when_matching_enforced(): void {
		Settings_Helper::update( 'format_matching', true );

		$result = $this->submit_square_into( array( 'after_paragraph' ) );

		$this->assertNotWPError( $result, 'A 300x250 square fits its own Box placement and should submit.' );
	}

	public function test_partial_mismatch_still_submits_when_matching_not_enforced(): void {
		// Simulates an existing (pre-3.2.0) site that hasn't opted in yet —
		// the test bootstrap's own fresh install already defaults
		// format_matching to true, so this is modeled explicitly.
		Settings_Helper::update( 'format_matching', false );

		$result = $this->submit_square_into( array( 'header', 'after_paragraph' ) );

		$this->assertNotWPError( $result, "Existing sites keep today's submission behavior until they turn shape matching on." );
	}
}
