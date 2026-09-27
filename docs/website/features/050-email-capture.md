# Email Capture

The Email Capture ad type renders an inline newsletter/subscribe form anywhere an ad can appear. Submissions are stored on your site and can be viewed, exported, and deleted from the admin.

## Create an Email Capture ad

1. Go to **Ad Manager -> Add New Ad** (or **Create an Email Capture ad** on the empty Email Captures screen, which opens the editor on this type).
2. In **Ad Settings** choose ad type **Email Capture**.
3. Fill in the form fields:

| Field | What it does | Default |
|-------|--------------|---------|
| Headline | Form heading | - |
| Description | Sub-text under the heading | - |
| Button text | Submit button label | `Subscribe` |
| Success message | Shown after a successful submit | - |
| Show name field | Adds a name input | Off |
| Redirect URL | Optional page to send subscribers to after submit | - |
| Privacy text | Small print below the form | - |
| Background / text / button colour | Form colours | `#ffffff` / `#1d2327` / `#2271b1` |

4. Check placements, then publish. An Email Capture ad sizes itself to its content, so there is no Sizing box, and it shows without the "Advertisement" label, because it is your own signup form (the `wbam_show_ad_label` filter can add the label back).

## Review captured leads

Go to **Ad Manager -> Email Captures** (right under Add New Ad) to see submissions (newest first), export them to CSV, and delete single rows for GDPR erasure requests. The CSV's **Visitor hash** column is a one-way hash of the visitor's IP, never the address itself.

## Forward leads to your email tool

Each successful submission fires the `wbam_email_captured` action with the email, name, and ad ID. Hook it to forward leads to Mailchimp, ConvertKit, a webhook, or anywhere else:

```php
add_action( 'wbam_email_captured', function ( $email, $name, $ad_id ) {
    // Forward $email / $name to your CRM or ESP.
}, 10, 3 );
```

See [Hooks and Filters](../developer-guide/010-hooks-and-filters.md) for the full form hook set, including `wbam_email_capture_cookie_days` (how many days the form stays hidden after a visitor closes it; 7 by default).

## Privacy

The form is nonce-protected and sanitized server-side. Visitor IP addresses are only ever stored as a one-way hash (see [Settings](../usage/010-settings.md#privacy--data)).

## Next steps

- [Ad Types](000-ad-types.md)
- [Email Captures REST endpoint](../developer-guide/000-rest-api.md)
