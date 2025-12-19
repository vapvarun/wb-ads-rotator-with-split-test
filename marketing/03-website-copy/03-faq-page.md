# WB Ad Manager - FAQ Page

**URL:** /faq/
**Purpose:** Answer common questions, reduce support load, improve SEO
**Tone:** Helpful, clear, confidence-building

---

## Page Header

### Headline
"Frequently Asked Questions"

### Subheadline
"Everything you need to know about WB Ad Manager. Can't find your answer? Contact our support team."

---

## General Questions

### What is WB Ad Manager?

WB Ad Manager is a complete advertising management plugin for WordPress. It lets you create, display, and track advertisements on your website - from simple banner ads to complex classified listings and advertiser portals.

The free version includes full ad management, affiliate link tracking, and community integrations. The Pro version adds classified marketplaces, advertiser self-service portals, advanced analytics, and A/B testing.

---

### Is WB Ad Manager really free?

Yes. The free version on WordPress.org is fully functional with no artificial limits. You can create unlimited ads, use all placement options, track affiliate links, and integrate with BuddyPress/bbPress - all for free.

WB Ad Manager Pro is a separate plugin that adds additional features for sites that want to sell advertising or run classified marketplaces.

---

### Who is WB Ad Manager for?

WB Ad Manager works for any WordPress site that wants to monetize with advertising:

- **Bloggers** - Display banner ads and track affiliate links
- **Affiliate marketers** - Cloak and track hundreds of affiliate URLs
- **Community sites** - Monetize BuddyPress/bbPress platforms
- **Directory sites** - Run classified listings with paid placements
- **Publishers** - Let advertisers manage their own campaigns

---

### How is this different from other ad plugins?

Most ad plugins do one thing - display banners OR track links OR run classifieds. WB Ad Manager combines all of these into one unified system:

- Ad management with 20+ placements
- Affiliate link cloaking and tracking
- BuddyPress and bbPress integration
- AdSense management
- (Pro) Classified marketplace
- (Pro) Advertiser portal with wallet
- (Pro) A/B testing

One plugin, one database, one interface. No conflicts between multiple plugins.

---

## Installation & Setup

### How do I install WB Ad Manager?

**From WordPress:**
1. Go to Plugins → Add New
2. Search "WB Ad Manager"
3. Click "Install Now"
4. Click "Activate"

**Manual Installation:**
1. Download the plugin ZIP file
2. Go to Plugins → Add New → Upload Plugin
3. Select the ZIP file
4. Click "Install Now"
5. Click "Activate"

---

### Do I need to configure anything before using it?

The plugin works out of the box, but we recommend running through the Setup Wizard:

1. After activation, click the "Setup" notice or go to WB Ad Manager → Settings
2. Configure your ad label preferences
3. Enter your AdSense Publisher ID (if applicable)
4. Set visibility preferences for administrators

This takes about 2 minutes.

---

### Does this work with my theme?

Yes. WB Ad Manager is designed to work with any properly-coded WordPress theme. It uses standard WordPress hooks, widgets, and shortcodes for placement.

In the rare case of a theme conflict, we provide CSS customization options and our support team can help with adjustments.

---

### Does this work with page builders?

Yes. WB Ad Manager ads can be displayed in:

- **Elementor** - Use the WordPress widget element or shortcode element
- **Beaver Builder** - Use the WordPress widget module or HTML module with shortcode
- **Divi** - Use the Code module with shortcode
- **Gutenberg** - Use the WB Ad Manager block or shortcode block
- **WPBakery** - Use the Text Block element with shortcode

---

## Ad Creation & Management

### What types of ads can I create?

**Free version:**
- Image ads (upload any banner image)
- Rich content (WYSIWYG editor)
- HTML/Code (custom HTML, JavaScript, or embed codes)
- AdSense (Google AdSense integration)
- Email Capture (newsletter signup forms)

**Pro version adds:**
- Classified listings
- Advertiser-submitted ads

---

### Where can I display ads?

**Content placements:**
- Before post content
- After post content
- After paragraph X
- Between archive posts

**Widget placements:**
- Any sidebar
- Any footer area
- Any widget-ready area

**Overlay placements:**
- Exit intent popup
- Timed popup
- Scroll-triggered popup

**Sticky placements:**
- Sticky footer bar
- Sticky header bar

**Community placements:**
- BuddyPress activity stream
- BuddyPress profiles
- bbPress topics
- bbPress replies

---

### Can I schedule ads to run at specific times?

Yes. Each ad has scheduling options:

- **Date range** - Start and end dates
- **Days of week** - Only run on selected days
- **Time of day** - Only show during specific hours

This is perfect for time-sensitive promotions or weekend-only campaigns.

---

### Can I show different ads to different users?

Yes. Targeting options include:

- **Device** - Desktop, tablet, mobile (any combination)
- **Login status** - Logged-in only, logged-out only, or both
- **User role** - Specific WordPress user roles
- **Geographic location** - Target by country (Pro)

You can also control which content types show ads (posts, pages, custom post types) and include/exclude specific categories.

---

### Is there a limit on how many ads I can create?

No. Both free and Pro versions allow unlimited ads. Create as many as you need.

---

## Affiliate Links

### How does link cloaking work?

When you create a cloaked link, WB Ad Manager:

1. Takes your affiliate URL (the ugly one with tracking codes)
2. Creates a clean redirect URL on your domain (e.g., yoursite.com/go/product-name)
3. When visitors click the clean URL, they're redirected to the affiliate URL
4. The click is logged for your analytics

