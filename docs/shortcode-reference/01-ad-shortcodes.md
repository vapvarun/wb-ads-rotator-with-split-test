# Ad Display Shortcodes

## What You'll Learn

- How to display ads using shortcodes
- All available parameters
- Common use cases and examples

---

## Available Shortcodes

| Shortcode | Purpose |
|-----------|---------|
| `[wbam_ad]` | Display single ad or zone |
| `[wbam_ads]` | Display multiple ads |

---

## [wbam_ad] - Single Ad Display

Display a single ad or all ads from a zone with rotation.

### Basic Usage

```
[wbam_ad zone="sidebar-banner"]
```

### All Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `zone` | string | - | Ad zone slug to display |
| `id` | int | - | Specific ad ID to display |
| `size` | string | - | Override size (e.g., "300x250") |
| `class` | string | - | Custom CSS class |
| `fallback` | string | - | HTML to show if no ads |
| `lazy` | bool | false | Enable lazy loading |

### Examples

**Display ads from a zone:**
```
[wbam_ad zone="header-leaderboard"]
```

**Display specific ad by ID:**
```
[wbam_ad id="123"]
```

**With custom CSS class:**
```
[wbam_ad zone="sidebar" class="my-custom-ad"]
```

**With fallback content:**
```
[wbam_ad zone="sidebar" fallback="<p>Advertise here!</p>"]
```

**With lazy loading (for below-fold ads):**
```
[wbam_ad zone="footer" lazy="true"]
```

**Override display size:**
```
[wbam_ad zone="sidebar" size="250x250"]
```

---

## [wbam_ads] - Multiple Ads Display

Display multiple ads from a zone in a grid or list layout.

### Basic Usage

```
[wbam_ads zone="sponsors" count="4"]
```

### All Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `zone` | string | - | Ad zone slug |
| `count` | int | 3 | Number of ads to show |
| `columns` | int | 1 | Grid columns (1-4) |
| `layout` | string | "grid" | "grid" or "list" |
| `class` | string | - | Custom CSS class |
| `orderby` | string | "random" | "random", "date", "weight" |

### Examples

**Show 4 sponsor ads in 2 columns:**
```
[wbam_ads zone="sponsors" count="4" columns="2"]
```

**Show ads in a vertical list:**
```
[wbam_ads zone="sidebar" count="5" layout="list"]
```

**Show highest weighted ads first:**
```
[wbam_ads zone="premium" count="3" orderby="weight"]
```

**Show newest ads first:**
```
[wbam_ads zone="latest" count="6" orderby="date"]
```

**With custom container class:**
```
[wbam_ads zone="partners" count="4" columns="4" class="partner-logos"]
```

---

## Finding Zone Slugs

To find your ad zone slug:

1. Go to **WB Ad Manager → Ad Zones**
2. Look at the "Slug" column
3. Use that slug in your shortcode

Example slugs:
- `header-banner`
- `sidebar`
- `footer-ads`
- `in-content`

---

## Using in Different Locations

### In Page/Post Content

Simply add the shortcode in the editor:
```
[wbam_ad zone="sidebar-banner"]
```

### In Widgets

1. Add a "Custom HTML" or "Text" widget
2. Paste the shortcode
3. Save

### In Theme Templates (PHP)

```php
<?php echo do_shortcode('[wbam_ad zone="sidebar-banner"]'); ?>
```

### In Theme with Function

```php
<?php
if (function_exists('wbam_display_zone')) {
    wbam_display_zone('sidebar-banner');
}
?>
```

---

## Styling Your Ads

### Default CSS Classes

```css
.wbam-ad              /* Single ad container */
.wbam-ad-zone         /* Zone container */
.wbam-ad-item         /* Individual ad in grid */
.wbam-ad-image        /* Image ad type */
.wbam-ad-text         /* Text ad type */
.wbam-ad-html         /* HTML ad type */
.wbam-ad-link         /* Clickable link wrapper */
```

### Custom Styling Examples

```css
/* Add border to all ads */
.wbam-ad {
    border: 1px solid #ddd;
    padding: 10px;
}

/* Rounded corners */
.wbam-ad img {
    border-radius: 8px;
}

/* Hover effect */
.wbam-ad:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

/* Grid gap */
.wbam-ad-zone.sponsors {
    gap: 20px;
}
```

---

## Ad Rotation

### How Rotation Works

When multiple ads are assigned to a zone:

| Rotation Type | Behavior |
|--------------|----------|
| **Random** | Different ad each page load |
| **Weighted** | Higher weight = more likely |
| **Sequential** | Cycles through in order |

### Setting Ad Weight

1. Edit your ad
2. Find the "Weight" field
3. Set value (1-100)
4. Higher numbers = more impressions

Example weights:
- Premium sponsor: 100
- Regular sponsor: 50
- House ads: 10

---

## Troubleshooting

### Ad not showing

1. Check zone slug is correct (case-sensitive)
2. Verify ad is published (not draft)
3. Check ad is assigned to the zone
4. Verify start/end dates if set
5. Clear any caching

### Wrong size displaying

1. Check uploaded image dimensions
2. Use `size` parameter to override
3. Add CSS: `.wbam-ad img { max-width: 100%; height: auto; }`

### Multiple ads showing same ad

1. Check you have multiple ads in the zone
2. Verify rotation type isn't "sequential"
3. Clear cache between page loads

### Clicks not tracking

1. Verify tracking is enabled in settings
2. Check destination URL is valid
3. Test in incognito mode (ad blockers)

---

## Performance Tips

1. **Optimize images** - Compress before uploading
2. **Use lazy loading** - For below-fold ads
3. **Limit zones per page** - 3-5 is optimal
4. **Enable caching** - In plugin settings
5. **Use appropriate sizes** - Don't upscale small images

---

## Complete Example

Here's a typical sidebar setup:

```html
<aside class="sidebar">
    <h3>Sponsors</h3>
    [wbam_ads zone="sidebar-sponsors" count="3" layout="list"]

    <h3>Featured</h3>
    [wbam_ad zone="sidebar-featured"]
</aside>
```

---

*See also: [Link Shortcodes](02-link-shortcodes.md)*
