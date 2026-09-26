<?php
/**
 * Server-side render for the `wb-ads/ad` block.
 *
 * Shares the exact rendering path as the [wbam_ad] shortcode
 * (Placement_Engine::render_ad()) - byte-identical output, one place to fix
 * ad rendering.
 *
 * @package WB_Ad_Manager
 * @since   3.2.0
 *
 * @var array<string, mixed> $attributes Block attributes.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ad_id = isset( $attributes['adId'] ) ? absint( $attributes['adId'] ) : 0;

if ( ! $ad_id ) {
	return;
}

$html = \WBAM\Modules\Placements\Placement_Engine::get_instance()->render_ad(
	$ad_id,
	array( 'placement' => 'block' )
);

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'wbam-block-ad' ) );

if ( '' === $html ) {
	// Editor preview only (the block sends wbam_preview): say why the ad
	// shows nothing instead of "Block rendered as empty". Visitors still
	// get no markup at all.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only preview flag, gated on edit_posts.
	if ( ! empty( $_GET['wbam_preview'] ) && current_user_can( 'edit_posts' ) ) {
		/**
		 * Filter the editor notice for a WB Ad block whose ad renders
		 * nothing right now.
		 *
		 * @since 3.2.0
		 * @param string $reason Notice text.
		 * @param int    $ad_id  Ad ID.
		 */
		$reason = apply_filters(
			'wbam_ad_not_delivering_reason',
			__( 'Not delivering: this ad is turned off, outside its schedule, or kept off this page by its display rules.', 'wb-ads-rotator-with-split-test' ),
			$ad_id
		);
		printf(
			'<div %1$s><p>%2$s</p></div>',
			$wrapper_attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() escapes its own output.
			esc_html( $reason )
		);
	}
	return;
}

printf(
	'<div %1$s>%2$s</div>',
	$wrapper_attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() escapes its own output.
	$html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by Placement_Engine::render_ad(), already escaped there.
);
