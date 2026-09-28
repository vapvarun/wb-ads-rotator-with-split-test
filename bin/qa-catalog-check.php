<?php
/**
 * Functionality catalog check: every surface the running site registers for
 * WB Ad Manager (Free + Pro) must be listed in that plugin's
 * docs/qa/FUNCTIONALITY_CATALOG.md, and every surface the catalog lists must
 * still exist. Run on a site with both plugins active, before each release:
 *
 * Usage:
 *   wp --exec='define("WP_ADMIN", true);' eval-file bin/qa-catalog-check.php        # report, exit 1 on gaps
 *   wp --exec='define("WP_ADMIN", true);' eval-file bin/qa-catalog-check.php list   # print every surface
 *
 * WP_ADMIN makes both plugins load their admin classes (menus, admin AJAX);
 * admin_init is never fired, so no redirects, saves or upgrades run.
 *
 * Surfaces are read from the live registries, not grepped, so constants and
 * loops resolve: REST routes, admin screens, settings sections, editor meta
 * boxes, dashboard widgets, shortcodes, blocks, widgets, abilities, AJAX and
 * admin-post handlers, URL routes, cron jobs, post types, taxonomies, roles,
 * placements, ad types, emails, template files, payment gateways, purchase
 * adapters, and personal data exporters and erasers. Each is attributed to Free or Pro by its callback's file.
 * A catalog lists a surface as `kind:id` in backticks, e.g. `rest:/wbam/v1/ads`.
 *
 * Read-only. Loads the admin menu as user 1 in memory; writes nothing.
 *
 * @package WBAM
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.PHP.DevelopmentFunctions, WordPress.Security.EscapeOutput, WordPress.NamingConventions.PrefixAllGlobals, WordPress.WP.AlternativeFunctions -- dev CLI tool, not shipped (.distignore /bin).

if ( ! defined( 'WP_CLI' ) ) {
	exit( 1 );
}

if ( ! is_admin() ) {
	WP_CLI::error( 'Run with the admin context so admin screens register: wp --exec=\'define("WP_ADMIN", true);\' eval-file bin/qa-catalog-check.php' );
}

$wbam_plugins = array(
	'free' => WP_PLUGIN_DIR . '/wb-ads-rotator-with-split-test',
	'pro'  => WP_PLUGIN_DIR . '/wb-ad-manager-pro',
);
$wbam_list    = in_array( 'list', $args ?? array(), true );

// Every surface kind a catalog entry may name.
const WBAM_QA_KINDS = 'rest|admin|settings|metabox|dashboard|shortcode|block|widget|ability|ajax|post|route|cron|cpt|tax|role|placement|adtype|email|template|gateway|adapter|privacy';

/** Which plugin a callable lives in, as array( owner, file:line ). */
$wbam_owner = static function ( $cb ) use ( $wbam_plugins ) {
	try {
		if ( is_string( $cb ) && str_contains( $cb, '::' ) ) {
			$cb = explode( '::', $cb, 2 );
		}
		if ( is_array( $cb ) && isset( $cb[0], $cb[1] ) ) {
			$r = new ReflectionMethod( $cb[0], $cb[1] );
		} elseif ( $cb instanceof Closure || is_string( $cb ) ) {
			$r = new ReflectionFunction( $cb );
		} elseif ( is_object( $cb ) && method_exists( $cb, '__invoke' ) ) {
			$r = new ReflectionMethod( $cb, '__invoke' );
		} else {
			return null;
		}
	} catch ( ReflectionException $e ) {
		return null;
	}
	$file = (string) $r->getFileName();
	foreach ( $wbam_plugins as $who => $dir ) {
		if ( str_starts_with( $file, $dir . '/' ) ) {
			return array( $who, substr( $file, strlen( $dir ) + 1 ) . ':' . $r->getStartLine() );
		}
	}
	return null;
};

// Maps each "kind:id" to its owner (free or pro) and file:line.
$wbam_found = array();
$wbam_add   = static function ( $key, $owner ) use ( &$wbam_found ) {
	if ( $owner && ! isset( $wbam_found[ $key ] ) ) {
		$wbam_found[ $key ] = $owner;
	}
};

// Some surfaces have no plugin callback to reflect on (post types, taxonomies,
// admin pages rendered from admin_init). Those belong to the plugin whose
// source quotes the name next to the call that registers it.
$wbam_src = array();
foreach ( $wbam_plugins as $who => $dir ) {
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir . '/includes', FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		if ( 'php' === $f->getExtension() ) {
			$wbam_src[] = array( $who, substr( $f->getPathname(), strlen( $dir ) + 1 ), (string) file_get_contents( $f->getPathname() ) );
		}
	}
}
$wbam_by_source = static function ( $name, $call ) use ( $wbam_src ) {
	foreach ( $wbam_src as $src ) {
		if ( str_contains( $src[2], "'" . $name . "'" ) && str_contains( $src[2], $call ) ) {
			return array( $src[0], $src[1] );
		}
	}
	return null;
};

