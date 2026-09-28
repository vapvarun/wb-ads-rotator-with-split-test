# WB Ad Manager (Free) - Functionality Catalog

Every feature of the free plugin, with every place it can be reached. An independent
auditor walks each row with the checklist in [`AUDIT_PROTOCOL.md`](AUDIT_PROTOCOL.md)
section 4. Pro's catalog is `wb-ad-manager-pro/docs/qa/FUNCTIONALITY_CATALOG.md`;
audit Free first.

**How to read a row**
- **Promise**: what the owner or visitor expects, in their words.
- **Where**: the screen or page a person uses.
- **Surfaces**: every registered entry point, as `kind:id`. `bin/qa-catalog-check.php`
  fails the release when the running site has a surface that no row lists, or a row
  lists one that no longer exists. Keep this list exact.
- **Expected behaviour**: the user doc that defines the promise
  (`docs/website/...`). A mismatch between the doc and the plugin is a finding either
  way.
- **Core**: the rank in `CORE_PATHS.md`, if the feature is on a core path.

Kinds: `admin` screen slug, `rest` route, `shortcode`, `block`, `widget`, `ajax`
action, `post` (admin-post action), `cron` hook, `cpt` post type, `tax` taxonomy,
`placement` class, `adtype` class, `email` method.

---

## F-ADS - Create and manage ads

- **Promise:** I create an ad, choose where it shows, publish it and see it on my site.
  I can tag, duplicate, schedule and unpublish ads, and the list tells me which ads are
  actually showing and why not.
- **Roles:** admin (creates); anon (sees the ad).
- **Where:** Ads > All Ads, Ads > Add New (block editor meta boxes), Ads > Tags.
- **Surfaces:** `cpt:wbam-ad` `tax:wbam_ad_tag` `rest:/wbam/v1/ads`
  `rest:/wbam/v1/ads/(?P<id>\d+)` `rest:/wbam/v1/ads/(?P<id>\d+)/duplicate`
  `rest:/wbam/v1/ads/types`
- **Expected behaviour:** `docs/website/usage/000-creating-and-managing-ads.md`
- **Core:** 1
- **Also check:** the status column (Live / Scheduled / Ended / Not showing, with one
  reason) matches what a visitor sees; an ad with no placement explains itself.

## F-TYPES - Ad types

- **Promise:** I can run an image banner, rich content, raw code/HTML, a Google AdSense
  unit or an email-capture box, and each one renders correctly in any placement.
- **Roles:** admin; anon.
- **Where:** the ad editor's type picker.
- **Surfaces:** `adtype:Image_Ad` `adtype:Rich_Content_Ad` `adtype:Code_Ad`
  `adtype:AdSense_Ad` `adtype:Email_Capture_Ad`
- **Expected behaviour:** `docs/website/features/000-ad-types.md`,
  `docs/website/integrations/030-google-adsense.md`
