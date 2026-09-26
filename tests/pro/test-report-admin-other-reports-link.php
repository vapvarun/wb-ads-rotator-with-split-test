<?php
/**
 * Report_Admin::render_report_details()'s "Other Reports" list links back
 * into the classified-reports admin screen. $list_url used to be read from
 * an undefined variable in that method (it was only ever set in the
 * sibling render_page() scope), so every link resolved with no `page`/
 * `action` query args - PHP's undefined-variable coercion made
 * add_query_arg() fall back to the current request URI instead.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;
use WBAM_Pro\Modules\Classifieds\Report;
use WBAM_Pro\Modules\Classifieds\Report_Admin;

class Test_Report_Admin_Other_Reports_Link extends Pro_Test_Case {

	private int $classified_id;
	private int $first_report_id;

	public function set_up(): void {
		parent::set_up();

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );

		set_current_screen( 'edit-post' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		$listing    = Classified_Manager::get_instance()->create(
			array(
				'title'         => 'Reported Listing',
				'description'   => 'A listing with two reports against it.',
				'advertiser_id' => $advertiser->id,
				'status'        => 'active',
			)
		);
		$this->assertNotWPError( $listing );
		$this->classified_id = (int) $listing->id;

		$this->first_report_id  = $this->make_report( 'First Reporter', 'first@example.com' );
		$this->make_report( 'Second Reporter', 'second@example.com' );
	}

	/**
	 * @param string $name  Reporter name.
	 * @param string $email Reporter email.
	 * @return int Saved report ID.
	 */
	private function make_report( string $name, string $email ): int {
		$report                  = new Report();
		$report->classified_id   = $this->classified_id;
		$report->reporter_name   = $name;
		$report->reporter_email  = $email;
		$report->reason          = 'spam';
		$report->details         = '';
		$report->status          = 'pending';
		$this->assertTrue( (bool) $report->save(), 'Report row must save.' );

		return (int) $report->id;
	}

	public function test_other_reports_link_points_at_the_reports_list_page(): void {
		$_GET['action']    = 'view';
		$_GET['report_id'] = $this->first_report_id;

		ob_start();
		Report_Admin::get_instance()->render_page();
		$html = ob_get_clean();

		unset( $_GET['action'], $_GET['report_id'] );

		$this->assertStringContainsString( 'wbam-other-reports', $html );
		$this->assertMatchesRegularExpression(
			'/wbam-other-reports.*?href="[^"]*page=wbam-classified-reports[^"]*action=view[^"]*"/s',
			$html,
			'The "Other Reports" link must carry the reports-list base URL (page + action), not an empty/current-request fallback.'
		);
	}
}
