# Quick Setup Guide - Get Ads Running in 10 Minutes

## What You'll Learn

- How to create ad zones
- How to create and publish ads
- How to display ads on your site
- How to track performance

---

## Overview

Setting up WB Starter Ads involves three simple steps:

1. **Create Ad Zones** - Define where ads will appear
2. **Create Ads** - Add your ad content
3. **Display Ads** - Use shortcodes to show ads

Let's get started!

---

## Step 1: Create Ad Zones (2 minutes)

Ad zones are containers that hold your ads. Think of them as "slots" on your website.

### Common Ad Zone Sizes

| Zone Type | Size | Where to Use |
|-----------|------|--------------|
| Leaderboard | 728x90 | Header, above content |
| Medium Rectangle | 300x250 | Sidebar, in-content |
| Wide Skyscraper | 160x600 | Sidebar |
| Mobile Banner | 320x50 | Mobile header/footer |

### Creating a Zone

1. Go to **WB Ad Manager → Ad Zones**
2. Click **Add New**
3. Fill in:
   - **Title**: "Sidebar Banner" (descriptive name)
   - **Slug**: `sidebar-banner` (used in shortcode)
   - **Width**: 300
   - **Height**: 250
4. Set rotation options:
   - **Rotation Type**: Random (default)
   - **Refresh**: Disabled or set interval
5. Click **Publish**

> **Tip:** Create 2-3 zones for different areas of your site.

---

## Step 2: Create Your First Ad (3 minutes)

### For Image Ads

1. Go to **WB Ad Manager → Ads**
2. Click **Add New**
3. Enter:
   - **Title**: "Summer Sale Banner" (internal only)
   - **Ad Type**: Image
4. Upload your banner image
5. Enter **Destination URL**: `https://example.com/sale`
6. In the **Ad Zones** box (right sidebar), check your zone
7. Set scheduling (optional):
   - Start Date
   - End Date
8. Click **Publish**

### For Text Ads

1. Go to **WB Ad Manager → Ads → Add New**
2. Select **Ad Type**: Text
3. Enter:
   - **Headline**: "50% Off Summer Sale"
   - **Description**: "Shop now and save big on all items"
   - **Display URL**: "example.com/sale"
   - **Destination URL**: Full URL
4. Assign to zone and **Publish**

### For HTML Ads

1. Go to **WB Ad Manager → Ads → Add New**
2. Select **Ad Type**: HTML
3. Paste your HTML/JavaScript code
4. Assign to zone and **Publish**

---

## Step 3: Display Ads on Your Site (2 minutes)

### Using Shortcodes

Add this shortcode where you want ads to appear:

```
[wbam_ad zone="sidebar-banner"]
```

**Where to add shortcodes:**

| Location | How to Add |
|----------|------------|
| Pages/Posts | Add directly in content editor |
| Widgets | Use Text/HTML widget in sidebar |
| Theme | Use `do_shortcode()` in PHP |

### Shortcode Examples

**Single ad from zone:**
```
[wbam_ad zone="sidebar-banner"]
```

**Multiple ads:**
```
[wbam_ads zone="sponsors" count="4"]
```

**Specific ad by ID:**
```
[wbam_ad id="123"]
```

### Using Widgets

1. Go to **Appearance → Widgets**
2. Find "WB Ad Manager" widget
3. Drag to your sidebar
4. Select the ad zone
5. Save

---

## Step 4: Verify It's Working (1 minute)

1. Visit a page where you added the shortcode
2. Your ad should display
3. Click the ad to test
4. Go to **WB Ad Manager → Analytics**
5. You should see 1 click recorded

---

## Step 5: Configure Tracking (2 minutes)

### Enable Analytics

1. Go to **WB Ad Manager → Settings → Analytics**
2. Enable:
   - Click tracking
   - Impression tracking
   - Unique visitor tracking
3. Set data retention period
4. Click **Save Changes**

### View Reports

1. Go to **WB Ad Manager → Analytics**
2. View:
   - Total impressions
   - Total clicks
   - Click-through rate (CTR)
   - Top performing ads

---

## Quick Reference

### Shortcodes Available

| Shortcode | Purpose |
|-----------|---------|
| `[wbam_ad]` | Display single ad/zone |
| `[wbam_ads]` | Display multiple ads |
| `[wbam_link]` | Display managed link |
| `[wbam_links]` | Display link list |
| `[wbam_link_url]` | Output raw tracked URL |
| `[wbam_partnership_inquiry]` | Link request form |

### Ad Statuses

| Status | Meaning |
|--------|---------|
| Published | Active and showing |
| Draft | Saved but not live |
| Scheduled | Will go live on start date |
| Expired | Past end date |

---

## Tips for Success

1. **Use appropriate sizes** - Match ad size to zone size
2. **Set weights** - Give important ads higher weight
3. **Monitor performance** - Check analytics weekly
4. **Rotate creatives** - Add multiple ads per zone
5. **A/B test** - Create variations to find winners

---

## Common Questions

### How many ads can I create?

Unlimited! Create as many ads and zones as you need.

### Can I schedule ads?

Yes! Set start and end dates when creating ads.

### Does it slow down my site?

No. Ads load efficiently with minimal impact.

### Can I use Google AdSense?

Yes! Create an HTML ad and paste your AdSense code.

---

## Next Steps

- [Create tracked links](../shortcode-reference/02-link-shortcodes.md)
- [Set up link partnerships](../for-site-owners/02-link-management.md)
- [View all shortcode options](../shortcode-reference/01-ad-shortcodes.md)

---

## Upgrade to Pro

Ready for more? WB Ad Manager Pro includes:

- **Advertiser Portal** - Let others submit and pay for ads
- **Classifieds** - Run a marketplace
- **Wallet System** - Accept payments
- **Advanced Analytics** - Detailed reports

[Learn More About Pro →](link-to-pro)

---

*Questions? Check our [Troubleshooting Guide](../troubleshooting/01-common-issues.md)*
