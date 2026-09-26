<?php
/**
 * Admin UX helpers — the one place the shared admin family is rendered.
 *
 * Every admin screen in both the free and Pro plugins builds its page chrome,
 * status pills and empty states through these methods, so 20-plus screens read
 * as one product. Ported from Learnomy's UX helper; the markup pairs with the
 * classes in assets/css/admin-family.css.
 *
 * Pro calls these as \WBAM\Admin\UX::page_header( … ); the free plugin is
 * always present under Pro, so no existence guard is needed on the Pro side.
 *
 * @package WB_Ad_Manager
 * @since   2.9.2
 */

namespace WBAM\Admin;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared admin UX component renderer.
 */
class UX {

	/**
	 * Render the page header block: title, optional subtitle, optional actions.
	 *
	 * Replaces the bare `<h1 class="wp-heading-inline">` + `<hr>` each screen
	 * hand-rolled, so titles, spacing and action buttons line up everywhere.
	 * Renders only the header block (plus the notices anchor) — the caller keeps
	 * its own `<div class="wrap wbam-admin">…</div>`. The `wbam-admin` class on
	 * that wrap is what scopes the family's WP-list-table normalisation, so add
	 * it when converting a screen.
	 *
	 * @since 2.9.2
	 * @param array $args {
	 *     @type string $title    Required. Page title (escaped here).
	 *     @type string $desc     Optional. One-line subtitle.
	 *     @type string $actions  Optional. Pre-escaped HTML for the right side
	 *                            (e.g. an "Add New" button). Caller escapes.
	 *     @type string $back_url   Optional. When set, renders a "back to list"
	 *                              link above the title instead of a bare link
	 *                              at the bottom of the page — the action-screen
	 *                              pattern (single-purpose forms: reject,
	 *                              decline, adjust balance, add/edit) always
	 *                              sets this instead of hand-rolling its own.
	 *     @type string $back_label Optional. Back link text; defaults to
	 *                              "Back to list".
	 *     @type bool   $echo     Optional. Echo (default true) or return.
	 * }
	 * @return string HTML when $echo is false, else empty string.
	 */
	public static function page_header( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'title'      => '',
				'desc'       => '',
				'actions'    => '',
				'back_url'   => '',
				'back_label' => __( 'Back to list', 'wb-ads-rotator-with-split-test' ),
				'echo'       => true,
			)
		);

		ob_start();
		?>
		<div class="wbam-page-header">
			<div class="wbam-page-header__left">
				<?php if ( '' !== $args['back_url'] ) : ?>
					<a href="<?php echo esc_url( $args['back_url'] ); ?>" class="wbam-page-header__back">
						<?php echo wbam_icon( 'arrow-left', array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?>
						<?php echo esc_html( $args['back_label'] ); ?>
					</a>
				<?php endif; ?>
				<h1 class="wbam-page-header__title"><?php echo esc_html( $args['title'] ); ?></h1>
				<?php if ( '' !== $args['desc'] ) : ?>
					<p class="wbam-page-header__desc"><?php echo esc_html( $args['desc'] ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( '' !== $args['actions'] ) : ?>
				<div class="wbam-page-header__actions"><?php echo wp_kses( $args['actions'], self::actions_allowed_html() ); ?></div>
			<?php endif; ?>
		</div>
		<?php
		// WordPress relocates admin notices to just after the first h1/hr; keep
		// an anchor so notices land under the header, not above it.
		?>
		<hr class="wp-header-end" style="margin:0;border:0;">
		<?php
		$html = ob_get_clean();

		if ( $args['echo'] ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from esc_html()/wp_kses_post() above.
			return '';
		}
		return $html;
	}

	/**
	 * Allowed HTML for the header/empty-state action slots.
	 *
	 * Actions are developer-authored button/link markup, often with an inline
	 * Lucide SVG icon — which wp_kses_post() would strip. This allowlist keeps
	 * the icons while still constraining the markup to buttons, links and SVG
	 * shapes.
	 *
	 * @since 2.9.2
	 * @return array<string,array<string,bool>>
	 */
	private static function actions_allowed_html() {
		$svg_attrs = array(
			'xmlns'           => true,
			'width'           => true,
			'height'          => true,
			'viewbox'         => true,
			'fill'            => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
			'class'           => true,
			'aria-hidden'     => true,
			'focusable'       => true,
			'd'               => true,
			'points'          => true,
			'x1'              => true,
			'y1'              => true,
			'x2'              => true,
			'y2'              => true,
			'cx'              => true,
			'cy'              => true,
			'r'               => true,
			'rx'              => true,
			'x'               => true,
			'y'               => true,
		);

		return array(
			'a'        => array(
				'href'   => true,
				'class'  => true,
				'id'     => true,
				'target' => true,
				'rel'    => true,
				'data-*' => true,
			),
			'button'   => array(
				'type'     => true,
				'class'    => true,
				'id'       => true,
				'data-*'   => true,
				'disabled' => true,
			),
			'span'     => array( 'class' => true ),
			'svg'      => $svg_attrs,
			'path'     => $svg_attrs,
			'line'     => $svg_attrs,
			'polyline' => $svg_attrs,
			'polygon'  => $svg_attrs,
			'circle'   => $svg_attrs,
			'rect'     => $svg_attrs,
		);
	}

	/**
	 * Map any status slug to a family badge variant.
	 *
	 * One state map for every screen in both plugins, so "pending" is amber
	 * everywhere and "rejected" is red everywhere, instead of nine badge
	 * families each drawing the same handful of states differently.
	 * Filterable so a module can add its own statuses without editing this map.
	 *
	 * @since 2.9.2
	 * @param string $status Status slug.
	 * @return string One of success|danger|warning|info|muted.
	 */
	public static function status_variant( $status ) {
		$map = array(
			// Success — live, approved, paid.
			'active'            => 'success',
			'enabled'           => 'success',
			'approved'          => 'success',
			'accepted'          => 'success',
			'paid'              => 'success',
			'resolved'          => 'success',
			'running'           => 'success',
			'replied'           => 'success',
			'live'              => 'success',
			'published'         => 'success',

			// Warning — needs attention, in progress.
			'pending'           => 'warning',
			'paused'            => 'warning',
			'changes_requested' => 'warning',
			'reviewed'          => 'warning',
			'unread'            => 'warning',
			'test'              => 'warning',

			// Danger — stopped, refused, unsafe.
			'rejected'          => 'danger',
			'suspended'         => 'danger',
			'expired'           => 'danger',
			'cancelled'         => 'danger',
			'canceled'          => 'danger',
			'spam'              => 'danger',
			'banned'            => 'danger',
			'failed'            => 'danger',
			'incomplete'        => 'danger',

			// Info — neutral, final, awaiting nothing.
			'completed'         => 'info',
			'sold'              => 'info',
			'draft'             => 'info',
			'refunded'          => 'info',

			// Muted — off, ordinary, no ad spend.
			'inactive'          => 'muted',
			'dismissed'         => 'muted',
			'member'            => 'muted',
			'archived'          => 'muted',
			'read'              => 'muted',
			'off'               => 'muted',
			'standard'          => 'muted',
			'disabled'          => 'muted',
		);

		$variant = isset( $map[ $status ] ) ? $map[ $status ] : 'muted';

		/**
		 * Filter the badge variant chosen for a status.
		 *
		 * @since 2.9.2
		 * @param string $variant success|danger|warn|info|muted.
		 * @param string $status  The status slug.
		 */
		return (string) apply_filters( 'wbam_admin_status_variant', $variant, $status );
	}

	/**
	 * Render a status pill.
	 *
	 * @since 2.9.2
	 * @param string      $status Status slug (also the default label).
	 * @param string|null $label  Optional display label; defaults to a
	 *                            title-cased slug.
	 * @return string Escaped badge HTML.
	 */
	public static function status_badge( $status, $label = null ) {
		$status  = (string) $status;
		$variant = self::status_variant( $status );
		if ( null === $label ) {
			$label = ucwords( str_replace( array( '_', '-' ), ' ', $status ) );
		}

		return sprintf(
			'<span class="wbam-status-badge wbam-status-badge--%s">%s</span>',
			esc_attr( $variant ),
			esc_html( $label )
		);
	}

	/**
	 * Render an empty / no-results panel.
	 *
	 * @since 2.9.2
	 * @param array $args {
	 *     @type string $icon    Optional. A Lucide icon name (rendered via
	 *                           wbam_icon()) shown above the title.
	 *     @type string $title   Optional. Headline.
	 *     @type string $message Optional. One-line explanation.
	 *     @type string $actions Optional. Pre-escaped action HTML.
	 * }
	 * @return string Escaped panel HTML.
	 */
	public static function empty_state( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'icon'    => '',
				'title'   => __( 'Nothing here yet', 'wb-ads-rotator-with-split-test' ),
				'message' => '',
				'actions' => '',
			)
		);

		ob_start();
		?>
		<div class="wbam-empty-state">
			<?php if ( '' !== $args['icon'] ) : ?>
				<span class="wbam-empty-state__icon">
					<?php echo wbam_icon( $args['icon'], array( 'size' => 'lg' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns pre-escaped markup. ?>
				</span>
			<?php endif; ?>
			<p class="wbam-empty-state__title"><?php echo esc_html( $args['title'] ); ?></p>
			<?php if ( '' !== $args['message'] ) : ?>
				<p><?php echo esc_html( $args['message'] ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $args['actions'] ) : ?>
				<div class="wbam-page-header__actions"><?php echo wp_kses( $args['actions'], self::actions_allowed_html() ); ?></div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render the "what this affects" summary at the top of an action-screen
	 * card — a bulk action lists the selected item titles (first five, then
	 * "and N more"); a single-item action shows the item title, an optional
	 * secondary line (seller, advertiser, …) and an optional status badge.
	 *
	 * @since 3.2.0
	 * @param array $args {
	 *     @type string[] $items  Bulk mode: selected item titles, in order.
	 *                             Non-empty triggers bulk mode; single-mode
	 *                             args are ignored when this is set.
	 *     @type string   $title  Single mode: the item's title/name.
	 *     @type string   $meta   Single mode: a secondary line (e.g. the
	 *                            seller or advertiser name).
	 *     @type string   $status Single mode: a status slug rendered as a
	 *                            status_badge().
	 * }
	 * @return string Escaped summary HTML, or '' when there is nothing to summarise.
	 */
	public static function action_summary( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'items'  => array(),
				'label'  => '',
				'title'  => '',
				'meta'   => '',
				'status' => '',
			)
		);

		ob_start();

		if ( ! empty( $args['items'] ) ) {
			$items     = array_values( $args['items'] );
			$total     = count( $items );
			$shown     = array_slice( $items, 0, 5 );
			$remaining = $total - count( $shown );
			?>
			<div class="wbam-action-summary">
				<?php if ( '' !== $args['label'] ) : ?>
					<p class="wbam-action-summary__title"><?php echo esc_html( $args['label'] ); ?></p>
				<?php endif; ?>
				<ul class="wbam-action-summary__list">
					<?php foreach ( $shown as $item ) : ?>
						<li><?php echo esc_html( $item ); ?></li>
					<?php endforeach; ?>
				</ul>
				<?php if ( $remaining > 0 ) : ?>
					<p class="wbam-action-summary__more">
						<?php
						printf(
							/* translators: %d: number of additional selected items not listed above */
							esc_html(
								/* translators: %d: number of additional selected items not listed above */
								_n( 'and %d more', 'and %d more', $remaining, 'wb-ads-rotator-with-split-test' )
							),
							(int) $remaining
						);
						?>
					</p>
				<?php endif; ?>
			</div>
			<?php
		} elseif ( '' !== $args['title'] ) {
			?>
			<div class="wbam-action-summary">
				<p class="wbam-action-summary__title"><?php echo esc_html( $args['title'] ); ?></p>
				<?php if ( '' !== $args['meta'] || '' !== $args['status'] ) : ?>
					<p class="wbam-action-summary__meta">
						<?php echo '' !== $args['meta'] ? esc_html( $args['meta'] ) : ''; ?>
						<?php echo '' !== $args['status'] ? wp_kses_post( self::status_badge( $args['status'] ) ) : ''; ?>
					</p>
				<?php endif; ?>
			</div>
			<?php
		}

		return ob_get_clean();
	}

	/**
	 * Render a left-hand section nav for a multi-section admin screen.
	 *
	 * Used by the one-page Settings screen (General, Ads & Display,
	 * Classifieds, Credits, ...) so every section lives behind one URL with
	 * `?section=` instead of one submenu page per section. Renders a
	 * grouped `<ul>` for desktop — a real surface with an icon per item,
	 * matching `.wbam-card` — and a `<select>` (groups become `<optgroup>`)
	 * inside a plain GET `<form>` for narrow screens. CSS in
	 * admin-family.css swaps between them at 782px, matching WP core's own
	 * admin-menu collapse point. No inline JS: the form's own "Go" submit
	 * button makes the `<select>` work with JS off; admin-settings-nav.js
	 * progressively auto-submits on change and hides that button.
	 *
	 * @since 3.2.0
	 * @since 3.2.0 Owner correction: rail redesigned as a card-like surface
	 *              with per-item Lucide icons and group headings; mobile
	 *              `<select>` moved off inline `onchange` into a real GET
	 *              form + external JS.
	 * @param array<string,array{label:string,url:string,icon?:string,group?:string}> $sections
	 *        Ordered map of section slug => { label, url, icon, group }.
	 * @param string                                                                  $current Active section slug.
	 * @return void
	 */
	public static function settings_nav( array $sections, string $current ) {
		if ( empty( $sections ) ) {
			return;
		}

		// Group while preserving the order sections were handed in, and the
		// order groups are first seen in — no separate sort pass needed.
		$groups = array();
		foreach ( $sections as $slug => $section ) {
			$group                     = isset( $section['group'] ) ? $section['group'] : '';
			$groups[ $group ][ $slug ] = $section;
		}
		?>
		<nav class="wbam-settings-nav" aria-label="<?php esc_attr_e( 'Settings sections', 'wb-ads-rotator-with-split-test' ); ?>">
			<form method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>" class="wbam-settings-nav__form">
				<input type="hidden" name="post_type" value="<?php echo esc_attr( \WBAM\Core\Admin_Links::POST_TYPE ); ?>" />
				<input type="hidden" name="page" value="wbam-settings" />
				<label class="screen-reader-text" for="wbam-settings-nav-select">
					<?php esc_html_e( 'Settings sections', 'wb-ads-rotator-with-split-test' ); ?>
				</label>
				<select id="wbam-settings-nav-select" class="wbam-settings-nav__select" name="section" aria-label="<?php esc_attr_e( 'Settings sections', 'wb-ads-rotator-with-split-test' ); ?>">
					<?php foreach ( $groups as $group_label => $items ) : ?>
						<?php if ( '' !== $group_label ) : ?>
							<optgroup label="<?php echo esc_attr( $group_label ); ?>">
						<?php endif; ?>
						<?php foreach ( $items as $slug => $section ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, $current ); ?>>
								<?php echo esc_html( $section['label'] ); ?>
							</option>
						<?php endforeach; ?>
						<?php if ( '' !== $group_label ) : ?>
							</optgroup>
						<?php endif; ?>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button wbam-settings-nav__go"><?php esc_html_e( 'Go', 'wb-ads-rotator-with-split-test' ); ?></button>
			</form>
			<div class="wbam-settings-nav__list">
				<?php foreach ( $groups as $group_label => $items ) : ?>
					<?php if ( '' !== $group_label ) : ?>
						<p class="wbam-settings-nav__group"><?php echo esc_html( $group_label ); ?></p>
					<?php endif; ?>
					<ul class="wbam-settings-nav__group-list">
						<?php foreach ( $items as $slug => $section ) : ?>
							<?php $is_current = $slug === $current; ?>
							<li>
								<a
									href="<?php echo esc_url( $section['url'] ); ?>"
									class="wbam-settings-nav__link<?php echo $is_current ? ' is-active' : ''; ?>"
									<?php echo $is_current ? ' aria-current="page"' : ''; ?>
								>
									<?php if ( ! empty( $section['icon'] ) && function_exists( 'wbam_icon' ) ) : ?>
										<?php
										// wbam_icon() pre-escapes its own markup.
										echo wbam_icon( $section['icon'], array( 'size' => 'sm' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										?>
									<?php endif; ?>
									<span class="wbam-settings-nav__label"><?php echo esc_html( $section['label'] ); ?></span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endforeach; ?>
			</div>
		</nav>
		<?php
	}

	/**
	 * Render an "On this page" jump row for a settings section with 4+
	 * cards (card 10343706274's own rule) — General, Ads & Display,
	 * Classifieds, ... Renders nothing for fewer than 4 items, since a jump
	 * row for 2-3 cards is not a navigation aid, it is clutter.
	 *
	 * Desktop: a small row of plain `#anchor` links — no JS required, the
	 * browser's native in-page navigation does the work.
	 *
	 * <=782px (same breakpoint as the sidebar rail): JS swaps the link row
	 * for a compact `<select>` with a visually-hidden `<label>`. This is
	 * *not* the rail's GET-form pattern, deliberately — the rail navigates
	 * to a different URL per option (a real `?section=` request survives a
	 * GET form's query-string rebuild); this jumps to a fragment on the
	 * *same* page, and a fragment is not part of a GET form's submitted
	 * query string, so there is no server round-trip to build a form
	 * around. The link row (always in the DOM, always functional) is
	 * therefore the no-JS baseline instead: admin-settings-nav.js adds
	 * `wbam-js-enhanced` once it runs, and only then does CSS swap to the
	 * `<select>` at the narrow width — a JS-less visitor keeps seeing the
	 * (wrapping, still small) link row at every width instead of a dead
	 * control.
	 *
	 * @since 3.2.0
	 * @param array<string,string> $items Ordered map of card id (no leading
	 *                                    `#`) => short label.
	 * @return void
	 */
	public static function page_jump_nav( array $items ) {
		if ( count( $items ) < 4 ) {
			return;
		}
		?>
		<nav class="wbam-page-jump" aria-label="<?php esc_attr_e( 'On this page', 'wb-ads-rotator-with-split-test' ); ?>">
			<ul class="wbam-page-jump__list">
				<?php foreach ( $items as $id => $label ) : ?>
					<li><a href="#<?php echo esc_attr( $id ); ?>" class="wbam-page-jump__link"><?php echo esc_html( $label ); ?></a></li>
				<?php endforeach; ?>
			</ul>
			<label class="screen-reader-text" for="wbam-page-jump-select">
				<?php esc_html_e( 'On this page', 'wb-ads-rotator-with-split-test' ); ?>
			</label>
			<select id="wbam-page-jump-select" class="wbam-page-jump__select" aria-label="<?php esc_attr_e( 'On this page', 'wb-ads-rotator-with-split-test' ); ?>">
				<option value=""><?php esc_html_e( 'Jump to a card...', 'wb-ads-rotator-with-split-test' ); ?></option>
				<?php foreach ( $items as $id => $label ) : ?>
					<option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</nav>
		<?php
	}

	/**
	 * Render the action bar at the bottom of an action-screen card: a submit
	 * button (primary or danger) plus a Cancel link back to the list.
	 *
	 * @since 3.2.0
	 * @param array $args {
	 *     @type string $submit_label Required. Button text.
	 *     @type string $submit_name  Optional. Button `name` attribute.
	 *     @type string $variant      Optional. 'primary' (default) or 'danger'
	 *                                — reject/decline/delete actions use danger.
	 *     @type string $cancel_url   Required. Where Cancel goes (the list screen).
	 *     @type string $cancel_label Optional. Defaults to "Cancel".
	 * }
	 * @return string Escaped action-bar HTML.
	 */
	public static function action_bar( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'submit_label' => __( 'Save', 'wb-ads-rotator-with-split-test' ),
				'submit_name'  => '',
				'variant'      => 'primary',
				'cancel_url'   => '',
				'cancel_label' => __( 'Cancel', 'wb-ads-rotator-with-split-test' ),
			)
		);

		$btn_variant = 'danger' === $args['variant'] ? 'danger' : 'primary';

		ob_start();
		?>
		<div class="wbam-action-bar">
			<button
				type="submit"
				<?php echo '' !== $args['submit_name'] ? 'name="' . esc_attr( $args['submit_name'] ) . '"' : ''; ?>
				class="wbam-admin-btn wbam-admin-btn--<?php echo esc_attr( $btn_variant ); ?>"
			><?php echo esc_html( $args['submit_label'] ); ?></button>
			<?php if ( '' !== $args['cancel_url'] ) : ?>
				<a href="<?php echo esc_url( $args['cancel_url'] ); ?>" class="wbam-action-bar__cancel"><?php echo esc_html( $args['cancel_label'] ); ?></a>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
