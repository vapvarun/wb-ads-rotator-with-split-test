<?php
/**
 * Wave 6 steps (card 10344285037): the shared confirm dialog, the header
 * reserve and named portal row actions.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

class Test_Wave6_Polish_Steps extends Pro_Test_Case {

	public function test_the_confirm_dialog_is_a_keyboard_safe_modal(): void {
		$js = (string) file_get_contents( WBAM_PATH . 'assets/js/toast.js' );

		$this->assertStringContainsString( 'cancelBtn.focus();', $js, 'Cancel gets focus, not the destructive button.' );
		$this->assertStringNotContainsString( 'confirmBtn.focus();', $js );
		$this->assertStringContainsString( "'Escape' === event.key", $js );
		$this->assertStringContainsString( "'Tab' === event.key", $js );
		$this->assertStringContainsString( 'returnFocus.focus();', $js );
		$this->assertStringContainsString( "__( 'Yes, proceed', 'wb-ads-rotator-with-split-test' )", $js, 'Button labels are translatable.' );
		$this->assertContains( 'wp-i18n', wp_scripts()->registered['wbam-toast']->deps );
	}

	public function test_header_footer_and_archive_reserve_a_sized_image_by_its_ratio(): void {
		$css = (string) file_get_contents( WBAM_PATH . 'assets/css/frontend.css' );
		foreach ( array( 'header', 'footer', 'before-archive', 'after-archive' ) as $placement ) {
			$this->assertStringContainsString( ".wbam-placement-{$placement} .wbam-ad-slot--sized.wbam-ad-slot > .wbam-ad-image", $css );
		}
		$this->assertStringContainsString( '.wbam-placement-header .wbam-ad-slot--sized.wbam-ad-slot > .wbam-ad-image', (string) file_get_contents( WBAM_PATH . 'assets/css/frontend-rtl.css' ) );
	}

	public function test_portal_row_actions_name_the_item(): void {
		foreach ( array( 'includes/Modules/Advertisers/class-advertiser-shortcodes.php', 'includes/Modules/Classifieds/class-classified-shortcodes.php' ) as $file ) {
			$php = (string) file_get_contents( WBAM_PRO_PATH . $file );
			foreach ( array( 'Edit', 'Renew', 'Delete' ) as $action ) {
				$this->assertStringContainsString( "__( '{$action}: %s', 'wb-ad-manager-pro' ), \$classified->get_title()", $php, "{$file}: {$action}" );
			}
		}
	}
}
