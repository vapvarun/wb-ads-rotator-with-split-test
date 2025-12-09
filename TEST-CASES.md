# WB Ad Manager - QA Test Cases

## Overview

Comprehensive test cases for QA testing the WB Ad Manager FREE plugin. Tests cover both backend (admin) and frontend (visitor) functionality.

---

## Backend Tests (Admin Panel)

### 1. Ad Management

#### 1.1 Create New Ad
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Navigate to WB Ads > Add New | Ad creation form displays |
| 2 | Enter ad title | Title field accepts input |
| 3 | Select ad type: Image | Image upload field appears |
| 4 | Upload image (JPG/PNG) | Image uploads and preview shows |
| 5 | Enter destination URL | URL field accepts valid URL |
| 6 | Select placements (Header, Sidebar) | Checkboxes can be selected |
| 7 | Enable the ad | Toggle switches to "On" |
| 8 | Click Publish | Ad is created with success message |
| 9 | Navigate to WB Ads > All Ads | New ad appears in list |

#### 1.2 Create Code Ad
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Add New > Select "Code Ad" type | Code editor appears |
| 2 | Paste AdSense/HTML code | Code is accepted |
| 3 | Click Preview | Code renders in preview area |
| 4 | Publish ad | Ad saves successfully |

#### 1.3 Create Rich Content Ad
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Add New > Select "Rich Content" type | WYSIWYG editor appears |
| 2 | Add text, images, formatting | Content displays correctly |
| 3 | Publish ad | Ad saves with formatted content |

#### 1.4 Edit Existing Ad
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Click on existing ad | Ad edit screen loads |
| 2 | Modify title | Title updates |
| 3 | Change image | New image replaces old |
| 4 | Update destination URL | URL saves correctly |
| 5 | Click Update | Changes save with success message |

#### 1.5 Delete Ad
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Hover over ad in list | Trash link appears |
| 2 | Click Trash | Ad moves to trash |
| 3 | Navigate to Trash | Ad appears in trash list |
| 4 | Click "Delete Permanently" | Ad is permanently deleted |

#### 1.6 Bulk Actions
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Select multiple ads via checkboxes | Checkboxes selected |
| 2 | Select "Move to Trash" from dropdown | Option selected |
| 3 | Click Apply | Selected ads move to trash |

---

### 2. Ad Performance Comparison Metabox

#### 2.1 View Comparison (Ads with Same Placements)
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create 2+ ads with same placement | Ads saved |
| 2 | Edit one of the ads | "Ad Performance Comparison" metabox appears |
| 3 | View competing ads section | Shows other ads sharing placements |
| 4 | Check stats displayed | Shows Impressions, Clicks, CTR for each |
| 5 | Verify bar chart | Visual bar shows relative CTR |

#### 2.2 Winner Badge Display
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Ensure one ad has 100+ impressions | Ad qualifies for winner calculation |
| 2 | Edit that ad | Winner badge shows on highest CTR ad |
| 3 | Note shows for low-impression ads | "Needs 100+ impressions" message displays |

#### 2.3 Disable Competing Ad
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | In comparison metabox, click "Disable" | Confirmation may appear |
| 2 | Page redirects | Ad status changed to disabled |
| 3 | Check disabled ad | `_wbam_enabled` meta = 0 |

#### 2.4 No Competing Ads
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create ad with unique placements | Ad saved |
| 2 | Edit the ad | Metabox shows helpful message |
| 3 | Message text | "No other enabled ads are using the same placements..." |

---

### 3. Targeting Engine

#### 3.1 Schedule Targeting
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Edit ad > Schedule section | Schedule options appear |
| 2 | Set start date in future | Date saved |
| 3 | View frontend before start date | Ad does not display |
| 4 | Wait until start date | Ad begins displaying |

| Step | Action | Expected Result |
|------|--------|-----------------|
| 5 | Set end date in past | Date saved |
| 6 | View frontend | Ad does not display (expired) |

