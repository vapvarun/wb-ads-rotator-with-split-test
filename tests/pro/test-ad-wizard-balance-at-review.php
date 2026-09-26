<?php
/**
 * The ad wizard asks for money only at the final Review step (card
 * 10343726490, step 1, owner decision). Step 2 (Package) used to block
 * "Next" with a "Buy credits now?" confirm before the advertiser had seen
 * placements, sizes or the campaign step. The balance/cost/shortfall
 * check (isShortOfCredits(), backed by the cost-balance-banner partial)
 * now only runs at Review; its top-up action opens in a new tab so the
 * in-progress draft is never lost.
 *
 * JS behavior asserted directly on source, the same way
 * Test_Portal_Script_Registration asserts the localized payload -
 * there is no JS test runner in this suite.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

class Test_Ad_Wizard_Balance_At_Review extends Pro_Test_Case {

	private function portal_js(): string {
		return (string) file_get_contents( WBAM_PRO_PATH . 'assets/js/portal.js' );
	}

	private function validate_step_case( int $case, string $js ): string {
		$pattern = '/case ' . $case . ':.*?break;/s';
		$this->assertMatchesRegularExpression( $pattern, $js, "validateStep case {$case} not found." );
		preg_match( $pattern, $js, $m );
		return $m[0];
	}

	public function test_package_step_no_longer_blocks_on_insufficient_credits(): void {
		$case2 = $this->validate_step_case( 2, $this->portal_js() );
		$this->assertStringNotContainsString( 'isShortOfCredits', $case2, 'Step 2 (Package) must not gate on balance - money is asked at Review only.' );
	}

	/** Regression guard: the server-matching balance check still runs at Review. */
	public function test_review_step_still_checks_the_balance(): void {
		$case6 = $this->validate_step_case( 6, $this->portal_js() );
		$this->assertStringContainsString( 'isShortOfCredits', $case6, 'Review must still refuse an underfunded submit client-side (the server refuses it either way).' );
	}

	/** The shared top-up action opens a new tab, not a same-tab navigation that would lose the wizard's draft. */
	public function test_offer_credits_keeps_the_draft_by_opening_a_new_tab(): void {
		$js = $this->portal_js();
		$this->assertMatchesRegularExpression( '/offerCredits:\s*function\s*\([^)]*\)\s*\{.*?\}/s', $js );
		preg_match( '/offerCredits:\s*function\s*\([^)]*\)\s*\{.*?\n\s*\}/s', $js, $m );
		$body = $m[0] ?? '';
		$this->assertStringContainsString( "window.open(purchaseUrl, '_blank'", $body, 'Top-up opens in a new tab so the draft is never navigated away from.' );
		$this->assertStringNotContainsString( 'window.location.href = purchaseUrl', $body );
	}

	/** The always-visible Review-step "Buy credits" link is also new-tab, for the no-JS/keyboard path. */
	public function test_review_banner_buy_credits_link_opens_a_new_tab(): void {
		$html = (string) file_get_contents( WBAM_PRO_PATH . 'templates/portal/partials/cost-balance-banner.php' );
		$this->assertStringContainsString( 'target="_blank"', $html );
	}
}
