<?php
/**
 * Classified Reports admin search: Report::get_all()'s 'search' arg matches
 * the reported listing's title, not just the reporter's name/email/details -
 * the toolbar search box added for card 10339876480 item 8.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Settings_Helper;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use WBAM_Pro\Modules\Classifieds\Classified_Manager;
use WBAM_Pro\Modules\Classifieds\Report;

class Test_Classified_Report_Search extends Pro_Test_Case {

	private object $matching_listing;
	private object $other_listing;

	public function set_up(): void {
		parent::set_up();

		$enabled                = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['classifieds'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );

		$user       = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );
		$manager    = Classified_Manager::get_instance();

		$this->matching_listing = $manager->create(
			array(
				'title'         => 'Vintage Leather Sofa',
				'description'   => 'Reported listing the search should find by title.',
				'advertiser_id' => $advertiser->id,
				'status'        => 'active',
			)
		);
		$this->other_listing = $manager->create(
			array(
				'title'         => 'Unrelated Bicycle',
				'description'   => 'A different listing that must not match the search.',
				'advertiser_id' => $advertiser->id,
				'status'        => 'active',
			)
		);
		$this->assertNotWPError( $this->matching_listing );
		$this->assertNotWPError( $this->other_listing );

		$this->make_report( $this->matching_listing->id, 'Someone Else', 'reporter-a@example.com' );
		$this->make_report( $this->other_listing->id, 'A Different Reporter', 'reporter-b@example.com' );
	}

	/**
	 * @param int    $classified_id Classified row ID (Report::classified_id).
	 * @param string $name          Reporter name.
	 * @param string $email         Reporter email.
	 * @return void
	 */
	private function make_report( int $classified_id, string $name, string $email ): void {
		$report                = new Report();
		$report->classified_id = $classified_id;
		$report->reporter_name = $name;
		$report->reporter_email = $email;
		$report->reason         = 'spam';
		$report->details        = '';
		$report->status         = 'pending';
		$saved                  = $report->save();
		$this->assertTrue( (bool) $saved, 'Report row must save.' );
	}

	public function test_search_matches_the_reported_listings_title(): void {
		$result = Report::get_all( array( 'search' => 'Vintage Leather' ) );

		$this->assertSame( 1, $result['total'] );
		$this->assertCount( 1, $result['items'] );
		$this->assertSame( (int) $this->matching_listing->id, (int) $result['items'][0]->classified_id );
	}

	public function test_search_still_matches_reporter_name_and_email(): void {
		$by_name  = Report::get_all( array( 'search' => 'Different Reporter' ) );
		$by_email = Report::get_all( array( 'search' => 'reporter-a@example.com' ) );

		$this->assertSame( 1, $by_name['total'] );
		$this->assertSame( (int) $this->other_listing->id, (int) $by_name['items'][0]->classified_id );

		$this->assertSame( 1, $by_email['total'] );
		$this->assertSame( (int) $this->matching_listing->id, (int) $by_email['items'][0]->classified_id );
	}

	public function test_search_with_no_match_returns_zero_via_count_star(): void {
		$result = Report::get_all( array( 'search' => 'nothing-matches-this-string' ) );

		$this->assertSame( 0, $result['total'] );
		$this->assertCount( 0, $result['items'] );
	}
}
