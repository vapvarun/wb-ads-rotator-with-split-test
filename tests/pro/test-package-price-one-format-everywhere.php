<?php
/**
 * One package price formatter, used at every surface (card 10343726490
 * step 2, owner decision). Before this, the wizard's package card built
 * its own amount+unit split (Package::get_price_parts(), now removed) and
 * the admin packages list built its own "%s per click" / "%s per 1K
 * views" strings - two more formatters than wbam_format_package_terms(),
 * each free to say something different about the same package. Asserted
 * on source, the same way other template-contract regressions in this
 * suite are (there is no template-rendering harness for these screens).
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

class Test_Package_Price_One_Format_Everywhere extends Pro_Test_Case {

	public function test_wizard_package_card_has_no_second_formatter(): void {
		$html = (string) file_get_contents( WBAM_PRO_PATH . 'templates/portal/ad-form.php' );

		$this->assertStringNotContainsString( 'get_price_parts', $html, 'The split amount+unit formatter is gone - wbam_format_package_terms() is the one source now.' );
		$this->assertStringContainsString( 'wbam_format_package_terms( $package, isset( $advertiser ) ? $advertiser : null )', $html );
	}

	public function test_removed_formatter_no_longer_exists_on_the_package_class(): void {
		$this->assertFalse( method_exists( \WBAM_Pro\Modules\Packages\Package::class, 'get_price_parts' ) );
	}

	public function test_admin_packages_list_reads_the_shared_formatter_not_its_own_wording(): void {
		$php = (string) file_get_contents( WBAM_PRO_PATH . 'includes/Core/class-pro-admin.php' );

		$this->assertStringNotContainsString( "'%s per click'", $php, 'The admin-only "per click" wording is gone.' );
		$this->assertStringNotContainsString( "'%s per 1K views'", $php, 'The admin-only "per 1K views" wording is gone.' );
		$this->assertStringContainsString( 'wbam_format_package_terms( $package )', $php );
	}

	public function test_advertise_page_already_reads_the_shared_formatter(): void {
		$html = (string) file_get_contents( WBAM_PRO_PATH . 'templates/portal/advertise-page.php' );

		$this->assertStringContainsString( 'wbam_format_package_terms( $package )', $html );
	}
}
