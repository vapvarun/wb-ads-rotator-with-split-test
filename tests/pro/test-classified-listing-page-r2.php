<?php
/**
 * Listing page QA round 2 (Basecamp card 10339749933).
 *
 * - A guest's Follow link on /seller/<slug>/ returns them to that seller,
 *   not to get_permalink() of whatever post the virtual page resolves to.
 * - The sticky aside offset is filterable and reaches the page as a CSS
 *   variable through the stylesheet, never a raw <style> tag.
 * - The contact form's message label is visible.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Core\Template_Loader;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Shortcodes;

class Test_Classified_Listing_Page_R2 extends Pro_Test_Case {

	private object $advertiser;
	private object $classified;

	public function set_up(): void {
		parent::set_up();

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );
		$classifieds                     = get_option( 'wbam_pro_classifieds_settings', array() );
		$classifieds['require_approval'] = false;
		$classifieds['enable_inquiries'] = true;
		update_option( 'wbam_pro_classifieds_settings', $classifieds );

		$user             = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		$this->classified = Classified_Manager::get_instance()->create(
			array(
				'title'          => 'R2 listing',
				'description'    => 'Listing page round two.',
				'advertiser_id'  => $this->advertiser->id,
				'status'         => 'active',
				'contact_method' => 'form',
			)
		);
		$this->assertNotWPError( $this->classified );
	}

	public function tear_down(): void {
		set_query_var( 'wbam_advertiser', null );
		wp_dequeue_style( 'wbam-pro-classified' );
		wp_styles()->registered['wbam-pro-classified']->extra = array();
		parent::tear_down();
	}

	public function test_guest_follow_login_returns_to_the_seller_profile(): void {
		wp_set_current_user( 0 );

		// A real post is the queried object, as on the virtual /seller/ route.
		$this->go_to( get_permalink( $this->classified->post_id ) );
		set_query_var( 'wbam_advertiser', $this->advertiser );

		// Block themes in the test suite ship no header.php / footer.php.
		$this->setExpectedDeprecated( 'Theme without header.php' );
		$this->setExpectedDeprecated( 'Theme without footer.php' );

		ob_start();
		include WBAM_PRO_PATH . 'templates/seller-profile.php';
		$html = (string) ob_get_clean();

		$this->assertMatchesRegularExpression( '/href="([^"]+)" class="wbam-follow-btn wbam-follow-login"/', $html );
		preg_match( '/href="([^"]+)" class="wbam-follow-btn wbam-follow-login"/', $html, $m );
		$expected = wp_login_url( $this->advertiser->get_profile_url() );
		$this->assertSame( esc_url( $expected ), $m[1], 'After login the guest lands back on the seller they wanted to follow.' );
	}

	public function test_sticky_offset_is_filterable_css_variable(): void {
		$this->go_to( get_permalink( $this->classified->post_id ) );
		$GLOBALS['wp_query']->the_post();

		add_filter(
			'wbam_pro_single_sticky_offset',
			static function () {
				return '120px;}body{color:red';
			}
		);
		$html = Classified_Shortcodes::get_instance()->single_classified_content( '' );

		$inline = implode( '', (array) wp_styles()->get_data( 'wbam-pro-classified', 'after' ) );
		$this->assertStringContainsString( '--wbam-sticky-offset:120pxbodycolorred;', $inline, 'Braces and semicolons are stripped, so the value cannot break out of the rule.' );
		$this->assertStringNotContainsString( '<style', $html );
	}

	public function test_message_label_is_visible(): void {
		$this->go_to( get_permalink( $this->classified->post_id ) );
		$GLOBALS['wp_query']->the_post();
		wp_set_current_user( 0 );

		ob_start();
		Template_Loader::load_template( 'classifieds/single', array( 'classified' => Classified_Manager::get_instance()->get( (int) $this->classified->id ) ) );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( '<label for="wbam-contact-message">', $html );
	}
}
