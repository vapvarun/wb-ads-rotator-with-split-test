<?php
/**
 * Partnership Data Model
 *
 * @package WB_Ad_Manager
 * @since   2.2.0
 */

namespace WBAM\Modules\Links;

/**
 * Partnership class.
 */
class Partnership {

	/**
	 * Partnership ID.
	 *
	 * @var int
	 */
	public $id = 0;

	/**
	 * Contact name.
	 *
	 * @var string
	 */
	public $name = '';

	/**
	 * Contact email.
	 *
	 * @var string
	 */
	public $email = '';

	/**
	 * Partner's website URL.
	 *
	 * @var string
	 */
	public $website_url = '';

	/**
	 * Partnership type (paid_link, exchange, sponsored_post).
	 *
	 * @var string
	 */
	public $partnership_type = 'paid_link';

	/**
	 * Target post ID for link placement.
	 *
	 * @var int|null
	 */
	public $target_post_id = null;

	/**
	 * Proposed anchor text.
	 *
	 * @var string
	 */
	public $anchor_text = '';

	/**
	 * Message/notes from requester.
	 *
	 * @var string
	 */
	public $message = '';

	/**
	 * Minimum budget (for paid links).
	 *
	 * @var float|null
	 */
	public $budget_min = null;

	/**
	 * Maximum budget (for paid links).
	 *
	 * @var float|null
	 */
	public $budget_max = null;

	/**
	 * Status (pending, accepted, rejected, spam).
	 *
	 * @var string
	 */
	public $status = 'pending';

	/**
	 * Admin notes.
	 *
	 * @var string
	 */
	public $admin_notes = '';

	/**
	 * IP address of requester.
	 *
	 * @var string
	 */
	public $ip_address = '';

	/**
	 * Created date.
	 *
	 * @var string
	 */
	public $created_at = '';

	/**
	 * Response date.
	 *
	 * @var string|null
	 */
	public $responded_at = null;

	/**
	 * Constructor.
	 *
	 * @param object|array $data Partnership data.
	 */
	public function __construct( $data = null ) {
		if ( $data ) {
			$this->populate( $data );
		}
	}

	/**
	 * Populate partnership from data.
	 *
	 * @param object|array $data Partnership data.
	 */
	public function populate( $data ) {
		$data = (object) $data;

		foreach ( get_object_vars( $this ) as $key => $value ) {
			if ( isset( $data->$key ) ) {
				$this->$key = $data->$key;
			}
		}

		// Cast integer fields.
		$this->id             = (int) $this->id;
		$this->target_post_id = $this->target_post_id ? (int) $this->target_post_id : null;

		// Cast float fields.
		$this->budget_min = null !== $this->budget_min ? (float) $this->budget_min : null;
		$this->budget_max = null !== $this->budget_max ? (float) $this->budget_max : null;
	}

	/**
	 * Get partnership type label.
	 *
	 * @return string
	 */
	public function get_type_label() {
		$types = self::get_partnership_types();
		return isset( $types[ $this->partnership_type ] ) ? $types[ $this->partnership_type ] : $this->partnership_type;
	}

	/**
	 * Get status label.
	 *
	 * @return string
	 */
	public function get_status_label() {
		$statuses = self::get_statuses();
		return isset( $statuses[ $this->status ] ) ? $statuses[ $this->status ] : $this->status;
	}

	/**
	 * Get target post title.
	 *
	 * @return string
	 */
	public function get_target_post_title() {
		if ( ! $this->target_post_id ) {
			return __( 'Any page', 'wb-ads-rotator-with-split-test' );
		}

		$post = get_post( $this->target_post_id );
		return $post ? $post->post_title : __( 'Unknown', 'wb-ads-rotator-with-split-test' );
	}

	/**
	 * Get formatted budget range.
	 *
	 * @return string
	 */
	public function get_budget_range() {
		if ( null === $this->budget_min && null === $this->budget_max ) {
			return __( 'Not specified', 'wb-ads-rotator-with-split-test' );
		}

		$currency = get_option( 'wbam_currency', 'USD' );
		$symbol   = self::get_currency_symbol( $currency );

		if ( null !== $this->budget_min && null !== $this->budget_max ) {
			if ( $this->budget_min === $this->budget_max ) {
				return $symbol . number_format( $this->budget_min, 2 );
			}
			return $symbol . number_format( $this->budget_min, 2 ) . ' - ' . $symbol . number_format( $this->budget_max, 2 );
		}

		if ( null !== $this->budget_min ) {
			return $symbol . number_format( $this->budget_min, 2 ) . '+';
		}

		return __( 'Up to ', 'wb-ads-rotator-with-split-test' ) . $symbol . number_format( $this->budget_max, 2 );
	}

	/**
	 * Check if partnership is pending.
	 *
	 * @return bool
	 */
	public function is_pending() {
		return 'pending' === $this->status;
	}

	/**
	 * Check if partnership is accepted.
	 *
	 * @return bool
	 */
	public function is_accepted() {
		return 'accepted' === $this->status;
	}

	/**
	 * Check if partnership is rejected.
	 *
	 * @return bool
	 */
	public function is_rejected() {
		return 'rejected' === $this->status;
	}

	/**
	 * Get available partnership types.
	 *
	 * @return array
	 */
	public static function get_partnership_types() {
		return array(
			'paid_link'      => __( 'Paid Link', 'wb-ads-rotator-with-split-test' ),
			'exchange'       => __( 'Link Exchange', 'wb-ads-rotator-with-split-test' ),
			'sponsored_post' => __( 'Sponsored Post / Guest Post', 'wb-ads-rotator-with-split-test' ),
		);
	}

	/**
	 * Get available statuses.
	 *
	 * @return array
	 */
	public static function get_statuses() {
		return array(
			'pending'  => __( 'Pending', 'wb-ads-rotator-with-split-test' ),
			'accepted' => __( 'Accepted', 'wb-ads-rotator-with-split-test' ),
			'rejected' => __( 'Rejected', 'wb-ads-rotator-with-split-test' ),
			'spam'     => __( 'Spam', 'wb-ads-rotator-with-split-test' ),
		);
	}

	/**
	 * Get currency symbol.
	 *
	 * @param string $currency Currency code.
	 * @return string
	 */
	public static function get_currency_symbol( $currency = 'USD' ) {
		$symbols = array(
			'USD' => '$',
			'EUR' => '€',
			'GBP' => '£',
			'INR' => '₹',
			'CAD' => 'CA$',
			'AUD' => 'A$',
			'JPY' => '¥',
		);

		return isset( $symbols[ $currency ] ) ? $symbols[ $currency ] : $currency . ' ';
	}

	/**
	 * Get time since creation.
	 *
	 * @return string
	 */
	public function get_time_ago() {
		return human_time_diff( strtotime( $this->created_at ), current_datetime()->getTimestamp() );
	}

	/**
	 * Convert to array.
	 *
	 * @return array
	 */
	public function to_array() {
		return get_object_vars( $this );
	}
}
