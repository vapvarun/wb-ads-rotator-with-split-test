# Link Management

The Links module turns long, messy outbound URLs into clean branded links on your own domain, tracks every click, and lets you manage SEO attributes in one place. Find it under **WB Ad Manager -> Links** (enable the Links module under **Settings -> Features** if the menu is hidden).

## Why cloaked links

Instead of pasting a raw affiliate URL like `amazon.com/gp/product/B07XYZ?ref=affiliate_123`, you publish a short branded link like `yoursite.com/go/book`. Benefits:

- **Click tracking** - counts per link, including unique clicks.
- **One place to edit** - change the destination once, every use updates.
- **SEO control** - add `rel="nofollow"` or `rel="sponsored"`.
- **Organization** - group links into categories.
- **Expiry** - set links to deactivate after a date.

## Create a link

1. Go to **WB Ad Manager -> Links -> Add New**.
2. Enter a **Name** (used as default anchor text) and the **Destination URL**.
3. Optionally set a slug, category, SEO attributes (nofollow/sponsored), and open-in-new-tab.
4. Save. The cloaked URL uses your cloak prefix, for example `yoursite.com/go/your-slug`.

## Link categories

Group links under **WB Ad Manager -> Links -> Categories** (for example: sponsors, partners, resources, affiliates). Categories can be used to render link lists with `[wbam_links]`.

## Cloak prefix and inactive links

Under **Settings -> Link Cloaking**:

| Setting | What it does | Default |
|---------|--------------|---------|
| Cloak prefix | The URL segment for cloaked links, e.g. `go`. Rewrite rules refresh when you change it. | `go` |
| Inactive link action | What happens when an inactive or expired link is opened: show 404, redirect home, or redirect to a custom URL. | Show 404 |
| Inactive link URL | The custom URL for the "redirect to custom URL" action. | (empty) |

## Display links

- Single link: `[wbam_link id="123"]Anchor text[/wbam_link]`
- A list of links: `[wbam_links category="5"]`
- Just the URL: `[wbam_link_url id="123"]`

See [Link Shortcodes](../shortcodes/10-link-shortcodes.md) for every attribute.

## Link partnerships

The module also accepts inbound partnership requests (paid link, link exchange, sponsored post) through an on-site form, with an accept/reject workflow and automatic emails. See [Partnership Inquiry Form](../shortcodes/20-partnership-inquiry-form.md).

## Next steps

- [Link Shortcodes](../shortcodes/10-link-shortcodes.md)
- [Settings](../usage/10-settings.md)
