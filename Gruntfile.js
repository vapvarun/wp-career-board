/* global module, require */
/**
 * WP Career Board — Gruntfile
 *
 * Tasks:
 *   grunt build        — compile blocks via @wordpress/scripts
 *   grunt start        — watch + rebuild (dev mode)
 *   grunt pot          — generate languages/wp-career-board.pot via WP-CLI
 *   grunt textdomain   — verify every PHP string uses correct text domain
 *   grunt i18n         — pot + textdomain
 *   grunt dist         — clean, copy release files, create zip
 *   grunt rtl          — generate RTL variants of admin.css and frontend.css
 *   grunt release      — build + i18n + rtl + dist (full pipeline)
 *   grunt version      — bump version: grunt version --ver=1.0.0
 */
module.exports = function ( grunt ) {
	'use strict';

	const pkg  = grunt.file.readJSON( 'package.json' );
	const slug = 'wp-career-board';
	const ver  = grunt.option( 'ver' ) || pkg.version;

	grunt.initConfig( {

		// ── Clean ────────────────────────────────────────────────────────────
		clean: {
			dist: [ 'dist/' ],
		},

		// ── Text domain check ────────────────────────────────────────────────
		checktextdomain: {
			options: {
				text_domain: slug,
				keywords: [
					'__:1,2d',
					'_e:1,2d',
					'_x:1,2c,3d',
					'esc_html__:1,2d',
					'esc_html_e:1,2d',
					'esc_attr__:1,2d',
					'esc_attr_e:1,2d',
					'esc_attr_x:1,2c,3d',
					'esc_html_x:1,2c,3d',
					'_n:1,2,4d',
					'_nx:1,2,4c,5d',
				],
			},
			files: {
				src: [
					'**/*.php',
					'!vendor/**',
					'!node_modules/**',
					'!dist/**',
				],
				expand: true,
			},
		},

		// ── Copy release files ───────────────────────────────────────────────
		copy: {
			dist: {
				expand: true,
				src: [
					'admin/**',
					'api/**',
					'assets/**',
					'blocks/**',
					'build/**',
					'cli/**',
					'core/**',
					'import/**',
					'integrations/**',
					'languages/**',
					'modules/**',
					'vendor/edd-sl-sdk/**',
				'!vendor/**/*.md',
				'!vendor/**/package.json',
				'!vendor/**/package-lock.json',
					'readme.txt',
					'theme.json',
					'uninstall.php',
					slug + '.php',
				],
				dest: 'dist/' + slug + '/',
			},
		},

		// ── Compress to zip ──────────────────────────────────────────────────
		compress: {
			dist: {
				options: {
					archive: 'dist/' + slug + '-' + ver + '.zip',
					mode:    'zip',
				},
				files: [
					{
						expand: true,
						cwd:    'dist/',
						src:    [ slug + '/**' ],
					},
				],
			},
		},

		// ── RTL CSS ──────────────────────────────────────────────────────────
		rtlcss: {
			options: {
				saveUnmodified: false,
			},
			dist: {
				expand: true,
				cwd:    'assets/css/',
				src:    [ 'admin.css', 'frontend.css' ],
				dest:   'assets/css/',
				ext:    '-rtl.css',
			},
		},

		// ── Shell commands ───────────────────────────────────────────────────
		shell: {
			build: {
				command: 'npm run build',
			},
			start: {
				command: 'npm run start',
			},
			pot: {
				command: [
					'wp i18n make-pot .',
					'languages/' + slug + '.pot',
					'--domain=' + slug,
					'--exclude=vendor,node_modules,build,dist,docs,tests',
				].join( ' ' ),
			},
			version: {
				command: [
					// Update PHP header Version: tag
					'sed -i "" "s/^ \\* Version:.*/ * Version: ' + ver + '/" ' + slug + '.php',
					// Update PHP constant
					'sed -i "" "s/define( .WCB_VERSION., .*/define( \'WCB_VERSION\', \'' + ver + '\' );/" ' + slug + '.php',
					// Update package.json
					'npm version ' + ver + ' --no-git-tag-version --allow-same-version',
				].join( ' && ' ),
			},
		},

	} );

	// ── Load tasks ──────────────────────────────────────────────────────────
	grunt.loadNpmTasks( 'grunt-rtlcss' );
	grunt.loadNpmTasks( 'grunt-contrib-clean' );
	grunt.loadNpmTasks( 'grunt-contrib-compress' );
	grunt.loadNpmTasks( 'grunt-contrib-copy' );
	grunt.loadNpmTasks( 'grunt-checktextdomain' );
	grunt.loadNpmTasks( 'grunt-shell' );

	// ── Composite tasks ─────────────────────────────────────────────────────
	grunt.registerTask( 'build',      [ 'shell:build' ] );
	grunt.registerTask( 'start',      [ 'shell:start' ] );
	grunt.registerTask( 'pot',        [ 'shell:pot' ] );
	grunt.registerTask( 'textdomain', [ 'checktextdomain' ] );
	grunt.registerTask( 'i18n',       [ 'pot', 'checktextdomain' ] );
	grunt.registerTask( 'rtl',        [ 'rtlcss:dist' ] );
	grunt.registerTask( 'dist',       [ 'clean:dist', 'copy:dist', 'compress:dist' ] );
	grunt.registerTask( 'version',    [ 'shell:version' ] );
	grunt.registerTask( 'release',    [ 'build', 'i18n', 'rtl', 'dist' ] );
};
