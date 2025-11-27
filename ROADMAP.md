# WB Ad Manager - Development Roadmap

**Plugin Name:** WB Ad Manager
**Premium Name:** WB Ad Manager Pro
**Current Version:** 1.0.0
**Last Updated:** November 28, 2024

---

## Overview

WB Ad Manager is a modular WordPress ad management plugin with BuddyPress integration. The free version provides core ad management features, while the Pro version adds advanced targeting, analytics, advertiser portal, and payment integration.

---

## Current Status Summary

| Component | Status |
|-----------|--------|
| Core Plugin Structure | ✅ Complete |
| Ad Types (3) | ✅ Complete |
| Basic Placements (7) | ✅ Complete |
| Admin UI & Metaboxes | ✅ Complete |
| Settings Page | ✅ Complete |
| Admin CSS/JS | ✅ Complete |
| Frontend CSS | ✅ Complete |
| BuddyPress Module | ✅ Complete |
| Smart Targeting | 🔲 Phase 2 |
| Geo-Targeting | 🔲 Phase 3 |
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
- [x] Rich Content (WYSIWYG editor)
- [x] Code ads (AdSense, custom HTML/JS)

**Placements Implemented:**
- [x] Header (`wp_head`)
- [x] Footer (`wp_footer`)
- [x] Before Content
- [x] After Content
- [x] After Paragraph X (with repeat option)
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

**Files Created:**
```
wb-ad-manager/
├── wb-ad-manager.php
├── assets/
│   ├── css/admin.css
│   ├── css/frontend.css
│   └── js/admin.js
└── includes/
    ├── Core/
    │   ├── trait-singleton.php
    │   └── class-plugin.php
    ├── Admin/
    │   ├── class-admin.php
    │   └── class-settings.php
    ├── Frontend/
    │   └── class-frontend.php
    └── Modules/
        ├── AdTypes/
        │   ├── interface-ad-type.php
        │   ├── class-image-ad.php
        │   ├── class-rich-content-ad.php
        │   └── class-code-ad.php
        ├── Placements/
        │   ├── interface-placement.php
        │   ├── class-placement-engine.php
        │   ├── class-header-placement.php
        │   ├── class-footer-placement.php
        │   ├── class-content-placement.php
        │   ├── class-paragraph-placement.php
        │   └── class-shortcode-placement.php
        └── BuddyPress/
            ├── class-bp-module.php
            └── class-bp-activity-placement.php
```

---

### Phase 2: Smart Placement System 🔲 NEXT

**Targeting Engine:**
- [ ] Create `Modules/Targeting/` directory structure
- [ ] Create `interface-targeting-rule.php`
- [ ] Create `class-targeting-engine.php` - Central targeting processor
- [ ] Create `class-post-targeting.php` - Specific posts targeting
- [ ] Create `class-category-targeting.php` - Category/taxonomy targeting
- [ ] Create `class-post-type-targeting.php` - Custom post type targeting
- [ ] Create `class-device-targeting.php` - Desktop/mobile/tablet
- [ ] Create `class-user-targeting.php` - Logged in/out, roles

**Exclusion Rules:**
- [ ] Create `class-exclusion-engine.php`
- [ ] Exclude specific posts
- [ ] Exclude categories/tags
- [ ] Exclude post types
- [ ] Exclude by URL pattern

**Scheduling:**
- [ ] Add start date field to ad metabox
- [ ] Add end date field to ad metabox
- [ ] Add day-of-week targeting
- [ ] Add time-of-day targeting
- [ ] Create `class-schedule-manager.php`

**Frequency Control:**
- [ ] Create `class-frequency-manager.php`
- [ ] Maximum ads per page
- [ ] Maximum ads per session (cookie-based)
- [ ] Ad rotation/randomization
- [ ] Priority/weight system

**Content Analysis:**
- [ ] Create `class-content-analyzer.php`
- [ ] Detect post length
- [ ] Count paragraphs
- [ ] Identify content type (text-heavy, image-heavy)

**Admin UI Updates:**
- [ ] Add "Targeting" metabox
- [ ] Add "Schedule" metabox
- [ ] Add "Display Rules" metabox
- [ ] Update admin CSS for new metaboxes

---

### Phase 3: Geo-Targeting 🔲 PLANNED

**Geo Detection System:**
- [ ] Create `Modules/GeoTargeting/` directory
- [ ] Create `interface-geo-provider.php`
- [ ] Create `class-geo-engine.php` - Main geo processor
- [ ] Create `class-ip-api-provider.php` - ip-api.com integration (free)
- [ ] Create `class-ipinfo-provider.php` - ipinfo.io integration
- [ ] Create `class-bp-location-provider.php` - BuddyPress profile location
- [ ] Create `class-geo-cache.php` - Cache geo lookups (transients)

