# Dates and times

WB Ad Manager follows the WordPress model: **store UTC, show and read in the site's time zone** (Settings > General). The site owner's time zone setting decides what everyone sees. This is an owner decision from 2026-09-27, and it applies to Free and Pro.

## The two kinds of value

| Kind | Examples | Stored as | Shown and picked as |
|---|---|---|---|
| A moment | created, expires, starts, billed, clicked, reviewed | UTC `Y-m-d H:i:s` | The site's zone |
| A calendar day with no time | a daily report bucket, an ad's schedule start and end day, a campaign's spent-today date | Site-calendar `Y-m-d` | Unchanged |

## Writing

- Write a moment with `current_time( 'mysql', true )` (or `gmdate( 'Y-m-d H:i:s', $unix )`), and always set it explicitly on insert. Never rely on a column's `DEFAULT CURRENT_TIMESTAMP` or `ON UPDATE CURRENT_TIMESTAMP`: those use the MySQL server's zone.
- A person picks times in the site's zone. Convert before saving with `wbam_site_to_utc( $value, $end_of_day )`, where a bare `Y-m-d` becomes that site day's start, or its last second.
- Never store site-local time. If the owner later changed the site's time zone, every stored value would silently shift.

## Comparing

- Compare a moment with a UTC value from PHP (`current_time( 'mysql', true )`, `time()`). Never use SQL `NOW()` or `CURDATE()`.
- To query moments by site days, use `wbam_site_day_utc_bounds( $start_day, $end_day )` (index-friendly `col BETWEEN %s AND %s`). To group by site day, use `wbam_sql_site_date( 'created_at' )`.
- For a reporting period, use `wbam_period_start( 'today'|'week'|'month'|'year'|$days )`. It returns the UTC start for moment columns and the site day for daily buckets.
- `strtotime( $utc )` is correct in WordPress, because PHP's default zone is UTC there.

## Showing

- `wbam_format_datetime( $utc, $format )` shows a stored moment in the site's zone.
- `wbam_format_day( $day, $format )` shows a site-calendar day without shifting it.
- `\WBAM\Core\Formatter::date()` and `::datetime()` use these helpers.
- Never pass a stored value to `date_i18n()`, `gmdate()` or `mysql2date()` for display: all three print UTC.
- `human_time_diff( strtotime( $utc ), time() )` is correct.
- A post's `post_date` is site time: use `post_date_gmt` for anything compared, sorted or returned.
- "Today" for a daily report or chart is the site's today: `wp_date( 'Y-m-d' )`, never `gmdate( 'Y-m-d' )`. `wbam_site_day_range()` gives the site days from a start day to today.

## The REST API and abilities

Every `*_at` field, and a post's `created` / `modified`, is the stored UTC value (`Y-m-d H:i:s`), the same as WordPress's own `date_gmt`. A field named `*_formatted` or `*_label` is already shown in the site's zone.

## Enforced

`bash bin/check-date-clocks.sh <dirs>` fails the build on:
- SQL `NOW()`, `CURDATE()` or `CURRENT_TIMESTAMP` outside a schema;
- `current_time( 'mysql' )` without `true`;
- `current_time( 'timestamp' )`;
- `date_i18n()`;
- `gmdate()` shown to a person: escaped for HTML, or a human format (`M j`, `F`, `g:i A`). HTTP and cookie dates (`GMT`) and machine ISO values (`gmdate( 'c' )`) are allowed.

It runs in `bin/architecture-checks.sh` and in `npm run release`.

## The one exception

The bundled Credits SDK owns its ledger tables, and they use the server clock. WB Ad Manager converts those values where it reads them, measured against `UTC_TIMESTAMP()`, which the guard allows.

## Upgrading from 3.1.1

Pro 4.3.18 and Free 1.9.3 convert existing rows once. Each column is shifted by the clock that wrote it: the site's offset (daylight saving is handled row by row) or the MySQL server's offset. Progress is kept in `wbam_pro_utc_migration` / `wbam_utc_migration`, so a column is never converted twice, and a large table resumes in the background.
