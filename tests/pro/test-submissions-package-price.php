<?php
/**
 * Submissions list shows what a CPM/CPC package submission will cost: the
 * budget it reserves on approval, not the package's $0.00 flat price.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Admin\Ad_Submissions_List_Table;
use WBAM_Pro\Core\Settings_Helper;

class Test_Submissions_Package_Price extends Pro_Test_Case {

	public function test_metered_package_shows_its_reserved_budget(): void {
		global $wpdb;

		$enabled             = Settings_Helper::get( 'enabled_modules', array() );
		$enabled['packages'] = true;
		Settings_Helper::update( 'enabled_modules', $enabled );

		$wpdb->insert(
			$wpdb->prefix . 'wbam_packages',
			array(
				'name'           => 'Click pack',
				'price'          => 0,
				'price_per_unit' => 0.5,
				'clicks_limit'   => 100,
				'pricing_model'  => 'cpc',
				'status'         => 'active',
				'created_at'     => current_time( 'mysql' ),
			)
		);

		require_once WBAM_PRO_PATH . 'includes/Admin/class-ad-submissions-list-table.php';
		$table = new Ad_Submissions_List_Table();
		$html  = $table->column_package( (object) array( 'package_id' => (int) $wpdb->insert_id ) );

		$this->assertStringContainsString( '$50.00 reserved', $html );
	}
}
