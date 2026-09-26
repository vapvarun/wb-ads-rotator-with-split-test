#!/usr/bin/env php
<?php
/**
 * Regenerates the "Hooks and Filters" developer-guide reference and the
 * hooks inventory in audit/manifest.json from source docblocks.
 *
 * Scans includes/ and the main plugin file for do_action()/apply_filters()
 * (+ _ref_array/_deprecated variants) calls whose hook name starts with
 * 'wbam_', reads the docblock immediately above each call site, and writes:
 *
 *   1. docs/website/developer-guide/10-hooks-and-filters.md — replaces the
 *      block between the BEGIN/END GENERATED markers. Everything outside the
 *      markers (intro prose, recipes, deprecated section) is preserved.
 *   2. audit/manifest.json — replaces the "hooks" key with the current
 *      inventory (name, type, file, line, dynamic flag, documented flag).
 *
 * Usage:
 *   php bin/generate-hooks-reference.php          Write both files.
 *   php bin/generate-hooks-reference.php --check   Exit 1 if either file
 *                                                   would change; write nothing.
 *
 * @package WBAM
 */

require __DIR__ . '/inc/hooks-scanner.php';

use WBAM\Bin\Hooks_Scanner;

$root      = dirname( __DIR__ );
$check_only = in_array( '--check', $argv, true );

$sites = Hooks_Scanner::scan(
	$root,
	array( 'includes', 'templates', 'blocks' ),
	array( 'wb-ads-rotator-with-split-test.php' )
);

// Dedupe by hook name, keeping the first documented occurrence if any is
// documented, else the first occurrence.
$by_name = array();
foreach ( $sites as $site ) {
	$key = $site->name;
	if ( ! isset( $by_name[ $key ] ) ) {
		$by_name[ $key ] = array( 'best' => $site, 'sites' => array() );
	}
	$by_name[ $key ]['sites'][] = $site;
	if ( null !== $site->doc_summary && null === $by_name[ $key ]['best']->doc_summary ) {
		$by_name[ $key ]['best'] = $site;
	}
}
ksort( $by_name );

// --- Build markdown tables -------------------------------------------------

$actions_documented   = array();
$filters_documented   = array();
$actions_undocumented = array();
$filters_undocumented = array();
$deprecated_rows      = array();

foreach ( $by_name as $name => $group ) {
	$best = $group['best'];

	// A hook name that ONLY ever fires via do_action_deprecated()/
	// apply_filters_deprecated() is a deprecated alias for a replacement
	// hook, not an undocumented one — list it separately with its
	// replacement and version instead of the doc/no-doc tables.
	$all_deprecated = true;
	foreach ( $group['sites'] as $s ) {
		if ( ! $s->deprecated ) {
			$all_deprecated = false;
			break;
		}
	}
	if ( $all_deprecated ) {
		$dep               = $group['sites'][0];
		$deprecated_rows[] = array(
			'name'        => $name,
			'type'        => $dep->type,
			'version'     => $dep->deprecated_version ?? '?',
			'replacement' => $dep->deprecated_replacement ?? '?',
			'where'       => $dep->file . ':' . $dep->line,
		);
		continue;
	}

	$row = array(
		'name'  => $name,
		'args'  => implode( ', ', $best->doc_params ),
		'desc'  => $best->doc_summary ?? '',
		'where' => $best->file . ':' . $best->line,
	);
	if ( 'action' === $best->type ) {
		if ( null !== $best->doc_summary ) {
			$actions_documented[] = $row;
		} else {
			$actions_undocumented[] = $row;
		}
	} elseif ( null !== $best->doc_summary ) {
			$filters_documented[] = $row;
	} else {
			$filters_undocumented[] = $row;
	}
}
usort( $deprecated_rows, static fn( $a, $b ) => $a['name'] <=> $b['name'] );

