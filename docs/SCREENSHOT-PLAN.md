# Screenshot Plan — WB Ad Manager (Free + Pro)

**Status:** draft for review.
**Target:** every user-facing doc under `docs/website/` gets screenshots that render on GitHub and on the wbcomdesigns docs site.
**Source site:** `http://wp-ads.local` (this local install has admin populated as an advertiser with sample ads / classifieds / campaigns / messages / inquiries / plans).

Before we take any shots we want one plan, not a file per doc. The plan covers:

1. What we keep vs delete from the current `docs/website/images/` tree.
2. Which docs get which screenshots, in the order they appear in each doc.
3. Naming + resolution conventions so the tree stays tidy.
4. Capture-time setup that the screenshots depend on.

---

## 1. Current state (what's on disk today)

```
docs/website/images/
├── buddypress-bbpress/          # empty → delete
├── for-site-owners/             # 3.9 MB, 12 PNGs, Free-era
├── getting-started/             # 324 KB, 1 PNG
└── troubleshooting/             # empty → delete
```

Referenced from docs (8 total):

| File | Doc referencing it | Still accurate? |
|---|---|---|
| `for-site-owners/settings-page.png` | ad-management/settings.md | Re-shoot — settings UI changed |
| `for-site-owners/free-ad-settings.png` | (orphan) | Delete — no reference |
| `for-site-owners/ad-editor-image-type.png` | ad-management/ad-types.md | Re-shoot on new editor |
| `for-site-owners/ad-editor-html-type.png` | ad-management/ad-types.md | Re-shoot on new editor |
| `for-site-owners/analytics-dashboard.png` | ad-management/managing-ads.md | Move to analytics doc + re-shoot |
| `for-site-owners/link-add-form.png` | link-management/link-management.md | Re-shoot |
| `for-site-owners/link-partnerships.png` | link-management/link-management.md | Re-shoot |
| `for-site-owners/link-analytics.png` | link-management/link-management.md | Re-shoot |
| `for-site-owners/link-import.png` | (orphan) | Delete |
| `for-site-owners/link-health.png` | (orphan) | Delete |
| `for-site-owners/link-keywords.png` | (orphan) | Delete |
| `for-site-owners/links-list-empty.png` | (orphan) | Delete |
| `getting-started/ads-list.png` | getting-started/quick-setup-guide.md | Re-shoot |

**Action for §1:**

- `rm -rf docs/website/images/` — start from a clean tree.
- Re-create two subdirectories that match our doc layout:
  - `docs/website/images/free/`
  - `docs/website/images/pro/`
- Optionally a `docs/website/images/flows/` once the Phase-2 flow docs have stable copy.

Rationale: the old `for-site-owners/` naming came from a scheme we no longer use. `free/` vs `pro/` mirrors the `tier:` frontmatter we just added and stops screenshots drifting into the wrong doc.

---

## 2. What we need per doc

Every row below lists: the doc, the capture, an exact filename, the admin URL (so whoever shoots knows the exact state), and a one-line alt text. **No speculative screenshots** — each one is cited from a specific doc section that currently exists.

### 2a. Getting Started

| # | Doc | Shot | Filename | Where / state |
|---|---|---|---|---|
| 1 | getting-started/installation.md | Upload Plugin screen with the downloaded ZIP selected | `free/install-search.png` | `/wp-admin/plugin-install.php?tab=upload` |
| 2 | getting-started/installation.md | "Plugins list with WB Ad Manager active" | `free/install-active.png` | `/wp-admin/plugins.php`, row highlighted |
| 3 | getting-started/pro-installation-requirements.md | "WB Ads → Pro Settings → Modules" grid | `pro/modules-toggle.png` | `/wp-admin/admin.php?page=wbam-pro-settings&tab=modules` |
| 4 | getting-started/pro-installation-requirements.md | "WB Ads → Pro Settings → Credits with adapter rows" | `pro/credits-adapters.png` | same page, tab=credits |
| 5 | getting-started/quick-setup-guide.md | Ads list (populated) | `free/ads-list.png` | `/wp-admin/edit.php?post_type=wbam-ad` |
| 6 | getting-started/quick-setup-guide.md | First ad rendered on frontend | `free/frontend-first-ad.png` | Home page, header placement |

### 2b. Ad Management (Free)

