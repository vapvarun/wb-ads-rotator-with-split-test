<?php
/**
 * A replayed tracking-pixel URL bills one impression per visitor per minute.
 *
 * Guest nonces are shared by every guest (user 0), so the same pixel URL was
 * valid from any fresh cookie jar and each request billed a CPM impression.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Analytics\Analytics_Tracker;

class Test_Pixel_Replay_Dedupe extends Pro_Test_Case {

	public function test_same_visitor_same_ad_counts_once_per_window(): void {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
		$tracker                = ( new \ReflectionClass( Analytics_Tracker::class ) )->newInstanceWithoutConstructor();

		$this->assertTrue( $tracker->claim_pixel_impression( 4242 ) );
		$this->assertFalse( $tracker->claim_pixel_impression( 4242 ), 'A replay inside the window must not bill again.' );
		$this->assertTrue( $tracker->claim_pixel_impression( 4243 ), 'Another ad is its own impression.' );
	}
}