- **Core:** 1, 5
- **Also check:** image size detection from the editor preview ("Couldn't read the
  image size - pick a size" when unreadable); an AdSense ad with no Slot ID stays Draft
  and says what to add; code ads never execute in the admin list.

## F-PLACE - Automatic placements

- **Promise:** I tick where an ad goes (header, footer, in content, after paragraph N,
  before or after archives, between comments, sticky bar, popup) and it appears there
  on my theme, on desktop and phone, without editing templates.
- **Roles:** admin; anon.
- **Where:** ad editor > Placements; Settings > placement defaults.
- **Surfaces:** `placement:Header_Placement` `placement:Footer_Placement`
  `placement:Content_Placement` `placement:Paragraph_Placement`
  `placement:Before_Archive_Placement` `placement:After_Archive_Placement`
  `placement:Comment_Placement` `placement:Sticky_Placement`
  `placement:Popup_Placement` `rest:/wbam/v1/ads/placements`
- **Expected behaviour:** `docs/website/features/010-placements.md`
- **Core:** 2
- **Also check:** block themes (ads inside the FSE header/footer and on block archives);
  format matching (a placement only takes sizes that fit, and says so); sticky and popup
  close by keyboard, stay closed for the repeat period, and have restrained defaults.

## F-MANUAL - Shortcodes, blocks and widgets

- **Promise:** I can drop one ad or a rotating group anywhere with a shortcode, a block
  or a widget.
- **Roles:** admin (inserts); anon (sees).
- **Where:** any post or page editor; Appearance > Widgets.
- **Surfaces:** `shortcode:wbam_ad` `shortcode:wbam_ads` `placement:Shortcode_Placement`
  `block:wb-ads/ad` `block:wb-ads/placement` `widget:wbam_ad_widget`
  `placement:Widget_Placement`
- **Expected behaviour:** `docs/website/shortcodes/000-ad-shortcodes.md`
- **Also check:** an empty shortcode or block prints an admin-only hint and nothing for
  visitors; the block editor preview matches the front end.

## F-TARGET - Targeting and scheduling

- **Promise:** I limit an ad by page type, device, logged-in state, role, country and
  dates, and it shows only where the rules say.
- **Roles:** admin; anon, logged-in member (to prove the rules).
- **Where:** ad editor > Display Rules, Visitor Conditions, Schedule; Settings > Location.
- **Surfaces:** `ajax:wbam_upload_geo_db`
- **Expected behaviour:** `docs/website/features/030-targeting-and-scheduling.md`
- **Core:** 3
- **Also check:** start and end dates are picked and shown in the site time zone and
  stored in UTC; geo is off by default and says which provider it uses; the uploaded
  geo database is not publicly downloadable.

## F-ROTATE - Rotation, serving and tracking

- **Promise:** several ads in one placement rotate by priority, and every impression and
  click is counted once.
- **Roles:** anon (views, clicks); admin (reads the counts).
- **Where:** any page with two or more ads in one placement.
- **Surfaces:** `rest:/wbam/v1/ads/serve` `rest:/wbam/v1/ads/track`
  `rest:/wbam/v1/analytics/track` `ajax:wbam_track_click`
- **Expected behaviour:** `docs/website/features/020-rotation-and-split-testing.md`
- **Core:** 4
- **Also check:** page caching does not freeze rotation; bots and admins are not
  counted; a double click counts once.

## F-STATS - Ad performance

- **Promise:** I see impressions, clicks and CTR per ad and for the site, by day.
- **Roles:** admin.
- **Where:** ad editor > Performance meta box; the All Ads list columns.
- **Surfaces:** `rest:/wbam/v1/ads/(?P<id>\d+)/stats` `rest:/wbam/v1/analytics/overview`
  `rest:/wbam/v1/analytics/daily` `rest:/wbam/v1/analytics/ads/(?P<id>\d+)`
- **Expected behaviour:** `docs/website/features/020-rotation-and-split-testing.md`
- **Core:** 4
- **Also check:** the numbers match between the ad editor, the dashboard and REST;
  day buckets follow the site calendar day.

## F-SETTINGS - Settings

- **Promise:** one settings screen, one section per topic, each option in one place,
  with sensible defaults so most sites never open it.
- **Roles:** admin.
- **Where:** Ads > Settings (sections in the left sidebar).
- **Surfaces:** `admin:wbam-settings` `rest:/wbam/v1/settings`
  `rest:/wbam/v1/settings/display` `post:wbam_dismiss_size_matching`
- **Expected behaviour:** `docs/website/usage/010-settings.md`
- **Also check:** every option is read somewhere (a saved option that changes nothing
  is a finding); privacy line says IPs are always hashed; with Pro active the layout
  and save flow stay uniform across Free and Pro sections.

## F-SETUP - First run, samples and help

- **Promise:** the setup wizard gets me to a working ad in minutes, sample ads say they
  are samples, and I can remove everything the plugin added in one step.
- **Roles:** admin.
- **Where:** the setup wizard (`index.php?page=wbam-setup`), first-run pointers,
  Ads > Help & Docs, Ads > Settings > Tools & License > Sample content.
- **Surfaces:** `admin:wbam-setup` `ajax:wbam_dismiss_setup` `post:wbam_skip_setup`
  `ajax:wbam_dismiss_pointer` `post:wbam_clear_demo_data` `admin:wbam-help`
- **Expected behaviour:** `docs/website/usage/020-setup-wizard.md`,
  `docs/website/getting-started/010-quick-setup.md`
- **Core:** 7
- **Also check:** with Pro active there is one wizard (Free hands off to Pro); Remove
  lists item by item what it will delete and deletes only what the plugin added (take a
  DB dump first).

## F-LINKS - Link management (cloaking)

- **Promise:** I cloak affiliate links at `/go/slug`, group them, insert them with a
  shortcode and see their clicks; old links never break.
- **Roles:** admin; anon (clicks).
- **Where:** Ads > Links > All Links, Link Categories.
- **Surfaces:** `admin:wbam-links` `admin:wbam-link-categories` `rest:/wbam/v1/links`
  `rest:/wbam/v1/links/(?P<id>\d+)` `rest:/wbam/v1/links/(?P<id>\d+)/stats`
  `rest:/wbam/v1/links/(?P<id>\d+)/track` `rest:/wbam/v1/links/categories`
  `ajax:wbam_track_link_click` `shortcode:wbam_link` `shortcode:wbam_link_url`
  `shortcode:wbam_links`
- **Expected behaviour:** `docs/website/features/040-link-management.md`,
  `docs/website/shortcodes/010-link-shortcodes.md`
- **Core:** 6
- **Also check:** after a prefix change the old prefix still redirects; with Link Manager
  off, existing `/go/` links still redirect; link rel follows `wbam_ad_link_rel`.

## F-PARTNER - Partnership inquiries

- **Promise:** a visitor asks to buy a link or sponsorship through a form; I review,
  accept or reject it, and both sides get an email.
- **Roles:** anon (submits); admin (reviews).
- **Where:** `[wbam_partnership_inquiry]` on a page; Ads > Links > Partnerships.
- **Surfaces:** `shortcode:wbam_partnership_inquiry` `ajax:wbam_submit_partnership`
  `admin:wbam-partnerships` `rest:/wbam/v1/partnerships`
  `rest:/wbam/v1/partnerships/(?P<id>\d+)`
  `email:Partnership_Emails::notify_admin_new_inquiry`
  `email:Partnership_Emails::notify_requester_accepted`
  `email:Partnership_Emails::notify_requester_rejected`
- **Expected behaviour:** `docs/website/shortcodes/020-partnership-inquiry-form.md`
- **Also check:** spam and double submit; the three emails at 600 and 390 wide.

## F-CAPTURE - Email capture

- **Promise:** an email-capture ad collects addresses with consent, and I can list,
  export and delete them.
- **Roles:** anon (subscribes); admin (manages).
- **Where:** any email-capture ad; Ads > Delivery > Email Captures.
- **Surfaces:** `ajax:wbam_email_capture` `admin:wbam-email-captures`
  `rest:/wbam/v1/email-captures` `post:wbam_export_email_captures`
  `post:wbam_delete_email_capture`
- **Expected behaviour:** `docs/website/features/050-email-capture.md`
- **Also check:** the CSV opens cleanly and contains no formula injection; the list
  pages at 2,000+ rows; personal data export and erase include captures.

## F-BP - BuddyPress

- **Promise:** ads show in the activity stream, member and group directories and profiles.
  BuddyPress is a placement target only.
- **Roles:** anon, member.
- **Where:** BuddyPress pages; Appearance > Widgets.
- **Surfaces:** `placement:BP_Activity_Placement` `placement:BP_Directory_Position`
  `widget:wbam_bp_activity_ad` `widget:wbam_bp_group_ad` `widget:wbam_bp_profile_ad`
- **Expected behaviour:** `docs/website/integrations/000-buddypress.md`
- **Core:** 8
- **Also check:** activity ads survive "load more"; no notification bells or activity
  posts are created for ads.

## F-BBP - bbPress

- **Promise:** ads show in forums and topics.
- **Roles:** anon, member.
- **Where:** bbPress forum and topic pages; widgets.
- **Surfaces:** `placement:bbPress_Placement` `widget:wbam_bbpress_forum_ad`
  `widget:wbam_bbpress_topic_ad`
- **Expected behaviour:** `docs/website/integrations/010-bbpress.md`
- **Core:** 8

## F-JETO - Jetonomy

- **Promise:** ads show in Jetonomy community pages.
- **Roles:** anon, member.
- **Where:** Jetonomy pages (needs Jetonomy active).
- **Surfaces:** `placement:Jetonomy_Placement`
- **Expected behaviour:** `docs/website/integrations/020-jetonomy.md`

## F-UPGRADE - Upgrades and background jobs

- **Promise:** updating the plugin keeps my ads, stats and settings, and old dates are
  converted to UTC once.
- **Roles:** admin (updates); none (cron).
- **Where:** Plugins screen; WP-Cron / Action Scheduler.
- **Surfaces:** `cron:wbam_utc_migration`
- **Expected behaviour:** `docs/website/getting-started/000-installation.md`
- **Also check:** update from the previous release on a copy of real data; the job runs
  once and is idempotent; uninstall removes data only when "Delete data on uninstall"
  is on and lists what goes.

## Not a registered surface (walk them anyway)

- **REST and Abilities API** for developers: `docs/website/developer-guide/`. Every
  route above must refuse other people's objects and never change a field's type
  between releases.
- **Hooks reference**: `docs/website/developer-guide/010-hooks-and-filters.md`,
  generated from docblocks; `bin/check-hooks-documented.sh` must pass.
- **Consent plugins** (AdSense and tracking respect cookie consent):
  `docs/website/integrations/030-google-adsense.md`.
