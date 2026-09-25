# WB Ad Manager PRO - Developer Guide

> **PRO feature reference.** This guide documents the [WB Ad Manager Pro](https://wbcomdesigns.com/downloads/wb-ad-manager-pro/) add-on. Developer hooks and APIs here are only available when the Pro plugin is active on top of the free plugin.

Complete developer documentation for extending and customizing WB Ad Manager PRO.

---

## Table of Contents

1. [Getting Started](#getting-started)
2. [Architecture Overview](#architecture-overview)
3. [Hooks Reference](#hooks-reference)
4. [Advertiser API](#advertiser-api)
5. [Campaign API](#campaign-api)
6. [Payment Integration](#payment-integration)
7. [Classifieds API](#classifieds-api)
8. [REST API](#rest-api)
9. [Database Schema](#database-schema)
10. [Code Examples](#code-examples)

---

## Getting Started

### Requirements
- WordPress 5.8+
- PHP 7.4+
- WB Ad Manager (FREE) plugin

### Plugin Structure

```
wb-ad-manager-pro/
├── wb-ad-manager-pro.php              # Main plugin file
├── includes/
│   ├── Core/                          # Core functionality
│   │   ├── class-pro-plugin.php       # Main plugin class
│   │   ├── class-installer.php        # Database setup
│   │   ├── class-pro-admin.php        # Admin menus
│   │   └── class-frontend.php         # Frontend portal
│   │
│   ├── Modules/
│   │   ├── Advertisers/               # Advertiser management
│   │   │   ├── class-advertiser.php
│   │   │   ├── class-advertiser-manager.php
│   │   │   ├── class-advertiser-portal.php
│   │   │   └── class-advertiser-shortcodes.php
│   │   │
│   │   ├── Campaigns/                 # Campaign management
│   │   │   ├── class-campaign.php
│   │   │   ├── class-campaign-manager.php
│   │   │   └── class-pricing-calculator.php
│   │   │
│   │   ├── Payments/                  # Payment processing
│   │   │   ├── class-payment-manager.php
│   │   │   ├── class-stripe-gateway.php
│   │   │   ├── class-paypal-gateway.php
│   │   │   ├── class-razorpay-gateway.php
│   │   │   └── class-woocommerce-gateway.php
│   │   │
│   │   ├── Ads/                       # Ad submissions
│   │   │   ├── class-ad-submission.php
│   │   │   └── class-ad-submission-manager.php
│   │   │
│   │   ├── Classifieds/               # Classifieds module
│   │   │   ├── class-classified.php
│   │   │   ├── class-classified-manager.php
│   │   │   └── class-classified-shortcodes.php
│   │   │
│   │   ├── Analytics/                 # Advanced analytics
│   │   │   ├── class-analytics-tracker.php
│   │   │   └── class-analytics-dashboard.php
│   │   │
│   │   └── Links/                     # PRO link features
│   │       ├── class-links-pro-module.php
│   │       ├── class-link-health-checker.php
│   │       ├── class-keyword-linker.php
│   │       └── class-link-importer.php
│   │
│   └── Admin/                         # Admin list tables
│       ├── class-advertisers-list-table.php
│       ├── class-campaigns-list-table.php
│       └── class-transactions-list-table.php
│
├── templates/                         # Template files
│   ├── portal/                        # Advertiser portal
│   └── classifieds/                   # Classified templates
│
└── assets/                            # CSS/JS files
```

### Constants

```php
// PRO plugin version
WBAM_PRO_VERSION

// PRO plugin file path
WBAM_PRO_FILE

// PRO plugin directory path
WBAM_PRO_PATH

// PRO plugin URL
WBAM_PRO_URL
```

---

## Architecture Overview

### Module Loading

PRO modules extend FREE plugin functionality:

```php
// Hook into PRO initialization
add_action( 'wbam_pro_init', function() {
    // Your PRO initialization code
}, 10 );

// Hook into specific module init
add_action( 'wbam_pro_advertisers_init', function( $manager ) {
    // Advertiser module initialized
}, 10, 1 );
```

### Dependency Check

Always verify FREE plugin is active:

```php
if ( ! defined( 'WBAM_VERSION' ) ) {
    // FREE plugin not active
    return;
}
```

---

## Hooks Reference

### Actions

#### Advertiser Lifecycle

```php
/**
 * Fires when a new advertiser is created.
 *
 * @since 1.0.0
 * @param int   $advertiser_id Advertiser ID.
 * @param array $data          Advertiser data.
 */
do_action( 'wbam_pro_advertiser_created', $advertiser_id, $data );

/**
 * Fires when an advertiser is updated.
 *
 * @since 1.0.0
 * @param int   $advertiser_id Advertiser ID.
 * @param array $data          Updated data.
 * @param array $old_data      Previous data.
 */
do_action( 'wbam_pro_advertiser_updated', $advertiser_id, $data, $old_data );

/**
 * Fires when advertiser status changes.
 *
 * @since 1.0.0
 * @param int    $advertiser_id Advertiser ID.
 * @param string $new_status    New status.
 * @param string $old_status    Previous status.
 */
do_action( 'wbam_pro_advertiser_status_changed', $advertiser_id, $new_status, $old_status );
```

#### Campaign Lifecycle

```php
/**
 * Fires when a campaign is created.
 *
 * @since 1.0.0
 * @param int   $campaign_id Campaign ID.
 * @param array $data        Campaign data.
 */
do_action( 'wbam_pro_campaign_created', $campaign_id, $data );

/**
 * Fires when a campaign is paused.
 *
 * @since 1.0.0
 * @param int    $campaign_id Campaign ID.
 * @param string $reason      Pause reason (manual, budget, schedule).
 */
do_action( 'wbam_pro_campaign_paused', $campaign_id, $reason );

/**
 * Fires when a campaign is resumed.
 *
 * @since 1.0.0
 * @param int $campaign_id Campaign ID.
 */
do_action( 'wbam_pro_campaign_resumed', $campaign_id );

/**
 * Fires when campaign budget is exhausted.
 *
 * @since 1.0.0
 * @param int   $campaign_id Campaign ID.
 * @param float $spent       Total spent amount.
 * @param float $budget      Campaign budget.
 */
do_action( 'wbam_pro_campaign_budget_exhausted', $campaign_id, $spent, $budget );
```

#### Payment Lifecycle

```php
/**
 * Fires after a payment record is created.
 *
 * @since 1.0.0
 * @param array  $payment_data   Payment data.
 * @param string $gateway_id     Gateway identifier.
 * @param float  $amount         Payment amount.
 * @param string $transaction_id Gateway transaction ID.
 */
do_action( 'wbam_pro_payment_created', $payment_data, $gateway_id, $amount, $transaction_id );

/**
 * Fires after payment verification completes.
 *
 * @since 1.0.0
 * @param bool   $verified   Whether payment was verified.
 * @param string $gateway_id Gateway identifier.
 * @param int    $payment_id Internal payment ID.
 * @param array  $data       Verification data.
 */
do_action( 'wbam_pro_payment_verified', $verified, $gateway_id, $payment_id, $data );

/**
 * Fires after webhook processing completes.
 *
 * @since 1.0.0
 * @param array  $result     Processing result.
 * @param string $gateway_id Gateway identifier.
 * @param array  $event      Webhook event data.
 */
do_action( 'wbam_pro_webhook_processed', $result, $gateway_id, $event );

/**
 * Fires when wallet is credited.
 *
 * @since 1.0.0
 * @param int    $advertiser_id Advertiser ID.
 * @param float  $amount        Credit amount.
 * @param float  $new_balance   New wallet balance.
 * @param string $source        Credit source (payment, manual, refund).
 */
do_action( 'wbam_pro_wallet_credited', $advertiser_id, $amount, $new_balance, $source );

/**
 * Fires when wallet is debited.
 *
 * @since 1.0.0
 * @param int    $advertiser_id Advertiser ID.
 * @param float  $amount        Debit amount.
 * @param float  $new_balance   New wallet balance.
 * @param string $reason        Debit reason.
 */
do_action( 'wbam_pro_wallet_debited', $advertiser_id, $amount, $new_balance, $reason );
```

#### Ad Submission Lifecycle

```php
/**
 * Fires when ad submission is created.
 *
 * @since 1.0.0
 * @param int   $submission_id Submission ID.
 * @param int   $advertiser_id Advertiser ID.
 * @param array $data          Submission data.
 */
do_action( 'wbam_pro_ad_submitted', $submission_id, $advertiser_id, $data );

/**
 * Fires when ad submission is approved.
 *
 * @since 1.0.0
 * @param int $submission_id Submission ID.
 * @param int $ad_id         Created ad post ID.
 * @param int $reviewer_id   Admin who approved.
 */
do_action( 'wbam_pro_ad_approved', $submission_id, $ad_id, $reviewer_id );

/**
 * Fires when ad submission is rejected.
 *
 * @since 1.0.0
 * @param int    $submission_id Submission ID.
 * @param string $reason        Rejection reason.
 * @param int    $reviewer_id   Admin who rejected.
 */
do_action( 'wbam_pro_ad_rejected', $submission_id, $reason, $reviewer_id );
```

#### Classifieds Lifecycle

```php
/**
 * Fires when a classified listing is created.
 *
 * @since 1.0.0
 * @param int   $classified_id Classified post ID.
 * @param int   $advertiser_id Advertiser ID.
 * @param array $data          Listing data.
 */
do_action( 'wbam_pro_classified_created', $classified_id, $advertiser_id, $data );

/**
 * Fires when a classified listing expires.
 *
 * @since 1.0.0
 * @param int $classified_id Classified post ID.
 */
do_action( 'wbam_pro_classified_expired', $classified_id );

/**
 * Fires when a classified is bumped.
 *
 * @since 1.0.0
 * @param int $classified_id Classified post ID.
 * @param int $bump_count    Total bump count.
 */
do_action( 'wbam_pro_classified_bumped', $classified_id, $bump_count );

/**
 * Fires when an inquiry is submitted.
 *
 * @since 1.0.0
 * @param int   $inquiry_id    Inquiry ID.
 * @param int   $classified_id Classified post ID.
 * @param array $data          Inquiry data.
 */
do_action( 'wbam_pro_inquiry_submitted', $inquiry_id, $classified_id, $data );

/**
 * Fires when a classified is renewed.
 *
 * @since 1.1.0
 * @param int $classified_id Classified post ID.
 * @param int $days          Days extended.
 */
do_action( 'wbam_classified_renewed', $classified_id, $days );

/**
 * Fires when classified is about to expire (warning sent).
 *
 * @since 1.1.0
 * @param int $classified_id Classified post ID.
 * @param int $days_left     Days until expiration.
 */
do_action( 'wbam_classified_expiring', $classified_id, $days_left );

/**
 * Fires when classified is upgraded (featured, highlighted, etc).
 *
 * @since 1.1.0
 * @param int    $classified_id Classified post ID.
 * @param string $upgrade_type  Type of upgrade.
 * @param array  $upgrade_data  Upgrade details.
 */
do_action( 'wbam_classified_upgraded', $classified_id, $upgrade_type, $upgrade_data );

/**
 * Fires when seller is followed.
 *
 * @since 1.1.0
 * @param int $advertiser_id Advertiser being followed.
 * @param int $follower_id   User who followed.
 */
do_action( 'wbam_seller_followed', $advertiser_id, $follower_id );

/**
 * Fires when seller is unfollowed.
 *
 * @since 1.1.0
 * @param int $advertiser_id Advertiser being unfollowed.
 * @param int $follower_id   User who unfollowed.
 */
do_action( 'wbam_seller_unfollowed', $advertiser_id, $follower_id );

/**
 * Fires when followers are notified of new listing.
 *
 * @since 1.1.0
 * @param int   $classified_id Classified post ID.
 * @param array $follower_ids  Array of notified user IDs.
 */
do_action( 'wbam_followers_notified_new_listing', $classified_id, $follower_ids );
```

#### Link Management (Pro)

```php
/**
 * Fires when Links Pro module initializes.
 *
 * @since 1.0.0
 * @param object $module Links Pro module instance.
 */
do_action( 'wbam_pro_links_module_init', $module );

/**
 * Fires when a post is scanned for links.
 *
 * @since 1.0.0
 * @param int   $post_id     Post ID scanned.
 * @param array $links_found Array of discovered links.
 */
do_action( 'wbam_post_scanned', $post_id, $links_found );

/**
 * Fires when link health check batch completes.
 *
 * @since 1.0.0
 * @param array $results Batch check results.
 */
do_action( 'wbam_health_check_batch_complete', $results );

/**
 * Fires when batch link scan completes.
 *
 * @since 1.0.0
 * @param array $results Scan results.
 */
do_action( 'wbam_batch_scan_complete', $results );

/**
 * Fires on link redirect (for tracking).
 *
 * @since 1.0.0
 * @param int    $link_id     Link ID.
 * @param string $destination Destination URL.
 */
do_action( 'wbam_pro_link_redirect', $link_id, $destination );
```

#### Cron & Scheduled Tasks

```php
/**
 * Fires after daily aggregation completes.
 *
 * @since 1.0.0
 * @param array $stats Aggregation statistics.
 */
do_action( 'wbam_daily_aggregation_complete', $stats );

/**
 * Fires after hourly cleanup completes.
 *
 * @since 1.0.0
 */
do_action( 'wbam_hourly_cleanup_complete' );

/**
 * Fires when classifieds expiration check runs.
 *
 * @since 1.0.0
 * @param array $expired_ids IDs of expired classifieds.
 */
do_action( 'wbam_classifieds_expired', $expired_ids );
```

### Filters

#### Advertiser Filters

```php
/**
 * Filter advertiser capabilities.
 *
 * @since 1.0.0
 * @param array $caps          Capabilities array.
 * @param int   $advertiser_id Advertiser ID.
 */
$caps = apply_filters( 'wbam_pro_advertiser_capabilities', $caps, $advertiser_id );

/**
 * Filter advertiser portal tabs.
 *
 * @since 1.0.0
 * @param array $tabs          Portal tabs.
 * @param int   $advertiser_id Advertiser ID.
 */
$tabs = apply_filters( 'wbam_pro_portal_tabs', $tabs, $advertiser_id );
```

#### Campaign Filters

```php
/**
 * Filter campaign pricing calculation.
 *
 * @since 1.0.0
 * @param float $price       Calculated price.
 * @param array $campaign    Campaign data.
 * @param array $impressions Impression data.
 */
$price = apply_filters( 'wbam_pro_campaign_price', $price, $campaign, $impressions );

/**
 * Filter available placements for package.
 *
 * @since 1.0.0
 * @param array $placements Available placements.
 * @param int   $package_id Package ID.
 */
$placements = apply_filters( 'wbam_pro_package_placements', $placements, $package_id );
```

#### Payment Filters

```php
/**
 * Filter available payment gateways.
 *
 * @since 1.0.0
 * @param array $gateways Available gateways.
 */
$gateways = apply_filters( 'wbam_pro_payment_gateways', $gateways );

/**
 * Filter payment amount before processing.
 *
 * @since 1.0.0
 * @param float  $amount      Payment amount.
 * @param string $gateway_id  Gateway identifier.
 * @param array  $context     Payment context.
 */
$amount = apply_filters( 'wbam_pro_payment_amount', $amount, $gateway_id, $context );
```

#### Classified Filters

```php
/**
 * Filter classified listing output.
 *
 * @since 1.0.0
 * @param string $output        Listing HTML.
 * @param int    $classified_id Classified ID.
 * @param array  $args          Display arguments.
 */
$output = apply_filters( 'wbam_pro_classified_output', $output, $classified_id, $args );

/**
 * Filter classified search query args.
 *
 * @since 1.0.0
 * @param array $args   Query arguments.
 * @param array $search Search parameters.
 */
$args = apply_filters( 'wbam_pro_classified_search_args', $args, $search );

/**
 * Filter classifieds per page.
 *
 * @since 1.0.0
 * @param int $per_page Number of items per page.
 */
$per_page = apply_filters( 'wbam_classifieds_per_page', 12 );

/**
 * Filter classified bump cost.
 *
 * @since 1.1.0
 * @param float $cost          Bump cost.
 * @param int   $classified_id Classified ID.
 */
$cost = apply_filters( 'wbam_classified_bump_cost', 2.00, $classified_id );

/**
 * Filter classified renewal cost.
 *
 * @since 1.1.0
 * @param float $cost          Renewal cost.
 * @param int   $classified_id Classified ID.
 */
$cost = apply_filters( 'wbam_classified_renewal_cost', 5.00, $classified_id );

/**
 * Filter classified renewal days.
 *
 * @since 1.1.0
 * @param int $days Default renewal days.
 */
$days = apply_filters( 'wbam_classified_renewal_days', 30 );

/**
 * Filter classified upgrade prices.
 *
 * @since 1.1.0
 * @param array $prices Array of upgrade prices.
 */
$prices = apply_filters( 'wbam_upgrade_prices', array(
    'featured'    => 5.00,
    'highlighted' => 3.00,
    'urgent'      => 4.00,
    'top'         => 6.00,
    'bump'        => 2.00,
) );

/**
 * Filter max images per classified.
 *
 * @since 1.0.0
 * @param int $max Max images allowed.
 */
$max = apply_filters( 'wbam_classified_max_images', 10 );

/**
 * Filter expiration warning days.
 *
 * @since 1.1.0
 * @param array $days Array of days before expiry to send warnings.
 */
$days = apply_filters( 'wbam_classified_expiration_warning_days', array( 7, 3 ) );
```

#### Link Filters (Pro)

```php
/**
 * Filter affiliate domains for link scanner.
 *
 * @since 1.0.0
 * @param array $domains List of affiliate domains.
 */
$domains = apply_filters( 'wbam_affiliate_domains', array(
    'amazon.com', 'amzn.to', 'shareasale.com', 'commission-junction.com'
) );

/**
 * Filter whether to auto-link a post.
 *
 * @since 1.0.0
 * @param bool $should  Whether to process post.
 * @param int  $post_id Post ID.
 */
$should = apply_filters( 'wbam_pro_should_auto_link_post', true, $post_id );

/**
 * Filter HTML tags excluded from auto-linking.
 *
 * @since 1.0.0
 * @param array $tags Tags to exclude.
 */
$tags = apply_filters( 'wbam_pro_auto_link_excluded_tags', array( 'a', 'script', 'style', 'code', 'pre' ) );

/**
 * Filter click tracking data.
 *
 * @since 1.0.0
 * @param array $data    Click data.
 * @param int   $link_id Link ID.
 */
$data = apply_filters( 'wbam_pro_click_data', $data, $link_id );

/**
 * Filter whether to track a click.
 *
 * @since 1.0.0
 * @param bool $should  Whether to track.
 * @param int  $link_id Link ID.
 */
$should = apply_filters( 'wbam_pro_should_track_click', true, $link_id );
```

#### Wallet Filters

```php
/**
 * Filter minimum deposit amount.
 *
 * @since 1.0.0
 * @param float $amount Minimum amount.
 */
$amount = apply_filters( 'wbam_pro_minimum_deposit', 5.00 );

/**
 * Filter available wallet payment methods.
 *
 * @since 1.0.0
 * @param array $methods Available methods.
 */
$methods = apply_filters( 'wbam_wallet_payment_methods', $methods );
```

#### Portal & Forms Filters

```php
/**
 * Filter ad submission validation.
 *
 * @since 1.0.0
 * @param bool|WP_Error $valid Valid or error.
 * @param array         $data  Submission data.
 */
$valid = apply_filters( 'wbam_pro_ad_submission_validation', true, $data );

/**
 * Filter registration validation.
 *
 * @since 1.0.0
 * @param bool|WP_Error $valid Valid or error.
 * @param array         $data  Registration data.
 */
$valid = apply_filters( 'wbam_pro_registration_validation', true, $data );

/**
 * Filter settings page tabs.
 *
 * @since 1.0.0
 * @param array $tabs Settings tabs.
 */
$tabs = apply_filters( 'wbam_pro_settings_tabs', $tabs );

/**
 * Filter cron jobs.
 *
 * @since 1.0.0
 * @param array $jobs Registered cron jobs.
 */
$jobs = apply_filters( 'wbam_pro_cron_jobs', $jobs );

/**
 * Filter bot detection patterns.
 *
 * @since 1.0.0
 * @param array $patterns Regex patterns to detect bots.
 */
$patterns = apply_filters( 'wbam_bot_patterns', $patterns );
```

#### Admin Tables

```php
/**
 * Filter transaction list table columns.
 *
 * @since 1.1.0
 * @param array $columns Table columns.
 */
$columns = apply_filters( 'wbam_transaction_table_columns', $columns );

/**
 * Filter transaction row actions.
 *
 * @since 1.1.0
 * @param array  $actions Row actions.
 * @param object $item    Transaction item.
 */
$actions = apply_filters( 'wbam_transaction_row_actions', $actions, $item );
```

#### Receipts & Email Templates

```php
/**
 * Fires before receipt template renders.
 *
 * @since 1.1.0
 * @param object $transaction Transaction object.
 * @param object $advertiser  Advertiser object.
 */
do_action( 'wbam_before_receipt', $transaction, $advertiser );

/**
 * Add custom sections to receipt before total.
 *
 * @since 1.1.0
 * @param object $transaction Transaction object.
 * @param object $advertiser  Advertiser object.
 */
do_action( 'wbam_receipt_before_total', $transaction, $advertiser );

/**
 * Filter classified expiring email heading.
 *
 * @since 1.1.0
 * @param string $heading    Email heading text.
 * @param object $classified Classified object.
 * @param int    $days_left  Days until expiration.
 */
$heading = apply_filters( 'wbam_classified_expiring_email_heading', $heading, $classified, $days_left );

/**
 * Filter renewal benefits list in expiring email.
 *
 * @since 1.1.0
 * @param array  $benefits   Array of benefit strings.
 * @param object $classified Classified object.
 */
$benefits = apply_filters( 'wbam_classified_expiring_email_benefits', $benefits, $classified );

/**
 * Add custom content before benefits list in expiring email.
 *
 * @since 1.1.0
 * @param object $classified Classified object.
 * @param int    $days_left  Days until expiration.
 */
do_action( 'wbam_classified_expiring_email_before_benefits', $classified, $days_left );
```

---

## Advertiser API

### Getting Advertiser Data

```php
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;

$manager = Advertiser_Manager::get_instance();

// Get by ID
$advertiser = $manager->get( $advertiser_id );

// Get by user ID
$advertiser = $manager->get_by_user( $user_id );

// Get by email
$advertiser = $manager->get_by_email( 'user@example.com' );

// List all advertisers
$advertisers = $manager->get_all( array(
    'status'   => 'active',
    'per_page' => 20,
    'page'     => 1,
    'orderby'  => 'created_at',
    'order'    => 'DESC',
) );
```

### Creating Advertisers

```php
$advertiser_id = $manager->create( array(
    'user_id'        => $user_id,
    'company_name'   => 'Acme Corp',
    'contact_name'   => 'John Doe',
    'email'          => 'john@acme.com',
    'phone'          => '555-1234',
    'website'        => 'https://acme.com',
    'status'         => 'active',
    'wallet_balance' => 0.00,
) );
```

### Wallet Operations

```php
// Credit wallet
$new_balance = $manager->credit_wallet( $advertiser_id, 100.00, 'payment', 'Stripe payment' );

// Debit wallet
$new_balance = $manager->debit_wallet( $advertiser_id, 25.00, 'campaign_spend', 'Campaign #123' );

// Get balance
$balance = $manager->get_wallet_balance( $advertiser_id );

// Get transaction history
$transactions = $manager->get_transactions( $advertiser_id, array(
    'per_page' => 20,
    'page'     => 1,
) );
```

---

## Campaign API

### Managing Campaigns

```php
use WBAM_Pro\Modules\Campaigns\Campaign_Manager;

$manager = Campaign_Manager::get_instance();

// Create campaign
$campaign_id = $manager->create( array(
    'advertiser_id' => $advertiser_id,
    'name'          => 'Summer Sale',
    'budget'        => 500.00,
    'pricing_model' => 'cpm', // cpm, cpc, flat
    'cpm_rate'      => 2.50,
    'start_date'    => '2024-06-01',
    'end_date'      => '2024-08-31',
    'status'        => 'active',
) );

// Get campaign
$campaign = $manager->get( $campaign_id );

// Pause campaign
$manager->pause( $campaign_id, 'manual' );

// Resume campaign
$manager->resume( $campaign_id );

// Get campaign stats
$stats = $manager->get_stats( $campaign_id );
// Returns: impressions, clicks, ctr, spent, remaining_budget
```

### Pricing Calculator

```php
use WBAM_Pro\Modules\Campaigns\Pricing_Calculator;

$calculator = Pricing_Calculator::get_instance();

// Calculate CPM cost
$cost = $calculator->calculate_cpm( $impressions, $cpm_rate );

// Calculate CPC cost
$cost = $calculator->calculate_cpc( $clicks, $cpc_rate );

// Estimate campaign cost
$estimate = $calculator->estimate( array(
    'pricing_model' => 'cpm',
    'rate'          => 2.50,
    'impressions'   => 100000,
    'duration_days' => 30,
) );
```

---

## Payment Integration

### Payment Manager

```php
use WBAM_Pro\Modules\Payments\Payment_Manager;

$manager = Payment_Manager::get_instance();

// Get available gateways
$gateways = $manager->get_gateways();

// Create payment
$payment_id = $manager->create_payment( array(
    'advertiser_id' => $advertiser_id,
    'amount'        => 100.00,
    'currency'      => 'USD',
    'gateway'       => 'stripe',
    'description'   => 'Wallet credit',
) );

// Process payment
$result = $manager->process( $payment_id, $gateway_data );

// Verify payment
$verified = $manager->verify_payment( $gateway_id, $payment_id, $verification_data );
```

### Creating Custom Gateway

```php
use WBAM_Pro\Modules\Payments\Payment_Gateway_Interface;

class My_Gateway implements Payment_Gateway_Interface {

    public function get_id() {
        return 'my_gateway';
    }

    public function get_label() {
        return __( 'My Payment Gateway', 'my-plugin' );
    }

    public function is_available() {
        // Check if gateway is configured
        return ! empty( get_option( 'my_gateway_api_key' ) );
    }

    public function create_checkout( $amount, $data ) {
        // Create checkout session
        return array(
            'checkout_url' => 'https://gateway.com/checkout/xxx',
            'session_id'   => 'sess_xxx',
        );
    }

    public function verify_payment( $payment_id ) {
        // Verify payment status
        return true; // or false
    }

    public function process_webhook( $event ) {
        // Handle webhook event
        return array(
            'success' => true,
            'payment_id' => $payment_id,
        );
    }

    public function render_settings() {
        // Render admin settings fields
    }
}

// Register gateway
add_filter( 'wbam_pro_payment_gateways', function( $gateways ) {
    $gateways['my_gateway'] = new My_Gateway();
    return $gateways;
} );
```

---

## Classifieds API

### Managing Classifieds

```php
use WBAM_Pro\Modules\Classifieds\Classified_Manager;

$manager = Classified_Manager::get_instance();

// Create classified
$classified_id = $manager->create( array(
    'title'          => 'iPhone 14 Pro for Sale',
    'description'    => 'Excellent condition...',
    'advertiser_id'  => $advertiser_id,
    'category'       => array( 10, 15 ), // Term IDs
    'location'       => array( 5 ),       // Term IDs
    'price'          => 799.00,
    'price_type'     => 'fixed', // fixed, negotiable, contact, free
    'contact_method' => 'form',
    'contact_email'  => 'seller@example.com',
    'images'         => array( 100, 101, 102 ), // Attachment IDs
    'duration_days'  => 30,
) );

// Get classified
$classified = $manager->get( $classified_id );

// Update classified
$manager->update( $classified_id, array(
    'price' => 749.00,
) );

// Bump classified
$manager->bump( $classified_id );

// Mark as sold
$manager->mark_sold( $classified_id );

// Renew listing
$manager->renew( $classified_id, 30 ); // days
```

### Searching Classifieds

```php
$results = $manager->search( array(
    'keyword'    => 'iphone',
    'category'   => 10,
    'location'   => 5,
    'price_min'  => 100,
    'price_max'  => 1000,
    'sort'       => 'newest', // newest, price_low, price_high
    'per_page'   => 20,
    'page'       => 1,
) );
```

### Managing Inquiries

```php
// Submit inquiry
$inquiry_id = $manager->submit_inquiry( $classified_id, array(
    'name'    => 'Jane Buyer',
    'email'   => 'jane@example.com',
    'phone'   => '555-9876',
    'message' => 'Is this still available?',
) );

// Get inquiries for classified
$inquiries = $manager->get_inquiries( $classified_id );

// Get inquiries for advertiser
$inquiries = $manager->get_advertiser_inquiries( $advertiser_id );

// Mark as replied
$manager->mark_inquiry_replied( $inquiry_id );
```

---

## REST API

### Authentication

PRO API endpoints require authentication:

```php
// Using Application Passwords (recommended)
Authorization: Basic base64(username:application_password)

// Using nonce (for logged-in users)
X-WP-Nonce: {nonce}
```

### Endpoints

#### Advertiser Profile

```
GET /wp-json/wbam-pro/v1/advertiser/profile

Response:
{
    "id": 123,
    "company_name": "Acme Corp",
    "contact_name": "John Doe",
    "email": "john@acme.com",
    "wallet_balance": 250.00,
    "status": "active",
    "created_at": "2024-01-15T10:30:00"
}
```

#### Advertiser Stats

```
GET /wp-json/wbam-pro/v1/advertiser/stats

Response:
{
    "active_ads": 5,
    "active_campaigns": 2,
    "active_classifieds": 10,
    "total_impressions": 150000,
    "total_clicks": 3500,
    "total_spent": 125.50,
    "wallet_balance": 250.00
}
```

#### Ads

```
GET /wp-json/wbam-pro/v1/ads
GET /wp-json/wbam-pro/v1/ads/{id}
POST /wp-json/wbam-pro/v1/ads
PUT /wp-json/wbam-pro/v1/ads/{id}
DELETE /wp-json/wbam-pro/v1/ads/{id}
```

#### Classifieds

```
GET /wp-json/wbam-pro/v1/classifieds
GET /wp-json/wbam-pro/v1/classifieds/{id}
POST /wp-json/wbam-pro/v1/classifieds
PUT /wp-json/wbam-pro/v1/classifieds/{id}
POST /wp-json/wbam-pro/v1/classifieds/{id}/inquiry
POST /wp-json/wbam-pro/v1/classifieds/{id}/bump
```

#### Wallet

```
GET /wp-json/wbam-pro/v1/wallet
GET /wp-json/wbam-pro/v1/wallet/transactions
POST /wp-json/wbam-pro/v1/wallet/checkout
```

#### Packages

```
GET /wp-json/wbam-pro/v1/packages
GET /wp-json/wbam-pro/v1/packages/{id}
```

---

## Database Schema

### PRO Tables

#### wbam_advertisers

```sql
CREATE TABLE {prefix}wbam_advertisers (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id bigint(20) UNSIGNED NOT NULL,
    company_name varchar(255) DEFAULT '',
    contact_name varchar(200) NOT NULL,
    email varchar(255) NOT NULL,
    phone varchar(50) DEFAULT '',
    website varchar(255) DEFAULT '',
    address text,
    wallet_balance decimal(15,2) DEFAULT 0.00,
    status varchar(20) DEFAULT 'pending',
    notes text,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY user_id (user_id),
    KEY status (status),
    KEY email (email)
);
```

#### wbam_campaigns

```sql
CREATE TABLE {prefix}wbam_campaigns (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    advertiser_id bigint(20) UNSIGNED NOT NULL,
    name varchar(255) NOT NULL,
    pricing_model varchar(20) NOT NULL,       -- cpm, cpc, flat
    cpm_rate decimal(10,4) DEFAULT NULL,
    cpc_rate decimal(10,4) DEFAULT NULL,
    flat_rate decimal(15,2) DEFAULT NULL,
    budget decimal(15,2) NOT NULL,
    spent decimal(15,2) DEFAULT 0.00,
    start_date datetime NOT NULL,
    end_date datetime DEFAULT NULL,
    status varchar(20) DEFAULT 'pending',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY advertiser_id (advertiser_id),
    KEY status (status)
);
```

#### wbam_packages

```sql
CREATE TABLE {prefix}wbam_packages (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    description text,
    price decimal(15,2) NOT NULL,
    duration_days int(11) DEFAULT 30,
    impression_limit bigint(20) UNSIGNED DEFAULT NULL,
    click_limit bigint(20) UNSIGNED DEFAULT NULL,
    placements text,                          -- Serialized array
    features text,                            -- Serialized array
    status varchar(20) DEFAULT 'active',
    sort_order int(11) DEFAULT 0,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY status (status)
);
```

#### wbam_transactions

```sql
CREATE TABLE {prefix}wbam_transactions (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    advertiser_id bigint(20) UNSIGNED NOT NULL,
    type varchar(20) NOT NULL,                -- credit, debit
    amount decimal(15,2) NOT NULL,
    balance_after decimal(15,2) NOT NULL,
    source varchar(50) NOT NULL,              -- payment, manual, refund, spend
    reference_type varchar(50) DEFAULT NULL,  -- campaign, package, etc.
    reference_id bigint(20) UNSIGNED DEFAULT NULL,
    gateway varchar(50) DEFAULT NULL,
    gateway_transaction_id varchar(255) DEFAULT NULL,
    description text,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY advertiser_id (advertiser_id),
    KEY type (type),
    KEY created_at (created_at)
);
```

#### wbam_ad_submissions

```sql
CREATE TABLE {prefix}wbam_ad_submissions (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    advertiser_id bigint(20) UNSIGNED NOT NULL,
    ad_id bigint(20) UNSIGNED DEFAULT NULL,
    package_id bigint(20) UNSIGNED DEFAULT NULL,
    campaign_id bigint(20) UNSIGNED DEFAULT NULL,
    ad_type varchar(50) NOT NULL,
    ad_data text NOT NULL,                    -- Serialized
    placements text,                          -- Serialized
    status varchar(20) DEFAULT 'pending',
    admin_notes text,
    rejection_reason text,
    submitted_at datetime DEFAULT CURRENT_TIMESTAMP,
    reviewed_at datetime DEFAULT NULL,
    reviewed_by bigint(20) UNSIGNED DEFAULT NULL,
    PRIMARY KEY (id),
    KEY advertiser_id (advertiser_id),
    KEY status (status)
);
```

#### wbam_classifieds

```sql
CREATE TABLE {prefix}wbam_classifieds (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id bigint(20) UNSIGNED NOT NULL,
    advertiser_id bigint(20) UNSIGNED NOT NULL,
    price decimal(15,2) DEFAULT NULL,
    price_type varchar(20) DEFAULT 'fixed',
    currency varchar(3) DEFAULT 'USD',
    contact_method varchar(20) DEFAULT 'form',
    contact_email varchar(255) DEFAULT NULL,
    contact_phone varchar(50) DEFAULT NULL,
    contact_name varchar(200) DEFAULT NULL,
    item_condition varchar(20) DEFAULT NULL,
    listing_type varchar(20) DEFAULT 'standard',
    is_featured tinyint(1) DEFAULT 0,
    is_highlighted tinyint(1) DEFAULT 0,
    views_count int(11) UNSIGNED DEFAULT 0,
    inquiries_count int(11) UNSIGNED DEFAULT 0,
    expires_at datetime DEFAULT NULL,
    bumped_at datetime DEFAULT NULL,
    bump_count int(11) UNSIGNED DEFAULT 0,
    status varchar(20) DEFAULT 'pending',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY post_id (post_id),
    KEY advertiser_id (advertiser_id),
    KEY status (status),
    KEY expires_at (expires_at)
);
```

#### wbam_classified_meta

```sql
CREATE TABLE {prefix}wbam_classified_meta (
    meta_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    classified_id bigint(20) UNSIGNED NOT NULL,
    meta_key varchar(255) NOT NULL,
    meta_value longtext DEFAULT NULL,
    PRIMARY KEY (meta_id),
    KEY classified_id (classified_id),
    KEY meta_key (meta_key(191))
);
```

#### wbam_classified_inquiries

```sql
CREATE TABLE {prefix}wbam_classified_inquiries (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    classified_id bigint(20) UNSIGNED NOT NULL,
    sender_user_id bigint(20) UNSIGNED DEFAULT NULL,
    sender_name varchar(200) NOT NULL,
    sender_email varchar(255) NOT NULL,
    sender_phone varchar(50) DEFAULT NULL,
    message text NOT NULL,
    status varchar(20) DEFAULT 'unread',
    replied_at datetime DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY classified_id (classified_id),
    KEY status (status)
);
```

---

## Code Examples

### Custom Payment Gateway Integration

```php
// Add custom payment option
add_action( 'wbam_pro_payment_verified', function( $verified, $gateway_id, $payment_id, $data ) {
    if ( ! $verified || 'my_gateway' !== $gateway_id ) {
        return;
    }

    // Send custom notification
    $payment = wbam_pro_get_payment( $payment_id );
    $advertiser = wbam_pro_get_advertiser( $payment['advertiser_id'] );

    wp_mail(
        $advertiser['email'],
        'Payment Received',
        sprintf( 'Your payment of $%s has been processed.', $payment['amount'] )
    );
}, 10, 4 );
```

### Auto-Approve Trusted Advertisers

```php
add_action( 'wbam_pro_ad_submitted', function( $submission_id, $advertiser_id, $data ) {
    $advertiser = wbam_pro_get_advertiser( $advertiser_id );

    // Auto-approve for trusted advertisers
    if ( 'trusted' === $advertiser['tier'] ) {
        $manager = WBAM_Pro\Modules\Ads\Ad_Submission_Manager::get_instance();
        $manager->approve( $submission_id, 0, 'Auto-approved (trusted advertiser)' );
    }
}, 10, 3 );
```

### Custom Classified Fields

Custom classified data is stored in the `wbam_classified_meta` table using the `Classified` model's meta API (mirrors WordPress `*_post_meta()` functions):

```php
// Add custom field to classified form
add_action( 'wbam_pro_classified_form_after_price', function( $classified_id ) {
    $classified = new \WBAM_Pro\Modules\Classifieds\Classified( $classified_id );
    $warranty   = $classified->get_meta( 'warranty_months' );
    ?>
    <div class="wbam-field">
        <label for="warranty_months"><?php esc_html_e( 'Warranty (months)', 'my-plugin' ); ?></label>
        <input type="number" id="warranty_months" name="warranty_months"
               value="<?php echo esc_attr( $warranty ); ?>" min="0" max="60">
    </div>
    <?php
} );

// Save custom field
add_action( 'wbam_pro_classified_saved', function( $classified_id, $data ) {
    if ( isset( $_POST['warranty_months'] ) ) {
        $classified = new \WBAM_Pro\Modules\Classifieds\Classified( $classified_id );
        $classified->update_meta( 'warranty_months', absint( $_POST['warranty_months'] ) );
    }
}, 10, 2 );

// Display in listing
add_filter( 'wbam_pro_classified_output', function( $output, $classified_id, $args ) {
    $classified = new \WBAM_Pro\Modules\Classifieds\Classified( $classified_id );
    $warranty   = $classified->get_meta( 'warranty_months' );

    if ( $warranty ) {
        $warranty_html = sprintf(
            '<div class="classified-warranty">%s: %d %s</div>',
            esc_html__( 'Warranty', 'my-plugin' ),
            $warranty,
            esc_html__( 'months', 'my-plugin' )
        );
        $output = str_replace( '</div><!-- .classified-meta -->', $warranty_html . '</div><!-- .classified-meta -->', $output );
    }

    return $output;
}, 10, 3 );
```

#### Meta API Reference

```php
$classified = new \WBAM_Pro\Modules\Classifieds\Classified( $id );

// Add meta (use $unique = true to prevent duplicates)
$classified->add_meta( 'color', 'red' );
$classified->add_meta( 'vin', '123ABC', true );

// Get meta (single value by default, pass false for all values)
$classified->get_meta( 'color' );              // 'red'
$classified->get_meta( 'color', false );       // array( 'red' )
$classified->get_meta();                       // array of all meta

// Update meta (auto-creates if key doesn't exist)
$classified->update_meta( 'color', 'blue' );

// Delete meta (optionally match a specific value)
$classified->delete_meta( 'color' );           // delete all 'color' rows
$classified->delete_meta( 'color', 'blue' );   // delete only matching value
```

### Budget Alert Integration

```php
add_action( 'wbam_pro_campaign_budget_exhausted', function( $campaign_id, $spent, $budget ) {
    $campaign = wbam_pro_get_campaign( $campaign_id );
    $advertiser = wbam_pro_get_advertiser( $campaign['advertiser_id'] );

    // Send Slack notification
    wp_remote_post( 'https://hooks.slack.com/services/xxx', array(
        'body' => wp_json_encode( array(
            'text' => sprintf(
                'Campaign "%s" for %s has exhausted its budget ($%s)',
                $campaign['name'],
                $advertiser['company_name'],
                number_format( $budget, 2 )
            ),
        ) ),
        'headers' => array( 'Content-Type' => 'application/json' ),
    ) );
}, 10, 3 );
```

### Custom Analytics Dashboard Widget

```php
add_action( 'wbam_pro_analytics_dashboard_widgets', function() {
    ?>
    <div class="wbam-widget">
        <h3><?php esc_html_e( 'Top Performing Advertisers', 'my-plugin' ); ?></h3>
        <?php
        global $wpdb;
        $top_advertisers = $wpdb->get_results( "
            SELECT a.company_name, SUM(t.amount) as total_spent
            FROM {$wpdb->prefix}wbam_advertisers a
            JOIN {$wpdb->prefix}wbam_transactions t ON a.id = t.advertiser_id
            WHERE t.type = 'debit' AND t.source = 'spend'
            GROUP BY a.id
            ORDER BY total_spent DESC
            LIMIT 10
        " );

        if ( $top_advertisers ) {
            echo '<ol>';
            foreach ( $top_advertisers as $adv ) {
                printf(
                    '<li>%s - $%s</li>',
                    esc_html( $adv->company_name ),
                    number_format( $adv->total_spent, 2 )
                );
            }
            echo '</ol>';
        }
        ?>
    </div>
    <?php
} );
```

---

## Support

For bug reports and feature requests, please use the GitHub issue tracker.

For questions about extending the plugin, check our knowledge base or contact support.

---

*Last updated: December 17, 2024*
