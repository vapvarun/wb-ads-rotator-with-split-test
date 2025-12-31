# Email Capture Ads

Build your email list with newsletter signup forms that appear as ads. Email Capture ads can be placed anywhere regular ads appear - in content, as popups, in sidebars, or sticky bars.

## What You'll Learn

- Creating an email capture ad
- Configuring the signup form
- Integration with email services
- Preventing repeat popups
- Tracking signups

---

## Creating an Email Capture Ad

### Step 1: Create New Ad

1. Go to **WB Ads > Add New**
2. Enter a title (e.g., "Newsletter Signup Popup")
3. Select **Email Capture** as the ad type

### Step 2: Configure the Form

| Field | Description | Example |
|-------|-------------|---------|
| **Headline** | Main title of the form | "Join 10,000+ subscribers" |
| **Description** | Supporting text | "Get weekly tips delivered to your inbox" |
| **Button Text** | Submit button label | "Subscribe Now" |
| **Success Message** | Shown after signup | "Thanks! Check your inbox." |

### Step 3: Form Fields

| Option | Description |
|--------|-------------|
| **Show Name Field** | Collect subscriber's name |
| **Name Required** | Make name field mandatory |
| **Email Placeholder** | Text inside email field |
| **Name Placeholder** | Text inside name field |

### Step 4: Behavior Settings

| Setting | Description | Default |
|---------|-------------|---------|
| **Cookie Days** | Days before showing again to same visitor | 7 |
| **Redirect URL** | Page to redirect after signup (optional) | None |
| **Privacy Text** | Privacy policy note (optional) | None |

---

## Integration Options

### Built-in Email Storage

By default, signups are stored in WordPress. View them at **WB Ads > Email Signups**.

### Third-Party Integration

Use hooks to send signups to your email service:

```php
add_action( 'wbam_email_captured', function( $email, $name, $ad_id ) {
    // Send to your email service
    // Mailchimp, ConvertKit, etc.
}, 10, 3 );
```

### Popular Services

| Service | Integration Method |
|---------|-------------------|
| Mailchimp | Use wbam_email_captured hook + Mailchimp API |
| ConvertKit | Use wbam_email_captured hook + CK API |
| ActiveCampaign | Use wbam_email_captured hook + AC API |
| Webhook | Send POST request to your endpoint |

---

## Placement Examples

### As a Popup

1. Create email capture ad
2. Assign to **Popup** placement
3. Set trigger: Exit intent, scroll %, or time delay

### In Content

1. Create email capture ad
2. Assign to **After Paragraph** placement
3. Choose paragraph number (e.g., after paragraph 3)

### Sticky Bar

1. Create email capture ad
2. Assign to **Sticky Footer** or **Sticky Header** placement
3. Form appears as compact bar

### Sidebar Widget

1. Create email capture ad
2. Assign to **Widget** placement
3. Add WB Ad widget to sidebar

---

## Preventing Repeat Displays

### Cookie-Based Control

When a visitor:
- **Submits the form** - Ad hidden for cookie duration
- **Dismisses the popup** - Ad hidden for cookie duration

Configure duration in the ad settings (default: 7 days).

### Session-Based

For popups, you can also use frequency settings to limit displays per session.

---

## Styling the Form

### CSS Classes

| Class | Element |
|-------|---------|
| `.wbam-email-form` | Form wrapper |
| `.wbam-email-headline` | Headline text |
| `.wbam-email-description` | Description text |
| `.wbam-email-field` | Input fields |
| `.wbam-email-submit` | Submit button |
| `.wbam-email-success` | Success message |

### Custom Styling Example

```css
.wbam-email-form {
    background: #f5f5f5;
    padding: 30px;
    border-radius: 10px;
}

.wbam-email-submit {
    background: #007bff;
    color: white;
    border: none;
    padding: 12px 24px;
}
```

---

## Form Customization Hooks

### Modify Form Data

```php
add_filter( 'wbam_email_form_data', function( $data, $ad_id ) {
    $data['button_text'] = 'Get Free Access';
    return $data;
}, 10, 2 );
```

### Add Custom Fields

```php
add_filter( 'wbam_email_form_fields', function( $fields, $ad_id ) {
    $fields['phone'] = '<input type="tel" name="phone" placeholder="Phone">';
    return $fields;
}, 10, 2 );
```

### Custom Success Message

```php
add_filter( 'wbam_email_form_success_message', function( $message, $ad_id ) {
    return 'Welcome aboard! Check your email for a special gift.';
}, 10, 2 );
```

---

## Tracking Performance

### Metrics Available

| Metric | Description |
|--------|-------------|
| Impressions | Times form was displayed |
| Submissions | Successful signups |
| Conversion Rate | Submissions / Impressions |

### Viewing Stats

1. Go to **WB Ads > All Ads**
2. Find your email capture ad
3. View impressions and clicks (submissions)

---

## Best Practices

### 1. Compelling Headlines

| Weak | Strong |
|------|--------|
| "Subscribe" | "Get Weekly Growth Tips" |
| "Newsletter" | "Join 5,000+ Marketers" |
| "Sign Up" | "Get Your Free Guide" |

### 2. Offer Value

Give visitors a reason to subscribe:
- Free ebook or guide
- Exclusive discounts
- Weekly tips
- Early access

### 3. Keep Forms Short

- Email only converts best
- Add name only if you'll use it
- Don't ask for phone unless necessary

### 4. Set Appropriate Cookie Duration

| Visitor Type | Suggested Duration |
|--------------|-------------------|
| High-intent blog | 7 days |
| E-commerce | 14 days |
| One-time visitors | 30 days |

---

## Troubleshooting

### Form not displaying

1. Check ad is Published
2. Verify placement is active
3. Clear cache if using caching plugin
4. Check if visitor already has cookie set

### Submissions not saving

1. Verify no JavaScript errors in console
2. Check WordPress error log
3. Ensure form action URL is correct

### Popup showing repeatedly

1. Check cookie settings
2. Verify cookies are being set (browser dev tools)
3. Clear browser cookies for testing

---

## Available Actions

| Hook | When Fired | Parameters |
|------|------------|------------|
| `wbam_email_form_before` | Before form renders | $ad_id, $data |
| `wbam_email_form_after` | After form renders | $ad_id |
| `wbam_email_captured` | After successful submission | $email, $name, $ad_id |

---

## Next Steps

- [Managing Ads](01-managing-ads.md) - Ad management basics
- [Placements](../shortcode-reference/01-ad-shortcodes.md) - Where to display ads
- [Popup Settings](02-popup-settings.md) - Configure popup triggers
