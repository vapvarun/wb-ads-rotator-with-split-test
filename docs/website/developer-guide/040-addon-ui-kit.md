# Add-on UI Kit

Every admin screen in Free and Pro — 20-plus of them — builds its page chrome, status pills, tabs and empty states through one shared class: `\WBAM\Admin\UX` (`includes/Admin/class-ux.php`). Build your add-on's own admin screens with the same helpers and they read as one product instead of a bolted-on extra. The markup pairs with `assets/css/admin-family.css`, already enqueued on every `wbam-admin`-scoped screen.

Pro calls these as `\WBAM\Admin\UX::page_header( … )` — Free is always active under Pro, so no `class_exists()` guard is needed on the Pro side. A third-party add-on that requires Free (or Free+Pro) can call them the same way.

Wrap your screen's markup in `<div class="wrap wbam-admin">…</div>` — the `wbam-admin` class is what scopes the family's WP-list-table normalisation and design tokens.

## `UX::page_header( $args = array() )`

Renders the page's title, optional subtitle and optional right-aligned actions — replaces a hand-rolled `<h1 class="wp-heading-inline">` + `<hr>`.

```php
\WBAM\Admin\UX::page_header( array(
    'title'   => __( 'My Add-on', 'my-addon' ),
    'desc'    => __( 'One-line description of what this screen does.', 'my-addon' ),
    'actions' => '<a href="' . esc_url( $add_new_url ) . '" class="wbam-admin-btn wbam-admin-btn--primary">' . esc_html__( 'Add New', 'my-addon' ) . '</a>',
) );
```

| Arg | Type | Description |
|---|---|---|
| `title` | string | Required. Page title (escaped by the helper). |
| `desc` | string | Optional one-line subtitle. |
| `actions` | string | Optional pre-escaped HTML for the right side (e.g. an "Add New" button). You escape it; the helper only constrains the allowed tags via `wp_kses()`. |
| `back_url` | string | When set, renders a "back to list" link above the title — use on single-purpose action screens (reject, decline, adjust balance, add/edit) instead of hand-rolling your own back link. |
| `back_label` | string | Back link text. Default `"Back to list"`. |
| `echo` | bool | Echo (default `true`) or return the HTML. |

Output: the header block plus a `<hr class="wp-header-end">` anchor so WordPress relocates admin notices under the header, not above it.

## `UX::status_badge( $status, $label = null )`

Renders a coloured status pill. The colour comes from `UX::status_variant( $status )`, a shared status→colour map (pending = amber, rejected = red, active = green, etc.) so the same status word means the same colour on every screen in both plugins.

```php
echo \WBAM\Admin\UX::status_badge( 'pending' );
// <span class="wbam-status-badge wbam-status-badge--warning">Pending</span>
```

| Arg | Type | Description |
|---|---|---|
| `$status` | string | Status slug; also the fallback label (title-cased). |
| `$label` | string\|null | Optional display label; defaults to the title-cased slug. |

A status slug the built-in map doesn't recognise falls back to `muted` (grey) rather than erroring. Add your own slug's colour via the `wbam_admin_status_variant` filter (`$variant, $status`) instead of a new function — see [Hooks and Filters](010-hooks-and-filters.md).

## `UX::empty_state( $args = array() )`

Renders a "nothing here yet" panel for an empty list/table.

```php
echo \WBAM\Admin\UX::empty_state( array(
    'icon'    => 'inbox',
    'title'   => __( 'No submissions yet', 'my-addon' ),
    'message' => __( 'Submissions will appear here once members start posting.', 'my-addon' ),
) );
```

| Arg | Type | Description |
|---|---|---|
| `icon` | string | Optional Lucide icon name (rendered via `wbam_icon()`), shown above the title. |
| `title` | string | Headline. Default `"Nothing here yet"`. |
| `message` | string | Optional one-line explanation. |
| `actions` | string | Optional pre-escaped action HTML (e.g. an "Add your first…" button). |

## `UX::status_variant( $status )`

The status→colour lookup `status_badge()` uses internally. Call it directly when you need the colour name (`success`/`danger`/`warning`/`info`/`muted`) without the badge markup — for example to colour a table row or an icon.

```php
$variant = \WBAM\Admin\UX::status_variant( 'rejected' ); // 'danger'
```

## `UX::tabs( array $tabs, $current, $variant = 'underline', $aria_label = '' )`

