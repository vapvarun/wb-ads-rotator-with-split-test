<?php
/**
 * The partnership form's CSS ships as a file; the 2.2.0 styles filter still works.
 *
 * Until 3.2.0 the form printed its CSS in an inline <style> block, and
 * wbam_partnership_form_styles received and returned that CSS. Sites that
 * filter it must keep getting their result now that the CSS is enqueued.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Modules\Links\Partnership_Form;
use WP_UnitTestCase;

class Test_Partnership_Form_Styles extends WP_UnitTestCase {

	public function tear_down(): void {
		remove_all_filters( 'wbam_partnership_form_styles' );
		wp_dequeue_script( 'wbam-partnership-form' );
		wp_dequeue_style( 'wbam-partnership-form' );
		wp_dequeue_style( 'wbam-partnership-form-custom' );
		parent::tear_down();
	}

	private function render(): string {
		return Partnership_Form::get_instance()->render_form( array() );
	}

	public function test_form_enqueues_its_stylesheet_and_prints_no_inline_style(): void {
		$html = $this->render();

		$this->assertTrue( wp_style_is( 'wbam-partnership-form', 'enqueued' ) );
		$this->assertStringNotContainsString( '<style', $html );
	}

	public function test_empty_filter_result_drops_the_stylesheet(): void {
		add_filter( 'wbam_partnership_form_styles', '__return_empty_string' );
		$this->render();

		$this->assertFalse( wp_style_is( 'wbam-partnership-form', 'enqueued' ) );
		$this->assertFalse( wp_style_is( 'wbam-partnership-form-custom', 'enqueued' ) );
	}

	public function test_changed_filter_result_replaces_the_stylesheet_inline(): void {
		add_filter(
			'wbam_partnership_form_styles',
			static function () {
				return '.wbam-partnership-form-wrap { color: red; }';
			}
		);
		$this->render();

		$this->assertFalse( wp_style_is( 'wbam-partnership-form', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'wbam-partnership-form-custom', 'enqueued' ) );
		$this->assertStringContainsString( 'color: red', implode( '', (array) wp_styles()->get_data( 'wbam-partnership-form-custom', 'after' ) ) );
	}
}
