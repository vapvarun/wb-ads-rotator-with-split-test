# QA Release Checklist — WB Ad Manager (Free + Pro)

The gate between "fixes are merged" and "customers get an update". Work top to bottom; a failure stops the release rather than earning a note.

## 0 — Repository truth

- [ ] `main` **contains the previous release tag**. Verify, don't assume: `git merge-base --is-ancestor <prev-tag> main`
- [ ] `main`'s version header matches the last shipped release
- [ ] No file present in the previous tag is missing from `main`:
      `diff <(git ls-tree -r <prev-tag> --name-only) <(git ls-tree -r main --name-only) | grep '^<'`
- [ ] Release branch is cut from a commit that satisfies all of the above

> This is not paranoia. Pro `main` sat at 3.0.0 while v3.1.0 shipped from a divergent commit; ten files including the whole Ad Folders feature existed only on the tag. A release built from `main` would have deleted a live feature.

## 1 — Board

- [ ] Bugs column empty, or every remaining card explicitly deferred with a reason
- [ ] Ready for Testing cleared by QA — not by the person who wrote the fix
- [ ] Every card moved to Done carries evidence: what was measured, before and after

## 2 — Static gates

- [ ] `php -l` clean across changed files
- [ ] WPCS: **no new** violations versus the previous tag. Compare like for like — swap the baseline file in at the same path and diff the counts, because line numbers shift and totals mislead
- [ ] PHPStan clean against the baseline. If the baseline moved, say **why** in the commit; a baseline that grows without explanation is a bypass
- [ ] Plugin Check: zero ERRORs
- [ ] Contract audit clean or baselined with reasons

> A bare `phpcs:enable` in a file carrying file-level `phpcs:disable` directives re-enables them for the rest of the file. Scope every enable to the sniffs you turned off, or omit it.

## 3 — Browser smoke

- [ ] `docs/qa/.last-smoke-pass.json` exists, `release_version` matches, `failures[]` and `debug_log_issues[]` both empty
- [ ] Or `PRE_RELEASE_SMOKE.md` completed by hand and attached
- [ ] Report is under 24h old

## 4 — Journey re-run after the last code change

- [ ] Full journey re-run **after** the final commit, not per-fix

> Not optional. Repairing the stale page-ID option switched on a guard that had never fired, which broke the CSV export shipped an hour earlier. Re-testing that export in isolation passed. Only the full journey caught it.

## 5 — Versions and packaging

- [ ] Version bumped in: main file header, version constant, `readme.txt` stable tag, `package.json`
- [ ] Free and Pro agree (lockstep)
- [ ] `readme.txt` changelog follows the action-prefix format — `New`/`Improve`/`Fix`/`Security`/`Dev`/`Compat`, no emoji, no em-dashes
- [ ] Built artifacts exist for **every** distribution channel: free zip, **standalone Pro zip**
- [ ] Combo zip built for testing the matched pair - **QA only, never attached to a release**; no customer channel serves it
- [ ] Zips exclude `node_modules`, `tests`, `.md` docs; bundled SDK sources are **present**
- [ ] Pristine Docker install of the built zip activates cleanly — dev-tree CI does not catch packaging bugs

## 6 — Documentation truth

- [ ] `audit/manifest.json` refreshed if hooks, routes, tables or capabilities changed
- [ ] Paired free/pro manifest reflects reality — boundary and duplication fields especially

> `duplication: "none"` was recorded for Analytics while free and Pro were both writing the same click row. A manifest that is wrong on the exact point it exists to guarantee is worse than no manifest.

- [ ] `CAPABILITIES.md` regenerated if features changed
- [ ] Customer docs updated for anything user-visible

## 7 — Release

- [ ] Tag created on the merged release commit
- [ ] Release notes follow the house format; free and Pro cross-link
- [ ] Artifacts attached to both releases
- [ ] Slack `#ready-for-release` notified

## 8 — First 24 hours

- [ ] Support inbox watched for "ads not showing" / "stats went to zero"
- [ ] One live site: Ad Analytics and All Ads agree for the same ad
- [ ] Error logs checked for `wbam_` fatals

## Emergency overrides

`--skip-browser-smoke` and `--allow-stale-docs` exist for internal builds. Using either on a customer-visible release means writing down who authorised it and why, on the release card.
