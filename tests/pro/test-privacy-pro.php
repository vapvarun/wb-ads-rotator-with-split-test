<?php
/**
 * Pro's personal data exporters and erasers (card 10345179396).
 *
 * One member who is an advertiser, a seller, an inquirer, a reporter and a
 * reviewer, plus a guest who wrote through the inbox. Every source exports
 * what it holds; erasing follows the owner's rules: money stays exactly as it
 * was, shared history is anonymised in place, the person's own data goes.
 *
 * Fixtures are inserted directly (no SDK money calls, which commit the test
 * transaction) and deleted in tear_down.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

class Test_Privacy_Pro extends Pro_Test_Case {

	private const EMAIL = 'priv-member@example.org';
	private const GUEST = 'priv-guest@example.org';

	private int $user          = 0;
	private int $advertiser_id = 0;
	private int $classified    = 0;
	private int $thread        = 0;

	/** @var array<string,int[]> Table => ids this test inserted. */
	private array $made = array();

	public function set_up(): void {
		parent::set_up();
		global $wpdb;

		$this->user          = (int) self::factory()->user->create( array( 'user_email' => self::EMAIL ) );
		$this->advertiser_id = (int) Advertiser_Manager::get_instance()->get_or_create( $this->user )->id;
		$wpdb->update(
			$wpdb->prefix . 'wbam_advertisers',
			array(
				'company_name' => 'Priv Co',
				'phone'        => '555-0100',
				'address'      => '1 Main St',
				'website'      => 'https://priv.example',
			),
			array( 'id' => $this->advertiser_id )
		);

		$post             = (int) self::factory()->post->create( array( 'post_title' => 'Blue bike', 'post_type' => 'wbam-classified' ) );
		$this->classified = $this->insert(
			'wbam_classifieds',
			array(
				'post_id'       => $post,
				'advertiser_id' => $this->advertiser_id,
				'contact_name'  => 'Priv Seller',
				'contact_email' => self::EMAIL,
				'contact_phone' => '555-0101',
				'status'        => 'active',
			)
		);

		$this->insert( 'wbam_classified_inquiries', array( 'classified_id' => $this->classified, 'sender_user_id' => $this->user, 'sender_name' => 'Priv', 'sender_email' => self::EMAIL, 'sender_phone' => '555-0102', 'message' => 'Is it available?' ) );
		$this->insert( 'wbam_classified_inquiries', array( 'classified_id' => $this->classified, 'sender_name' => 'Guest G', 'sender_email' => self::GUEST, 'message' => 'Guest question' ) );

		$this->thread = $this->insert( 'wbam_message_threads', array( 'classified_id' => $this->classified, 'guest_name' => 'Guest G', 'guest_email' => self::GUEST, 'participant_a' => $this->user, 'participant_b' => 0 ) );
		$this->insert( 'wbam_messages', array( 'thread_id' => $this->thread, 'sender_id' => 0, 'sender_type' => 'guest', 'content' => 'Guest message' ) );
		$this->insert( 'wbam_messages', array( 'thread_id' => $this->thread, 'sender_id' => $this->user, 'sender_type' => 'user', 'content' => 'Seller reply' ) );

		$this->insert( 'wbam_classified_reports', array( 'classified_id' => $this->classified, 'reporter_user_id' => $this->user, 'reporter_name' => 'Priv', 'reporter_email' => self::EMAIL, 'reason' => 'spam', 'details' => 'Looks fake', 'status' => 'pending' ) );
		$this->insert( 'wbam_reviews', array( 'advertiser_id' => $this->advertiser_id + 1000, 'reviewer_user_id' => $this->user, 'classified_id' => $this->classified, 'rating' => 4, 'title' => 'Good seller', 'comment' => 'Fast reply', 'status' => 'approved' ) );
		$this->insert( 'wbam_audit_log', array( 'user_id' => $this->user, 'action' => 'ad_submitted', 'object_type' => 'ad', 'object_id' => 1, 'ip_address' => '203.0.113.7', 'created_at' => '2026-09-01 10:00:00' ) );
		$this->insert( 'wbam_analytics', array( 'ad_id' => 1, 'event_type' => 'impression', 'user_id' => $this->user, 'created_at' => '2026-09-01 10:00:00' ) );

		$this->insert( 'wbam_credit_ledger', array( 'user_id' => $this->user, 'item_id' => 0, 'entry_type' => 'topup', 'amount' => 5000, 'note' => 'Top-up', 'created_at' => '2026-09-01 10:00:00' ) );
		$this->insert( 'wbam_revenue', array( 'ledger_id' => 0, 'advertiser_id' => $this->advertiser_id, 'source' => 'offline', 'item_type' => 'topup', 'item_id' => 0, 'amount' => 50, 'created_at' => '2026-09-01 10:00:00' ) );

		update_user_meta( $this->user, 'wbam_profile_extra', array( 'contact_name' => 'Priv Person', 'city' => 'Pune' ) );
		update_user_meta( $this->user, '_wbam_favorite_classifieds', array( $this->classified ) );
		update_user_meta( $this->user, '_wbam_following_sellers', array( $this->advertiser_id ) );
	}

	public function tear_down(): void {
		global $wpdb;
		foreach ( $this->made as $table => $ids ) {
			$wpdb->query( "DELETE FROM {$wpdb->prefix}{$table} WHERE " . ( 'wbam_classified_meta' === $table ? 'meta_id' : 'id' ) . ' IN (' . implode( ',', array_map( 'absint', $ids ) ) . ')' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		parent::tear_down();
	}

	private function insert( string $table, array $row ): int {
		global $wpdb;
		$wpdb->insert( $wpdb->prefix . $table, $row );
		$this->made[ $table ][] = (int) $wpdb->insert_id;
		return (int) $wpdb->insert_id;
	}

	private function run_all( string $filter, string $email ): array {
		$out = array();
		foreach ( apply_filters( $filter, array() ) as $key => $tool ) {
			if ( ! str_starts_with( $key, 'wbam-pro-' ) ) {
				continue;
			}
			$page = 1;
			do {
				$result = call_user_func( $tool['callback'], $email, $page++ );
				$out[ $key ][] = $result;
			} while ( empty( $result['done'] ) && $page < 50 );
		}
		return $out;
	}

	private function exported_values( string $email ): string {
		$values = array();
		foreach ( $this->run_all( 'wp_privacy_personal_data_exporters', $email ) as $pages ) {
			foreach ( $pages as $page ) {
				foreach ( $page['data'] as $item ) {
					foreach ( $item['data'] as $pair ) {
						$values[] = $pair['value'];
					}
				}
			}
		}
		return implode( ' | ', $values );
	}

	private function money_snapshot(): array {
		global $wpdb;
		return array(
			(string) $wpdb->get_var( $wpdb->prepare( "SELECT CONCAT( COUNT(*), ':', SUM(amount) ) FROM {$wpdb->prefix}wbam_credit_ledger WHERE user_id = %d", $this->user ) ),
			(string) $wpdb->get_var( $wpdb->prepare( "SELECT CONCAT( COUNT(*), ':', SUM(amount) ) FROM {$wpdb->prefix}wbam_revenue WHERE advertiser_id = %d", $this->advertiser_id ) ),
		);
	}

	public function test_member_export_covers_every_source(): void {
		$all = $this->exported_values( self::EMAIL );

		foreach ( array( 'Priv Co', '555-0100', 'Blue bike', '555-0101', 'Is it available?', 'Seller reply', 'Looks fake', 'Fast reply', '203.0.113.7', 'Priv Person', 'Pune' ) as $expected ) {
			$this->assertStringContainsString( $expected, $all, "Export includes: $expected" );
		}
		$this->assertStringNotContainsString( 'Guest question', $all, 'Another person\'s inquiry is not in this export.' );
	}

	public function test_guest_export_finds_their_inquiry_and_messages(): void {
		$all = $this->exported_values( self::GUEST );

		$this->assertStringContainsString( 'Guest question', $all );
		$this->assertStringContainsString( 'Guest message', $all );
		$this->assertStringNotContainsString( 'Seller reply', $all );
	}

	public function test_member_erase_follows_the_owner_rules(): void {
		global $wpdb;
		$p      = $wpdb->prefix;
		$before = $this->money_snapshot();

		$messages = '';
		foreach ( $this->run_all( 'wp_privacy_personal_data_erasers', self::EMAIL ) as $pages ) {
			foreach ( $pages as $page ) {
				$messages .= ' ' . implode( ' ', $page['messages'] );
			}
		}

		// Money stays exactly as it was, and WordPress is told why.
		$this->assertSame( $before, $this->money_snapshot() );
		$this->assertStringContainsString( 'financial and tax record', $messages );

		// Own data goes.
		$adv = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$p}wbam_advertisers WHERE id = %d", $this->advertiser_id ) );
		$this->assertNotNull( $adv, 'The advertiser row stays.' );
		$this->assertSame( array( '', '', '', '' ), array( $adv->company_name, $adv->phone, $adv->address, $adv->website ) );
		$this->assertSame( '', get_user_meta( $this->user, 'wbam_profile_extra', true ) );
		$this->assertSame( '', get_user_meta( $this->user, '_wbam_favorite_classifieds', true ) );
		$listing = $wpdb->get_row( $wpdb->prepare( "SELECT contact_name, contact_email, contact_phone FROM {$p}wbam_classifieds WHERE id = %d", $this->classified ) );
		$this->assertSame( array( '', '', '' ), array( $listing->contact_name, $listing->contact_email, $listing->contact_phone ) );

		// Shared history stays, anonymised.
		$mask    = wp_privacy_anonymize_data( 'longtext' );
		$inquiry = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$p}wbam_classified_inquiries WHERE id = %d", $this->made['wbam_classified_inquiries'][0] ) );
		$this->assertNull( $inquiry->sender_user_id );
		$this->assertSame( array( '', '', '', $mask ), array( $inquiry->sender_name, $inquiry->sender_email, $inquiry->sender_phone, $inquiry->message ) );
		$this->assertSame( 'Guest question', $wpdb->get_var( $wpdb->prepare( "SELECT message FROM {$p}wbam_classified_inquiries WHERE id = %d", $this->made['wbam_classified_inquiries'][1] ) ), 'The guest\'s inquiry is not touched.' );
		$this->assertSame( $mask, $wpdb->get_var( $wpdb->prepare( "SELECT content FROM {$p}wbam_messages WHERE id = %d", $this->made['wbam_messages'][1] ) ) );
		$this->assertSame( 'Guest message', $wpdb->get_var( $wpdb->prepare( "SELECT content FROM {$p}wbam_messages WHERE id = %d", $this->made['wbam_messages'][0] ) ) );
		$report = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$p}wbam_classified_reports WHERE id = %d", $this->made['wbam_classified_reports'][0] ) );
		$this->assertSame( array( '', '', $mask ), array( $report->reporter_name, $report->reporter_email, $report->details ) );
		$review = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$p}wbam_reviews WHERE id = %d", $this->made['wbam_reviews'][0] ) );
		$this->assertSame( array( '4', '', $mask ), array( (string) $review->rating, $review->title, $review->comment ), 'The rating stays; the words go.' );
		$this->assertNull( $wpdb->get_var( $wpdb->prepare( "SELECT ip_address FROM {$p}wbam_audit_log WHERE id = %d", $this->made['wbam_audit_log'][0] ) ) );
		$this->assertNull( $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$p}wbam_analytics WHERE id = %d", $this->made['wbam_analytics'][0] ) ) );

		// Running it again finds nothing more to change.
		foreach ( $this->run_all( 'wp_privacy_personal_data_erasers', self::EMAIL ) as $key => $pages ) {
			$this->assertFalse( $pages[0]['items_removed'], "$key changes nothing the second time." );
		}
	}

	public function test_guest_erase_clears_the_thread_and_their_words(): void {
		global $wpdb;
		$p = $wpdb->prefix;

		$this->run_all( 'wp_privacy_personal_data_erasers', self::GUEST );

		$thread = $wpdb->get_row( $wpdb->prepare( "SELECT guest_name, guest_email FROM {$p}wbam_message_threads WHERE id = %d", $this->thread ) );
		$this->assertSame( '', $thread->guest_name );
		$this->assertSame( 'erased-' . $this->thread . '@deleted.invalid', $thread->guest_email, 'A unique placeholder, because the email is part of a unique key.' );
		$this->assertSame( wp_privacy_anonymize_data( 'longtext' ), $wpdb->get_var( $wpdb->prepare( "SELECT content FROM {$p}wbam_messages WHERE id = %d", $this->made['wbam_messages'][0] ) ) );
		$this->assertSame( 'Seller reply', $wpdb->get_var( $wpdb->prepare( "SELECT content FROM {$p}wbam_messages WHERE id = %d", $this->made['wbam_messages'][1] ) ), 'The seller\'s own reply stays.' );
	}
}
