<?php
/**
 * Shared hook-scanning logic for generate-hooks-reference.php and
 * check-hooks-documented.php. Pure PHP tokenizer, no dependencies.
 *
 * Scans a set of directories/files for do_action()/apply_filters() (and the
 * _ref_array/_deprecated variants) calls whose first argument is (or starts
 * with) a 'wbam_' literal, and pairs each call site with the PHP docblock
 * immediately above it, if any.
 *
 * @package WBAM
 */

namespace WBAM\Bin;

/**
 * One scanned hook call site.
 */
final class Hook_Site {
	public string $name;       // Literal name, or dynamic pattern e.g. "wbam_pro_free_setting_{$key}".
	public bool $dynamic = false;
	public string $type;       // 'action' | 'filter'.
	public string $file;       // Repo-relative path.
	public int $line;
	public int $arg_count = 0; // Number of arguments passed after the hook name.
	public ?string $doc_summary = null;
	public array $doc_params = array(); // List of variable names with @param tags.
	public ?string $doc_since = null;
	public ?string $doc_return = null;
	public bool $deprecated = false;
	public ?string $deprecated_version = null;     // do_action_deprecated()'s $version arg.
	public ?string $deprecated_replacement = null; // do_action_deprecated()'s $replacement arg.
}

final class Hooks_Scanner {

	private const ACTION_FUNCS = array( 'do_action', 'do_action_ref_array', 'do_action_deprecated' );
	private const FILTER_FUNCS = array( 'apply_filters', 'apply_filters_ref_array', 'apply_filters_deprecated' );