**Detection Priority Chain:**
1. BuddyPress profile location (if logged in + BP active)
2. IP-based geolocation with caching
3. Browser geolocation (with consent)
4. Default/fallback ads

**Targeting Options:**
- [ ] Country targeting (include/exclude)
- [ ] Region/state targeting
- [ ] City targeting
- [ ] Radius targeting (around a point)
- [ ] Geo-based ad groups

**Admin UI:**
- [ ] Add geo targeting fields to targeting metabox
- [ ] Country multi-select with search
- [ ] Region/city autocomplete
- [ ] Map preview (optional)

---

### Phase 4: Additional Placements 🔲 PLANNED

**New WordPress Placements:**
- [ ] Between posts in archive/blog listing
- [ ] After X comments
- [ ] Sidebar widget (dedicated widget class)
- [ ] Floating/sticky ads (corner, bottom bar)
- [ ] Popup/modal ads (with frequency limit)
- [ ] Exit-intent popup

**Additional BuddyPress Placements:**
- [ ] Before activity form
- [ ] After activity form
- [ ] In member directory
- [ ] In group directory
- [ ] Member profile sidebar
- [ ] Group header area

**bbPress Placements (if bbPress active):**
- [ ] Before/after forum list
- [ ] Before/after topic list
- [ ] Between topic replies
- [ ] Forum sidebar

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

**Approval Workflow:**
1. Advertiser submits ad → Status: pending
2. Admin receives email notification
3. Admin reviews in WP admin
4. Admin approves/rejects with optional notes
5. Advertiser receives email with decision
6. If approved, ad goes live based on schedule

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

**Reports:**
- [ ] Performance report
- [ ] Revenue report
- [ ] Advertiser report
- [ ] Geo report
- [ ] Device report

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

**Lazy Loading:**
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

## File Structure (Complete)

```
wb-ad-manager/                          # FREE PLUGIN
├── wb-ad-manager.php
├── uninstall.php
├── ROADMAP.md
├── README.md
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   └── frontend.css
│   └── js/
│       ├── admin.js
│       └── frontend.js
├── includes/
│   ├── Core/
│   │   ├── trait-singleton.php
│   │   ├── class-plugin.php
│   │   ├── class-activator.php
│   │   └── class-deactivator.php
│   ├── Admin/
│   │   ├── class-admin.php
│   │   └── class-settings.php
│   ├── Frontend/
│   │   └── class-frontend.php
│   └── Modules/
│       ├── AdTypes/
│       │   ├── interface-ad-type.php
│       │   ├── class-image-ad.php
│       │   ├── class-rich-content-ad.php
│       │   └── class-code-ad.php
│       ├── Placements/
│       │   ├── interface-placement.php
│       │   ├── class-placement-engine.php
│       │   └── [placement classes]
│       ├── Targeting/                   # Phase 2
│       │   ├── interface-targeting-rule.php
│       │   ├── class-targeting-engine.php
│       │   └── [targeting classes]
│       ├── GeoTargeting/                # Phase 3
│       │   ├── interface-geo-provider.php
│       │   ├── class-geo-engine.php
│       │   └── [geo classes]
│       └── BuddyPress/
│           ├── class-bp-module.php
│           └── class-bp-activity-placement.php
├── languages/
│   └── wb-ad-manager.pot
└── templates/
    └── [template files]

wb-ad-manager-pro/                       # PRO PLUGIN
├── wb-ad-manager-pro.php
├── includes/
│   ├── Core/
│   │   ├── class-pro-plugin.php
│   │   └── class-license-manager.php
│   └── Modules/
│       ├── AdNetworks/                  # P1
│       ├── WooCommerce/                 # P2
│       ├── Advertisers/                 # P3
│       ├── Payments/                    # P3
│       ├── Analytics/                   # P4
│       └── ABTesting/                   # P5
└── assets/
```

---

## Priority Order for Development

### Immediate (Free v1.1.0)
1. ~~Phase 1: Core Foundation~~ ✅ DONE
2. Bug fixes and testing
3. WordPress.org submission preparation

### Short-term (Free v1.2.0)
4. Phase 2: Smart Placement System
5. Phase 4: Additional Placements (partial)

### Medium-term (Free v1.3.0)
6. Phase 3: Geo-Targeting

### Pro Development
7. Pro P1: Ad Network Integration
8. Pro P4: Analytics Dashboard (high demand)
9. Pro P3: Advertiser Portal & Payments
10. Pro P2: WooCommerce Integration
11. Pro P5: A/B Testing
12. Pro P6: Advanced Features

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