#### 3.2 Day/Time Targeting
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Select specific days (Mon, Wed, Fri) | Days saved |
| 2 | View frontend on Tuesday | Ad does not display |
| 3 | View frontend on Wednesday | Ad displays |

#### 3.3 Display Rules - Specific Pages
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Set "Display On: Specific" | Options expand |
| 2 | Select post types: Posts only | Saved |
| 3 | View ad on a Page | Ad does not display |
| 4 | View ad on a Post | Ad displays |

#### 3.4 Display Rules - Exclusions
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Set "Display On: All" | Default all pages |
| 2 | Add exclusion: Category "News" | Saved |
| 3 | View post in "News" category | Ad does not display |
| 4 | View post in other category | Ad displays |

#### 3.5 Device Targeting
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Set devices: Desktop only | Saved |
| 2 | View on mobile device | Ad does not display |
| 3 | View on desktop | Ad displays |

#### 3.6 User Status Targeting
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Set "Logged Out Users Only" | Saved |
| 2 | View while logged in | Ad does not display |
| 3 | Log out, view page | Ad displays |

---

### 4. Placements

#### 4.1 Header Placement
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create ad with Header placement | Saved |
| 2 | View any frontend page | Ad appears in header area |

#### 4.2 Footer Placement
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create ad with Footer placement | Saved |
| 2 | View any frontend page | Ad appears in footer area |

#### 4.3 Sidebar Widget
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Go to Appearance > Widgets | Widget area displays |
| 2 | Add "WBAM Ad" widget to sidebar | Widget added |
| 3 | Select specific ad | Ad selected |
| 4 | View frontend page with sidebar | Ad displays in sidebar |

#### 4.4 Shortcode Placement
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Copy shortcode `[wbam_ad id="123"]` | Shortcode copied |
| 2 | Paste in post/page content | Shortcode in editor |
| 3 | View post/page | Ad renders in content |

#### 4.5 Paragraph Insertion
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Select "After Paragraph X" placement | Saved |
| 2 | Set paragraph number: 2 | Saved |
| 3 | View post with 5+ paragraphs | Ad appears after 2nd paragraph |

#### 4.6 Archive Placement
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Enable archive placement | Saved |
| 2 | Set "After every X posts" | Saved |
| 3 | View category/archive page | Ad appears between posts |

---

### 5. Link Manager

#### 5.1 Create Affiliate Link
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Navigate to WB Ads > Links > Add New | Form displays |
| 2 | Enter link name | Name accepted |
| 3 | Enter destination URL | URL accepted |
| 4 | Set cloaked slug | Slug auto-generates |
| 5 | Save link | Link created |
| 6 | Visit cloaked URL | Redirects to destination |

#### 5.2 Link Click Tracking
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Click affiliate link multiple times | Clicks registered |
| 2 | View link in admin | Click count updated |

#### 5.3 NoFollow Settings
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Enable nofollow for link | Saved |
| 2 | View page source | `rel="nofollow"` present |

---

### 6. Link Partnership Manager

#### 6.1 Shortcode Display
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Add `[wbam_partnership_inquiry]` to page | Shortcode in editor |
| 2 | View page (logged out) | Partnership inquiry form displays |
| 3 | Form fields present | Name, Email, Website, Type, Message |

#### 6.2 Form Submission
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Fill all required fields | Fields populated |
| 2 | Submit form | Success message appears |
| 3 | Check admin > Partnerships | Inquiry appears in list |

#### 6.3 Admin Review
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | View partnership inquiry | Details display |
| 2 | Add admin note | Note saved |
| 3 | Click "Accept" | Status changes to accepted |
| 4 | Click "Reject" | Status changes to rejected |

---

### 7. Settings

