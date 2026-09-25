<?php
/**
 * DB upgrade 4.3.3 clears stale page placements on existing
 * advertiser-owned video ads only.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Installer;

class Test_Video_Placement_Db_Upgrade extends Pro_Test_Case {

	private function create_ad( string $ad_type, bool $with_advertiser ): int {
		$ad_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'wbam-ad',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $ad_id, '_wbam_ad_type', $ad_type );
		update_post_meta( $ad_id, '_wbam_placements', array( 'header', 'sidebar' ) );
		if ( $with_advertiser ) {
			update_post_meta( $ad_id, '_wbam_advertiser_id', 1 );
		}
		return $ad_id;
	}

	private function run_upgrade(): void {
		$method = new \ReflectionMethod( Installer::class, 'upgrade_to_4_3_3' );
		$method->setAccessible( true );
		$method->invoke( null );
	}

	public function test_clears_placements_on_advertiser_video_ads(): void {
		$ad_id = $this->create_ad( 'video', true );

		$this->run_upgrade();

		$this->assertSame( array(), get_post_meta( $ad_id, '_wbam_placements', true ) );
	}

	public function test_leaves_admin_created_video_ad_alone(): void {
		$ad_id = $this->create_ad( 'video', false );

		$this->run_upgrade();

		$this->assertSame( array( 'header', 'sidebar' ), get_post_meta( $ad_id, '_wbam_placements', true ) );
	}

	public function test_leaves_non_video_advertiser_ad_alone(): void {
		$ad_id = $this->create_ad( 'image', true );

		$this->run_upgrade();

		$this->assertSame( array( 'header', 'sidebar' ), get_post_meta( $ad_id, '_wbam_placements', true ) );
	}

	public function test_db_version_constant_was_bumped(): void {
		$this->assertSame( '4.3.3', Installer::DB_VERSION );
	}
}
