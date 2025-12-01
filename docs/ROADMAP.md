# WB Ad Manager - Development Roadmap

## Current Release (v1.0.0)

### Ad Types
- [x] Image Ad - Banner images with links
- [x] Rich Content Ad - WYSIWYG editor content
- [x] Code Ad - Custom HTML/JavaScript
- [x] Google AdSense - Auto script management, multiple formats

### Placements
- [x] Header - Before/after header
- [x] Footer - Before footer
- [x] Content - Before/after content
- [x] Paragraph - After specific paragraphs
- [x] Shortcode - Manual placement via shortcode
- [x] Widget - Sidebar/widget areas
- [x] Archive - Between posts in archives
- [x] Sticky - Fixed position ads
- [x] Popup - Modal/overlay ads
- [x] Comment - Within comment sections

### Targeting
- [x] Geo-targeting (Country/Region via IP)
- [x] Frequency capping (impressions per user)
- [x] Post type exclusions
- [x] Logged-in user exclusions
- [x] Admin exclusions

### Integrations
- [x] BuddyPress Activity Stream
- [x] bbPress Forums

### Settings
- [x] Global AdSense Publisher ID
- [x] AdSense Auto Ads
- [x] Ad labels
- [x] Lazy loading
- [x] Query caching
- [x] Max ads per page

---

## Phase 2 - Free Version Enhancements

### 1. Device Targeting
**Priority:** High
**Complexity:** Medium

Target ads based on user's device type.

**Features:**
- Desktop only
- Mobile only
- Tablet only
- Any combination

**Implementation:**
- Add `_wbam_device_targeting` meta field
- Options: `all`, `desktop`, `mobile`, `tablet`, `desktop_tablet`, `mobile_tablet`
- Use `wp_is_mobile()` and user agent detection
- Create `Device_Targeting` class in `Modules/Targeting/`

**Files to modify/create:**
```
includes/Modules/Targeting/class-device-targeting.php (new)
includes/Admin/class-admin.php (add metabox fields)
assets/js/admin.js (conditional field display)
```

**Database:**
```php
'_wbam_device_targeting' => 'all' // all, desktop, mobile, tablet
```

---

### 2. Ad Scheduling
**Priority:** High
**Complexity:** Low

Schedule ads to run during specific date ranges.

**Features:**
- Start date/time
- End date/time
- Recurring schedules (optional, Phase 3)
- Timezone support

**Implementation:**
- Add `_wbam_schedule_start` and `_wbam_schedule_end` meta fields
- Add date/time pickers in admin
- Check schedule in `Targeting_Engine::should_display()`

**Files to modify/create:**
```
includes/Modules/Targeting/class-targeting-engine.php (add schedule check)
includes/Admin/class-admin.php (add date pickers)
assets/css/admin.css (datepicker styles)
```

**Database:**
```php
'_wbam_schedule_start' => '2024-01-01 00:00:00'
'_wbam_schedule_end'   => '2024-12-31 23:59:59'
```

---

### 3. Ad Groups & Rotation
**Priority:** High
**Complexity:** High

Group multiple ads together and rotate them in a placement.

**Features:**
- Create ad groups (custom taxonomy or CPT)
- Assign ads to groups
- Rotation types: Random, Weighted, Sequential
- Fallback ad if group is empty

**Implementation:**
- Register `wbam-ad-group` custom taxonomy OR custom post type
- Add group assignment to ad edit screen
- Create `Ad_Group` class for rotation logic
- Add `[wbam_group id="X"]` shortcode

**Files to create:**
```
includes/Modules/Groups/class-ad-group.php
includes/Modules/Groups/class-group-rotator.php
includes/Admin/class-group-admin.php
```

**Rotation Logic:**
```php
class Group_Rotator {
    public function get_ad( $group_id, $method = 'random' ) {
        $ads = $this->get_group_ads( $group_id );

        switch ( $method ) {
            case 'random':
                return $ads[ array_rand( $ads ) ];
            case 'weighted':
                return $this->weighted_random( $ads );
            case 'sequential':
                return $this->get_next_in_sequence( $group_id, $ads );
        }
    }
}
```

---

### 4. Category/Taxonomy Targeting
**Priority:** Medium
**Complexity:** Medium

Show ads only on posts with specific categories, tags, or custom taxonomies.

