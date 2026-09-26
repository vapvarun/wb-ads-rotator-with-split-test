<?php
/**
 * Card 10339876480, step 12: Help & Docs said "5 ad types" even once Pro's
 * Video ad type made it 6. The count now reads the live registry instead
 * of a number that drifts every time a type is added.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Admin\Help_Docs;
use WBAM\Modules\Placements\Placement_Engine;
use WP_UnitTestCase;

class Test_Help_Docs_Ad_Type_Count extends WP_UnitTestCase {

	public function test_features_tab_counts_the_live_ad_type_registry(): void {
		$expected = count( Placement_Engine::get_instance()->get_ad_types() );

		$method = new \ReflectionMethod( Help_Docs::class, 'render_features_tab' );
		$method->setAccessible( true );
		ob_start();
		$method->invoke( Help_Docs::get_instance() );
		$html = ob_get_clean();

		/* translators: %d: number of ad types available */
		$expected_phrase = sprintf( _n( '%d ad type:', '%d ad types:', $expected, 'wb-ads-rotator-with-split-test' ), $expected );
		$this->assertStringContainsString( $expected_phrase, $html );
	}
}
