# WB Ad Manager - User Guide

Complete user documentation for WB Ad Manager - the powerful WordPress ad management plugin.

---

## Table of Contents

1. [Introduction](#introduction)
2. [Getting Started](#getting-started)
3. [Creating Ads](#creating-ads)
4. [Ad Types](#ad-types)
5. [Placements](#placements)
6. [Targeting & Display Rules](#targeting--display-rules)
7. [Link Management](#link-management)
8. [Link Partnerships](#link-partnerships)
9. [Ad Performance Comparison](#ad-performance-comparison)
10. [Settings](#settings)
11. [Shortcodes](#shortcodes)
12. [FAQ](#faq)

---

## Introduction

WB Ad Manager is a comprehensive WordPress plugin for managing advertisements on your website. Whether you're displaying your own promotional content, running affiliate ads, or managing AdSense, this plugin provides all the tools you need.

### Key Features

- **Multiple Ad Types** - Image, code, rich content, AdSense, and email capture ads
- **Flexible Placements** - Header, footer, sidebar, in-content, popup, and more
- **Advanced Targeting** - Schedule, geo-location, device, user role targeting
- **Link Management** - Create and track affiliate links with cloaking
- **Link Partnerships** - Accept paid link and exchange requests
- **Performance Tracking** - View impressions, clicks, and CTR
- **Ad Comparison** - Compare competing ads to find winners

---

## Getting Started

### Installation

1. Download the plugin ZIP file
2. Go to **Plugins > Add New** in WordPress admin
3. Click **Upload Plugin** and select the ZIP file
4. Click **Install Now** and then **Activate**

### First Ad

1. Navigate to **WB Ads > Add New**
2. Enter a title for your ad (internal use only)
3. Select an ad type (e.g., Image Ad)
4. Upload your ad creative
5. Enter the destination URL
6. Select placements (where to show the ad)
7. Click **Publish**

Your ad is now live!

---

## Creating Ads

### Basic Ad Settings

Every ad has these core settings:

| Setting | Description |
|---------|-------------|
| **Title** | Internal name for the ad (not shown to visitors) |
| **Ad Type** | Type of ad content (image, code, etc.) |
| **Enabled** | Toggle to enable/disable the ad |
| **Destination URL** | Where clicks will redirect |
| **Open in New Tab** | Whether to open link in new window |

### Enabling/Disabling Ads

- **Enable**: Toggle the "Enabled" switch to ON (green)
- **Disable**: Toggle to OFF or use the quick disable in the ad list
- Disabled ads won't display anywhere on your site

### Scheduling Ads

Set when your ad should be active:

1. In the ad editor, find the **Schedule** section
2. Set **Start Date** - when the ad begins showing
3. Set **End Date** - when the ad stops showing
4. Optionally set specific **Days** (Mon, Wed, Fri, etc.)
5. Optionally set **Time Range** (9am - 5pm)

---

## Ad Types

### Image Ad

Display banner images with click tracking.

**Settings:**
- **Image** - Upload or select from media library
- **Destination URL** - Click-through URL
- **Alt Text** - Image alt attribute for accessibility
- **Size** - Original, responsive, or custom dimensions

**Best For:** Banner ads, promotional graphics, affiliate banners

### Code Ad

Insert custom HTML/JavaScript code.

**Settings:**
- **Code** - HTML, JavaScript, or ad network code
- **Wrap in Container** - Add wrapper div

**Best For:** AdSense, third-party ad networks, custom scripts

**Note:** Be careful with JavaScript - only use code from trusted sources.

### Rich Content Ad

Create ads with the WordPress editor.

**Settings:**
- **Content** - Full WYSIWYG editor
- **Destination URL** - Optional click-through

**Best For:** Native ads, content promotions, styled announcements

### AdSense Ad

Dedicated AdSense integration.

**Settings:**
- **Ad Unit ID** - Your AdSense ad unit ID
- **Format** - Auto, horizontal, vertical, rectangle
- **Responsive** - Enable responsive sizing

**Best For:** Google AdSense monetization

### Email Capture Ad

Collect email addresses with a subscription form.

**Settings:**
- **Headline** - Form headline text
- **Description** - Form description
- **Button Text** - Submit button text
- **Show Name Field** - Include name input
- **Success Message** - Thank you message
- **Redirect URL** - Optional redirect after submission

**Best For:** Newsletter signups, lead generation, list building

**Integration:** Use the `wbam_email_captured` hook to integrate with Mailchimp, ConvertKit, etc.

---

## Placements

Placements determine where your ads appear on your site.

### Available Placements

| Placement | Description |
|-----------|-------------|
| **Header** | Top of every page, before content |
| **Footer** | Bottom of every page, after content |
| **Before Content** | Before post/page content starts |
| **After Content** | After post/page content ends |
| **Sidebar** | Widget area (requires adding widget) |
| **After Paragraph X** | After specific paragraph number |
| **Between Posts** | In archive/blog lists |
| **Popup** | Modal overlay |
| **Sticky** | Fixed position bar |
| **Comment Section** | Before/after comments |
| **Shortcode** | Manual placement via shortcode |

### Setting Up Placements

1. Edit your ad
2. Find the **Placements** metabox
3. Check the placements where you want the ad to appear
4. Save the ad

### Sidebar Widget

To display ads in widget areas:

1. Go to **Appearance > Widgets**
2. Find the **WBAM Ad** widget
3. Drag it to your desired widget area
4. Select which ad to display (or random from placement)
5. Save

### Shortcode Placement

For manual placement in content:

```
[wbam_ad id="123"]
```

Or display any ad from a placement:

```
[wbam_placement placement="sidebar"]
```

---

## Targeting & Display Rules

### Display Rules

Control where ads appear on your site.

#### Show on All Pages (with exclusions)

1. Select "All Pages"
2. Add exclusions:
   - **Exclude Posts** - Select specific posts to hide ad
   - **Exclude Categories** - Hide from certain categories
   - **Exclude Tags** - Hide from certain tags
   - **Exclude Page Types** - Hide from front page, blog, archives, etc.

#### Show on Specific Pages Only

1. Select "Specific"
2. Add inclusions:
   - **Post Types** - Show only on posts, pages, or custom types
   - **Specific Posts** - Show on selected posts/pages
   - **Categories** - Show on posts in categories or category archives
   - **Tags** - Show on posts with tags or tag archives
   - **Page Types** - Front page, blog, singular, archive, search, 404

### Visitor Conditions

Target specific visitors.

#### Device Targeting

- **Desktop** - Regular computers
- **Tablet** - iPad, Android tablets
- **Mobile** - Smartphones

Select one or more devices. Ad only shows on selected devices.

#### User Status

- **All Users** - Show to everyone
- **Logged In Only** - Only registered users
- **Logged Out Only** - Only visitors (not logged in)

#### User Roles

When showing to logged-in users, target specific roles:
- Administrator
- Editor
- Author
- Subscriber
- Custom roles

### Geo Targeting

Target by geographic location (if enabled).

- **Countries** - Show in specific countries
- **Regions/States** - Target regions within countries
- **Cities** - Target specific cities

**Note:** Geo targeting requires IP geolocation setup.

---

## Link Management

Create and manage cloaked affiliate links.

### Creating a Link

1. Go to **Links > All Links**
2. Click **Add New**
3. Fill in:
   - **Name** - Internal name for the link
   - **Destination URL** - Where the link points
   - **Cloaked Slug** - The URL path (e.g., "amazon-deal")
4. Configure options:
   - **NoFollow** - Add rel="nofollow"
   - **Sponsored** - Add rel="sponsored"
   - **Tracking** - Enable click tracking
5. Click **Save**

Your cloaked link: `yoursite.com/go/amazon-deal`

### Link Categories

Organize links by category:

1. Go to **Links > Categories**
2. Add new categories
3. Assign categories when editing links

### Using Links

**Shortcode:**
```
[wbam_link id="123"]Click Here[/wbam_link]
```

**Direct URL:**
```
https://yoursite.com/go/your-slug
```

### Viewing Link Stats

Each link shows:
- Total clicks
- Clicks today
- Clicks this week/month
- Click trend

---

## Link Partnerships

Accept paid link and link exchange requests from other websites.

### Setting Up Partnership Form

Add this shortcode to a page:

```
[wbam_partnership_inquiry]
```

This displays a form where others can submit partnership requests.

### Partnership Types

- **Paid Link** - Someone pays to place their link on your site
- **Sponsored Post** - Paid article with links
- **Guest Post** - Content contribution with links
- **Link Exchange** - Mutual linking arrangement
- **Other** - Custom partnership type

### Managing Inquiries

1. Go to **Links > Partnerships**
2. View pending inquiries
3. For each inquiry:
   - **Accept** - Approve the partnership
   - **Reject** - Decline with optional reason
   - **Add Notes** - Internal notes
4. Filter by status: Pending, Accepted, Rejected, All

### Partnership Details

Each inquiry shows:
- Contact name and email
- Website URL
- Partnership type
- Target page (if specified)
- Anchor text request
- Budget range (for paid)
- Message/proposal
- Submission date

---

## Ad Performance Comparison

Compare competing ads to find the best performers.

### Accessing Comparison

1. Edit any ad
2. Find the **Ad Performance Comparison** metabox (right sidebar)

### What It Shows

For all ads sharing the same placements:

| Metric | Description |
|--------|-------------|
| **Impressions** | Times the ad was displayed |
| **Clicks** | Times the ad was clicked |
| **CTR** | Click-through rate (clicks ÷ impressions × 100) |
| **Visual Bar** | Relative CTR comparison |

### Winner Badge

The ad with the highest CTR gets a "Winner" badge.

**Requirements:**
- At least 100 impressions (for statistical significance)
- Must have higher CTR than competitors

### Quick Disable

Found an underperformer? Click "Disable" next to any ad in the comparison to turn it off immediately.

### Tips for A/B Testing

1. Create 2-3 variations of an ad
2. Assign them to the same placement
3. Wait for 100+ impressions each
4. Check the comparison metabox
5. Disable underperformers
6. Create new variations to test

---

## Settings

### General Settings

Navigate to **WB Ads > Settings**

| Setting | Description |
|---------|-------------|
| **Disable for Logged-in Users** | Hide all ads from logged-in users |
| **Disable for Admins** | Hide all ads from administrators |
| **Disabled Post Types** | Post types where ads won't show |

### AdSense Settings

| Setting | Description |
|---------|-------------|
| **Publisher ID** | Your AdSense publisher ID (ca-pub-xxx) |
| **Auto Ads** | Enable AdSense Auto Ads |

### Link Settings

| Setting | Description |
|---------|-------------|
| **Link Prefix** | URL prefix for cloaked links (default: "go") |
| **Default NoFollow** | Add nofollow to all links by default |
| **Enable Tracking** | Track clicks by default |

---

## Shortcodes

### Display Specific Ad

```
[wbam_ad id="123"]
```

**Parameters:**
- `id` - Ad post ID (required)

### Display Ads from Placement

```
[wbam_placement placement="header"]
```

**Parameters:**
- `placement` - Placement ID (required)
- `limit` - Maximum ads to show (default: 1)

### Display Link

```
[wbam_link id="123"]Click Here[/wbam_link]
```

**Parameters:**
- `id` - Link post ID (required)
- Content between tags becomes link text

### Partnership Inquiry Form

```
[wbam_partnership_inquiry]
```

Displays the link partnership request form.

---

## FAQ

### General Questions

**Q: How many ads can I create?**
A: Unlimited. Create as many ads as you need.

**Q: Will ads slow down my site?**
A: No. The plugin is optimized for performance with minimal impact.

**Q: Do ads work with caching plugins?**
A: Yes. Ads are compatible with most caching plugins.

**Q: Can I use multiple ad types together?**
A: Yes. Mix image, code, and other ad types freely.

### Ad Display Issues

**Q: My ad isn't showing. Why?**
Check these common issues:
1. Is the ad enabled? (Toggle must be ON)
2. Is the schedule correct? (Not expired or future-dated)
3. Are placements selected?
4. Check display rules (not excluded from current page)
5. Check visitor conditions (device, user status)
6. Check if ads are disabled for your user role in settings

**Q: Ad shows on some pages but not others.**
A: Check your display rules. You may have exclusions set or "Specific" display mode limiting where ads appear.

**Q: Ad shows but clicks don't track.**
A: Ensure the ad has a destination URL set. Click tracking only works for ads with links.

### Targeting Questions

**Q: How accurate is geo targeting?**
A: IP-based geolocation is typically 95-99% accurate at the country level, less accurate at city level.

**Q: Can I target specific users?**
A: Yes, via user roles. You can also use the `wbam_should_display_ad` filter for custom targeting.

**Q: How does device detection work?**
A: The plugin analyzes the visitor's user agent string to determine device type.

### Link Questions

**Q: Why use cloaked links?**
A: Cloaked links:
- Look cleaner/more trustworthy
- Hide affiliate parameters
- Allow you to change destinations without updating content
- Enable click tracking

**Q: Will cloaked links affect SEO?**
A: No, when using nofollow/sponsored attributes (recommended for affiliate links).

**Q: Can I import existing affiliate links?**
A: Not in the free version. PRO includes a CSV import feature.

### Performance Questions

**Q: What's a good CTR?**
A: CTR varies by industry and ad type:
- Display ads: 0.5% - 1% is typical
- Native ads: 0.2% - 0.5%
- Email capture: 1% - 3%

**Q: How many impressions do I need for reliable data?**
A: At least 100 impressions per ad for meaningful comparison. More is better for statistical significance.

### Partnership Questions

**Q: Do I need to accept every partnership request?**
A: No. You have full control. Review each inquiry and accept/reject based on your criteria.

**Q: Can partners submit directly to my site?**
A: They submit a request form. You review and decide whether to accept.

---

## Getting Help

### Documentation
- This user guide
- Developer guide (for customization)
- Help & Docs section in admin

### Support
- Check our knowledge base
- Contact support for assistance

### Upgrading to PRO
For advanced features like:
- Advertiser portal
- Self-serve ad submissions
- Classifieds
- Advanced analytics
- Payment integration

Navigate to **WB Ads > Upgrade to PRO**

---

*Last updated: December 9, 2024*
