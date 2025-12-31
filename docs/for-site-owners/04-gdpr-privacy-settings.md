# GDPR & Privacy Settings

Configure WB Ad Manager for GDPR compliance and user privacy. This guide covers consent integration, IP anonymization, and data handling.

## What You'll Learn

- Enabling consent requirements for ads
- Integration with cookie consent plugins
- IP anonymization for analytics
- Data retention and deletion
- Privacy policy recommendations

---

## Privacy Settings Overview

Go to **WB Ads > Settings > Privacy** to configure:

| Setting | Description | Default |
|---------|-------------|---------|
| **Require Consent for AdSense** | Wait for user consent before loading AdSense | Off |
| **Anonymize IP Addresses** | Remove last octet of IP for geo-targeting | Off |
| **Delete Data on Uninstall** | Remove all plugin data when uninstalled | Off |

---

## Consent Management

### How It Works

When consent is required:

1. Plugin checks if user has given consent
2. If no consent, AdSense and tracking scripts are blocked
3. Once consent given, ads load normally
4. Consent state is cached for performance

### Enabling Consent Requirement

1. Go to **WB Ads > Settings > Privacy**
2. Enable **Require Consent for AdSense**
3. Click **Save Settings**

> **Important:** You must have a cookie consent plugin installed for this to work.

---

## Supported Consent Plugins

WB Ad Manager automatically integrates with these popular consent plugins:

| Plugin | Detection Method | Notes |
|--------|-----------------|-------|
| **Cookie Notice** by dFactory | `cn_cookies_accepted()` | Full support |
| **CookieYes** (Cookie Law Info) | Cookie: `cookieyes-consent` | Checks marketing/analytics |
| **Complianz GDPR** | `cmplz_has_consent()` | Full support |
| **GDPR Cookie Consent** by WebToffee | Cookie: `viewed_cookie_policy` | Basic support |
| **Borlabs Cookie** | `BorlabsCookieHelper()` | Checks statistics/marketing |
| **Real Cookie Banner** | Native integration | Full support |

### What If My Plugin Isn't Listed?

Use the filter hook to add custom consent checking:

```php
add_filter( 'wbam_has_consent', function( $has_consent, $consent_type ) {
    // Your custom consent check logic
    if ( isset( $_COOKIE['my_consent_cookie'] ) ) {
        return $_COOKIE['my_consent_cookie'] === 'accepted';
    }
    return false;
}, 10, 2 );
```

---

## IP Anonymization

### What It Does

When enabled, the last segment of visitor IP addresses is removed:
- `192.168.1.100` becomes `192.168.1.0`
- `2001:db8::1` becomes `2001:db8::`

### Why Use It

- **GDPR compliance** - IP addresses are personal data
- **Privacy protection** - Less precise tracking
- **Legal requirement** - Required in some jurisdictions

### Enabling IP Anonymization

1. Go to **WB Ads > Settings > Privacy**
2. Enable **Anonymize IP Addresses**
3. Click **Save Settings**

> **Note:** This may slightly reduce geo-targeting accuracy.

---

## Data Handling

### What Data Is Collected

| Data Type | Purpose | Storage |
|-----------|---------|---------|
| Impressions | Analytics | WordPress database |
| Clicks | Analytics | WordPress database |
| IP Addresses | Geo-targeting | Not stored (transient only) |
| Email Signups | Email capture ads | WordPress database |

### Data Retention

Configure how long to keep analytics data:

1. Go to **WB Ads > Settings > Privacy**
2. Set **Data Retention Period** (days)
3. Old data is automatically purged

### Delete on Uninstall

When enabled, uninstalling the plugin removes:
- All ad posts and meta
- Analytics data
- Settings
- Custom database tables

> **Warning:** This cannot be undone. Only enable if you're permanently removing the plugin.

---

## AdSense and GDPR

### Non-Personalized Ads

If a visitor hasn't given consent, you can show non-personalized ads:

