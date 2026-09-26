<?php
/**
 * Viewable impressions: with the owner setting on, popup, sticky and
 * code/network ads count when seen, not when rendered.
 *
 * A popup that never opened, or a network unit that stayed empty, counted
 * an impression at render (Basecamp card 10343188140). With the setting on
 * those ads carry a beacon URL instead; frontend.js sends it once at least
 * half the ad has been on screen for one second. Other ad types, and every
 * ad with the setting off, keep counting at render.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Frontend\Frontend;
use WBAM\Modules\Placements\Placement_Engine;
use WP_UnitTestCase;

class Test_Viewable_Impressions extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( 'wbam_settings' );
		parent::tear_down();
	}

	private function ad( string $type ): int {
		$id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'wbam-ad',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $id, '_wbam_enabled', '1' );
		update_post_meta(
			$id,
			'_wbam_ad_data',
			array(
				'type'    => $type,
				'code'    => '<span>unit</span>',
				'content' => 'Body',
			)
		);

		return $id;
	}

	private function render( int $ad_id, string $placement ): string {
		return Placement_Engine::get_instance()->render_ad(
			$ad_id,
			array(
				'placement'       => $placement,
				'allow_duplicate' => true,
				'skip_targeting'  => true,
			)
		);
	}

	public function test_off_by_default_so_existing_counts_do_not_shift(): void {
		$code = $this->ad( 'code' );

		$this->assertFalse( Frontend::defers_impression( $code, 'popup' ) );
		$this->assertStringNotContainsString( 'data-wbam-viewable', $this->render( $code, 'header' ) );

		$sanitized = \WBAM\Admin\Settings::get_instance()->sanitize_settings( array( '_fields' => array( 'viewable_impressions' ) ) );
		$this->assertFalse( $sanitized['viewable_impressions'] );

		$sanitized = \WBAM\Admin\Settings::get_instance()->sanitize_settings(
			array(
				'_fields'              => array( 'viewable_impressions' ),
				'viewable_impressions' => '1',
			)
		);
		$this->assertTrue( $sanitized['viewable_impressions'] );
	}

	public function test_on_defers_popup_sticky_and_code_ads_only(): void {
		update_option( 'wbam_settings', array( 'viewable_impressions' => true ) );
		$code  = $this->ad( 'code' );
		$sense = $this->ad( 'adsense' );
		$rich  = $this->ad( 'rich-content' );

		$this->assertTrue( Frontend::defers_impression( $rich, 'popup' ) );
		$this->assertTrue( Frontend::defers_impression( $rich, 'sticky' ) );
		$this->assertTrue( Frontend::defers_impression( $code, 'header' ) );
		$this->assertTrue( Frontend::defers_impression( $sense, 'content' ) );
		$this->assertFalse( Frontend::defers_impression( $rich, 'header' ), 'Other ad types keep counting at render.' );

		$this->assertMatchesRegularExpression( '/data-wbam-viewable="[^"]+ad_id(=|%3D)' . $code . '/', $this->render( $code, 'header' ) );
		$this->assertStringNotContainsString( 'data-wbam-viewable', $this->render( $rich, 'header' ) );
	}

	/**
	 * Plug and play (owner decision, card 10343706274): no Settings UI field
	 * any more, so a site's already-stored value is the `wbam_viewable_impressions`
	 * filter's default (nothing changes silently), and a developer can
	 * override it without a field to click.
	 */
	public function test_plug_and_play_filter_defaults_to_the_stored_value_and_is_overridable(): void {
		update_option( 'wbam_settings', array( 'viewable_impressions' => true ) );
		$code = $this->ad( 'code' );
		$this->assertTrue( Frontend::defers_impression( $code, 'header' ), "Site's stored 'on' value is the filter's default." );

		$forced_off = static function () {
			return false;
		};
		add_filter( 'wbam_viewable_impressions', $forced_off );
		$this->assertFalse( Frontend::defers_impression( $code, 'header' ), 'Filter overrides the stored default.' );
		remove_filter( 'wbam_viewable_impressions', $forced_off );
	}

	/** The field is gone from the Ads & Display settings page. */
	public function test_no_settings_field_renders_for_viewable_impressions(): void {
		$settings = \WBAM\Admin\Settings::get_instance();
		$settings->register_settings();

		ob_start();
		$settings->render_ads_display_page();
		$html = ob_get_clean();

		$this->assertStringNotContainsString( 'wbam_settings[viewable_impressions]', $html );
	}
}
