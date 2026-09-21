# Ad Shortcodes

Use these shortcodes to display ads by ID anywhere shortcodes run - posts, pages, and text/HTML widgets. For automatic placement (header, footer, content), use the Placements checkboxes on the ad instead - no shortcode needed.

## `[wbam_ad]` - one ad by ID

```
[wbam_ad id="123"]
```

| Attribute | Default | Description |
|-----------|---------|-------------|
| `id` | `0` | Ad post ID (required - renders nothing if missing or 0) |
| `class` | (empty) | Extra CSS class on the ad wrapper |

Example with a class:

```
[wbam_ad id="123" class="my-custom-ad"]
```

## `[wbam_ads]` - several ads by ID

```
[wbam_ads ids="1,2,3"]
```

| Attribute | Default | Description |
|-----------|---------|-------------|
| `ids` | (empty) | Comma-separated ad post IDs (required) |
| `class` | (empty) | Extra CSS class on each ad wrapper |

Example:

```
[wbam_ads ids="10,20,30" class="partner-logo"]
```

## Using shortcodes in templates

In a PHP theme template:

```php
echo do_shortcode( '[wbam_ad id="123"]' );
```

Or call the helper directly - see [Helper Functions](../developer-guide/20-helper-functions.md).

## Next steps

- [Link Shortcodes](10-link-shortcodes.md)
- [Partnership Inquiry Form](20-partnership-inquiry-form.md)
