# MaxMind DB Reader (vendored)

Pure-PHP reader for the MaxMind DB binary format (`.mmdb`), used to look up a
visitor's country/city from a **local** GeoLite2 database the site owner
downloads from their own MaxMind account. No network call, no visitor IP
ever leaves the site with this provider.

- Upstream: https://github.com/maxmind/MaxMind-DB-Reader-php
- Version vendored: `main` @ 2026-09-26, files under `src/MaxMind/Db/`
- License: Apache-2.0 (see `LICENSE`)
- Zero runtime dependencies (bcmath/gmp are optional, only needed for
  integer fields wider than 32 bits, which country/city databases don't use)

Vendored by hand into `libs/` (same pattern as Pro's `libs/wbcom-credits-sdk/`)
instead of via Composer because this plugin's release zip never ships
`/vendor` (see `.distignore`) and this is the only runtime PHP dependency
the Free plugin has. Loaded on demand by `load.php` — only required when an
owner actually selects the MaxMind provider, so sites that never touch
geolocation never pay the `require` cost.

Do not hand-edit the files under `src/`. To update, re-download the same
paths from the upstream repo.