// REST routes: attributed by their first endpoint's callback.
foreach ( rest_get_server()->get_routes() as $route => $endpoints ) {
	foreach ( $endpoints as $ep ) {
		if ( isset( $ep['callback'] ) ) {
			$wbam_add( 'rest:' . $route, $wbam_owner( $ep['callback'] ) );
			break;
		}
	}
}

// Admin menu pages, as the admin sees them.
wp_set_current_user( 1 );
set_current_screen( 'dashboard' );
require_once ABSPATH . 'wp-admin/includes/admin.php';
global $menu, $submenu, $_registered_pages, $wp_filter;
$wbam_settings = null;
$menu    = array();
$submenu = array();
do_action( 'admin_menu' );
foreach ( array_keys( (array) $_registered_pages ) as $hook ) {
	if ( ! preg_match( '/_page_(.+)$/', $hook, $m ) ) {
		continue;
	}
	foreach ( $wp_filter[ $hook ]->callbacks ?? array() as $cbs ) {
		foreach ( $cbs as $cb ) {
			$wbam_add( 'admin:' . $m[1], $wbam_owner( $cb['function'] ) );
			if ( is_array( $cb['function'] ) && $cb['function'][0] instanceof WBAM\Admin\Settings ) {
				$wbam_settings = $cb['function'][0];
			}
		}
	}
	if ( str_starts_with( $m[1], 'wbam' ) ) {
		$wbam_add( 'admin:' . $m[1], $wbam_by_source( $m[1], '_page(' ) );
	}
}

// Settings sections (Free's registry plus what Pro adds through the filter).
if ( $wbam_settings ) {
	$sections = new ReflectionMethod( $wbam_settings, 'get_sections' );
	$sections->setAccessible( true );
	foreach ( (array) $sections->invoke( $wbam_settings ) as $key => $section ) {
		$wbam_add( 'settings:' . $key, $wbam_owner( $section['render'] ?? null ) );
	}
}

// Editor meta boxes for the plugins' post types. Registration only: the
// default post is built in memory, never saved.
global $wp_meta_boxes;
foreach ( array( 'wbam-ad', 'wbam-classified' ) as $pt ) {
	if ( ! post_type_exists( $pt ) ) {
		continue;
	}
	set_current_screen( $pt );
	$draft = get_default_post_to_edit( $pt, false );
	do_action( 'add_meta_boxes', $pt, $draft );
	do_action( "add_meta_boxes_{$pt}", $draft );
	foreach ( (array) ( $wp_meta_boxes[ $pt ] ?? array() ) as $contexts ) {
		foreach ( $contexts as $boxes ) {
			foreach ( (array) $boxes as $id => $box ) {
				if ( $box ) {
					$wbam_add( 'metabox:' . $pt . '/' . $id, $wbam_owner( $box['callback'] ) );
				}
			}
		}
	}
}

// Dashboard widgets.
require_once ABSPATH . 'wp-admin/includes/dashboard.php';
set_current_screen( 'dashboard' );
$wp_meta_boxes = array();
do_action( 'wp_dashboard_setup' );
foreach ( (array) ( $wp_meta_boxes['dashboard'] ?? array() ) as $contexts ) {
	foreach ( $contexts as $boxes ) {
		foreach ( (array) $boxes as $id => $box ) {
			if ( $box ) {
				$wbam_add( 'dashboard:' . $id, $wbam_owner( $box['callback'] ) );
			}
		}
	}
}

// Abilities API (WordPress 6.9): owned by name prefix.
if ( function_exists( 'wp_get_abilities' ) ) {
	foreach ( wp_get_abilities() as $ability ) {
		$name = $ability->get_name();
		$who  = str_starts_with( $name, 'wbam-pro/' ) ? 'pro' : ( str_starts_with( $name, 'wbam/' ) ? 'free' : '' );
		$wbam_add( 'ability:' . $name, $who ? array( $who, 'abilities' ) : null );
	}
}

