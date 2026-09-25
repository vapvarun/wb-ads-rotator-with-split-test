<?php
/**
 * Admin design system: the shared UX helper (badges, page header).
 *
 * One state map drives every status pill in both plugins (nine former
 * badge families migrated onto it in the admin design-system project).
 * This guards the tone each state resolves to so a future edit can't
 * silently turn "pending" green on one screen while it stays amber
 * everywhere else.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Free;

use WP_UnitTestCase;
use WBAM\Admin\UX;

class Test_UX extends WP_UnitTestCase {

	public function tear_down(): void {
		remove_all_filters( 'wbam_admin_status_variant' );
		parent::tear_down();
	}

	/**
	 * @dataProvider provider_status_tones
	 */
	public function test_status_variant_tone_map( string $status, string $expected_variant ): void {
		$this->assertSame( $expected_variant, UX::status_variant( $status ) );
	}

	public function provider_status_tones(): array {
		return array(
			// Success.
			'active is success'     => array( 'active', 'success' ),
			'approved is success'   => array( 'approved', 'success' ),
			'accepted is success'   => array( 'accepted', 'success' ),
			'paid is success'       => array( 'paid', 'success' ),
			'resolved is success'   => array( 'resolved', 'success' ),
			'running is success'    => array( 'running', 'success' ),
			// Warning.
			'pending is warning'    => array( 'pending', 'warning' ),
			'paused is warning'     => array( 'paused', 'warning' ),
			'changes_requested'     => array( 'changes_requested', 'warning' ),
			'reviewed is warning'   => array( 'reviewed', 'warning' ),
			// Danger.
			'rejected is danger'    => array( 'rejected', 'danger' ),
			'suspended is danger'   => array( 'suspended', 'danger' ),
			'expired is danger'     => array( 'expired', 'danger' ),
			'cancelled is danger'   => array( 'cancelled', 'danger' ),
			'spam is danger'        => array( 'spam', 'danger' ),
			// Info.
			'completed is info'     => array( 'completed', 'info' ),
			'sold is info'          => array( 'sold', 'info' ),
			'draft is info'         => array( 'draft', 'info' ),
			// Muted.
			'inactive is muted'     => array( 'inactive', 'muted' ),
			'dismissed is muted'    => array( 'dismissed', 'muted' ),
			'member is muted'       => array( 'member', 'muted' ),
			// Unknown status falls back to muted rather than an
			// unstyled/undefined pill.
			'unknown falls to muted' => array( 'some-unmapped-status', 'muted' ),
		);
	}

	public function test_status_variant_is_filterable(): void {
		add_filter(
			'wbam_admin_status_variant',
			static function ( $variant, $status ) {
				return 'flagged' === $status ? 'danger' : $variant;
			},
			10,
			2
		);

		$this->assertSame( 'danger', UX::status_variant( 'flagged' ) );
	}

	public function test_status_badge_renders_the_shared_pill_markup(): void {
		$html = UX::status_badge( 'pending' );

		$this->assertStringContainsString( 'wbam-status-badge', $html );
		$this->assertStringContainsString( 'wbam-status-badge--warning', $html );
		$this->assertStringContainsString( 'Pending', $html );
	}

	public function test_status_badge_accepts_a_custom_label(): void {
		$html = UX::status_badge( 'active', 'Live' );

		$this->assertStringContainsString( 'wbam-status-badge--success', $html );
		$this->assertStringContainsString( 'Live', $html );
		$this->assertStringNotContainsString( 'Active', $html );
	}

	public function test_page_header_renders_a_back_link_in_the_header_not_a_bare_link(): void {
		$html = UX::page_header(
			array(
				'title'    => 'Reject listing',
				'back_url' => 'https://example.test/wp-admin/admin.php?page=wbam-classifieds',
				'echo'     => false,
			)
		);

		$this->assertStringContainsString( 'wbam-page-header__back', $html );
		$this->assertStringContainsString( 'https://example.test/wp-admin/admin.php?page=wbam-classifieds', $html );
	}

	public function test_page_header_omits_the_back_link_when_no_url_given(): void {
		$html = UX::page_header( array( 'title' => 'Ads', 'echo' => false ) );

		$this->assertStringNotContainsString( 'wbam-page-header__back', $html );
	}

	public function test_action_summary_bulk_mode_lists_first_five_and_counts_the_rest(): void {
		$html = UX::action_summary(
			array(
				'items' => array( 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven' ),
			)
		);

		$this->assertStringContainsString( 'One', $html );
		$this->assertStringContainsString( 'Five', $html );
		$this->assertStringNotContainsString( 'Six', $html );
		$this->assertStringContainsString( 'and 2 more', $html );
	}

	public function test_action_summary_bulk_mode_hides_the_more_line_at_five_or_fewer(): void {
		$html = UX::action_summary( array( 'items' => array( 'One', 'Two' ) ) );

		$this->assertStringNotContainsString( 'more', $html );
	}

	public function test_action_summary_single_mode_shows_title_meta_and_status(): void {
		$html = UX::action_summary(
			array(
				'title'  => 'Vintage bicycle',
				'meta'   => 'Seller: Jane Doe',
				'status' => 'pending',
			)
		);

		$this->assertStringContainsString( 'Vintage bicycle', $html );
		$this->assertStringContainsString( 'Seller: Jane Doe', $html );
		$this->assertStringContainsString( 'wbam-status-badge--warning', $html );
	}

	public function test_action_summary_returns_empty_string_with_no_items_or_title(): void {
		$this->assertSame( '', UX::action_summary() );
	}

	public function test_action_bar_default_variant_is_primary(): void {
		$html = UX::action_bar(
			array(
				'submit_label' => 'Save changes',
				'cancel_url'   => 'https://example.test/list',
			)
		);

		$this->assertStringContainsString( 'wbam-admin-btn--primary', $html );
		$this->assertStringNotContainsString( 'wbam-admin-btn--danger', $html );
		$this->assertStringContainsString( 'Save changes', $html );
		$this->assertStringContainsString( 'https://example.test/list', $html );
	}

	public function test_action_bar_danger_variant_for_destructive_actions(): void {
		$html = UX::action_bar(
			array(
				'submit_label' => 'Reject listing',
				'variant'      => 'danger',
				'cancel_url'   => 'https://example.test/list',
			)
		);

		$this->assertStringContainsString( 'wbam-admin-btn--danger', $html );
		$this->assertStringNotContainsString( 'wbam-admin-btn--primary', $html );
	}
}
