# WB Ad Manager - Development Roadmap

**Plugin Name:** WB Ad Manager
**Premium Name:** WB Ad Manager Pro
**Current Version:** 1.0.0
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

## PREMIUM VERSION (Separate Plugin)

### Pro Phase P1: Ad Network Integration 🔲

**Architecture:**
- [ ] Create `wb-ad-manager-pro/` plugin
- [ ] Create Pro bootstrap with free plugin dependency check
- [ ] Create `class-pro-plugin.php`
- [ ] Create `Modules/AdNetworks/` directory

**Ad Networks:**
- [ ] Create `interface-ad-network.php`
- [ ] Create `class-network-manager.php`
- [ ] Create `class-adsense-network.php` - Google AdSense
- [ ] Create `class-medianet-network.php` - Media.net
- [ ] Create `class-ezoic-network.php` - Ezoic
- [ ] Create `class-amazon-network.php` - Amazon Associates

**Features:**
- [ ] Auto-ad insertion from networks
- [ ] Fallback system (show own ads if network fails)
- [ ] A/B testing between networks
- [ ] Network performance comparison

---

### Pro Phase P2: WooCommerce Integration 🔲

**Architecture:**
- [ ] Create `Modules/WooCommerce/` directory
- [ ] Create `class-wc-module.php` - Main WC integration
- [ ] Create `class-wc-placements.php` - WC-specific placements
- [ ] Create `class-wc-targeting.php` - Product-based targeting

**WooCommerce Placements:**
- [ ] Before/after product description
- [ ] After product gallery
- [ ] After add to cart button
- [ ] Before/after related products
- [ ] Cart page (top/bottom/sidebar)
- [ ] Checkout page header
- [ ] Thank you page
- [ ] My Account page

**WooCommerce Targeting:**
- [ ] Product categories
- [ ] Product tags
- [ ] Product attributes
- [ ] Price range
- [ ] On sale products
- [ ] Cart contents
- [ ] Purchase history

---

### Pro Phase P3: Advertiser Portal & Payments 🔲

**Database Tables:**
```sql
{prefix}wbam_advertisers (
    id, user_id, company_name, status, balance,
    total_spent, created_at, updated_at
)

{prefix}wbam_campaigns (
    id, advertiser_id, ad_id, name, budget, spent,
    pricing_model, price_per_unit, impressions_limit,
    clicks_limit, start_date, end_date, status, created_at
)

{prefix}wbam_packages (
    id, name, description, placements, duration_days,
    impressions_limit, clicks_limit, price, status, created_at
)

{prefix}wbam_transactions (
    id, advertiser_id, campaign_id, package_id, amount,
    type, payment_method, payment_id, status, notes, created_at
)
```

**Advertiser System:**
- [ ] Create `Modules/Advertisers/` directory
- [ ] Create `class-advertiser-manager.php`
- [ ] Create `class-advertiser-registration.php`
- [ ] Create `class-advertiser-dashboard.php`
- [ ] Advertiser registration form
- [ ] Frontend ad submission form
- [ ] Shortcode `[wbam_submit_ad]`
- [ ] Shortcode `[wbam_advertiser_dashboard]`
- [ ] BuddyPress profile tab "My Ads"
- [ ] Email notifications (submission, approval, rejection)

**Payment Integration:**
- [ ] Create `Modules/Payments/` directory
- [ ] Create `interface-payment-gateway.php`
- [ ] Create `class-payment-manager.php`
- [ ] Create `class-stripe-gateway.php` - Direct Stripe
- [ ] Create `class-wc-gateway.php` - WooCommerce checkout
- [ ] Create `class-paypal-gateway.php` - PayPal
- [ ] Credit/balance system
- [ ] Package purchase system

**Pricing Models:**
- [ ] CPC (Cost per Click)
- [ ] CPM (Cost per 1000 Impressions)
- [ ] Flat rate (fixed price for duration)
- [ ] Package-based (pre-defined bundles)

---

### Pro Phase P4: Analytics Dashboard 🔲

