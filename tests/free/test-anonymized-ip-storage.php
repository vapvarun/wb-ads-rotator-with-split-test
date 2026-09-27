<?php
/**
 * Anonymised IPs fit the columns that store them.
 *
 * Privacy_Helper::get_storage_ip() always returns a 64-char SHA-256 hash
 * (card 10344382999: there is no setting that stores a raw IP). The ip_address columns were varchar(45),
 * so $wpdb refused every partnership inquiry insert and the form answered
 * "An error occurred" to every visitor.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Core\Privacy_Helper;
use WBAM\Modules\Links\Partnership_Manager;
use WP_UnitTestCase;

class Test_Anonymized_Ip_Storage extends WP_UnitTestCase {

	public function test_partnership_inquiry_saves_with_anonymized_ip(): void {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
		$this->assertSame( 64, strlen( Privacy_Helper::get_storage_ip() ) );

		$partnership = Partnership_Manager::get_instance()->create(
			array(
				'name'             => 'Inquirer',
				'email'            => 'inquirer@example.com',
				'website_url'      => 'https://wordpress.org',
				'partnership_type' => 'paid_link',
				'message'          => 'Anonymised IP storage',
			)
		);

		$this->assertNotEmpty( $partnership, 'A 64-char IP hash must not make the insert fail.' );
	}

	public function test_the_stored_ip_is_never_the_raw_address(): void {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
		update_option( 'wbam_settings', array( 'anonymize_ip' => false ) );

		$this->assertNotSame( '203.0.113.7', Privacy_Helper::get_storage_ip(), 'An old "off" value must not store the raw IP.' );
		$this->assertSame( 64, strlen( Privacy_Helper::get_storage_ip() ) );
	}
}
