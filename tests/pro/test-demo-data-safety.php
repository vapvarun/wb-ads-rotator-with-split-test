<?php
/**
 * "Remove All Demo Data" removes demo data and nothing else (card 10217449688).
 *
 * QA's run of Import -> Import -> Remove on a site with real content lost
 * the installer's Advertiser Dashboard and Classifieds pages, every term in
 * both classified taxonomies (one used by 17 real listings), and three real
 * users plus their advertiser rows - all of which the importer had matched
 * and recorded. It also left behind links, ledger, revenue and attachments
 * it did create, and wrote 30 days of analytics onto real ads.
 *
 * The rule under test: the importer records only what it CREATED, and
 * removal deletes only recorded ids.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Next_Step_Banner;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

class Test_Demo_Data_Safety extends Pro_Test_Case {

	/**
	 * Real content seeded before the import.
	 *
	 * @var array
	 */
	private $real = array();

	public function set_up(): void {
		parent::set_up();

		if ( ! defined( 'WBAM_DEMO_DATA_INCLUDED' ) ) {
			define( 'WBAM_DEMO_DATA_INCLUDED', true );
		}
		require_once WBAM_PRO_PATH . 'demo-data-setup.php';

		foreach ( array( 'wbam-classified-cat', 'wbam-classified-loc' ) as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				register_taxonomy( $taxonomy, 'wbam-classified', array( 'hierarchical' => true ) );
			}
		}

		delete_option( \WBAM_Demo_Data_Generator::DEMO_IDS_OPTION );
		delete_option( \WBAM_Demo_Data_Generator::LEGACY_IDS_OPTION );

		// Advertiser rows left by other suites survive their rollback (see
		// Pro_Test_Case::truncate_credits_ledger()) and would pair with the
		// ids of users this test creates.
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}wbam_advertisers" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * The Credits SDK creates its tables lazily, and that DDL commits the
	 * test transaction - so rows written after it survive the rollback.
	 * Remove this test's rows explicitly so later runs start clean.
	 */
	public function tear_down(): void {
		( new \WBAM_Demo_Data_Generator() )->delete_tracked_demo_data();

		global $wpdb;
		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}
		foreach ( $this->real['users'] ?? array() as $user_id ) {
			wp_delete_user( $user_id );
		}
		foreach ( $this->real['posts'] ?? array() as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		foreach ( $this->real['terms'] ?? array() as $term_id => $taxonomy ) {
			wp_delete_term( $term_id, $taxonomy );
		}
		foreach ( array( 'wbam_links' => 'links', 'wbam_packages' => 'packages', 'wbam_advertisers' => 'advertisers', 'wbam_campaigns' => 'campaigns' ) as $table => $key ) {
			foreach ( $this->real[ $key ] ?? array() as $id ) {
				$wpdb->delete( $wpdb->prefix . $table, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			}
		}
		foreach ( array( 'wbam_page_advertiser_dashboard', 'wbam_page_classifieds', \WBAM_Demo_Data_Generator::DEMO_IDS_OPTION, \WBAM_Demo_Data_Generator::LEGACY_IDS_OPTION ) as $option ) {
			delete_option( $option );
		}

		parent::tear_down();
	}

	/**
	 * Seed what a live site has before anyone clicks Import Demo Data.
	 */
	private function seed_real_content(): void {
		global $wpdb;

		// The installer's option-backed pages, at the importer's slugs.
		$dashboard = (int) self::factory()->post->create( array( 'post_type' => 'page', 'post_name' => 'advertiser-dashboard', 'post_title' => 'Advertiser Dashboard', 'post_content' => '[wbam_advertiser_dashboard]' ) );
		$browse    = (int) self::factory()->post->create( array( 'post_type' => 'page', 'post_name' => 'classifieds', 'post_title' => 'Classifieds', 'post_content' => '[wbam_browse_classifieds]' ) );
		update_option( 'wbam_page_advertiser_dashboard', $dashboard );
		update_option( 'wbam_page_classifieds', $browse );

		// A real category at a slug the importer seeds, plus a real one of
		// its own, both filed on a real listing.
		$electronics = wp_insert_term( 'Electronics', 'wbam-classified-cat', array( 'slug' => 'electronics' ) );
		$qa_cat      = wp_insert_term( 'QA Electronics', 'wbam-classified-cat', array( 'slug' => 'qa-electronics' ) );
		$real_loc    = wp_insert_term( 'Miami', 'wbam-classified-loc', array( 'slug' => 'miami' ) );
		$listing     = (int) self::factory()->post->create( array( 'post_type' => 'wbam-classified', 'post_title' => 'Real listing' ) );
		wp_set_object_terms( $listing, array( (int) $electronics['term_id'], (int) $qa_cat['term_id'] ), 'wbam-classified-cat' );
		wp_set_object_terms( $listing, array( (int) $real_loc['term_id'] ), 'wbam-classified-loc' );

		// A real account that happens to use a demo advertiser's email.
		$user       = (int) self::factory()->user->create( array( 'user_login' => 'techstartup_real', 'user_email' => 'ads@techstartup.demo', 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );

		// Real ads, one sharing a demo ad's title.
		$ad_a    = (int) self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_title' => 'Real ad A' ) );
		$ad_same = (int) self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_title' => 'Summer Tech Sale - 50% Off' ) );

		// A real link on a demo slug and a real package with a demo name.
		$wpdb->insert( $wpdb->prefix . 'wbam_links', array( 'name' => 'Real Amazon', 'destination_url' => 'https://example.com/real', 'slug' => 'amazon-best', 'status' => 'active' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$link = (int) $wpdb->insert_id;
		$wpdb->insert( $wpdb->prefix . 'wbam_packages', array( 'name' => 'Starter Package', 'status' => 'active' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$package = (int) $wpdb->insert_id;

		$this->real = array(
			'pages'       => array( $dashboard, $browse ),
			'posts'       => array( $dashboard, $browse, $listing, $ad_a, $ad_same ),
			'ads'         => array( $ad_a, $ad_same ),
			'listing'     => $listing,
			'terms'       => array(
				(int) $electronics['term_id'] => 'wbam-classified-cat',
				(int) $qa_cat['term_id']      => 'wbam-classified-cat',
				(int) $real_loc['term_id']    => 'wbam-classified-loc',
			),
			'users'       => array( $user ),
			'advertisers' => array( (int) $advertiser->id ),
			'links'       => array( $link ),
			'packages'    => array( $package ),
			'campaigns'   => array(),
		);
	}

	private function import(): \WBAM_Demo_Data_Generator {
		$generator = new \WBAM_Demo_Data_Generator();
		ob_start();
		$generator->run();
		ob_end_clean();

		return $generator;
	}

	private function registry(): array {
		return (array) get_option( \WBAM_Demo_Data_Generator::DEMO_IDS_OPTION, array() );
	}

	private function row_exists( string $table, int $id, string $column = 'id' ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$column} = %d", $id ) );
	}

	private function assert_real_content_survives(): void {
		global $wpdb;

		foreach ( $this->real['posts'] as $post_id ) {
			$this->assertNotNull( get_post( $post_id ), "Real post {$post_id} must survive." );
		}
		foreach ( $this->real['terms'] as $term_id => $taxonomy ) {
			$this->assertInstanceOf( \WP_Term::class, get_term( $term_id, $taxonomy ), "Real term {$term_id} must survive." );
		}
		$this->assertCount( 2, wp_get_object_terms( $this->real['listing'], 'wbam-classified-cat', array( 'fields' => 'ids' ) ), 'The real listing keeps its categories.' );
		foreach ( $this->real['users'] as $user_id ) {
			$this->assertNotFalse( get_userdata( $user_id ), "Real user {$user_id} must survive." );
		}
		foreach ( array( 'advertisers', 'links', 'packages' ) as $key ) {
			foreach ( $this->real[ $key ] as $id ) {
				$this->assertTrue( $this->row_exists( $wpdb->prefix . 'wbam_' . $key, $id ), "Real {$key} row {$id} must survive." );
			}
		}
		$this->assertSame( $this->real['pages'][0], (int) get_option( 'wbam_page_advertiser_dashboard' ) );
	}

	public function test_import_records_only_what_it_created(): void {
		global $wpdb;
		$this->seed_real_content();

		$this->import();
		$this->import();
		$ids = $this->registry();

		$this->assertSame( \WBAM_Demo_Data_Generator::REGISTRY_VERSION, $ids['version'] );
		$this->assertNotEmpty( $ids['ads'], 'The import created demo ads.' );
		$this->assertNotEmpty( $ids['terms'], 'The import created demo terms.' );
		$this->assertNotEmpty( $ids['attachments'], 'The import sideloaded demo images.' );
		$this->assertNotEmpty( $ids['links'], 'The import created demo links.' );

		$this->assertEmpty( array_intersect( $this->real['pages'], $ids['pages'] ), 'Option-backed pages are never recorded.' );
		$this->assertEmpty( array_intersect( $this->real['ads'], $ids['ads'] ), 'A real ad sharing a demo title is never recorded.' );
		$this->assertEmpty( array_intersect( array_keys( $this->real['terms'] ), $ids['terms'] ), 'Terms found by slug are never recorded.' );
		$this->assertEmpty( array_intersect( $this->real['users'], $ids['users'] ), 'A user matched by email is never recorded.' );
		$this->assertEmpty( array_intersect( $this->real['advertisers'], $ids['advertisers'] ), 'An advertiser row the site had is never recorded.' );
		$this->assertEmpty( array_intersect( $this->real['links'], $ids['links'] ), 'A link on a demo slug is never recorded.' );
		$this->assertEmpty( array_intersect( $this->real['packages'], $ids['packages'] ), 'A package matched by name is never recorded.' );

		foreach ( $this->real['ads'] as $ad_id ) {
			$this->assertFalse( $this->row_exists( $wpdb->prefix . 'wbam_analytics_daily', $ad_id, 'ad_id' ), "No invented analytics on real ad {$ad_id}." );
		}

		foreach ( $ids['users'] as $user_id ) {
			$this->assertSame( '1', (string) get_user_meta( $user_id, \WBAM_Demo_Data_Generator::USER_MARKER, true ), 'Created users carry the creation marker.' );
		}

		if ( \WBAM_Pro\Core\Credits_Bridge::is_enabled() ) {
			$this->assertNotEmpty( $ids['ledger'], 'Ledger rows the import wrote are recorded.' );
		}
	}

	public function test_remove_deletes_every_created_row_and_nothing_real(): void {
		global $wpdb;
		$this->seed_real_content();

		$this->import();
		$ids = $this->registry();

		// Capture what child rows and files exist before removal.
		$files = array();
		foreach ( $ids['attachments'] as $attachment_id ) {
			$files[] = get_attached_file( $attachment_id );
		}
		$files = array_filter( $files );
		$this->assertNotEmpty( $files );
		foreach ( $files as $file ) {
			$this->assertFileExists( $file );
		}
		$classified_rows = array();
		if ( ! empty( $ids['classifieds'] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$classified_rows = array_map( 'intval', $wpdb->get_col( "SELECT id FROM {$wpdb->prefix}wbam_classifieds WHERE post_id IN (" . implode( ',', $ids['classifieds'] ) . ')' ) );
		}

		$generator = new \WBAM_Demo_Data_Generator();
		$generator->delete_tracked_demo_data();

		$this->assert_real_content_survives();

		foreach ( array( 'pages', 'ads', 'classifieds', 'attachments' ) as $bucket ) {
			foreach ( $ids[ $bucket ] as $post_id ) {
				$this->assertNull( get_post( $post_id ), "Demo {$bucket} post {$post_id} must be gone." );
			}
		}
		foreach ( $files as $file ) {
			$this->assertFileDoesNotExist( $file, 'Sideloaded demo files are deleted with their attachment.' );
		}
		foreach ( $ids['terms'] as $term_id ) {
			$this->assertNull( get_term( $term_id ), "Demo term {$term_id} must be gone." );
		}
		foreach ( $ids['users'] as $user_id ) {
			$this->assertFalse( get_userdata( $user_id ), "Demo user {$user_id} must be gone." );
		}

		$tables = array(
			'advertisers'  => 'wbam_advertisers',
			'packages'     => 'wbam_packages',
			'campaigns'    => 'wbam_campaigns',
			'links'        => 'wbam_links',
			'ab_tests'     => 'wbam_ab_tests',
			'partnerships' => 'wbam_link_partnerships',
		);
		foreach ( $tables as $bucket => $table ) {
			foreach ( $ids[ $bucket ] as $id ) {
				$this->assertFalse( $this->row_exists( $wpdb->prefix . $table, $id ), "Demo {$bucket} row {$id} must be gone." );
			}
		}
		foreach ( $ids['ab_tests'] as $test_id ) {
			$this->assertFalse( $this->row_exists( $wpdb->prefix . 'wbam_ab_test_stats', $test_id, 'test_id' ) );
		}
		foreach ( $ids['links'] as $link_id ) {
			$this->assertFalse( $this->row_exists( $wpdb->prefix . 'wbam_link_clicks_daily', $link_id, 'link_id' ) );
			$this->assertFalse( $this->row_exists( $wpdb->prefix . 'wbam_link_clicks_detailed', $link_id, 'link_id' ) );
		}
		foreach ( $ids['ads'] as $ad_id ) {
			$this->assertFalse( $this->row_exists( $wpdb->prefix . 'wbam_analytics_daily', $ad_id, 'ad_id' ), "Analytics of demo ad {$ad_id} must be gone." );
		}
		foreach ( $classified_rows as $row_id ) {
			$this->assertFalse( $this->row_exists( $wpdb->prefix . 'wbam_classifieds', $row_id ) );
			$this->assertFalse( $this->row_exists( $wpdb->prefix . 'wbam_classified_inquiries', $row_id, 'classified_id' ) );
		}
		if ( ! empty( $ids['ledger'] ) ) {
			$ledger = \Wbcom\Credits\Ledger::table_name( \WBAM_Pro\Core\Credits_Bridge::PREFIX );
			foreach ( $ids['ledger'] as $ledger_id ) {
				$this->assertFalse( $this->row_exists( $ledger, $ledger_id ), "Demo ledger row {$ledger_id} must be gone." );
				$this->assertFalse( $this->row_exists( $wpdb->prefix . 'wbam_revenue', $ledger_id, 'ledger_id' ), "Revenue mirroring ledger row {$ledger_id} must be gone." );
			}
		}

		$this->assertFalse( get_option( \WBAM_Demo_Data_Generator::DEMO_IDS_OPTION ), 'The registry is cleared.' );
		$this->assertSame( 0, $generator->skipped_count, 'A version-2 registry holds only created rows, so nothing is kept.' );
		$this->assertGreaterThan( 0, $generator->removed_count );
	}

	public function test_legacy_registry_never_deletes_essential_pages_or_adopted_accounts(): void {
		global $wpdb;
		$this->seed_real_content();

		// What an older importer or the 3.6.0 migration left: the essential
		// pages flagged as demo, the email-matched user and their advertiser
		// row, a name-matched package, a slug-matched link - plus one real
		// demo ad it did create.
		foreach ( $this->real['pages'] as $page_id ) {
			update_post_meta( $page_id, '_wbam_is_demo', 1 );
		}
		$legacy_ad = (int) self::factory()->post->create( array( 'post_type' => 'wbam-ad', 'post_title' => 'Old demo ad' ) );
		update_post_meta( $legacy_ad, '_wbam_is_demo', 1 );
		update_option(
			\WBAM_Demo_Data_Generator::DEMO_IDS_OPTION,
			array(
				'pages'       => $this->real['pages'],
				'ads'         => array( $legacy_ad ),
				'users'       => $this->real['users'],
				'advertisers' => $this->real['advertisers'],
				'packages'    => $this->real['packages'],
				'links'       => $this->real['links'],
			)
		);

		// A new import on top parks the legacy registry instead of mixing it.
		$this->import();
		$this->assertSame( \WBAM_Demo_Data_Generator::REGISTRY_VERSION, $this->registry()['version'] );
		$legacy = (array) get_option( \WBAM_Demo_Data_Generator::LEGACY_IDS_OPTION );
		$this->assertSame( $this->real['users'], $legacy['users'] );

		$generator = new \WBAM_Demo_Data_Generator();
		$generator->delete_tracked_demo_data();

		$this->assert_real_content_survives();
		$this->assertNull( get_post( $legacy_ad ), 'A flagged legacy demo ad is still removed.' );
		$this->assertGreaterThanOrEqual( 6, $generator->skipped_count, 'Pages, user, advertiser, package and link are kept and reported.' );
		$this->assertFalse( get_option( \WBAM_Demo_Data_Generator::LEGACY_IDS_OPTION ), 'The parked registry is cleared too.' );
	}

	/**
	 * How the installer's pages got into the registry in the first place:
	 * install() creates them, then runs the 3.6.0 migration, which recorded
	 * any page at a demo slug.
	 */
	public function test_install_migration_does_not_record_the_plugins_own_pages(): void {
		$this->seed_real_content();
		delete_option( 'wbam_pro_demo_migration_360_done' );

		$migrate = new \ReflectionMethod( \WBAM_Pro\Core\Installer::class, 'upgrade_to_3_6_0' );
		$migrate->invoke( null );

		$ids = (array) get_option( \WBAM_Demo_Data_Generator::DEMO_IDS_OPTION );
		$this->assertEmpty( array_intersect( $this->real['pages'], (array) ( $ids['pages'] ?? array() ) ) );
		$this->assertSame( '', (string) get_post_meta( $this->real['pages'][0], '_wbam_is_demo', true ) );
	}

	public function test_banner_demo_step_outranks_pending_applications(): void {
		$applicant = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		Advertiser_Manager::get_instance()->get_or_create( $applicant );
		$this->assertGreaterThan( 0, Next_Step_Banner::collect_state()['pending_advertisers'] );

		update_option( \WBAM_Demo_Data_Generator::DEMO_IDS_OPTION, array( 'ads' => array( 10 ) ) );

		$step = Next_Step_Banner::resolve_next_step();
		$this->assertStringStartsWith( 'demo-data-', $step['slug'], 'The go-live blocker is not hidden by pending applications.' );

		delete_option( \WBAM_Demo_Data_Generator::DEMO_IDS_OPTION );
		$this->assertStringStartsWith( 'review-applications-', Next_Step_Banner::resolve_next_step()['slug'] );
	}

	public function test_banner_slug_changes_on_every_import_even_with_the_same_count(): void {
		$generator = new \WBAM_Demo_Data_Generator();
		$begin     = new \ReflectionMethod( \WBAM_Demo_Data_Generator::class, 'begin_import' );
		$register  = new \ReflectionMethod( \WBAM_Demo_Data_Generator::class, 'register_demo_id' );

		$begin->invoke( $generator );
		$register->invoke( $generator, 'ads', 101 );
		$first = Next_Step_Banner::resolve_next_step()['slug'];

		$begin->invoke( $generator );
		$second = Next_Step_Banner::resolve_next_step()['slug'];

		$this->assertSame( 1, Next_Step_Banner::collect_state()['demo_data_count'], 'Same demo count on both imports.' );
		$this->assertStringStartsWith( 'demo-data-', $first );
		$this->assertNotSame( $first, $second, 'A dismissed demo banner returns after a re-import.' );
	}
}
