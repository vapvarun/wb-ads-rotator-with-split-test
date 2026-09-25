<?php
/**
 * Demo importer recovers legacy-renamed users instead of skipping them.
 *
 * Regression guard for the QA bounce on Basecamp cards 10163569807 /
 * 10163569637: sites seeded by an older importer carry renamed logins
 * (techstartup_demo) with the same demo email. Matching only by login
 * sent those into wp_create_user, which failed on the duplicate email
 * and `continue`d - leaving $user_ids/$advertiser_ids sparse, so the
 * downstream isset() guards silently skipped ad relinking and ledger
 * seeding for exactly the advertisers a healing re-import exists to
 * repair. The importer now looks up by email too, and when the match is
 * an account it did not create it fills the index with its own fallback
 * identity instead of adopting the account or abandoning the index.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Settings_Helper;

class Test_Demo_Import_Legacy_User_Recovery extends Pro_Test_Case {

	public function set_up(): void {
		parent::set_up();

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );
	}

	private function run_create_advertisers(): object {
		if ( ! defined( 'WBAM_DEMO_DATA_INCLUDED' ) ) {
			define( 'WBAM_DEMO_DATA_INCLUDED', true );
		}
		require_once WBAM_PRO_PATH . 'demo-data-setup.php';

		$generator = new \WBAM_Demo_Data_Generator();
		$method    = new \ReflectionMethod( \WBAM_Demo_Data_Generator::class, 'create_advertisers' );
		$method->setAccessible( true );
		ob_start();
		$method->invoke( $generator );
		ob_end_clean();

		return $generator;
	}

	private function generator_prop( object $generator, string $prop ): array {
		$ref = new \ReflectionProperty( \WBAM_Demo_Data_Generator::class, $prop );
		$ref->setAccessible( true );

		return (array) $ref->getValue( $generator );
	}

	public function test_legacy_renamed_user_is_recovered_by_email(): void {
		// The legacy state: same demo email, different (renamed) login.
		$legacy = (int) self::factory()->user->create(
			array(
				'user_login' => 'techstartup_demo',
				'user_email' => 'ads@techstartup.demo',
				'role'       => 'subscriber',
			)
		);

		$generator = $this->run_create_advertisers();
		$user_ids  = $this->generator_prop( $generator, 'user_ids' );

		// Card 10340186779: demo rows attach only to accounts the importer
		// created - Remove never deletes a legacy account, so anything hung
		// on it stayed forever. The index is filled by the importer's own
		// fallback identity instead of being abandoned.
		$this->assertNotContains( $legacy, array_map( 'intval', $user_ids ), 'Demo data must not attach to a legacy account the importer did not create.' );
		$this->assertArrayHasKey( 0, $user_ids, 'The index is still filled, by an importer-created user.' );
		$this->assertSame( '1', (string) get_user_meta( (int) $user_ids[0], \WBAM_Demo_Data_Generator::USER_MARKER, true ) );

		$advertiser_ids = $this->generator_prop( $generator, 'advertiser_ids' );
		$this->assertCount(
			count( $user_ids ),
			$advertiser_ids,
			'No sparse indices: every resolved user must have an advertiser id, or downstream healing silently skips it.'
		);
	}

	public function test_encoded_display_name_is_healed(): void {
		self::factory()->user->create(
			array(
				'user_login'   => 'localshop_demo',
				'user_email'   => 'marketing@localshop.demo',
				'display_name' => 'Local Shop &amp;amp; More',
				'role'         => 'subscriber',
			)
		);

		$this->run_create_advertisers();

		$user = get_user_by( 'email', 'marketing@localshop.demo' );
		$this->assertSame(
			'Local Shop &amp; More',
			$user->display_name,
			'Double-encoded legacy display names heal to the canonical single-encoded form WP core stores.'
		);
	}
}
