# WB Ad Manager (Free) - QA Checklist

The list to hand the QA team for a release. Tick every box, as the role named, on
branch `3.2.0` (or the release branch), with Pro active unless a line says otherwise.
Pro's list is `wb-ad-manager-pro/docs/qa/QA_CHECKLIST.md`; do Free first.

- **How to set up, rules, where to file:** [`AUDIT_PROTOCOL.md`](AUDIT_PROTOCOL.md)
  sections 1, 2, 6 and 7.
- **What each feature promises, and every screen and endpoint behind it:**
  [`FUNCTIONALITY_CATALOG.md`](FUNCTIONALITY_CATALOG.md) (IDs below match it).
- **Most-used flows, walked first:** [`CORE_PATHS.md`](CORE_PATHS.md).

Copy this file into your release report and fill it in: `[x]` = pass,
`[!] <card link>` = failed and filed, `[-] <reason>` = not covered (name the
environment you would need).

## 0. Before anything

- [ ] Both repos on the release branch; commit SHAs written in the report.
- [ ] `wp --exec='define("WP_ADMIN", true);' eval-file bin/qa-catalog-check.php` prints
      "Every surface is in a catalog" (else tell the developer first).
- [ ] `bash bin/qa-fixtures.sh` run; test users exist (visitor, advertiser A and B,
      seller, buyer, admin).
- [ ] Database exported before any destructive step.
- [ ] Mailpit open for this site.

## 1. On every feature below (the universal checks)

Run these on each feature, then its own lines. Full wording in AUDIT_PROTOCOL.md
section 4.

- Works end to end through the real screen, not only the API.
- Same result from every entry point (screen, REST, shortcode, block, widget, email).
- Every role that can reach it; a member cannot touch another member's items by ID.
- Empty, one, many (21+, paging), last item on the last page.
- What the screen says matches the database; dates shown in the site time zone.
- Side effects happen exactly once (emails, counts, balance). Double click and two
  tabs do not double anything.
- Errors say what happened and what to do next; no PHP notices in `debug.log`.
- 1440, 1024, 820 and 390 wide: no sideways scroll, nothing clipped, 44px tap targets.
- Light and dark theme; keyboard only; visible focus; labelled buttons.
- Plain words, the glossary in `docs/standards/glossary.md`, no raw keys or IDs.
- BuddyX (light and dark), Reign, a block theme and a default theme for public pages.

## 2. Free features

### F-ADS - Create and manage ads (admin)
- [ ] Ads > Add New: create an image ad, tick Header, publish; it shows on the front page with no other settings (zero-config).
- [ ] Every meta box saves and reloads its value: Ad Settings, Status, Sizing, Pro options.
- [ ] All Ads status column shows Live / Scheduled / Ended / Not showing with one reason that matches what a visitor sees.
- [ ] Duplicate, tag, trash and restore an ad; bulk actions on 20+ ads.
- [ ] An ad with no placement explains why it is not showing.
- [ ] Abilities and REST: list, get, create, update, delete an ad as admin; refused for a subscriber.

### F-TYPES - Ad types
- [ ] Image: size is read from the preview; unreadable image says "Couldn't read the image size - pick a size".
- [ ] Rich content renders with the theme's styles and no broken markup.
- [ ] Code/HTML ad renders on the front end and never runs in the admin list.
- [ ] AdSense without Slot ID stays Draft with a notice; the list shows "Slot ID missing"; Publisher ID falls back to the site one.
- [ ] Email capture ad collects an address (see F-CAPTURE).

### F-PLACE - Automatic placements (visitor)
- [ ] Header, footer, in content, after paragraph N, before and after archives, between comments: each shows where it says, on every theme in the matrix.
- [ ] Block theme: ads sit inside the FSE header and footer and on block archives.
- [ ] Format matching: a placement only takes sizes that fit, and says so in the editor.
- [ ] Sticky and popup: restrained by default, close with keyboard and screen reader, stay closed for the repeat period, no layout shift.

