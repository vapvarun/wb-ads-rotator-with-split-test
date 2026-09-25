<?php
/**
 * Server-side render for the `wb-ads/placement` block.
 *
 * Shares the same rendering path as Before/After Archive Placement
 * (Placement_Engine::render_placement()) - one place decides what a
 * placement slot outputs, whether it's reached via a PHP hook, the
 * block-safe render_block_core/query filter, or this block.
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

$placement_id = isset( $attributes['placementId'] ) ? sanitize_key( $attributes['placementId'] ) : '';

if ( '' === $placement_id ) {
	return;
}

$html = \WBAM\Modules\Placements\Placement_Engine::get_instance()->render_placement( $placement_id );

if ( '' === $html ) {
	return;
}

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'wbam-block-placement' ) );

printf(
	'<div %1$s>%2$s</div>',
	$wrapper_attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() escapes its own output.
	$html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by Placement_Engine::render_placement(), already escaped there.
);
