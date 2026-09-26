<?php
/**
 * One inbox (Basecamp 10342786624), fresh-install QA reject:
 * - REST contact went through a removed method (500) and skipped the inbox;
 * - the inquiry migration looped forever on inquiries whose seller is gone;
 * - admin could open any private member-to-member thread;
 * - guest and member reply emails used two styles, and the guest reply was
 *   dropped when the "message received" toggle was off;
 * - the inquiry email linked to My Listings, not the Inbox thread;
 * - list-threads reported the page size as the total;
 * - demo inquiries never reached the Inbox.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Installer;
use WBAM_Pro\Core\Pro_Admin;
use WBAM_Pro\Core\Pro_Abilities;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_API;
use WBAM_Pro\Modules\Messaging\Message_Manager;

class Test_One_Inbox_QA_Reject extends Pro_Test_Case {

	private array $sent = array();
	private $classifieds_settings;
	private $email_settings;
	private $dashboard_page;

	public function set_up(): void {
		parent::set_up();
		$this->classifieds_settings = get_option( 'wbam_pro_classifieds_settings' );
		$this->email_settings       = get_option( 'wbam_pro_email_settings' );
		$this->dashboard_page       = get_option( 'wbam_page_advertiser_dashboard' );
		update_option(
			'wbam_page_advertiser_dashboard',
			self::factory()->post->create(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_content' => '[wbam_advertiser_dashboard]',
				)
			)
		);
		add_filter( 'pre_wp_mail', array( $this, 'capture' ), 10, 2 );
		add_filter( 'wbam_pro_rate_limit_max', '__return_zero' );
	}

	public function tear_down(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'capture' ), 10 );
		remove_filter( 'wbam_pro_rate_limit_max', '__return_zero' );
		update_option( 'wbam_pro_classifieds_settings', $this->classifieds_settings );
		update_option( 'wbam_pro_email_settings', $this->email_settings );
		update_option( 'wbam_page_advertiser_dashboard', $this->dashboard_page );
		parent::tear_down();
	}

	public function capture( $short_circuit, $atts ) {
		$this->sent[] = $atts;
		return true;
	}

	private function make_seller_with_listing( string $display_name = 'Sam Seller' ): array {
		global $wpdb;

		$seller_user_id = (int) self::factory()->user->create( array( 'display_name' => $display_name ) );
		$advertiser     = Advertiser_Manager::get_instance()->create( $seller_user_id, array( 'status' => 'active' ) );
		$post_id        = self::factory()->post->create(
			array(
				'post_type'  => 'wbam-classified',
				'post_title' => 'Blue bike',
			)
		);
		$wpdb->insert(
			$wpdb->prefix . 'wbam_classifieds',
			array(
				'post_id'       => $post_id,
				'advertiser_id' => $advertiser->id,
				'status'        => 'active',
			)
		);

		return array( $seller_user_id, (int) $wpdb->insert_id, $advertiser );
	}

	private function contact( int $classified_id ) {
		$request = new \WP_REST_Request( 'POST' );
		$request->set_param( 'id', $classified_id );
		$request->set_param( 'name', 'Gary Guest' );
		$request->set_param( 'email', 'gary@example.test' );
		$request->set_param( 'message', 'Is it still available?' );
		return ( new Classified_API() )->contact_seller( $request );
	}

	public function test_rest_contact_puts_the_inquiry_in_the_sellers_inbox(): void {
		list( $seller_user_id, $classified_id ) = $this->make_seller_with_listing();

		$response = $this->contact( $classified_id );

		$this->assertNotWPError( $response );
		$threads = Message_Manager::get_instance()->get_threads( $seller_user_id );
		$this->assertCount( 1, $threads, 'The REST inquiry is a thread in the seller\'s Inbox.' );
		$this->assertSame( 'gary@example.test', $threads[0]->guest_email );
		$this->assertSame( 'Is it still available?', $threads[0]->last_message );
	}

	public function test_rest_contact_respects_enable_inquiries(): void {
		list( $seller_user_id, $classified_id ) = $this->make_seller_with_listing();
		update_option( 'wbam_pro_classifieds_settings', array_merge( (array) $this->classifieds_settings, array( 'enable_inquiries' => false ) ) );

		$response = $this->contact( $classified_id );

		$this->assertWPError( $response );
		$this->assertEmpty( Message_Manager::get_instance()->get_threads( $seller_user_id ) );
		$this->assertEmpty( $this->sent );
	}

	public function test_inquiry_email_links_to_the_inbox_thread(): void {
		list( $seller_user_id, $classified_id ) = $this->make_seller_with_listing();

		$this->contact( $classified_id );

		$thread_id = (int) Message_Manager::get_instance()->get_threads( $seller_user_id )[0]->id;
		$this->assertCount( 1, $this->sent, 'One email to the seller.' );
		$this->assertStringContainsString( 'tab=inbox', $this->sent[0]['message'] );
		$this->assertStringContainsString( 'thread=' . $thread_id, $this->sent[0]['message'] );
	}

	public function test_migration_always_progresses_past_inquiries_with_no_seller(): void {
		global $wpdb;

		list( , $classified_id, $advertiser ) = $this->make_seller_with_listing();
		// The seller's account is gone: no user to own a thread.
		$wpdb->update( $wpdb->prefix . 'wbam_advertisers', array( 'user_id' => 0 ), array( 'id' => $advertiser->id ) );
		wp_cache_flush();

		// A full batch (500) of such rows is what made the loop spin forever.
		$values = array();
		for ( $i = 0; $i < 501; $i++ ) {
			$values[] = $wpdb->prepare( "(%d, 'Orphan', 'orphan@example.test', 'Hi', 'unread', %s)", $classified_id, current_time( 'mysql', true ) );
		}
		$wpdb->query( "INSERT INTO {$wpdb->prefix}wbam_classified_inquiries (classified_id, sender_name, sender_email, message, status, created_at) VALUES " . implode( ',', $values ) ); // phpcs:ignore

		$batches = 0;
		$guard   = function ( $sql ) use ( &$batches ) {
			if ( false !== strpos( $sql, 'm.source_inquiry_id = i.id' ) && ++$batches > 20 ) {
				throw new \RuntimeException( 'Migration re-read the same batch forever.' );
			}
			return $sql;
		};
		add_filter( 'query', $guard );
		try {
			$method = new \ReflectionMethod( Installer::class, 'migrate_inquiries_to_threads' );
			$method->invoke( null );
		} finally {
			remove_filter( 'query', $guard );
		}

		$this->assertLessThanOrEqual( 3, $batches, 'Each batch moves forward, so 501 rows take 2 batches (plus one empty read).' );
	}

	private function render_admin_thread( int $thread_id ): string {
		$admin  = ( new \ReflectionClass( Pro_Admin::class ) )->newInstanceWithoutConstructor();
		$method = new \ReflectionMethod( Pro_Admin::class, 'render_inquiry_thread_view' );
		ob_start();
		try {
			$method->invoke( $admin, $thread_id );
		} catch ( \WPDieException $e ) {
			ob_end_clean();
			return 'WP_DIE: ' . $e->getMessage();
		}
		return (string) ob_get_clean();
	}

	public function test_admin_can_open_a_listing_inquiry_but_not_a_private_thread(): void {
		global $wpdb;

		list( $seller_user_id, $classified_id ) = $this->make_seller_with_listing();
		$manager = Message_Manager::get_instance();
		$guest   = $manager->get_or_create_guest_thread( $seller_user_id, $classified_id, 'Gary Guest', 'gary@example.test' );
		$manager->add_guest_message( $guest, 'Is it still available?' );

		$other = (int) self::factory()->user->create();
		$wpdb->insert(
			$wpdb->prefix . 'wbam_message_threads',
			array(
				'participant_a' => min( $seller_user_id, $other ),
				'participant_b' => max( $seller_user_id, $other ),
				'classified_id' => 0,
				'status'        => 'active',
			)
		);
		$private = (int) $wpdb->insert_id;
		$wpdb->insert(
			$wpdb->prefix . 'wbam_messages',
			array(
				'thread_id' => $private,
				'sender_id' => $other,
				'content'   => 'A private note',
			)
		);

		$this->assertStringContainsString( 'Is it still available?', $this->render_admin_thread( $guest ) );

		$private_html = $this->render_admin_thread( $private );
		$this->assertStringNotContainsString( 'A private note', $private_html );
		$this->assertStringContainsString( 'not available', $private_html );
	}

	public function test_a_guest_reply_is_emailed_even_with_the_message_email_off(): void {
		list( $seller_user_id, $classified_id ) = $this->make_seller_with_listing();
		update_option( 'wbam_pro_email_settings', array_merge( (array) $this->email_settings, array( 'message_received' => false ) ) );

		$manager = Message_Manager::get_instance();
		$thread  = $manager->get_or_create_guest_thread( $seller_user_id, $classified_id, 'Gary Guest', 'gary@example.test' );
		$manager->send_message( $thread, $seller_user_id, 'Yes, still available!' );

		$this->assertCount( 1, $this->sent, 'The guest has no inbox; email is how the reply reaches them.' );
	}

	public function test_member_message_email_uses_the_inquiry_email_style(): void {
		list( $seller_user_id, $classified_id ) = $this->make_seller_with_listing( 'TechStartup Inc.' );
		$buyer_id = (int) self::factory()->user->create();

		$manager = Message_Manager::get_instance();
		$thread  = $manager->get_or_create_thread( $buyer_id, $seller_user_id, $classified_id );
		$manager->send_message( $thread, $seller_user_id, 'Yes, still available!' );

		$this->assertCount( 1, $this->sent );
		$message = $this->sent[0]['message'];
		$this->assertStringContainsString( 'Yes, still available!', $message, 'Like the guest reply, the email carries the message.' );
		$this->assertStringContainsString( 'Blue bike', $message, 'Like the guest reply, the email names the listing.' );
		$this->assertStringContainsString( 'thread=' . $thread, $message );
		$this->assertStringNotContainsString( 'Inc..', $message );
	}

	public function test_list_threads_total_is_all_threads_not_the_page(): void {
		list( $seller_user_id, $classified_id ) = $this->make_seller_with_listing();
		$manager = Message_Manager::get_instance();
		$manager->get_or_create_guest_thread( $seller_user_id, $classified_id, 'A', 'a@example.test' );
		$manager->get_or_create_guest_thread( $seller_user_id, $classified_id, 'B', 'b@example.test' );
		wp_set_current_user( $seller_user_id );

		$abilities = ( new \ReflectionClass( Pro_Abilities::class ) )->newInstanceWithoutConstructor();
		$result    = $abilities->execute_list_threads( array( 'per_page' => 1 ) );

		$this->assertCount( 1, $result['items'] );
		$this->assertSame( 2, $result['total'] );
	}

	public function test_demo_inquiries_reach_the_inbox(): void {
		global $wpdb;

		if ( ! defined( 'WBAM_DEMO_DATA_INCLUDED' ) ) {
			define( 'WBAM_DEMO_DATA_INCLUDED', true );
		}
		require_once WBAM_PRO_PATH . 'demo-data-setup.php';

		$generator = new \WBAM_Demo_Data_Generator();
		ob_start();
		$generator->run();
		ob_end_clean();

		$inquiries = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wbam_classified_inquiries" ); // phpcs:ignore
		$missing   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wbam_classified_inquiries i LEFT JOIN {$wpdb->prefix}wbam_messages m ON m.source_inquiry_id = i.id WHERE m.id IS NULL" ); // phpcs:ignore

		$generator->delete_tracked_demo_data();

		$this->assertGreaterThan( 0, $inquiries, 'The demo seeds inquiries.' );
		$this->assertSame( 0, $missing, 'Every demo inquiry is an Inbox thread.' );
	}
}