#### 7.1 General Settings
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Navigate to WB Ads > Settings | Settings page loads |
| 2 | Toggle "Disable for logged-in users" | Option saves |
| 3 | Log in as subscriber | Ads hidden |
| 4 | Log out | Ads visible |

#### 7.2 AdSense Settings
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Enter Publisher ID | ID saved |
| 2 | Enable Auto Ads | Checkbox saved |
| 3 | View frontend page source | AdSense script present |

#### 7.3 Post Type Settings
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Disable ads on Pages | Saved |
| 2 | View a Page | No ads display |
| 3 | View a Post | Ads display normally |

---

## Frontend Tests (Visitor Experience)

### 8. Ad Display

#### 8.1 Image Ad Click
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | View page with image ad | Ad displays correctly |
| 2 | Click on ad | Redirects to destination URL |
| 3 | Check click tracking | Click recorded in analytics |

#### 8.2 Responsive Ads
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | View page on desktop | Ad displays at full size |
| 2 | Resize to tablet width | Ad scales appropriately |
| 3 | Resize to mobile width | Ad adjusts or hides per settings |

#### 8.3 Ad Rotation
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create 3 ads for same placement | Ads saved |
| 2 | Refresh page multiple times | Different ads appear |
| 3 | All 3 ads show eventually | Rotation working |

---

### 9. Analytics Tracking

#### 9.1 Impression Tracking
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | View page with ad | Page loads |
| 2 | Check admin > Analytics | Impression recorded |
| 3 | Note timestamp | Matches page view time |

#### 9.2 Click Tracking
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Click on ad | Redirects |
| 2 | Check admin > Analytics | Click recorded |
| 3 | Verify ad_id matches | Correct ad credited |

#### 9.3 CTR Calculation
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Generate 100 impressions | Impressions recorded |
| 2 | Generate 5 clicks | Clicks recorded |
| 3 | Check CTR display | Shows 5% (5/100) |

---

### 10. Email Capture Ad

#### 10.1 Form Display
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create Email Capture ad type | Saved |
| 2 | Place ad on page | Form displays |
| 3 | Form fields present | Email field visible, optional name |

#### 10.2 Submission
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Enter valid email | Field accepts input |
| 2 | Click Subscribe | Success message appears |
| 3 | Check admin > Email Submissions | Submission recorded |

#### 10.3 Invalid Email
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Enter "notanemail" | Field has input |
| 2 | Submit | Error message "Please enter valid email" |

---

### 11. Popup/Sticky Ads

#### 11.1 Popup Display
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create popup ad | Saved |
| 2 | Set trigger: On page load | Saved |
| 3 | Visit page | Popup appears |
| 4 | Click close button | Popup closes |

#### 11.2 Sticky Ad
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create sticky ad | Saved |
| 2 | Visit page | Sticky bar visible |
| 3 | Scroll down | Sticky ad remains fixed |

---

### 12. Geo Targeting (if enabled)

#### 12.1 Country Targeting
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Set ad to show in USA only | Saved |
| 2 | View from USA IP | Ad displays |
| 3 | Use VPN to non-USA country | Ad does not display |

---

## Developer Hook Tests

### 13. Filter Tests

#### 13.1 `wbam_ad_display_rules` Filter
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Add filter in theme/plugin | Filter registered |
| 2 | Modify rules in filter | Custom logic executes |
| 3 | Ad display respects modified rules | Filter working |

```php
add_filter( 'wbam_ad_display_rules', function( $rules, $ad_id ) {
    // Custom rule modifications
    return $rules;
}, 10, 2 );
```

#### 13.2 `wbam_ads_for_placement` Filter
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Add filter to modify ad array | Filter registered |
| 2 | Remove specific ad from array | Ad excluded |
| 3 | Verify ad doesn't show | Filter working |

#### 13.3 `wbam_ad_data_before_save` Filter
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Add filter before save | Filter registered |
| 2 | Modify ad data in filter | Data changed |
| 3 | Save ad and check meta | Modified data saved |

