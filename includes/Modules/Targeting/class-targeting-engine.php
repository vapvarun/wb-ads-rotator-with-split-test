<?php
/**
 * Targeting Engine
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Modules\Targeting;

use WBAM\Core\Singleton;
use WBAM\Admin\Settings;
use WBAM\Modules\GeoTargeting\Geo_Engine;

/**
 * Targeting Engine class.
 */
class Targeting_Engine {

	use Singleton;

	/**
	 * Check if ad should display based on all targeting rules.
	 *
	 * @param int $ad_id Ad ID.
	 * @return bool
	 */
	public function should_display( $ad_id ) {
		// Check if ad is enabled.
		$enabled = get_post_meta( $ad_id, '_wbam_enabled', true );
		if ( '0' === $enabled ) {
			return false;
		}

		// Check global settings.
		if ( ! $this->check_global_settings() ) {
			return false;
		}

		// Check schedule.
		if ( ! $this->check_schedule( $ad_id ) ) {
			return false;
		}

		// Check display rules.
		if ( ! $this->check_display_rules( $ad_id ) ) {
			return false;
		}

		// Check visitor conditions.
		if ( ! $this->check_visitor_conditions( $ad_id ) ) {
			return false;
		}

		// Check geo targeting.
		if ( ! $this->check_geo_targeting( $ad_id ) ) {
			return false;
		}

		// Check session/frequency limits.
		$frequency_manager = Frequency_Manager::get_instance();
		if ( ! $frequency_manager->can_show_ad( $ad_id ) ) {
			return false;
		}

		return apply_filters( 'wbam_should_display_ad', true, $ad_id );
	}

	/**
	 * Check geo targeting.
	 *
	 * @param int $ad_id Ad ID.
	 * @return bool
	 */
	private function check_geo_targeting( $ad_id ) {
		$geo_engine = Geo_Engine::get_instance();
		return $geo_engine->matches_targeting( $ad_id );
	}

