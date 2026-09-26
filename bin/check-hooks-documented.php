#!/usr/bin/env php
<?php
/**
 * Gate: fails if any wbam_ hook is missing a docblock summary or @param per
 * argument, or if audit/manifest.json's hooks inventory is out of sync with
 * the source (a hook in code but not in the manifest, or vice versa).
 *
 * Exit 0: clean. Exit 1: violations found (printed to stdout).
 *
 * Wired as: bin/check-hooks-documented.sh (thin wrapper around this file).
 *
 * @package WBAM
 */

require __DIR__ . '/inc/hooks-scanner.php';

use WBAM\Bin\Hooks_Scanner;

$root  = dirname( __DIR__ );
$sites = Hooks_Scanner::scan(
	$root,
	array( 'includes', 'templates', 'blocks' ),
	array( 'wb-ads-rotator-with-split-test.php' )
);

$violations = array();

// --- (a) docblock completeness -----------------------------------------
// A hook needs ONE canonical docblock somewhere (WordPress convention), not
// one per call site — the same hook commonly fires from several places
// (e.g. `wbam_save_ad_meta` after every save path). Group by name first;
// prefer the best-documented occurrence per name, same as the generator.

$by_name = array();
foreach ( $sites as $site ) {
	if ( ! isset( $by_name[ $site->name ] ) ) {
		$by_name[ $site->name ] = array();
	}
	$by_name[ $site->name ][] = $site;
}

foreach ( $by_name as $name => $group ) {
	$best = $group[0];
	foreach ( $group as $candidate ) {
		if ( $candidate->deprecated ) {
			continue 2; // Deprecated shims are documented in the Deprecated section, not per-arg.
		}
		if ( null !== $candidate->doc_summary
			&& ( null === $best->doc_summary || count( $candidate->doc_params ) > count( $best->doc_params ) )
		) {
			$best = $candidate;
		}
	}

	$max_args = max( array_map( static fn( $s ) => $s->arg_count, $group ) );
	$label    = "{$name} (e.g. {$best->file}:{$best->line})";

	if ( null === $best->doc_summary ) {
		$violations[] = "MISSING DOCBLOCK: {$label} has no docblock summary at any of its " . count( $group ) . ' call site(s).';
		continue;
	}
	if ( count( $best->doc_params ) < $max_args ) {
		$violations[] = sprintf(
			'MISSING @param: %s — the best-documented call site has %d @param tag(s) but the hook is fired with up to %d argument(s) elsewhere.',
			$label,
			count( $best->doc_params ),
			$max_args
		);
	}
}

// --- (b) manifest sync -------------------------------------------------------

$manifest_path  = $root . '/audit/manifest.json';
$manifest       = json_decode( (string) file_get_contents( $manifest_path ), true );
$manifest_names = array();
if ( is_array( $manifest ) && isset( $manifest['hooks_fired'] ) && is_array( $manifest['hooks_fired'] ) ) {
	foreach ( $manifest['hooks_fired'] as $entry ) {
		if ( isset( $entry['name'] ) ) {
			$manifest_names[ $entry['name'] ] = true;
		}
	}
}

$code_names = array();
foreach ( $sites as $site ) {
	$code_names[ $site->name ] = true;
}

foreach ( array_diff_key( $code_names, $manifest_names ) as $name => $_ ) {
	$violations[] = "MISSING FROM MANIFEST: {$name} exists in code but not in audit/manifest.json hooks.entries.";
}
foreach ( array_diff_key( $manifest_names, $code_names ) as $name => $_ ) {
	$violations[] = "ORPHANED IN MANIFEST: {$name} is listed in audit/manifest.json but no longer exists in code.";
}

// --- Report ------------------------------------------------------------------

if ( empty( $violations ) ) {
	printf( "OK: %d wbam_ hook call sites, all documented and in sync with the manifest.\n", count( $sites ) );
	exit( 0 );
}

foreach ( $violations as $v ) {
	echo $v . "\n";
}
printf( "\n%d violation(s) across %d scanned hook call sites.\n", count( $violations ), count( $sites ) );
exit( 1 );
