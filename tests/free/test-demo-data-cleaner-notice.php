<?php
/**
 * Demo_Data_Cleaner's notice answers only its own redirect (card 10217449688).
 *
 * Pro's Tools page redirects with wbam_demo_cleared=ok|empty and prints
 * its own result. The Free notice fired on any non-empty value, so the
 * same screen read "No demo items needed to be removed." and "Removed 64
 * demo items." at once.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Admin\Demo_Data_Cleaner;
use WP_UnitTestCase;

class Test_Demo_Data_Cleaner_Notice extends WP_UnitTestCase {

	public function tear_down(): void {
		unset( $_GET['wbam_demo_cleared'], $_GET['ads'] );
		parent::tear_down();
	}

	private function render( string $value ): string {
		$_GET['wbam_demo_cleared'] = $value;
		ob_start();
		Demo_Data_Cleaner::maybe_render_notice();
		return (string) ob_get_clean();
	}

	public function test_pro_tools_redirect_values_render_nothing(): void {
		$this->assertSame( '', $this->render( 'ok' ) );
		$this->assertSame( '', $this->render( 'empty' ) );
	}

	public function test_own_redirect_still_renders(): void {
		$_GET['ads'] = '2';
		$this->assertStringContainsString( '2 ads', $this->render( '1' ) );
	}
}