	/**
	 * Check global settings.
	 *
	 * @return bool
	 */
	private function check_global_settings() {
		$settings = Settings::get_instance();

		// Disable for logged-in users.
		if ( $settings->get( 'disable_ads_logged_in' ) && is_user_logged_in() ) {
			return false;
		}

		// Disable for admins.
		if ( $settings->get( 'disable_ads_admin' ) && current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Check disabled post types.
		$disabled_post_types = $settings->get( 'disable_on_post_types', array() );
		if ( ! empty( $disabled_post_types ) && is_singular() ) {
			$current_post_type = get_post_type();
			if ( in_array( $current_post_type, $disabled_post_types, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check schedule.
	 *
	 * @param int $ad_id Ad ID.
	 * @return bool
	 */
	private function check_schedule( $ad_id ) {
		$schedule = get_post_meta( $ad_id, '_wbam_schedule', true );

		// Also check standalone date meta (used by PRO advertiser dashboard).
		$standalone_start = get_post_meta( $ad_id, '_wbam_start_date', true );
		$standalone_end   = get_post_meta( $ad_id, '_wbam_end_date', true );

		// If no schedule array and no standalone dates, show the ad.
		if ( ( empty( $schedule ) || ! is_array( $schedule ) ) && empty( $standalone_start ) && empty( $standalone_end ) ) {
			return true;
		}

		// Initialize schedule array if empty.
		if ( empty( $schedule ) || ! is_array( $schedule ) ) {
			$schedule = array();
		}

		// Merge standalone dates into schedule (standalone takes precedence if set).
		if ( ! empty( $standalone_start ) ) {
			$schedule['start_date'] = $standalone_start;
		}
		if ( ! empty( $standalone_end ) ) {
			$schedule['end_date'] = $standalone_end;
		}

		// Use current_datetime() for WP 5.3+ compatibility (avoids deprecated current_time('timestamp')).
		$now_datetime = current_datetime();
		$now          = $now_datetime->getTimestamp();

		// Check start date.
		if ( ! empty( $schedule['start_date'] ) ) {
			$start = strtotime( $schedule['start_date'], $now );
			if ( $start && $now < $start ) {
				return false;
			}
		}

		// Check end date.
		if ( ! empty( $schedule['end_date'] ) ) {
			$end = strtotime( $schedule['end_date'] . ' 23:59:59', $now );
			if ( $end && $now > $end ) {
				return false;
			}
		}

		// Check day of week.
		if ( ! empty( $schedule['days'] ) && is_array( $schedule['days'] ) ) {
			$current_day = strtolower( current_time( 'D' ) );
			if ( ! in_array( $current_day, $schedule['days'], true ) ) {
				return false;
			}
		}

		// Check time of day.
		if ( ! empty( $schedule['time_start'] ) || ! empty( $schedule['time_end'] ) ) {
			$current_time = current_time( 'H:i' );
			$time_start   = ! empty( $schedule['time_start'] ) ? $schedule['time_start'] : '00:00';
			$time_end     = ! empty( $schedule['time_end'] ) ? $schedule['time_end'] : '23:59';

			// Handle overnight schedules (e.g., 22:00 to 06:00).
			if ( $time_start > $time_end ) {
				// Overnight: valid if current time >= start OR current time <= end.
				if ( $current_time < $time_start && $current_time > $time_end ) {
					return false;
				}
			} elseif ( $current_time < $time_start || $current_time > $time_end ) {
				// Normal schedule: valid if current time is between start and end.
				return false;
			}
		}

		return true;
	}

	/**
	 * Check display rules.
	 *
	 * @param int $ad_id Ad ID.
	 * @return bool
	 */
	private function check_display_rules( $ad_id ) {
		$rules = get_post_meta( $ad_id, '_wbam_display_rules', true );

		/**
		 * Filter display rules for an ad.
		 *
		 * @since 2.3.0
		 * @param array $rules Display rules.
		 * @param int   $ad_id Ad ID.
		 */
		$rules = apply_filters( 'wbam_ad_display_rules', $rules, $ad_id );

		if ( empty( $rules ) || ! is_array( $rules ) ) {
			return true; // No rules = show everywhere.
		}

		$display_on = isset( $rules['display_on'] ) ? $rules['display_on'] : 'all';

		// Show on all pages.
		if ( 'all' === $display_on ) {
			return $this->check_exclusions( $rules );
		}

		// Show on specific conditions.
		if ( 'specific' === $display_on ) {
			return $this->check_inclusions( $rules );
		}

		return true;
	}

	/**
	 * Check inclusion rules.
	 *
	 * @param array $rules Rules.
	 * @return bool
	 */
	private function check_inclusions( $rules ) {
		$matched = false;

		// Check post types.
		if ( ! empty( $rules['post_types'] ) && is_singular() ) {
			if ( in_array( get_post_type(), $rules['post_types'], true ) ) {
				$matched = true;
			}
		}

		// Check specific posts.
		if ( ! empty( $rules['posts'] ) && is_singular() ) {
			if ( in_array( get_the_ID(), array_map( 'intval', $rules['posts'] ), true ) ) {
				$matched = true;
			}
		}

		// Check categories.
		if ( ! empty( $rules['categories'] ) ) {
			// Check on single posts.
			if ( is_singular( 'post' ) ) {
				$post_categories = wp_get_post_categories( get_the_ID() );
				if ( array_intersect( $rules['categories'], $post_categories ) ) {
					$matched = true;
				}
			}
			// Check on category archives.
			if ( is_category() ) {
				$current_cat = get_queried_object_id();
				if ( in_array( $current_cat, array_map( 'intval', $rules['categories'] ), true ) ) {
					$matched = true;
				}
			}
		}

		// Check tags.
		if ( ! empty( $rules['tags'] ) ) {
			// Check on single posts.
			if ( is_singular( 'post' ) ) {
				$post_tags = wp_get_post_tags( get_the_ID(), array( 'fields' => 'ids' ) );
				if ( array_intersect( $rules['tags'], $post_tags ) ) {
					$matched = true;
				}
			}
			// Check on tag archives.
			if ( is_tag() ) {
				$current_tag = get_queried_object_id();
				if ( in_array( $current_tag, array_map( 'intval', $rules['tags'] ), true ) ) {
					$matched = true;
				}
			}
		}

		// Check page types.
		if ( ! empty( $rules['page_types'] ) ) {
			foreach ( $rules['page_types'] as $page_type ) {
				if ( $this->is_page_type( $page_type ) ) {
					$matched = true;
					break;
				}
			}
		}

		return $matched;
	}

	/**
	 * Check exclusion rules.
	 *
	 * @param array $rules Rules.
	 * @return bool
	 */
	private function check_exclusions( $rules ) {
		// Exclude specific posts.
		if ( ! empty( $rules['exclude_posts'] ) && is_singular() ) {
			if ( in_array( get_the_ID(), array_map( 'intval', $rules['exclude_posts'] ), true ) ) {
				return false;
			}
		}

		// Exclude categories.
		if ( ! empty( $rules['exclude_categories'] ) ) {
			// Check on single posts.
			if ( is_singular( 'post' ) ) {
				$post_categories = wp_get_post_categories( get_the_ID() );
				if ( array_intersect( $rules['exclude_categories'], $post_categories ) ) {
					return false;
				}
			}
			// Check on category archives.
			if ( is_category() ) {
				$current_cat = get_queried_object_id();
				if ( in_array( $current_cat, array_map( 'intval', $rules['exclude_categories'] ), true ) ) {
					return false;
				}
			}
		}

		// Exclude tags.
		if ( ! empty( $rules['exclude_tags'] ) ) {
			// Check on single posts.
			if ( is_singular( 'post' ) ) {
				$post_tags = wp_get_post_tags( get_the_ID(), array( 'fields' => 'ids' ) );
				if ( array_intersect( $rules['exclude_tags'], $post_tags ) ) {
					return false;
				}
			}
			// Check on tag archives.
			if ( is_tag() ) {
				$current_tag = get_queried_object_id();
				if ( in_array( $current_tag, array_map( 'intval', $rules['exclude_tags'] ), true ) ) {
					return false;
				}
			}
		}

		// Exclude page types.
		if ( ! empty( $rules['exclude_page_types'] ) ) {
			foreach ( $rules['exclude_page_types'] as $page_type ) {
				if ( $this->is_page_type( $page_type ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Check if current page matches type.
	 *
	 * @param string $type Page type.
	 * @return bool
	 */
	private function is_page_type( $type ) {
		switch ( $type ) {
			case 'front_page':
				return is_front_page();
			case 'blog':
				return is_home();
			case 'singular':
				return is_singular();
			case 'archive':
				return is_archive();
			case 'search':
				return is_search();
			case '404':
				return is_404();
			case 'page':
				return is_page();
			case 'post':
				return is_single();
			default:
				return false;
		}
	}

	/**
	 * Check visitor conditions.
	 *
	 * @param int $ad_id Ad ID.
	 * @return bool
	 */
	private function check_visitor_conditions( $ad_id ) {
		$conditions = get_post_meta( $ad_id, '_wbam_visitor_conditions', true );

		if ( empty( $conditions ) || ! is_array( $conditions ) ) {
			return true;
		}

		// Check devices.
		if ( ! empty( $conditions['devices'] ) ) {
			$current_device = $this->detect_device();
			if ( ! in_array( $current_device, $conditions['devices'], true ) ) {
				return false;
			}
		}

		// Check user status.
		if ( ! empty( $conditions['user_status'] ) ) {
			$is_logged_in = is_user_logged_in();
			if ( 'logged_in' === $conditions['user_status'] && ! $is_logged_in ) {
				return false;
			}
			if ( 'logged_out' === $conditions['user_status'] && $is_logged_in ) {
				return false;
			}
		}

		// Check user roles.
		if ( ! empty( $conditions['user_roles'] ) && is_user_logged_in() ) {
			$user  = wp_get_current_user();
			$roles = $user->roles;
			if ( ! array_intersect( $conditions['user_roles'], $roles ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Cached device type for current request.
	 *
	 * @var string|null
	 */
	private $cached_device = null;

	/**
	 * Detect current device type.
	 *
	 * Uses multiple detection methods for reliability:
	 * 1. Client Hints API (modern browsers)
	 * 2. User-Agent pattern matching (comprehensive patterns)
	 * 3. wp_is_mobile() fallback
	 *
	 * @since 1.0.0
	 * @since 1.2.0 Added Client Hints support, caching, and filter.
	 *
	 * @return string Device type: 'mobile', 'tablet', or 'desktop'.
	 */
	private function detect_device() {
		// Return cached result if already detected in this request.
		if ( null !== $this->cached_device ) {
			return $this->cached_device;
		}

		$device = 'desktop';

		// Method 1: Check Client Hints (modern browsers).
		$device = $this->detect_device_from_client_hints();

		if ( 'desktop' === $device ) {
			// Method 2: User-Agent detection.
			$device = $this->detect_device_from_user_agent();
		}

		// Method 3: WordPress fallback - if we detected desktop but wp_is_mobile() returns true.
		if ( 'desktop' === $device && function_exists( 'wp_is_mobile' ) && wp_is_mobile() ) {
			$device = 'mobile';
		}

		/**
		 * Filter the detected device type.
		 *
		 * Allows themes/plugins to override device detection for custom logic.
		 *
		 * @since 1.2.0
		 *
		 * @param string $device     Detected device type: 'mobile', 'tablet', or 'desktop'.
		 * @param string $user_agent The User-Agent string (if available).
		 */
		$device = apply_filters( 'wbam_detected_device', $device, $this->get_user_agent() );

		// Cache for this request.
		$this->cached_device = $device;

		return $device;
	}

	/**
	 * Get sanitized User-Agent string.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	private function get_user_agent() {
		if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return '';
		}
		return sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
	}

	/**
	 * Detect device from Client Hints headers.
	 *
	 * Client Hints provide more reliable device info from modern browsers.
	 * Requires `Sec-CH-UA-Mobile` header to be sent by browser.
	 *
	 * @since 1.2.0
	 *
	 * @return string Device type or 'desktop' if not available.
	 */
	private function detect_device_from_client_hints() {
		// Check Sec-CH-UA-Mobile header (most reliable modern method).
		if ( isset( $_SERVER['HTTP_SEC_CH_UA_MOBILE'] ) ) {
			$mobile_hint = sanitize_text_field( wp_unslash( $_SERVER['HTTP_SEC_CH_UA_MOBILE'] ) );
			$mobile_hint = trim( $mobile_hint, '"? ' );
			if ( '1' === $mobile_hint || 'true' === strtolower( $mobile_hint ) ) {
				// Mobile detected, but could be tablet - check platform.
				return $this->detect_tablet_from_hints() ? 'tablet' : 'mobile';
			}
			// Explicitly not mobile.
			return 'desktop';
		}

		return 'desktop';
	}

	/**
	 * Check if Client Hints indicate a tablet device.
	 *
	 * @since 1.2.0
	 *
	 * @return bool
	 */
	private function detect_tablet_from_hints() {
		// Check Sec-CH-UA-Platform for tablet indicators.
		if ( isset( $_SERVER['HTTP_SEC_CH_UA_PLATFORM'] ) ) {
			$platform = sanitize_text_field( wp_unslash( $_SERVER['HTTP_SEC_CH_UA_PLATFORM'] ) );
			$platform = strtolower( trim( $platform, '"' ) );
			// iPadOS identifies as "iOS" but typically has larger viewport.
			// We'll rely on UA fallback for tablet distinction.
			if ( 'android' === $platform ) {
				// Check if Android tablet by looking at screen hints if available.
				return $this->is_android_tablet_from_ua();
			}
		}
		return false;
	}

	/**
	 * Check if Android device is a tablet from User-Agent.
	 *
	 * Android tablets typically don't have "Mobile" in their UA.
	 *
	 * @since 1.2.0
	 *
	 * @return bool
	 */
	private function is_android_tablet_from_ua() {
		$ua = $this->get_user_agent();
		return preg_match( '/android/i', $ua ) && ! preg_match( '/mobile/i', $ua );
	}

	/**
	 * Detect device from User-Agent string.
	 *
	 * Uses comprehensive regex patterns to detect various devices.
	 * Patterns are regularly updated to handle modern browsers.
	 *
	 * @since 1.2.0
	 *
	 * @return string Device type: 'mobile', 'tablet', or 'desktop'.
	 */
	private function detect_device_from_user_agent() {
		$ua = $this->get_user_agent();

		if ( empty( $ua ) ) {
			return 'desktop';
		}

		// Tablet patterns (check first as some tablets match mobile patterns).
		$tablet_patterns = array(
			// Apple tablets.
			'/ipad/i',
			'/macintosh.*mobile/i', // iPadOS 13+ requesting desktop site.
			// Android tablets (no "Mobile" in UA).
			'/android(?!.*mobile)/i',
			// Amazon tablets.
			'/kindle|silk|kftt|kfot|kfjwa|kfjwi|kfsowi|kfthwa|kfthwi|kfapwa|kfapwi|kfdowi|kfgiwi|kffowi|kfmewi|kftbwi|kfarwi|kfaswi|kfsawa|kfsawi|kfauwi/i',
			// Other tablets.
			'/tablet|playbook|nexus\s*(?:7|9|10)/i',
			'/samsung.*tablet|gt-p|sm-t|sm-p/i',
			'/surface|tab\s/i',
		);

		foreach ( $tablet_patterns as $pattern ) {
			if ( preg_match( $pattern, $ua ) ) {
				return 'tablet';
			}
		}

		// Mobile patterns (comprehensive list).
		$mobile_patterns = array(
			// Apple mobile.
			'/iphone|ipod/i',
			// Android mobile (with "Mobile" in UA).
			'/android.*mobile/i',
			// Windows mobile.
			'/windows\s*phone|iemobile|wpdesktop/i',
			// BlackBerry.
			'/blackberry|bb10|rim\s*tablet/i',
			// Opera mobile.
			'/opera\s*(mini|mobi)/i',
			// Other mobile browsers and devices.
			'/mobile|webos|palm|symbian|nokia|samsung.*mobile|lg[-;\/\s]|htc[-;\/\s]|mot[-;\/\s]|sonyericsson|sie-|portalmmm|blazer|avantgo|danger|elaine|hiptop|fennec|maemo|iris|3g_t|g1\su|mobile\ssafari/i',
			// Feature phones.
			'/vodafone|o2|pocket|wap|midp|cldc|j2me|docomo|au-mic|softbank|kddi|obigo|netfront/i',
			// Smart TV browsers (treat as mobile for limited capabilities).
			'/smart-tv|googletv|appletv|hbbtv|pov_tv|netcast\.tv/i',
		);

		foreach ( $mobile_patterns as $pattern ) {
			if ( preg_match( $pattern, $ua ) ) {
				return 'mobile';
			}
		}

		return 'desktop';
	}
}