### F-MANUAL - Shortcodes, blocks, widgets
- [ ] `[wbam_ad id=...]` and `[wbam_ads]` on a page show the right ad(s).
- [ ] The WB Ad block and the Placement block: editor preview matches the front end.
- [ ] The classic ad widget in a sidebar.
- [ ] An empty shortcode or block shows a hint to admins only and nothing to visitors.

### F-TARGET - Targeting and scheduling
- [ ] Display rules: show/hide by page type; verify on each page type.
- [ ] Visitor conditions: device, logged-in state, role; verify as visitor and member, desktop and phone.
- [ ] Schedule: start and end dates picked in the site time zone, stored in UTC, the ad appears and disappears on time.
- [ ] Geo: off by default; with a provider set (Settings > Location) an ad limited to a country shows only there; the uploaded geo file is not downloadable by URL.

### F-ROTATE - Rotation and tracking
- [ ] Three ads in one placement rotate by priority over repeated loads.
- [ ] Each view and click counts once; admins and bots are not counted.
- [ ] With page caching on, rotation still changes.

### F-STATS - Ad performance
- [ ] Ad editor Performance box: impressions, clicks, CTR by day.
- [ ] The same numbers in the All Ads list, the REST/abilities stats and Pro's Ad Analytics.

### F-SETTINGS - Settings
- [ ] Every section (Ads & Display, Links, Location, Privacy & Data, Tools & License) saves with one Save and keeps each option in one place.
- [ ] Change each option and see its effect somewhere, then restore (`OWNER_INVENTORY.md` lists every option and default).
- [ ] Privacy says IPs are always hashed; one privacy switch only, with Pro active.
- [ ] Free without Pro: its own General section appears and works.

### F-SETUP - First run and sample content
- [ ] Fresh install: the setup wizard reaches a visible ad in minutes; with Pro active there is one wizard.
- [ ] Sample ads read "Sample ad: replace me".
- [ ] Settings > Tools & License > Sample content > Remove lists every item first, then removes only what the plugins added (DB export first).
- [ ] First-run pointers and the setup banner can be dismissed and stay dismissed.

### F-LINKS - Link manager
- [ ] Create a link; `/go/<slug>` redirects; clicks are counted.
- [ ] Link categories; `[wbam_link]`, `[wbam_link_url]`, `[wbam_links]` output.
- [ ] Change the link prefix: the old prefix still redirects.
- [ ] Turn Link Manager off: existing `/go/` links still redirect.

### F-PARTNER - Partnership inquiries
- [ ] `[wbam_partnership_inquiry]` submits as a visitor; double submit makes one request.
- [ ] Ads > Links > Partnerships: accept and reject; admin and requester emails arrive (600 and 390 wide).

### F-CAPTURE - Email capture
- [ ] A visitor subscribes through an email-capture ad.
- [ ] Ads > Delivery > Email Captures: list pages at 2,000+ rows, CSV export opens cleanly (no formula injection), delete works.

### F-BP / F-BBP / F-JETO - Community plugins
- [ ] BuddyPress activity, directories, profile and group widgets show ads; ads survive "load more".
- [ ] No notification bells or activity posts are created for ads.
- [ ] bbPress forum and topic ads and widgets.
- [ ] Jetonomy pages show their placement (Jetonomy active).

### F-PRIVACY - Personal data export and erase (admin)
- [ ] Tools > Export Personal Data for an email subscriber: the zip lists their sign-ups (email, name, ad, date).
- [ ] Same for a partnership requester: name, email, website, message, IP, date.
- [ ] Tools > Erase Personal Data for each: their rows are gone; another person's rows stay.
- [ ] A person with 101+ rows: export has all of them; erase removes all of them.

### F-UPGRADE - Update and uninstall
- [ ] Update from the previous release on a copy of real data: ads, stats and settings kept; the UTC migration runs once.
- [ ] Uninstall with "Delete data on uninstall" off keeps data; on, lists what goes and removes it.

## 3. Do not re-file

- Owner decisions that are not bugs: AUDIT_PROTOCOL.md section 5.
