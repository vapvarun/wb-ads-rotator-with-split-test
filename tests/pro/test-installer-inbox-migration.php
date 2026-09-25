<?php
/**
 * 4.3.10 migration: existing classified inquiries become guest message
 * threads, so the seller's Inbox tab (Basecamp #10342786624) shows them
 * alongside member conversations.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Installer;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Messaging\Message_Manager;

class Test_Installer_Inbox_Migration extends Pro_Test_Case {

	/**
	 * Invoke the private migration routine directly - the routine is only
	 * reached from run_upgrades() on a version bump, which this suite's
	 * fresh install never triggers.
	 */
	private function run_migration(): void {
		$method = new \ReflectionMethod( Installer::class, 'migrate_inquiries_to_threads' );
		$method->setAccessible( true );
		$method->invoke( null );
	}

	private function insert_inquiry( int $classified_id, string $name, string $email, string $message, string $status = 'unread' ): int {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'wbam_classified_inquiries',
			array(
				'classified_id' => $classified_id,
				'sender_name'   => $name,
				'sender_email'  => $email,
				'message'       => $message,
				'status'        => $status,
				'created_at'    => current_time( 'mysql', true ),
			)
		);

		return (int) $wpdb->insert_id;
	}

	private function make_seller_with_listing(): array {
		global $wpdb;

		$seller_user_id = (int) self::factory()->user->create();
		$advertiser      = Advertiser_Manager::get_instance()->create( $seller_user_id, array( 'status' => 'active' ) );
		$post_id         = self::factory()->post->create( array( 'post_type' => 'wbam-classified' ) );

		$wpdb->insert(
			$wpdb->prefix . 'wbam_classifieds',
			array(
				'post_id'       => $post_id,
				'advertiser_id' => $advertiser->id,
				'status'        => 'active',
			)
		);
		$classified_id = (int) $wpdb->insert_id;

		return array( $seller_user_id, $classified_id );
	}

	public function test_migration_copies_inquiry_into_a_guest_thread(): void {
		list( $seller_user_id, $classified_id ) = $this->make_seller_with_listing();
		$inquiry_id = $this->insert_inquiry( $classified_id, 'Gary Guest', 'gary@example.test', 'Is it still available?' );

		$this->run_migration();

		$message_manager = Message_Manager::get_instance();
		$threads          = $message_manager->get_threads( $seller_user_id );

		$this->assertCount( 1, $threads, 'The inquiry became exactly one thread for the seller.' );
		$this->assertSame( 'gary@example.test', $threads[0]->guest_email );
		$this->assertSame( 'Gary Guest', $threads[0]->guest_name );

		$messages = $message_manager->get_messages( (int) $threads[0]->id );
		$this->assertCount( 1, $messages );
		$this->assertSame( 'Is it still available?', $messages[0]->content );
		$this->assertSame( 'guest', $messages[0]->sender_type );
		$this->assertSame( $inquiry_id, (int) $messages[0]->source_inquiry_id );
	}

	public function test_migration_is_idempotent_on_rerun(): void {
		list( $seller_user_id, $classified_id ) = $this->make_seller_with_listing();
		$this->insert_inquiry( $classified_id, 'Gary Guest', 'gary@example.test', 'Is it still available?' );

		$this->run_migration();
		$this->run_migration();

		$message_manager = Message_Manager::get_instance();
		$threads          = $message_manager->get_threads( $seller_user_id );

		$this->assertCount( 1, $threads, 'A second run must not duplicate the thread.' );
		$this->assertCount( 1, $message_manager->get_messages( (int) $threads[0]->id ), 'A second run must not duplicate the message.' );
	}

	public function test_migration_leaves_the_inquiries_table_untouched(): void {
		global $wpdb;

		list( , $classified_id ) = $this->make_seller_with_listing();
		$inquiry_id = $this->insert_inquiry( $classified_id, 'Gary Guest', 'gary@example.test', 'Is it still available?', 'unread' );

		$this->run_migration();

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wbam_classified_inquiries WHERE id = %d", $inquiry_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$this->assertNotNull( $row, 'The migration never deletes an inquiry row.' );
		$this->assertSame( 'unread', $row->status, 'The migration never rewrites the inquiry row it copied.' );
	}

	public function test_a_second_inquiry_from_the_same_guest_reuses_the_thread(): void {
		list( $seller_user_id, $classified_id ) = $this->make_seller_with_listing();
		$this->insert_inquiry( $classified_id, 'Gary Guest', 'gary@example.test', 'Is it still available?' );
		$this->insert_inquiry( $classified_id, 'Gary Guest', 'gary@example.test', 'Would you take less?' );

		$this->run_migration();

		$message_manager = Message_Manager::get_instance();
		$threads          = $message_manager->get_threads( $seller_user_id );

		$this->assertCount( 1, $threads, 'Same guest, same listing: one thread, not two.' );
		$this->assertCount( 2, $message_manager->get_messages( (int) $threads[0]->id ) );
	}
}
