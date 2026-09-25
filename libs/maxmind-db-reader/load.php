<?php
/**
 * Loader for the vendored MaxMind DB reader.
 *
 * Required on demand from Geo_Engine only when the `maxmind` provider is
 * selected — sites that never enable geolocation, or use the HTTPS API
 * provider, never load this code.
 *
 * @package WB_Ad_Manager
 * @since   3.2.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( '\MaxMind\Db\Reader', false ) ) {
	return;
}

$wbam_maxmind_reader_dir = __DIR__ . '/src/MaxMind/Db/';

require_once $wbam_maxmind_reader_dir . 'Reader/InvalidDatabaseException.php';
require_once $wbam_maxmind_reader_dir . 'Reader/Util.php';
require_once $wbam_maxmind_reader_dir . 'Reader/Metadata.php';
require_once $wbam_maxmind_reader_dir . 'Reader/Decoder.php';
require_once $wbam_maxmind_reader_dir . 'Reader.php';

unset( $wbam_maxmind_reader_dir );
