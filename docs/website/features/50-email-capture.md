# Email Capture

The Email Capture ad type renders an inline newsletter/subscribe form anywhere an ad can appear. Submissions are stored on your site and can be viewed, exported, and deleted from the admin.

## Create an Email Capture ad

1. Go to **WB Ad Manager -> Ads -> Add New**.
2. In **Ad Settings** choose ad type **Email Capture**.
3. Fill in the form fields:

| Field | What it does | Default |
|-------|--------------|---------|
| Headline | Form heading | - |
| Description | Sub-text under the heading | - |
| Button text | Submit button label | `Subscribe` |
| Success message | Shown after a successful submit | - |
| Show name field | Adds a name input | Off |
| Cookie days | Days to hide the form from a visitor after they submit | 7 |
| Redirect URL | Optional page to send subscribers to after submit | - |
| Privacy text | Small print below the form | - |
| Background / text / button colour | Form colours | `#ffffff` / `#1d2327` / `#2271b1` |

4. Check placements, then publish.

## Review captured leads

Go to **WB Ad Manager -> Email Captures** to see submissions (newest first), export them to CSV, and delete individual rows for GDPR erasure requests.

## Forward leads to your email tool

Each successful submission fires the `wbam_email_captured` action with the email, name, and ad ID. Hook it to forward leads to Mailchimp, ConvertKit, a webhook, or anywhere else:

```php
add_action( 'wbam_email_captured', function ( $email, $name, $ad_id ) {
    // Forward $email / $name to your CRM or ESP.
}, 10, 3 );
```

See [Hooks and Filters](../developer-guide/10-hooks-and-filters.md) for the full form-customization hook set.

## Privacy

The form is nonce-protected and sanitized server-side. IP anonymization for tracking is controlled under **Settings -> Privacy & GDPR** (see [Settings](../usage/10-settings.md)).

## Next steps

- [Ad Types](00-ad-types.md)
- [Email Captures REST endpoint](../developer-guide/00-rest-api.md)
