# WB Ad Manager - Development Roadmap

**Plugin Name:** WB Ad Manager
**Premium Name:** WB Ad Manager Pro
**Current Version:** 2.0.0
**Last Updated:** December 1, 2024

---

## Overview

WB Ad Manager is a modular WordPress ad management plugin with BuddyPress integration. The free version provides core ad management features, while the Pro version adds advanced targeting, analytics, advertiser portal, and payment integration.

---

## Current Status Summary

| Component | Status |
|-----------|--------|
| Core Plugin Structure | ✅ Complete |
| Ad Types (4) | ✅ Complete |
| Setup Wizard | ✅ Complete |
| All Placements (14) | ✅ Complete |
| Admin UI & Metaboxes | ✅ Complete |
| Settings Page | ✅ Complete |
| Admin CSS/JS | ✅ Complete |
| Frontend CSS/JS | ✅ Complete |
| BuddyPress Module | ✅ Complete |
| BuddyPress Widgets (3) | ✅ Complete |
| bbPress Module | ✅ Complete |
| bbPress Widgets (2) | ✅ Complete |
| Targeting Engine | ✅ Complete |
| Content Analyzer | ✅ Complete |
| Display Rules (Include/Exclude) | ✅ Complete |
| Visitor Conditions | ✅ Complete |
| Basic Scheduling | ✅ Complete |
| Geo-Targeting (3 providers) | ✅ Complete |
| Advanced Scheduling | ✅ Complete |
| Frequency Control | ✅ Complete |
| Sticky/Floating Ads | ✅ Complete |
| Popup/Modal Ads | ✅ Complete |
| Comment Ads | ✅ Complete |
| Pro Features | 🔲 Pro Plugin |

---

## FREE VERSION PHASES

### Phase 1: Core Foundation ✅ COMPLETE

