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
	 *     @type string $title   Required. Page title (escaped here).
	 *     @type string $desc    Optional. One-line subtitle.
	 *     @type string $actions Optional. Pre-escaped HTML for the right side
	 *                           (e.g. an "Add New" button). Caller escapes.
	 *     @type bool   $echo    Optional. Echo (default true) or return.
	 * }
	 * @return string HTML when $echo is false, else empty string.
	 */
	public static function page_header( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'title'   => '',
				'desc'    => '',
				'actions' => '',
				'echo'    => true,
			)
		);

		ob_start();
		?>
		<div class="wbam-page-header">
			<div class="wbam-page-header__left">
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
				'title'   => __( 'Nothing here yet', 'wb-ads-rotator-with-split-test' ),
				'message' => '',
				'actions' => '',
			)
		);

		ob_start();
		?>
		<div class="wbam-empty-state">
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
}
