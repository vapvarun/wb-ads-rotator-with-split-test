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

if ( '' === $html ) {
	return;
}

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'wbam-block-ad' ) );

printf(
	'<div %1$s>%2$s</div>',
	$wrapper_attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() escapes its own output.
	$html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by Placement_Engine::render_ad(), already escaped there.
);
