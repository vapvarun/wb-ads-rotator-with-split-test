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
				'assets/js/*.min.js'
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

		// Watch for changes.
		watch: {
			css: {
				files: [ 'assets/css/*.css', '!assets/css/*.min.css' ],
				tasks: [ 'cssmin' ]
			},
			js: {
				files: [ 'assets/js/*.js', '!assets/js/*.min.js' ],
				tasks: [ 'uglify' ]
			}
		}
	} );

	// Register tasks.
	grunt.registerTask( 'minify', [ 'cssmin', 'uglify' ] );
	grunt.registerTask( 'i18n', [ 'makepot' ] );
	grunt.registerTask( 'build', [ 'clean:build', 'minify', 'makepot' ] );
	grunt.registerTask( 'default', [ 'build' ] );

	// Release zip: bin/build-release.sh (WordPress.org folder standard).
};