// Custom URL routes (add_rewrite_rule), keyed by query var because the URL
// prefix is a setting. Post type and taxonomy archives are covered by cpt/tax.
global $wp_rewrite;
foreach ( (array) $wp_rewrite->extra_rules_top as $query ) {
	if ( preg_match( '/[?&](wbam[\w-]*)=/', (string) $query, $q ) && ! post_type_exists( $q[1] ) && ! taxonomy_exists( $q[1] ) ) {
		$wbam_add( 'route:' . $q[1], $wbam_by_source( $q[1], 'add_rewrite_rule' ) );
	}
}

// Roles.
foreach ( array_keys( wp_roles()->roles ) as $role ) {
	if ( str_starts_with( $role, 'wbam' ) ) {
		$wbam_add( 'role:' . $role, $wbam_by_source( $role, 'add_role' ) );
	}
}

// Payment gateways and purchase adapters of Pro's bundled Credits SDK.
if ( class_exists( 'Wbcom\\Credits\\Gateways\\Gateway_Registry' ) ) {
	foreach ( array_keys( Wbcom\Credits\Gateways\Gateway_Registry::for_slug( 'wbam-pro' )->get_all() ) as $id ) {
		$wbam_add( 'gateway:' . $id, array( 'pro', 'libs/wbcom-credits-sdk' ) );
	}
	// The adapter registry boots straight away (no hook to find it on), so
	// list the adapters by interface; the SDK ships one class per store.
	foreach ( get_declared_classes() as $class ) {
		if ( is_subclass_of( $class, 'Wbcom\\Credits\\Adapters\\AdapterInterface' ) ) {
			$wbam_add( 'adapter:' . ( new ReflectionClass( $class ) )->getShortName(), array( 'pro', 'libs/wbcom-credits-sdk' ) );
		}
	}
}

// Personal data exporters and erasers (Tools > Export / Erase Personal Data).
foreach ( array( 'export' => 'wp_privacy_personal_data_exporters', 'erase' => 'wp_privacy_personal_data_erasers' ) as $verb => $filter ) {
	foreach ( (array) apply_filters( $filter, array() ) as $key => $tool ) {
		$wbam_add( 'privacy:' . $verb . '/' . $key, $wbam_owner( $tool['callback'] ?? null ) );
	}
}

// Template files (the presentation layer). A catalog may cover a folder with
// a pattern such as `template:emails/*`.
foreach ( $wbam_plugins as $who => $dir ) {
	if ( ! is_dir( $dir . '/templates' ) ) {
		continue;
	}
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir . '/templates', FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		if ( 'php' === $f->getExtension() ) {
			$rel = substr( $f->getPathname(), strlen( $dir . '/templates/' ) );
			$wbam_add( 'template:' . $rel, array( $who, 'templates/' . $rel ) );
		}
	}
}

// Shortcodes, blocks, widgets.
global $shortcode_tags, $wp_widget_factory;
foreach ( $shortcode_tags as $tag => $cb ) {
	$wbam_add( 'shortcode:' . $tag, $wbam_owner( $cb ) );
}
foreach ( WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $type ) {
	// block.json renders are wrapped in a core closure; fall back to the namespace.
	$owner = $type->render_callback ? $wbam_owner( $type->render_callback ) : null;
	$ns    = strtok( $name, '/' );
	$wbam_add( 'block:' . $name, $owner ?? ( 'wb-ads' === $ns ? array( 'free', 'block.json' ) : ( 'wbam-pro' === $ns ? array( 'pro', 'block.json' ) : null ) ) );
}
foreach ( $wp_widget_factory->widgets as $class => $widget ) {
	$wbam_add( 'widget:' . $widget->id_base, $wbam_owner( array( $widget, 'widget' ) ) );
}

// AJAX actions and cron hooks: any hook with a callback in our plugins.
$wbam_cron = array();
foreach ( (array) _get_cron_array() as $events ) {
	$wbam_cron += array_fill_keys( array_keys( $events ), true );
}
foreach ( $wp_filter as $hook => $obj ) {
	$ajax = preg_match( '/^(wp_ajax|admin_post)_(?:nopriv_)?(.+)$/', $hook, $m );
	$cron = isset( $wbam_cron[ $hook ] ) || preg_match( '/^wbam.*(_cron|_cleanup|_aggregation|_backfill|_reports|_migration)$/', $hook );
	if ( ! $ajax && ! $cron ) {
		continue;
	}
	foreach ( $obj->callbacks as $cbs ) {
		foreach ( $cbs as $cb ) {
			$wbam_add( ( $ajax ? ( 'wp_ajax' === $m[1] ? 'ajax:' : 'post:' ) . $m[2] : 'cron:' . $hook ), $wbam_owner( $cb['function'] ) );
		}
	}
}

