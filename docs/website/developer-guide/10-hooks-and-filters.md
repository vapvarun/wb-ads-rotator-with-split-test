# Hooks and Filters

WB Ad Manager fires actions and filters throughout its lifecycle so you can extend it without editing the plugin. All hooks use the `wbam_` prefix. Below are the extension points most useful to site developers; the plugin fires many more granular form hooks documented in the relevant class docblocks.

## Actions

| Hook | Arguments | Fires when |
|------|-----------|------------|
| `wbam_init` | - | The plugin finished bootstrapping |
| `wbam_placements_init` | `$engine` | The placement engine is ready |
| `wbam_register_placements` | `$engine` | Register custom placement objects |
| `wbam_register_ad_types` | `$engine` | Register custom ad types |
| `wbam_save_ad_meta` | `$post_id` | An ad's meta was saved |
| `wbam_ad_impression` | `$ad_id, $placement` | An impression was recorded |
| `wbam_ad_clicked` | `$ad_id, $placement` | A click was recorded |
| `wbam_ad_cap_reached` | `$ad_id, $delivered` | An ad hit its frequency cap |
| `wbam_email_captured` | `$email, $name, $ad_id` | An Email Capture form was submitted |
| `wbam_link_clicked` | `$id, $link` | A cloaked link was clicked |
| `wbam_before_link_redirect` | `$link, $destination` | Just before a cloaked-link redirect |
| `wbam_partnership_created` / `_accepted` / `_rejected` | `$partnership` | Partnership lifecycle events |
| `wbam_setup_wizard_complete` | - | The setup wizard finished |
| `wbam_demo_data_cleared` | `$counts` | Demo data was purged |

## Filters

| Hook | Filters |
|------|---------|
| `wbam_ads_for_placement` | The final set of ads chosen for a placement |
| `wbam_ad_output` | The rendered ad HTML |
| `wbam_placement_render_mode` | Rotate (pick one) vs stack (render all) for a placement |
| `wbam_enforce_page_cap` | Whether the once-per-page cap applies to an ad |
| `wbam_should_display_ad` | Master gate on whether an ad shows |
| `wbam_ad_display_rules` | The targeting rule set for an ad |
| `wbam_detected_device` | The detected device type |
| `wbam_module_defaults` / `wbam_is_module_enabled` | Optional-module list and on/off state |
| `wbam_enabled_placements` | The site placement gate |
| `wbam_ad_formats` / `wbam_placement_format_map` | Registered ad sizes and placement-to-format mapping |
| `wbam_ad_tag_taxonomy_args` | Arguments for the `wbam_ad_tag` taxonomy (relabel, widen caps, enable hierarchy) |
| `wbam_has_consent` | Consent state used for the AdSense consent gate |
| `wbam_link_redirect_url` / `wbam_link_redirect_type` | Cloaked-link destination and HTTP redirect code |
| `wbam_link_shortcode_defaults` / `wbam_links_shortcode_defaults` | Defaults for the link shortcodes |
| `wbam_code_ad_use_sandbox` / `wbam_code_ad_sandbox_attrs` | Code-ad iframe sandboxing |
| `wbam_partnership_form_duplicate_hours` | The duplicate-submission window (default 24) |

## Example: register a custom placement

```php
add_action( 'wbam_register_placements', function ( $engine ) {
    // $engine->register_placement( new My_Custom_Placement() );
} );
```

## Example: forward captured emails

```php
add_action( 'wbam_email_captured', function ( $email, $name, $ad_id ) {
    // Send $email to your ESP.
}, 10, 3 );
```

## Next steps

- [REST API](00-rest-api.md)
- [Helper Functions](20-helper-functions.md)
