<?php
/**
 * Demo Data Cleaner
 *
 * Phase K of the format-aware placement matching plan (free plugin side).
 *
 * Provides a one-click "Remove demo data" action that deletes every row
 * previously seeded by the Setup Wizard — and only those rows. Safety is
 * enforced with belt-and-suspenders:
 *
 *  1. The action reads `wbam_demo_data_ids` (IDs we recorded on creation).
 *  2. For every ID it also verifies the post still carries `_wbam_is_demo = 1`.
 *     If the meta is missing the ID is skipped, so a repurposed post or a
 *     corrupted option value cannot wipe out real content.
 *
 * Also renders the "Remove demo data" button for reuse on any admin
 * screen (Help & Docs → Getting Started tab by default).
 *
 * @package WB_Ad_Manager
 * @since   2.8.0
 */

namespace WBAM\Admin;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Demo_Data_Cleaner class.
 */
class Demo_Data_Cleaner {

	/**
	 * Option name holding the tracked demo IDs.
	 *
	 * @var string
	 */
	const OPTION_IDS = 'wbam_demo_data_ids';

	/**
	 * Post meta flag set on every demo row at creation time.
	 *
	 * @var string
	 */
	const META_FLAG = '_wbam_is_demo';

	/**
	 * Nonce action for the clear request.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'wbam_clear_demo_data';

	/**
	 * Register hooks.
	 */
	public function register() {
		add_action( 'admin_post_wbam_clear_demo_data', array( __CLASS__, 'handle_clear_request' ) );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_render_notice' ) );

		// Help & Docs promises "Remove the samples any time from Tools" but
		// render_clear_button() had no caller — the button never actually
		// appeared there. Settings > Tools already fires this action for
		// PRO's own demo-data cleaner; hook the same spot so a Free-only
		// site (and a Free+Pro site, showing both) gets a working button.
		add_action( 'wbam_settings_tools_content', array( __CLASS__, 'render_clear_button_section' ) );
	}

	/**
	 * How many setup-wizard samples are still recorded (ads, pages, links).
	 *
	 * @since 3.2.0
	 * @return int
	 */
	public static function count() {
		$registry = get_option( self::OPTION_IDS, array() );
		$total    = 0;
		foreach ( array( 'ads', 'pages', 'links' ) as $bucket ) {
			if ( is_array( $registry ) && ! empty( $registry[ $bucket ] ) && is_array( $registry[ $bucket ] ) ) {
				$total += count( $registry[ $bucket ] );
			}
		}
		return $total;
	}

	/**
	 * Wrapper for the `wbam_settings_tools_content` action: the Sample
	 * content card, only when there is something to remove. An add-on that
	 * shows its own Sample content card (Pro lists these samples next to its
	 * demo set, and its one Remove clears both: owner decision, card
	 * 10344381767) says so through the filter, and this prints nothing.
	 *
	 * @since 3.2.0
	 */
	public static function render_clear_button_section() {
		/**
		 * Whether an add-on renders the Sample content card instead.
		 *
		 * @since 3.2.0
		 * @param bool $owned Default false.
		 */
		if ( apply_filters( 'wbam_sample_content_card_owned', false ) || self::count() <= 0 ) {
			return;
		}
		?>
		<h2 class="wbam-settings-heading"><?php esc_html_e( 'Sample content', 'wb-ads-rotator-with-split-test' ); ?></h2>
		<div class="wbam-card">
			<p><?php esc_html_e( 'Remove the sample ads created by the setup wizard.', 'wb-ads-rotator-with-split-test' ); ?></p>
			<?php self::render_clear_button(); ?>
		</div>
		<?php
	}

	/**
	 * Remove the WB Ad Manager widgets that showed one of these ads (the
	 * setup wizard's sample widget), so removing the samples leaves no
	 * orphan widget behind (card 10344381767).
	 *
	 * @param int[] $ad_ids Removed ad IDs.
	 */
	private static function remove_widgets_for( array $ad_ids ) {
		$instances = get_option( 'widget_wbam_ad_widget', array() );
		if ( ! $ad_ids || ! is_array( $instances ) ) {
			return;
		}

		$gone = array();
		foreach ( $instances as $number => $instance ) {
			if ( is_array( $instance ) && in_array( (int) ( $instance['ad_id'] ?? 0 ), $ad_ids, true ) ) {
				unset( $instances[ $number ] );
				$gone[] = 'wbam_ad_widget-' . $number;
			}
		}
		if ( ! $gone ) {
			return;
		}
		update_option( 'widget_wbam_ad_widget', $instances );

		$sidebars = wp_get_sidebars_widgets();
		foreach ( $sidebars as $sidebar_id => $widgets ) {
			if ( is_array( $widgets ) ) {
				$sidebars[ $sidebar_id ] = array_values( array_diff( $widgets, $gone ) );
			}
		}
		wp_set_sidebars_widgets( $sidebars );
	}