// Placements, ad types and emails: found by interface / class name, never
// instantiated. An email is each public send_* / notify_* method, plus every
// handler on an *Email_Notifications class.
foreach ( get_declared_classes() as $class ) {
	if ( ! str_starts_with( $class, 'WBAM\\' ) && ! str_starts_with( $class, 'WBAM_Pro\\' ) ) {
		continue;
	}
	$r     = new ReflectionClass( $class );
	$short = $r->getShortName();
	$kind  = $r->implementsInterface( 'WBAM\\Modules\\Placements\\Placement_Interface' ) ? 'placement'
		: ( $r->implementsInterface( 'WBAM\\Modules\\AdTypes\\Ad_Type_Interface' ) ? 'adtype' : '' );
	if ( $kind ) {
		$first = $r->getMethods()[0] ?? null;
		$wbam_add( $kind . ':' . $short, $first ? $wbam_owner( array( $class, $first->name ) ) : null );
	}
	if ( ! preg_match( '/Emails?$|Email_Notifications$/', $short ) ) {
		continue;
	}
	foreach ( $r->getMethods( ReflectionMethod::IS_PUBLIC ) as $m ) {
		$all = str_ends_with( $short, 'Email_Notifications' ) && ! preg_match( '/^(get_|init|deliver|__)|_url$/', $m->name );
		if ( $m->class === $class && ( $all || preg_match( '/^(send|notify)_/', $m->name ) ) ) {
			$wbam_add( 'email:' . $short . '::' . $m->name, $wbam_owner( array( $class, $m->name ) ) );
		}
	}
}

// Post types and taxonomies under the plugins' prefix.
$wbam_types = array_merge(
	array_map( static fn ( $n ) => 'cpt:' . $n, get_post_types() ),
	array_map( static fn ( $n ) => 'tax:' . $n, get_taxonomies() )
);
foreach ( $wbam_types as $key ) {
	$name = substr( $key, 4 );
	if ( ! str_starts_with( $name, 'wbam' ) ) {
		continue;
	}
	$wbam_add( $key, $wbam_by_source( $name, str_starts_with( $key, 'cpt:' ) ? 'register_post_type' : 'register_taxonomy' ) );
}

// Compare with each catalog.
ksort( $wbam_found );
$wbam_gaps = 0;
foreach ( $wbam_plugins as $who => $dir ) {
	$mine    = array_filter( $wbam_found, static fn ( $o ) => $who === $o[0] );
	$catalog = $dir . '/docs/qa/FUNCTIONALITY_CATALOG.md';
	$text    = is_readable( $catalog ) ? (string) file_get_contents( $catalog ) : '';
	preg_match_all( '/`((?:' . WBAM_QA_KINDS . '):[^`\s]+)`/', $text, $m );
	$listed = array_unique( $m[1] );
	// An entry covers a surface exactly, or by pattern when it holds a `*`.
	$covered = static function ( $key ) use ( $listed ) {
		foreach ( $listed as $entry ) {
			if ( $entry === $key || ( str_contains( $entry, '*' ) && fnmatch( $entry, $key ) ) ) {
				return true;
			}
		}
		return false;
	};

	WP_CLI::log( sprintf( '== %s: %d surfaces, %d listed in %s', strtoupper( $who ), count( $mine ), count( $listed ), $text ? 'docs/qa/FUNCTIONALITY_CATALOG.md' : 'NO CATALOG' ) );
	if ( $wbam_list ) {
		foreach ( $mine as $key => $o ) {
			WP_CLI::log( sprintf( '  %-70s %s', $key, $o[1] ) );
		}
		continue;
	}
	foreach ( array_filter( array_keys( $mine ), static fn ( $k ) => ! $covered( $k ) ) as $key ) {
		WP_CLI::log( sprintf( '  MISSING from catalog: %-60s %s', $key, $mine[ $key ][1] ) );
		++$wbam_gaps;
	}
	$stale = array_filter(
		$listed,
		static fn ( $e ) => str_contains( $e, '*' ) ? ! array_filter( array_keys( $wbam_found ), static fn ( $k ) => fnmatch( $e, $k ) ) : ! isset( $wbam_found[ $e ] )
	);
	foreach ( $stale as $key ) {
		WP_CLI::log( '  STALE in catalog (not registered): ' . $key );
		++$wbam_gaps;
	}
}

if ( ! $wbam_list ) {
	$wbam_gaps ? WP_CLI::error( "$wbam_gaps catalog gap(s). Add each surface to a feature, or remove it from the catalog." ) : WP_CLI::success( 'Every surface is in a catalog, and every catalog entry exists.' );
}
