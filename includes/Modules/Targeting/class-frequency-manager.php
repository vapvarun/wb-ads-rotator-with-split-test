<?php
/**
 * Frequency Manager
 *
 * @package WB_Ad_Manager
 * @since   1.1.0
 */

namespace WBAM\Modules\Targeting;

use WBAM\Core\Singleton;
use WBAM\Admin\Settings;

/**
 * Frequency Manager class.
 */
class Frequency_Manager {

	use Singleton;

	/**
	 * Cookie name for session tracking.
	 */
	const COOKIE_NAME = 'wbam_ad_views';

	/**
	 * Cookie expiration (1 day).
	 */
	const COOKIE_EXPIRATION = DAY_IN_SECONDS;

	/**
	 * Ads shown on current page.
	 *
	 * @var array
	 */
	private $page_ads = array();

	/**
	 * Initialize.
	 */
	public function init() {
		add_action( 'wp_footer', array( $this, 'set_view_cookie' ), 999 );
	}

	/**
	 * Track ad impression on current page.
	 *
	 * @param int $ad_id Ad ID.
	 */
	public function track_impression( $ad_id ) {
		$this->page_ads[] = $ad_id;
	}

	/**
	 * Get number of ads shown on current page.
	 *
	 * @return int
	 */
	public function get_page_ad_count() {
		return count( array_unique( $this->page_ads ) );
	}

	/**
	 * Check if page limit reached.
	 *
	 * @return bool
	 */
	public function page_limit_reached() {
		$settings  = Settings::get_instance();
		$max_page  = $settings->get( 'max_ads_per_page', 0 );

		if ( $max_page <= 0 ) {
			return false;
		}

		return $this->get_page_ad_count() >= $max_page;
	}

	/**
	 * Check if specific ad can be shown (session limit).
	 *
	 * @param int $ad_id Ad ID.
	 * @return bool
	 */
	public function can_show_ad( $ad_id ) {
		// Check page limit first.
		if ( $this->page_limit_reached() ) {
			return false;
		}

		// Check session limit for this specific ad.
		$session_limit = get_post_meta( $ad_id, '_wbam_session_limit', true );

		if ( empty( $session_limit ) || $session_limit <= 0 ) {
			return true;
		}

		$views = $this->get_ad_views( $ad_id );
		return $views < $session_limit;
	}

	/**
	 * Get ad views from cookie.
	 *
	 * @param int $ad_id Ad ID.
	 * @return int
	 */
	public function get_ad_views( $ad_id ) {
		$cookie_data = $this->get_cookie_data();
		return isset( $cookie_data[ $ad_id ] ) ? (int) $cookie_data[ $ad_id ] : 0;
	}

	/**
	 * Get cookie data.
	 *
	 * @return array
	 */
	private function get_cookie_data() {
		if ( ! isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return array();
		}

		$data = json_decode( sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) ), true );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Set view cookie in footer.
	 */
	public function set_view_cookie() {
		if ( empty( $this->page_ads ) ) {
			return;
		}

		$cookie_data = $this->get_cookie_data();

		foreach ( $this->page_ads as $ad_id ) {
			if ( isset( $cookie_data[ $ad_id ] ) ) {
				$cookie_data[ $ad_id ]++;
			} else {
				$cookie_data[ $ad_id ] = 1;
			}
		}

		// Output JS to set cookie.
		$json = wp_json_encode( $cookie_data );
		$exp  = time() + self::COOKIE_EXPIRATION;
		?>
		<script>
		(function() {
			document.cookie = '<?php echo esc_js( self::COOKIE_NAME ); ?>=' + encodeURIComponent('<?php echo esc_js( $json ); ?>') + ';expires=<?php echo esc_js( gmdate( 'D, d M Y H:i:s', $exp ) ); ?> GMT;path=/';
		})();
		</script>
		<?php
	}

	/**
	 * Get ads sorted by priority.
	 *
	 * @param array $ad_ids Array of ad IDs.
	 * @return array Sorted ad IDs.
	 */
	public function sort_by_priority( $ad_ids ) {
		if ( empty( $ad_ids ) ) {
			return array();
		}

		$ads_with_priority = array();

		foreach ( $ad_ids as $ad_id ) {
			$priority = get_post_meta( $ad_id, '_wbam_priority', true );
			$ads_with_priority[ $ad_id ] = ! empty( $priority ) ? (int) $priority : 5;
		}

		// Sort by priority (higher = first).
		arsort( $ads_with_priority );

		return array_keys( $ads_with_priority );
	}

	/**
	 * Get random ad from list with weight.
	 *
	 * @param array $ad_ids Array of ad IDs.
	 * @return int|null Selected ad ID or null.
	 */
	public function get_weighted_random( $ad_ids ) {
		if ( empty( $ad_ids ) ) {
			return null;
		}

		$weighted = array();

		foreach ( $ad_ids as $ad_id ) {
			$priority = get_post_meta( $ad_id, '_wbam_priority', true );
			$weight   = ! empty( $priority ) ? (int) $priority : 5;

			// Add ad to pool based on weight.
			for ( $i = 0; $i < $weight; $i++ ) {
				$weighted[] = $ad_id;
			}
		}

		if ( empty( $weighted ) ) {
			return $ad_ids[0];
		}

		return $weighted[ array_rand( $weighted ) ];
	}

	/**
	 * Filter ads by frequency rules.
	 *
	 * @param array $ad_ids Array of ad IDs.
	 * @return array Filtered ad IDs.
	 */
	public function filter_by_frequency( $ad_ids ) {
		$filtered = array();

		foreach ( $ad_ids as $ad_id ) {
			if ( $this->can_show_ad( $ad_id ) ) {
				$filtered[] = $ad_id;
			}
		}

		return $filtered;
	}
}
