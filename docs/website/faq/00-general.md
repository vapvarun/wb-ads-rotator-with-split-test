# Frequently Asked Questions

## How do I create an ad?

Go to **WB Ad Manager -> Ads -> Add New**. Enter a title, choose an ad type, add your content, check placements, and publish. See [Quick Setup](../getting-started/10-quick-setup.md).

## How do I display an ad with a shortcode?

Use `[wbam_ad id="123"]`, where `123` is the ad ID. For several ads: `[wbam_ads ids="1,2,3"]`. See [Ad Shortcodes](../shortcodes/00-ad-shortcodes.md).

## How many ads can I create?

Unlimited. There is no ad cap in the free plugin.

## How does rotation work?

When several ads share a placement, the plugin picks one at weighted random based on each ad's Priority (1-10). Higher priority means a larger share of impressions. See [Rotation and Split Testing](../features/20-rotation-and-split-testing.md).

## Can I A/B test ads?

Yes. Run two or more ads on the same placement at equal priority, then read the **Ad Performance Comparison** metabox on any of them to see impressions, clicks, and CTR side by side. WB Ad Manager Pro adds true A/B tests with a traffic split between an original ad and its variants, plus a statistical significance readout to help you pick the winner.

## Does this support Google AdSense?

Yes. Set your Publisher ID under **Settings -> Google AdSense**, then create AdSense ad types. The AdSense script is managed automatically and loads once per page. See [Google AdSense](../integrations/30-google-adsense.md).

## Does this work with BuddyPress and bbPress?

Yes. When BuddyPress or bbPress is active, extra placements register automatically. See [BuddyPress](../integrations/00-buddypress.md) and [bbPress](../integrations/10-bbpress.md).

## Can I schedule ads?

Yes. Set start/end dates, specific days of the week, and a time-of-day range per ad in the **Schedule** metabox. See [Targeting and Scheduling](../features/30-targeting-and-scheduling.md).

## Which geo-targeting providers are supported?

ip-api.com (free, no key), ipinfo.io (free tier, optional key), and ipapi.co (free, no key). If the primary provider fails, the plugin tries the next. See [Settings](../usage/10-settings.md).

## Will my data be removed if I uninstall?

No, unless you opt in. Enable **Delete data on uninstall** under **Settings -> Advanced** first. By default, uninstalling keeps all your ads, links, and analytics.

## What is in the Pro version?

WB Ad Manager Pro adds an advertiser portal, a wallet and payments layer, campaigns with budgets, a classifieds marketplace, advanced analytics, and A/B testing with traffic splitting. The free plugin's features keep working alongside it.
