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
		$this->assertSame( 'classifieds', $groups['wbam-classified-reports'] );
		$this->assertSame( 'classifieds', $groups['edit-tags.php?taxonomy=wbam-classified-cat&amp;post_type=wbam-classified'] );
		$this->assertSame( array( 'ads', 'campaigns', 'advertisers', 'classifieds', 'links', 'reports' ), array_keys( $sections ) );

		// Another menu is left alone.
		$this->assertSame( array(), apply_filters( 'wbam_admin_menu_section_map', array(), 'some-other-menu' ) );
	}
}