Renders the page-navigation tab row for a screen with more than one full sub-page (e.g. Help & Docs, or Overview/Audit views). Each tab is a plain link to its own URL — a `<nav aria-label>` of `<a>`s with `aria-current="page"` on the active one, not an ARIA tablist (this doesn't swap panels in place under one URL).

```php
\WBAM\Admin\UX::tabs(
    array(
        'overview' => array( 'label' => __( 'Overview', 'my-addon' ), 'url' => $overview_url ),
        'audit'    => array( 'label' => __( 'Audit', 'my-addon' ), 'url' => $audit_url ),
    ),
    'overview'
);
```

| Arg | Type | Description |
|---|---|---|
| `$tabs` | array | Ordered map of tab slug => `{ label, url }`. |
| `$current` | string | Active tab slug. |
| `$variant` | string | Deprecated, ignored — `'underline'` is the only style. |
| `$aria_label` | string | Accessible name for the nav landmark. Defaults to "Section navigation". |

Renders nothing (`void`, echoes directly) when `$tabs` is empty.

If your screen instead needs a same-page value picker (not a link to another URL), use a radiogroup of cards — see the ad-type picker in `Admin::render_settings_metabox()` — not this helper.

## `UX::settings_nav( array $sections, string $current )`

Renders the left-hand section rail for a multi-section, single-URL admin screen (the pattern the plugin's own Settings screen uses: General, Ads & Display, Classifieds, … all behind one page with `?section=`). Desktop gets a grouped `<ul>` card-like rail with a Lucide icon per item; narrow screens (≤782px, matching WP core's own admin-menu collapse point) get a `<select>` inside a plain GET form instead.

```php
\WBAM\Admin\UX::settings_nav(
    array(
        'general' => array( 'label' => __( 'General', 'my-addon' ), 'url' => $general_url, 'icon' => 'settings', 'group' => __( 'My Add-on', 'my-addon' ) ),
        'advanced' => array( 'label' => __( 'Advanced', 'my-addon' ), 'url' => $advanced_url, 'icon' => 'sliders', 'group' => __( 'My Add-on', 'my-addon' ) ),
    ),
    $current_section
);
```

| Arg | Type | Description |
|---|---|---|
| `$sections` | array | Ordered map of section slug => `{ label, url, icon?, group? }`. Sections sharing a `group` value render under one heading, in first-seen order. |
| `$current` | string | Active section slug. |

## `UX::page_jump_nav( array $items )`

Renders an "On this page" jump row of `#anchor` links for a settings section with 4 or more cards — deliberately renders nothing below that threshold (a jump row for 2-3 cards is clutter, not a navigation aid). Narrow screens get the same links swapped for a compact `<select>` once JS has run; a JS-less visitor keeps the always-functional link row.

```php
\WBAM\Admin\UX::page_jump_nav( array(
    'my-addon-general'  => __( 'General', 'my-addon' ),
    'my-addon-advanced' => __( 'Advanced', 'my-addon' ),
) );
```

| Arg | Type | Description |
|---|---|---|
| `$items` | array | Ordered map of card id (no leading `#`) => short label. Each id must match an `id="…"` on the corresponding card in your markup. |

## `UX::action_summary( $args = array() )`

Renders the "what this affects" summary at the top of an action-screen card (reject/decline/adjust-balance/delete screens). Bulk mode lists the first five selected item titles plus "and N more"; single mode shows one item's title, an optional secondary line, and an optional status badge.

```php
// Single mode.
echo \WBAM\Admin\UX::action_summary( array(
    'title'  => $submission->title,
    'meta'   => $submission->advertiser_name,
    'status' => $submission->status,
) );

// Bulk mode.
echo \WBAM\Admin\UX::action_summary( array(
    'label' => __( 'You are about to reject:', 'my-addon' ),
    'items' => $selected_titles,
) );
```

| Arg | Type | Description |
|---|---|---|
| `items` | string[] | Bulk mode: selected item titles, in order. Non-empty triggers bulk mode; single-mode args are then ignored. |
| `label` | string | Bulk mode: heading above the list. |
| `title` | string | Single mode: the item's title/name. |
| `meta` | string | Single mode: a secondary line (e.g. the seller or advertiser name). |
| `status` | string | Single mode: a status slug, rendered via `status_badge()`. |

## `UX::action_bar( $args = array() )`

Renders the submit/cancel row at the bottom of an action-screen card: a primary or danger submit button plus a Cancel link back to the list.

```php
echo \WBAM\Admin\UX::action_bar( array(
    'submit_label' => __( 'Reject Submission', 'my-addon' ),
    'submit_name'  => 'reject',
    'variant'      => 'danger',
    'cancel_url'   => $list_url,
) );
```

| Arg | Type | Description |
|---|---|---|
| `submit_label` | string | Required. Button text. |
| `submit_name` | string | Optional `name` attribute on the submit button. |
| `variant` | string | `'primary'` (default) or `'danger'` — reject/decline/delete actions use `danger`. |
| `cancel_url` | string | Required. Where Cancel goes (the list screen). |
| `cancel_label` | string | Defaults to "Cancel". |

## Accent colour

Buttons rendered through `action_bar()`/`page_header()` follow the same `--wbam-accent` CSS custom property the frontend ad chrome uses — see the accent-colour example in [Hooks and Filters](010-hooks-and-filters.md). You don't need your own colour variable for an add-on screen; token-driven CSS in `admin-family.css` already carries it.

## Next steps

- [Hooks and Filters](010-hooks-and-filters.md)
- [Helper Functions](020-helper-functions.md)
