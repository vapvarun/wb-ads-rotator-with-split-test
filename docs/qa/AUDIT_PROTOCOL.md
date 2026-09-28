# WB Ad Manager - Independent Audit Protocol (Free + Pro)

How an auditor (a person or an agent) checks every feature of WB Ad Manager Free and
Pro for **functionality and UX**, on every release. The feature lists are:

- Free: [`FUNCTIONALITY_CATALOG.md`](FUNCTIONALITY_CATALOG.md) (this folder)
- Pro: `wb-ad-manager-pro/docs/qa/FUNCTIONALITY_CATALOG.md`

Your job is to **find and file**, not to fix. Do not push to either repo. Every
finding becomes a Basecamp card (section 6). The owner and the developer decide what
gets fixed.

---

## 1. Before you start (once per release)

1. **Code.** Both repos on the release branch at origin HEAD
   (`git -C <repo> pull --ff-only`). Write the two commit SHAs into your report.
   Pro needs Free: always audit them together.
2. **Site.** A Local site with both plugins active (the dev site is `wp-ads.local`).
   Pro's site mode matters: record it (`wp option get wbam_pro_settings --format=json`,
   key `site_mode`) and walk the features the mode includes (section 3).
3. **Catalog is current.** Run the surface check from the Free repo:

   ```bash
   wp --exec='define("WP_ADMIN", true);' eval-file bin/qa-catalog-check.php
   ```

   It lists every admin screen, REST route, shortcode, block, widget, AJAX and
   admin-post handler, cron job, post type, taxonomy, placement, ad type and email that
   the running site registers for Free and Pro. It then compares them with both
   catalogs. **MISSING** means the release added a surface nobody catalogued: add it
   to the right feature before auditing (or file a card if you cannot tell what it
   does, which is itself a finding). **STALE** means a catalogued surface is gone:
   confirm it was removed on purpose. Add `list` at the end to print everything with
   its file:line.
4. **People.** `bash bin/qa-fixtures.sh` (in each repo) creates the test users from
   `docs/qa/qa-config.json` -> `personas`. Log in with `?autologin=<login>`; never type
   passwords. The ladder:

   | Persona | Login | Use it for |
   |---|---|---|
   | anon | (logged out) | every public page, ad rendering, the Advertise page |
   | advertiser-a | `qa_advertiser_a` | owns ads, campaigns and a balance |
   | advertiser-b | `qa_advertiser_b` | must never see or change A's data |
   | seller | `qa_seller` | classified listings |
   | buyer | `qa_buyer` | browses, favourites, contacts sellers; is not an advertiser |
   | admin | `1` | the site owner. The control rung: never the only role you test |

5. **Email.** Each Local site has Mailpit. Find the port with
   `lsof -iTCP -sTCP:LISTEN | grep mailpit`; read real messages from
   `http://localhost:<port>/api/v1/messages`. An email log row is not proof of delivery.
6. **Money.** Test with manual credit first (Pro: Advertisers > Adjust Balance).
   Payment gateways (Stripe, PayPal, WooCommerce) are tested **last**, in sandbox mode,
   with credentials the owner gives you. Never put credentials in cards, chat or commits.

## 2. Ground rules

- **Restore what you change.** Every setting, mode or module you flip goes back, and
  your report says what you touched. Prefer changes scoped to your test users.
- **Never run destructive tools without a backup.** Take a DB dump before "Remove sample
  content", uninstall or any bulk delete (`wp db export <path outside the web root>`).
- **No files in the web root.** Screenshots go to `~/Local Sites/<site>/app/qa-artifacts/<release>/`.
- **Reproduce before you file.** Reading code is not reproducing. Check your own harness
  first: a wrong URL parameter, role or empty fixture fakes a bug convincingly.
- **Stay in scope.** The plugins own their screens, templates, options, tables, emails
  and REST. The theme's markup, another plugin's data and server config are context,
  not findings against us. But a plugin screen that breaks because the theme styles
  bare `button`/`a`/`input` **is** our defect: the plugin must own its look.

## 3. Which features to walk

Every feature in both catalogs, in this order:

1. **Core paths first:** `docs/qa/CORE_PATHS.md` in each repo (Free before Pro). A
   broken core path outranks everything else in your report.
2. **Then every feature row**, in catalog order. Each row names its roles, where it
   lives, its surfaces and the user doc that defines the promise.
3. **Pro site modes.** A feature exists only in the modes whose bundle includes it
   (`Site_Mode::module_bundle()`, `includes/Core/class-site-mode.php`). Walk the release
   in **Full** mode, then switch to **Publisher** and confirm Pro-only screens and
   portal features are hidden and their data kept, then restore the mode.
   Switching a mode off must never delete data.
4. **Theme matrix** for every public surface: BuddyX (light and dark), Reign, a
   block theme (Twenty Twenty-Five) and one classic default theme.

## 4. The checklist for every feature

Answer each line that applies. Write down which ones you checked.

**Functionality**
- [ ] Does it do what its user doc says, end to end, through the real UI (not only REST)?
- [ ] **Every entry point**: frontend page, admin screen, REST route, shortcode, block,
      widget, email, cron. The same action gives the same result from each.
- [ ] **Every role** that can reach it, including advertiser-b trying A's objects by ID
      (REST and AJAX too). Logged out must be refused cleanly, never a PHP error.