```php
add_filter( 'wbam_adsense_params', function( $params ) {
    if ( ! WBAM\Core\Privacy_Helper::has_consent( 'marketing' ) ) {
        $params['data-npa'] = '1'; // Non-personalized ads
    }
    return $params;
});
```

### Complete Blocking

By default, when consent is required but not given:
- AdSense scripts don't load
- No ads are displayed
- No tracking occurs

---

## Privacy Policy Recommendations

Add these sections to your privacy policy:

### Advertising Section

```
We display advertisements on our website. These ads may use cookies
to show you relevant content. We use [WB Ad Manager] to manage our
advertising. When required, we ask for your consent before loading
personalized advertisements.
```

### Data Collection Section

```
Our advertising system collects:
- Page views (impressions) for analytics
- Click data for performance measurement
- Geographic location (country-level) for targeting

We do not store your IP address permanently. All analytics data
is aggregated and anonymized.
```

### Cookie Information

List the cookies used:
| Cookie | Purpose | Duration |
|--------|---------|----------|
| `wbam_session` | Frequency limiting | Session |
| `wbam_dismissed_X` | Remember dismissed popups | Variable |

---

## Testing Consent Integration

### How to Test

1. Clear all cookies in your browser
2. Visit your site
3. Check that ads don't load (if consent required)
4. Accept cookies in consent popup
5. Verify ads now load

### Debug Mode

Add this to check consent status:

```php
add_action( 'wp_footer', function() {
    if ( current_user_can( 'manage_options' ) ) {
        $consent = WBAM\Core\Privacy_Helper::has_consent( 'marketing' );
        echo '<!-- WBAM Consent: ' . ( $consent ? 'Yes' : 'No' ) . ' -->';
    }
});
```

---

## Common Questions

### Do I need consent for all ads?

- **AdSense/Ad Networks:** Yes, they use cookies
- **Image Ads (self-hosted):** Generally no, if no tracking
- **Affiliate Links:** Depends on tracking method

### What about non-EU visitors?

You can conditionally require consent based on location:

```php
add_filter( 'wbam_require_consent', function( $require ) {
    // Only require consent for EU visitors
    $eu_countries = array( 'DE', 'FR', 'IT', 'ES', ... );
    $country = wbam_get_visitor_country();
    return in_array( $country, $eu_countries );
});
```

### Does this make me fully GDPR compliant?

This plugin helps with advertising compliance, but full GDPR compliance requires:
- Proper privacy policy
- Cookie consent mechanism
- Data processing agreements
- Right to access/deletion procedures

Consult a legal professional for complete compliance.

---

## Troubleshooting

### Ads not loading even with consent

1. Check consent plugin is detecting consent correctly
2. Verify cookie is being set
3. Check JavaScript console for errors
4. Ensure consent plugin is compatible

### Consent not being detected

1. Verify your consent plugin is on supported list
2. Check cookie names match expected format
3. Use the `wbam_has_consent` filter for custom integration

### AdSense showing personalized ads despite no consent

1. Ensure "Require Consent for AdSense" is enabled
2. Clear all caches
3. Check that consent check runs before ad loading

---

## Developer Hooks

### Check Consent

```php
if ( WBAM\Core\Privacy_Helper::has_consent( 'marketing' ) ) {
    // Load tracking scripts
}
```

### Override Consent Check

```php
add_filter( 'wbam_has_consent', function( $consent, $type ) {
    // Your logic here
    return true; // or false
}, 10, 2 );
```

### Before AdSense Loads

```php
add_action( 'wbam_before_adsense', function( $ad_id ) {
    // Custom pre-loading logic
});
```

---

## Next Steps

- [Managing Ads](01-managing-ads.md) - Ad management basics
- [AdSense Integration](../shortcode-reference/01-ad-shortcodes.md) - AdSense setup
- [Settings Reference](02-settings-reference.md) - All plugin settings