	/**
	 * @param string   $root       Plugin root directory (absolute).
	 * @param string[] $scan_dirs  Directories relative to root to scan recursively.
	 * @param string[] $extra_files Extra individual files relative to root (e.g. the main plugin file).
	 * @return Hook_Site[]
	 */
	public static function scan( string $root, array $scan_dirs, array $extra_files = array() ): array {
		$files = array();
		foreach ( $scan_dirs as $dir ) {
			$abs = rtrim( $root, '/' ) . '/' . $dir;
			if ( ! is_dir( $abs ) ) {
				continue;
			}
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $abs, \FilesystemIterator::SKIP_DOTS )
			);
			foreach ( $iterator as $file_info ) {
				if ( $file_info->getExtension() === 'php' ) {
					$files[] = $file_info->getPathname();
				}
			}
		}
		foreach ( $extra_files as $rel ) {
			$abs = rtrim( $root, '/' ) . '/' . $rel;
			if ( is_file( $abs ) ) {
				$files[] = $abs;
			}
		}
		sort( $files );

		$sites = array();
		foreach ( $files as $abs_path ) {
			$rel_path = ltrim( str_replace( rtrim( $root, '/' ), '', $abs_path ), '/' );
			$sites    = array_merge( $sites, self::scan_file( $abs_path, $rel_path ) );
		}
		return $sites;
	}

	/**
	 * @return Hook_Site[]
	 */
	private static function scan_file( string $abs_path, string $rel_path ): array {
		$code   = file_get_contents( $abs_path );
		if ( false === $code ) {
			return array();
		}
		$tokens = token_get_all( $code );
		$count  = count( $tokens );

		$sites          = array();
		$pending_doc    = null; // [text, end_index] of the last doc comment seen.

		for ( $i = 0; $i < $count; $i++ ) {
			$token = $tokens[ $i ];

			if ( is_array( $token ) && T_DOC_COMMENT === $token[0] ) {
				$pending_doc = array( $token[1], $i );
				continue;
			}
			if ( is_array( $token ) && ( T_WHITESPACE === $token[0] || T_COMMENT === $token[0] ) ) {
				continue; // Does not break docblock adjacency.
			}

			if ( is_array( $token ) && T_STRING === $token[0] ) {
				$fname = $token[1];
				$is_action = in_array( $fname, self::ACTION_FUNCS, true );
				$is_filter = in_array( $fname, self::FILTER_FUNCS, true );

				if ( $is_action || $is_filter ) {
					$next = self::next_significant( $tokens, $i );
					if ( null !== $next && '(' === $tokens[ $next ] ) {
						$parsed = self::parse_call( $tokens, $next );
						if ( null !== $parsed && 0 === strncmp( $parsed['name'], 'wbam_', 5 ) ) {
							$site             = new Hook_Site();
							$site->name       = $parsed['name'];
							$site->dynamic    = $parsed['dynamic'];
							$site->type       = $is_action ? 'action' : 'filter';
							$site->file       = $rel_path;
							$site->line       = $token[2];
							$site->arg_count  = $parsed['arg_count'];
							$site->deprecated = ( false !== strpos( $fname, '_deprecated' ) );
							if ( $site->deprecated ) {
								// do_action_deprecated()/apply_filters_deprecated() shape:
								// ( $hook, $args, $version, $replacement ). Args 2 and 3
								// (0-indexed, hook name is arg 0) are always bare strings.
								$site->deprecated_version     = $parsed['arg_literals'][2] ?? null;
								$site->deprecated_replacement = $parsed['arg_literals'][3] ?? null;
							}

							// A docblock documents the hook call if it opens the same
							// statement (e.g. "/** doc */\n$x = apply_filters(...)" or
							// "/** doc */\nreturn (bool) apply_filters(...)"), even
							// though the call token itself isn't textually adjacent to
							// the doc comment. We only drop $pending_doc at a real
							// statement/block boundary (see below), so if it's still
							// set here it belongs to the statement containing this call.
							if ( null !== $pending_doc ) {
								self::apply_doc( $site, $pending_doc[0] );
								// Consumed: don't let the same docblock also attach to a
								// second hook call further down the same statement (e.g.
								// nested apply_filters() calls building one array).
								$pending_doc = null;
							}
							$sites[] = $site;
						}
					}
				}
			}

			// Reset the pending docblock only at a statement/block boundary.
			// Anything else (assignment operators, casts like "(array)",
			// "return", variable names, etc.) is part of the same statement
			// the docblock introduces and must not break the association.
			$val = is_array( $token ) ? $token[1] : $token;
			if ( in_array( $val, array( ';', '{', '}' ), true ) ) {
				$pending_doc = null;
			}
		}

		return $sites;
	}

	private static function next_significant( array $tokens, int $from ): ?int {
		for ( $j = $from + 1; $j < count( $tokens ); $j++ ) {
			$t = $tokens[ $j ];
			if ( is_array( $t ) && ( T_WHITESPACE === $t[0] || T_COMMENT === $t[0] ) ) {
				continue;
			}
			return $j;
		}
		return null;
	}

	/**
	 * Parses a call starting at the '(' token index. Reconstructs the first
	 * argument (hook name, possibly built from concatenation/interpolation),
	 * counts the remaining top-level arguments, and records any top-level
	 * argument that is a bare string literal (used to read the $version and
	 * $replacement arguments of a do_action_deprecated()/apply_filters_deprecated()
	 * call, which are always plain strings).
	 *
	 * @return array{name:string,dynamic:bool,arg_count:int,arg_literals:array<int,string>}|null
	 */
	private static function parse_call( array $tokens, int $paren_index ): ?array {
		$depth      = 0;
		$name_parts = array();
		$dynamic    = false;
		$commas_at_depth1 = 0;
		$building_first_arg = true;
		$arg_index    = 0; // 0-based position of the argument currently being scanned.
		$arg_literals = array();

		for ( $j = $paren_index; $j < count( $tokens ); $j++ ) {
			$t = $tokens[ $j ];
			$val = is_array( $t ) ? $t[1] : $t;

			if ( '(' === $val ) {
				$depth++;
				if ( 1 === $depth ) {
					continue; // Opening paren of the call itself.
				}
			} elseif ( ')' === $val ) {
				$depth--;
				if ( 0 === $depth ) {
					break; // End of call.
				}
			} elseif ( ',' === $val && 1 === $depth ) {
				$commas_at_depth1++;
				$building_first_arg = false;
				++$arg_index;
				continue;
			}

			if ( $building_first_arg && 1 === $depth ) {
				if ( is_array( $t ) && T_CONSTANT_ENCAPSED_STRING === $t[0] ) {
					$literal = trim( $t[1], "'\"" );
					if ( false !== strpos( $t[1], '"' ) && false !== strpos( $literal, '$' ) ) {
						$dynamic = true;
						$literal = preg_replace( '/\$\{?([a-zA-Z_][a-zA-Z0-9_]*)\}?/', '{$$1}', $literal );
					}
					$name_parts[] = $literal;
				} elseif ( is_array( $t ) && T_VARIABLE === $t[0] ) {
					$dynamic      = true;
					$name_parts[] = '{' . $t[1] . '}';
				} elseif ( '.' === $val ) {
					continue; // Concatenation operator, no output needed.
				}
			} elseif ( 1 === $depth && is_array( $t ) && T_CONSTANT_ENCAPSED_STRING === $t[0] ) {
				$arg_literals[ $arg_index ] = trim( $t[1], "'\"" );
			}
		}

		if ( empty( $name_parts ) ) {
			return null;
		}

		return array(
			'name'         => implode( '', $name_parts ),
			'dynamic'      => $dynamic,
			'arg_count'    => $commas_at_depth1, // First arg is the hook name; remaining args = commas at depth 1.
			'arg_literals' => $arg_literals,
		);
	}

	/**
	 * Parses a /** ... *\/ docblock into summary/@param/@since/@return.
	 */
	private static function apply_doc( Hook_Site $site, string $raw ): void {
		$lines   = preg_split( '/\R/', $raw );
		$summary = array();
		foreach ( $lines as $line ) {
			$line = trim( $line );
			$line = preg_replace( '#^/?\*+/?#', '', $line );
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			if ( 0 === strpos( $line, '@param' ) ) {
				// Type is everything between "@param" and the variable name,
				// non-greedy so it stops at the FIRST "$var" — needed because
				// this codebase's array shape types commonly contain a space
				// after the comma, e.g. "array<string, int>".
				if ( preg_match( '/@param\s+.+?\s+(\$\w+)/', $line, $m ) ) {
					$site->doc_params[] = $m[1];
				}
			} elseif ( 0 === strpos( $line, '@since' ) ) {
				$site->doc_since = trim( substr( $line, 6 ) );
			} elseif ( 0 === strpos( $line, '@return' ) ) {
				$site->doc_return = trim( substr( $line, 7 ) );
			} elseif ( 0 === strpos( $line, '@deprecated' ) ) {
				$site->deprecated = true;
			} elseif ( 0 === strpos( $line, '@' ) ) {
				continue; // Other tags, ignored for this report.
			} else {
				$summary[] = $line;
			}
		}
		if ( ! empty( $summary ) ) {
			$site->doc_summary = implode( ' ', $summary );
		}
	}
}
