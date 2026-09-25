<?php
/**
 * Regression for BC#10339874175 item 3: a ticked category/tag in Display
 * Rules must survive a reload, and a second Update must not silently drop
 * it from _wbam_display_rules.
 *
 * Root cause: sanitize_display_rules() stored term/post IDs as strings
 * (sanitize_text_field), while render_display_rules() compared them with a
 * strict in_array() against int term_id/post ID — every previously-saved
 * choice rendered unticked, and a second save (which only posts what's
 * ticked) wrote back an empty list.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Admin\Display_Options;
use WP_UnitTestCase;

class Test_Display_Rules_Term_Id_Round_Trip extends WP_UnitTestCase {

	public function test_categories_and_tags_stay_ticked_after_sanitize_and_render(): void {
		$cat_id  = self::factory()->category->create( array( 'name' => 'QA Category' ) );
		$tag_id  = self::factory()->tag->create( array( 'name' => 'QA Tag' ) );
		$post_id = self::factory()->post->create( array( 'post_type' => 'wbam-ad' ) );

		$display_options = Display_Options::get_instance();
		$sanitize         = new \ReflectionMethod( $display_options, 'sanitize_display_rules' );
		$sanitize->setAccessible( true );

		// PHP always delivers checkbox/multi-select values from $_POST as
		// strings, whatever numeric type the stored option is.
		$sanitized = $sanitize->invoke(
			$display_options,
			array(
				'display_on' => 'specific',
				'categories' => array( (string) $cat_id ),
				'tags'       => array( (string) $tag_id ),
			)
		);

		$this->assertSame( array( $cat_id ), $sanitized['categories'], 'Category IDs must be stored as ints, not strings.' );
		$this->assertSame( array( $tag_id ), $sanitized['tags'], 'Tag IDs must be stored as ints, not strings.' );

		update_post_meta( $post_id, '_wbam_display_rules', $sanitized );

		$render = new \ReflectionMethod( $display_options, 'render_display_rules' );
		$render->setAccessible( true );

		ob_start();
		$render->invoke( $display_options, get_post( $post_id ) );
		$html = ob_get_clean();

		$this->assertMatchesRegularExpression(
			'/<option value="' . $cat_id . '"[^>]*selected=[\'"]selected[\'"]/',
			$html,
			'The previously-saved category must render ticked on reload, not unticked.'
		);
		$this->assertMatchesRegularExpression(
			'/<option value="' . $tag_id . '"[^>]*selected=[\'"]selected[\'"]/',
			$html,
			'The previously-saved tag must render ticked on reload, not unticked.'
		);
	}

	/**
	 * Same bug, legacy data: a category/tag saved as a string (from before
	 * this fix) must still render ticked — render_display_rules() normalises
	 * on read too, not just sanitize_display_rules() on write.
	 */
	public function test_legacy_string_ids_still_render_ticked(): void {
		$cat_id  = self::factory()->category->create( array( 'name' => 'QA Legacy Category' ) );
		$post_id = self::factory()->post->create( array( 'post_type' => 'wbam-ad' ) );

		update_post_meta(
			$post_id,
			'_wbam_display_rules',
			array(
				'display_on' => 'specific',
				'categories' => array( (string) $cat_id ), // Legacy string, as sanitize_text_field() used to store it.
			)
		);

		$display_options = Display_Options::get_instance();
		$render           = new \ReflectionMethod( $display_options, 'render_display_rules' );
		$render->setAccessible( true );

		ob_start();
		$render->invoke( $display_options, get_post( $post_id ) );
		$html = ob_get_clean();

		$this->assertMatchesRegularExpression(
			'/<option value="' . $cat_id . '"[^>]*selected=[\'"]selected[\'"]/',
			$html,
			'Pre-existing string-stored category IDs must still render ticked.'
		);
	}
}