| # | Doc | Shot | Filename | Where |
|---|---|---|---|---|
| 7 | ad-management/managing-ads.md | Ad list with filter + bulk actions open | `free/ads-list-bulk.png` | list, bulk action dropdown expanded |
| 8 | ad-management/managing-ads.md | Ad editor, Image type | `free/ad-editor-image.png` | edit ID 142 |
| 9 | ad-management/ad-types.md | Image ad editor | `free/ad-editor-image.png` (reuse) | — |
| 10 | ad-management/ad-types.md | Rich Content ad editor | `free/ad-editor-rich.png` | edit ID 143 |
| 11 | ad-management/ad-types.md | Code/HTML ad editor | `free/ad-editor-code.png` | edit ID 144 |
| 12 | ad-management/ad-types.md | AdSense ad editor | `free/ad-editor-adsense.png` | edit ID 145 |
| 13 | ad-management/ad-types.md | Email Capture ad editor | `free/ad-editor-email.png` | edit ID 146 |
| 14 | ad-management/placements.md | Placements metabox checklist | `free/ad-placements-metabox.png` | any ad edit screen, Placements box |
| 15 | ad-management/placements.md | Frontend header-placement render | `free/placement-header.png` | home page |
| 16 | ad-management/settings.md | Settings → General tab | `free/settings-general.png` | `/wp-admin/edit.php?post_type=wbam-ad&page=wbam-settings` |
| 17 | ad-management/targeting.md | Targeting metabox | `free/ad-targeting-metabox.png` | any ad edit screen, Targeting box |

### 2c. Link Management (Free)

| # | Doc | Shot | Filename | Where |
|---|---|---|---|---|
| 18 | link-management/link-management.md | Add Link form | `free/link-add.png` | `/wp-admin/post-new.php?post_type=wbam_link` |
| 19 | link-management/link-management.md | Links list table | `free/links-list.png` | `/wp-admin/edit.php?post_type=wbam_link` |
| 20 | link-management/link-management.md | Link analytics panel | `free/link-analytics.png` | single link edit → analytics meta |
| 21 | link-management/partnership-inquiries.md | Partnership inquiries list | `free/partnership-inquiries.png` | admin page for partnerships |

### 2d. Shortcode Reference (Free)

| # | Doc | Shot | Filename | Where |
|---|---|---|---|---|
| 22 | shortcode-reference/ad-shortcodes.md | One rendered `[wbam_ad]` output example | `free/shortcode-ad-output.png` | a test page running the shortcode |
| 23 | shortcode-reference/link-shortcodes.md | One rendered link shortcode | `free/shortcode-link-output.png` | a test page |

### 2e. Advertiser Portal (Pro)

Admin → advertiser id 6 populated. These screenshots are the "model portal" for selling the product.

| # | Doc | Shot | Filename | Where |
|---|---|---|---|---|
| 24 | advertiser-portal/advertiser-portal-overview.md | Portal landing — Ads tab populated | `pro/portal-ads.png` | `/advertiser-dashboard/?tab=ads` |
| 25 | advertiser-portal/advertiser-portal-overview.md | Portal tab bar (wide shot) | `pro/portal-tabs.png` | same page, showing the tab strip |
| 26 | advertiser-portal/ad-submissions-approval-workflow.md | Submit Ad wizard step 1 (package pick) | `pro/submit-step-package.png` | `?tab=ads&action=new` |
| 27 | advertiser-portal/ad-submissions-approval-workflow.md | Submit Ad wizard step 4 (creative) | `pro/submit-step-creative.png` | continued |
| 28 | advertiser-portal/campaign-management.md | Campaigns tab populated | `pro/portal-campaigns.png` | `?tab=campaigns` |
| 29 | advertiser-portal/campaign-management.md | Campaign budget / performance panel | `pro/campaign-detail.png` | click into a campaign |
| 30 | advertiser-portal/link-management-system.md | Portal Links tab | `pro/portal-links.png` | `?tab=links` |

### 2f. Classifieds (Pro)

| # | Doc | Shot | Filename | Where |
|---|---|---|---|---|
| 31 | classifieds/setting-up-classifieds.md | Classifieds module settings | `pro/classifieds-settings.png` | Pro Settings → Classifieds tab |
| 32 | classifieds/setting-up-classifieds.md | Portal My Classifieds tab | `pro/portal-classifieds.png` | `?tab=classifieds` |
| 33 | classifieds/setting-up-classifieds.md | Frontend browse page | `pro/classifieds-browse.png` | `/classifieds/` |
| 34 | classifieds/setting-up-classifieds.md | Single classified detail | `pro/classifieds-single.png` | any single classified |
| 35 | classifieds/setting-up-classifieds.md | Inquiries tab populated | `pro/portal-inquiries.png` | `?tab=inquiries` |
| 36 | classifieds/setting-up-classifieds.md | Messages tab with open thread | `pro/portal-messages.png` | `?tab=messages` |

