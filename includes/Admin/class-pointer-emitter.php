<?php
/**
 * Shared wp-pointer emitter.
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
 * Builds and enqueues the wp-pointer walkthrough script.
 *
 * Both this plugin and Pro ship onboarding pointers, and both had their own
 * copy of the same twenty lines of inline JavaScript that drives them. The two
 * copies had already drifted: Pro passed an explicit admin-ajax URL because the
 * `ajaxurl` global does not exist on the frontend, and used `.first()` so a
 * selector matching several nodes could not open a pointer per node. This
 * plugin's copy had neither, which is latent rather than harmless - it is
 * admin-only today, and would break the moment a pointer was pointed at
 * anything on the frontend.
 *
 * One emitter, carrying the better of the two behaviours. Callers keep their own
 * pointer definitions, their own AJAX action and their own dismissal meta -
 * those are genuinely per-plugin and should not be shared.
 *
 * @since 3.0.0
 */
final class Pointer_Emitter {

	/**
	 * Enqueue the walkthrough for a set of pointers.
	 *
	 * @since 3.0.0
	 * @param array<string, array<string, string>> $pointers Slug => { target,
	 *                              title, content, edge, align }.
	 * @param string $ajax_action   Action name the dismissal posts to. Also used
	 *                              as the nonce action.
	 * @param string $pointer_class Extra class on the pointer element, so each
	 *                              plugin can style its own.
	 * @return void
	 */
	public static function emit( array $pointers, $ajax_action, $pointer_class ) {
		if ( empty( $pointers ) ) {
			return;
		}

		wp_enqueue_style( 'wp-pointer' );
		wp_enqueue_script( 'wp-pointer' );
		wp_enqueue_script( 'jquery' );

		// Core's default pointer width (320px) is barely wider than the
		// sidebar metaboxes these pointers target (Ad Sizing, Package
		// Allowed Formats), so a couple of sentences of guidance wrap into
		// a box tall enough to cover the fields underneath it. Widen it so
		// the same copy needs fewer lines.
		wp_add_inline_style( 'wp-pointer', '.wp-pointer{width:420px;max-width:90vw;}' );

		$inline  = 'jQuery(function($){';
		$inline .= 'var pointers = ' . wp_json_encode( $pointers ) . ';';
		$inline .= 'var ajaxAction = ' . wp_json_encode( $ajax_action ) . ';';
		$inline .= 'var nonce = ' . wp_json_encode( wp_create_nonce( $ajax_action ) ) . ';';
		// Explicit rather than relying on the `ajaxurl` global, which WordPress
		// only defines in the admin. A frontend pointer would post to undefined.
		$inline .= 'var ajaxUrl = ' . wp_json_encode( admin_url( 'admin-ajax.php' ) ) . ';';
		$inline .= 'function showNext(keys){';
		$inline .= 'if(!keys.length){return;}';
		$inline .= 'var slug = keys.shift();';
		$inline .= 'var p = pointers[slug];';
		$inline .= 'if(!p){showNext(keys);return;}';
		$inline .= 'var $t = $(p.target);';
		$inline .= 'if(!$t.length){showNext(keys);return;}';
		// .first() so a selector that matches several nodes opens one pointer,
		// not one per node.
		$inline .= '$t.first().pointer({';
		$inline .= 'content: "<h3>" + p.title + "</h3><p>" + p.content + "</p>",';
		$inline .= 'position: { edge: p.edge || "top", align: p.align || "center" },';
		// E2 fix: the 420px CSS default (set below for wide content-area
		// targets like the Sizing section) overflows a narrow sidebar
		// target sitting close to the viewport's right edge, clipping the
		// pointer's own Close button off-screen. A definition can opt into
		// a narrower box via `width`; wp-pointer.js applies `pointerWidth`
		// as an inline style, which wins over the shared class rule below.
		$inline .= 'pointerWidth: p.width || 420,';
		$inline .= 'pointerClass: ' . wp_json_encode( 'wp-pointer ' . $pointer_class ) . ',';
		$inline .= 'close: function(){';
		$inline .= '$.post(ajaxUrl, { action: ajaxAction, pointer: slug, _ajax_nonce: nonce });';
		$inline .= 'showNext(keys);';
		$inline .= '}';
		$inline .= '}).pointer("open");';
		$inline .= '}';
		$inline .= 'showNext(Object.keys(pointers));';
		$inline .= '});';

		wp_add_inline_script( 'wp-pointer', $inline );
	}
}