- [x] Plugin bootstrap with PSR-4 style namespaces (`WBAM\`)
- [x] Singleton trait for instance management
- [x] Custom Post Type `wbam-ad`
- [x] Module-based architecture
- [x] Placement Engine with interface-based placements
- [x] Admin metaboxes with card-based UI
- [x] Settings page with global options
- [x] Frontend asset loading

**Ad Types Implemented:**
- [x] Image ads (with link, alt text, target)
- [x] Rich Content (HTML textarea)
- [x] Code ads (custom HTML/JS)
- [x] Google AdSense (auto script management, multiple formats, Auto Ads support)

**Placements Implemented:**
- [x] Header (`wp_head`)
- [x] Footer (`wp_footer`)
- [x] Before Content
- [x] After Content
- [x] After Paragraph X (with repeat option)
- [x] Archive (between posts)
- [x] Widget
- [x] Shortcode `[wbam_ad id="X"]` and `[wbam_ads ids="X,Y,Z"]`
- [x] BuddyPress Activity Stream

**Settings Implemented:**
- [x] Disable ads for logged-in users
- [x] Disable ads for admins
- [x] Minimum content length for paragraph ads
- [x] Disable on specific post types
- [x] Ad label text & position
- [x] Custom container CSS class
- [x] Lazy load option
- [x] Cache ad queries option
- [x] Google AdSense Publisher ID (global)
- [x] Google AdSense Auto Ads toggle

**Setup Wizard:**
- [x] First-time activation wizard
- [x] 3 sample ads creation (image, rich content, code)
- [x] Auto-placement setup
- [x] Skip/dismiss option

**Targeting Implemented:**
- [x] Targeting Engine with rule processing
- [x] Display Rules metabox
  - [x] Show on all pages / specific pages
  - [x] Include by post types
  - [x] Include by page types (front, blog, archive, search, 404)
  - [x] Include by categories
  - [x] Include by tags
  - [x] Exclude by page types
  - [x] Exclude by categories
  - [x] Exclude by tags
- [x] Schedule metabox
  - [x] Start date
  - [x] End date
- [x] Visitor Conditions metabox
  - [x] Device targeting (desktop, tablet, mobile)
  - [x] User status (all, logged in, logged out)
  - [x] User roles
- [x] Geo Targeting metabox
  - [x] Country include/exclude
  - [x] IP-based geolocation (ip-api.com)
  - [x] BuddyPress profile location fallback
  - [x] Unknown location handling
  - [x] Geo cache with transients

**Files Created:**
```
wb-ad-manager/
├── wb-ad-manager.php
├── readme.txt
├── ROADMAP.md
├── Gruntfile.js
├── package.json
├── assets/
│   ├── css/admin.css
│   ├── css/admin.min.css
│   ├── css/frontend.css
│   ├── css/frontend.min.css
│   ├── js/admin.js
│   ├── js/admin.min.js
│   ├── js/frontend.js
│   └── js/frontend.min.js
├── languages/
│   └── wb-ad-manager.pot
└── includes/
    ├── Core/
    │   ├── trait-singleton.php
    │   └── class-plugin.php
    ├── Admin/
    │   ├── class-admin.php
    │   ├── class-settings.php
    │   ├── class-display-options.php
    │   └── class-setup-wizard.php
    ├── Frontend/
    │   └── class-frontend.php
    └── Modules/
        ├── AdTypes/
        │   ├── interface-ad-type.php
        │   ├── class-image-ad.php
        │   ├── class-rich-content-ad.php
        │   ├── class-code-ad.php
        │   └── class-ad-sense-ad.php
        ├── Placements/
        │   ├── interface-placement.php
        │   ├── class-placement-engine.php
        │   ├── class-header-placement.php
        │   ├── class-footer-placement.php
        │   ├── class-content-placement.php
        │   ├── class-paragraph-placement.php
        │   ├── class-archive-placement.php
        │   ├── class-widget-placement.php
        │   └── class-shortcode-placement.php
        ├── Targeting/
        │   ├── interface-targeting-rule.php
        │   └── class-targeting-engine.php
        ├── GeoTargeting/
        │   └── class-geo-engine.php
        └── BuddyPress/
            ├── class-bp-module.php
            └── class-bp-activity-placement.php
```

---

### Phase 2: Advanced Scheduling & Frequency ✅ COMPLETE

**Advanced Scheduling:**
- [x] Add day-of-week targeting (Mon, Tue, Wed, etc.)
- [x] Add time-of-day targeting (time range)
- [x] Uses site timezone

**Frequency Control:**
- [x] Create `class-frequency-manager.php`
- [x] Maximum ads per page setting (in Settings)
- [x] Maximum ads per session (cookie-based, per-ad setting)
- [x] Ad rotation/randomization (weighted random)
- [x] Priority/weight system for ads (1-10 scale)
- [x] Add priority field to ad metabox
- [x] Add session limit field to ad metabox

**Content Analysis:**
- [x] Create `class-content-analyzer.php`
- [x] Detect post length (character, word count)
- [x] Count paragraphs, headings, images, links
- [x] Reading time estimation
- [x] Smart ad position suggestions based on content

---

### Phase 3: Additional Placements ✅ COMPLETE

**New WordPress Placements:**
- [x] Floating/sticky ads (corner, bottom bar) - `class-sticky-placement.php`
  - Bottom Right
  - Bottom Left
  - Bottom Bar (Full Width)
  - Top Bar (Full Width)
- [x] Popup/modal ads (with frequency limit) - `class-popup-placement.php`
  - Time Delay trigger
  - Scroll Percentage trigger
  - Exit Intent trigger
- [x] Frontend JS for sticky/popup functionality
- [x] Comment placements - `class-comment-placement.php`
  - Before Comment Form
  - After Comment Form
  - Between Comments (with repeat option)

**Additional BuddyPress Placements:**
- [x] In member directory - `class-bp-directory-placement.php`
  - Before Members List
  - After Members List
  - Between Members (with repeat option)
- [x] In group directory - `class-bp-directory-placement.php`
  - Before Groups List
  - After Groups List
  - Between Groups (with repeat option)
- [x] BuddyPress Widgets - `class-bp-widgets.php`
  - Profile Ad Widget (shows on member profiles)
  - Group Ad Widget (shows on group pages)
  - Activity Ad Widget (shows on activity pages)

**bbPress Placements (if bbPress active):**
- [x] bbPress Module - `class-bbpress-module.php`
  - Before/After Forum List
  - Before/After Topic List
  - Before/After Single Topic
  - Between Replies (with repeat option)
- [x] bbPress Widgets
  - Forum Ad Widget (all bbPress pages, forum only, or topic only)
  - Topic Sidebar Ad Widget (single topic pages)

**Files Created:**
```
includes/Modules/Placements/class-sticky-placement.php
includes/Modules/Placements/class-popup-placement.php
includes/Modules/Placements/class-comment-placement.php
includes/Modules/BuddyPress/class-bp-directory-placement.php
includes/Modules/BuddyPress/class-bp-widgets.php
includes/Modules/bbPress/class-bbpress-module.php
includes/Modules/Targeting/class-content-analyzer.php
assets/js/frontend.js
```

---

## FREE VERSION v1.1+ - Future Enhancements

### Ad Groups & Rotation 🔲
**Priority:** High | **Complexity:** High

- [ ] Create `wbam-ad-group` custom taxonomy or CPT
- [ ] Group multiple ads together
- [ ] Rotation types: Random, Weighted, Sequential
- [ ] Fallback ad if group is empty
- [ ] `[wbam_group id="X"]` shortcode

### Impression Tracking 🔲
**Priority:** Medium | **Complexity:** Medium

- [ ] Create `wbam_impressions` database table
- [ ] AJAX/beacon tracking endpoint
- [ ] Daily aggregation (no PII storage)
- [ ] Simple stats column in ads list table

### ads.txt Editor 🔲
**Priority:** Low | **Complexity:** Low

- [ ] Settings page textarea for ads.txt
- [ ] Auto-add AdSense entry option
- [ ] Format validation

### Ad Blocker Detection 🔲
**Priority:** Low | **Complexity:** Medium

- [ ] JavaScript bait element detection
- [ ] Customizable fallback message
- [ ] CSS class for blocked state styling

---

## Technical Considerations

### Performance
- Use transients for caching ad queries
- Lazy load ads below the fold
- Async tracking beacons
- Database query optimization
- Object caching support

### Security
- Sanitize all ad content (especially HTML/JS)
- Nonce verification on all forms
- Capability checks
- SQL injection prevention
- XSS prevention
- Rate limiting on tracking endpoints

### Privacy (GDPR)
- Consent options for tracking
- IP anonymization option
- Data export/deletion tools
- Cookie notice integration
- Privacy policy template

### Compatibility
- Test with popular themes
- Test with page builders (Elementor, Divi, Gutenberg)
- Test with caching plugins
- Test with security plugins
- PHP 7.4+ and 8.x support
- WordPress 5.8+ support

---

## Resources

- [Advanced Ads](https://wpadvancedads.com/) - Placement system reference
- [Ad Inserter](https://adinserter.pro/) - Content insertion reference
- [ip-api.com](http://ip-api.com/) - Free geo IP service
- [ipinfo.io](https://ipinfo.io/) - Geo IP service with API
- [Google AdSense](https://www.google.com/adsense/) - Ad network integration

---

*Last updated: December 1, 2024*
