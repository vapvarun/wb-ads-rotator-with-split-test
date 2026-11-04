#!/usr/bin/env bash
#
# Install the WP test harness for PHPUnit.
# Usage: bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database]

set -euo pipefail

DB_NAME="${1:-wbam_test}"
DB_USER="${2:-root}"
DB_PASS="${3:-root}"
DB_HOST="${4:-localhost}"
WP_VERSION="${5:-latest}"
SKIP_DB="${6:-false}"

# "latest" has no develop.svn tag, so the suite fell back to trunk and broke
# against the released core. Resolve it to the real version number.
if [ "$WP_VERSION" = "latest" ]; then
	WP_VERSION="$(curl -fsSL https://api.wordpress.org/core/version-check/1.7/ | grep -o '"version":"[^"]*"' | head -1 | cut -d'"' -f4)"
fi

WP_TESTS_DIR="${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}"
WP_CORE_DIR="${WP_CORE_DIR:-/tmp/wordpress/}"

download() {
	if command -v curl >/dev/null 2>&1; then
		curl -fsSL -o "$2" "$1"
	else
		wget -nv -O "$2" "$1"
	fi
}

install_wp() {
	if [ -d "$WP_CORE_DIR" ]; then
		return
	fi
	mkdir -p "$WP_CORE_DIR"
	if [ "$WP_VERSION" = "latest" ]; then
		ARCHIVE="https://wordpress.org/latest.tar.gz"
	else
		ARCHIVE="https://wordpress.org/wordpress-${WP_VERSION}.tar.gz"
	fi
	download "$ARCHIVE" /tmp/wordpress.tar.gz
	tar --strip-components=1 -zxmf /tmp/wordpress.tar.gz -C "$WP_CORE_DIR"
	download https://raw.githubusercontent.com/markoheijnen/wp-mysqli/master/db.php "$WP_CORE_DIR/wp-content/db.php"
}

install_test_suite() {
	if [ ! -d "$WP_TESTS_DIR" ]; then
		mkdir -p "$WP_TESTS_DIR"
		svn co --quiet "https://develop.svn.wordpress.org/tags/${WP_VERSION}/tests/phpunit/includes/" "$WP_TESTS_DIR/includes" || \
			svn co --quiet "https://develop.svn.wordpress.org/trunk/tests/phpunit/includes/" "$WP_TESTS_DIR/includes"
		svn co --quiet "https://develop.svn.wordpress.org/tags/${WP_VERSION}/tests/phpunit/data/" "$WP_TESTS_DIR/data" || \
			svn co --quiet "https://develop.svn.wordpress.org/trunk/tests/phpunit/data/" "$WP_TESTS_DIR/data"
	fi

	if [ ! -f "$WP_TESTS_DIR/wp-tests-config.php" ]; then
		download https://develop.svn.wordpress.org/trunk/wp-tests-config-sample.php "$WP_TESTS_DIR/wp-tests-config.php"
		sed -i.bak "s|dirname( __FILE__ ) . '/src/'|'$WP_CORE_DIR'|" "$WP_TESTS_DIR/wp-tests-config.php"
		sed -i.bak "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR/wp-tests-config.php"
		sed -i.bak "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR/wp-tests-config.php"
		sed -i.bak "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR/wp-tests-config.php"
		sed -i.bak "s|localhost|$DB_HOST|" "$WP_TESTS_DIR/wp-tests-config.php"
		rm -f "$WP_TESTS_DIR/wp-tests-config.php.bak"
	fi
}

install_db() {
	if [ "$SKIP_DB" = "true" ]; then
		return
	fi
	mysqladmin create "$DB_NAME" --user="$DB_USER" --password="$DB_PASS" --host="$DB_HOST" --force 2>/dev/null || true
}

install_wp
install_test_suite
install_db

echo "WP test suite ready at $WP_TESTS_DIR"
echo "DB: $DB_NAME@$DB_HOST"
