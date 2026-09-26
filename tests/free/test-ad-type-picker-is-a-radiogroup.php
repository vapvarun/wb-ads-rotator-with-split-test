<?php
/**
 * The ad-type picker chooses a value, so it must be a radiogroup of real,
 * labelled radios — not a tablist of decorative <a role="tab"> pills with
 * the actual value living in a separate, disconnected set of hidden radios
 * (QA wave 4, card 10343712795).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Admin\Admin;
use WBAM\Modules\Placements\Placement_Engine;

class Test_Ad_Type_Picker_Is_A_Radiogroup extends \WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	private function render(): string {
		$post_id = self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_status' => 'publish' ) );
		$post    = get_post( $post_id );

		ob_start();
		Admin::get_instance()->render_settings_metabox( $post );
		return (string) ob_get_clean();
	}

	public function test_the_picker_is_a_radiogroup_not_a_tablist(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'role="radiogroup"', $html );
		$this->assertStringNotContainsString( 'role="tablist"', $html );
		$this->assertStringNotContainsString( 'role="tab"', $html );
	}

	public function test_every_ad_type_is_a_real_labelled_radio_in_the_dom(): void {
		$html      = $this->render();
		$ad_types  = Placement_Engine::get_instance()->get_ad_types();
		$radio_count = substr_count( $html, 'name="wbam_data[type]"' );

		$this->assertGreaterThanOrEqual( count( $ad_types ), $radio_count, 'Every registered ad type must render its own radio.' );

		foreach ( $ad_types as $type ) {
			$needle = 'value="' . $type->get_id() . '"';
			$this->assertStringContainsString( $needle, $html, "'{$type->get_id()}' must be a selectable radio, not just a label." );
		}
	}

	public function test_no_option_is_hidden_with_display_none(): void {
		$html = $this->render();

		// The picker's own pills must never be display:none (the SEPARATE
		// per-type content panels below the picker legitimately are, until
		// selected - this assertion is scoped to the tab/label markup by
		// only scanning up to the first content panel).
		$panel_pos    = strpos( $html, 'wbam-adtype-content' );
		$picker_markup = false !== $panel_pos ? substr( $html, 0, $panel_pos ) : $html;

		$this->assertStringNotContainsString( 'display:none', $picker_markup );
		$this->assertStringNotContainsString( 'display: none', $picker_markup );
	}
}
