<?php
/**
 * One vocabulary (card 10343726476): every stored status maps to a label
 * from the owner's set, never a re-declared literal.
 *
 * Regression guard for the reported drift — Pending / Pending Review /
 * Pending review, and Approved / Active / Running — where ad submissions,
 * campaigns, A/B tests, classifieds, advertisers, reviews and reports each
 * declared their own label array with different words and casing.
 * Status_Labels::get_label() is the one map every entity's
 * get_status_label() now routes through.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Status_Labels;

class Test_Status_Labels extends Pro_Test_Case {

	public function test_ad_submission_statuses_map_to_the_owner_set(): void {
		$this->assertSame( 'Pending review', Status_Labels::get_label( 'ad_submission', 'pending' ) );
		$this->assertSame( 'Live', Status_Labels::get_label( 'ad_submission', 'approved' ) );
		$this->assertSame( 'Rejected', Status_Labels::get_label( 'ad_submission', 'rejected' ) );
		$this->assertSame( 'Changes requested', Status_Labels::get_label( 'ad_submission', 'changes_requested' ) );
		$this->assertSame( 'Cancelled', Status_Labels::get_label( 'ad_submission', 'cancelled' ) );
	}

	public function test_ad_statuses_map_to_the_owner_set(): void {
		$this->assertSame( 'Live', Status_Labels::get_label( 'ad', 'active' ) );
		$this->assertSame( 'Paused', Status_Labels::get_label( 'ad', 'paused' ) );
		$this->assertSame( 'Pending review', Status_Labels::get_label( 'ad', 'pending' ) );
		$this->assertSame( 'Draft', Status_Labels::get_label( 'ad', 'draft' ) );
		$this->assertSame( 'Rejected', Status_Labels::get_label( 'ad', 'rejected' ) );
		$this->assertSame( 'Changes requested', Status_Labels::get_label( 'ad', 'changes_requested' ) );
	}

	public function test_campaign_statuses_map_to_the_owner_set(): void {
		$this->assertSame( 'Draft', Status_Labels::get_label( 'campaign', 'draft' ) );
		$this->assertSame( 'Pending review', Status_Labels::get_label( 'campaign', 'pending' ) );
		$this->assertSame( 'Live', Status_Labels::get_label( 'campaign', 'active' ) );
		$this->assertSame( 'Paused', Status_Labels::get_label( 'campaign', 'paused' ) );
		$this->assertSame( 'Cancelled', Status_Labels::get_label( 'campaign', 'cancelled' ) );
	}

	public function test_campaign_completed_and_expired_both_read_ended(): void {
		// One word for "stopped on its own" (budget or date reached) instead
		// of two ("Completed" and "Expired") that meant the same thing to
		// an advertiser reading their campaign list.
		$this->assertSame( 'Ended', Status_Labels::get_label( 'campaign', 'completed' ) );
		$this->assertSame( 'Ended', Status_Labels::get_label( 'campaign', 'expired' ) );
	}

	public function test_ab_test_statuses_map_to_the_owner_set(): void {
		$this->assertSame( 'Live', Status_Labels::get_label( 'ab_test', 'running' ) );
		$this->assertSame( 'Paused', Status_Labels::get_label( 'ab_test', 'paused' ) );
		$this->assertSame( 'Draft', Status_Labels::get_label( 'ab_test', 'draft' ) );
		$this->assertSame( 'Ended', Status_Labels::get_label( 'ab_test', 'completed' ) );
	}

	public function test_classified_statuses_use_the_ad_words(): void {
		// Owner decision (card 10343726476, wave 6): listings say Live and
		// Ended like ads and campaigns; Sold stays, buyers need the reason.
		$this->assertSame( 'Pending review', Status_Labels::get_label( 'classified', 'pending' ) );
		$this->assertSame( 'Live', Status_Labels::get_label( 'classified', 'active' ) );
		$this->assertSame( 'Sold', Status_Labels::get_label( 'classified', 'sold' ) );
		$this->assertSame( 'Ended', Status_Labels::get_label( 'classified', 'expired' ) );
		$this->assertSame( 'Rejected', Status_Labels::get_label( 'classified', 'rejected' ) );
		$this->assertSame( 'Draft', Status_Labels::get_label( 'classified', 'draft' ) );
	}

	public function test_advertiser_account_statuses_keep_their_own_words(): void {
		// Accounts are not "live" either — only the "pending" casing drift
		// is fixed; Active/Suspended/Banned are unchanged.
		$this->assertSame( 'Pending review', Status_Labels::get_label( 'advertiser', 'pending' ) );
		$this->assertSame( 'Active', Status_Labels::get_label( 'advertiser', 'active' ) );
		$this->assertSame( 'Member (no ads)', Status_Labels::get_label( 'advertiser', 'member' ) );
		$this->assertSame( 'Suspended', Status_Labels::get_label( 'advertiser', 'suspended' ) );
		$this->assertSame( 'Banned', Status_Labels::get_label( 'advertiser', 'banned' ) );
	}

	public function test_review_statuses_keep_their_own_words(): void {
		$this->assertSame( 'Pending review', Status_Labels::get_label( 'review', 'pending' ) );
		$this->assertSame( 'Approved', Status_Labels::get_label( 'review', 'approved' ) );
		$this->assertSame( 'Rejected', Status_Labels::get_label( 'review', 'rejected' ) );
	}

	public function test_report_statuses_keep_their_own_words(): void {
		$this->assertSame( 'Pending review', Status_Labels::get_label( 'report', 'pending' ) );
		$this->assertSame( 'Reviewed', Status_Labels::get_label( 'report', 'reviewed' ) );
		$this->assertSame( 'Resolved', Status_Labels::get_label( 'report', 'resolved' ) );
		$this->assertSame( 'Dismissed', Status_Labels::get_label( 'report', 'dismissed' ) );
	}

	public function test_unknown_status_falls_back_to_a_readable_label_not_a_raw_slug(): void {
		$this->assertSame( 'Some weird value', Status_Labels::get_label( 'campaign', 'some_weird_value' ) );
	}

	public function test_unknown_domain_returns_empty_map(): void {
		$this->assertSame( array(), Status_Labels::all( 'not_a_real_domain' ) );
	}

	public function test_all_returns_the_full_map_for_a_domain(): void {
		$this->assertSame(
			array(
				'pending'           => 'Pending review',
				'approved'          => 'Live',
				'rejected'          => 'Rejected',
				'changes_requested' => 'Changes requested',
				'cancelled'         => 'Cancelled',
			),
			Status_Labels::all( 'ad_submission' )
		);
	}
}
