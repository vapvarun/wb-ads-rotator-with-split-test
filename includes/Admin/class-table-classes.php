<?php
/**
 * Shared list-table class list.
 *
 * @package WB_Ad_Manager
 * @since   3.0.0
 */

namespace WBAM\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Supplies the admin-family class list for a WP_List_Table.
 *
 * WP_List_Table::display() builds its own class list, so `.wbam-admin-table`
 * can only reach a list table through get_table_classes(). Without it the
 * family styles the table's container - border, radius, shadow, header
 * background, all via `.wbam-admin .wp-list-table` - but never its interior,
 * so cell padding and header typography stay at WordPress defaults while every
 * hand-rolled table in the plugin uses the family scale.
 *
 * This is a class rather than a trait on purpose. Pro's six list tables consume
 * it across the plugin boundary, and `use SomeTrait` is resolved when the class
 * is compiled - if Pro updates before Free on a live site, a missing trait is a
 * fatal. A class can be guarded with class_exists() and fall back to
 * parent::get_table_classes(), so the worst case is a table that looks like
 * stock WordPress for one update cycle.
 *
 * @since 3.0.0
 */
final class Table_Classes {

	/**
	 * Build the class list for a list table.
	 *
	 * `fixed` is kept. Dropping it switches the column-width algorithm for every
	 * table at once, which can only be judged per screen against real data.
	 *
	 * `wp-list-table` was missing from this list (the class list this method
	 * builds REPLACES WP_List_Table's own default, it doesn't add to it), so
	 * every table using it silently lost the class every other admin.css rule
	 * scoped to `.wp-list-table` (including `.wp-list-table:has(> tbody > tr.no-items)
	 * tfoot { display: none }`, which is why the empty-state tfoot-repeat fix
	 * held on stock WP screens but not on these).
	 *
	 * @since 3.0.0
	 * @param string $plural Plural table arg; several screens' CSS and JS key off
	 *                       the per-table class WordPress generates from it.
	 * @return string[]
	 */
	public static function get( $plural = '' ) {
		return array_values(
			array_filter(
				array( 'wp-list-table', 'wbam-admin-table', 'widefat', 'striped', 'fixed', (string) $plural )
			)
		);
	}
}
