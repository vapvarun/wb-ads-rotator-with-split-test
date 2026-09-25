<?php
/**
 * Display ads stay off members' private account pages by default.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

class Test_Account_Page_Ads extends Pro_Test_Case {

	private function visit_page_with( string $content ): void {
		$page = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => $content,
			)
		);
		$this->go_to( get_permalink( $page ) );
	}

	public function tear_down(): void {
		remove_all_filters( 'wbam_pro_show_ads_on_account_pages' );
		parent::tear_down();
	}

	public function test_dashboard_page_hides_ads_and_owner_can_allow_them(): void {
		$this->visit_page_with( '[wbam_advertiser_dashboard]' );
		$this->assertFalse( apply_filters( 'wbam_should_display_ad', true, 1 ) );

		add_filter( 'wbam_pro_show_ads_on_account_pages', '__return_true' );
		$this->assertTrue( apply_filters( 'wbam_should_display_ad', true, 1 ) );
	}

	public function test_public_browse_page_keeps_ads(): void {
		$this->visit_page_with( '[wbam_browse_classifieds]' );
		$this->assertTrue( apply_filters( 'wbam_should_display_ad', true, 1 ) );
	}
}
