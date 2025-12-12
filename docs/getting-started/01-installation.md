# Installation Guide - WB Starter Ads

## What You'll Learn

- How to install WB Starter Ads (free plugin)
- Initial configuration steps
- How to verify installation

---

## Prerequisites

Before installing, make sure you have:

- WordPress 5.8 or higher
- PHP 7.4 or higher

---

## Installation Methods

### Method A: Install from WordPress.org (Recommended)

1. Log in to your WordPress dashboard
2. Go to **Plugins → Add New**
3. Search for "WB Starter Ads"
4. Click **Install Now**
5. Click **Activate**

### Method B: Upload ZIP File

1. Download the plugin ZIP from WordPress.org
2. Go to **Plugins → Add New → Upload Plugin**
3. Choose the ZIP file
4. Click **Install Now**
5. Click **Activate**

---

## After Installation

Once activated, you'll see:

1. **WB Ad Manager** menu in your WordPress admin sidebar
2. **Ads** - Manage your advertisements
3. **Ad Zones** - Create display areas for ads
4. **Links** - Manage tracked links
5. **Settings** - Configure plugin options

---

## Quick Configuration

### Step 1: Configure Basic Settings

1. Go to **WB Ad Manager → Settings**
2. Review the **General** tab:
   - Enable/disable ad rotation
   - Set default ad behavior
   - Configure tracking options
3. Click **Save Changes**

### Step 2: Create Your First Ad Zone

1. Go to **WB Ad Manager → Ad Zones**
2. Click **Add New**
3. Enter a name (e.g., "Sidebar Banner")
4. Set the size (e.g., 300x250)
5. Click **Publish**

### Step 3: Create Your First Ad

1. Go to **WB Ad Manager → Ads**
2. Click **Add New**
3. Enter ad details:
   - Title (internal use)
   - Ad type (Image, Text, or HTML)
   - Content (image, text, or code)
   - Destination URL
4. Assign to your ad zone
5. Click **Publish**

---

## Verify Installation

To confirm everything is working:

1. Add this shortcode to any page:
   ```
   [wbam_ad zone="your-zone-slug"]
   ```
2. View the page on the frontend
3. Your ad should display
4. Click the ad and check analytics

---

## What's Included (Free Version)

| Feature | Description |
|---------|-------------|
| **Ad Management** | Create and manage unlimited ads |
| **Ad Zones** | Organize ads into display zones |
| **Ad Types** | Image, Text, HTML ads |
| **Ad Rotation** | Random, weighted, sequential |
| **Click Tracking** | Track clicks and impressions |
| **Link Management** | Create and track outbound links |
| **Partnership Forms** | Accept link partnership requests |
| **Shortcodes** | Display ads anywhere |

---

## Upgrade to Pro

Want more features? WB Ad Manager Pro adds:

- Advertiser Portal (let others buy ads)
- Classifieds Marketplace
- Wallet & Payments
- Advanced Analytics
- Email Notifications
- And much more!

[Learn about Pro →](link-to-pro-page)

---

## Next Steps

- [Quick Setup Guide](02-quick-setup-guide.md) - Complete setup in 10 minutes
- [Create Your First Ad](03-first-ad-in-5-minutes.md) - Step-by-step guide
- [Shortcode Reference](../shortcode-reference/01-ad-shortcodes.md) - All display options

---

## Troubleshooting

### Plugin won't activate

- Check PHP version (requires 7.4+)
- Check WordPress version (requires 5.8+)
- Deactivate other plugins to check for conflicts

### Menu not appearing

- Clear your browser cache
- Try logging out and back in
- Check user role has admin capabilities

---

*Need help? Visit our [support forum](link-to-support)*
