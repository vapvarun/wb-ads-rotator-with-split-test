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

		if ( empty( $schedule ) || ! is_array( $schedule ) ) {
			return true;
		}

		$now = current_time( 'timestamp' );

		// Check start date.
		if ( ! empty( $schedule['start_date'] ) ) {
			$start = strtotime( $schedule['start_date'] );
			if ( $start && $now < $start ) {
				return false;
			}
		}

		// Check end date.
		if ( ! empty( $schedule['end_date'] ) ) {
			$end = strtotime( $schedule['end_date'] . ' 23:59:59' );
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

			if ( ! empty( $schedule['time_start'] ) && $current_time < $schedule['time_start'] ) {
				return false;
			}

			if ( ! empty( $schedule['time_end'] ) && $current_time > $schedule['time_end'] ) {
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
	 * Detect current device type.
	 *
	 * @return string
	 */
	private function detect_device() {
		if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return 'desktop';
		}

		$ua = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );

		// Check for tablets first (iPad, Android tablets, etc.).
		// Note: Android tablets don't have "Mobile" in UA, Android phones do.
		if ( preg_match( '/tablet|ipad|playbook|silk|kindle/i', $ua ) ) {
			return 'tablet';
		}

		// Android without "Mobile" is typically a tablet.
		if ( preg_match( '/android/i', $ua ) && ! preg_match( '/mobile/i', $ua ) ) {
			return 'tablet';
		}

		// Check for mobile devices.
		if ( preg_match( '/mobile|iphone|ipod|android|blackberry|opera mini|opera mobi|iemobile|windows phone|webos|palm|symbian|nokia|samsung|lg-|htc|mot-|sonyericsson/i', $ua ) ) {
			return 'mobile';
		}

		return 'desktop';
	}
}
