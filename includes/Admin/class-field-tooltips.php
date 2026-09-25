<?php
/**
 * Field-Level Tooltip Popovers
 *
 * Ships small click-to-open info popovers next to non-obvious fields
 * on the Ad edit screen. Complements WP-pointer onboarding tours by
 * giving targeted, always-available help without cluttering the
 * metabox with inline description text.
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
 * Field_Tooltips class.
 *
 * Admin templates call the static helper tip_icon() to emit a
 * standardized info trigger + popover pair next to a field label.
 * A tiny vanilla-JS enhancer toggles visibility on click, syncs
 * aria-expanded, and closes on outside-click or Escape.
 */
class Field_Tooltips {

	/**
	 * Script + style handle used for both CSS and JS.
	 *
	 * @var string
	 */
	const HANDLE = 'wbam-admin-tooltips';

	/**
	 * Monotonically increasing id counter so each tooltip gets a
	 * unique aria-describedby target even when rendered multiple
	 * times on the same screen.
	 *
	 * @var int
	 */
	private static $uid = 0;

	/**
	 * Register hooks.
	 */
	public function init() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue tooltip CSS + JS on the Ad edit screen only.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue( $hook_suffix ) {
		if ( 'post.php' !== $hook_suffix && 'post-new.php' !== $hook_suffix ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'wbam-ad' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style( 'dashicons' );

		wp_enqueue_style(
			self::HANDLE,
			WBAM_URL . 'assets/css/admin-tooltips.min.css',
			array( 'dashicons', 'wbam-admin-tokens' ),
			WBAM_VERSION
		);

		wp_enqueue_script(
			self::HANDLE,
			WBAM_URL . 'assets/js/admin-tooltips.min.js',
			array(),
			WBAM_VERSION,
			true
		);
	}

	/**
	 * Translatable copy strings keyed by a stable slug. Keeping the
	 * strings in one place keeps them grep-able and makes .pot
	 * generation straightforward.
	 *
	 * @return array<string,string>
	 */
	public static function copy() {
		return array(
			'session_limit' => __( 'Caps how many times one visitor sees this ad per browser session. 0 or empty means unlimited. Pairs with Pro advertiser-level caps if installed.', 'wb-ads-rotator-with-split-test' ),
			'priority'      => __( 'Weighted rotation: an ad with priority 10 appears twice as often as one with priority 5. Default 5 is neutral.', 'wb-ads-rotator-with-split-test' ),
			'sizing_mode'   => __( 'Responsive fits any slot and suits AdSense auto or fluid HTML. Fixed size matches only placements that accept your exact width and height.', 'wb-ads-rotator-with-split-test' ),
			'custom_dims'   => __( 'Width and height in pixels are matched against each placement exactly. Only placements that accept these dimensions will render this ad.', 'wb-ads-rotator-with-split-test' ),
		);
	}

	/**
	 * Render a standardized tooltip trigger (info icon + popover).
	 *
	 * The markup is a single inline wrapper that callers can drop
	 * right after a field's label text. The vanilla-JS enhancer
	 * handles toggling via delegated click, so templates don't need
	 * to print any per-field script.
	 *
	 * @param string $content   Helper copy. Already translated.
	 * @param string $placement Popover position hint. Accepts 'top',
	 *                          'bottom', 'left', 'right'. Defaults
	 *                          to 'top'.
	 * @return string HTML ready to echo from a template.
	 */
	public static function tip_icon( $content, $placement = 'top' ) {
		$content = (string) $content;
		if ( '' === $content ) {
			return '';
		}

		$allowed_placements = array( 'top', 'bottom', 'left', 'right' );
		if ( ! in_array( $placement, $allowed_placements, true ) ) {
			$placement = 'top';
		}

		++self::$uid;
		$pop_id = 'wbam-tip-' . self::$uid;

		$button_label = __( 'More info', 'wb-ads-rotator-with-split-test' );

		$html  = '<span class="wbam-tip" data-placement="' . esc_attr( $placement ) . '">';
		$html .= '<button type="button" class="wbam-tip__icon" aria-expanded="false" aria-describedby="' . esc_attr( $pop_id ) . '" aria-label="' . esc_attr( $button_label ) . '">';
		$html .= wbam_icon( 'help-circle', array( 'size' => 'sm' ) );
		$html .= '</button>';
		$html .= '<span class="wbam-tip__popover" id="' . esc_attr( $pop_id ) . '" role="tooltip" hidden>';
		$html .= '<span class="wbam-tip__arrow" aria-hidden="true"></span>';
		$html .= '<span class="wbam-tip__body">' . esc_html( $content ) . '</span>';
		$html .= '</span>';
		$html .= '</span>';

		return $html;
	}

	/**
	 * Convenience wrapper: look up copy by slug and render.
	 *
	 * @param string $slug      Key into the copy() map.
	 * @param string $placement Popover position hint.
	 * @return string HTML, or empty if the slug is unknown.
	 */
	public static function tip_for( $slug, $placement = 'top' ) {
		$copy = self::copy();
		if ( ! isset( $copy[ $slug ] ) ) {
			return '';
		}
		return self::tip_icon( $copy[ $slug ], $placement );
	}
}