The visitor never sees the affiliate URL in their browser until after they click.

---

### What redirect types are available?

- **301 (Permanent)** - Best for SEO, tells search engines this is a permanent redirect
- **302 (Temporary)** - For short-term promotions
- **307 (Temporary)** - Preserves HTTP method (for advanced use cases)

Most affiliate links should use 301 redirects.

---

### How does broken link detection work?

WB Ad Manager periodically checks your destination URLs to verify they still work. If a link returns a 404 error, changed redirect, or domain issue, you'll see a warning in your links dashboard.

This helps you catch problems before visitors encounter broken links (and you lose commissions).

---

### Can I import existing affiliate links?

Yes. Use the bulk import feature to:

1. Export your existing links to a CSV file
2. Upload the CSV to WB Ad Manager
3. Map columns to fields (URL, name, slug)
4. Import all links at once

Each imported link gets automatic cloaking and tracking.

---

## BuddyPress & bbPress

### Does this work with BuddyPress?

Yes. When BuddyPress is active, WB Ad Manager automatically adds placement options for:

- Activity stream (between activities)
- Member profiles
- Group pages

No additional configuration needed.

---

### Does this work with bbPress?

Yes. When bbPress is active, WB Ad Manager automatically adds placement options for:

- Forum topics (between replies)
- Forum archives (between topic listings)

No additional configuration needed.

---

### What if I don't use BuddyPress or bbPress?

Those placement options simply won't appear. WB Ad Manager detects which plugins are active and shows only relevant options. It won't slow down your site or create conflicts.

---

## Google AdSense

### How do I set up AdSense?

1. Go to WB Ad Manager → Settings → AdSense
2. Enter your Publisher ID (starts with "pub-")
3. Choose whether to enable Auto Ads globally
4. Save settings

Now when you create a new ad, select "AdSense" as the ad type and configure your ad unit settings.

---

### Can I use AdSense Auto Ads?

Yes. Enable Auto Ads in settings and Google will automatically place ads on your site. You can still create manual AdSense ads for specific placements.

---

### Can I control where AdSense ads appear?

Yes. AdSense ads created in WB Ad Manager get the same targeting options as all other ads:

- Specific post types
- Specific categories
- Device targeting
- User targeting
- Scheduling

This gives you more control than using AdSense codes directly.

---

## Pro Features

### What's included in WB Ad Manager Pro?

**Classified Marketplace**
- Frontend submission forms
- Paid upgrades (featured, highlighted, urgent)
- Categories and locations
- Automatic expiration
- Inquiry system

**Advertiser Portal**
- Advertiser self-registration
- Wallet system (Stripe, PayPal, WooCommerce)
- Advertiser ad submission
- Approval workflow
- Advertiser analytics

**A/B Testing**
- Split traffic between ad variants
- Statistical significance tracking
- Automatic winner promotion

**Advanced Analytics**
- Charts and graphs over time
- Revenue tracking
- Device breakdown
- Geographic data
- Export to CSV

**Link Partnerships**
- Mutual link exchange tracking
- Partner performance analytics

---

### How much does Pro cost?

Visit our pricing page for current pricing. We offer single-site and unlimited-site licenses with annual renewals.

---

### Is there a trial or refund policy?

Yes. We offer a 14-day money-back guarantee. If WB Ad Manager Pro doesn't meet your needs, contact support within 14 days for a full refund.

---

### Can I upgrade from Free to Pro later?

Absolutely. Install WB Ad Manager Free, set up your ads, and upgrade to Pro when you need advanced features. All your existing ads, links, and settings are preserved.

---

## Technical Questions

### Will this slow down my site?

No. WB Ad Manager is optimized for performance:

- Lazy loading for popup and sticky ads
- Efficient database queries with caching
- Minimal frontend JavaScript
- No external API calls for ad display

Page load impact is negligible.

---

### Is this compatible with caching plugins?

Yes. WB Ad Manager works with all major caching plugins including:

- WP Super Cache
- W3 Total Cache
- LiteSpeed Cache
- WP Rocket

For dynamic targeting (user-based ads), the plugin handles cache fragmentation automatically.

---

### Does this work with Multisite?

Yes. WB Ad Manager can be activated on individual sites within a WordPress Multisite network. Each site manages its own ads independently.

---

### Is the plugin GDPR compliant?

WB Ad Manager itself doesn't set tracking cookies or collect personal data from visitors. If you use third-party ad networks like AdSense, compliance depends on those networks.

For click tracking, we log anonymized data (click timestamps, page URLs) that isn't personally identifiable.

---

## Support & Updates

### How do I get support?

**Free version:**
- Community support via WordPress.org forums
- Documentation on our website

**Pro version:**
- Priority email support
- Dedicated support portal
- Faster response times

---

### How often is the plugin updated?

We release updates regularly for:

- WordPress compatibility (major WP releases)
- Security patches (as needed)
- Bug fixes (ongoing)
- New features (based on roadmap)

Active license holders receive all updates automatically.

---

### Where can I find documentation?

Documentation is available at [website]/docs/. It includes:

- Getting Started Guide
- Ad Creation Tutorials
- Placement Reference
- Targeting Options
- Pro Feature Guides
- Developer Hooks Reference

---

## Contact

### Have a question we didn't answer?

**Free users:** Post on the WordPress.org support forum
**Pro users:** Submit a ticket at [support portal URL]
**General inquiries:** [contact email]

We typically respond within 24-48 hours on business days.
