# Release builder

`npm run release` produces `dist/{name}-{version}.zip` ready to ship to
customers. It runs four defences so a broken zip cannot escape the
build:

1. **Strip dev files** per `.distignore`, applied with
   `rsync --exclude-from` exactly as `bin/build-zips.sh` applies it.
2. **Completeness scan** — walks `assets/` for every CSS / JS / image
   file and `includes/` for every PHP file, confirms each ends up in
   the zip tree.
3. **Runtime-reference scan** — walks every PHP file and looks for
   string literals concatenated with a plugin path / URL anchor
   (`__DIR__`, `__FILE__`, `plugin_dir_path()`, `WBAM_PRO_PATH`,
   `WBAM_URL`, etc.). Any referenced file that is not in the zip fails
   the build. This catches the class of bugs where the admin UI
   references a file that was stripped by `.distignore` or never
   committed (see "History of bugs caught" below).
4. **PHP syntax smoke test** — `php -l` every PHP file in the zip. A
   syntax error in any file fails the build before the zip is
   finalised. Skipped if `php` is not on `$PATH`.

Exit `0` = clean zip. Exit `1` = something was missing or broken.

## Running

```bash
npm run release          # produces dist/{name}-{version}.zip
```

That is the only command. Everything else is automatic.

## What goes into the zip

- Defined in `.distignore`, the only exclude list. Only what is NOT
  excluded ships. `npm run release` and the free plugin's
  `bin/build-zips.sh` (free, Pro and combo zips) all read it; Grunt has
  no dist task.
- rsync pattern rules: a leading `/` anchors to the plugin root. A
  pattern without `/` matches that name at any depth, so an unanchored
  `vendor` would also strip `assets/vendor/`. Anchor directories.
- `/.*` excludes every top-level dotfile, so a new config file never
  needs listing.

## The release name vs. in-zip folder name

The in-zip plugin folder is whatever the Text Domain header says (the
WordPress.org slug). The zip FILENAME uses the `package.json` `"name"`
field so marketing-name files like `wb-ad-manager-2.8.0.zip` ship even
though the internal directory is `wb-ads-rotator-with-split-test/`.

## History of bugs this builder caught (in order they bit us)

| # | What | How the builder now blocks it |
|---|------|-------------------------------|
| 1 | `Site_Mode` class not loaded → fatal on activation | require-target scan across `includes/` |
| 2 | Empty `docs/` / `marketing/` / `tests/` dirs leaking | directory-aware stripper |
| 3 | `vendor/wbcom-credits-sdk/` stripped → fatal on portal | require-target scan via `WBAM_PRO_PATH . 'vendor/...'` |
| 4 | `assets/vendor/lucide.min.js` stripped → 404 on dashboard | pattern matcher now plugin-root-relative only |
| 5 | `demo-data-setup.php` + `demo-data/` stripped → feature broken | runtime-reference scanner picks up `WBAM_PRO_PATH . 'demo-data-setup.php'` |
| 6 | `assets/images/placeholder.png` referenced but never committed | runtime-reference scanner caught it on next build |

Every one of these was a runtime failure. Every one is now a build-time
error. If the builder says "OK Completeness checks passed" you can ship
the zip.

## When a new reference gets false-positive-caught

If the scanner reports a missing file that is either:
- A WordPress core file (`wp-admin/includes/file.php` etc.) — the
  scanner already skips `wp-(admin|includes|content)/`, but add a new
  skip pattern in `parseRequires()` if needed.
- An external URL passed through a constant — skipped if the string
  starts with `http:`, `https:`, or `//`.
- A file intentionally optional (user-provided config, etc.) — either
  commit a stub or add the path to `.distignore` (stripped files are
  skipped by the scanner too).

## Running against a branch without `dist`

The builder writes to `dist/` which is `.gitignore`d. Always run from a
clean working tree — uncommitted files are NOT in `git archive HEAD`
output, so the scanner will flag them missing.