### 2g. Payments / Wallet (Pro)

| # | Doc | Shot | Filename | Where |
|---|---|---|---|---|
| 37 | payments/wallet-and-payments.md | Wallet tab with balance + ledger | `pro/portal-wallet.png` | `?tab=wallet` |
| 38 | payments/wallet-and-payments.md | Credits adapter mapping UI | `pro/credits-mapping.png` | Pro Settings → Credits → expanded row |
| 39 | payments/wallet-and-payments.md | Admin Transactions → Pending Approval | `pro/transactions-pending.png` | `/wp-admin/admin.php?page=wbam-transactions&filter=pending` |

### 2h. Analytics (Pro)

| # | Doc | Shot | Filename | Where |
|---|---|---|---|---|
| 40 | analytics/analytics-dashboard.md | Pro Analytics dashboard | `pro/analytics-dashboard.png` | `/wp-admin/admin.php?page=wbam-analytics` |
| 41 | analytics/analytics-dashboard.md | Portal Analytics tab | `pro/portal-analytics.png` | `?tab=analytics` |

### 2i. Pro Settings

| # | Doc | Shot | Filename | Where |
|---|---|---|---|---|
| 42 | settings/pro-settings-configuration.md | General tab | `pro/settings-general.png` | `wbam-pro-settings&tab=general` |
| 43 | settings/pro-settings-configuration.md | Modules tab | `pro/modules-toggle.png` (reuse 3) | — |
| 44 | settings/pro-settings-configuration.md | Credits tab | `pro/credits-adapters.png` (reuse 4) | — |
| 45 | settings/pro-settings-configuration.md | Pages tab | `pro/settings-pages.png` | `&tab=pages` |
| 46 | settings/pro-settings-configuration.md | Analytics & Privacy tab | `pro/settings-privacy.png` | `&tab=analytics_privacy` |
| 47 | settings/pro-settings-configuration.md | Emails tab | `pro/settings-emails.png` | `&tab=emails` |
| 48 | settings/creating-ad-packages.md | Packages list table | `pro/packages-list.png` | `/wp-admin/admin.php?page=wbam-packages` |
| 49 | settings/creating-ad-packages.md | Create package form | `pro/packages-form.png` | package edit screen |

### 2j. Memberships (Pro, Classifieds-scoped)

| # | Doc | Shot | Filename | Where |
|---|---|---|---|---|
| 50 | classifieds/setting-up-classifieds.md (new section) | Classifieds Membership Plans list | `pro/memberships-list.png` | `/wp-admin/admin.php?page=wbam-membership-plans` |
| 51 | classifieds/setting-up-classifieds.md | Edit plan form | `pro/memberships-edit.png` | `&action=edit&plan_id=2` |
| 52 | classifieds/setting-up-classifieds.md | Portal Membership tab | `pro/portal-membership.png` | `?tab=membership` |

### 2k. Flows

Each flow doc gets one "lead" shot that shows the end-state the flow promises.

| # | Doc | Shot | Filename | Where |
|---|---|---|---|---|
| 53 | flows/publish-first-ad.md | Frontend showing the first ad | `flows/publish-first-ad.png` | home page |
| 54 | flows/monetize-affiliate-links.md | Links list with click counts | `flows/monetize-links.png` | list, filter clicks > 0 |
| 55 | flows/accept-paid-ads.md | Portal Ads tab with a Running campaign | `flows/accept-paid-ads.png` | `?tab=ads` with active campaign |
| 56 | flows/launch-classifieds-marketplace.md | Frontend classifieds browse with mixed listings | `flows/classifieds-marketplace.png` | `/classifieds/` |
| 57 | flows/extend-via-hooks.md | Worked code sample + its admin effect | `flows/extend-hooks.png` | usually paired with a code block — the screenshot is the visible result |

### 2l. Troubleshooting

Only user-facing UI screenshots — no debug console dumps.

| # | Doc | Shot | Filename | Where |
|---|---|---|---|---|
| 58 | troubleshooting/common-issues.md | Ads list Status filter set to "Disabled" | `free/troubleshoot-disabled-filter.png` | list table with filter active |
| 59 | troubleshooting/pro-troubleshooting.md | Pro Settings → Pages — "Create" button | `pro/troubleshoot-pages.png` | pages tab |

