/**
 * Grunt configuration for WB Ad Manager
 *
 * @package WB_Ad_Manager
 */

module.exports = function( grunt ) {
	'use strict';

	// Load all grunt tasks.
	require( 'load-grunt-tasks' )( grunt );

	// Project configuration.
	grunt.initConfig( {
		pkg: grunt.file.readJSON( 'package.json' ),

		// Clean generated files.
		clean: {
			build: [
				'assets/css/*.min.css',
				'assets/js/*.min.js',
				'blocks/*.min.js'
			]
		},

		// Minify CSS.
		cssmin: {
			options: {
				sourceMap: false
			},
			target: {
				files: [ {
					expand: true,
					cwd: 'assets/css',
					src: [ '*.css', '!*.min.css' ],
					dest: 'assets/css',
					ext: '.min.css'
				} ]
			}
		},

		// Minify JavaScript.
		uglify: {
			options: {
				sourceMap: false,
				mangle: {
					reserved: [ 'jQuery' ]
				}
			},
			target: {
				files: [ {
					expand: true,
					cwd: 'assets/js',
					src: [ '*.js', '!*.min.js' ],
					dest: 'assets/js',
					ext: '.min.js'
				} ]
			},
			// The block editor script is a plain file with no build pipeline
			// (see includes/Modules/Blocks/class-block-registry.php) but is
			// still routed through wbam_asset_url(), so it needs the same
			// SCRIPT_DEBUG-off .min sibling as everything under assets/js.
			blocks: {
				files: [ {
					expand: true,
					cwd: 'blocks',
					src: [ '*.js', '!*.min.js' ],
					dest: 'blocks',
					ext: '.min.js'
				} ]
			}
		},

		// Generate POT file.
		makepot: {
			target: {
				options: {
					domainPath: '/languages',
					exclude: [
						'node_modules/.*',
						'vendor/.*',
						'dist/.*',
						'tests/.*'
					],
					mainFile: 'wb-ads-rotator-with-split-test.php',
					potFilename: 'wb-ads-rotator-with-split-test.pot',
					potHeaders: {
						poedit: true,
						'x-poedit-keywordslist': true,
						'Report-Msgid-Bugs-To': 'https://wbcomdesigns.com/support/',
						'Last-Translator': 'Wbcom Designs <developer@wbcomdesigns.com>',
						'Language-Team': 'Wbcom Designs <developer@wbcomdesigns.com>'
					},
					type: 'wp-plugin',
					updateTimestamp: true
				}
			}
		},

		// No copy/compress dist task on purpose. The release zip is built by
		// npm run release (scripts/build-release.mjs) or the free plugin's
		// bin/build-zips.sh, and both read .distignore - the one list of what
		// ships. A third hand-kept list here drifted from it and shipped dev files.

		// Watch for changes.
		watch: {
			css: {
				files: [ 'assets/css/*.css', '!assets/css/*.min.css' ],
				tasks: [ 'cssmin' ]
			},
			js: {
				files: [ 'assets/js/*.js', '!assets/js/*.min.js' ],
				tasks: [ 'uglify:target' ]
			},
			blocks: {
				files: [ 'blocks/*.js', '!blocks/*.min.js' ],
				tasks: [ 'uglify:blocks' ]
			}
		}
	} );

	// Register tasks.
	grunt.registerTask( 'minify', [ 'cssmin', 'uglify' ] );
	grunt.registerTask( 'i18n', [ 'makepot' ] );
	grunt.registerTask( 'build', [ 'clean:build', 'minify', 'makepot' ] );
	grunt.registerTask( 'default', [ 'build' ] );
};
