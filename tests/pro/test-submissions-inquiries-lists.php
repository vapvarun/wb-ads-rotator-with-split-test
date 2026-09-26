<?php
/**
 * Ad Submissions search and per-page cost; Classified Inquiries seller column
 * and per-page cost.
 *
 * Submissions had no search (only a dropdown of the first 100 advertisers)
 * and loaded each row's advertiser and user one at a time. Inquiries showed
 * no seller and loaded each row's listing and post one at a time.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Admin\Ad_Submissions_List_Table;
use WBAM_Pro\Core\Pro_Admin;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

class Test_Submissions_Inquiries_Lists extends Pro_Test_Case {

	public function set_up(): void {
		parent::set_up();
		require_once WBAM_PRO_PATH . 'includes/Admin/class-ad-submissions-list-table.php';
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'toplevel_page_wbam-submissions' );
	}

	public function tear_down(): void {
		unset( $_GET['s'] );
		set_current_screen( 'front' );
		parent::tear_down();
	}

	private function advertiser( string $company ): int {
		$user       = (int) self::factory()->user->create();
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		Advertiser_Manager::get_instance()->update( $advertiser->id, array( 'company_name' => $company ) );
		return (int) $advertiser->id;
	}

	private function submission( string $company, string $title = 'Banner' ): void {
		global $wpdb;
		$ad = self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_title' => $title ) );
		$wpdb->insert(
			$wpdb->prefix . 'wbam_ad_submissions',
			array(
				'advertiser_id' => $this->advertiser( $company ),
				'ad_id'         => $ad,
				'status'        => 'pending',
				'submitted_at'  => current_time( 'mysql' ),
			)
		);
	}

	private function render_submissions(): array {
		global $wpdb;
		$table  = new Ad_Submissions_List_Table();
		$before = $wpdb->num_queries;
		$table->prepare_items();
		foreach ( $table->items as $item ) {
			$table->column_advertiser( $item );
			$table->column_ad( $item );
		}
		return array( $table, $wpdb->num_queries - $before );
	}

	public function test_submission_search_matches_advertiser_and_ad_title(): void {
		$this->submission( 'Acme Widgets', 'Spring banner' );
		$this->submission( 'Globex', 'Winter banner' );

		$_GET['s'] = 'globex';
		list( $table ) = $this->render_submissions();
		$this->assertCount( 1, $table->items );

		$_GET['s'] = 'spring';
		list( $table ) = $this->render_submissions();
		$this->assertCount( 1, $table->items );
		$this->assertSame( 1, $table->get_pagination_arg( 'total_items' ) );
	}

	public function test_submission_page_cost_does_not_grow_with_rows(): void {
		foreach ( range( 1, 2 ) as $i ) {
			$this->submission( "Small {$i}" );
		}
		list( , $small ) = $this->render_submissions();
		foreach ( range( 1, 10 ) as $i ) {
			$this->submission( "Big {$i}" );
		}
		list( $table, $big ) = $this->render_submissions();

		$this->assertCount( 12, $table->items );
		$this->assertSame( $small, $big );
	}

	/**
	 * One inbox (card 10343726590): the admin Inquiries screen reads
	 * `wbam_message_threads`/`wbam_messages`, not the legacy
	 * `wbam_classified_inquiries` table, so this seeds a listing-inquiry
	 * thread the same way Message_Manager::get_or_create_guest_thread()
	 * and add_guest_message() do.
	 */
	private function inquiry( string $company ): void {
		global $wpdb;
		$seller_user = (int) self::factory()->user->create();
		$advertiser  = Advertiser_Manager::get_instance()->get_or_create( $seller_user );
		Advertiser_Manager::get_instance()->update( $advertiser->id, array( 'company_name' => $company ) );

		$post = self::factory()->post->create( array( 'post_type' => 'wbam-classified', 'post_title' => 'Bike ' . $company ) );
		$wpdb->insert(
			$wpdb->prefix . 'wbam_classifieds',
			array(
				'post_id'       => $post,
				'advertiser_id' => $advertiser->id,
			)
		);
		$classified_id = (int) $wpdb->insert_id;

		$wpdb->insert(
			$wpdb->prefix . 'wbam_message_threads',
			array(
				'classified_id'   => $classified_id,
				'guest_name'      => 'Buyer',
				'guest_email'     => 'buyer@example.org',
				'participant_a'   => 0,
				'participant_b'   => $seller_user,
				'last_message_at' => current_time( 'mysql' ),
			)
		);
		$thread_id = (int) $wpdb->insert_id;

		$wpdb->insert(
			$wpdb->prefix . 'wbam_messages',
			array(
				'thread_id'   => $thread_id,
				'sender_id'   => 0,
				'sender_type' => 'guest',
				'content'     => 'Still available?',
				'is_read'     => 0,
				'created_at'  => current_time( 'mysql' ),
			)
		);
	}

	private function render_inquiries(): array {
		global $wpdb;
		$before = $wpdb->num_queries;
		ob_start();
		( new Pro_Admin() )->render_inquiries_page();
		return array( (string) ob_get_clean(), $wpdb->num_queries - $before );
	}

	public function test_inquiries_show_the_seller_at_a_flat_cost(): void {
		$this->inquiry( 'Small 1' );
		$this->inquiry( 'Small 2' );
		list( , $small ) = $this->render_inquiries();

		foreach ( range( 1, 10 ) as $i ) {
			$this->inquiry( "Big {$i}" );
		}
		list( $html, $big ) = $this->render_inquiries();

		$this->assertStringContainsString( 'column-seller', $html );
		$this->assertStringContainsString( 'Big 10', $html );
		$this->assertSame( $small, $big, 'Listings and sellers must load per page, not per row.' );
	}
}