---

## 3. Capture conventions

- **Browser:** Chromium via Playwright MCP, 1440×900 viewport for desktop shots, 390×844 for mobile variants when a doc specifically calls for mobile (rare).
- **PNG only.** 1× pixel density. Do not capture Retina unless the doc is making a point about sharpness (none do).
- **Crop.** Full page for list tables; screenshot a named element (`ref` / `element`) for single-card examples so the image isn't 70 % whitespace.
- **User.** Logged in as admin via `?autologin=1`.
- **Data state.** The populate scripts already ran — admin has ads, classifieds, campaigns, inquiries, messages, favorites, following, membership-plans. Do NOT overwrite the state mid-shoot; if a shot needs a specific view (e.g. "running campaign"), note it up-front so we set it once.
- **Filename rule.** Lowercase, kebab-case, no spaces, `.png`. Prefix with `free/` or `pro/` or `flows/`.
- **Alt text.** The doc's image line always supplies alt text — we never rely on the filename.
- **Consistency.** Same theme (currently BuddyX), same admin color scheme (Fresh), same browser zoom 100 %.

---

## 4. Setup before the shoot

- [ ] Admin color scheme: **Fresh** (WP default). Confirm via user profile.
- [ ] Frontend theme: **BuddyX** (already active on wp-ads.local).
- [ ] Turn off any "What's new" / welcome banners that overlap the main area on first-visit (admin already dismissed them but re-confirm before each capture).
- [ ] Flush caches once before the session: `wp cache flush` + clear any object cache.
- [ ] Disable query monitor / debug bars in the browser toolbar so they don't intrude.
- [ ] Admin notice suppression is already in place for plugin pages (enqueued via `in_admin_header:1`) — safe.

---

## 5. Execution order (proposed)

1. **Delete old images + scaffold new dirs** (`rm -rf docs/website/images && mkdir -p docs/website/images/{free,pro,flows}`).
2. **Batch 1 — Free plugin (shots 1, 2, 5–23, 58)** — single browser session, all admin-side Free screens + a few frontend ones.
3. **Batch 2 — Pro plugin (shots 3, 4, 24–52, 59)** — single session, all admin-side Pro screens + portal tabs.
4. **Batch 3 — Flows (shots 53–57)** — the glue shots that tie doc to outcome.
5. **Doc rewrite pass.** Insert each image into the named doc at the location called out in §2. Every image line gets alt text, not just the filename, and a one-sentence caption if the context isn't obvious.
6. **Verify rendered Markdown on GitHub** — open each PR preview to make sure the relative image paths resolve. (Path from `docs/website/<category>/<doc>.md` is `../images/<bucket>/<file>.png`.)

---

## 6. Decisions locked (2026-04-19)

- **Hosting:** GitHub only. No wbcom-docs MCP publish, no wbcomdesigns.com upload, no CDN dual-publish. Images live at `docs/website/images/` and render via GitHub's Markdown viewer.
- **Hero/index images:** None on `docs/README.md` or category landing docs. Category landing docs already link to their sub-pages; extra art adds noise without helping a reader.
- **Localisation:** English only for 2.8.0. Re-evaluate when a second locale actually ships.
- **BuddyBoss theme variant:** Out of scope — we ship one theme-agnostic set against BuddyX.

---

## 7. After the shoot — maintenance policy

- Whenever an admin screen's chrome changes (new tab, renamed menu, moved button), the screenshot in the corresponding doc gets updated in the same PR. `bin/verify-docs.sh` already catches banned phrases and broken links; extend it in Phase 3.5 to flag image references whose file mtime is older than the referenced file's last git-mod date.
- Quarterly audit: re-shoot the five most-visited docs (by search analytics) unconditionally.
- Any future "WB Ads → …" admin label change is a docs-touching change per §9 of DOCS-PLAN.

---

## 8. What this plan does not do

- **Video.** Out of scope.
- **Animated GIFs.** Out of scope — they age badly and don't render cleanly on the docs site.
- **Before / after compositions.** Out of scope — too opinionated, and we can't automate a re-shoot.
- **Screenshots for internal `pro-internal/` docs.** Those aren't customer-facing and stay text-only.

---

**Decide before shoot:** § 6 questions.
**Approve:** the 59-shot list in § 2.
Once both are green I'll delete the old tree, shoot Batch 1, and wire the images in before Batch 2.
