# Link Management Shortcodes

## What You'll Learn

- How to display tracked links
- How to create link partnership forms
- All available parameters and options

---

## Available Shortcodes

| Shortcode | Purpose |
|-----------|---------|
| `[wbam_link]` | Display single tracked link |
| `[wbam_links]` | Display list of links |
| `[wbam_link_url]` | Output raw tracked URL |
| `[wbam_partnership_inquiry]` | Link partnership request form |

---

## [wbam_link] - Single Link Display

Display a tracked link with click counting.

### Basic Usage

```
[wbam_link id="123"]
```

### All Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `id` | int | required | Link ID |
| `text` | string | link title | Display text |
| `class` | string | - | CSS class |
| `target` | string | "_blank" | Link target |
| `rel` | string | varies | Rel attribute |
| `nofollow` | bool | false | Add nofollow |

### Examples

**Basic tracked link:**
```
[wbam_link id="123"]
```

**Custom anchor text:**
```
[wbam_link id="123" text="Visit Our Partner"]
```

**With custom styling:**
```
[wbam_link id="123" class="partner-link featured"]
```

**Nofollow link (SEO):**
```
[wbam_link id="123" nofollow="true"]
```

**Open in same window:**
```
[wbam_link id="123" target="_self"]
```

**Sponsored link (rel attribute):**
```
[wbam_link id="123" rel="sponsored"]
```

---

## [wbam_links] - Link List Display

Display multiple links in a list or grid.

### Basic Usage

```
[wbam_links]
```

### All Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `category` | string | all | Filter by category |
| `count` | int | 10 | Number of links |
| `layout` | string | "list" | "list" or "grid" |
| `columns` | int | 3 | Grid columns |
| `orderby` | string | "date" | "date", "title", "random" |
| `show_description` | bool | true | Show link description |
| `show_icon` | bool | true | Show favicon |

### Examples

**All partner links:**
```
[wbam_links category="partners"]
```

**Sponsor grid:**
```
[wbam_links category="sponsors" count="8" layout="grid" columns="4"]
```

**Random featured links:**
```
[wbam_links count="5" orderby="random"]
```

**Simple list without icons:**
```
[wbam_links layout="list" show_icon="false" show_description="false"]
```

**Footer resources:**
```
[wbam_links category="resources" count="6" columns="3"]
```

---

## [wbam_link_url] - Raw URL Output

Output just the tracked URL (for use in custom HTML).

### Basic Usage

```
[wbam_link_url id="123"]
```

### Use Cases

**In custom button:**
```html
<a href="[wbam_link_url id='123']" class="my-button">
    Click Here
</a>
```

**In image link:**
```html
<a href="[wbam_link_url id='123']">
    <img src="partner-logo.png" alt="Partner Name">
</a>
```

**In CSS background:**
```html
<a href="[wbam_link_url id='123']" class="banner-link"
   style="background-image: url('banner.jpg')">
    Special Offer
</a>
```

---

## [wbam_partnership_inquiry] - Partnership Form

Display a form for visitors to request link partnerships.

### Basic Usage

```
[wbam_partnership_inquiry]
```

### All Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `title` | string | "Request Link Partnership" | Form title |
| `success_message` | string | default text | Success message |
| `redirect` | string | - | Redirect URL after submit |
| `show_title` | bool | true | Display form title |

### Examples

**Basic form:**
```
[wbam_partnership_inquiry]
```

**Custom title:**
```
[wbam_partnership_inquiry title="Become a Partner"]
```

**Custom success message:**
```
[wbam_partnership_inquiry success_message="Thanks! We'll review your request within 24 hours."]
```

**Redirect after submission:**
```
[wbam_partnership_inquiry redirect="/thank-you/"]
```

**Without title:**
```
[wbam_partnership_inquiry show_title="false"]
```

### Form Fields

The form collects:
- Name (required)
- Email (required)
- Website URL (required)
- Desired anchor text
- Message/pitch
- Preferred link category

---

## Creating Links

### Step 1: Add a New Link

1. Go to **WB Ad Manager → Links**
2. Click **Add New**
3. Enter:
   - **Title**: Display name
   - **URL**: Destination URL
   - **Description**: Optional description
4. Set options:
   - **Category**: For organization
   - **Nofollow**: SEO setting
   - **Target**: New window or same
5. Click **Publish**

### Step 2: Get the Link ID

After publishing, note the link ID from:
- The URL: `post=123`
- The links list ID column

### Step 3: Display the Link

```
[wbam_link id="123"]
```

---

## Link Categories

Organize links into categories for easy filtering:

### Common Categories

| Category | Use For |
|----------|---------|
| `sponsors` | Paid sponsors |
| `partners` | Business partners |
| `resources` | Helpful resources |
| `affiliates` | Affiliate links |

### Creating Categories

1. Go to **WB Ad Manager → Links → Categories**
2. Add new category
3. Use category slug in shortcodes

---

## Tracking & Analytics

### What's Tracked

For each link:
- Total clicks
- Unique visitors
- Click dates/times
- Referrer pages

### Viewing Stats

1. Go to **WB Ad Manager → Links**
2. See click counts in the list
3. Click a link to see detailed stats

---

## Page Setup Examples

### Partners Page

```html
<h1>Our Partners</h1>
<p>We're proud to work with these amazing companies.</p>

[wbam_links category="partners" layout="grid" columns="3" show_description="true"]

<h2>Become a Partner</h2>
[wbam_partnership_inquiry title="Apply for Partnership"]
```

### Sponsors Sidebar

```html
<div class="sponsors-widget">
    <h4>Our Sponsors</h4>
    [wbam_links category="sponsors" count="5" layout="list" show_description="false"]
</div>
```

### Resources Section

```html
<section class="resources">
    <h2>Helpful Resources</h2>
    [wbam_links category="resources" count="6" layout="grid" columns="2"]
</section>
```

---

## Styling Links

### CSS Classes

```css
.wbam-link                 /* Single link */
.wbam-links-container      /* Links list container */
.wbam-links-list           /* List layout */
.wbam-links-grid           /* Grid layout */
.wbam-link-item            /* Individual link item */
.wbam-link-title           /* Link title text */
.wbam-link-description     /* Link description */
.wbam-link-icon            /* Favicon container */
.wbam-partnership-form     /* Partnership form */
```

### Example Styles

```css
/* Partner cards */
.wbam-links-grid .wbam-link-item {
    padding: 20px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    text-align: center;
    transition: box-shadow 0.2s;
}

.wbam-links-grid .wbam-link-item:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

/* Sponsor list */
.wbam-links-list .wbam-link-item {
    padding: 10px 0;
    border-bottom: 1px solid #eee;
}
```

---

## SEO Considerations

### Nofollow Links

For paid/sponsored links, use nofollow:

```
[wbam_link id="123" nofollow="true"]
```

Or set it per-link in the admin.

### Rel Attributes

| Attribute | When to Use |
|-----------|-------------|
| `nofollow` | Paid links, untrusted |
| `sponsored` | Paid advertisements |
| `ugc` | User-submitted links |

---

## Troubleshooting

### Link not tracking clicks

1. Check link is published
2. Verify tracking enabled in settings
3. Test in incognito mode
4. Check for JavaScript errors

### Form not submitting

1. Check all required fields filled
2. Verify email format is valid
3. Check for AJAX errors in console
4. Ensure form isn't cached

### Links not displaying

1. Verify link ID is correct
2. Check link category exists
3. Ensure shortcode syntax is correct

---

*See also: [Ad Shortcodes](01-ad-shortcodes.md)*
