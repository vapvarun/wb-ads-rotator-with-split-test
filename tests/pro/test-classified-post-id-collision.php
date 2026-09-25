<?php
/**
 * A classified id that equals another listing's post id must not hijack it.
 *
 * Regression guard for Basecamp card 10339749933: Classified::load() tried
 * the classified id first and fell back to post_id, and the single page,
 * the SEO schema, GA4 tracking and the editor status sync all passed a post
 * id. So /classifieds/qa-c3-expiry/ (post 80) rendered classified 80, a
 * different listing. The constructor now takes only a classified id and
 * post-id callers use Classified_Manager::get_by_post().
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_SEO;
use WBAM_Pro\Modules\Classifieds\Classified_Shortcodes;

class Test_Classified_Post_Id_Collision extends Pro_Test_Case {

	private object $viewed;
	private object $other;

	public function set_up(): void {
		parent::set_up();
		global $wpdb;

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );
		$classifieds                     = get_option( 'wbam_pro_classifieds_settings', array() );
		$classifieds['require_approval'] = false;
		update_option( 'wbam_pro_classifieds_settings', $classifieds );

		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		$manager    = Classified_Manager::get_instance();

		$this->other  = $manager->create(
			array(
				'title'         => 'Other Listing Hijacker',
				'description'   => 'Must never appear on the viewed page.',
				'advertiser_id' => $advertiser->id,
				'status'        => 'active',
			)
		);
		$this->viewed = $manager->create(
			array(
				'title'         => 'Viewed Listing Owner',
				'description'   => 'The page being visited.',
				'advertiser_id' => $advertiser->id,
				'status'        => 'active',
			)
		);
		$this->assertNotWPError( $this->other );
		$this->assertNotWPError( $this->viewed );

		// Make the other listing's classified id equal the viewed listing's post id.
		$table = $wpdb->prefix . 'wbam_classifieds';
		$this->assertNull( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d", $this->viewed->post_id ) ), 'Precondition: target id is free.' ); // phpcs:ignore WordPress.DB
		$wpdb->update( $table, array( 'id' => (int) $this->viewed->post_id ), array( 'id' => (int) $this->other->id ) ); // phpcs:ignore WordPress.DB
		$this->other = $manager->get( (int) $this->viewed->post_id );
		$this->assertSame( 'Other Listing Hijacker', $this->other->get_title() );
	}

	public function test_constructor_takes_only_a_classified_id(): void {
		$by_post_id = new Classified( (int) $this->viewed->post_id );
		$this->assertSame( (int) $this->other->id, (int) $by_post_id->id, 'An id is a classified id, never a post id.' );

		$none = new Classified( (int) $this->other->post_id );
		$this->assertSame( 0, (int) $none->id, 'No post_id fallback guess.' );
	}

	public function test_single_page_renders_its_own_listing(): void {
		$this->go_to( get_permalink( $this->viewed->post_id ) );
		$GLOBALS['wp_query']->the_post();

		$html = Classified_Shortcodes::get_instance()->single_classified_content( '' );

		$this->assertStringContainsString( 'Viewed Listing Owner', $html );
		$this->assertStringNotContainsString( 'Other Listing Hijacker', $html );
	}

	public function test_seo_schema_and_ga4_describe_the_viewed_listing(): void {
		$this->go_to( get_permalink( $this->viewed->post_id ) );
		$GLOBALS['wp_query']->the_post();

		$seo = new Classified_SEO();
		ob_start();
		$seo->output_schema_markup();
		$seo->output_ga4_tracking();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Viewed Listing Owner', $html );
		$this->assertStringNotContainsString( 'Other Listing Hijacker', $html );
	}

	public function test_editor_status_change_syncs_only_its_own_row(): void {
		wp_update_post(
			array(
				'ID'          => (int) $this->viewed->post_id,
				'post_status' => 'draft',
			)
		);

		$manager = Classified_Manager::get_instance();
		$this->assertSame( 'active', $manager->get( (int) $this->other->id )->status, 'The other listing must not change status.' );
		$this->assertSame( 'draft', $manager->get_by_post( (int) $this->viewed->post_id )->status );
	}
}
