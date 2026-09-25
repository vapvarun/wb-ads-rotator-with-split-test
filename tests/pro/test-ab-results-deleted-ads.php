<?php
/**
 * A/B results stay readable after the ads are deleted.
 *
 * A completed demo test showed "Winner:" with nothing after it (the winning
 * ad was deleted, so get_the_title() was empty) and only the original card
 * despite a 33/67 split: the demo import stored the variants as a
 * serialized array, which the comma-list loader read as no variants.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\ABTesting\AB_Test_Admin;

/**
 * @group pro
 * @group reports
 */
class Test_AB_Results_Deleted_Ads extends Pro_Test_Case {

	public function test_completed_test_names_a_deleted_winner_and_lists_every_variant(): void {
		global $wpdb;

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$wpdb->insert(
			$wpdb->prefix . 'wbam_ab_tests',
			array(
				'name'             => 'Deleted ads test',
				'status'           => 'completed',
				'original_ad_id'   => 991001,
				'variant_ad_ids'   => maybe_serialize( array( 991002, 991003 ) ),
				'traffic_split'    => 33,
				'goal'             => 'ctr',
				'min_sample_size'  => 150,
				'confidence_level' => 95,
				'winner_id'        => 991001,
			)
		);

		$render = new \ReflectionMethod( AB_Test_Admin::class, 'render_test_details' );
		ob_start();
		$render->invoke( AB_Test_Admin::get_instance(), (int) $wpdb->insert_id );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Winner: <strong>Original (A)</strong>, Deleted ad', $html );
		$this->assertStringContainsString( 'Variant B', $html );
		$this->assertStringContainsString( 'Variant C', $html );
	}
}
