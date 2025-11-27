# WB Ad Manager - Developer Documentation

Technical reference for developers extending WB Ad Manager.

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Namespace Structure](#namespace-structure)
3. [Custom Post Type](#custom-post-type)
4. [Hooks & Filters](#hooks--filters)
5. [Classes Reference](#classes-reference)
6. [Creating Custom Placements](#creating-custom-placements)
7. [Creating Custom Ad Types](#creating-custom-ad-types)
8. [Geo Targeting API](#geo-targeting-api)
9. [Content Analyzer API](#content-analyzer-api)
10. [Testing](#testing)

---

## Architecture Overview

WB Ad Manager uses a modular architecture with the following components:

```
wb-ad-manager/
├── wb-ad-manager.php          # Main plugin file
├── includes/
│   ├── Core/
│   │   ├── trait-singleton.php    # Singleton pattern
│   │   └── class-plugin.php       # Main plugin class
│   ├── Admin/
│   │   ├── class-admin.php        # Admin functionality
│   │   ├── class-settings.php     # Settings page
│   │   └── class-display-options.php
│   ├── Frontend/
│   │   └── class-frontend.php     # Frontend rendering
│   └── Modules/
│       ├── AdTypes/               # Ad type implementations
│       ├── Placements/            # Placement implementations
│       ├── Targeting/             # Targeting engine
│       ├── GeoTargeting/          # Geo location
│       ├── BuddyPress/            # BP integration
│       └── bbPress/               # bbPress integration
├── assets/
│   ├── css/
│   └── js/
└── tests/
    ├── bootstrap.php
    ├── mocks/
    └── unit/
```

---

## Namespace Structure

All classes use the `WBAM` root namespace:

```php
WBAM\Core\Plugin
WBAM\Core\Singleton
WBAM\Admin\Admin
WBAM\Admin\Settings
WBAM\Frontend\Frontend
WBAM\Modules\AdTypes\Image_Ad
WBAM\Modules\AdTypes\Rich_Content_Ad
WBAM\Modules\AdTypes\Code_Ad
WBAM\Modules\Placements\Placement_Engine
WBAM\Modules\Placements\Placement_Interface
WBAM\Modules\Targeting\Targeting_Engine
WBAM\Modules\Targeting\Content_Analyzer
WBAM\Modules\GeoTargeting\Geo_Engine
WBAM\Modules\BuddyPress\BP_Module
WBAM\Modules\bbPress\bbPress_Module
```

---

## Custom Post Type

### Post Type: `wbam-ad`

```php
// Get all published ads
$ads = get_posts( array(
    'post_type'   => 'wbam-ad',
    'post_status' => 'publish',
    'numberposts' => -1,
) );
```

### Meta Keys

| Meta Key | Type | Description |
|----------|------|-------------|
| `_wbam_ad_type` | string | `image`, `rich_content`, or `code` |
| `_wbam_placement` | string | Placement ID |
| `_wbam_image_url` | string | Image ad URL |
| `_wbam_image_link` | string | Click destination |
| `_wbam_image_alt` | string | Alt text |
| `_wbam_image_target` | string | `_self` or `_blank` |
| `_wbam_rich_content` | string | HTML content |
| `_wbam_code` | string | Raw HTML/JS code |
| `_wbam_priority` | int | 1-10 priority |
| `_wbam_session_limit` | int | Max per session |
| `_wbam_display_rules` | array | Targeting rules |
| `_wbam_visitor_conditions` | array | Visitor targeting |
| `_wbam_schedule` | array | Scheduling options |
| `_wbam_geo_targeting` | array | Geo targeting rules |

---

## Hooks & Filters

### Actions

#### `wbam_before_ad_output`

Fires before an ad is rendered.

```php
add_action( 'wbam_before_ad_output', function( $ad_id, $placement ) {
    // Track impression, add wrapper, etc.
}, 10, 2 );
```

#### `wbam_after_ad_output`

Fires after an ad is rendered.

```php
add_action( 'wbam_after_ad_output', function( $ad_id, $placement ) {
    // Close wrapper, analytics, etc.
}, 10, 2 );
```

#### `wbam_ad_displayed`

Fires when an ad is displayed (for tracking).

```php
add_action( 'wbam_ad_displayed', function( $ad_id, $placement, $context ) {
    // Log impression
}, 10, 3 );
```

#### `wbam_placement_registered`

Fires when a placement is registered.

```php
add_action( 'wbam_placement_registered', function( $placement_id, $placement_instance ) {
    // Custom initialization
}, 10, 2 );
```

### Filters

#### `wbam_ad_output`

Filter the final ad HTML output.

```php
add_filter( 'wbam_ad_output', function( $output, $ad_id, $placement ) {
    return '<div class="custom-wrapper">' . $output . '</div>';
}, 10, 3 );
```

#### `wbam_should_display_ad`

Control whether an ad should display.

```php
add_filter( 'wbam_should_display_ad', function( $should_display, $ad_id, $context ) {
    if ( is_page( 'no-ads' ) ) {
        return false;
    }
    return $should_display;
}, 10, 3 );
```

#### `wbam_get_ads_for_placement`

Filter ads for a specific placement.

```php
add_filter( 'wbam_get_ads_for_placement', function( $ads, $placement_id ) {
    // Filter or reorder ads
    return $ads;
}, 10, 2 );
```

#### `wbam_targeting_rules`

Modify targeting rules for an ad.

```php
add_filter( 'wbam_targeting_rules', function( $rules, $ad_id ) {
    // Add custom rules
    return $rules;
}, 10, 2 );
```

#### `wbam_geo_location`

Filter detected geo location.

```php
add_filter( 'wbam_geo_location', function( $location, $ip ) {
    // Override or enhance location data
    return $location;
}, 10, 2 );
```

#### `wbam_content_analysis`

Filter content analysis results.

```php
add_filter( 'wbam_content_analysis', function( $analysis, $content, $post_id ) {
    // Add custom metrics
    $analysis['custom_metric'] = my_custom_analysis( $content );
    return $analysis;
}, 10, 3 );
```

#### `wbam_placements`

Filter available placements.

```php
add_filter( 'wbam_placements', function( $placements ) {
    // Add or remove placements
    return $placements;
} );
```

#### `wbam_ad_wrapper_classes`

Filter CSS classes on ad wrapper.

```php
add_filter( 'wbam_ad_wrapper_classes', function( $classes, $ad_id, $placement ) {
    $classes[] = 'my-custom-class';
    return $classes;
}, 10, 3 );
```

---

## Classes Reference

### Singleton Trait

All major classes use the Singleton pattern:

```php
use WBAM\Core\Singleton;

class My_Class {
    use Singleton;

    // Your code here
}

// Usage
$instance = My_Class::get_instance();
```

### Placement Engine

```php
use WBAM\Modules\Placements\Placement_Engine;

$engine = Placement_Engine::get_instance();

// Get all placements
$placements = $engine->get_placements();

// Get ads for a placement
$ads = $engine->get_ads_for_placement( 'after_content' );

// Render a placement
$engine->render_placement( 'after_content' );
```

### Targeting Engine

```php
use WBAM\Modules\Targeting\Targeting_Engine;

$targeting = Targeting_Engine::get_instance();

// Check if ad should display
$should_display = $targeting->should_display( $ad_id );

// Check specific rules
$matches = $targeting->check_display_rules( $ad_id );
$visitor_match = $targeting->check_visitor_conditions( $ad_id );
$schedule_match = $targeting->check_schedule( $ad_id );
```

### Content Analyzer

```php
use WBAM\Modules\Targeting\Content_Analyzer;

$analyzer = Content_Analyzer::get_instance();

// Analyze content
$analysis = $analyzer->analyze( $content, $post_id );
/*
Returns:
[
    'character_count' => 1500,
    'word_count'      => 250,
    'paragraph_count' => 5,
    'heading_count'   => ['h1' => 1, 'h2' => 3, ...],
    'image_count'     => 2,
    'link_count'      => 5,
    'reading_time'    => 2,
    'content_length'  => 'medium',
]
*/

// Get suggested ad positions
$positions = $analyzer->get_suggested_positions( $content );

// Check content requirements
$meets_reqs = $analyzer->meets_requirements( $content, array(
    'min_words'      => 100,
    'min_paragraphs' => 3,
) );
```

### Geo Engine

```php
use WBAM\Modules\GeoTargeting\Geo_Engine;

$geo = Geo_Engine::get_instance();

// Get visitor location
$location = $geo->get_visitor_location();
/*
Returns:
[
    'country'      => 'United States',
    'country_code' => 'US',
    'region'       => 'California',
    'city'         => 'San Francisco',
    'source'       => 'ip',
    'provider'     => 'ip-api',
]
*/

// Check if ad matches geo targeting
$matches = $geo->matches_targeting( $ad_id );

// Get countries list
$countries = $geo->get_countries_list();

// Test a provider
$result = $geo->test_provider( 'ip-api', '8.8.8.8' );
```

---

## Creating Custom Placements

### Step 1: Create Placement Class

```php
namespace WBAM\Modules\Placements;

class My_Custom_Placement implements Placement_Interface {

    /**
     * Get placement ID.
     */
    public function get_id() {
        return 'my_custom_placement';
    }

    /**
     * Get placement label.
     */
    public function get_label() {
        return __( 'My Custom Placement', 'my-plugin' );
    }

    /**
     * Get placement group.
     */
    public function get_group() {
        return 'custom';
    }

    /**
     * Initialize hooks.
     */
    public function init() {
        add_action( 'my_custom_hook', array( $this, 'render' ) );
    }

    /**
     * Render ads for this placement.
     */
    public function render() {
        $engine = Placement_Engine::get_instance();
        $engine->render_placement( $this->get_id() );
    }

    /**
     * Get placement options for metabox.
     */
    public function get_options() {
        return array(
            'custom_option' => array(
                'label' => __( 'Custom Option', 'my-plugin' ),
                'type'  => 'text',
            ),
        );
    }
}
```

### Step 2: Register Placement

```php
add_action( 'wbam_register_placements', function( $engine ) {
    $engine->register_placement( new My_Custom_Placement() );
} );
```

---

## Creating Custom Ad Types

### Step 1: Create Ad Type Class

```php
namespace WBAM\Modules\AdTypes;

class My_Ad_Type implements Ad_Type_Interface {

    /**
     * Get ad type ID.
     */
    public function get_id() {
        return 'my_ad_type';
    }

    /**
     * Get ad type label.
     */
    public function get_label() {
        return __( 'My Ad Type', 'my-plugin' );
    }

    /**
     * Render metabox fields.
     */
    public function render_metabox( $post ) {
        $value = get_post_meta( $post->ID, '_wbam_my_field', true );
        ?>
        <label for="wbam_my_field"><?php esc_html_e( 'My Field', 'my-plugin' ); ?></label>
        <input type="text" id="wbam_my_field" name="wbam_my_field" value="<?php echo esc_attr( $value ); ?>">
        <?php
    }

    /**
     * Save metabox data.
     */
    public function save_metabox( $post_id ) {
        if ( isset( $_POST['wbam_my_field'] ) ) {
            update_post_meta( $post_id, '_wbam_my_field', sanitize_text_field( $_POST['wbam_my_field'] ) );
        }
    }

    /**
     * Render ad output.
     */
    public function render( $ad_id ) {
        $value = get_post_meta( $ad_id, '_wbam_my_field', true );
        return '<div class="wbam-my-ad-type">' . esc_html( $value ) . '</div>';
    }
}
```

### Step 2: Register Ad Type

```php
add_action( 'wbam_register_ad_types', function( $manager ) {
    $manager->register_ad_type( new My_Ad_Type() );
} );
```

---

## Geo Targeting API

### Available Providers

| Provider | ID | Requires Key | Rate Limit |
|----------|-----|--------------|------------|
| ip-api.com | `ip-api` | No | 45/min |
| ipinfo.io | `ipinfo` | Yes | 50K/month |
| ipapi.co | `ipapi-co` | No | 1K/day |

### Adding Custom Provider

```php
add_filter( 'wbam_geo_providers', function( $providers ) {
    $providers['my_provider'] = array(
        'name'         => 'My Provider',
        'requires_key' => true,
        'limit'        => '100K/month',
        'callback'     => 'my_geo_provider_callback',
    );
    return $providers;
} );

function my_geo_provider_callback( $ip, $settings ) {
    // Query your provider
    $response = wp_remote_get( "https://my-provider.com/api/{$ip}" );

    if ( is_wp_error( $response ) ) {
        return array();
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );

    return array(
        'country'      => $data['country_name'],
        'country_code' => $data['country_code'],
        'region'       => $data['region'],
        'city'         => $data['city'],
        'source'       => 'ip',
    );
}
```

---

## Content Analyzer API

### Metrics Available

| Metric | Type | Description |
|--------|------|-------------|
| `character_count` | int | Total characters (tags stripped) |
| `word_count` | int | Total words |
| `paragraph_count` | int | Number of `<p>` tags |
| `heading_count` | array | Count per heading level + total |
| `image_count` | int | Number of `<img>` tags |
| `link_count` | int | Number of `<a>` tags |
| `reading_time` | int | Estimated minutes at 200 wpm |
| `content_length` | string | `short`, `medium`, `long`, `very_long` |

### Content Length Categories

| Category | Word Count |
|----------|------------|
| short | < 300 |
| medium | 300-999 |
| long | 1000-1999 |
| very_long | 2000+ |

### Adding Custom Metrics

```php
add_filter( 'wbam_content_analysis', function( $analysis, $content, $post_id ) {
    // Add sentiment analysis
    $analysis['sentiment'] = my_sentiment_analysis( $content );

    // Add keyword density
    $analysis['keyword_density'] = my_keyword_analysis( $content );

    return $analysis;
}, 10, 3 );
```

---

## Testing

### Running Tests

```bash
# From plugin directory
cd wp-content/plugins/wb-ad-manager

# Run all tests
./vendor/bin/phpunit

# Run specific test
./vendor/bin/phpunit tests/unit/ContentAnalyzerTest.php

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage/
```

### Writing Tests

```php
namespace WBAM\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WBAM\Modules\Targeting\Content_Analyzer;

class MyTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        // Reset singletons, mock data, etc.
    }

    public function test_something() {
        $this->assertTrue( true );
    }
}
```

### Mock WordPress Functions

The plugin includes mock WordPress functions for standalone testing in `tests/mocks/wp-functions.php`.

Available mocks:
- `wp_parse_args()`
- `sanitize_text_field()`
- `sanitize_key()`
- `absint()`
- `get_option()` / `update_option()`
- `get_post_meta()`
- `get_transient()` / `set_transient()` / `delete_transient()`
- `add_action()` / `add_filter()` / `do_action()` / `apply_filters()`
- `is_admin()`
- `current_time()`
- `esc_html()` / `esc_attr()` / `esc_html__()`

---

## Constants

| Constant | Description |
|----------|-------------|
| `WBAM_VERSION` | Plugin version |
| `WBAM_PATH` | Plugin directory path |
| `WBAM_URL` | Plugin URL |
| `WBAM_TESTING` | True during PHPUnit tests |

---

## Database Queries

The plugin uses the standard WordPress `WP_Query` for retrieving ads. For performance, enable query caching in Settings.

```php
// Example: Get all ads for a placement
$query = new WP_Query( array(
    'post_type'      => 'wbam-ad',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'meta_query'     => array(
        array(
            'key'   => '_wbam_placement',
            'value' => 'after_content',
        ),
    ),
    'orderby'        => 'meta_value_num',
    'meta_key'       => '_wbam_priority',
    'order'          => 'DESC',
) );
```

---

## Best Practices

1. **Use Filters**: Always use filters to modify plugin behavior
2. **Cache Results**: Use transients for expensive operations
3. **Sanitize Input**: Always sanitize user input
4. **Escape Output**: Always escape output
5. **Use Interfaces**: Implement provided interfaces for custom extensions
6. **Test Your Code**: Use PHPUnit for automated testing

---

*Last updated: November 2024*
