=== Wbcom Designs - WB Ad Manager ===
Contributors: vapvarun, wbcomdesigns
Donate link: https://wbcomdesigns.com/
Tags: ads, ad manager, ad rotation, split test, adsense
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Comprehensive ad management for WordPress with ad rotation, split testing, multiple placements, Google AdSense, BuddyPress, and bbPress integration.

== Description ==

WB Ad Manager is a powerful and easy-to-use ad management plugin for WordPress. It allows you to create and manage ads with multiple placement options, targeting rules, and supports BuddyPress and bbPress.

**Key Features:**

* **Ad Rotation & Split Testing** - Multiple ads rotate in same placement with weighted priority
* **4 Ad Types** - Image, Rich Content, HTML/JS Code, and Google AdSense
* **14+ Placements** - Header, Footer, Content, Paragraph, Sticky, Popup, Comments, Archive, Shortcode, Widget, BuddyPress, bbPress
* **Google AdSense** - Native AdSense support with automatic script management and Auto Ads
* **BuddyPress Integration** - Activity stream, member/group directories, profile widgets
* **bbPress Integration** - Forum, topic, and reply ad placements
* **Geo-Targeting** - Target ads by country using IP geolocation
* **Device Targeting** - Desktop, tablet, or mobile specific ads
* **Scheduling** - Start/end dates, day-of-week, and time-of-day targeting
* **Frequency Control** - Limit ad impressions per session
* **Setup Wizard** - Easy first-time configuration with sample ads

**Ad Types:**

1. **Image Ad** - Banner images with link, alt text, and target options
2. **Rich Content** - WYSIWYG editor for HTML content
3. **HTML/JS Code** - Paste ad network code (custom scripts)
4. **Google AdSense** - Native integration with auto script management

**Placements:**

* Header (wp_head)
* Footer (wp_footer)
* Before/After Post Content
* After Paragraph X (with repeat option)
* Archive Pages (between posts)
* Sticky/Floating Ads (corners, bars)
* Popup/Modal Ads (time delay, scroll, exit intent)
* Comment Areas
* Shortcode `[wbam_ad id="123"]`
* Widget Areas
* BuddyPress Activity Stream
* BuddyPress Member/Group Directories
* bbPress Forums and Topics

**Targeting Options:**

* Post types and page types
* Categories and tags
* Device type (desktop/tablet/mobile)
* User status (logged in/out)
* User roles
* Geographic location (country)
* Custom scheduling

= WB Ad Manager Pro =

Take your ad management to the next level with [WB Ad Manager Pro](https://wbcomdesigns.com/downloads/wb-ad-manager-pro/). The Pro version includes everything in the free plugin plus:

**Advertiser Portal & Self-Service:**

* Complete advertiser dashboard with analytics
* Self-service ad submission and management
* Wallet system with Stripe payments
* Campaign management with budgets and goals
* Advertiser performance tracking

**Classifieds Marketplace:**

* Full classified listings system
* Category and location taxonomies
* Featured listings and upgrades
* Seller profiles and following system
* Inquiry management

**Advanced Link Management:**

* Affiliate link cloaking and tracking
* Link scanner to find monetization opportunities
* Partnership management system
* Click tracking and analytics

**Revenue & Analytics:**

* Revenue dashboard with earnings reports
* CPM, CPC, and flat-rate billing
* A/B testing with statistical analysis
* Share of Voice reporting
* Detailed impression and click analytics

**Community Integrations:**

* Enhanced BuddyPress integration
* Seller profiles in member directories
* Activity stream for listings
* Following/favorites system

[Learn more about WB Ad Manager Pro](https://wbcomdesigns.com/downloads/wb-ad-manager-pro/)

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/wb-ad-manager/` directory, or install through WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Complete the Setup Wizard or go to WB Ad Manager menu to create your first ad.

== Frequently Asked Questions ==

= How do I create an ad? =

Go to WB Ad Manager > Add New. Enter a title, select the ad type, add your content, choose placements, and publish.

= How do I display an ad using shortcode? =

Use the shortcode `[wbam_ad id="123"]` where 123 is your ad ID. For multiple ads: `[wbam_ads ids="1,2,3"]`

= Does this plugin support Google AdSense? =

Yes! WB Ad Manager has native AdSense support. Set your Publisher ID in Settings, then create AdSense ad types. The AdSense script is automatically managed and only loads once per page.

= Does this plugin support BuddyPress? =

Yes! If BuddyPress is active, you can display ads in activity streams, member directories, group directories, and use BuddyPress-specific widgets.

= Does this plugin support bbPress? =

Yes! If bbPress is active, you can display ads in forums, topics, and between replies.

= Can I schedule ads? =

Yes, you can set start/end dates, specific days of the week, and time-of-day ranges for each ad.

= What geo-targeting providers are supported? =

The plugin supports ip-api.com (free), ipinfo.io (free tier), and ipapi.co for IP geolocation.

== Screenshots ==

1. Ad listing screen with status and placement info
2. Add new ad screen with ad type selection
3. Google AdSense ad configuration
4. Placement options with multiple choices
5. Targeting rules metabox
6. Settings page with AdSense configuration
7. Setup wizard for first-time users

== Changelog ==

= 2.5.0 =
* Fix: Add GDPR privacy helper for IP anonymization in frequency tracking
* Fix: Frequency tracking now properly calls track_impression via wbam_ad_output filter
* Improvement: Add npm scripts for build/dist/watch commands
* Improvement: Fix Gruntfile makepot config for correct plugin name
* Improvement: Add future roadmap for planned features
* Dev: Update POT file for translations

= 2.4.0 =
* Security: GDPR compliance - stop storing raw IP addresses in analytics
* Security: Add user-based rate limiting to AJAX handlers
* Security: Add capability check to setup wizard dismiss handler
* Security: Document security model for unescaped ad output in placements
* Security: Add security measures for code ad type
* Performance: Add object caching for placement ad queries
* Performance: Cache table existence checks to avoid repeated queries
* Fix: Impressions not being recorded properly
* Fix: Image upload/remove button functionality
* Fix: Paragraph placement HTML corruption with preg_replace_callback
* Fix: wp_send_json_error signature and add missing HTTP status codes
* Fix: Raw $_POST passed to hooks before sanitization
* Fix: Geo targeting UI simplified with single mode selector
* Fix: Device detection reliability improvements
* Fix: Image ad UI with proper container width constraints
* Fix: Display Rules UI clarity and organization
* Fix: Specific Pages dropdown now only shows pages
* Fix: 16 additional bugs from comprehensive audit
* New: Comprehensive marketing materials included

= 2.0.0 =
* Complete rewrite with modern architecture
* Ad rotation and split testing with weighted priority system
* 4 ad types: Image, Rich Content, Code, Google AdSense
* 14+ placement options including sticky, popup, and comment ads
* Google AdSense integration with Auto Ads support
* BuddyPress integration (activity stream, directories, widgets)
* bbPress integration (forums, topics, replies)
* Geo-targeting with 3 IP providers
* Device, schedule, and user targeting
* Frequency control and ad priority
* Setup wizard with sample ads
* Full internationalization support
* PSR-4 style namespaces and modular architecture

= 1.0.0 =
* Legacy version

== Upgrade Notice ==

= 2.5.0 =
Build system improvements and translation updates.

= 2.4.0 =
Security and stability update with GDPR compliance, performance caching, and 20+ bug fixes. Recommended update for all users.

= 2.0.0 =
Major update! Complete rewrite with modern architecture, 14+ placements, Google AdSense Auto Ads, BuddyPress & bbPress integration. Backup recommended before updating.
