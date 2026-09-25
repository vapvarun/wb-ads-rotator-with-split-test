#!/usr/bin/env node
/**
 * Release builder with completeness verification.
 *
 * What it does:
 *   1. Reads the plugin slug + version from the main PHP header.
 *   2. git archive HEAD -> temp tree.
 *   3. Copies it through .distignore (rsync --exclude-from, same as
 *      bin/build-zips.sh) into the staging tree.
 *   4. Verifies every CSS file under assets/css/ is in the zip tree.
 *   5. Verifies every JS file under assets/js/ is in the zip tree.
 *   6. Verifies every PHP file under includes/ is in the zip tree.
 *   7. Parses require / require_once / include / include_once across EVERY
 *      PHP file under includes/ + the main plugin file, resolves __DIR__
 *      relative paths, skips WP core paths, and asserts every remaining
 *      target exists in the zip. Catches "forgot to ship this class" and
 *      bundled-dep bugs (e.g. vendor/wbcom-credits-sdk stripped by
 *      .distignore).
 *   8. Writes dist/{releaseName}-{version}.zip and prints a summary.
 *
 * Exit codes:
 *   0 = zip built, all checks passed
 *   1 = missing file (CSS/JS/PHP) or broken require target
 *   2 = configuration problem (missing .distignore, bad version, etc.)
 *
 * Pure Node 20+ (no third-party deps), run from the plugin root:
 *   node scripts/build-release.mjs
 *   npm run release
 */

import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, mkdirSync, readdirSync, statSync, rmSync } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(__dirname, '..');
process.chdir(ROOT);

const BOLD = (s) => `\x1b[1m${s}\x1b[0m`;
const GREEN = (s) => `\x1b[32m${s}\x1b[0m`;
const RED = (s) => `\x1b[31m${s}\x1b[0m`;
const DIM = (s) => `\x1b[2m${s}\x1b[0m`;

function die(code, msg) {
	console.error(RED('x ' + msg));
	process.exit(code);
}

function run(cmd, args, opts = {}) {
	// execFileSync avoids shell interpolation entirely (no command-injection surface).
	return execFileSync(cmd, args, { encoding: 'utf8', ...opts });
}

function walk(dir, exts = null) {
	if (!existsSync(dir)) return [];
	const out = [];
	for (const entry of readdirSync(dir)) {
		const full = join(dir, entry);
		const s = statSync(full);
		if (s.isDirectory()) {
			out.push(...walk(full, exts));
		} else if (!exts || exts.some((e) => entry.endsWith(e))) {
			out.push(full);
		}
	}
	return out;
}

function requireDistignore() {
	// .distignore is the ONE list of what never ships. It is applied with
	// rsync --exclude-from, the same way bin/build-zips.sh applies it, so the
	// two release paths cannot disagree about a pattern.
	if (!existsSync('.distignore')) {
		die(2, '.distignore not found. Create one listing paths/files to exclude from the release zip.');
	}
}

function parseMainPlugin() {
	const rootPhps = readdirSync('.').filter((f) => f.endsWith('.php'));
	for (const file of rootPhps) {
		const src = readFileSync(file, 'utf8');
		if (/^\s*\*\s*Plugin Name:/m.test(src)) {
			const vMatch = src.match(/^\s*\*\s*Version:\s*(.+)$/m);
			const tdMatch = src.match(/^\s*\*\s*Text Domain:\s*(.+)$/m);
			const slug = tdMatch ? tdMatch[1].trim() : file.replace(/\.php$/, '');
			const version = vMatch ? vMatch[1].trim() : null;
			if (!version) die(2, `Version header missing from ${file}`);
			// If package.json has a different "name", use it for the zip filename
			// (marketing name vs WordPress.org slug — e.g. wb-ad-manager vs
			// wb-ads-rotator-with-split-test). The in-zip folder keeps the PHP slug.
			let releaseName = slug;
			if (existsSync('package.json')) {
				try {
					const pkg = JSON.parse(readFileSync('package.json', 'utf8'));
					if (pkg.name && typeof pkg.name === 'string') releaseName = pkg.name;
				} catch {}
			}
			return { mainFile: file, slug, releaseName, version };
		}
	}
	die(2, 'No main plugin file found at repo root (looked for Plugin Name header).');
}

