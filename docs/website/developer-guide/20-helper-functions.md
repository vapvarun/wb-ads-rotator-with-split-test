# Helper Functions

WB Ad Manager defines a few global PHP helpers you can call from a theme template or another plugin.

## `wbam_display_ad( $ad_id, $options = array() )`

Returns the rendered HTML for one ad by ID.

```php
echo wbam_display_ad( 123 );
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `$ad_id` | int | The ad post ID |
| `$options` | array | Optional display options passed to the placement engine |

## `wbam_get_ads( $placement_id )`

Returns the array of ads eligible for a given placement.

```php
$ads = wbam_get_ads( 'header' );
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `$placement_id` | string | A placement ID (see [Placements](../features/10-placements.md)) |

## `wbam()`

Returns the main plugin instance, from which you can reach the placement engine and other subsystems.

```php
$engine = wbam()->placements();
```

## Rendering an ad by shortcode in PHP

If you prefer shortcodes in templates:

```php
echo do_shortcode( '[wbam_ad id="123"]' );
```

## Next steps

- [Hooks and Filters](10-hooks-and-filters.md)
- [Ad Shortcodes](../shortcodes/00-ad-shortcodes.md)
