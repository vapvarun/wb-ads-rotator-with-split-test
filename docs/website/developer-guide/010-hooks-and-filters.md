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
| `wbam_placement_candidates` | `$ad_ids`, `$placement_id` | A placement's candidate ads, before each is checked; batch-load data for your `wbam_should_display_ad` callback here |

## Filters

| Hook | Filters |
|------|---------|
| `wbam_ads_for_placement` | The final set of ads chosen for a placement |
| `wbam_ads_eligible_for_placement` | Ads eligible for a placement before the winner is drawn |
| `wbam_ad_delivery_tier` | An ad's tier: paid (20) beats house (10) beats sample (0) for a slot |
| `wbam_rotation_pick` | The winner among same-tier ads (Pro's rotation model hooks here for paid ads) |
| `wbam_ad_link_rel` | rel on an ad's link: `sponsored noopener` for paid ads, `noopener` for house ads. An ad saved with the old per-ad nofollow option on (removed in 3.2.0) starts from its value plus `nofollow`. Return a string with `nofollow` to add it site-wide |
| `wbam_popup_repeat_days` | Days before a visitor sees a popup ad again (`$days`, `$ad_id`; default 1, 0 = every page until closed). An ad saved with the old 'Show again after' field starts from its stored value |
| `wbam_popup_skip_mobile_first_view` | Hold a popup ad back on a phone visitor's first page view (`$skip`, `$ad_id`; default false). An ad saved with the old first-view field starts from its stored value |
| `wbam_ad_output` | The rendered ad HTML |
| `wbam_ad_not_delivering_reason` | Editor-only notice a WB Ad block shows when its ad renders nothing (`$reason`, `$ad_id`). Pro names a missing or ended campaign |
| `wbam_placement_render_mode` | Rotate (draw `wbam_placement_ad_count` ads) vs stack (render every ad of the top delivery tier) for a placement |
| `wbam_placement_ad_count` | Ads a rotating placement shows per page load (`$count`, `$placement_id`; default 1, 0 = every eligible ad). Paid ads take the places first, house and sample ads fill the rest. Pro's "Ads shown" column sets it |
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

## Recipe: add a settings section

The one Settings screen builds its sidebar from `wbam_settings_sections`, an
ordered map of `slug => array( 'label' => ..., 'render' => callable )`. Add
your own section without touching core files:

```php
// Register your own option group, separate from the plugin's own
// wbam_settings_group — an add-on section owns its own data, never the
// plugin's option.
add_action( 'admin_init', function () {
    register_setting( 'my_addon_settings_group', 'my_addon_settings', array(
        'sanitize_callback' => 'my_addon_sanitize_settings',
        'default'           => array(),
    ) );
} );

add_filter( 'wbam_settings_sections', function ( $sections ) {
    $sections['my-addon'] = array(
        'label'  => __( 'My Add-on', 'my-addon' ),
        'render' => 'my_addon_render_settings_section',
    );
    return $sections;
} );

function my_addon_render_settings_section() {
    $settings = get_option( 'my_addon_settings', array() );
    ?>
    <form action="options.php" method="post">
        <?php settings_fields( 'my_addon_settings_group' ); ?>
        <!-- Your own fields, named my_addon_settings[field] -->
        <?php submit_button(); ?>
    </form>
    <?php
}
```

A section slug the built-in icon map does not recognise falls back to a
plain circle in the settings rail rather than rendering with no icon. Each
section renders its own `<form action="options.php">` and its own Save
button — don't post through the plugin's `wbam_settings_group`; that group
belongs to the plugin's own option and sanitizer.

## Recipe: register a custom placement and a custom ad type

Both the placement engine and the ad-type registry are open at plugin init,
after the built-ins are added and before anything renders:

```php
add_action( 'wbam_register_placements', function ( $engine ) {
    $engine->register_placement( new My_Custom_Placement() );
} );

add_action( 'wbam_register_ad_types', function ( $engine ) {
    $engine->register_ad_type( new My_Custom_Ad_Type() );
} );
```

`My_Custom_Placement` implements `Placement_Interface`
(`includes/Modules/Placements/`); `My_Custom_Ad_Type` implements
`Ad_Type_Interface` (`includes/Modules/AdTypes/`). Look at one of the
built-in placements/ad types for the minimal interface to satisfy. A
placement registered after `wbam_placements_init` has already fired (for
example from a plugin that loads late) still registers immediately — see
`Placement_Engine::register_placement()`.

## Example: forward captured emails

```php
add_action( 'wbam_email_captured', function ( $email, $name, $ad_id ) {
    // Send $email to your ESP.
}, 10, 3 );
```

## Example: set your own accent colour

Ads, buttons and links take the theme's colours: BuddyX and Reign accents first, then a block theme's button colour from theme.json, then the `primary` palette colour. To pick a different accent, set the `--wbam-accent` CSS variable in your theme or in Appearance > Customize > Additional CSS:

```css
html:root {
    --wbam-accent: #0a7d4f;
}
```

Text on buttons and Pro's tints follow it. Dark mode follows the theme's own dark switch, not the visitor's operating system.

## Full hook reference

The tables above are the hooks most site developers reach for. Every
`wbam_` hook in the codebase — including internal form/admin hooks not
listed above — is regenerated from source docblocks by
`php bin/generate-hooks-reference.php` (gate: `bash bin/check-hooks-documented.sh`).
Run it after adding or changing a hook; it also refreshes the `hooks_fired`
inventory in `audit/manifest.json`.

<!-- BEGIN GENERATED HOOKS REFERENCE — DO NOT EDIT BY HAND. Run: php bin/generate-hooks-reference.php -->

### Actions (documented)

| Hook | Arguments | Fires when |
|---|---|---|
| `wbam_ad_cap_reached` | $ad_id, $delivered | Fired the moment an ad reaches its total impression cap. |
| `wbam_ad_clicked` | $ad_id, $placement | Action fired when an ad is clicked. |
| `wbam_ad_duplicated` | $new_id, $id | Fires after an ad has been duplicated. |
| `wbam_ad_impression` | $ad_id, $placement | Action fired when an ad impression is recorded. |
| `wbam_ad_metabox_options` | $post | Action for adding additional metabox options. |
| `wbam_ad_status_prime` | $ad_ids | Batch-load anything a `wbam_ad_status` callback reads per ad. |
| `wbam_before_link_delete` | $id, $link | Fires before a partnership link is deleted, while the link and its click records still exist. |
| `wbam_before_link_redirect` | $link, $destination | Fires immediately before a partnership link click redirects the visitor. |
| `wbam_before_partnership_delete` | $id, $partnership | Fires before a partnership inquiry is deleted, while it still exists. |
| `wbam_demo_data_cleared` | $counts | Fires after demo data has been cleared. |
| `wbam_email_captured` | $email, $name, $ad_id | Action fired when an email is captured. Use this hook to integrate with email services like Mailchimp, ConvertKit, etc. |
| `wbam_email_form_after` | $ad_id, $data | Fires after the email capture form. |
| `wbam_email_form_after_email` | $ad_id, $data | Fires after the email field. |
| `wbam_email_form_after_fields` | $ad_id, $data | Fires after all fields, before the submit button. Use this to add custom fields like phone, company, etc. |
| `wbam_email_form_after_name` | $ad_id, $data | Fires after the name field. |
| `wbam_email_form_before` | $ad_id, $data | Fires before the email capture form. |
| `wbam_email_form_before_fields` | $ad_id, $data | Fires before the form fields. |
| `wbam_email_form_submission_after` | $email, $name, $ad_id | Fires after successful email capture submission. |
| `wbam_email_form_submission_before` | $email, $name, $ad_id, $form_data | Fires before processing email capture submission. |
| `wbam_inactive_link_accessed` | $link | Fires when an inactive/expired partnership link is accessed, after the configured inactive-link action (home/custom/404) has run. |
| `wbam_init` | - | Fires once the plugin's components and hooks are fully wired. The safe point for add-ons (including Pro) to register their own placements, ad types and integrations. |
| `wbam_link_category_form_after` | $category, $is_edit | Fires after the link category form. |
| `wbam_link_category_form_before` | $category, $is_edit | Fires before the link category form. |
| `wbam_link_category_form_fields_after` | $category, $is_edit | Fires after all category form fields. Use this to add custom fields to the category form. |
| `wbam_link_category_form_fields_before` | $category, $is_edit | Fires at the beginning of the category form fields. |
| `wbam_link_category_save_after` | $category_id, $data, $is_edit, $message | Fires after saving a link category. |
| `wbam_link_category_save_before` | $data, $category_id, $is_edit | Fires before saving a link category. |
| `wbam_link_click_tracked` | $link_id, $_post | Fires after a link click AJAX request has incremented the click count, so an extension (Pro's Link_Tracker) can record richer per-click data (referrer, geo, device) from the same request. |
| `wbam_link_clicked` | $id, $link | Fires after a REST-tracked partnership link click is recorded. |
| `wbam_link_created` | $link_id, $data | Fires after a new partnership link is inserted. |
| `wbam_link_deleted` | $id | Fires after a partnership link and its click records are deleted. |
| `wbam_link_form_after` | $link, $is_edit | Fires after the link form. |
| `wbam_link_form_after_fields` | $link, $is_edit | Fires after the link form table, before submit button. |
| `wbam_link_form_before` | $link, $is_edit | Fires before the link edit form. |
| `wbam_link_form_fields_after` | $link, $is_edit | Fires after all link form fields. Use this to add custom fields to the link form. |
| `wbam_link_form_fields_before` | $link, $is_edit | Fires at the beginning of the link form fields. |
| `wbam_link_not_found` | - | Fires when a partnership link slug doesn't resolve to any link, after the default 404 response has already been set. |
| `wbam_link_save_after` | $link_id, $data, $is_edit, $message | Fires after saving a link. |
| `wbam_link_save_before` | $data, $link_id, $is_edit | Fires before saving a link. |
| `wbam_link_updated` | $id, $data | Fires after a partnership link is updated. |
| `wbam_links_module_init` | $module | Fires after the Links module has wired its own hooks, so an extension (Pro's Links_Pro_Module) can add its own AJAX handlers and filters on top. |
| `wbam_partnership_accepted` | $partnership | Fires after a partnership inquiry is accepted. Notifies the requester by default (see Partnership_Emails::notify_requester_accepted()). |
| `wbam_partnership_created` | $partnership | Fires after a new partnership inquiry is inserted. |
| `wbam_partnership_deleted` | $id | Fires after a partnership inquiry is deleted. |
| `wbam_partnership_email_sent` | $to, $subject, $message, $sent | Fires after a partnership email has been sent (or skipped/failed). |
| `wbam_partnership_form_after` | $atts | Fires after the partnership form. |
| `wbam_partnership_form_after_anchor` | $atts | Fires after the anchor text field. |
| `wbam_partnership_form_after_budget` | $atts | Fires after the budget fields. |
| `wbam_partnership_form_after_fields` | $atts | Fires after all standard fields, before the submit button. Use this hook to add custom fields to the form. |
| `wbam_partnership_form_after_message` | $atts | Fires after the message field. |
| `wbam_partnership_form_after_name_email` | $atts | Fires after the name/email fields row. |
| `wbam_partnership_form_after_type` | $atts | Fires after the partnership type field. |
| `wbam_partnership_form_after_website` | $atts | Fires after the website field. |
| `wbam_partnership_form_before` | $atts | Fires before the partnership form renders. |
| `wbam_partnership_form_before_fields` | $atts | Fires at the beginning of the form, before any fields. |
| `wbam_partnership_form_submission_after` | $partnership, $data | Fires after successful form submission. |
| `wbam_partnership_form_submission_before` | $_POST | Fires before processing the form submission. |
| `wbam_partnership_rejected` | $partnership | Fires after a partnership inquiry is rejected. Notifies the requester by default (see Partnership_Emails::notify_requester_rejected()). |
| `wbam_partnership_updated` | $updated_partnership, $existing | Fires after a partnership inquiry is updated. |
| `wbam_placement_candidates` | $ad_ids, $placement_id | Fires with a placement's candidate ads before each one is checked, so an extension can batch-load what its `wbam_should_display_ad` callback needs in one query instead of one per ad. |
| `wbam_placement_matrix_cell` | $id, $placement | Fires once per placement row in the matrix, after the built-in "Live ads" cell, when an active add-on has registered a callback here (see wbam_placement_matrix_head for the matching header cell). Echo one <td> matching the extra header column. |
| `wbam_placement_matrix_head` | - | Fires inside the placement matrix's <thead> row, after the built-in columns, when an active add-on has registered a wbam_placement_matrix_cell callback. Echo one <th> per extra column added in the row below. |
| `wbam_placement_matrix_intro` | - | Fires after the Placements intro copy, before the matrix table. PRO's rotation module hooks this to explain the "Ads shown" column it adds to the matrix (see Placement_Settings::render_table()'s `wbam_placement_matrix_head`/`wbam_placement_matrix_cell` hooks). |
| `wbam_placements_init` | $engine | Fires once the placement engine has registered its built-in ad types and placements and is ready to serve ads. |
| `wbam_register_ad_types` | $engine | Fires after the built-in ad types are registered. Call `$engine->register_ad_type( new My_Ad_Type() )` here to add a custom ad type; `My_Ad_Type` must implement `Ad_Type_Interface`. |
| `wbam_register_placements` | $engine | Fires after the built-in placements are registered. Call `$engine->register_placement( new My_Placement() )` here to add a custom placement; `My_Placement` must implement `Placement_Interface`. Placements registered after `init` (id est after `wbam_placements_init` has fired) still register immediately — see `register_placement()`. |
| `wbam_rest_event_tracked` | $ad_id, $event_type, $placement | Fires after a REST-tracked impression/click is recorded. |
| `wbam_save_ad_meta` | $post_id | Action fired after ad meta is saved. |
| `wbam_settings_ads_display_content` | - | Fires inside the Ads & Display section's one `<form>`, after FREE's own cards and before the single Save button (card 10343706274: one form, one Save per section) - PRO hooks its Ad Rotation card here (when the rotation module is active). PRO's fields post through this same `options.php` submission because `wbam_pro_settings` is also registered under this page's `wbam_settings_group` (see Pro_Admin::register_settings()) - its own sanitizer runs unchanged, only the physical form is shared. |
| `wbam_settings_links_content` | - | Fires inside the Links section's one `<form>`, after cloaking settings and before the single Save button (card 10343706274: one form, one Save per section). |
| `wbam_settings_location_content` | $saving | Fires inside the Location section's one `<form>`, after visitor geolocation and before the single Save button (card 10343706274: one form, one Save per section). PRO's classified-maps card writes its own option (`wbam_pro_geolocation_settings`) directly - it cannot share this page's native `wbam_settings_group` Settings API processing the way `wbam_pro_settings` does elsewhere, so it is instead gated on the `$saving` flag this same submission already verified. save of this page's form. |
| `wbam_settings_privacy_content` | - | Fires inside the Privacy & Data section's one `<form>`, after FREE's own cards and before the single Save button (card 10343706274: one form, one Save per section). PRO's analytics/GDPR card posts through this same `options.php` submission because `wbam_pro_settings` is also registered under this page's `wbam_settings_group`. |
| `wbam_settings_tools_content` | - | Fires inside the Tools section. Print a heading (`<h2 class="wbam-settings-heading">`) and one `.wbam-card`. |
| `wbam_setup_wizard_complete` | - | Fires when setup wizard is completed. |
| `wbam_setup_wizard_ready_after` | - | Fires after the ready step content. |
| `wbam_setup_wizard_ready_after_steps` | - | Fires after the next steps links, before the dashboard button. |
| `wbam_setup_wizard_ready_before` | - | Fires before the ready step content. |
| `wbam_setup_wizard_sample_ad_created` | $post_id, $ad_key, $ad | Fires after a sample ad is created. |
| `wbam_setup_wizard_sample_after` | - | Fires after the sample ads step content. |
| `wbam_setup_wizard_sample_before` | - | Fires before the sample ads step content. |
| `wbam_setup_wizard_sample_form_after` | - | Fires after the sample ads form fields. |
| `wbam_setup_wizard_sample_form_before` | - | Fires before the sample ads form fields. |
| `wbam_setup_wizard_sample_save_after` | $sample_ads | Fires after creating sample ads. |
| `wbam_setup_wizard_sample_save_before` | $sample_ads | Fires before creating sample ads. |

### Filters (documented)

| Hook | Arguments | Filters |
|---|---|---|
| `wbam_ad_container_class` | $container_class | Filter the extra CSS class added to every rendered ad's container wrapper. Plug and play (owner decision, card 10343726590): no Settings UI field any more. A site's already-stored `container_class` is this filter's default, so nothing changes silently; a developer who wants a class without a field to click uses this filter instead. |
| `wbam_ad_data_before_save` | $data, $post_id, $raw_data | Filter ad data before saving. |
| `wbam_ad_delivery_tier` | $tier, $ad_id, $placement_id | Filter an ad's delivery tier. Higher tiers win the slot first. |
| `wbam_ad_display_rules` | $rules, $ad_id | Filter display rules for an ad. |
| `wbam_ad_event_total` | $count, $post_id, $event_type | Filters the lifetime event total shown in the ads list table. |
| `wbam_ad_event_totals` | $totals | Filters the lifetime event totals for a page of ads. |
| `wbam_ad_formats` | $formats | Filter the canonical ad format taxonomy. Downstream consumers may append site-specific formats here, but should not remove or rename the built-in entries — ads and packages reference them by slug. |
| `wbam_ad_link_rel` | $rel, $ad_id | Filter the rel attribute of an ad's click-through link. Return 'sponsored noopener' to mark a house ad as paid. |
| `wbam_ad_not_delivering_reason` | $reason, $ad_id | Filter the editor notice for a WB Ad block whose ad renders nothing right now. |
| `wbam_ad_output` | $output, $ad_id, $placement | Filter the ad output HTML. |
| `wbam_ad_status` | $status, $ad_id | Filter an ad's state and reason. Pro adds campaign reasons. |
| `wbam_ad_tag_taxonomy_args` | $args | Filter the ad tag taxonomy arguments. Lets a site relabel the taxonomy, widen its capabilities, or turn on hierarchy without forking the plugin. |
| `wbam_ad_types_without_placements` | $types | Filter which ad type IDs are never served through a placement (e.g. a video ad played in-stream by the host plugin instead of painted into a header/sidebar slot). |
| `wbam_ad_types_without_sizing` | $types | Filter which ad type IDs skip the fixed width/height sizing metabox. |
| `wbam_admin_menu_section_map` | $groups, $parent_slug | Filter the slug -> section-key map for a menu. $parent_slug says which menu. |
| `wbam_admin_menu_sections` | $group_order, $parent_slug | Filter the ordered section-key -> label list for a menu. Controls both section order and labels (an empty label renders no header row). |
| `wbam_admin_status_variant` | $variant, $status | Filter the badge variant chosen for a status. |
| `wbam_ads_eligible_for_placement` | $filtered, $placement_id | Filter the ads eligible for a placement, before a winner is picked. Eligibility rules that depend on the placement belong here, not on `wbam_ads_for_placement`: dropping the winner after the draw blanks the slot instead of letting the next eligible ad fill it. |
| `wbam_ads_for_placement` | $filtered, $placement_id, $ad_ids | Filter the ads returned for a placement. |
| `wbam_advertiser_placements` | $ids | Filter the placements sellable to advertisers. |
| `wbam_analytics_raw_retention_days` | $days | Filters how many days raw analytics events are kept. Older events are summed into wbam_analytics_daily and deleted, so lifetime totals are unchanged. |
| `wbam_asset_suffix` | $suffix, $relative_path | Filter the minification suffix an asset URL resolves to. Exists so the test suite can drive the SCRIPT_DEBUG-on (source file) path without defining the SCRIPT_DEBUG constant globally, which would leak into every other test in the run. |
| `wbam_bot_patterns` | $bot_patterns | Filter the user-agent fragments treated as bots. |
| `wbam_classifieds_label` | $label, $form | Filters the site's name for a classified item on Free screens. |
| `wbam_code_ad_content` | $code, $ad_id, $options | Filter code ad content before rendering. Allows developers to apply custom sanitization or processing to code ads for additional security measures. |
| `wbam_code_ad_sandbox_attrs` | $sandbox_attrs, $ad_id | Filter the sandbox attributes for code ad iframes. allow-same-origin is dropped whenever allow-scripts is present. |
| `wbam_code_ad_use_sandbox` | $use_sandbox, $ad_id, $code | Filter whether to use iframe sandbox for this code ad. When enabled, the ad code will be rendered in a sandboxed iframe for additional security isolation. |
| `wbam_count_analytics_event` | $counts, $ad_id, $event_type, $placement | Filter whether this analytics event is counted. |
| `wbam_count_visitor_views` | $counts, $ad_id | Filters whether visitor views of an ad are counted. Return true when a cap outside the ad's own daily limit reads get_ad_views() for this ad. |
| `wbam_currency_code` | $currency | Filter the site's currency code. |
| `wbam_currency_symbol` | $symbol, $currency | Filter the currency symbol. |
| `wbam_detected_device` | $device, $user_agent | Filter the detected device type. Allows themes/plugins to override device detection for custom logic. |
| `wbam_email_capture_cookie_days` | $days, $ad_id | Filter how many days a dismissed email sign-up ad stays hidden. Plug and play (owner decision, card 10343726590): no Settings UI field any more. This ad's already-stored `cookie_days` is this filter's default, so nothing changes silently. |
| `wbam_email_capture_success_message` | $success_message, $email, $ad_id | Filter the success message for email capture. |
| `wbam_email_form_button_text` | $button_text, $ad_id, $data | Filter the button text. |
| `wbam_email_form_classes` | $classes, $ad_id, $options | Filter the wrapper CSS classes. |
| `wbam_email_form_data` | $data, $ad_id | Filter the form configuration data. |
| `wbam_email_form_placeholders` | $placeholders, $ad_id | Filter the field placeholders. |
| `wbam_email_form_privacy_text` | $privacy_text, $ad_id, $data | Filter the privacy text. |
| `wbam_email_form_show_cookie_check` | $check_cookie, $ad_id | Filter whether to check the dismiss cookie. Return false to always show the form regardless of cookie. |
| `wbam_email_form_success_message` | $success_msg, $ad_id, $data | Filter the success message. |
| `wbam_email_form_validation` | $valid, $email, $name, $ad_id, $form_data | Custom validation filter for email capture. Return a WP_Error to fail validation with a custom message. |
| `wbam_enable_setup_wizard` | $enabled | Filter whether the first-run setup wizard is available at all. init hook and notice. Default true. |
| `wbam_enabled_placements` | $ids | Filter the placements usable on this site. |
| `wbam_enforce_format_matching` | $enforce, $post_id | Filter whether the ad editor greys out placements the ad's resolved size doesn't fit, instead of letting every placement stay tickable regardless of shape. to the site's format_matching setting. |
| `wbam_enforce_page_cap` | $enforce_cap, $ad_id, $options | Filter whether the once-per-request page cap is enforced for an ad. Lets a site owner opt out per placement (e.g. a sticky bar that must always show, even if the same ad already rendered elsewhere). the inverse of $options['allow_duplicate']. |
| `wbam_format_fit_tolerance` | $tolerance | Filter the pixel tolerance when matching dimensions to the taxonomy. 0 = exact match required (default). |
| `wbam_get_placements` | $registry | Filter the placement registry: slug => { name, description, group, accepted_formats }. This is the single source of truth for "which placements exist and what do they accept" across admin, REST, WP-CLI and the frontend. Free seeds it from its own Placement_Engine at priority 5; Placement_Format_Map attaches accepted_formats at priority 20; Pro and third-party placements add their own entries at the default priority 10. |
| `wbam_has_consent` | $has_consent, $consent_type | Filter the consent check result before the plugin's own logic runs, e.g. to bridge a third-party consent-management plugin. null (default) to defer to the plugin's own `require_consent_adsense` setting. |
| `wbam_is_admin_screen` | $is_ours, $hook | Filter whether the shared admin token palette should load here. Lets an extension opt a custom screen into the WB Ad Manager admin styling foundation. |
| `wbam_is_module_enabled` | $enabled, $slug | Filter whether a single module is enabled. PRO reads this so a module switched off in FREE also removes the submenus PRO contributes to it. |
| `wbam_legacy_settings_tab_map` | $map | Filter the old Pro Settings tab slug -> new section slug map. Most tabs keep their slug as the section slug; PRO adds the few that were renamed or merged (analytics -> privacy; modules/pages/rotation -> advertising). |
| `wbam_link_category_save_data` | $data, $category_id, $raw_post | Filter category data before saving. |
| `wbam_link_click_trends` | $trends, $days, $start_date | Filter the click-trends payload before it is returned. |
| `wbam_link_redirect_type` | $redirect_type, $link | Filter the HTTP redirect status used for a partnership link. |
| `wbam_link_redirect_url` | $url, $link | Filter the destination URL a partnership link redirects visitors to. |
| `wbam_link_save_data` | $data, $link_id, $raw_post | Filter link data before saving. |
| `wbam_link_shortcode_attributes` | $link_attrs, $link, $atts | Filter link HTML attributes before rendering. |
| `wbam_link_shortcode_defaults` | $defaults | Filter default attributes for the wbam_link shortcode. |
| `wbam_link_shortcode_output` | $output, $link, $atts, $text | Filter the final link shortcode output. |
| `wbam_link_url_shortcode_output` | $url, $link, $atts | Filter the link URL shortcode output. |
| `wbam_links_shortcode_defaults` | $defaults | Filter default attributes for the wbam_links shortcode. |
| `wbam_links_shortcode_output` | $output, $links, $atts | Filter the links list shortcode output. |
| `wbam_links_shortcode_query_args` | $args, $atts | Filter query arguments for the wbam_links shortcode. |
| `wbam_load_link_tracking_js` | $load | Filter whether the frontend click-tracking script is enqueued. |
| `wbam_module_defaults` | $defaults | Filter the optional module list and their default states. |
| `wbam_notice_suppressor_namespaces` | $namespaces | Filter the namespaces whose admin notices are kept on WB Ads screens. Companion plugins add their own so their notices are not stripped as third-party. |
| `wbam_partnership_accepted_notification_message` | $message, $partnership | Filter the plain-text body of the requester's "accepted" notification email. |
| `wbam_partnership_admin_notification_message` | $message, $partnership | Filter the plain-text body of the admin new-inquiry notification email. |
| `wbam_partnership_email_headers` | $headers | Filter the wp_mail() headers used for a partnership email. This class's own get_email_headers() adds the plain-text Content-Type and From header at default priority. |
| `wbam_partnership_form_attributes` | $atts | Filter the shortcode attributes. |
| `wbam_partnership_form_button_class` | $button_classes, $atts | Filter the submit button CSS classes. |
| `wbam_partnership_form_data` | $data, $_POST | Filter the submission data before validation. Use this to add custom fields to the data array. |
| `wbam_partnership_form_duplicate_hours` | $hours | Filter how many hours a duplicate partnership submission (same email + website URL) is blocked for. Return 0 to disable the check. |
| `wbam_partnership_form_error_messages` | $strings | Filter the error messages for the form. |
| `wbam_partnership_form_scripts` | $script_data | Filter the localized script data. |
| `wbam_partnership_form_styles` | $styles | Filter the partnership form CSS. Return an empty string to drop the form's styles completely. |
| `wbam_partnership_form_success_message` | $success_message, $partnership, $data | Filter the success message. |
| `wbam_partnership_form_types` | $partnership_types | Filter the available partnership types. |
| `wbam_partnership_form_validation` | $valid, $data, $_POST | Custom validation filter. Return a WP_Error object to fail validation with custom message. |
| `wbam_partnership_form_wrapper_class` | $wrapper_classes, $atts | Filter the wrapper CSS classes. |
| `wbam_partnership_pre_send_email` | $sent, $to, $subject, $message | Short-circuit sending a partnership email, e.g. to route it through another mailer or email layout. |
| `wbam_partnership_rejected_notification_message` | $message, $partnership | Filter the plain-text body of the requester's "rejected" notification email. |
| `wbam_placement_ad_count` | $count, $placement_id | Filter how many ads a rotating placement shows per page load. Pro's "Ads shown" column on the Placements matrix sets it. |
| `wbam_placement_format_map` | $map | Filter the placement -> accepted-formats map. Third-party placement authors should hook this filter to register their slug. Unregistered placements are treated as permissive (empty accepted_formats = accept anything). |
| `wbam_placement_render_mode` | $render_mode, $placement_id | Filter the render mode for a placement. Slot policy: a rotating placement shows `wbam_placement_ad_count` ads per load (one by default), never every creative that targets it. Placements that legitimately render every eligible ad can opt out per-placement by returning 'stack' for their slug. |
| `wbam_placement_shapes` | $shapes | Filter the shape -> format-slugs map. |
| `wbam_popup_repeat_days` | $days, $ad_id | Days before a visitor sees this popup again; 0 shows it on every page until they close it. Ads saved while this was a field start from their stored value. |
| `wbam_popup_skip_mobile_first_view` | $skip, $ad_id | Whether to hold this popup back on a phone visitor's first page view. Ads saved while this was a field start from their stored value. |
| `wbam_preload_frontend_assets` | $preload | Whether this request should preload the frontend ad CSS/JS in the head even though none of the built-in signals matched - e.g. a theme template that calls `do_shortcode('[wbam_ad id="1"]')` outside post_content, where has_shortcode() cannot see it. |
| `wbam_priority_hint` | $hint, $post_id | Filter the editor's priority hint. Pro explains that a paid ad's share comes from its campaign's rotation, not Priority. |
| `wbam_rotation_pick` | $pick, $pool, $placement_id, $tier | Choose the winner from a pool of same-tier ads. Return an ID from the pool, or null for the default priority-weighted draw. |
| `wbam_sample_ad_link` | $url | Where a sample ad links. Empty (the default) means no link; Pro returns its Advertise page when one is published. |
| `wbam_sample_content_card_owned` | $owned | Whether an add-on renders the Sample content card instead. |
| `wbam_send_partnership_accepted_notification` | $send, $partnership | Filter whether the requester's "accepted" notification email sends. |
| `wbam_send_partnership_admin_notification` | $send, $partnership | Filter whether the admin new-inquiry notification email sends. |
| `wbam_send_partnership_rejected_notification` | $send, $partnership | Filter whether the requester's "rejected" notification email sends. |
| `wbam_settings_section_aliases` | $aliases | Filter the old section slug -> current section slug map. Old section slugs (pre-3.2.0 layout, or PRO's retired horizontal tabs) that now render somewhere else — merged into another section's body (License) or simply renamed (Ad Display, Geolocation, Advertising). Map those here so both the nav highlight and the body agree on which section is "current". |
| `wbam_settings_sections` | $sections | Filter the sidebar sections on the one Settings screen. |
| `wbam_setup_wizard_next_steps` | $next_steps | Filter the next steps shown on the ready screen. |
| `wbam_setup_wizard_sample_ads` | $sample_ads, $ads_to_create | Filter the sample ads definitions. Allows developers to modify, add, or remove sample ad configurations. |
| `wbam_setup_wizard_sample_options` | $options | Filter available sample ad options in setup wizard. |
| `wbam_setup_wizard_steps` | $steps | Filter the setup wizard steps. Allows developers to add, remove, or modify wizard steps. |
| `wbam_should_display_ad` | $should_display, $ad_id | Filter the final should-display decision for an ad, after the built-in schedule, targeting and frequency checks have all passed. |
| `wbam_show_ad_label` | $show, $ad_id, $ad_type | Whether this ad carries the disclosure label. The site's own signup form is not an advertisement, so Email Capture ads go without it unless this filter says otherwise. |
| `wbam_skip_content_injection` | $skip, $content | Filter whether to skip in-content ad injection (before/after content and after-paragraph) on the current page. Application pages - an account dashboard, a posting form, a message thread - render their UI through the_content, and ads injected there land inside forms. |
| `wbam_uninstall_data_items` | $items | Filter the list of data 'Delete Data on Uninstall' removes. |
| `wbam_viewable_beacon_url` | $url, $ad_id, $placement | Filters the URL the viewability beacon sends for an ad. |
| `wbam_viewable_impressions` | $enabled | Whether "viewable" impressions (popup/sticky/code/AdSense ads count only once actually seen) are counted this way. Plug and play (owner decision, card 10343706274): this used to be a Settings UI checkbox; the field is gone, but a site that already had it on keeps counting this way — the current stored value is this filter's default, so nothing changes silently. A developer who wants a different default uses this filter. |

### Not yet documented

These hooks exist in the code but have no docblock summary yet. Run `bash bin/check-hooks-documented.sh` to see the full gate report; add a docblock at the call site and re-run the generator to move an entry into the tables above.

**Actions:**

_None._

**Filters:**

_None._

### Deprecated

A hook below still fires (existing listeners keep working, with a core `_doing_it_wrong()`-style notice) but should be moved to its replacement — the old name is removed after a full minor-version cycle.

_None currently deprecated._

<!-- END GENERATED HOOKS REFERENCE -->

## Deprecated

No `wbam_` hook is currently deprecated in this plugin — none has been
renamed or removed since the 3.0.0 rewrite from the legacy
`buddypress-ads-rotator` structure.

When a hook does need to be renamed or removed, the policy (see
`plan/roadmap-5-year.md`) is: once a `wbam_` action or filter ships, its name
and signature are guaranteed for the lifetime of the major version. A rename
goes through `do_action_deprecated()` / `apply_filters_deprecated()` for a
full minor-version cycle before removal, so existing listeners keep firing
(with a `_doing_it_wrong()`-style notice) until the old name is dropped. This
generator lists any such hook under a "Deprecated" heading here automatically
once one exists, sourced from the `@deprecated` tag on its docblock.

## Next steps

- [REST API](000-rest-api.md)
- [Helper Functions](020-helper-functions.md)
- [Add-on UI Kit](040-addon-ui-kit.md)
