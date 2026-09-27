<?php
/**
 * One state and one reason for every ad (owner decision 5, card 10344382789).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Core\Ad_Status;
use WP_UnitTestCase;

class Test_Ad_Status extends WP_UnitTestCase {

	private function ad( array $meta = array(), string $post_status = 'publish' ): int {
		$ad_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'wbam-ad',
				'post_status' => $post_status,
				'post_title'  => 'Status probe',
			)
		);
		$meta += array(
			'_wbam_enabled'    => '1',
			'_wbam_placements' => array( 'header' ),
			'_wbam_ad_data'    => array(
				'type'    => 'rich-content',
				'content' => '<p>Probe</p>',
			),
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $ad_id, $key, $value );
		}
		return $ad_id;
	}

	public function test_a_complete_ad_is_live(): void {
		$this->assertSame( Ad_Status::LIVE, Ad_Status::get( $this->ad() )['state'] );
	}

	public function test_each_reason_an_ad_is_not_showing(): void {
		$this->assertSame( Ad_Status::PENDING, Ad_Status::get( $this->ad( array(), 'pending' ) )['state'] );
		$this->assertSame( Ad_Status::DRAFT, Ad_Status::get( $this->ad( array(), 'draft' ) )['state'] );
		$this->assertSame( Ad_Status::OFF, Ad_Status::get( $this->ad( array( '_wbam_enabled' => '0' ) ) )['state'] );

		$scheduled = Ad_Status::get( $this->ad( array( '_wbam_start_date' => wp_date( 'Y-m-d', time() + 5 * DAY_IN_SECONDS ) ) ) );
		$this->assertSame( Ad_Status::SCHEDULED, $scheduled['state'] );
		$this->assertStringStartsWith( 'Starts on', $scheduled['reason'] );

		$ended = Ad_Status::get( $this->ad( array( '_wbam_end_date' => wp_date( 'Y-m-d', time() - 5 * DAY_IN_SECONDS ) ) ) );
		$this->assertSame( Ad_Status::ENDED, $ended['state'] );

		$no_slot = Ad_Status::get(
			$this->ad(
				array(
					'_wbam_ad_data' => array(
						'type'         => 'adsense',
						'publisher_id' => 'ca-pub-123',
					),
				)
			)
		);
		$this->assertSame( Ad_Status::NOT_SHOWING, $no_slot['state'] );
		$this->assertSame( 'Slot ID missing.', $no_slot['reason'] );

		$unplaced = Ad_Status::get( $this->ad( array( '_wbam_placements' => array() ) ) );
		$this->assertSame( Ad_Status::LIVE, $unplaced['state'], 'A shortcode or block can still show it.' );
		$this->assertStringStartsWith( 'No placement ticked', $unplaced['reason'] );

		$capped = Ad_Status::get(
			$this->ad(
				array(
					'_wbam_impression_cap'   => 100,
					'_wbam_impression_count' => 100,
				)
			)
		);
		$this->assertSame( Ad_Status::ENDED, $capped['state'] );
		$this->assertStringContainsString( '100', $capped['reason'] );
	}

	public function test_a_widget_only_ad_with_no_widget_placed_says_so(): void {
		$status = Ad_Status::get( $this->ad( array( '_wbam_placements' => array( 'widget' ) ) ) );

		$this->assertSame( Ad_Status::NOT_SHOWING, $status['state'] );
		$this->assertStringContainsString( 'widget', strtolower( $status['reason'] ) );
	}

	public function test_the_editor_hint_is_for_editors_only(): void {
		$ad_id = $this->ad( array( '_wbam_enabled' => '0' ) );

		wp_set_current_user( 0 );
		$this->assertSame( '', do_shortcode( '[wbam_ad id="' . $ad_id . '"]' ), 'Visitors see nothing.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertStringContainsString( 'Turned off in Ad Status.', do_shortcode( '[wbam_ad id="' . $ad_id . '"]' ), 'An empty shortcode explains itself to editors.' );
	}

	public function test_pro_can_add_a_reason(): void {
		$ad_id = $this->ad();
		$add   = static function ( $status ) {
			return Ad_Status::make( Ad_Status::NOT_SHOWING, 'No live campaign.' );
		};
		add_filter( 'wbam_ad_status', $add );

		$this->assertSame( 'No live campaign.', Ad_Status::get( $ad_id )['reason'] );

		remove_filter( 'wbam_ad_status', $add );
	}
}
