<?php
/**
 * Featured is one upgrade, one price, one period, bought once (cards
 * 10343726590 and 10343726490, QA wave 5 + owner decisions).
 *
 * - Promote used to last 30 days (a hardcoded constant) while posting
 *   lasted the owner's Upgrade Duration (7 days). Every path now applies
 *   the period through Classified_Manager::add_upgrades().
 * - A listing featured at posting (plan credit, or paid) could buy Featured
 *   again on Promote, because only a Promote-bought 'paid' flag counted.
 * - The upgrades bundle no longer includes Featured at a discount.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM\Tests\Helpers\Factory;
use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Core\Template_Loader;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Shortcodes;
use WBAM_Pro\Modules\Memberships\Membership_Manager;

class Test_Featured_One_Period_One_Purchase extends Pro_Test_Case {

	private object $advertiser;

	private int $user_id;

	public function set_up(): void {
		parent::set_up();

		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}wbam_classifieds" ); // phpcs:ignore WordPress.DB -- test isolation, see Test_Classified_Featured_Upgrade_Fee.

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		$enabled['memberships'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );
		update_option( 'wbam_credits_payment_method', 'manual' );
		Settings_Helper::update_module( 'classifieds', 'featured_price', 5 );
		Settings_Helper::update_module( 'classifieds', 'upgrade_duration', 7 );

		$this->user_id    = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $this->user_id );
		Factory::topup_user( $this->user_id, 10000 );
	}

	public function tear_down(): void {
		delete_option( 'wbam_credits_payment_method' );
		parent::tear_down();
	}

	private function active_listing() {
		$manager    = Classified_Manager::get_instance();
		$classified = $manager->create(
			array(
				'title'         => 'Featured period probe',
				'description'   => 'Probe.',
				'advertiser_id' => $this->advertiser->id,
			)
		);
		$this->assertNotWPError( $classified );
		$manager->update( (int) $classified->id, array( 'status' => 'active' ) );
		return $manager->get( (int) $classified->id );
	}

	private function promote_html( $classified ): string {
		$shortcodes = ( new \ReflectionClass( Classified_Shortcodes::class ) )->newInstanceWithoutConstructor();
		ob_start();
		( new \ReflectionMethod( $shortcodes, 'render_promote_form' ) )->invoke( $shortcodes, $this->advertiser, Classified_Manager::get_instance()->get( (int) $classified->id ), home_url( '/' ) );
		return (string) ob_get_clean();
	}

	/** Card 10343726590: Promote lasts the same Upgrade Duration as posting, not a hidden 30 days. */
	public function test_promote_featured_lasts_the_upgrade_duration(): void {
		$classified = $this->active_listing();

		$this->assertTrue( $classified->upgrade_to_featured( 1, 5.0, true ) );

		$expires = strtotime( Classified_Manager::get_instance()->get( (int) $classified->id )->featured_expires_at . ' UTC' );
		$this->assertEqualsWithDelta( time() + 7 * DAY_IN_SECONDS, $expires, HOUR_IN_SECONDS, 'Promote must use the Upgrade Duration (7 days).' );
		$this->assertArrayHasKey( 'featured', $classified->get_active_upgrades(), 'Promote records the same upgrade row posting does, so the expiry cron ends it.' );
	}

	/** Card 10343726590: the Promote page states the period it sells. */
	public function test_promote_page_states_the_featured_period(): void {
		$this->assertStringContainsString( 'for 7 days', $this->promote_html( $this->active_listing() ) );
	}

	/** Card 10343726490 (a): a listing featured at posting cannot be charged for Featured again. */
	public function test_a_listing_featured_at_posting_cannot_buy_featured_again(): void {
		$classified = $this->active_listing();
		$this->assertTrue( Classified_Manager::get_instance()->add_upgrades( (int) $classified->id, array( 'featured' ) ) );
		$classified = Classified_Manager::get_instance()->get( (int) $classified->id );

		$before = (float) Credits_Bridge::get_balance( $this->advertiser->id );
		$result = $classified->upgrade_to_featured( 1, 5.0, true );

		$this->assertWPError( $result );
		$this->assertSame( 'already_featured', $result->get_error_code() );
		$this->assertSame( $before, (float) Credits_Bridge::get_balance( $this->advertiser->id ), 'Nothing may be charged.' );

		$html = $this->promote_html( $classified );
		$this->assertStringNotContainsString( 'wbam-upgrade-featured', $html, 'Promote must not offer Featured to a listing that already has it.' );
		$this->assertStringNotContainsString( '1970', $html, 'The featured-until date comes from the running period.' );
	}

	/** Card 10343726490 (b): a plan member with a Featured credit sees "Included in plan" on the Upgrades step, not "+$5.00". */
	public function test_upgrades_step_shows_featured_included_for_a_plan_member(): void {
		$members = Membership_Manager::get_instance();
		$members->save_plan(
			array(
				'name'          => 'Featured plan',
				'price'         => 0,
				'billing_cycle' => 'monthly',
				'max_listings'  => 0,
				'max_featured'  => 1,
				'status'        => 'active',
			)
		);
		$plans = $members->get_plans();
		$this->assertNotWPError( $members->subscribe( $this->advertiser->id, end( $plans )->id ) );

		$html = (string) Template_Loader::load_template(
			'portal/classified-form',
			array(
				'advertiser'    => $this->advertiser,
				'classified_id' => 0,
				'is_edit'       => false,
			),
			true
		);

		$step5 = substr( $html, (int) strpos( $html, '<div class="wbam-wizard-step" data-step="5">' ) );
		$card  = substr( $step5, (int) strpos( $step5, 'value="featured"' ), 1200 );
		$this->assertStringContainsString( 'Included in plan', $card );
		$this->assertStringNotContainsString( '+$5.00', $card );
		$this->assertStringContainsString( 'for 7 days', $card, 'The period is still stated.' );
	}

	/** Owner decision: the bundle no longer includes Featured; Featured is always its one price. */
	public function test_bundle_does_not_include_featured(): void {
		Settings_Helper::update_module( 'classifieds', 'highlighted_price', 10 );
		Settings_Helper::update_module( 'classifieds', 'top_price', 10 );
		$term = wp_insert_term( 'Bundle ' . wp_generate_password( 6, false ), Classified_Manager::TAXONOMY_CATEGORY );

		$before     = (float) Credits_Bridge::get_balance( $this->advertiser->id );
		$classified = Classified_Manager::get_instance()->submit(
			$this->advertiser,
			array(
				'title'           => 'Bundle probe',
				'description'     => 'Bundle only.',
				'categories'      => array( (int) $term['term_id'] ),
				'listing_package' => 0,
				'upgrades'        => array( 'bundle' ),
			)
		);

		$this->assertNotWPError( $classified, is_wp_error( $classified ) ? $classified->get_error_message() : '' );
		$this->assertSame( 16.0, round( $before - (float) Credits_Bridge::get_balance( $this->advertiser->id ), 2 ), 'Bundle = (Highlighted + Top) at 20% off, no Featured.' );
		$pending = (array) $classified->get_meta( 'pending_upgrades' );
		$applied = array_keys( $classified->get_active_upgrades() );
		$this->assertNotContains( 'featured', array_merge( $pending, $applied ) );
	}

	/** Card 10343726490 (c): Review copy - shortfall wording, one charge-timing line, no Buy credits when nothing is owed. */
	public function test_review_banner_copy(): void {
		$banner = (string) file_get_contents( WBAM_PRO_PATH . 'templates/portal/partials/cost-balance-banner.php' );
		$js     = (string) file_get_contents( WBAM_PRO_PATH . 'assets/js/portal.js' );

		$this->assertStringContainsString( 'Short by', $banner, 'A shortfall reads "Short by $X", not a negative balance.' );
		$this->assertStringNotContainsString( 'data-wbam-timing', $banner, 'The Review cost note already says when credits are charged.' );
		$this->assertStringNotContainsString( '−', $js, 'No negative balance is printed any more.' );
		$this->assertStringContainsString( "find('.wbam-credit-banner__cta').toggle(cost > 0)", $js, 'Buy credits hides when nothing is owed.' );
	}
}
