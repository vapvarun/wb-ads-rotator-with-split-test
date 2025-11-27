# WB Ad Manager - Complete User Guide

Your complete guide to monetizing your WordPress site with advertisements. Whether you're displaying Google AdSense, promoting affiliate products, or showcasing sponsor banners, this guide will help you get started in minutes.

---

## Table of Contents

1. [Quick Start Guide](#quick-start-guide)
2. [Understanding Ad Types](#understanding-ad-types)
3. [Choosing the Right Placement](#choosing-the-right-placement)
4. [Step-by-Step Tutorials](#step-by-step-tutorials)
5. [Targeting Your Audience](#targeting-your-audience)
6. [Scheduling Ads](#scheduling-ads)
7. [Using Widgets](#using-widgets)
8. [Using Shortcodes](#using-shortcodes)
9. [Plugin Settings Explained](#plugin-settings-explained)
10. [BuddyPress Integration](#buddypress-integration)
11. [bbPress Integration](#bbpress-integration)
12. [Best Practices](#best-practices)
13. [Troubleshooting](#troubleshooting)
14. [FAQ](#faq)

---

## Quick Start Guide

### Your First Ad in 5 Minutes

Follow these steps to create and display your first ad:

**Step 1: Access the Ad Manager**
- Log into your WordPress admin dashboard
- Look for **"WB Ads"** in the left sidebar menu
- Click **"Add New"**

**Step 2: Create Your Ad**
- Enter a title (e.g., "Sidebar Banner - November 2024") - this is for your reference only
- Under **"Ad Type"**, select **Image Ad** for a simple banner
- Click **"Upload Image"** and select your banner image
- Enter the **Link URL** where you want visitors to go when they click
- Check **"Open in new tab"** if you want the link to open separately

**Step 3: Choose Where to Display**
- Scroll to the **"Placement"** section
- Select **"After Content"** to show your ad below blog posts
- This is the safest option to start with

**Step 4: Publish**
- Click the blue **"Publish"** button
- Visit any blog post on your site to see your ad!

> **Tip:** Your ad won't show on pages, only on posts. To show on pages too, you'll need to adjust the targeting settings.

---

## Understanding Ad Types

WB Ad Manager supports three types of ads. Choose based on what you want to display:

### Image Ad

**What it is:** A clickable banner image.

**Use this when you have:**
- A banner image from an advertiser
- A promotional graphic you've created
- A sponsor logo that links to their website
- An affiliate product image

**Real-world example:**
> You have a 728x90 banner from a software company paying you $200/month to display it. Upload the image, paste their website URL, and you're done.

**How to set it up:**
1. Select **"Image Ad"** as the ad type
2. Click **"Upload Image"** or **"Select from Media Library"**
3. Enter the destination URL in **"Link URL"**
4. Add descriptive **"Alt Text"** (e.g., "50% off hosting at XYZ Company")
5. Choose whether to **"Open in new tab"** (recommended for external links)

**Supported image formats:** JPG, PNG, GIF, WebP

**Recommended sizes:**
| Size | Best For |
|------|----------|
| 728x90 | Leaderboard (header/footer) |
| 300x250 | Medium Rectangle (sidebar) |
| 336x280 | Large Rectangle (in-content) |
| 300x600 | Half Page (sidebar) |
| 320x50 | Mobile Banner |

---

### Rich Content Ad

**What it is:** A mini content block with text, images, and formatting.

**Use this when you want to:**
- Write promotional text with formatting
- Create a newsletter signup box
- Display a custom "Recommended Products" section
- Show a promotional message with styled text

**Real-world example:**
> You want to promote your own ebook with a headline, description, and buy button. Use Rich Content to create a styled promotional block.

**How to set it up:**
1. Select **"Rich Content"** as the ad type
2. Use the WordPress editor to create your content
3. Add headings, bold text, images, and links as needed
4. Use the "Add Media" button to insert images

**Sample Rich Content Ad:**
```html
<div style="background: #f5f5f5; padding: 20px; border-radius: 8px;">
  <h3>Free WordPress Security Checklist</h3>
  <p>Protect your website from hackers with our 25-point security checklist.</p>
  <a href="https://example.com/download" style="background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">Download Free</a>
</div>
```

---

### Code Ad

**What it is:** Raw HTML/JavaScript code for ad networks.

**Use this when you have:**
- Google AdSense code
- Media.net ad code
- Amazon Associates banners
- Any third-party ad network code
- Custom tracking pixels

**Real-world example:**
> You've been approved for Google AdSense and have your ad unit code. Paste the entire code block into a Code Ad.

**How to set it up:**
1. Select **"Code Ad"** as the ad type
2. Paste your ad network code exactly as provided
3. Don't modify the code unless you know what you're doing

**Google AdSense Example:**
```html
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-XXXXXXXXXXXXXXXX" crossorigin="anonymous"></script>
<ins class="adsbygoogle"
     style="display:block"
     data-ad-client="ca-pub-XXXXXXXXXXXXXXXX"
     data-ad-slot="XXXXXXXXXX"
     data-ad-format="auto"
     data-full-width-responsive="true"></ins>
<script>
     (adsbygoogle = window.adsbygoogle || []).push({});
</script>
```

> **Warning:** Only paste code from trusted sources. Malicious code can harm your website and visitors.

---

## Choosing the Right Placement

Placement determines WHERE your ad appears. Choose wisely - placement significantly impacts click-through rates and user experience.

### Quick Placement Guide

| Goal | Recommended Placement |
|------|----------------------|
| Maximum visibility | Before Content or Header |
| Good engagement without annoyance | After Paragraph 2 or 3 |
| Non-intrusive | After Content or Sidebar Widget |
| Promote something important | Popup (use sparingly!) |
| Always visible | Sticky Bottom Bar |
| Blog monetization | Archive (between posts) |

---

### Placement Types Explained

#### Header
**Where:** At the very top of every page, before any content.

**Best for:** Important announcements, site-wide promotions.

**Pros:** Maximum visibility
**Cons:** Can feel intrusive, may slow initial page load perception

**Example use:** "Black Friday Sale - 50% off everything!"

---

#### Footer
**Where:** At the bottom of every page.

**Best for:** Secondary promotions, affiliate disclosures.

**Pros:** Non-intrusive
**Cons:** Low visibility (many visitors don't scroll to bottom)

---

#### Before Content
**Where:** Right before the main post/page content begins.

**Best for:** Relevant promotions, newsletter signups.

**Pros:** High visibility, seen before reading
**Cons:** Can deter readers if too large

**Tip:** Keep these ads compact. A 728x90 leaderboard works well here.

---

#### After Content
**Where:** Right after the post/page content ends.

**Best for:** Related products, "You might also like" promotions.

**Pros:** Reaches engaged readers who finished your content
**Cons:** Only seen by readers who scroll to the end

**This is often the best placement for beginners** - it doesn't interrupt the reading experience.

---

#### After Paragraph (Most Flexible)
**Where:** Injected after a specific paragraph in your content.

**Configuration options:**
- **Paragraph Number:** Which paragraph to insert after (e.g., 2 = after second paragraph)
- **Repeat Every:** Show the ad again every X paragraphs (e.g., 5 = show after paragraphs 2, 7, 12...)
- **Minimum Paragraphs:** Only show if the content has at least X paragraphs

**Best for:** In-content ads that feel native, AdSense optimization.

**Real-world example:**
> Your blog posts average 10 paragraphs. Set "After Paragraph: 3" and "Repeat Every: 4" to show ads after paragraphs 3 and 7, giving readers breaks without overwhelming them.

**Tip:** Don't place ads after paragraph 1 - let readers get into your content first.

---

#### Archive (Between Posts)
**Where:** In blog listing pages, between post summaries.

**Configuration:**
- **Position:** Show after every X posts (e.g., 3 = after posts 3, 6, 9...)

**Best for:** Blog index pages, category archives.

**Example:** On your homepage showing latest posts, an ad appears after every 3 posts.

---

#### Sticky/Floating Ads
**Where:** Fixed position that stays visible while scrolling.

**Types available:**
| Type | Position | Best For |
|------|----------|----------|
| Bottom Right | Fixed bottom-right corner | Newsletter signups, chat widgets |
| Bottom Left | Fixed bottom-left corner | Cookie notices, secondary promos |
| Bottom Bar | Full-width bar at bottom | Important announcements |
| Top Bar | Full-width bar at top | Sales, limited-time offers |

**Configuration options:**
- **Show Close Button:** Let users dismiss the ad
- **Delay:** Show after X seconds (don't show immediately!)

**Best practices:**
- Always include a close button
- Set a delay of at least 3-5 seconds
- Don't use more than one sticky ad
- Mobile users especially dislike sticky ads - consider disabling for mobile

---

#### Popup/Modal Ads
**Where:** Overlay that appears on top of the page.

**Trigger options:**
| Trigger | When It Shows | Best For |
|---------|---------------|----------|
| Time Delay | After X seconds on page | Newsletter signups |
| Scroll Percentage | After scrolling X% of page | Engaged readers |
| Exit Intent | When mouse moves toward closing | Last-chance offers |

**Configuration:**
- **Frequency Limit:** Maximum times to show per visitor session
- **Delay/Trigger:** When to show the popup

**Real-world example:**
> Show a newsletter signup popup when visitors scroll 50% down the page (they're engaged) but only once per session (don't annoy them).

**Warning:** Overusing popups will drive visitors away. Use sparingly and always provide value.

---

#### Comment Placements
**Where:** Around the comment section on posts.

| Position | Where Exactly |
|----------|---------------|
| Before Comment Form | Above the "Leave a Reply" form |
| After Comment Form | Below the submit button |
| Between Comments | Between individual comments |

**Best for:** Posts with active comment sections, community-focused sites.

---

## Step-by-Step Tutorials

### Tutorial 1: Setting Up Google AdSense

**Goal:** Display Google AdSense ads in your blog posts.

**Prerequisites:**
- Approved Google AdSense account
- At least one ad unit created in AdSense

**Steps:**

1. **Get your AdSense code:**
   - Log into Google AdSense
   - Go to Ads → By ad unit
   - Click on your ad unit
   - Copy the entire code

2. **Create the ad in WB Ad Manager:**
   - Go to WB Ads → Add New
   - Title: "AdSense - In Content Auto"
   - Ad Type: **Code Ad**
   - Paste your AdSense code in the code box

3. **Set the placement:**
   - Placement: **After Paragraph**
   - Paragraph Number: **3**
   - Repeat Every: **5** (shows every 5 paragraphs)
   - Minimum Paragraphs: **4** (only on longer posts)

4. **Configure targeting:**
   - Display Rules: Show on **Posts** only
   - Visitor Conditions: Show to **All visitors**

5. **Publish and test:**
   - Click Publish
   - Visit a blog post with 5+ paragraphs
   - You should see your AdSense ad after paragraph 3

> **AdSense Tip:** Create multiple ads with different placements (After Content, Sidebar Widget) to maximize revenue while staying within AdSense policies.

---

### Tutorial 2: Affiliate Product Banner in Sidebar

**Goal:** Display an affiliate banner in your sidebar that links to a product.

**Steps:**

1. **Prepare your banner:**
   - Download the affiliate banner from your affiliate program
   - Get your affiliate link (with your tracking ID)

2. **Create the ad:**
   - Go to WB Ads → Add New
   - Title: "Amazon Affiliate - Web Hosting"
   - Ad Type: **Image Ad**
   - Upload your banner image
   - Link URL: Your affiliate link (e.g., `https://affiliate.com/product?ref=yourID`)
   - Alt Text: "Best web hosting - 50% off"
   - Check "Open in new tab"

3. **Set up the widget:**
   - Go to Appearance → Widgets
   - Find **"WB Ad Manager Widget"**
   - Drag it to your sidebar
   - Select your ad from the dropdown
   - Save

4. **Optional - Target specific content:**
   - Edit your ad
   - Under Display Rules, select specific categories
   - Example: Only show hosting ads on posts in the "WordPress" category

---

### Tutorial 3: Holiday Sale Announcement Bar

**Goal:** Show a promotional bar at the top of your site during Black Friday.

**Steps:**

1. **Create the ad:**
   - Go to WB Ads → Add New
   - Title: "Black Friday 2024 Sale"
   - Ad Type: **Rich Content**
   - Create your message:
   ```
   🎉 BLACK FRIDAY SALE: 50% off all courses! Use code FRIDAY50. Ends Monday!
   ```
   - Style it with center alignment and make the text bold

2. **Set the placement:**
   - Placement: **Sticky - Top Bar**
   - Enable "Show close button"

3. **Schedule it:**
   - Under Schedule:
   - Start Date: November 29, 2024, 00:00
   - End Date: December 2, 2024, 23:59

4. **Publish:**
   - Click Publish (or Schedule if you set future dates)
   - The bar will automatically appear and disappear based on your schedule

---

### Tutorial 4: Newsletter Popup for Engaged Readers

**Goal:** Show a newsletter signup popup to readers who've scrolled halfway through your content.

**Steps:**

1. **Create your signup form:**
   - Get your email signup form HTML from Mailchimp, ConvertKit, etc.

2. **Create the ad:**
   - Go to WB Ads → Add New
   - Title: "Newsletter Popup"
   - Ad Type: **Rich Content** or **Code Ad**
   - Add your signup form content

3. **Set the placement:**
   - Placement: **Popup**
   - Trigger: **Scroll Percentage**
   - Scroll Percentage: **50**
   - Frequency Limit: **1** (show only once per session)

4. **Target appropriately:**
   - Display Rules: Show on **Posts** only (not on pages)
   - Visitor Conditions: **Logged Out users** only (logged-in users likely already subscribed)

5. **Publish and test:**
   - Visit a blog post
   - Scroll down 50%
   - The popup should appear
   - Close it and scroll again - it shouldn't reappear (session limit)

---

## Targeting Your Audience

Targeting lets you control WHO sees your ads. This is crucial for relevance and conversion rates.

### Display Rules

Control which pages/posts show your ad.

**Show on All Pages (Default)**
- Ad appears everywhere on your site
- Good for site-wide announcements

**Show on Specific Post Types**
- **Posts:** Blog articles
- **Pages:** Static pages (About, Contact)
- **Products:** WooCommerce products (if installed)
- Custom post types from other plugins

**Example:** Show affiliate product ads only on blog posts, not on your About or Contact pages.

**Show on Specific Page Types**
| Page Type | What It Means |
|-----------|---------------|
| Front Page | Your homepage only |
| Blog Page | Your main blog listing page |
| Archive | Category, tag, date archives |
| Search Results | Search results page |
| 404 Page | "Page not found" error page |

**Show on Specific Categories/Tags**
- Select specific categories or tags
- Ad only shows on posts in those categories/tags

**Example:** You have a "Photography" category and a camera affiliate deal. Target the ad to only show on Photography posts.

---

### Exclude Rules

Sometimes it's easier to say where NOT to show ads.

**Exclude Page Types**
- Exclude 404 and Search pages (low conversion)
- Exclude your sales pages (don't distract buyers)

**Exclude Categories/Tags**
- Exclude sensitive categories from showing ads
- Exclude categories where ads aren't relevant

---

### Visitor Conditions

Control WHO sees your ad based on visitor attributes.

**Device Targeting**
| Device | Description |
|--------|-------------|
| Desktop | Computers and laptops |
| Tablet | iPad and similar devices |
| Mobile | Smartphones |

**Example:** Your ad is 728px wide and looks bad on mobile. Target Desktop and Tablet only.

**User Status**
| Status | Who |
|--------|-----|
| All | Everyone |
| Logged In | Registered users who are logged in |
| Logged Out | Visitors who aren't logged in |

**Example:** Show ads only to logged-out visitors - your paying members shouldn't see ads.

**User Roles**
- Show or hide ads for specific WordPress roles
- Subscriber, Author, Editor, Administrator, etc.

**Example:** Hide all ads from Administrators so you can test your site without ads.

---

### Geo Targeting

Show ads based on visitor location.

**How it works:**
1. When a visitor loads your page, their IP address is checked
2. A geolocation service determines their country
3. Your targeting rules are applied

**Setting up geo targeting:**

1. **Configure the provider (one-time setup):**
   - Go to WB Ads → Settings → Geo Targeting
   - Primary Provider: **ip-api.com** (recommended, free)
   - Click Save

2. **Apply geo rules to your ad:**
   - Edit your ad
   - Find the **Geo Targeting** section
   - Enable geo targeting
   - Choose countries to Include OR Exclude

**Example scenarios:**

| Scenario | Configuration |
|----------|---------------|
| US-only affiliate program | Include: United States |
| Don't show to EU (GDPR concern) | Exclude: All EU countries |
| Show different ads per region | Create separate ads for each region |

**Provider comparison:**

| Provider | Cost | Requests | Accuracy |
|----------|------|----------|----------|
| ip-api.com | Free | 45/minute | Good |
| ipapi.co | Free | 1,000/day | Good |
| ipinfo.io | Free tier available | 50,000/month | Excellent |

**What about VPNs?**
Visitors using VPNs will appear to be from the VPN server's location. There's no reliable way to detect this.

---

## Scheduling Ads

Schedule ads to run during specific times - perfect for sales, events, and rotating promotions.

### Basic Scheduling

**Start Date:** When the ad begins showing
**End Date:** When the ad stops showing

**Example:** Run a Christmas sale ad from December 15-25.

1. Edit your ad
2. Under Schedule:
   - Start Date: December 15, 2024
   - End Date: December 25, 2024
3. Publish (even if the start date is in the future)

The ad will automatically activate and deactivate based on your schedule.

---

### Advanced Scheduling

**Days of the Week**
Only show on specific days.

**Example:** Restaurant ad that only shows on Friday, Saturday, Sunday (weekend specials).

1. Edit your ad
2. Under Advanced Schedule:
   - Check: Friday, Saturday, Sunday
   - Uncheck: Monday, Tuesday, Wednesday, Thursday

**Time of Day**
Only show during specific hours.

**Example:** Lunch special ad from 11 AM to 2 PM.

1. Edit your ad
2. Under Advanced Schedule:
   - Start Time: 11:00
   - End Time: 14:00

**Combining rules:**
You can combine days and times. Example: "Happy Hour" ad that shows only on Fridays from 4 PM to 7 PM.

> **Important:** Times use your WordPress timezone. Check Settings → General → Timezone to ensure it's correct.

---

## Using Widgets

Widgets let you place ads in your theme's widget areas (sidebars, footer, etc.).

### Adding an Ad Widget

1. Go to **Appearance → Widgets**
2. Find **"WB Ad Manager Widget"** in the available widgets
3. Drag it to your desired widget area (e.g., "Sidebar")
4. Configure:
   - **Title:** Optional heading above the ad (leave blank for no heading)
   - **Select Ad:** Choose from your published ads
5. Click **Save**

### Widget Tips

- **Create a widget-specific ad:** When creating ads for widgets, set the placement to "Widget" so they don't also appear elsewhere.
- **Multiple ads in sidebar:** Add multiple WB Ad Manager widgets with different ads.
- **Responsive consideration:** Sidebar ads typically need to be 300px wide or less.

### BuddyPress-Specific Widgets

If you have BuddyPress installed, you get additional widgets:

| Widget | Shows On | Example Use |
|--------|----------|-------------|
| BP Profile Ad | Member profile pages | "Upgrade to Premium" promotion |
| BP Group Ad | Group pages | Group-related sponsors |
| BP Activity Ad | Activity stream | Social engagement ads |

### bbPress-Specific Widgets

If you have bbPress installed:

| Widget | Shows On | Example Use |
|--------|----------|-------------|
| bbPress Forum Ad | Forum pages | Forum sponsors |
| bbPress Topic Ad | Topic discussion pages | Related product ads |

---

## Using Shortcodes

Shortcodes let you place ads anywhere you can add text - posts, pages, text widgets, and even some theme areas.

### Basic Shortcode

```
[wbam_ad id="123"]
```

Replace `123` with your ad's ID.

### Finding Your Ad ID

1. Go to **WB Ads → All Ads**
2. Hover over the ad title
3. Look at the bottom of your browser - you'll see a URL like:
   `site.com/wp-admin/post.php?post=123&action=edit`
4. The number after `post=` is your Ad ID (123 in this example)

**Alternative:** Edit the ad and look at the URL in your browser's address bar.

### Multiple Ads Shortcode

Display several ads at once:

```
[wbam_ads ids="123,456,789"]
```

This respects priority settings - higher priority ads show first.

### Shortcode Examples

**In a blog post:**
> Check out our sponsor:
> [wbam_ad id="45"]
> Now back to the article...

**In a text widget:**
Add a Text widget, then add:
```
[wbam_ad id="45"]
```

**In a page builder:**
Most page builders (Elementor, Divi, etc.) have a "Shortcode" or "Text" widget where you can paste shortcodes.

### When to Use Shortcodes vs. Placements

| Use Shortcodes When | Use Placements When |
|---------------------|---------------------|
| You want an ad in ONE specific post | You want an ad on ALL posts |
| You need precise control over position | You want automatic insertion |
| The ad is unique to that content | The ad is a general promotion |
| You're using a page builder | You want set-and-forget |

---

## Plugin Settings Explained

Navigate to **WB Ads → Settings** to configure global options.

### General Settings

**Disable ads for logged-in users**
- When ON: No ads shown to anyone logged into your site
- Use case: You sell memberships and want ad-free experience for members

**Disable ads for administrators**
- When ON: Admins never see ads (but other users do)
- Use case: You want to browse your site without ads while testing

**Minimum content length for paragraph ads**
- Number of characters required before paragraph ads display
- Default: 500 characters
- Use case: Prevent ads from appearing on very short posts

**Disabled post types**
- Select post types where ads should never appear
- Use case: Disable ads on WooCommerce products or portfolio items

---

### Display Settings

**Ad label text**
- Text displayed with your ads (e.g., "Advertisement", "Sponsored")
- Leave blank for no label
- Required by some ad networks and for FTC compliance with affiliate ads

**Label position**
- **Above:** Label appears above the ad
- **Below:** Label appears below the ad

**Container CSS class**
- Add custom CSS classes to all ad containers
- Use case: Apply your own styling to ads
- Example: `my-custom-ad-style`

---

### Performance Settings

**Enable lazy loading**
- When ON: Ads load only when they're about to enter the viewport
- Benefits: Faster initial page load, better Core Web Vitals
- Note: Some ad networks (like AdSense) have their own lazy loading

**Cache ad queries**
- When ON: Database queries for ads are cached
- Benefits: Faster page loads on high-traffic sites
- Note: New ads may take a few minutes to appear after publishing

**Maximum ads per page**
- Limit the total number of ads on any single page
- Default: 0 (unlimited)
- Use case: Prevent ad overload if you have many ads with overlapping targets

---

### Geo Targeting Settings

**Primary provider**
Your first-choice geolocation service.
- **ip-api.com** (recommended): Free, fast, 45 requests/minute limit
- **ipapi.co:** Free, 1,000 requests/day limit
- **ipinfo.io:** Requires API key, 50,000/month free tier

**ipinfo.io API key**
If using ipinfo.io, enter your API key here.
Get one free at: https://ipinfo.io/signup

---

## BuddyPress Integration

If BuddyPress is active, you get additional ad placement options.

### Activity Stream Ads

Show ads within the activity feed (like Facebook's sponsored posts).

**Setup:**
1. Create a new ad
2. Set Placement to **BuddyPress Activity**
3. Configure **Position:** After every X activities (e.g., 5 = after activities 5, 10, 15...)
4. Publish

**Best practices:**
- Use image or rich content ads (code ads may look awkward)
- Match the visual style of activity items
- Don't set position too low (showing ads too frequently)

### Member/Group Directory Ads

Show ads in member and group listings.

**Available positions:**
- Before the member/group list
- After the member/group list
- Between members/groups (repeating)

**Setup:**
1. Create a new ad
2. Set Placement to **BuddyPress Members Directory** or **BuddyPress Groups Directory**
3. Choose the specific position
4. If "Between," set the repeat interval
5. Publish

### BuddyPress Widgets

Place ads on specific BuddyPress pages using widgets:

| Widget | Where to Use |
|--------|--------------|
| BP Profile Ad Widget | Sidebar on member profiles |
| BP Group Ad Widget | Sidebar on group pages |
| BP Activity Ad Widget | Sidebar on activity pages |

**Why use these special widgets?**
They automatically detect if you're on the right type of page. A "BP Profile Ad Widget" only shows when viewing a member profile, even if it's in a sidebar that appears on other pages too.

---

## bbPress Integration

If bbPress is active, you get forum-specific placements.

### Forum Placements

| Position | Where It Shows |
|----------|----------------|
| Before Forums | Above the forum list |
| After Forums | Below the forum list |
| Before Topics | Above the topic list in a forum |
| After Topics | Below the topic list in a forum |
| Before Single Topic | Above a topic's content |
| After Single Topic | Below a topic's content |
| Between Replies | Between individual replies in a topic |

**Example setup for forum monetization:**

1. **Create a leaderboard ad for forum tops:**
   - Placement: Before Forums
   - Good for sponsor banners

2. **Create an in-content ad between replies:**
   - Placement: Between Replies
   - Position: Every 5 replies
   - Good for AdSense

### bbPress Widgets

| Widget | Best Used For |
|--------|---------------|
| bbPress Forum Ad Widget | Sidebar ads on all forum pages |
| bbPress Topic Ad Widget | Sidebar ads only when viewing topics |

---

## Best Practices

### Do's

**Start small**
- Begin with 1-2 ads in non-intrusive placements
- Monitor user feedback and analytics
- Gradually add more if engagement stays high

**Match ad content to page content**
- Tech product ads on tech articles
- Fitness ads on health content
- Use targeting to ensure relevance

**Test different placements**
- Try the same ad in different positions
- Compare click-through rates
- Optimize based on data, not assumptions

**Use scheduling for promotions**
- Schedule seasonal ads in advance
- Set end dates so old promotions don't linger
- Plan your ad calendar monthly

**Optimize for mobile**
- Test all ads on mobile devices
- Use responsive images
- Consider hiding some ads on mobile

**Label affiliate ads**
- Use the ad label feature for FTC compliance
- "Affiliate Link" or "Sponsored" are common choices

### Don'ts

**Don't overwhelm visitors**
- More ads ≠ more revenue
- Too many ads = visitors leave
- Quality over quantity

**Don't place ads too early**
- Let visitors start reading first
- After paragraph 1 is too early
- After paragraph 2-3 is the sweet spot

**Don't use multiple popup ads**
- One popup maximum per page
- Users hate popups - use sparingly
- Always provide a close button

**Don't forget mobile users**
- 50%+ of traffic is mobile
- Sticky/floating ads are especially annoying on mobile
- Test everything on a real phone

**Don't mix competing ads**
- Don't show competitor products together
- Check advertiser exclusivity requirements
- Use targeting to separate incompatible ads

**Don't ignore load time**
- Ads add to page load time
- Enable lazy loading
- Optimize image file sizes
- Consider ad placement impact on Core Web Vitals

---

## Troubleshooting

### Ad Not Showing

**Check these in order:**

1. **Is the ad Published?**
   - Go to WB Ads → All Ads
   - Status should show "Published," not "Draft"

2. **Is it within the schedule?**
   - Edit the ad
   - Check Start Date and End Date
   - Ensure current date/time falls within range

3. **Are you excluded by targeting?**
   - Check Display Rules - are you on an excluded page type?
   - Check Visitor Conditions - are you logged in when it targets logged-out users?
   - Check Geo Targeting - are you in an excluded country?

4. **Is your user role excluded?**
   - Check if "Disable for admins" is on in Settings
   - Check Visitor Conditions for role restrictions

5. **Is the placement correct?**
   - "After Paragraph 5" won't show on a 3-paragraph post
   - "BuddyPress Activity" won't show without BuddyPress active

6. **Is caching interfering?**
   - Clear your caching plugin cache
   - Clear your CDN cache (Cloudflare, etc.)
   - Clear your browser cache
   - Try an incognito/private browser window

7. **Is there a JavaScript error?**
   - Open browser Developer Tools (F12)
   - Check the Console tab for errors
   - Errors can prevent ads from loading

---

### Ad Shows in Wrong Place

1. **Verify placement selection**
   - Edit the ad and confirm the placement setting

2. **Check for duplicate ads**
   - You might have multiple ads with the same placement
   - Priority determines which shows first

3. **Theme conflict**
   - Some themes modify content areas
   - Try switching to a default theme temporarily

---

### Ad Looks Wrong/Broken

1. **Image too large/small**
   - Resize your image to match the placement
   - Sidebar: max 300px wide
   - Content: max 728px wide

2. **CSS conflict**
   - Your theme's CSS may affect ad styling
   - Add custom CSS to fix:
   ```css
   .wbam-ad img {
       max-width: 100%;
       height: auto;
   }
   ```

3. **Code ad broken**
   - Check if the ad network code is complete
   - Some networks require additional setup
   - Test the code on a blank HTML page first

---

### Geo Targeting Not Working

1. **Test the provider**
   - Go to WB Ads → Settings → Geo Targeting
   - Use the test button if available
   - Try a different provider

2. **Check rate limits**
   - Free providers have request limits
   - High-traffic sites may exceed limits
   - Consider ipinfo.io with API key for higher limits

3. **VPN/Proxy interference**
   - If testing, disable any VPN
   - Your server might be detected as a different country
   - Test from a mobile device on cellular data

4. **Caching conflict**
   - Page caching caches the same content for all visitors
   - Geo-targeted ads need special handling with caching
   - Consult your caching plugin documentation

---

### Performance Issues

1. **Enable lazy loading**
   - Settings → Performance → Enable lazy loading

2. **Enable ad query caching**
   - Settings → Performance → Cache ad queries

3. **Reduce ad count**
   - Set a maximum ads per page limit
   - Remove low-performing ads

4. **Optimize images**
   - Compress image ads before uploading
   - Use WebP format if possible
   - Resize to actual display dimensions

5. **Audit third-party code**
   - Some ad network code is slow
   - Test page speed with and without Code ads
   - Consider async loading options

---

## FAQ

### General Questions

**Q: How many ads can I create?**
A: Unlimited. Create as many as you need.

**Q: Can I use this with Google AdSense?**
A: Yes! Use the "Code Ad" type and paste your AdSense code. Make sure to follow AdSense policies regarding ad placement and density.

**Q: Will ads slow down my site?**
A: Any ads add some load time. Minimize impact by:
- Enabling lazy loading
- Optimizing image sizes
- Not overloading pages with ads
- Using ad query caching

**Q: Can I show different ads to different users?**
A: Yes, use Visitor Conditions to target by login status, user role, or device type. Use Geo Targeting for location-based targeting.

---

### Billing & Advertiser Questions

**Q: Can I sell ad space to advertisers?**
A: The free version doesn't include payment processing. You can manually create ads for paying advertisers. The Pro version includes an advertiser portal with payments.

**Q: How do I track ad performance?**
A: Basic tracking isn't included in the free version. You can:
- Use UTM parameters in your links
- Check Google Analytics for landing page traffic
- The Pro version includes detailed analytics

---

### Technical Questions

**Q: Does this work with page builders?**
A: Yes. For automatic placements (header, footer, content), it works automatically. For manual placement, use the shortcode in your page builder's text or shortcode widget.

**Q: Is this compatible with caching plugins?**
A: Yes, with one caveat: geo-targeted and user-role-targeted ads may cache incorrectly. Most caching plugins have options to exclude certain elements from caching.

**Q: Can I style the ads with CSS?**
A: Yes. All ads are wrapped with classes you can target:
- `.wbam-ad` - All ads
- `.wbam-placement` - Placement wrapper
- `.wbam-ad-image` - Image ads
- `.wbam-ad-rich-content` - Rich content ads
- `.wbam-ad-code` - Code ads

**Q: Why doesn't my ad show to logged-in users?**
A: Check Settings → General → "Disable ads for logged-in users." Also check the ad's Visitor Conditions settings.

---

### Common Scenarios

**Q: I want ads only on blog posts, not pages. How?**
A: Edit your ad → Display Rules → Post Types → Select only "Posts."

**Q: I want to show a promotional bar during my sale only. How?**
A: Create the ad with Sticky Top Bar placement, then set the Start Date and End Date under Schedule to your sale period.

**Q: I want different sidebar ads for different categories. How?**
A: Create separate ads for each category. On each ad, go to Display Rules → Categories and select the relevant category.

**Q: I have a membership site and don't want members to see ads. How?**
A: Go to Settings → General → Enable "Disable ads for logged-in users." Or, on individual ads, set Visitor Conditions → User Status to "Logged Out."

**Q: How do I make sure my ad works on mobile?**
A:
1. Use responsive images (the plugin handles this automatically)
2. Test on a real mobile device
3. For very wide ads (728px+), consider creating a separate mobile-friendly ad and using Device Targeting

---

## Getting Help

**Before contacting support:**
1. Check this documentation
2. Try the troubleshooting steps
3. Test with a default theme and no other plugins

**When contacting support, include:**
- WordPress version
- Plugin version
- Theme name
- Brief description of the issue
- Steps to reproduce
- Any error messages

---

*Documentation Version: 1.1.0*
*Last Updated: November 2024*
