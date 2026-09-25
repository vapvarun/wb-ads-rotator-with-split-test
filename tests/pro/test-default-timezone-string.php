<?php
/**
 * BC#10339750662 item 7: wp_timezone_string() resolves a plain UTC+0 site
 * (gmt_offset = 0, no timezone_string) to 'Africa/Abidjan' — a real IANA
 * zone for that offset, but not the label a UTC+0 site owner set, and
 * wp_timezone_choice() lists it under Africa instead of under the manual
 * "UTC+0" option. wbam_pro_default_timezone_string() replicates the
 * 'UTC±N' fallback WP core's own Settings > General page uses instead.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

class Test_Default_Timezone_String extends Pro_Test_Case {

	private $original_timezone_string;
	private $original_gmt_offset;

	public function set_up(): void {
		parent::set_up();
		$this->original_timezone_string = get_option( 'timezone_string' );
		$this->original_gmt_offset      = get_option( 'gmt_offset' );
	}

	public function tear_down(): void {
		update_option( 'timezone_string', $this->original_timezone_string );
		update_option( 'gmt_offset', $this->original_gmt_offset );
		parent::tear_down();
	}

	public function test_utc_zero_offset_resolves_to_utc_plus_0_not_abidjan(): void {
		update_option( 'timezone_string', '' );
		update_option( 'gmt_offset', '0' );

		$this->assertSame( 'UTC+0', wbam_pro_default_timezone_string() );
	}

	public function test_positive_offset_gets_a_leading_plus(): void {
		update_option( 'timezone_string', '' );
		update_option( 'gmt_offset', '5.5' );

		$this->assertSame( 'UTC+5.5', wbam_pro_default_timezone_string() );
	}

	public function test_negative_offset_keeps_its_own_minus(): void {
		update_option( 'timezone_string', '' );
		update_option( 'gmt_offset', '-8' );

		$this->assertSame( 'UTC-8', wbam_pro_default_timezone_string() );
	}

	public function test_a_real_timezone_string_wins_over_the_offset(): void {
		update_option( 'timezone_string', 'America/New_York' );
		update_option( 'gmt_offset', '0' );

		$this->assertSame( 'America/New_York', wbam_pro_default_timezone_string() );
	}
}
