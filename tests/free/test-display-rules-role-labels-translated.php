<?php
/**
 * The ad editor's Visitor Conditions > User Roles picker must show each
 * role through translate_user_role() - the same call core's own Users
 * list and Pro_Admin::render_role_chip_grid() use - not the raw,
 * untranslated name wp_roles()->get_names() returns (card 10339876480,
 * QA wave 3: "Roles show as raw slugs").
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Admin\Display_Options;
use WBAM\Tests\Helpers\Factory;
use WP_UnitTestCase;

class Test_Display_Rules_Role_Labels_Translated extends WP_UnitTestCase {

	private $marker_added = false;

	public function tear_down(): void {
		if ( $this->marker_added ) {
			remove_filter( 'gettext_with_context', array( $this, 'mark_role_translation' ), 10 );
		}
		parent::tear_down();
	}

	/**
	 * Appends a marker only for calls that go through translate_user_role()
	 * (context 'User role', domain 'default') - the same probe used to
	 * confirm core's own role-name translation ran.
	 */
	public function mark_role_translation( $translation, $text, $context, $domain ) {
		if ( 'User role' === $context && 'default' === $domain ) {
			return $text . ' [i18n]';
		}
		return $translation;
	}

	public function test_role_labels_go_through_translate_user_role(): void {
		add_filter( 'gettext_with_context', array( $this, 'mark_role_translation' ), 10, 4 );
		$this->marker_added = true;

		$ad = Factory::make_ad();

		ob_start();
		Display_Options::get_instance()->render_visitor_conditions( get_post( $ad ) );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Subscriber [i18n]', $html );
	}
}