function table( array $rows, array $headers ) {
	$out   = '| ' . implode( ' | ', $headers ) . " |\n";
	$out  .= '|' . str_repeat( '---|', count( $headers ) ) . "\n";
	foreach ( $rows as $r ) {
		$out .= '| `' . $r['name'] . '` | ' . ( $r['args'] !== '' ? $r['args'] : '-' ) . ' | ' . $r['desc'] . " |\n";
	}
	return $out;
}

function name_list( array $rows ) {
	if ( empty( $rows ) ) {
		return "_None._\n";
	}
	$out = '';
	foreach ( $rows as $r ) {
		$out .= '- `' . $r['name'] . '` (' . $r['where'] . ")\n";
	}
	return $out;
}

/**
 * Renders the Deprecated table: old hook name, type, its replacement, and
 * the version it was deprecated in — sourced from the do_action_deprecated()/
 * apply_filters_deprecated() call itself, not a docblock (these shims
 * intentionally carry no @since/@param; they exist only to keep old
 * listeners firing with a core _doing_it_wrong()-style notice).
 */
function deprecated_table( array $rows ) {
	if ( empty( $rows ) ) {
		return "_None currently deprecated._\n";
	}
	$out  = "| Old hook | Type | Replacement | Deprecated in |\n";
	$out .= "|---|---|---|---|\n";
	foreach ( $rows as $r ) {
		$out .= '| `' . $r['name'] . '` | ' . $r['type'] . ' | `' . $r['replacement'] . '` | ' . $r['version'] . " |\n";
	}
	return $out;
}

$generated  = "<!-- BEGIN GENERATED HOOKS REFERENCE — DO NOT EDIT BY HAND. Run: php bin/generate-hooks-reference.php -->\n\n";
$generated .= "### Actions (documented)\n\n" . table( $actions_documented, array( 'Hook', 'Arguments', 'Fires when' ) ) . "\n";
$generated .= "### Filters (documented)\n\n" . table( $filters_documented, array( 'Hook', 'Arguments', 'Filters' ) ) . "\n";
$generated .= "### Not yet documented\n\nThese hooks exist in the code but have no docblock summary yet. Run `bash bin/check-hooks-documented.sh` to see the full gate report; add a docblock at the call site and re-run the generator to move an entry into the tables above.\n\n";
$generated .= "**Actions:**\n\n" . name_list( $actions_undocumented ) . "\n";
$generated .= "**Filters:**\n\n" . name_list( $filters_undocumented ) . "\n";
$generated .= "### Deprecated\n\nA hook below still fires (existing listeners keep working, with a core `_doing_it_wrong()`-style notice) but should be moved to its replacement — the old name is removed after a full minor-version cycle.\n\n" . deprecated_table( $deprecated_rows ) . "\n";
$generated .= "<!-- END GENERATED HOOKS REFERENCE -->\n";

// --- Merge into the doc file -----------------------------------------------

$doc_path = $root . '/docs/website/developer-guide/10-hooks-and-filters.md';
$existing = is_file( $doc_path ) ? file_get_contents( $doc_path ) : "# Hooks and Filters\n\n<!-- BEGIN GENERATED HOOKS REFERENCE — DO NOT EDIT BY HAND. Run: php bin/generate-hooks-reference.php -->\n<!-- END GENERATED HOOKS REFERENCE -->\n";

$begin = '<!-- BEGIN GENERATED HOOKS REFERENCE — DO NOT EDIT BY HAND. Run: php bin/generate-hooks-reference.php -->';
$end   = '<!-- END GENERATED HOOKS REFERENCE -->';

if ( false !== strpos( $existing, $begin ) && false !== strpos( $existing, $end ) ) {
	$pattern = '/' . preg_quote( $begin, '/' ) . '.*?' . preg_quote( $end, '/' ) . '/s';
	$new_doc = preg_replace( $pattern, rtrim( $generated ), $existing );
} else {
	$new_doc = rtrim( $existing ) . "\n\n" . $generated;
}

// --- Merge hooks inventory into manifest.json -------------------------------

