# Vocabulary Glossary

Card 10343726476: "One vocabulary across admin, portal, public pages and emails."
Owner-decided terms for WB Ad Manager Pro (and Free, where the same concepts
appear). This is the single source of truth for customer-facing wording. When
in doubt, this file wins over any older comment, README or code convention.

## The rule

Every translatable string (`__()`, `_e()`, `_x()`, `_n()`, `esc_html__()`,
`esc_html_e()`, `esc_attr__()`, `esc_attr_e()`, and JS strings shown to a
user) must use the owner's terms below. No exceptions for admin screens —
the site owner is a customer too.

## Owner's terms

| Concept | Say this | Never this |
|---|---|---|
| Money the advertiser holds | **Balance** | Wallet, Credits, Credit balance |
| Adding money to the balance | **Add funds** | Buy credits, Top up credits, Purchase credits |
| A single top-up/spend row in the ledger | **Top-up** (add), plain money wording (spend) | Credit, Debit |
| A free grant of money | **Complimentary funds** | Complimentary credit(s) |
| Where an ad can appear | **Placement** (`Placement inventory` for the admin screen) | Slot, Zone |
| A geographic area (unrelated to ad placement) | **Location** | Zone |
| The visual/media of an ad | **Ad** (the whole unit), **image** (the file) | Creative(s) |
| A classifieds entry the owner's classifieds label always (see below) | *dynamic* | "Listing", "Classified" hard-coded |

### Item = the owner's classifieds label, always

The classifieds module lets a site owner rename what they're selling
(default "Classified" / "Classifieds", but a site can relabel to "Property" /
"Properties", "Item" / "Items", anything). Every string that names the thing
being posted, browsed, promoted, reported, etc. **must** pull the label at
render time — never hard-code "listing" or "classified".

```php
use WBAM_Pro\Core\Settings_Helper;

// Singular, Title Case default "Classified" - use where a heading/label reads naturally capitalized.
Settings_Helper::get_classifieds_label( 'singular' );

// Plural, Title Case default "Classifieds".
Settings_Helper::get_classifieds_label( 'plural' );

// Mid-sentence (house style: sentence case) - lowercase it yourself.
strtolower( Settings_Helper::get_classifieds_label( 'singular' ) );
```

Always route the label through `sprintf()`/`printf()` with a `translators:`
comment. Never concatenate.

```php
// Right.
printf(
    /* translators: %s: the site's singular item label, e.g. "listing" */
    esc_html__( 'Are you sure you want to delete this %s?', 'wb-ad-manager-pro' ),
    esc_html( strtolower( Settings_Helper::get_classifieds_label( 'singular' ) ) )
);

// Wrong - hard-codes the word the site owner may have renamed.
esc_html_e( 'Are you sure you want to delete this classified?', 'wb-ad-manager-pro' );
```

For a count that needs to read naturally in both English plural forms
("1 listing" / "5 listings"), do not use `_n()` for the item word itself
(the label already carries the singular/plural distinction) - pick the
matching label form by the count and interpolate both as plain `%s`:

```php
printf(
    /* translators: 1: number of items, 2: the site's singular or plural item label matching the count, e.g. "1 listing" / "5 listings" */
    esc_html__( '%1$s %2$s', 'wb-ad-manager-pro' ),
    esc_html( number_format_i18n( $count ) ),
    esc_html( 1 === (int) $count ? strtolower( Settings_Helper::get_classifieds_label( 'singular' ) ) : strtolower( Settings_Helper::get_classifieds_label( 'plural' ) ) )
);
```

### "Price per unit of balance" - the one narrow money exception

Money is "Balance" + "Add funds" everywhere. The single place the
underlying per-unit rate is named is the Settings > Payments "Price per
unit of balance" row, and only when a site actually sells below face value
(`wbam_credit_price_cents` below the minor-unit factor). At the default (1
unit = 1 currency unit, true for every site that never touches this), the
row is hidden. See `includes/Admin/class-credits-settings.php`
`save_credit_price()` for the fallback and the `wbam_pro_credit_price_cents`
filter a site uses to sell below face value (plug-and-play, no separate
settings field).

