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
	// Editors are told the block has no ad yet; visitors get nothing.
	echo \WBAM\Core\Ad_Status::editor_hint( 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- note() escapes its own output.
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
		$wbam_status = \WBAM\Core\Ad_Status::get( $ad_id );
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
			\WBAM\Core\Ad_Status::LIVE === $wbam_status['state']
				? __( 'Not delivering: it is live, but its display rules keep it off this page.', 'wb-ads-rotator-with-split-test' )
				/* translators: 1: status, e.g. "Not showing", 2: reason */
				: sprintf( __( '%1$s: %2$s', 'wb-ads-rotator-with-split-test' ), $wbam_status['label'], $wbam_status['reason'] ),
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
