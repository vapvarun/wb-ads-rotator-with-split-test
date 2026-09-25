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
}