**Database Tables:**
```sql
{prefix}wbam_analytics (
    id, ad_id, campaign_id, event_type, user_id, ip_hash,
    country, region, city, device_type, browser,
    referrer, placement, page_url, created_at
)

{prefix}wbam_analytics_daily (
    id, ad_id, campaign_id, date, impressions, clicks,
    unique_impressions, unique_clicks, conversions, revenue
)
```

**Tracking Implementation:**
- [ ] Create `Modules/Analytics/` directory
- [ ] Create `class-analytics-tracker.php`
- [ ] Create `class-impression-tracker.php` - Beacon/pixel tracking
- [ ] Create `class-click-tracker.php` - Click tracking
- [ ] Create `class-analytics-aggregator.php` - Daily stats cron
- [ ] Viewport detection (viewable impressions)
- [ ] Bot/crawler detection and filtering

**Analytics Dashboard:**
- [ ] Create `class-analytics-dashboard.php`
- [ ] Admin analytics page with charts
- [ ] Advertiser analytics view
- [ ] Date range selector
- [ ] Comparison mode (vs previous period)
- [ ] Export functionality (CSV, PDF)

**Metrics to Track:**
- [ ] Total impressions
- [ ] Unique impressions
- [ ] Total clicks
- [ ] Unique clicks
- [ ] CTR (Click-through rate)
- [ ] Viewable impressions
- [ ] Viewability rate
- [ ] Impressions over time (chart)
- [ ] Top performing ads
- [ ] Geo breakdown (map visualization)
- [ ] Device breakdown (pie chart)
- [ ] Browser breakdown
- [ ] Placement performance
- [ ] Revenue tracking

---

### Pro Phase P5: A/B Testing 🔲

- [ ] Create `Modules/ABTesting/` directory
- [ ] Create `class-ab-test-manager.php`
- [ ] Create ad variants
- [ ] Traffic splitting
- [ ] Statistical significance calculation
- [ ] Winner detection
- [ ] Auto-optimize (pause losers)

---

### Pro Phase P6: Advanced Features 🔲

**Ad Blocker Detection:**
- [ ] Create `class-adblock-detector.php`
- [ ] Detect ad blockers
- [ ] Show alternative content
- [ ] Analytics for blocked impressions

**Lazy Loading (Enhanced):**
- [ ] Create `class-lazy-loader.php`
- [ ] Intersection Observer implementation
- [ ] Placeholder system
- [ ] Performance optimization

**Import/Export:**
- [ ] Export ads to JSON/CSV
- [ ] Import ads from JSON/CSV
- [ ] Migrate from other ad plugins

**REST API:**
- [ ] Create `class-rest-api.php`
- [ ] GET /ads endpoint
- [ ] POST /impressions endpoint
- [ ] POST /clicks endpoint
- [ ] Authentication via API keys

---

## Priority Order for Development

### Free Version v1.1.0 ✅ ALL COMPLETE
1. ~~Phase 1: Core Foundation~~ ✅ DONE
2. ~~Phase 2: Advanced Scheduling & Frequency~~ ✅ DONE
3. ~~Phase 3: Additional Placements~~ ✅ DONE
4. ~~Content Analyzer~~ ✅ DONE
5. ~~Comment Placements~~ ✅ DONE
6. ~~BuddyPress Widgets~~ ✅ DONE
7. ~~bbPress Integration~~ ✅ DONE
8. Bug fixes and testing
9. WordPress.org submission preparation

### Pro Development
8. Pro P4: Analytics Dashboard (high demand)
9. Pro P1: Ad Network Integration
10. Pro P3: Advertiser Portal & Payments
11. Pro P2: WooCommerce Integration
12. Pro P5: A/B Testing
13. Pro P6: Advanced Features

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
- [MaxMind GeoLite2](https://dev.maxmind.com/geoip/geolite2-free-geolocation-data) - Geo database
- [Stripe Docs](https://stripe.com/docs) - Payment integration
- [Chart.js](https://www.chartjs.org/) - Analytics charts

---

*Last updated: November 28, 2024*