### 14. Action Tests

#### 14.1 `wbam_ad_impression` Action
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Add action hook | Hook registered |
| 2 | View ad on frontend | Action fires |
| 3 | Custom code executes | Hook working |

```php
add_action( 'wbam_ad_impression', function( $ad_id, $placement ) {
    // Custom impression handling
}, 10, 2 );
```

#### 14.2 `wbam_ad_clicked` Action
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Add action hook | Hook registered |
| 2 | Click ad | Action fires |
| 3 | Custom code executes | Hook working |

---

## Security Tests

### 15. Nonce Verification

#### 15.1 Disable Ad Security
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Copy disable ad URL | URL copied |
| 2 | Modify nonce in URL | Nonce invalid |
| 3 | Visit modified URL | "Security check failed" error |

#### 15.2 AJAX Nonce
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Submit form without nonce | Request blocked |
| 2 | Submit with invalid nonce | 403 error returned |

### 16. Rate Limiting

#### 16.1 Click Tracking Rate Limit
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send 30 click requests in 1 minute | All succeed |
| 2 | Send 31st request | 429 Too Many Requests |

#### 16.2 Email Capture Rate Limit
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Submit 10 emails in 1 minute | All succeed |
| 2 | Submit 11th request | Rate limit error |

---

## Performance Tests

### 17. Page Load

#### 17.1 Multiple Ads
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Add 10 ads to one page | Ads configured |
| 2 | Load page | All ads render |
| 3 | Check page load time | Under 3 seconds |

#### 17.2 Caching Compatibility
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Enable page caching plugin | Cache active |
| 2 | View page twice | Second load is cached |
| 3 | Ads still display correctly | Cache-compatible |

---

## Error Handling Tests

### 18. Edge Cases

#### 18.1 Missing Image
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Delete image from media library | Image removed |
| 2 | View ad on frontend | Graceful fallback (no broken image) |

#### 18.2 Invalid Destination URL
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Set destination to invalid URL | URL saved |
| 2 | Click ad | Handles gracefully |

#### 18.3 Zero Impressions CTR
| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create new ad (0 impressions) | Ad created |
| 2 | View comparison metabox | No division by zero error |
| 3 | CTR shows "0.00%" | Correct display |

---

## Browser Compatibility

### 19. Cross-Browser Tests

| Browser | Version | Admin Panel | Frontend Ads | Click Tracking |
|---------|---------|-------------|--------------|----------------|
| Chrome | Latest | ✓ | ✓ | ✓ |
| Firefox | Latest | ✓ | ✓ | ✓ |
| Safari | Latest | ✓ | ✓ | ✓ |
| Edge | Latest | ✓ | ✓ | ✓ |
| Mobile Safari | Latest | ✓ | ✓ | ✓ |
| Chrome Mobile | Latest | ✓ | ✓ | ✓ |

---

## Test Summary Checklist

### Backend
- [ ] Create all ad types (Image, Code, Rich Content, Email Capture)
- [ ] Ad Performance Comparison metabox displays correctly
- [ ] Disable competing ad functionality works
- [ ] All targeting rules work (schedule, device, user, geo)
- [ ] All placements work (header, footer, sidebar, shortcode, paragraph)
- [ ] Link Manager creates and tracks links
- [ ] Partnership inquiry form submits and appears in admin
- [ ] Settings save and apply correctly

### Frontend
- [ ] Ads display in correct positions
- [ ] Ad clicks redirect properly
- [ ] Analytics track impressions and clicks
- [ ] Email capture form works
- [ ] Popup/sticky ads function correctly
- [ ] Ads respect targeting rules

### Security
- [ ] Nonce verification blocks invalid requests
- [ ] Rate limiting works
- [ ] Capability checks prevent unauthorized access

### Performance
- [ ] Pages with multiple ads load quickly
- [ ] Compatible with caching plugins

---

*Last updated: December 9, 2024*