- [ ] **Object states**: none, one, many (21+ so paging shows), last item on the last
      page, draft/pending/live/ended, deleted parent.
- [ ] **Data**: what the screen shows matches the database, and dates are stored in UTC
      and shown in the Settings > General time zone.
- [ ] **Side effects** fire once each: emails, balance changes, revenue rows, counters,
      hooks. Double submit and two tabs must not double charge or double send.
- [ ] **Settings that drive it**: change each one, walk every place it is read, restore.
- [ ] **Error paths**: bad input, expired link, insufficient balance, missing
      configuration. Each tells the person what happened and what to do next.

**UX and presentation**
- [ ] **Owner's eye**: could a site owner who never reads code set this up and trust it?
      Zero-config default works; no setting needed for the common case.
- [ ] **Words**: plain sentences, the owner's labels, the glossary in
      `docs/standards/glossary.md` (Balance and Add funds, never "Credits" or "Wallet"
      on screens; always "Placement"). No raw keys, slugs or IDs shown to people. No
      em dashes in translated strings.
- [ ] **Consistency**: every label, badge, count, status and empty state on the page
      agrees with the object's real state.
- [ ] **Feedback**: success survives the reload it triggers; errors appear without a
      reload; destructive confirms focus Cancel; no browser `alert()`/`confirm()`.
- [ ] **Layout** at 1440, 1024, 820 and 390 wide: no horizontal scroll, touch targets
      at least 44px, nothing clipped.
- [ ] **Dark mode and RTL** where the theme supports them.
- [ ] **Accessibility**: keyboard reachable in order, visible focus, labelled controls,
      popups and sticky ads closable by keyboard and screen reader.
- [ ] **Ads as visitors see them**: every ad type in every placement it supports, as
      anon, at 1280 and 390, light and dark: no layout shift, a readable "Ad" label.
- [ ] **Reports**: numbers agree across screens (portal vs admin vs export) and with the
      ledger.
- [ ] **Emails** rendered at 600 and 390 wide: every link resolves, no empty fields,
      correct amounts and dates, no overflow.
- [ ] **Scale**: lists are paged, filterable and fast at 2,000+ rows (seed with WP-CLI).

## 5. Decided already (do not file these as bugs)

The owner's decisions for 3.2.0 are recorded on Basecamp card 10342761510 ("[3.2.0]
Owner decisions", project 44982066); its comments are the source of truth. Highlights:

- Revenue is counted once, at top-up. Spending a balance is usage. Complimentary credit
  is never revenue.
- Refunds return only the unspent balance, capped, never negative. Delivered
  impressions and clicks are final.
- The currency symbol always sits before the amount; decimals follow the currency.
- Featured is one upgrade at one price, one-time (no recurring). "Premium" listings are gone.
- New installs start in Publisher mode; Pro creates no pages until the owner opts in.
- No guest purchase: visitors log in or register first.
- BuddyPress is a placement target only: no notification bells or activity posts for ads.
- Rare technical options are filters, not settings (plug-and-play), but business
  controls (mode, placements, prices, packages, approval, payments, labels, currency,
  pages, privacy, uninstall) always stay settings.
- IPs are always hashed; Pro has one privacy switch.
- Minimums: PHP 8.1 and WordPress 6.9.

If you think a decision hurts site owners, file it as a **question** card, not a bug.

## 6. Filing findings

Basecamp project **WB Ad Manager** (`44982066`), column **Triage** (`9334950390`).
A finding in the bundled Credits SDK goes to project **Wbcom Credits SDK**
(`49046587`), column Triage (`10344574141`), linked from a WBAM card.

- **Before filing**, search Bugs, Ready for Testing and In Testing for the same thing.
- **Title**: `[<version>] <what is wrong, in plain words>`, e.g.
  `[3.2.0] Advertiser B can open Advertiser A's campaign stats by ID`.
- **Body** (every field required):
  - **Feature:** the catalog ID (e.g. `P-CAMP`) and the checklist line that failed.
  - **Where:** URL or screen, role, viewport, theme, and the file:line if you found it
    (with the commit SHA).
  - **Steps:** exact steps a new person can follow, with the data used.
  - **Seen vs expected:** what happened, and what the user doc or owner decision says
    should happen.
  - **Evidence:** screenshot path, email id, DB row, response body.
  - **Who it costs:** which owners or users, and how (money, privacy, blocked task,
    trust).
  - **Fix pointer:** optional, the smallest fix you would suggest.
- One finding per card. Group only true duplicates. A presentation defect is a real
  finding; a personal preference is not (see section 5).

## 7. Your report

One markdown file per release under
`~/Documents/sites/wbadmanager.com/reports/<date>-<version>-independent-audit.md`:

- the two commit SHAs, site mode, themes and viewports used;
- the output of `qa-catalog-check.php`;
- per feature ID: PASS / FAIL (card link) / NOT COVERED (and why: which environment
  you would need);
- the list of everything you changed and restored.

A "not covered" line the site could have answered (seed a fixture, flip a setting and
restore) is unfinished work, not a result.

## 8. Keeping the catalog alive (every release)

The developer updates the catalogs in the same change that adds or removes a surface,
and `qa-catalog-check.php` must print "Every surface is in a catalog" before release.
When an audit or a customer finds a miss, add the checklist line that would have caught
it to section 4 here, and name the card it came from.
