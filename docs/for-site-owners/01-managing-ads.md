# Managing Ads - Site Owner Guide

## What You'll Learn

- How to create and manage ad zones
- How to create different ad types
- How to schedule and rotate ads
- Best practices for ad management

---

## Understanding Ad Zones

Ad zones are containers that hold and display your ads. Think of them as "slots" on your website where ads appear.

### Creating an Ad Zone

1. Go to **WB Ad Manager → Ad Zones**
2. Click **Add New**
3. Configure the zone:
   - **Title**: Descriptive name (e.g., "Sidebar Banner")
   - **Slug**: URL-friendly name (e.g., `sidebar-banner`)
   - **Width**: Zone width in pixels
   - **Height**: Zone height in pixels
4. Set rotation options
5. Click **Publish**

### Common Zone Sizes

| Name | Size | Best Use |
|------|------|----------|
| Leaderboard | 728x90 | Header, above content |
| Medium Rectangle | 300x250 | Sidebar, in-content |
| Wide Skyscraper | 160x600 | Sidebar |
| Large Rectangle | 336x280 | In-content |
| Mobile Banner | 320x50 | Mobile sites |
| Half Page | 300x600 | Sidebar (large) |

### Zone Rotation Types

| Type | How It Works |
|------|--------------|
| **Random** | Shows random ad each load |
| **Weighted** | Higher weight = more shows |
| **Sequential** | Cycles through in order |
| **Single** | Always shows same ad |

---

## Creating Ads

### Step-by-Step

1. Go to **WB Ad Manager → Ads**
2. Click **Add New**
3. Enter ad title (internal reference)
4. Choose ad type
5. Add content based on type
6. Assign to zone(s)
7. Set schedule (optional)
8. Click **Publish**

### Ad Types

#### Image Ads

Best for: Banners, promotional graphics

**Required fields:**
- Image upload (JPG, PNG, GIF)
- Destination URL
- Alt text (for accessibility)

**Tips:**
- Optimize images before upload
- Match image size to zone size
- Use clear call-to-action
- Animated GIFs are supported

#### Text Ads

Best for: Simple promotions, links

**Required fields:**
- Headline (main clickable text)
- Description (supporting text)
- Destination URL

**Tips:**
- Keep headlines under 25 characters
- Use action words (Get, Save, Try)
- Include clear benefit

#### HTML Ads

Best for: Third-party ad code, rich media

**Required fields:**
- HTML/JavaScript code

**Tips:**
- Test code before saving
- Be careful with JavaScript
- Use for AdSense, affiliate codes
- Check mobile compatibility

---

## Ad Scheduling

### Setting Date Ranges

When creating/editing an ad:

1. Find **Schedule** section
2. Set **Start Date** - When ad becomes active
3. Set **End Date** - When ad expires (optional)
4. Save changes

### Schedule Examples

| Scenario | Start Date | End Date |
|----------|------------|----------|
| Always active | Leave empty | Leave empty |
| Future launch | Dec 25, 2024 | Leave empty |
| Limited time | Dec 1, 2024 | Dec 31, 2024 |
| One day | Dec 25, 2024 | Dec 25, 2024 |

---

## Ad Weighting

Control how often ads show relative to others in the same zone.

### Setting Weight

1. Edit the ad
2. Find **Weight** field
3. Enter value (1-100)
4. Save

### Weight Examples

| Ad | Weight | Approximate Show Rate |
|----|---------|-----------------------|
| Premium Sponsor | 100 | 50% |
| Regular Sponsor | 50 | 25% |
| House Ad A | 25 | 12.5% |
| House Ad B | 25 | 12.5% |

> Higher weight = more impressions

---

## Organizing Ads

### Using Categories

Create categories to organize ads:

1. Go to **WB Ad Manager → Ads → Categories**
2. Add categories (e.g., "Sponsors", "House Ads")
3. Assign ads to categories when editing

### Best Practices

- Use consistent naming conventions
- Create categories by advertiser or campaign
- Archive old ads instead of deleting
- Use tags for additional organization

---

## Displaying Ads

### Via Shortcode

Add to any page, post, or widget:

```
[wbam_ad zone="sidebar-banner"]
```

### Via Widget

1. Go to **Appearance → Widgets**
2. Add "WB Ad Manager" widget
3. Select zone
4. Save

### Via PHP (Theme)

```php
<?php echo do_shortcode('[wbam_ad zone="sidebar-banner"]'); ?>
```

---

## Tracking Performance

### Viewing Stats

1. Go to **WB Ad Manager → Analytics**
2. View overall stats:
   - Impressions
   - Clicks
   - CTR (Click-through rate)
3. Filter by date range
4. View individual ad performance

### Key Metrics

| Metric | What It Means | Good Target |
|--------|---------------|-------------|
| **Impressions** | Times ad shown | Varies |
| **Clicks** | Times clicked | More is better |
| **CTR** | Clicks ÷ Impressions | 0.5%+ |

---

## Managing Third-Party Ads

### Google AdSense

1. Create new ad (HTML type)
2. Paste AdSense code
3. Assign to zone
4. Publish

### Affiliate Networks

1. Get affiliate banner code
2. Create HTML ad
3. Paste code
4. Track via analytics

### Tips

- Test ads load correctly
- Check mobile display
- Monitor for policy violations
- Use fallback ads for ad blockers

---

## Bulk Operations

### Bulk Edit Ads

1. Go to **WB Ad Manager → Ads**
2. Check multiple ads
3. Select bulk action:
   - Move to Trash
   - Edit (change status, zone)
4. Click Apply

### Export/Import

- Export: Use WordPress export (Tools → Export)
- Import: Use WordPress import (Tools → Import)

---

## Best Practices

### Ad Placement

| Location | Effectiveness | Notes |
|----------|---------------|-------|
| Above fold | High | Seen immediately |
| Sidebar | Medium | Always visible |
| In-content | High | Read with content |
| Footer | Low | Often missed |

### Performance Tips

1. **A/B test** - Try different creatives
2. **Rotate frequently** - Prevent ad blindness
3. **Match context** - Relevant ads perform better
4. **Optimize images** - Faster load times
5. **Monitor CTR** - Remove underperformers

### Common Mistakes

- Too many ads per page (3-5 is ideal)
- Ads that don't match content
- Slow-loading ad images
- Ignoring mobile users
- Not tracking performance

---

## Next Steps

- [Set up link management](02-link-management.md)
- [View all shortcodes](../shortcode-reference/01-ad-shortcodes.md)
- [Troubleshooting](../troubleshooting/01-common-issues.md)

---

*Need more features? [Upgrade to Pro](link-to-pro) for advertiser portal, payments, and more.*