	/**
	 * Handle the admin-post clear request.
	 */
	public static function handle_clear_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You do not have permission to perform this action.', 'wb-ads-rotator-with-split-test' ),
				esc_html__( 'Permission denied', 'wb-ads-rotator-with-split-test' ),
				array( 'response' => 403 )
			);
		}

		$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_die(
				esc_html__( 'Security check failed.', 'wb-ads-rotator-with-split-test' ),
				esc_html__( 'Security check failed', 'wb-ads-rotator-with-split-test' ),
				array( 'response' => 403 )
			);
		}

		$result = self::clear();

		$redirect = isset( $_REQUEST['_wp_http_referer'] )
			? esc_url_raw( wp_unslash( $_REQUEST['_wp_http_referer'] ) )
			: admin_url( 'edit.php?post_type=wbam-ad&page=wbam-help' );

		$redirect = add_query_arg(
			array(
				'wbam_demo_cleared' => 1,
				'ads'               => (int) $result['ads'],
				'pages'             => (int) $result['pages'],
				'links'             => (int) $result['links'],
				'skipped'           => (int) $result['skipped'],
			),
			$redirect
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Clear all tracked demo rows.
	 *
	 * For every tracked ID:
	 *   - Verify the post exists.
	 *   - Verify `_wbam_is_demo = 1` meta is present.
	 *   - `wp_delete_post( $id, true )` (force delete, no trash).
	 *
	 * Rows missing the meta flag are SKIPPED (not deleted). After the
	 * pass completes, the tracking option is deleted so re-running is
	 * a no-op.
	 *
	 * @return array{ads:int, pages:int, links:int, skipped:int} Counts.
	 */
	public static function clear() {
		$registry = get_option( self::OPTION_IDS, array() );
		if ( ! is_array( $registry ) ) {
			$registry = array();
		}

		$counts = array(
			'ads'     => 0,
			'pages'   => 0,
			'links'   => 0,
			'skipped' => 0,
		);

		$removed_ads = array();

		// Post-backed buckets: ads + pages.
		foreach ( array( 'ads', 'pages' ) as $bucket ) {
			if ( empty( $registry[ $bucket ] ) || ! is_array( $registry[ $bucket ] ) ) {
				continue;
			}

			foreach ( $registry[ $bucket ] as $post_id ) {
				$post_id = (int) $post_id;
				if ( $post_id <= 0 ) {
					continue;
				}

				$post = get_post( $post_id );
				if ( ! $post ) {
					// Already gone — not an error, not a skip either.
					continue;
				}

				// Defensive double-check: only delete if the demo meta
				// flag is still present. If it's missing, the post may
				// have been repurposed by the admin, or the option got
				// corrupted — either way, DO NOT delete.
				$is_demo = get_post_meta( $post_id, self::META_FLAG, true );
				if ( '1' !== (string) $is_demo ) {
					++$counts['skipped'];
					continue;
				}

				$deleted = wp_delete_post( $post_id, true );
				if ( $deleted ) {
					++$counts[ $bucket ];
					if ( 'ads' === $bucket ) {
						$removed_ads[] = $post_id;
					}
				}
			}
		}

		// Links bucket — rows live in a custom table, deleted via the
		// links module. Free plugin does not currently seed links, but
		// we clear them here anyway for forward-compat.
		if ( ! empty( $registry['links'] ) && is_array( $registry['links'] ) ) {
			global $wpdb;
			$table = $wpdb->prefix . 'wbam_links';
			foreach ( $registry['links'] as $link_id ) {
				$link_id = (int) $link_id;
				if ( $link_id <= 0 ) {
					continue;
				}
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$affected = $wpdb->delete( $table, array( 'id' => $link_id ), array( '%d' ) );
				if ( $affected ) {
					++$counts['links'];
				}
			}
		}

		self::remove_widgets_for( $removed_ads );

		delete_option( self::OPTION_IDS );

		/**
		 * Fires after demo data has been cleared.
		 *
		 * @since 2.8.0
		 *
		 * @param array $counts Per-bucket deletion counts + skipped.
		 */
		do_action( 'wbam_demo_data_cleared', $counts );

		return $counts;
	}

	/**
	 * Render the "Remove demo data" form button for reuse.
	 *
	 * The button is only emitted when there is tracked demo data. Posts
	 * to admin-post.php with a nonce and the `manage_options` check is
	 * enforced on the handler side.
	 *
	 * @param string $label Optional button label. Defaults to "Remove sample ads".
	 */
	public static function render_clear_button( $label = '' ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$registry = get_option( self::OPTION_IDS, array() );
		if ( ! is_array( $registry ) ) {
			$registry = array();
		}

		$total = 0;
		foreach ( array( 'ads', 'pages', 'links' ) as $bucket ) {
			if ( ! empty( $registry[ $bucket ] ) && is_array( $registry[ $bucket ] ) ) {
				$total += count( $registry[ $bucket ] );
			}
		}

		if ( $total <= 0 ) {
			return;
		}

		if ( '' === $label ) {
			$label = __( 'Remove sample ads', 'wb-ads-rotator-with-split-test' );
		}

		$confirm = sprintf(
			/* translators: %d: number of demo items that will be removed. */
			_n(
				'Remove %d sample item created by the setup wizard? This cannot be undone.',
				'Remove %d sample items created by the setup wizard? This cannot be undone.',
				$total,
				'wb-ads-rotator-with-split-test'
			),
			$total
		);
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wbam-demo-clear-form">
			<input type="hidden" name="action" value="wbam_clear_demo_data" />
			<?php wp_nonce_field( self::NONCE_ACTION ); ?>
			<button type="submit" class="button" data-wbam-confirm="<?php echo esc_attr( $confirm ); ?>" data-wbam-confirm-tone="danger">
				<?php echo wbam_icon( 'trash-2', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?>
				<?php echo esc_html( $label ); ?>
				<span class="wbam-demo-count">(<?php echo esc_html( (string) $total ); ?>)</span>
			</button>
		</form>
		<?php
	}

	/**
	 * Render the "demo data cleared" admin notice after a successful clear.
	 */
	public static function maybe_render_notice() {
		// Only this class's own redirect ('1'). Pro's Tools page reuses the
		// query arg with 'ok'/'empty' and renders its own notice; answering
		// that too printed "No demo items needed to be removed." next to
		// Pro's "Removed N demo items."
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['wbam_demo_cleared'] ) || '1' !== sanitize_text_field( wp_unslash( $_GET['wbam_demo_cleared'] ) ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$ads     = isset( $_GET['ads'] ) ? (int) $_GET['ads'] : 0;
		$pages   = isset( $_GET['pages'] ) ? (int) $_GET['pages'] : 0;
		$links   = isset( $_GET['links'] ) ? (int) $_GET['links'] : 0;
		$skipped = isset( $_GET['skipped'] ) ? (int) $_GET['skipped'] : 0;
		// phpcs:enable

		$parts = array();
		if ( $ads > 0 ) {
			/* translators: %d: number of ads removed. */
			$parts[] = sprintf( _n( '%d ad', '%d ads', $ads, 'wb-ads-rotator-with-split-test' ), $ads );
		}
		if ( $pages > 0 ) {
			/* translators: %d: number of pages removed. */
			$parts[] = sprintf( _n( '%d page', '%d pages', $pages, 'wb-ads-rotator-with-split-test' ), $pages );
		}
		if ( $links > 0 ) {
			/* translators: %d: number of links removed. */
			$parts[] = sprintf( _n( '%d link', '%d links', $links, 'wb-ads-rotator-with-split-test' ), $links );
		}

		$removed_summary = empty( $parts )
			? __( 'There were no sample ads to remove.', 'wb-ads-rotator-with-split-test' )
			: sprintf(
				/* translators: %s: comma-separated list, e.g. "3 ads, 1 page". */
				__( 'Sample ads removed: %s.', 'wb-ads-rotator-with-split-test' ),
				implode( ', ', $parts )
			);

		$skipped_notice = '';
		if ( $skipped > 0 ) {
			$skipped_notice = ' ' . sprintf(
				/* translators: %d: number of skipped items. */
				_n(
					'%d item was kept because it is no longer marked as a sample (you may have edited it).',
					'%d items were kept because they are no longer marked as samples (you may have edited them).',
					$skipped,
					'wb-ads-rotator-with-split-test'
				),
				$skipped
			);
		}
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( $removed_summary . $skipped_notice ); ?></p>
		</div>
		<?php
	}
}
