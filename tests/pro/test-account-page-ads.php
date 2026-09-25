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

	public function test_configured_account_page_without_shortcode_hides_ads(): void {
		// Page builders keep the shortcode out of post_content; the page id is the signal.
		$page = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => 'Built with a page builder',
			)
		);
		update_option( 'wbam_page_my_favorites', $page );
		$this->go_to( get_permalink( $page ) );

		$this->assertFalse( apply_filters( 'wbam_should_display_ad', true, 1 ) );
	}

	public function test_public_browse_page_keeps_ads(): void {
		$this->visit_page_with( '[wbam_browse_classifieds]' );
		$this->assertTrue( apply_filters( 'wbam_should_display_ad', true, 1 ) );
	}

	/**
	 * `wbam_should_display_ad` above governs whole display slots (header,
	 * sidebar); `wbam_skip_content_injection` is the separate gate for the
	 * after-paragraph in-content ad, which landed inside chat bubbles on a
	 * single listing and inside the browse results grid.
	 */
	public function test_skip_content_injection_on_browse_page(): void {
		$this->visit_page_with( '[wbam_browse_classifieds]' );
		$this->assertTrue( apply_filters( 'wbam_skip_content_injection', false ) );
	}

	public function test_skip_content_injection_on_single_classified(): void {
		$classified = self::factory()->post->create(
			array(
				'post_type'   => \WBAM_Pro\Modules\Classifieds\Classified_Manager::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		$this->go_to( get_permalink( $classified ) );

		$this->assertTrue( apply_filters( 'wbam_skip_content_injection', false ) );
	}

	public function test_skip_content_injection_leaves_plain_post_alone(): void {
		$post = self::factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
			)
		);
		$this->go_to( get_permalink( $post ) );

		$this->assertFalse( apply_filters( 'wbam_skip_content_injection', false ) );
	}
}
