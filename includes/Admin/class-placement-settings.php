<?php
/**
 * Placements settings table.
 *
 * Renders the two-gate placement matrix on the Settings screen and counts
 * how many live creatives each slot carries, so an admin closing a slot
 * can see what it costs before they save.
 *
 * @package WB_Ad_Manager
 * @since   2.11.0
 */

namespace WBAM\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Placements settings table.
 */
class Placement_Settings {

	/**
	 * Enabled-ad count per placement ID.
	 *
	 * One grouped pass over the ad meta rather than a query per row — the
	 * settings screen lists every registered placement, and a per-row
	 * query would be an N+1 that grows with every integration module.
	 *
	 * `_wbam_placements` is a serialized array, so it cannot be GROUP BY'd
	 * in SQL. We fetch the enabled ads' meta in one IN() query and tally
	 * in PHP. Ad counts are in the hundreds at most, never the thousands.
	 *
	 * Only published ads count. This number is what the close-a-slot
	 * confirm quotes ("N active ad(s) will stop rendering"), and delivery
	 * runs through Placement_Engine::get_ads_for_placement(), whose
	 * get_posts() call defaults to post_status=publish. Counting drafts,
	 * pending, private or scheduled ads here would overstate the damage and
	 * make the warning untrustworthy.
	 *
	 * @since 2.11.0
	 * @return array<string,int> Placement ID => enabled ad count.
	 */
	public static function get_ad_counts() {
		global $wpdb;

		$cached = wp_cache_get( 'wbam_placement_ad_counts', 'wbam' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- aggregate for an
		// admin screen, cached below and invalidated on wbam_save_ad_meta.
		$rows = $wpdb->get_col(
			"SELECT pm.meta_value
			   FROM {$wpdb->postmeta} pm
			   JOIN {$wpdb->postmeta} en
			     ON en.post_id = pm.post_id
			    AND en.meta_key = '_wbam_enabled'
			    AND en.meta_value = '1'
			   JOIN {$wpdb->posts} p
			     ON p.ID = pm.post_id
			    AND p.post_type = 'wbam-ad'
			    AND p.post_status = 'publish'
			  WHERE pm.meta_key = '_wbam_placements'"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		$counts = array();
		foreach ( (array) $rows as $row ) {
			foreach ( (array) maybe_unserialize( $row ) as $slug ) {
				$slug = sanitize_key( (string) $slug );
				if ( '' === $slug ) {
					continue;
				}
				$counts[ $slug ] = isset( $counts[ $slug ] ) ? $counts[ $slug ] + 1 : 1;
			}
		}

		wp_cache_set( 'wbam_placement_ad_counts', $counts, 'wbam', 5 * MINUTE_IN_SECONDS );

		return $counts;
	}

	/**
	 * Drop the cached counts when an ad's placements change.
	 *
	 * @since 2.11.0
	 * @return void
	 */
	public static function clear_count_cache() {
		wp_cache_delete( 'wbam_placement_ad_counts', 'wbam' );
	}

	/**
	 * Render the two-gate placement matrix.
	 *
	 * Lists EVERY registered placement, not just selectable ones — an
	 * admin must be able to re-open a slot the site gate has closed, and
	 * a closed slot is absent from get_selectable_placements() by design.
	 *
	 * Emits two transport-only hidden fields after the table:
	 * `placement_gates_submitted` says the matrix was part of this request,
	 * and `placement_gates_offered` lists the exact rows it drew. Without
	 * them Settings::sanitize_settings() cannot tell "the admin unticked
	 * everything" from "this POST never contained the matrix" — an
	 * unticked checkbox posts nothing either way. Neither field is stored;
	 * see Settings_Helper::GATE_NONE for the encoding they feed.
	 *
	 * @since 2.11.0
	 * @return void
	 */
	public static function render_table() {
		$engine     = \WBAM\Modules\Placements\Placement_Engine::get_instance();
		$counts     = self::get_ad_counts();
		$site       = \WBAM\Core\Settings_Helper::enabled_placements();
		$advertiser = \WBAM\Core\Settings_Helper::advertiser_placements();
		$all_open   = empty( $site );
		$grouped    = $engine->get_placements_grouped();
		$option     = \WBAM\Admin\Settings::OPTION_NAME;
		$offered    = array();

		// The Advertisers column gates which slots may be SOLD, which only
		// means anything once the Pro advertiser portal exists. Free has no
		// portal, packages, or submissions, so the column is three clicks of
		// confusion with nothing behind it. Drawing it is Pro's call.
		$show_adv = defined( 'WBAM_PRO_VERSION' );

		// Extra column(s) an active add-on wants in this same matrix - e.g.
		// Pro's rotation module adds "Ads shown" here instead of repeating
		// every placement in its own separate card (owner decision, admin
		// polish audit item 3b). Gated on has_action() so a bare Free
		// install draws no empty column.
		$show_extra = has_action( 'wbam_placement_matrix_cell' );

		// Column header text is reused as the mobile card's row label (via
		// the `data-label` attribute + a CSS `content: attr()` rule below
		// 782px — see .wbam-placement-matrix in assets/css/admin.css).
		// Fetched once so the <thead> and every stacked row agree.
		$label_site = __( 'Site', 'wb-ads-rotator-with-split-test' );
		$label_adv  = __( 'Advertisers', 'wb-ads-rotator-with-split-test' );
		$label_ads  = __( 'Active ads', 'wb-ads-rotator-with-split-test' );
		?>
		<div class="wbam-placement-matrix__scroll">
		<table class="widefat wbam-placement-matrix" role="table">
			<thead role="rowgroup">
				<tr role="row">
					<th scope="col" role="columnheader"><?php esc_html_e( 'Slot', 'wb-ads-rotator-with-split-test' ); ?></th>
					<th scope="col" role="columnheader"><?php echo esc_html( $label_site ); ?></th>
					<?php if ( $show_adv ) : ?>
						<th scope="col" role="columnheader"><?php echo esc_html( $label_adv ); ?></th>
					<?php endif; ?>
					<th scope="col" role="columnheader"><?php echo esc_html( $label_ads ); ?></th>
					<?php if ( $show_extra ) : ?>
						<?php do_action( 'wbam_placement_matrix_head' ); ?>
					<?php endif; ?>
				</tr>
			</thead>
			<tbody role="rowgroup">
			<?php foreach ( $grouped as $group => $placements ) : ?>
				<tr class="wbam-placement-matrix__group" role="row">
					<th colspan="<?php echo (int) ( 3 + $show_adv + $show_extra ); ?>" scope="colgroup" role="columnheader"><?php echo esc_html( ucfirst( (string) $group ) ); ?></th>
				</tr>
				<?php
				foreach ( $placements as $id => $placement ) :
					if ( ! $placement->show_in_selector() ) {
						continue;
					}
					$offered[]   = (string) $id;
					$count       = isset( $counts[ $id ] ) ? (int) $counts[ $id ] : 0;
					$site_on     = $all_open || in_array( $id, $site, true );
					$adv_on      = empty( $advertiser ) || in_array( $id, $advertiser, true );
					$unavailable = ! $placement->is_available();
					?>
					<tr role="row"<?php echo $unavailable ? ' class="wbam-placement-matrix__row--unavailable"' : ''; ?>>
						<td role="cell">
							<strong><?php echo esc_html( $placement->get_name() ); ?></strong>
							<span class="description"><?php echo esc_html( $placement->get_description() ); ?></span>
							<?php if ( $unavailable ) : ?>
								<em><?php esc_html_e( 'Integration inactive', 'wb-ads-rotator-with-split-test' ); ?></em>
							<?php endif; ?>
						</td>
						<td role="cell" data-label="<?php echo esc_attr( $label_site ); ?>">
							<label>
								<span class="screen-reader-text">
									<?php
									/* translators: %s: placement name. */
									echo esc_html( sprintf( __( 'Use %s on this site', 'wb-ads-rotator-with-split-test' ), $placement->get_name() ) );
									?>
								</span>
								<input type="checkbox"
									class="wbam-gate-site"
									data-placement="<?php echo esc_attr( $id ); ?>"
									data-count="<?php echo esc_attr( (string) $count ); ?>"
									name="<?php echo esc_attr( $option . '[enabled_placements][]' ); ?>"
									value="<?php echo esc_attr( $id ); ?>"
									<?php checked( $site_on ); ?> />
							</label>
						</td>
						<?php if ( $show_adv ) : ?>
							<td role="cell" data-label="<?php echo esc_attr( $label_adv ); ?>">
								<label>
									<span class="screen-reader-text">
										<?php
										/* translators: %s: placement name. */
										echo esc_html( sprintf( __( 'Sell %s to advertisers', 'wb-ads-rotator-with-split-test' ), $placement->get_name() ) );
										?>
									</span>
									<input type="checkbox"
										class="wbam-gate-advertiser"
										data-placement="<?php echo esc_attr( $id ); ?>"
										name="<?php echo esc_attr( $option . '[advertiser_placements][]' ); ?>"
										value="<?php echo esc_attr( $id ); ?>"
										<?php checked( $adv_on ); ?>
										<?php disabled( ! $site_on ); ?> />
								</label>
							</td>
						<?php endif; ?>
						<td role="cell" data-label="<?php echo esc_attr( $label_ads ); ?>"><?php echo esc_html( (string) $count ); ?></td>
						<?php if ( $show_extra ) : ?>
							<?php do_action( 'wbam_placement_matrix_cell', $id, $placement ); ?>
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>
			<?php endforeach; ?>
			</tbody>
		</table>
		</div>
		<?php if ( ! $show_adv ) : ?>
			<p class="description wbam-placement-matrix__upsell">
				<?php
				printf(
					/* translators: %s: link to the Upgrade to PRO screen. */
					esc_html__( 'Want to sell these slots to advertisers and take submissions? That needs %s.', 'wb-ads-rotator-with-split-test' ),
					'<a href="' . esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-upgrade' ) ) . '">' . esc_html__( 'WB Ad Manager Pro', 'wb-ads-rotator-with-split-test' ) . '</a>'
				);
				?>
			</p>
		<?php endif; ?>
		<input type="hidden" name="<?php echo esc_attr( $option . '[placement_gates_submitted]' ); ?>" value="1" />
		<input type="hidden" name="<?php echo esc_attr( $option . '[placement_gates_offered]' ); ?>" value="<?php echo esc_attr( implode( ',', $offered ) ); ?>" />
		<?php if ( $show_adv ) : ?>
			<?php // Says the Advertisers column was on screen. Without it a free-side save reads as "every sell box unticked" and stores GATE_NONE. ?>
			<input type="hidden" name="<?php echo esc_attr( $option . '[placement_gates_adv_submitted]' ); ?>" value="1" />
		<?php endif; ?>
		<?php
	}
}
