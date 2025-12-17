# WB Ad Manager - Developer Guide

Complete developer documentation for extending and customizing WB Ad Manager.

---

## Table of Contents

1. [Getting Started](#getting-started)
2. [Architecture Overview](#architecture-overview)
3. [Hooks Reference](#hooks-reference)
4. [Custom Ad Types](#custom-ad-types)
5. [Custom Placements](#custom-placements)
6. [REST API](#rest-api)
7. [Database Schema](#database-schema)
8. [Code Examples](#code-examples)

---

## Getting Started

### Requirements
- WordPress 5.8+
- PHP 7.4+

### Plugin Structure

```
wb-ads-rotator-with-split-test/
├── wb-ads-rotator-with-split-test.php  # Main plugin file
├── includes/
│   ├── Core/                           # Core functionality
│   │   ├── class-plugin.php            # Main plugin class
│   │   ├── class-installer.php         # Database setup
│   │   ├── class-cpt.php               # Custom post type
│   │   └── trait-singleton.php         # Singleton pattern
│   │
│   ├── Admin/                          # Admin functionality
│   │   ├── class-admin.php             # Metaboxes, columns
│   │   ├── class-settings.php          # Settings page
│   │   └── class-help-docs.php         # Help documentation
│   │
│   ├── Frontend/                       # Frontend functionality
│   │   └── class-frontend.php          # Assets, tracking
│   │
│   └── Modules/                        # Feature modules
│       ├── AdTypes/                    # Ad type handlers
│       ├── Placements/                 # Placement handlers
│       ├── Targeting/                  # Targeting engine
│       ├── GeoTargeting/               # Geo-location
│       └── Links/                      # Link management
│
├── assets/                             # CSS/JS files
├── templates/                          # Template files
└── languages/                          # Translation files
```

### Constants

```php
// Plugin version
WBAM_VERSION

// Plugin file path
WBAM_FILE

// Plugin directory path
WBAM_PATH

// Plugin URL
WBAM_URL
```

---

## Architecture Overview

### Singleton Pattern

All main classes use the Singleton pattern:

```php
use WBAM\Core\Singleton;

class My_Class {
    use Singleton;

    public function init() {
        // Initialization code
    }
}

// Usage
My_Class::get_instance()->init();
```

### Module Loading

Modules are loaded in the main plugin class:

```php
// Hook into module initialization
add_action( 'wbam_init', function() {
    // Your initialization code
}, 10 );
```

---

## Hooks Reference

### Actions

#### Ad Lifecycle

```php
/**
 * Fires when an ad impression is recorded.
 *
 * @since 2.3.0
 * @param int    $ad_id     Ad post ID.
 * @param string $placement Placement ID where ad was displayed.
 */
do_action( 'wbam_ad_impression', $ad_id, $placement );

/**
 * Fires when an ad is clicked.
 *
 * @since 1.0.0
 * @param int    $ad_id     Ad post ID.
 * @param string $placement Placement ID.
 */
do_action( 'wbam_ad_clicked', $ad_id, $placement );

/**
 * Fires after ad data is saved.
 *
 * @since 1.0.0
 * @param int   $post_id  Ad post ID.
 * @param array $ad_data  Saved ad data.
 */
do_action( 'wbam_ad_saved', $post_id, $ad_data );
```

#### Email Capture

```php
/**
 * Fires before processing email capture submission.
 *
 * @since 2.2.0
 * @param string $email  Subscriber email.
 * @param string $name   Subscriber name.
 * @param int    $ad_id  Ad ID.
 * @param array  $_POST  Raw POST data.
 */
do_action( 'wbam_email_form_submission_before', $email, $name, $ad_id, $_POST );

/**
 * Fires when an email is captured (integrate with email services here).
 *
 * @since 2.2.0
 * @param string $email Subscriber email.
 * @param string $name  Subscriber name.
 * @param int    $ad_id Ad ID.
 */
do_action( 'wbam_email_captured', $email, $name, $ad_id );

/**
 * Fires after successful email capture submission.
 *
 * @since 2.2.0
 * @param string $email Subscriber email.
 * @param string $name  Subscriber name.
 * @param int    $ad_id Ad ID.
 */
do_action( 'wbam_email_form_submission_after', $email, $name, $ad_id );
```

#### Link Partnerships

```php
/**
 * Fires when a partnership inquiry is submitted.
 *
 * @since 2.3.0
 * @param int   $partnership_id Partnership record ID.
 * @param array $data           Submitted data.
 */
do_action( 'wbam_partnership_submitted', $partnership_id, $data );

/**
 * Fires when partnership status changes.
 *
 * @since 2.3.0
 * @param int    $partnership_id Partnership ID.
 * @param string $new_status     New status.
 * @param string $old_status     Previous status.
 */
do_action( 'wbam_partnership_status_changed', $partnership_id, $new_status, $old_status );
```

#### System

```php
/**
 * Fires after placements are initialized.
 *
 * @since 1.0.0
 * @param Placement_Engine $engine Placement engine instance.
 */
do_action( 'wbam_placements_init', $engine );

/**
 * Fires during ad type registration.
 *
 * @since 1.0.0
 * @param Placement_Engine $engine Placement engine instance.
 */
do_action( 'wbam_register_ad_types', $engine );

/**
 * Fires during placement registration.
 *
 * @since 1.0.0
 * @param Placement_Engine $engine Placement engine instance.
 */
do_action( 'wbam_register_placements', $engine );
```

### Filters

#### Ad Display

```php
/**
 * Filter whether an ad should be displayed.
 *
 * @since 1.0.0
 * @param bool $should_display Whether to display the ad.
 * @param int  $ad_id          Ad post ID.
 */
$should_display = apply_filters( 'wbam_should_display_ad', $should_display, $ad_id );

/**
 * Filter display rules before evaluation.
 *
 * @since 2.3.0
 * @param array $rules Display rules array.
 * @param int   $ad_id Ad post ID.
 */
$rules = apply_filters( 'wbam_ad_display_rules', $rules, $ad_id );

/**
 * Filter ads returned for a placement.
 *
 * @since 2.3.0
 * @param array  $ad_ids       Array of ad IDs that passed targeting.
 * @param string $placement_id Placement ID.
 * @param array  $original_ids Original array before targeting.
 */
$ad_ids = apply_filters( 'wbam_ads_for_placement', $ad_ids, $placement_id, $original_ids );

/**
 * Filter the final ad output HTML.
 *
 * @since 1.0.0
 * @param string $output    Ad output HTML.
 * @param int    $ad_id     Ad post ID.
 * @param string $placement Placement ID.
 */
$output = apply_filters( 'wbam_ad_output', $output, $ad_id, $placement );
```

#### Ad Data

```php
/**
 * Filter ad data before saving.
 *
 * @since 2.3.0
 * @param array $ad_data Ad data array.
 * @param int   $post_id Ad post ID.
 */
$ad_data = apply_filters( 'wbam_ad_data_before_save', $ad_data, $post_id );

/**
 * Filter ad data after retrieval.
 *
 * @since 1.0.0
 * @param array $ad_data Ad data array.
 * @param int   $ad_id   Ad post ID.
 */
$ad_data = apply_filters( 'wbam_ad_data', $ad_data, $ad_id );
```

#### Email Capture

```php
/**
 * Filter email capture form validation.
 *
 * Return WP_Error to fail validation.
 *
 * @since 2.2.0
 * @param true|WP_Error $valid True if valid, WP_Error to fail.
 * @param string        $email Subscriber email.
 * @param string        $name  Subscriber name.
 * @param int           $ad_id Ad ID.
 * @param array         $_POST Raw POST data.
 */
$valid = apply_filters( 'wbam_email_form_validation', true, $email, $name, $ad_id, $_POST );

/**
 * Filter the email capture success message.
 *
 * @since 2.2.0
 * @param string $message Success message.
 * @param string $email   Subscriber email.
 * @param int    $ad_id   Ad ID.
 */
$message = apply_filters( 'wbam_email_capture_success_message', $message, $email, $ad_id );
```

#### Links

```php
/**
 * Filter link output HTML.
 *
 * @since 2.0.0
 * @param string $html    Link HTML.
 * @param array  $link    Link data.
 * @param array  $options Rendering options.
 */
$html = apply_filters( 'wbam_link_output', $html, $link, $options );

/**
 * Filter link redirect URL.
 *
 * @since 2.0.0
 * @param string $url     Destination URL.
 * @param int    $link_id Link post ID.
 */
$url = apply_filters( 'wbam_link_redirect_url', $url, $link_id );

/**
 * Filter link redirect type (301, 302, etc).
 *
 * @since 2.3.0
 * @param string $type Redirect type.
 * @param object $link Link object.
 */
$type = apply_filters( 'wbam_link_redirect_type', $type, $link );

/**
 * Filter link data before saving.
 *
 * @since 2.3.0
 * @param array $data    Link data.
 * @param int   $link_id Link ID.
 * @param array $_POST   Raw POST data.
 */
$data = apply_filters( 'wbam_link_save_data', $data, $link_id, $_POST );

/**
 * Fires when a link is clicked.
 *
 * @since 2.0.0
 * @param int $link_id Link ID.
 */
do_action( 'wbam_link_clicked', $link_id );

/**
 * Fires before link redirect.
 *
 * @since 2.3.0
 * @param object $link        Link object.
 * @param string $destination Destination URL.
 */
do_action( 'wbam_before_link_redirect', $link, $destination );

/**
 * Fires when a link is created.
 *
 * @since 2.3.0
 * @param int   $link_id Link ID.
 * @param array $data    Link data.
 */
do_action( 'wbam_link_created', $link_id, $data );

/**
 * Fires when a link is updated.
 *
 * @since 2.3.0
 * @param int   $link_id Link ID.
 * @param array $data    Link data.
 */
do_action( 'wbam_link_updated', $link_id, $data );

/**
 * Fires when a link is deleted.
 *
 * @since 2.3.0
 * @param int $link_id Link ID.
 */
do_action( 'wbam_link_deleted', $link_id );
```

#### Link Partnerships

```php
/**
 * Fires when a partnership inquiry is submitted.
 *
 * @since 2.3.0
 * @param object $partnership Partnership object.
 * @param array  $data        Submitted data.
 */
do_action( 'wbam_partnership_form_submission_after', $partnership, $data );

/**
 * Fires when partnership is created.
 *
 * @since 2.3.0
 * @param object $partnership Partnership object.
 */
do_action( 'wbam_partnership_created', $partnership );

/**
 * Fires when partnership is accepted.
 *
 * @since 2.3.0
 * @param object $partnership Partnership object.
 */
do_action( 'wbam_partnership_accepted', $partnership );

/**
 * Fires when partnership is rejected.
 *
 * @since 2.3.0
 * @param object $partnership Partnership object.
 */
do_action( 'wbam_partnership_rejected', $partnership );

/**
 * Filter partnership form data before saving.
 *
 * @since 2.3.0
 * @param array $data  Partnership data.
 * @param array $_POST Raw POST data.
 */
$data = apply_filters( 'wbam_partnership_form_data', $data, $_POST );

/**
 * Filter partnership form validation.
 *
 * @since 2.3.0
 * @param bool  $valid Validation result.
 * @param array $data  Partnership data.
 * @param array $_POST Raw POST data.
 */
$valid = apply_filters( 'wbam_partnership_form_validation', true, $data, $_POST );

/**
 * Filter partnership types available in form.
 *
 * @since 2.3.0
 * @param array $types Array of partnership types.
 */
$types = apply_filters( 'wbam_partnership_form_types', $types );

/**
 * Filter whether to send admin notification.
 *
 * @since 2.3.0
 * @param bool   $send        Whether to send.
 * @param object $partnership Partnership object.
 */
$send = apply_filters( 'wbam_send_partnership_admin_notification', true, $partnership );
```

---

## Custom Ad Types

### Creating a Custom Ad Type

```php
use WBAM\Modules\AdTypes\Ad_Type_Interface;

class Video_Ad implements Ad_Type_Interface {

    /**
     * Get ad type ID.
     */
    public function get_id() {
        return 'video';
    }

    /**
     * Get ad type label.
     */
    public function get_label() {
        return __( 'Video Ad', 'my-plugin' );
    }

    /**
     * Get ad type description.
     */
    public function get_description() {
        return __( 'Display video advertisements.', 'my-plugin' );
    }

    /**
     * Get ad type icon.
     */
    public function get_icon() {
        return 'dashicons-video-alt3';
    }

    /**
     * Render the admin form fields.
     */
    public function render_fields( $ad_id ) {
        $data = get_post_meta( $ad_id, '_wbam_ad_data', true );
        $video_url = isset( $data['video_url'] ) ? $data['video_url'] : '';
        ?>
        <p>
            <label for="wbam_video_url"><?php esc_html_e( 'Video URL', 'my-plugin' ); ?></label>
            <input type="url" id="wbam_video_url" name="wbam_ad_data[video_url]"
                   value="<?php echo esc_url( $video_url ); ?>" class="widefat">
        </p>
        <?php
    }

    /**
     * Sanitize ad data before saving.
     */
    public function sanitize( $data ) {
        if ( isset( $data['video_url'] ) ) {
            $data['video_url'] = esc_url_raw( $data['video_url'] );
        }
        return $data;
    }

    /**
     * Render the ad output.
     */
    public function render( $ad_id, $options = array() ) {
        $data = get_post_meta( $ad_id, '_wbam_ad_data', true );
        $video_url = isset( $data['video_url'] ) ? $data['video_url'] : '';

        if ( empty( $video_url ) ) {
            return '';
        }

        ob_start();
        ?>
        <div class="wbam-ad wbam-video-ad" data-ad-id="<?php echo esc_attr( $ad_id ); ?>">
            <video src="<?php echo esc_url( $video_url ); ?>" controls></video>
        </div>
        <?php
        return ob_get_clean();
    }
}
```

### Registering the Ad Type

```php
add_action( 'wbam_register_ad_types', function( $engine ) {
    $engine->register_ad_type( new Video_Ad() );
} );
```

---

## Custom Placements

### Creating a Custom Placement

```php
use WBAM\Modules\Placements\Placement_Interface;

class Custom_Placement implements Placement_Interface {

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
     * Get placement description.
     */
    public function get_description() {
        return __( 'Displays ads in a custom location.', 'my-plugin' );
    }

    /**
     * Get placement group for admin UI.
     */
    public function get_group() {
        return 'custom';
    }

    /**
     * Check if placement is available.
     */
    public function is_available() {
        return true; // Add conditions if needed
    }

    /**
     * Register the placement hooks.
     */
    public function register() {
        add_action( 'my_custom_hook', array( $this, 'display' ) );
    }

    /**
     * Display ads for this placement.
     */
    public function display() {
        $engine = \WBAM\Modules\Placements\Placement_Engine::get_instance();
        $ads = $engine->get_ads_for_placement( $this->get_id() );

        if ( empty( $ads ) ) {
            return;
        }

        // Pick random ad or implement rotation
        $ad_id = $ads[ array_rand( $ads ) ];

        echo $engine->render_ad( $ad_id, array( 'placement' => $this->get_id() ) );
    }
}
```

### Registering the Placement

```php
add_action( 'wbam_register_placements', function( $engine ) {
    $engine->register_placement( new Custom_Placement() );
} );
```

---

## REST API

### Available Endpoints

The plugin registers endpoints under the `wbam/v1` namespace.

#### Get Ad

```
GET /wp-json/wbam/v1/ads/{id}
```

**Response:**
```json
{
    "id": 123,
    "title": "My Ad",
    "type": "image",
    "enabled": true,
    "placements": ["header", "sidebar"],
    "data": {
        "image_url": "https://example.com/ad.jpg",
        "destination_url": "https://example.com"
    }
}
```

#### Track Impression (Internal)

```
POST /wp-json/wbam/v1/track/impression
```

**Body:**
```json
{
    "ad_id": 123,
    "placement": "header"
}
```

### Adding Custom Endpoints

```php
add_action( 'rest_api_init', function() {
    register_rest_route( 'wbam/v1', '/custom-endpoint', array(
        'methods'             => 'GET',
        'callback'            => 'my_custom_endpoint_callback',
        'permission_callback' => function() {
            return current_user_can( 'manage_options' );
        },
    ) );
} );

function my_custom_endpoint_callback( $request ) {
    return new WP_REST_Response( array( 'message' => 'Success' ), 200 );
}
```

---

## Database Schema

### Custom Tables

#### wbam_analytics

Stores impression and click events.

```sql
CREATE TABLE {prefix}wbam_analytics (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    ad_id bigint(20) UNSIGNED NOT NULL,
    event_type varchar(20) NOT NULL,           -- 'impression' or 'click'
    placement varchar(100) DEFAULT '',
    visitor_hash varchar(64) DEFAULT '',       -- Anonymized visitor ID
    ip_address varchar(45) DEFAULT '',
    user_agent text,
    referer text,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ad_id (ad_id),
    KEY event_type (event_type),
    KEY created_at (created_at)
);
```

#### wbam_email_submissions

Stores email capture form submissions.

```sql
CREATE TABLE {prefix}wbam_email_submissions (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    ad_id bigint(20) UNSIGNED NOT NULL,
    email varchar(255) NOT NULL,
    name varchar(200) DEFAULT '',
    ip_address varchar(45) DEFAULT '',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ad_id (ad_id),
    KEY email (email)
);
```

#### wbam_link_partnerships

Stores link partnership inquiries.

```sql
CREATE TABLE {prefix}wbam_link_partnerships (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    email varchar(255) NOT NULL,
    website_url varchar(2000) NOT NULL,
    partnership_type varchar(30) NOT NULL DEFAULT 'paid_link',
    target_post_id bigint(20) UNSIGNED DEFAULT NULL,
    anchor_text varchar(255) DEFAULT NULL,
    message text,
    budget_min decimal(10,2) DEFAULT NULL,
    budget_max decimal(10,2) DEFAULT NULL,
    status varchar(20) DEFAULT 'pending',
    admin_notes text,
    ip_address varchar(45) DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    responded_at datetime DEFAULT NULL,
    PRIMARY KEY (id),
    KEY status (status),
    KEY partnership_type (partnership_type),
    KEY email (email),
    KEY created_at (created_at)
);
```

### Post Meta Keys

| Meta Key | Description |
|----------|-------------|
| `_wbam_enabled` | Ad enabled status (1/0) |
| `_wbam_ad_data` | Serialized ad data array |
| `_wbam_placements` | Serialized array of placement IDs |
| `_wbam_schedule` | Serialized schedule settings |
| `_wbam_display_rules` | Serialized display rules |
| `_wbam_visitor_conditions` | Serialized visitor conditions |

---

## Code Examples

### Integrate Email Capture with Mailchimp

```php
add_action( 'wbam_email_captured', function( $email, $name, $ad_id ) {
    $api_key = 'your-mailchimp-api-key';
    $list_id = 'your-list-id';

    $data = array(
        'email_address' => $email,
        'status'        => 'subscribed',
        'merge_fields'  => array(
            'FNAME' => $name,
        ),
    );

    $response = wp_remote_post(
        "https://usX.api.mailchimp.com/3.0/lists/{$list_id}/members",
        array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( 'anystring:' . $api_key ),
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode( $data ),
        )
    );
}, 10, 3 );
```

### Custom Validation for Email Capture

```php
add_filter( 'wbam_email_form_validation', function( $valid, $email, $name, $ad_id, $post_data ) {
    // Block disposable email domains
    $blocked_domains = array( 'tempmail.com', 'throwaway.com' );
    $email_domain = substr( strrchr( $email, '@' ), 1 );

    if ( in_array( $email_domain, $blocked_domains, true ) ) {
        return new WP_Error( 'blocked_domain', __( 'Please use a valid email address.', 'my-plugin' ) );
    }

    return $valid;
}, 10, 5 );
```

### Exclude Ads for Specific Users

```php
add_filter( 'wbam_should_display_ad', function( $should_display, $ad_id ) {
    // Don't show ads to users with premium role
    if ( is_user_logged_in() && current_user_can( 'premium_member' ) ) {
        return false;
    }

    return $should_display;
}, 10, 2 );
```

### Modify Ads for Specific Placement

```php
add_filter( 'wbam_ads_for_placement', function( $ad_ids, $placement_id, $original_ids ) {
    // Limit sidebar to max 2 ads
    if ( 'sidebar' === $placement_id && count( $ad_ids ) > 2 ) {
        return array_slice( $ad_ids, 0, 2 );
    }

    return $ad_ids;
}, 10, 3 );
```

### Add Custom Field to Ad Data

```php
// Add field to admin form
add_action( 'wbam_ad_settings_after', function( $ad_id ) {
    $data = get_post_meta( $ad_id, '_wbam_ad_data', true );
    $sponsor = isset( $data['sponsor'] ) ? $data['sponsor'] : '';
    ?>
    <p>
        <label for="wbam_sponsor"><?php esc_html_e( 'Sponsor Name', 'my-plugin' ); ?></label>
        <input type="text" id="wbam_sponsor" name="wbam_ad_data[sponsor]"
               value="<?php echo esc_attr( $sponsor ); ?>" class="widefat">
    </p>
    <?php
} );

// Modify output to include sponsor
add_filter( 'wbam_ad_output', function( $output, $ad_id, $placement ) {
    $data = get_post_meta( $ad_id, '_wbam_ad_data', true );

    if ( ! empty( $data['sponsor'] ) ) {
        $sponsor_html = sprintf(
            '<div class="wbam-sponsor">%s %s</div>',
            esc_html__( 'Sponsored by', 'my-plugin' ),
            esc_html( $data['sponsor'] )
        );
        $output = $sponsor_html . $output;
    }

    return $output;
}, 10, 3 );
```

### Log All Ad Clicks to External Service

```php
add_action( 'wbam_ad_clicked', function( $ad_id, $placement ) {
    $post = get_post( $ad_id );

    wp_remote_post( 'https://your-analytics-service.com/track', array(
        'body' => array(
            'event'     => 'ad_click',
            'ad_id'     => $ad_id,
            'ad_title'  => $post->post_title,
            'placement' => $placement,
            'timestamp' => current_time( 'mysql' ),
            'url'       => isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '',
        ),
        'blocking' => false, // Non-blocking request
    ) );
}, 10, 2 );
```

---

## Support

For bug reports and feature requests, please use the GitHub issue tracker.

For questions about extending the plugin, check our knowledge base or contact support.

---

*Last updated: December 17, 2024*