**Features:**
- Include specific categories
- Exclude specific categories
- Tag targeting
- Custom taxonomy support

**Implementation:**
- Add taxonomy selector metabox
- Store as serialized array in post meta
- Check in `Targeting_Engine`

**Files to modify:**
```
includes/Modules/Targeting/class-targeting-engine.php
includes/Admin/class-admin.php
```

**Database:**
```php
'_wbam_target_categories' => array( 5, 12, 18 )  // Include
'_wbam_exclude_categories' => array( 3 )         // Exclude
'_wbam_target_tags' => array( 'featured', 'sponsored' )
```

---

### 5. User Role Targeting
**Priority:** Medium
**Complexity:** Low

Target ads based on user roles.

**Features:**
- Target specific roles (subscriber, author, etc.)
- Target logged-in vs logged-out
- Target by user capability

**Implementation:**
- Add role checkboxes in ad metabox
- Check `current_user_can()` or user roles

**Database:**
```php
'_wbam_target_roles' => array( 'subscriber', 'customer' )
'_wbam_target_logged_in' => 'logged_out' // logged_in, logged_out, all
```

---

### 6. Basic Impression Tracking
**Priority:** Medium
**Complexity:** Medium

Track how many times each ad is viewed.

**Features:**
- Impression count per ad
- Daily/weekly/monthly breakdown
- Simple stats in ad list table

**Implementation:**
- Create `wbam_impressions` database table
- AJAX endpoint for tracking (or beacon)
- Consider privacy (aggregate only, no PII)
- Add column to ads list table

**Files to create:**
```
includes/Modules/Analytics/class-impression-tracker.php
includes/Modules/Analytics/class-analytics-db.php
```

**Database Table:**
```sql
CREATE TABLE {prefix}wbam_impressions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ad_id BIGINT UNSIGNED NOT NULL,
    placement VARCHAR(50),
    date DATE NOT NULL,
    count INT UNSIGNED DEFAULT 1,
    UNIQUE KEY ad_date (ad_id, placement, date),
    INDEX (date)
);
```

---

### 7. ads.txt Editor
**Priority:** Low
**Complexity:** Low

Manage ads.txt file from WordPress admin.

**Features:**
- Edit ads.txt content
- Auto-add AdSense entry
- Validate format

**Implementation:**
- Settings page textarea
- Save to `ads.txt` in root or use virtual file via rewrite
- Add validation for format

**Files to create:**
```
includes/Admin/class-ads-txt.php
```

---

### 8. Ad Blocker Detection
**Priority:** Low
**Complexity:** Medium

Detect ad blockers and show alternative content.

**Features:**
- Detect common ad blockers
- Show custom message
- Optional: Show alternative content

**Implementation:**
- JavaScript detection (bait element method)
- Customizable message in settings
- CSS class for styling blocked state

**Files to create:**
```
assets/js/adblock-detect.js
includes/Frontend/class-adblock-detector.php
```

---

## Phase 3 - Pro Version Features

### 1. Advanced Analytics Dashboard
- Impressions, clicks, CTR graphs
- Revenue tracking (manual entry or API)
- Performance comparisons
- Export reports (CSV, PDF)
- Date range filtering

### 2. A/B Testing
- Create ad variants
- Automatic traffic splitting
- Statistical significance calculation
- Winner selection

### 3. Additional Ad Networks
- Amazon Associates
- Media.net
- Taboola
- Outbrain
- Custom network API

### 4. Video Ads
- HTML5 video upload
- YouTube/Vimeo embeds
- Pre-roll, mid-roll support
- Autoplay options

### 5. WooCommerce Integration
- Product-based ads
- Cart abandonment ads
- Purchase history targeting
- Product category targeting

### 6. Advertiser Management
- Advertiser user role
- Self-serve ad submission
- Approval workflow
- Advertiser dashboard

### 7. Payment Integration
- WooCommerce payments
- Stripe direct
- PayPal
- Subscription/credits system

### 8. Advanced Scheduling
- Recurring schedules
- Day of week targeting
- Hour of day targeting
- Holiday schedules

### 9. Referrer Targeting
- Target by referrer domain
- Search engine visitors
- Social media visitors
- Direct visitors

### 10. URL Parameter Targeting
- UTM parameter targeting
- Custom query parameters
- Landing page specific ads

### 11. Geofencing
- Radius-based targeting
- City-level targeting
- Postal code targeting

