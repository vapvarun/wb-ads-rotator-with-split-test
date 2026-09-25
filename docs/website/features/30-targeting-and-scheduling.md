# Targeting and Scheduling

Each ad carries four metaboxes that control when, where, and to whom it shows: **Display Rules**, **Visitor Conditions**, **Schedule**, and **Geo Targeting**. All of them are in the free plugin and applied on every request before an ad is chosen.

## Display Rules

Decide which pages an ad may appear on.

- **Show on all pages (with exclusions)** - the default. The ad shows everywhere except the items you exclude.
- **Show on specific pages only** - the ad shows only on the items you include.

Both modes can match by:

- Post types (posts, pages, custom types)
- Specific posts or pages
- Categories and tags
- Page types: front page, blog, singular, archive, search, 404, page, post

## Visitor Conditions

Target by who is viewing.

| Condition | Options |
|-----------|---------|
| Device | Desktop, tablet, mobile (choose one or more) |
| User status | All users, logged in only, logged out only |
| User roles | Any WordPress roles (when targeting logged-in users) |

Device detection uses Client Hints where available, falling back to user-agent parsing.

## Schedule

Limit an ad to a time window.

| Field | Effect |
|-------|--------|
| Start date | Ad becomes eligible on this date |
| End date | Ad stops after this date |
| Days of week | Restrict to chosen weekdays |
| Time of day | Restrict to an hour range (uses your WordPress timezone) |

Leave a field empty for no restriction.

## Geo Targeting

Show or hide an ad by visitor country.

1. Open the **Geo Targeting** metabox on the ad.
2. Enable geo targeting.
3. Add countries to **include** (show only there) or **exclude** (hide there), and choose whether to show the ad when the country is unknown.

Country is resolved from the visitor's BuddyPress profile where present, otherwise from IP geolocation. Configure the provider under **Settings -> Geo Targeting** (see [Settings](../usage/10-settings.md)). Geo targeting in the free plugin is country-level.

## Frequency control

The **Ad Status** metabox exposes two caps:

- **Max views per visitor per day** - the maximum times a single visitor sees the ad in a day (counted in a browser cookie that resets at midnight site time).
- **Impression cap** - a total lifetime impression limit across all visitors; once reached, the ad stops serving.

## Next steps

- [Rotation and Split Testing](20-rotation-and-split-testing.md)
- [Settings](../usage/10-settings.md) - the geo provider and global display options.
