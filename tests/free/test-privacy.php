<?php
/**
 * Free's personal data exporters and erasers (card 10345179396): email
 * sign-ups and partnership requests are found by email, exported in pages,
 * and deleted in batches; other people's rows are untouched.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Tests\Helpers\Factory;
use WP_UnitTestCase;

class Test_Privacy extends WP_UnitTestCase {

	private function signup( string $email, int $ad ): void {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'wbam_email_submissions',
			array(
				'ad_id'      => $ad,
				'email'      => $email,
				'name'       => 'Sam',
				'ip_address' => '',
				'created_at' => '2026-09-01 10:00:00',
			)
		);
	}

	private function partnership( string $email ): void {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'wbam_link_partnerships',
			array(
				'name'             => 'Sam',
				'email'            => $email,
				'website_url'      => 'https://example.org',
				'partnership_type' => 'guest_post',
				'message'          => 'Hello',
				'status'           => 'pending',
				'ip_address'       => '203.0.113.9',
				'created_at'       => '2026-09-01 10:00:00',
			)
		);
	}

	private function count_rows( string $table, string $email ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}{$table} WHERE email = %s", $email ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	private function tool( string $filter, string $key ): callable {
		$registered = apply_filters( $filter, array() );
		$this->assertArrayHasKey( $key, $registered, "$key is registered on $filter" );
		return $registered[ $key ]['callback'];
	}

	public function test_signups_export_in_pages_and_erase_in_batches(): void {
		$ad = Factory::make_ad( array( 'post_title' => 'Newsletter' ) );
		for ( $i = 0; $i < 101; $i++ ) {
			$this->signup( 'sam@example.org', $ad );
		}
		$this->signup( 'other@example.org', $ad );

		$export = $this->tool( 'wp_privacy_personal_data_exporters', 'wbam-email-signups' );
		$first  = $export( 'sam@example.org', 1 );
		$second = $export( 'sam@example.org', 2 );
		$this->assertCount( 100, $first['data'] );
		$this->assertFalse( $first['done'] );
		$this->assertCount( 1, $second['data'] );
		$this->assertTrue( $second['done'] );
		$fields = wp_list_pluck( $first['data'][0]['data'], 'value', 'name' );
		$this->assertSame( 'sam@example.org', $fields['Email'] );
		$this->assertSame( 'Newsletter', $fields['Signed up on'] );

		$erase = $this->tool( 'wp_privacy_personal_data_erasers', 'wbam-email-signups' );
		$one   = $erase( 'sam@example.org', 1 );
		$this->assertTrue( $one['items_removed'] );
		$this->assertFalse( $one['done'], 'A full batch asks WordPress to call again.' );
		$two = $erase( 'sam@example.org', 2 );
		$this->assertTrue( $two['done'] );

		$this->assertSame( 0, $this->count_rows( 'wbam_email_submissions', 'sam@example.org' ) );
		$this->assertSame( 1, $this->count_rows( 'wbam_email_submissions', 'other@example.org' ), 'Someone else\'s sign-up stays.' );
	}

	public function test_partnership_requests_export_and_erase(): void {
		$this->partnership( 'sam@example.org' );
		$this->partnership( 'other@example.org' );

		$export = $this->tool( 'wp_privacy_personal_data_exporters', 'wbam-partnership-requests' );
		$page   = $export( 'sam@example.org', 1 );
		$this->assertCount( 1, $page['data'] );
		$this->assertTrue( $page['done'] );
		$fields = wp_list_pluck( $page['data'][0]['data'], 'value', 'name' );
		$this->assertSame( 'Hello', $fields['Message'] );
		$this->assertSame( '203.0.113.9', $fields['IP address'] );

		$erase  = $this->tool( 'wp_privacy_personal_data_erasers', 'wbam-partnership-requests' );
		$result = $erase( 'sam@example.org', 1 );
		$this->assertTrue( $result['items_removed'] );
		$this->assertFalse( $result['items_retained'] );
		$this->assertTrue( $result['done'] );
		$this->assertSame( 0, $this->count_rows( 'wbam_link_partnerships', 'sam@example.org' ) );
		$this->assertSame( 1, $this->count_rows( 'wbam_link_partnerships', 'other@example.org' ) );
	}

	public function test_unknown_email_finds_nothing(): void {
		$export = $this->tool( 'wp_privacy_personal_data_exporters', 'wbam-email-signups' );
		$page   = $export( 'nobody@example.org', 1 );
		$this->assertSame( array(), $page['data'] );
		$this->assertTrue( $page['done'] );
	}
}
