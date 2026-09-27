<?php
/**
 * Classifieds, Advertisers and Links folded into the one WB Ad Manager menu
 * in 3.2.0 must land in their own labelled sections, not trail after
 * Settings in the header-less catch-all.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Pro_Plugin;

class Test_Admin_Menu_Sections extends Pro_Test_Case {

	public function test_pro_pages_get_their_own_sections_after_campaigns(): void {
		$parent   = 'edit.php?post_type=wbam-ad';
		$groups   = apply_filters( 'wbam_admin_menu_section_map', array(), $parent );
		$sections = apply_filters(
			'wbam_admin_menu_sections',
			array(
				'ads'       => '',
				'campaigns' => 'Campaigns',
				'links'     => 'Links',
				'reports'   => 'Reports',
			),
			$parent
		);

		$this->assertSame( 'advertisers', $groups['wbam-transactions'] );
		$this->assertArrayNotHasKey( 'wbam-email-captures', $groups, 'Card 10344383905: Pro leaves Email Captures in Free\'s ads section.' );
		$this->assertSame( 'classifieds', $groups['wbam-classified-reports'] );
		$this->assertSame( 'classifieds', $groups['edit-tags.php?taxonomy=wbam-classified-cat&amp;post_type=wbam-classified'] );
		$this->assertSame( array( 'ads', 'campaigns', 'advertisers', 'classifieds', 'links', 'reports' ), array_keys( $sections ) );

		// Another menu is left alone.
		$this->assertSame( array(), apply_filters( 'wbam_admin_menu_section_map', array(), 'some-other-menu' ) );
	}

	public function test_email_captures_sits_under_add_new_with_pro_active(): void {
		global $submenu;

		// The order pages register in on a Pro site: WordPress's two, Pro's
		// pages, then Free's Email Captures (QA wave 10 saw it at 16 of 24).
		$pro_pages = array( 'wbam-advertisers', 'wbam-packages', 'wbam-transactions', 'wbam-membership-plans', 'wbam-classifieds', 'wbam-campaigns', 'wbam-analytics' );
		$items     = array(
			array( 'All Ads', 'edit_posts', 'edit.php?post_type=wbam-ad' ),
			array( 'Add New Ad', 'edit_posts', 'post-new.php?post_type=wbam-ad' ),
		);
		foreach ( $pro_pages as $slug ) {
			$items[] = array( $slug, 'manage_options', $slug );
		}
		$items[] = array( 'Email Captures', 'manage_options', 'wbam-email-captures' );
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test fixture.
		$submenu = array( 'edit.php?post_type=wbam-ad' => $items );

		( new \WBAM\Admin\Admin() )->reorder_submenu_into_sections();

		$slugs = array_values(
			array_filter(
				array_map( static fn ( $item ) => $item[2] ?? '', $submenu['edit.php?post_type=wbam-ad'] ),
				static fn ( $slug ) => false === strpos( $slug, '#' )
			)
		);
		$this->assertSame( array( 'edit.php?post_type=wbam-ad', 'post-new.php?post_type=wbam-ad', 'wbam-email-captures' ), array_slice( $slugs, 0, 3 ) );
		$this->assertCount( count( $items ), $slugs, 'Nothing dropped.' );
	}
}
