<?php
/**
 * A "Buy credits" trip from Review opens in a new tab (draft intact,
 * card 10343726490 step 1), but that leaves the balance shown in the
 * original tab stale until re-fetched. When the tab regains focus while
 * Review is on screen, portal.js re-reads /advertiser/profile (the
 * existing endpoint that already returns the live balance - no new route)
 * and redraws the banner.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

class Test_Ad_Wizard_Refresh_Balance_On_Return extends Pro_Test_Case {

	private function portal_js(): string {
		return (string) file_get_contents( WBAM_PRO_PATH . 'assets/js/portal.js' );
	}

	public function test_tab_focus_while_on_review_refetches_the_balance(): void {
		$js = $this->portal_js();

		$this->assertStringContainsString( 'visibilitychange', $js );
		$this->assertStringContainsString( 'self.refreshBalance()', $js, 'Regaining focus on Review must trigger a refresh.' );
		$this->assertMatchesRegularExpression(
			'/currentStep\s*===\s*self\.totalSteps/',
			$js,
			'The refresh only fires while Review (the last step) is on screen.'
		);
	}

	public function test_refresh_balance_reads_the_existing_advertiser_profile_endpoint(): void {
		$js = $this->portal_js();

		$this->assertMatchesRegularExpression( '/refreshBalance:\s*function\s*\([^)]*\)\s*\{.*?\n\s*\},/s', $js );
		preg_match( '/refreshBalance:\s*function\s*\([^)]*\)\s*\{.*?\n\s*\},/s', $js, $m );
		$body = $m[0] ?? '';

		$this->assertStringContainsString( "apiRequest('advertiser/profile')", $body, 'Reuses the existing endpoint that already returns balance - no new REST route.' );
		$this->assertStringContainsString( 'data-balance', $body, "Updates the banner's balance data attribute so updateAdCreditBanner() recalculates against the fresh figure." );
	}
}
