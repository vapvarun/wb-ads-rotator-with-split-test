<?php
/**
 * A rich-content ad from the portal keeps its click URL.
 *
 * Regression guard for Basecamp card 10340184996: the portal form posts
 * every type's panel, so the image panel's empty target_url always
 * arrived and `target_url ?? rich_target_url` picked the empty string.
 * The URL was never saved and the served ad had no link.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Modules\Placements\Placement_Engine;
use WBAM_Pro\Modules\AdSubmissions\Ad_Submission_Manager;
use WBAM_Pro\Modules\AdSubmissions\Ad_Submission_Shortcodes;

class Test_Rich_Content_Click_Url extends Pro_Test_Case {

	public function tear_down(): void {
		$_POST = array();
		parent::tear_down();
	}

	/**
	 * Run the portal's shared create/update collector against a POST body.
	 */
	private function collect( array $post ): array {
		$_POST  = $post;
		$method = new \ReflectionMethod( Ad_Submission_Shortcodes::class, 'collect_ad_data_from_post' );

		return $method->invoke( new Ad_Submission_Shortcodes() );
	}

	private function portal_post( string $type ): array {
		return array(
			'title'           => 'Rich click guard',
			'ad_type'         => $type,
			// Both panels post: the hidden one arrives empty.
			'target_url'      => 'image' === $type ? 'https://image.example/landing' : '',
			'rich_target_url' => 'rich-content' === $type ? 'https://rich.example/landing' : '',
			'content'         => '<strong>Rich</strong> body copy',
			'image_url'       => 'https://image.example/banner.png',
			'new_tab'         => '1',
		);
	}

	public function test_rich_ad_saves_and_renders_its_click_url(): void {
		$data = $this->collect( $this->portal_post( 'rich-content' ) );
		$this->assertSame( 'https://rich.example/landing', $data['click_url'] );

		$ad_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'wbam-ad',
				'post_status' => 'publish',
			)
		);
		Ad_Submission_Manager::get_instance()->update_ad_meta( $ad_id, $data );

		$this->assertSame( 'https://rich.example/landing', get_post_meta( $ad_id, '_wbam_click_url', true ), 'Edit screen and admin View read this key.' );

		$output = Placement_Engine::get_instance()->render_ad(
			$ad_id,
			array(
				'allow_duplicate' => true,
				'skip_targeting'  => true,
			)
		);

		$this->assertStringContainsString( 'href="https://rich.example/landing"', $output, 'The served rich ad must link to its click URL.' );
		$this->assertStringContainsString( 'target="_blank"', $output );
		$this->assertStringContainsString( 'Rich</strong> body copy', $output );
	}

	public function test_image_ad_still_reads_its_own_field(): void {
		$data = $this->collect( $this->portal_post( 'image' ) );

		$this->assertSame( 'https://image.example/landing', $data['click_url'] );
	}

	public function test_rich_content_with_its_own_links_is_not_nested(): void {
		$ad_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'wbam-ad',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $ad_id, '_wbam_enabled', '1' );
		update_post_meta(
			$ad_id,
			'_wbam_ad_data',
			array(
				'type'     => 'rich-content',
				'content'  => 'Read <a href="https://own.example/">our guide</a>',
				'link_url' => 'https://rich.example/landing',
			)
		);

		$output = Placement_Engine::get_instance()->render_ad(
			$ad_id,
			array(
				'allow_duplicate' => true,
				'skip_targeting'  => true,
			)
		);

		$this->assertStringContainsString( 'https://own.example/', $output );
		$this->assertStringNotContainsString( 'rich.example', $output, 'An <a> inside an <a> is invalid HTML.' );
	}

	public function test_admin_metabox_save_keeps_the_link(): void {
		$handler = Placement_Engine::get_instance()->get_ad_type( 'rich-content' );
		$saved   = $handler->save(
			0,
			array(
				'content'       => 'Body',
				// The image panel's field posts in the same form.
				'link_url'      => 'https://image-panel.example/',
				'rich_link_url' => 'https://rich.example/landing',
				'rich_target'   => '_self',
			)
		);

		$this->assertSame( 'https://rich.example/landing', $saved['link_url'] );
		$this->assertSame( '_self', $saved['target'] );
	}
}
