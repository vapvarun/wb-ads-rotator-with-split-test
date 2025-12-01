<?php
/**
 * Link Data Model
 *
 * @package WB_Ad_Manager
 * @since   2.1.0
 */

namespace WBAM\Modules\Links;

/**
 * Link class.
 */
class Link {

	/**
	 * Link ID.
	 *
	 * @var int
	 */
	public $id = 0;

	/**
	 * Link name.
	 *
	 * @var string
	 */
	public $name = '';

	/**
	 * Destination URL.
	 *
	 * @var string
	 */
	public $destination_url = '';

	/**
	 * Cloaked slug.
	 *
	 * @var string
	 */
	public $slug = '';

	/**
	 * Link type (affiliate, guest_post, external, internal).
	 *
	 * @var string
	 */
	public $link_type = 'affiliate';

	/**
	 * Whether cloaking is enabled.
	 *
	 * @var bool
	 */
	public $cloaking_enabled = true;

	/**
	 * Whether to add nofollow.
	 *
	 * @var bool
	 */
	public $nofollow = true;

	/**
	 * Whether to add sponsored.
	 *
	 * @var bool
	 */
	public $sponsored = false;

	/**
	 * Whether to open in new tab.
	 *
	 * @var bool
	 */
	public $new_tab = true;

	/**
	 * Category ID.
	 *
	 * @var int
	 */
	public $category_id = 0;

	/**
	 * Description.
	 *
	 * @var string
	 */
	public $description = '';

	/**
	 * Additional URL parameters (JSON).
	 *
	 * @var string
	 */
	public $parameters = '';

	/**
	 * Redirect type (301, 302, 307).
	 *
	 * @var int
	 */
	public $redirect_type = 307;

	/**
	 * Click count.
	 *
	 * @var int
	 */
	public $click_count = 0;

	/**
	 * Status (active, inactive, expired).
	 *
	 * @var string
	 */
	public $status = 'active';

	/**
	 * Expiration date.
	 *
	 * @var string|null
	 */
	public $expires_at = null;

	/**
	 * User who created the link.
	 *
	 * @var int
	 */
	public $created_by = 0;

	/**
	 * Created date.
	 *
	 * @var string
	 */
	public $created_at = '';

	/**
	 * Updated date.
	 *
	 * @var string
	 */
	public $updated_at = '';

	/**
	 * Constructor.
	 *
	 * @param object|array $data Link data.
	 */
	public function __construct( $data = null ) {
		if ( $data ) {
			$this->populate( $data );
		}
	}

	/**
	 * Populate link from data.
	 *
	 * @param object|array $data Link data.
	 */
	public function populate( $data ) {
		$data = (object) $data;

		foreach ( get_object_vars( $this ) as $key => $value ) {
			if ( isset( $data->$key ) ) {
				$this->$key = $data->$key;
			}
		}

		// Cast boolean fields.
		$this->cloaking_enabled = (bool) $this->cloaking_enabled;
		$this->nofollow         = (bool) $this->nofollow;
		$this->sponsored        = (bool) $this->sponsored;
		$this->new_tab          = (bool) $this->new_tab;

		// Cast integer fields.
		$this->id            = (int) $this->id;
		$this->category_id   = (int) $this->category_id;
		$this->redirect_type = (int) $this->redirect_type;
		$this->click_count   = (int) $this->click_count;
		$this->created_by    = (int) $this->created_by;
	}

	/**
	 * Get the link URL (cloaked or direct based on settings).
	 *
	 * @return string
	 */
	public function get_url() {
		if ( $this->cloaking_enabled && $this->slug ) {
			return home_url( '/' . $this->get_cloak_prefix() . '/' . $this->slug );
		}
		return $this->get_destination_url();
	}

	/**
	 * Get destination URL with parameters appended.
	 *
	 * @return string
	 */
	public function get_destination_url() {
		$url = $this->destination_url;

		if ( ! empty( $this->parameters ) ) {
			$params = json_decode( $this->parameters, true );
			if ( $params && is_array( $params ) ) {
				$url = add_query_arg( $params, $url );
			}
		}

		return $url;
	}

	/**
	 * Get cloaking prefix from settings.
	 *
	 * @return string
	 */
	public function get_cloak_prefix() {
		$settings = get_option( 'wbam_settings', array() );
		return isset( $settings['link_cloak_prefix'] ) ? $settings['link_cloak_prefix'] : 'go';
	}

	/**
	 * Get HTML link attributes.
	 *
	 * @return array
	 */
	public function get_attributes() {
		$rel = array();

		if ( $this->nofollow ) {
			$rel[] = 'nofollow';
		}
		if ( $this->sponsored ) {
			$rel[] = 'sponsored';
		}

		$attrs = array(
			'href'         => $this->get_url(),
			'rel'          => implode( ' ', $rel ),
			'target'       => $this->new_tab ? '_blank' : '_self',
			'class'        => 'wbam-link wbam-link-' . esc_attr( $this->link_type ),
			'data-link-id' => $this->id,
		);

		return $attrs;
	}

	/**
	 * Get HTML link tag.
	 *
	 * @param string $text Link text.
	 * @return string
	 */
	public function get_html( $text = '' ) {
		if ( empty( $text ) ) {
			$text = $this->name;
		}

		$attrs      = $this->get_attributes();
		$attr_parts = array();

		foreach ( $attrs as $name => $value ) {
			if ( '' !== $value ) {
				$attr_parts[] = sprintf( '%s="%s"', esc_attr( $name ), esc_attr( $value ) );
			}
		}

		return sprintf( '<a %s>%s</a>', implode( ' ', $attr_parts ), esc_html( $text ) );
	}

	/**
	 * Check if link is active.
	 *
	 * @return bool
	 */
	public function is_active() {
		if ( 'active' !== $this->status ) {
			return false;
		}

		if ( $this->expires_at && strtotime( $this->expires_at ) < time() ) {
			return false;
		}

		return true;
	}

	/**
	 * Get link type label.
	 *
	 * @return string
	 */
	public function get_type_label() {
		$types = self::get_link_types();
		return isset( $types[ $this->link_type ] ) ? $types[ $this->link_type ] : $this->link_type;
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
	 * Get available link types.
	 *
	 * @return array
	 */
	public static function get_link_types() {
		return array(
			'affiliate'  => __( 'Affiliate', 'wb-ad-manager' ),
			'guest_post' => __( 'Guest Post', 'wb-ad-manager' ),
			'external'   => __( 'External', 'wb-ad-manager' ),
			'internal'   => __( 'Internal', 'wb-ad-manager' ),
		);
	}

	/**
	 * Get available statuses.
	 *
	 * @return array
	 */
	public static function get_statuses() {
		return array(
			'active'   => __( 'Active', 'wb-ad-manager' ),
			'inactive' => __( 'Inactive', 'wb-ad-manager' ),
			'expired'  => __( 'Expired', 'wb-ad-manager' ),
		);
	}

	/**
	 * Get available redirect types.
	 *
	 * @return array
	 */
	public static function get_redirect_types() {
		return array(
			301 => __( '301 Permanent', 'wb-ad-manager' ),
			302 => __( '302 Temporary', 'wb-ad-manager' ),
			307 => __( '307 Temporary (Recommended)', 'wb-ad-manager' ),
		);
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