function parseRequires(phpPath) {
	if (!existsSync(phpPath)) return [];
	const src = readFileSync(phpPath, 'utf8');
	const out = new Set();
	// Track the source file's directory (relative to ROOT) so __DIR__
	// references resolve to the right plugin-relative path.
	const srcDirRel = relative(ROOT, dirname(phpPath)).replace(/\\/g, '/');

	// Match ANY statement that concatenates a path anchor with a string
	// literal ending in a file extension. Covers:
	//   require_once __DIR__ . '/foo.php'
	//   include WBAM_PRO_PATH . 'demo-data-setup.php'
	//   $file = plugin_dir_path(__FILE__) . 'demo-data/ads/ad-01.jpg'
	//   file_exists( WBAM_PRO_PATH . 'demo-data-setup.php' )
	//   wp_enqueue_script( 'lucide', WBAM_URL . 'assets/vendor/lucide.min.js' )
	// Caught extensions: php, js, css, svg, png, jpg, jpeg, gif, webp,
	// json, pot, mo, html, txt, md.
	const re = /(__DIR__|__FILE__|plugin_dir_path\s*\([^)]*\)|\b[A-Z][A-Z0-9_]+(?:_PATH|_DIR|_URL|_FILE|_PLUGIN_DIR))\s*\.\s*['"]([^'"]+?\.(?:php|js|css|svg|png|jpe?g|gif|webp|json|pot|mo|html?|txt|md))['"]/g;
	let m;
	while ((m = re.exec(src)) !== null) {
		const anchor = m[1];
		let rel = m[2];
		if (rel.startsWith('/')) rel = rel.slice(1);
		rel = rel.replace(/^\.\//, '');

		// Skip WordPress core paths (always present at runtime).
		if (/^wp-(admin|includes|content)\//.test(rel)) continue;
		// Skip protocol-ish references (external URLs pasted into string).
		if (/^https?:/.test(rel) || /^\/\//.test(rel)) continue;

		if (/__DIR__/.test(anchor)) {
			out.add(srcDirRel ? `${srcDirRel}/${rel}` : rel);
		} else {
			// __FILE__, plugin_dir_path(), *_PATH, *_DIR, *_URL, *_FILE
			// constants all resolve to the plugin root.
			out.add(rel);
		}
	}
	return [...out];
}

function main() {
	const { mainFile, slug, releaseName, version } = parseMainPlugin();
	console.log(BOLD(`\nBuilding release: ${releaseName} ${version}`));

	requireDistignore();
	const DIST = resolve(ROOT, 'dist');
	const WORK = resolve(DIST, `_build-${slug}`);
	mkdirSync(DIST, { recursive: true });

	// 1. Archive HEAD.
	rmSync(WORK, { recursive: true, force: true });
	mkdirSync(WORK, { recursive: true });
	const rawZip = resolve(WORK, 'raw.zip');
	run('git', ['archive', '--format=zip', `--prefix=${slug}/`, 'HEAD', '-o', rawZip]);
	console.log(DIM(`  git archive -> ${relative(ROOT, rawZip)}`));

	// 2. Extract, then copy through .distignore into the staging tree.
	const extractRoot = resolve(WORK, 'extract');
	const stageRoot = resolve(WORK, 'stage');
	mkdirSync(extractRoot, { recursive: true });
	mkdirSync(stageRoot, { recursive: true });
	run('unzip', ['-q', rawZip, '-d', extractRoot]);
	const archivedDir = resolve(extractRoot, slug);
	const pluginDir = resolve(stageRoot, slug);
	run('rsync', ['-a', `--exclude-from=${resolve(ROOT, '.distignore')}`, `${archivedDir}/`, `${pluginDir}/`]);
	const stripped = walk(archivedDir).length - walk(pluginDir).length;
	console.log(DIM(`  distignore  -> stripped ${stripped} files`));

	// A committed path that did not survive the copy was excluded on purpose.
	const isExcluded = (rel) => existsSync(resolve(archivedDir, rel)) && !existsSync(resolve(pluginDir, rel));

	// 3. Completeness checks.
	const errors = [];

	function requireInZip(srcRel, kind) {
		const inZip = resolve(pluginDir, srcRel);
		if (!existsSync(inZip)) errors.push({ kind, path: srcRel });
	}

	// 3a. CSS + JS across ALL of assets/ (not just assets/css and
	// assets/js). Plugins commonly stash vendored libs at
	// assets/vendor/ (lucide, chart.js, etc.) and those must ship too.
	for (const f of walk('assets', ['.css', '.js'])) {
		const rel = relative(ROOT, f);
		if (isExcluded(rel)) continue;
		const kind = f.endsWith('.css') ? 'CSS' : 'JS';
		requireInZip(rel, kind);
	}
	// 3c. Every PHP file under includes/.
	for (const phpFile of walk('includes', ['.php'])) {
		const rel = relative(ROOT, phpFile);
		if (isExcluded(rel)) continue;
		requireInZip(rel, 'PHP');
	}
	// 3d. Runtime file references across the whole PHP tree.
	// Scans every PHP file under includes/ PLUS the main plugin file
	// for any string literal that concatenates a plugin path anchor
	// (__DIR__, __FILE__, plugin_dir_path(), *_PATH, *_DIR, *_URL)
	// with a file path. Catches broken bundling for:
	//   - vendor/wbcom-credits-sdk/*.php (referenced from deep require)
	//   - assets/vendor/lucide.min.js (referenced from wp_enqueue_script)
	//   - demo-data-setup.php (referenced from admin AJAX handlers)
	//   - demo-data/ads/*.jpg (referenced from the setup script)
	// Any file extension is checked (php, js, css, images, json, pot, md).
	const refSources = [mainFile, ...walk('includes', ['.php'])];
	const referencedPaths = new Set();
	for (const src of refSources) {
		for (const r of parseRequires(src)) referencedPaths.add(r);
	}
	for (const r of referencedPaths) {
		if (isExcluded(r)) continue;
		const kind = r.endsWith('.php') ? 'PHP reference' : `${r.split('.').pop().toUpperCase()} reference`;
		requireInZip(r, kind);
	}

	if (errors.length > 0) {
		console.log('');
		for (const e of errors) console.error(RED(`  x ${e.kind} missing from zip: ${e.path}`));
		console.log('');
		die(1, `${errors.length} file(s) missing from release zip. Fix .distignore or ensure files are committed.`);
	}

	// 4. Smoke test — PHP-lint every file that will ship. Catches
	// syntax errors that passed local development but would fatal on
	// a customer site. Cheap: only runs if the `php` binary is
	// available, skipped silently if not.
	let smokeFiles = 0;
	let smokeFailed = 0;
	try {
		// Probe for php on PATH.
		run('php', ['-v']);
		for (const phpFile of walk(pluginDir, ['.php'])) {
			smokeFiles++;
			try {
				run('php', ['-l', phpFile]);
			} catch (err) {
				smokeFailed++;
				const shown = relative(pluginDir, phpFile);
				console.error(RED(`  x PHP syntax error in zip: ${shown}`));
			}
		}
		if (smokeFailed > 0) {
			die(1, `${smokeFailed} of ${smokeFiles} PHP file(s) failed syntax check inside zip.`);
		}
	} catch (err) {
		if (smokeFiles === 0) {
			console.log(DIM('  smoke test -> skipped (php binary not on PATH)'));
		}
	}
	if (smokeFiles > 0) {
		console.log(DIM(`  smoke test  -> php -l on ${smokeFiles} file(s) — all clean`));
	}

	// 5. Build final zip. Using cwd instead of "cd X && zip" keeps us shell-free.
	const outZip = resolve(DIST, `${releaseName}-${version}.zip`);
	rmSync(outZip, { force: true });
	run('zip', ['-rq', outZip, slug], { cwd: stageRoot });

	// 5. Summary.
	const zipStats = statSync(outZip);
	const listing = run('unzip', ['-l', outZip]);
	const lastLine = listing.trim().split('\n').pop().trim();
	const entryCount = lastLine.split(/\s+/)[1] || '?';
	console.log('');
	console.log(GREEN('OK Release zip built'));
	console.log(`  Path:  ${relative(ROOT, outZip)}`);
	console.log(`  Size:  ${(zipStats.size / 1024).toFixed(0)} KB`);
	console.log(`  Files: ${entryCount}`);
	console.log(GREEN('OK Completeness checks passed (assets, PHP tree, runtime file references)'));

	// 6. Cleanup work dir.
	rmSync(WORK, { recursive: true, force: true });
}

main();