### 12. Header Bidding
- Prebid.js integration
- Multiple SSP support
- Real-time bidding

---

## Implementation Priority Matrix

| Feature | Impact | Effort | Priority |
|---------|--------|--------|----------|
| Device Targeting | High | Medium | P1 |
| Ad Scheduling | High | Low | P1 |
| Ad Groups/Rotation | High | High | P1 |
| Category Targeting | Medium | Medium | P2 |
| User Role Targeting | Medium | Low | P2 |
| Impression Tracking | Medium | Medium | P2 |
| ads.txt Editor | Low | Low | P3 |
| Ad Blocker Detection | Low | Medium | P3 |

---

## Database Schema Changes (Phase 2)

```sql
-- Impressions table
CREATE TABLE {prefix}wbam_impressions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ad_id BIGINT UNSIGNED NOT NULL,
    placement VARCHAR(50) DEFAULT '',
    date DATE NOT NULL,
    impressions INT UNSIGNED DEFAULT 0,
    clicks INT UNSIGNED DEFAULT 0,
    UNIQUE KEY ad_placement_date (ad_id, placement, date),
    INDEX idx_date (date),
    INDEX idx_ad_id (ad_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ad groups (if using custom table instead of taxonomy)
CREATE TABLE {prefix}wbam_ad_groups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL,
    rotation_type ENUM('random', 'weighted', 'sequential') DEFAULT 'random',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Group assignments
CREATE TABLE {prefix}wbam_ad_group_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id BIGINT UNSIGNED NOT NULL,
    ad_id BIGINT UNSIGNED NOT NULL,
    weight INT UNSIGNED DEFAULT 1,
    position INT UNSIGNED DEFAULT 0,
    UNIQUE KEY group_ad (group_id, ad_id),
    INDEX idx_group (group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## File Structure (After Phase 2)

```
wb-ad-manager/
├── includes/
│   ├── Admin/
│   │   ├── class-admin.php
│   │   ├── class-settings.php
│   │   ├── class-display-options.php
│   │   ├── class-setup-wizard.php
│   │   ├── class-group-admin.php (new)
│   │   └── class-ads-txt.php (new)
│   ├── Core/
│   │   ├── class-plugin.php
│   │   └── trait-singleton.php
│   ├── Frontend/
│   │   ├── class-frontend.php
│   │   └── class-adblock-detector.php (new)
│   └── Modules/
│       ├── AdTypes/
│       │   ├── interface-ad-type.php
│       │   ├── class-image-ad.php
│       │   ├── class-rich-content-ad.php
│       │   ├── class-code-ad.php
│       │   └── class-ad-sense-ad.php
│       ├── Analytics/ (new)
│       │   ├── class-impression-tracker.php
│       │   └── class-analytics-db.php
│       ├── BuddyPress/
│       │   └── class-bp-module.php
│       ├── bbPress/
│       │   └── class-bbpress-module.php
│       ├── Groups/ (new)
│       │   ├── class-ad-group.php
│       │   └── class-group-rotator.php
│       ├── Placements/
│       │   └── ... (existing)
│       └── Targeting/
│           ├── class-targeting-engine.php
│           ├── class-frequency-manager.php
│           ├── class-geo-provider.php
│           └── class-device-targeting.php (new)
└── docs/
    └── ROADMAP.md
```

---

## Version Planning

### v1.0.0 (Current Release)
- Core ad management
- Basic placements
- AdSense support
- Geo-targeting
- BuddyPress/bbPress

### v1.1.0 (Phase 2 - Part 1)
- Device targeting
- Ad scheduling
- Category targeting

### v1.2.0 (Phase 2 - Part 2)
- Ad groups & rotation
- User role targeting
- Basic impression tracking

### v1.3.0 (Phase 2 - Part 3)
- ads.txt editor
- Ad blocker detection
- Performance optimizations

### v2.0.0 (Pro Launch)
- Analytics dashboard
- A/B testing
- Video ads
- Additional networks

---

## Notes

1. **Backward Compatibility**: All new meta fields should have sensible defaults to not break existing ads.

2. **Performance**: Impression tracking should use batch inserts and consider using object cache.

3. **Privacy**: Impression tracking should be GDPR compliant - no PII storage, aggregate data only.

4. **Testing**: Each new feature should include unit tests and integration tests.

5. **Documentation**: Update user documentation with each release.
