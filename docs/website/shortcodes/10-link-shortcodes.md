# Link Shortcodes

Display managed (cloaked, tracked) links from the Links module. See [Link Management](../features/40-link-management.md) to create links first.

## `[wbam_link]` - a single tracked link

Renders an `<a>` tag for one managed link, counting every click.

```
[wbam_link id="123"]
[wbam_link id="123"]Custom anchor text[/wbam_link]
```

| Attribute | Default | Description |
|-----------|---------|-------------|
| `id` | `0` | Link ID (use `id` or `slug`) |
| `slug` | (empty) | Link slug, an alternative to `id` |
| `text` | (empty) | Anchor text; falls back to shortcode content, then the link name |
| `class` | (empty) | Extra CSS class |
| `nofollow` | (empty) | `true`/`false`; empty uses the link's own setting |
| `sponsored` | (empty) | `true`/`false`; empty uses the link's own setting |
| `new_tab` | (empty) | `true`/`false`; empty uses the link's own setting |

Renders nothing if the link is missing or inactive.

## `[wbam_links]` - a list of links

Renders multiple active links as a list, inline row, or grid.

```
[wbam_links category="5" format="grid" limit="6"]
```

| Attribute | Default | Description |
|-----------|---------|-------------|
| `category` | `0` | Link category ID to filter by |
| `type` | (empty) | Filter by link type (e.g. affiliate, sponsored, internal, external) |
| `limit` | `10` | Maximum links to show |
| `orderby` | `name` | Order field |
| `order` | `ASC` | `ASC` or `DESC` |
| `class` | (empty) | Extra CSS class on the list wrapper |
| `item_class` | (empty) | Extra CSS class on each item |
| `format` | `list` | `list`, `inline`, or `grid` |

Only active links are shown.

## `[wbam_link_url]` - just the URL

Outputs the cloaked URL as plain text, for use inside your own markup.

```
<a href="[wbam_link_url id="123"]">Buy the book</a>
```

| Attribute | Default | Description |
|-----------|---------|-------------|
| `id` | `0` | Link ID (use `id` or `slug`) |
| `slug` | (empty) | Link slug, an alternative to `id` |

## Next steps

- [Ad Shortcodes](00-ad-shortcodes.md)
- [Partnership Inquiry Form](20-partnership-inquiry-form.md)