## Banned words (blocked by CI)

`bin/check-retired-vocab-in-i18n.sh` fails the build on any of these in a
translation-function string literal, anywhere in the codebase (admin
included):

- `slot(s)` - say **placement**
- `zone(s)` - say **placement** (ad location) or **location** (geography)
- `creative(s)` - say **ad** or **image**
- `credit(s)` - money only, say **Balance** / **Add funds**
- `wallet(s)` - money only, say **Balance**
- `listing(s)` / `classified(s)` hard-coded - use
  `Settings_Helper::get_classifieds_label()` via `sprintf()` instead

It also fails on a translated string whose ENTIRE trimmed content is exactly
`Pending`, `Pending Review`, `Approved`, `Active` or `Running` - these are
status-label words that must come from
`includes/Core/class-status-labels.php`, not a re-declared literal. A full
sentence using one of these words as a verb ("Your ad has been approved")
does not match; only a bare status word does.

## Identifier exceptions - never rename these

Per the owner's explicit instruction, code identifiers are not vocabulary
and stay exactly as they are, even when they spell a retired word:

- Hooks and filters (`wbam_pro_classifieds_label`, `wbam_classified_approved`, etc.)
- Option keys (`wbam_pro_classifieds_settings`, `wbam_credit_price_cents`, etc.)
- The `'wallet'` portal tab slug and `?tab=wallet` URL parameter
- REST routes and REST field names (`/classifieds`, `classified_meta`, `listing_type`, etc.)
- CSS classes and `data-*` attributes (`.wbam-credit-banner`, `data-credit-price-cents`, `wbam-post-listing-btn`, etc.)
- Database table and column names (`wbam_classifieds`, `wbam_classified_meta`, `listing_type`, `max_listings`, etc.)
- PHP method/class names (`Credits_Bridge::credit()`, `Revenue_Ledger::SOURCE_CLASSIFIED_LISTING`, etc.)
- Enum/constant values compared in code (`'listing_expired'` reason codes, `Classified::LISTING_TYPES`, etc.)
- The third-party SDK proper noun **"Wbcom Credits"** (library/SDK name - it is a product name, not a vocabulary choice)
- `Settings_Helper::get_classifieds_label()`'s own default fallback values (`__( 'Classified', ... )` / `__( 'Classifieds', ... )` inside the function itself, and the installer's activation-time default) - the function cannot call itself
- The Settings > Classifieds label-editor field's own placeholder examples (`e.g. "Classified", "Item", "Listing"`) - this field is where a site *chooses* one of those words as their label; showing them as examples is the correct UI, not a violation
- Verb usage of "list/listing" unrelated to the classifieds domain (e.g. "a page listing your ad packages", describing a Packages page) - not a hit

## Free plugin scope

The Free plugin has no classifieds module. Its own upsell (`class-upgrade-pro.php`)
and help (`class-help-docs.php`) screens describe the Pro-only classifieds
feature by its generic name ("Classifieds Marketplace", "Classified
Listings"). Free cannot call Pro's `Settings_Helper` (the Free/Pro contract:
Free never depends on Pro being installed) and has no way to know a site's
chosen label before Pro is even active, so the generic name is correct
there and is excluded from the CI guard for those two files specifically.

## Running the guard

```bash
bash bin/check-retired-vocab-in-i18n.sh <dir> [<dir> ...]
```

Exits 0 clean, 1 on any hit (prints `file:line: reason: snippet`). Free's
`bin/check-retired-vocab-in-i18n.sh` is a thin delegator to Pro's canonical
copy (same pattern as `composer arch-checks`) - one script, one allowlist,
shared by both plugins. Wired into `bin/architecture-checks.sh` (Pro) /
`composer arch-checks` (Free) so it runs on every release.
