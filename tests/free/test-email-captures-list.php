<?php
/**
 * Email Captures list: search, ad filter, sort, bulk delete, formatted date,
 * and an export that holds the filtered rows.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WBAM\Admin\Email_Captures;
use WBAM\Admin\Email_Captures_List_Table;
use WBAM\Tests\Helpers\Factory;
use WP_UnitTestCase;

class Test_Email_Captures_List extends WP_UnitTestCase {

	private int $ad_a;
	private int $ad_b;

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'wbam-ad_page_wbam-email-captures' );
		$this->ad_a = Factory::make_ad( array( 'post_title' => 'Newsletter A' ) );
		$this->ad_b = Factory::make_ad( array( 'post_title' => 'Newsletter B' ) );
		$this->capture( $this->ad_a, 'zoe@example.org', 'Zoe' );
		$this->capture( $this->ad_a, 'adam@example.org', 'Adam' );
		$this->capture( $this->ad_b, 'bea@example.org', 'Bea' );
	}

	public function tear_down(): void {
		unset( $_GET['s'], $_GET['capture_ad'], $_GET['orderby'], $_GET['order'], $_GET['page'], $_GET['section'], $_GET['action'], $_GET['capture_ids'], $_GET['_wpnonce'], $_REQUEST['_wpnonce'] );
		set_current_screen( 'front' );
		parent::tear_down();
	}

	private function capture( int $ad, string $email, string $name ): void {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'wbam_email_submissions',
			array(
				'ad_id'      => $ad,
				'email'      => $email,
				'name'       => $name,
				'created_at' => '2026-09-01 10:00:00',
			)
		);
	}

	private function table(): Email_Captures_List_Table {
		$table = new Email_Captures_List_Table( new Email_Captures() );
		$table->prepare_items();
		return $table;
	}

	public function test_search_filter_and_sort(): void {
		$_GET['s'] = 'bea';
		$this->assertSame( array( 'bea@example.org' ), wp_list_pluck( $this->table()->items, 'email' ) );

		unset( $_GET['s'] );
		$_GET['capture_ad'] = (string) $this->ad_a;
		$_GET['orderby']    = 'email';
		$_GET['order']      = 'asc';
		$this->assertSame( array( 'adam@example.org', 'zoe@example.org' ), wp_list_pluck( $this->table()->items, 'email' ) );
	}

	public function test_date_is_formatted(): void {
		$table = $this->table();
		$html  = $table->column_default( $table->items[0], 'created_at' );

		$this->assertStringNotContainsString( '2026-09-01 10:00:00', $html );
		$this->assertStringContainsString( '2026', $html );
	}

	public function test_export_holds_the_filtered_rows(): void {
		$handle = fopen( 'php://memory', 'w+' );
		( new Email_Captures() )->stream_csv( $handle, array( 'ad_id' => $this->ad_b ) );
		rewind( $handle );
		$csv = stream_get_contents( $handle );

		$this->assertStringContainsString( 'bea@example.org', $csv );
		$this->assertStringNotContainsString( 'zoe@example.org', $csv );
	}

	public function test_bulk_delete_removes_the_selected_rows(): void {
		$ids = wp_list_pluck( $this->table()->items, 'id' );

		$_GET['page']         = 'wbam-email-captures';
		$_GET['action']       = 'delete_captures';
		$_GET['capture_ids']  = array( (string) $ids[0], (string) $ids[1] );
		$_GET['_wpnonce']     = wp_create_nonce( 'bulk-captures' );
		$_REQUEST['_wpnonce'] = $_GET['_wpnonce'];

		add_filter(
			'wp_redirect',
			static function ( $location ) {
				throw new \RuntimeException( (string) $location );
			}
		);
		try {
			( new Email_Captures() )->handle_bulk_delete();
			$this->fail( 'Expected a redirect.' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'page=wbam-email-captures', $e->getMessage(), 'Redirects to Email Captures\' own screen, not the old Settings section.' );
		}

		$this->assertSame( 1, ( new Email_Captures() )->count() );
	}

	/** Old `?section=email-captures` URL redirects to the new standalone screen. */
	public function test_legacy_settings_section_url_redirects_to_the_new_screen(): void {
		$settings = \WBAM\Admin\Settings::get_instance();
		$_GET['section'] = 'email-captures';

		add_filter(
			'wp_redirect',
			static function ( $location ) {
				throw new \RuntimeException( (string) $location );
			}
		);
		try {
			$settings->render_page();
			$this->fail( 'Expected a redirect.' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( 'page=wbam-email-captures', $e->getMessage() );
		}
	}

	/** Its own submenu (card 10343706274), registered directly, not through Settings. */
	public function test_registers_its_own_submenu_page(): void {
		global $submenu;
		$before = $submenu;

		( new Email_Captures() )->add_menu();

		$slugs = wp_list_pluck( $submenu['edit.php?post_type=wbam-ad'] ?? array(), 2 );
		$this->assertContains( 'wbam-email-captures', $slugs );

		$submenu = $before; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test cleanup, restoring the global we just mutated.
	}

	/** No longer embedded on the Settings screen's Tools section. */
	public function test_no_longer_embedded_on_the_tools_section(): void {
		ob_start();
		\WBAM\Admin\Settings::get_instance()->render_tools_section();
		$html = ob_get_clean();

		$this->assertStringNotContainsString( 'id="email-captures"', $html );
		$this->assertStringNotContainsString( 'wbam-email-captures', $html );
	}
}
