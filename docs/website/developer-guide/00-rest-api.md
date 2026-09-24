# REST API

WB Ad Manager exposes a REST API under the namespace `wbam/v1`. Admin routes require the `manage_options` capability (they return a 403 `WP_Error` otherwise). Public routes are open; tracking routes are IP rate-limited.

Base URL: `/wp-json/wbam/v1`

## Ads

| Method | Path | Access | Purpose |
|--------|------|--------|---------|
| GET | `/ads` | Public | List published, enabled ads (`per_page` 1-100 default 20, `page`) |
| POST | `/ads` | `manage_options` | Create an ad (`title` required) |
| GET | `/ads/serve` | Public | Rendered ad HTML for a placement (`placement` required, `post_id`, `page_url`) |
| GET | `/ads/placements` | Public | Selectable placement types (respects the site gate) |
| GET | `/ads/types` | Public | Available ad types |
| POST | `/ads/track` | Public (60/min per IP) | Record `impression`/`click` (`ad_id`, `event_type` required, `placement`) |
| GET | `/ads/{id}` | `manage_options` | Get one ad with full meta |
| PUT/PATCH | `/ads/{id}` | `manage_options` | Update an ad |
| DELETE | `/ads/{id}` | `manage_options` | Delete an ad |
| GET | `/ads/{id}/stats` | `manage_options` | Per-ad impressions/clicks/CTR (optional `start_date`/`end_date`) |
| POST | `/ads/{id}/duplicate` | `manage_options` | Duplicate an ad (saved as draft) |

## Analytics

| Method | Path | Access | Purpose |
|--------|------|--------|---------|
| GET | `/analytics/overview` | `manage_options` | Totals plus top ads (`limit` 1-50 default 10, optional dates) |
| GET | `/analytics/ads/{id}` | `manage_options` | Per-ad stats with by-placement breakdown |
| GET | `/analytics/daily` | `manage_options` | Daily time-series (`start_date` + `end_date` required, optional `ad_id`) |
| POST | `/analytics/track` | Public (60/min per IP) | Alternate event-tracking path |

## Links

| Method | Path | Access | Purpose |
|--------|------|--------|---------|
| GET | `/links` | `manage_options` | List links (filters: `status`, `link_type`, `category_id`, `search`, `per_page`, `page`) |
| POST | `/links` | `manage_options` | Create a link (`name` + `destination_url` required) |
| GET | `/links/categories` | `manage_options` | List link categories |
| POST | `/links/categories` | `manage_options` | Create a category (`name` required) |
| GET | `/links/{id}` | `manage_options` | Get one link |
| PUT/PATCH | `/links/{id}` | `manage_options` | Update a link |
| DELETE | `/links/{id}` | `manage_options` | Delete a link |
| GET | `/links/{id}/stats` | `manage_options` | Total and unique clicks (optional dates) |
| POST | `/links/{id}/track` | Public (30/min per IP) | Record a link click (optional `referrer`) |
| GET | `/partnerships` | `manage_options` | List partnership inquiries (`status`, `per_page`, `page`) |
| PUT/PATCH | `/partnerships/{id}` | `manage_options` | Update status (`status`: pending/accepted/rejected/spam; optional `admin_notes`) - fires the matching emails |

## Settings

| Method | Path | Access | Purpose |
|--------|------|--------|---------|
| GET | `/settings` | `manage_options` | Get all settings |
| PUT/PATCH | `/settings` | `manage_options` | Merge and save settings (`settings` object required) |
| GET | `/settings/display` | `manage_options` | Get the display subset (`ad_label`, `ad_label_position`) |
| PUT/PATCH | `/settings/display` | `manage_options` | Update the display subset (only those two keys) |

## Email Captures

| Method | Path | Access | Purpose |
|--------|------|--------|---------|
| GET | `/email-captures` | `manage_options` | Paginated captured emails, newest first (`page`, `per_page` default 25 max 200; `X-WP-Total` / `X-WP-TotalPages` headers) |

## Next steps

- [Hooks and Filters](10-hooks-and-filters.md)
- [Abilities API](30-abilities-api.md)
