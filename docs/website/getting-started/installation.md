---
title: Installation Guide
persona: Evaluator — Free
tier: free
one_job: Get someone trying the free plugin from zero to a working install, verified.
outcome: Reader can download the free plugin from wbcomdesigns.com, install it by ZIP upload, meet the prerequisites, and confirm the install succeeded.
assumes: WordPress 5.8+, PHP 7.4+, admin access to the target site.
---

# Installation Guide

![WP Ad Manager active on the Plugins list](../images/free/install-active.png)

## What You'll Learn

- How to install WB Ad Manager (free plugin)
- Initial configuration steps
- How to verify installation

---

## Prerequisites

Before installing, make sure you have:

- WordPress 5.8 or higher
- PHP 7.4 or higher

---

## Installation Methods

WB Ad Manager is distributed from wbcomdesigns.com. Both the free plugin and Pro
come from the same downloads page, so start by downloading the ZIP: searching the
WordPress plugin directory will not find it.

**[Download WB Ad Manager](https://wbcomdesigns.com/downloads/wb-ad-manager/)**

### Method A: Upload ZIP File (Recommended)

1. Download the plugin ZIP from the [downloads page](https://wbcomdesigns.com/downloads/wb-ad-manager/)
2. Go to **Plugins → Add New → Upload Plugin**
3. Choose the ZIP file
4. Click **Install Now**
5. Click **Activate**

---

## After Installation

Once activated, you'll see a **WB Ad Manager** menu in your WordPress admin sidebar with:

1. **Ads** - Create and manage your advertisements
2. **Links** - Manage tracked affiliate links
3. **Settings** - Configure plugin options

---

## Quick Configuration

### Step 1: Configure Basic Settings

1. Go to **WB Ad Manager → Settings**
2. Review the **General** tab:
   - Optionally hide ads from logged-in users or admins
   - Configure maximum ads per page
3. Click **Save Settings**

### Step 2: Create Your First Ad

1. Go to **WB Ad Manager → Ads**
2. Click **Add New**
3. Enter the ad title (internal reference)
4. Choose an ad type (Image, Rich Content, Code/HTML/JS, AdSense, or Email Capture)
5. Fill in the ad content
6. In the **Placements** metabox, check where the ad should appear (header, footer, content, etc.)
7. Click **Publish**

> **How rotation works:** Multiple ads assigned to the same placement rotate automatically based on priority. Higher priority ads display more often.

---

## Verify Installation

To confirm everything is working:

1. Add this shortcode to any page or post, replacing `123` with a real ad ID:
   ```
   [wbam_ad id="123"]
   ```
2. View the page on the frontend
3. Your ad should display
4. Click the ad and check analytics

---

## What's Included (Free Version)

| Feature | Description |
|---------|-------------|
| **Ad Management** | Create and manage unlimited ads |
| **5 Ad Types** | Image, Rich Content, Code/HTML/JS, AdSense, Email Capture |
| **14+ Placements** | Header, footer, content, sidebar, and more |
| **Priority-Based Rotation** | Higher priority ads show more often |
| **Click Tracking** | Track clicks and impressions |
| **Link Management** | Create and track affiliate links |
| **Partnership Forms** | Accept link partnership requests |
| **Shortcodes** | Display specific ads anywhere |

---

## Upgrade to Pro

Want more features? WB Ad Manager Pro adds:

- Advertiser Portal (let others buy ads)
- Classifieds Marketplace
- Wallet & Payments
- Advanced Analytics
- Email Notifications
- And much more!

*Learn about WB Ad Manager Pro for advanced features like campaigns, wallet, classifieds, and analytics.*

---

## Next Steps

- [Quick Setup Guide](quick-setup-guide.md) — configure the plugin and publish your first ad
- [Shortcode Reference](../shortcode-reference/ad-shortcodes.md) - Display ads anywhere

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

*Need help? Visit the support forum*
