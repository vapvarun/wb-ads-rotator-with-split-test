# Common Issues

Work through the quick fixes first, then the specific sections.

## Quick fixes

1. Clear caches - browser, WordPress page cache, and CDN.
2. Flush permalinks - **Settings -> Permalinks -> Save Changes** (needed after changing the cloak prefix).
3. Test in an incognito window to rule out session/impression limits.
4. Temporarily deactivate other plugins to check for conflicts.

## Plugin will not activate

- Confirm PHP 7.4+ (**Tools -> Site Health -> Info -> Server**).
- Confirm WordPress 5.8+.
- Raise the PHP memory limit to 128MB+.
- Check the error log for the specific fatal message.

## Menu not appearing

- Clear the browser cache and refresh.
- Log out and back in.
- Confirm the user has administrator (`manage_options`) capabilities.

## Ads not showing

Check each item:

- The ad is **Published**, not Draft.
- The **Enabled** toggle in the Ad Status metabox is on (a disabled ad never serves).
- At least one placement is checked, or you are using a shortcode with the correct ID.
- The start date has passed and the end date has not.
- The **Max views per visitor per day** or **Impression cap** for this ad has not been reached - test in incognito.
- **Disable for admins** or **Disable for logged-in users** is not hiding the ad from you (**Settings -> General**).
- The placement is open under **Settings -> Placements**.

Debug: try `[wbam_ad id="123"]` directly on a page, and view the page source to see whether the ad container renders.

## Shortcode shows as plain text

- Confirm the plugin is active.
- Check the spelling: `wbam_ad`, not `wbam-ad`.
- Remove stray spaces inside the shortcode.
- Switch to a default theme temporarily to rule out a theme issue.

## Same ad always shows

- Only one ad is assigned to that placement - add more ads with the same placement checked.
- Aggressive page caching is serving a cached pick - clear caches and test in incognito.

## Clicks or impressions not tracking

- Confirm the destination URL starts with `http` or `https`.
- Test in incognito - ad blockers can interfere with the tracking request.
- Check the browser console for JavaScript errors.
- Confirm the tracking request is not blocked by a CDN or security plugin (the tracking routes are rate-limited per IP).

## Cloaked links 404 or redirect unexpectedly

- Flush permalinks after changing the **Cloak prefix**.
- Check **Settings -> Link Cloaking -> Inactive link action** - an expired or inactive link follows that setting (404, home, or custom URL).

## Partnership form submits but nothing happens

- Check the browser console for JavaScript errors.
- The same email cannot submit against the same target page more than once per 24 hours - later attempts in that window are ignored by design.
- Confirm WordPress can send email (the accept/reject flow relies on it).

## Still stuck?

Gather the plugin version, WordPress version, PHP version, and the exact steps to reproduce, then contact support.
