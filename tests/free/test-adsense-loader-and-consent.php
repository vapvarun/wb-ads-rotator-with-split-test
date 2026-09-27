<?php
/**
 * AdSense (card 10344381767, owner-seat audit): Google's loader must print
 * after every placement renders (Footer is wp_footer 10, Popup and Sticky
 * 50), and 'Require consent for AdSense' covers ad units, not only Auto Ads.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Core\Settings_Helper;
use WBAM\Modules\AdTypes\AdSense_Ad;
use WP_UnitTestCase;

class Test_AdSense_Loader_And_Consent extends WP_UnitTestCase {

	private function reset_flags(): void {
		foreach ( array( 'ad_rendered', 'script_enqueued' ) as $flag ) {
			$prop = new \ReflectionProperty( AdSense_Ad::class, $flag );
			$prop->setAccessible( true );
			$prop->setValue( null, 'ad_rendered' === $flag ? '' : false );
		}
	}

	private function adsense_ad(): int {
		$ad_id = self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_status' => 'publish' ) );
		update_post_meta( $ad_id, '_wbam_ad_type', 'adsense' );
		update_post_meta(
			$ad_id,
			'_wbam_ad_data',
			array(
				'slot_id'      => '1234567890',
				'publisher_id' => 'ca-pub-1234567890123456',
			)
		);
		return $ad_id;
	}

	public function set_up(): void {
		parent::set_up();
		$this->reset_flags();
	}

	public function tear_down(): void {
		$this->reset_flags();
		parent::tear_down();
	}

	public function test_the_loader_runs_after_popup_and_sticky(): void {
		$ad = new AdSense_Ad();
		$this->assertSame( 1000, has_action( 'wp_footer', array( $ad, 'maybe_enqueue_adsense_script' ) ) );
	}

	public function test_an_ad_rendered_in_the_footer_still_gets_the_loader(): void {
		$ad   = new AdSense_Ad();
		$html = $ad->render( $this->adsense_ad() );
		$this->assertStringContainsString( 'adsbygoogle', $html );

		ob_start();
		$ad->maybe_enqueue_adsense_script();
		$out = (string) ob_get_clean();

		$this->assertStringContainsString( 'pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1234567890123456', $out );
		$this->assertStringContainsString( 'async', $out );
		$this->assertStringContainsString( 'crossorigin="anonymous"', $out );
	}

	public function test_no_consent_means_no_unit_and_no_script(): void {
		Settings_Helper::update( 'require_consent_adsense', true );
		unset( $_COOKIE['cookie_notice_accepted'] );

		$ad = new AdSense_Ad();
		$this->assertSame( '', $ad->render( $this->adsense_ad() ) );

		ob_start();
		$ad->maybe_enqueue_adsense_script();
		$this->assertSame( '', (string) ob_get_clean() );

		$_COOKIE['cookie_notice_accepted'] = 'true';
		$this->assertStringContainsString( 'adsbygoogle', $ad->render( $this->adsense_ad() ), 'With consent the unit renders.' );
		unset( $_COOKIE['cookie_notice_accepted'] );
	}
}
