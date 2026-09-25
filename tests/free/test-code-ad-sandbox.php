<?php
/**
 * Code-ad sandbox never pairs allow-scripts with allow-same-origin.
 *
 * A srcdoc iframe takes on the parent's origin. With both flags the framed
 * script can reach the parent document and remove its own sandbox - in the
 * wp-admin preview that is the logged-in admin's session. Both the preview
 * and the front-end sandbox mode must run scripts in an opaque origin.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Modules\AdTypes\Code_Ad;

class Test_Code_Ad_Sandbox extends \WP_UnitTestCase {

	private function make_code_ad(): int {
		$ad_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'wbam-ad',
				'post_status' => 'publish',
			)
		);
		update_post_meta(
			$ad_id,
			'_wbam_ad_data',
			array(
				'type' => 'code',
				'code' => '<a href="https://example.com/">Ad</a><script>document.title="x";</script>',
			)
		);

		return $ad_id;
	}

	/**
	 * Sandbox tokens of every iframe in the markup.
	 *
	 * @param string $html Markup.
	 * @return string[][]
	 */
	private function sandbox_tokens( string $html ): array {
		preg_match_all( '/<iframe[^>]*\ssandbox="([^"]*)"/i', $html, $matches );
		$this->assertNotEmpty( $matches[1], 'Expected a sandboxed iframe.' );

		return array_map(
			static function ( $value ) {
				return preg_split( '/\s+/', trim( $value ) );
			},
			$matches[1]
		);
	}

	public function test_admin_preview_runs_scripts_without_the_parent_origin(): void {
		$ad_id = $this->make_code_ad();

		ob_start();
		\WBAM\Admin\Admin::get_instance()->render_preview_metabox( get_post( $ad_id ) );
		$html = (string) ob_get_clean();

		foreach ( $this->sandbox_tokens( $html ) as $tokens ) {
			$this->assertContains( 'allow-scripts', $tokens );
			$this->assertNotContains( 'allow-same-origin', $tokens, 'allow-scripts + allow-same-origin lets the preview escape into wp-admin.' );
		}
	}

	public function test_front_end_sandbox_mode_runs_scripts_without_the_parent_origin(): void {
		$ad_id = $this->make_code_ad();
		update_post_meta( $ad_id, '_wbam_code_sandbox', '1' );

		$html = ( new Code_Ad() )->render( $ad_id );

		foreach ( $this->sandbox_tokens( $html ) as $tokens ) {
			$this->assertContains( 'allow-scripts', $tokens );
			$this->assertNotContains( 'allow-same-origin', $tokens );
			// Click-through still works: new tab, or the top window on a click.
			$this->assertContains( 'allow-popups', $tokens );
			$this->assertContains( 'allow-popups-to-escape-sandbox', $tokens );
			$this->assertContains( 'allow-top-navigation-by-user-activation', $tokens );
		}
	}

	public function test_filter_cannot_put_allow_same_origin_back_next_to_scripts(): void {
		$ad_id = $this->make_code_ad();
		update_post_meta( $ad_id, '_wbam_code_sandbox', '1' );

		$add_same_origin = static function ( $attrs ) {
			return $attrs . ' allow-same-origin';
		};
		add_filter( 'wbam_code_ad_sandbox_attrs', $add_same_origin );
		$html = ( new Code_Ad() )->render( $ad_id );
		remove_filter( 'wbam_code_ad_sandbox_attrs', $add_same_origin );

		foreach ( $this->sandbox_tokens( $html ) as $tokens ) {
			$this->assertNotContains( 'allow-same-origin', $tokens );
		}
	}

	public function test_allow_same_origin_is_kept_when_scripts_are_off(): void {
		$this->assertSame( 'allow-same-origin', Code_Ad::without_sandbox_escape( 'allow-same-origin' ) );
		$this->assertSame( 'allow-scripts allow-popups', Code_Ad::without_sandbox_escape( ' ALLOW-SCRIPTS  allow-same-origin allow-popups ' ) );
	}
}