$manifest_path = $root . '/audit/manifest.json';
$manifest_json = file_get_contents( $manifest_path );
$manifest      = json_decode( $manifest_json, true );
if ( ! is_array( $manifest ) ) {
	fwrite( STDERR, "Could not parse audit/manifest.json\n" );
	exit( 2 );
}

// manifest.json already carries a hand-maintained "hooks_fired" array
// (schema: name/type/args/where/purpose) — merge into it rather than adding
// a parallel key. Existing "purpose" text (often hand-written) is preserved;
// only "type"/"args"/"where" are kept in sync with the source, and a
// "purpose" is filled in from the source docblock only when the existing
// entry has none.
$existing_hooks   = ( isset( $manifest['hooks_fired'] ) && is_array( $manifest['hooks_fired'] ) ) ? $manifest['hooks_fired'] : array();
$existing_by_name = array();
foreach ( $existing_hooks as $idx => $entry ) {
	if ( isset( $entry['name'] ) ) {
		$existing_by_name[ $entry['name'] ] = $idx;
	}
}

$new_hooks_fired = array();
foreach ( $by_name as $name => $group ) {
	$best     = $group['best'];
	$max_args = max( array_map( static fn( $s ) => $s->arg_count, $group['sites'] ) );
	$where    = $best->file . ':' . $best->line;

	if ( isset( $existing_by_name[ $name ] ) ) {
		$entry           = $existing_hooks[ $existing_by_name[ $name ] ];
		$entry['type']   = $best->type;
		$entry['args']   = $max_args;
		if ( empty( $entry['where'] ) ) {
			$entry['where'] = $where;
		}
		if ( empty( $entry['purpose'] ) && null !== $best->doc_summary ) {
			$entry['purpose'] = $best->doc_summary;
		}
	} else {
		$entry = array(
			'name'    => $name,
			'type'    => $best->type,
			'args'    => $max_args,
			'where'   => $where,
			'purpose' => $best->doc_summary ?? '',
		);
	}
	if ( $best->dynamic ) {
		$entry['dynamic'] = true;
	}
	if ( $best->deprecated ) {
		$entry['deprecated']    = true;
		$entry['replaced_by']   = $best->deprecated_replacement ?? '';
		$entry['deprecated_in'] = $best->deprecated_version ?? '';
	}
	$new_hooks_fired[ $name ] = $entry;
}
ksort( $new_hooks_fired );
$manifest['hooks_fired'] = array_values( $new_hooks_fired );

/**
 * json_encode re-indented to 2 spaces, matching manifest.json's existing
 * style (PHP's JSON_PRETTY_PRINT is hardcoded to 4 spaces per level, which
 * would otherwise reindent every line and bury the real diff).
 */
function wp_json_pretty( array $data ): string {
	$encoded = json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	$lines   = explode( "\n", $encoded );
	foreach ( $lines as &$line ) {
		if ( preg_match( '/^( +)/', $line, $m ) ) {
			$level = strlen( $m[1] ) / 4;
			$line  = str_repeat( '  ', (int) $level ) . substr( $line, strlen( $m[1] ) );
		}
	}
	unset( $line );
	return implode( "\n", $lines ) . "\n";
}
$new_manifest_json = wp_json_pretty( $manifest );

// --- Write or check ---------------------------------------------------------

$doc_changed      = $new_doc !== $existing;
$manifest_changed = $new_manifest_json !== $manifest_json;

if ( $check_only ) {
	if ( $doc_changed || $manifest_changed ) {
		fwrite( STDERR, "Hooks reference is stale. Run: php bin/generate-hooks-reference.php\n" );
		exit( 1 );
	}
	echo "Hooks reference is up to date.\n";
	exit( 0 );
}

if ( $doc_changed ) {
	file_put_contents( $doc_path, $new_doc );
}
if ( $manifest_changed ) {
	file_put_contents( $manifest_path, $new_manifest_json );
}

printf(
	"Scanned %d unique wbam_ hooks (%d documented, %d not yet documented).\n",
	count( $by_name ),
	count( $actions_documented ) + count( $filters_documented ),
	count( $actions_undocumented ) + count( $filters_undocumented )
);
