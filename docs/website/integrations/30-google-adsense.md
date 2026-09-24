# Google AdSense

WB Ad Manager has native AdSense support. It manages the AdSense script for you (loaded once per page) and can gate loading behind visitor consent.

## Set your Publisher ID once

1. Go to **WB Ad Manager -> Settings -> Google AdSense**.
2. Paste your **Publisher ID** (`ca-pub-...`) from your AdSense account.
3. Save. This becomes the default for every AdSense ad.

## Create an AdSense ad

1. **WB Ad Manager -> Ads -> Add New**.
2. Ad type: **Google AdSense**.
3. Enter your **Ad slot ID** (the Publisher ID defaults to your site setting).
4. Choose an ad format (`auto`, horizontal, vertical, rectangle) and responsive vs fixed sizing.
5. Check placements and publish.

You can also paste raw AdSense code into an **HTML/JS Code** ad if you prefer to manage the unit markup yourself.

## Auto Ads

Turn on **Auto Ads** under **Settings -> Google AdSense** to let Google place ads across your site automatically. This is separate from the per-ad AdSense type.

## Consent and privacy

Under **Settings -> Privacy & GDPR**, enable **Require consent for AdSense** to load AdSense scripts only after the visitor consents (works with common consent plugins). See [Settings](../usage/10-settings.md).

## Next steps

- [Ad Types](../features/00-ad-types.md)
- [Settings](../usage/10-settings.md)
