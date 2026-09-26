<?php
/**
 * The per-ad nofollow option is gone (owner decision, card 10342823390);
 * an ad that had it saved on keeps nofollow in its link rel, as the default
 * the wbam_ad_link_rel filter starts from, so nothing changes silently.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Modules\Placements\Placement_Engine;

class Test_Legacy_Nofollow_Rel extends Pro_Test_Case {

	public function test_stored_nofollow_is_the_filter_default(): void {
		$engine = Placement_Engine::get_instance();
		$ad_id  = (int) self::factory()->post->create( array( 'post_type' => 'wbam-ad' ) );
		$this->assertSame( 'noopener', $engine->get_ad_link_rel( $ad_id ) );

		update_post_meta( $ad_id, '_wbam_nofollow', '1' );
		$seen = null;
		$spy  = static function ( $rel ) use ( &$seen ) {
			$seen = $rel;
			return $rel;
		};
		add_filter( 'wbam_ad_link_rel', $spy );
		$rel = $engine->get_ad_link_rel( $ad_id );
		remove_filter( 'wbam_ad_link_rel', $spy );

		$this->assertSame( 'noopener nofollow', $rel );
		$this->assertSame( 'noopener nofollow', $seen, 'A site filter sees the stored nofollow as its default.' );
	}
}
