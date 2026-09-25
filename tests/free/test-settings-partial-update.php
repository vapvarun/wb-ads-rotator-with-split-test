<?php
/**
 * A partial wbam_settings write must not reset keys it does not carry.
 *
 * Regression: on a fresh install the first PRO Modules save (which goes
 * through Settings_Helper::update( 'modules', ... )) blanked ad_label,
 * switched anonymize_ip off and zeroed max_ads_per_page, because the
 * register_setting sanitizer rebuilt every field from the partial input.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Core\Settings_Helper;
use WP_UnitTestCase;

class Test_Settings_Partial_Update extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		( new \WBAM\Admin\Settings() )->register_settings();
	}

	public function tear_down(): void {
		delete_option( 'wbam_settings' );
		parent::tear_down();
	}

	public function test_fresh_install_modules_save_keeps_defaults(): void {
		update_option( 'wbam_settings', array() );

		Settings_Helper::update( 'modules', array( 'links' => false ) );

		$stored = get_option( 'wbam_settings' );
		$this->assertSame( 'Advertisement', $stored['ad_label'] );
		$this->assertTrue( $stored['anonymize_ip'], 'GDPR IP anonymisation must stay on.' );
		$this->assertSame( 10, $stored['max_ads_per_page'] );
		$this->assertFalse( $stored['modules']['links'] );
	}

	public function test_partial_update_keeps_other_stored_keys(): void {
		update_option(
			'wbam_settings',
			array(
				'ad_label'          => 'Sponsored',
				'anonymize_ip'      => false,
				'adsense_auto_ads'  => true,
				'link_cloak_prefix' => 'out',
			)
		);

		Settings_Helper::update( 'max_ads_per_page', 3 );

		$stored = get_option( 'wbam_settings' );
		$this->assertSame( 3, $stored['max_ads_per_page'] );
		$this->assertSame( 'Sponsored', $stored['ad_label'] );
		$this->assertFalse( $stored['anonymize_ip'] );
		$this->assertTrue( $stored['adsense_auto_ads'] );
		$this->assertSame( 'out', $stored['link_cloak_prefix'] );
	}

	public function test_form_post_with_unchecked_checkbox_saves_false(): void {
		update_option(
			'wbam_settings',
			array(
				'anonymize_ip'          => true,
				'disable_ads_admin'     => true,
				'disable_on_post_types' => array( 'page' ),
			)
		);

		// The form names every checkbox it drew; the cleared ones are absent.
		$post = array(
			'_fields'          => array( 'anonymize_ip', 'disable_ads_admin', 'disable_on_post_types', 'adsense_auto_ads' ),
			'adsense_auto_ads' => '1',
			'ad_label'         => 'Ad',
			'max_ads_per_page' => '4',
		);
		update_option( 'wbam_settings', $post );

		$stored = get_option( 'wbam_settings' );
		$this->assertFalse( $stored['anonymize_ip'] );
		$this->assertFalse( $stored['disable_ads_admin'] );
		$this->assertSame( array(), $stored['disable_on_post_types'] );
		$this->assertTrue( $stored['adsense_auto_ads'] );
		$this->assertSame( 'Ad', $stored['ad_label'] );
		$this->assertSame( 4, $stored['max_ads_per_page'] );
		$this->assertArrayNotHasKey( '_fields', $stored, 'The contract is transport-only.' );
	}

	public function test_rendered_checkbox_declares_itself_in_the_contract(): void {
		ob_start();
		( new \WBAM\Admin\Settings() )->render_checkbox_field( array( 'id' => 'anonymize_ip' ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'name="wbam_settings[_fields][]" value="anonymize_ip"', $html );
	}
}
