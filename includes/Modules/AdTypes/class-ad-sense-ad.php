<?php
/**
 * Google AdSense Ad Type
 *
 * Filename follows the autoloader's hyphenate-before-uppercase convention
 * (AdSense -> ad-sense -> class-ad-sense-ad.php) rather than WPCS's
 * preferred class-adsense-ad.php. Renaming the file would break the
 * autoloader for every existing deployment.
 *
 * phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- Filename matches the PSR-4-style autoloader convention; renaming would break existing sites.
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Modules\AdTypes;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use WBAM\Core\Settings_Helper;
use WBAM\Core\Privacy_Helper;

/**
 * AdSense Ad class.
 */
class AdSense_Ad implements Ad_Type_Interface {

	/**
	 * Publisher ID of the first AdSense ad rendered on this request, or ''.
	 *
	 * Set by render(); read by maybe_enqueue_adsense_script() so the
	 * pagead script only loads on pages that actually contain an
	 * AdSense ad (ad-blockers otherwise block sibling non-AdSense ads as
	 * collateral damage), and with the ID that ad uses: a per-ad Publisher
	 * ID on a site with no default used to render a unit with no script.
	 *
	 * @var string
	 */
	private static $ad_rendered = '';

	/**
	 * Whether the pagead script has already been enqueued (idempotency).
	 *
	 * @var bool
	 */
	private static $script_enqueued = false;

	/**
	 * Publisher ID from settings.
	 *
	 * @var string
	 */
	private $publisher_id = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->publisher_id = Settings_Helper::get( 'adsense_publisher_id', '' );

		// Print Google's loader ONLY if an AdSense ad actually rendered on
		// this request (render() sets $ad_rendered), and only after every
		// placement has: Footer renders at wp_footer 10 and Popup/Sticky at
		// 50, so the old priority 5 never saw their ads (card 10344381767).
		add_action( 'wp_footer', array( $this, 'maybe_enqueue_adsense_script' ), 1000 );
	}

	/**
	 * Get ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'adsense';
	}

	/**
	 * Get name.
	 *
	 * @return string
	 */
	public function get_name() {
		return __( 'Google AdSense', 'wb-ads-rotator-with-split-test' );
	}

	/**
	 * Get description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Display Google AdSense ads with automatic script management.', 'wb-ads-rotator-with-split-test' );
	}

	/**
	 * Get icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'dashicons-google';
	}

	/**
	 * Maybe enqueue AdSense script.
	 *
	 * Only enqueues when an AdSense ad has actually rendered on this
	 * request (set via render()), so ad-blockers do not see the pagead
	 * URL on pages that have no AdSense ad and block sibling non-AdSense
	 * ads as collateral damage.
	 */
	public function maybe_enqueue_adsense_script() {
		// Hard gate: no AdSense ad on this page → don't load the script.
		if ( ! self::$ad_rendered ) {
			return;
		}

		if ( self::$script_enqueued ) {
			return;
		}

		// Skip if Auto Ads is enabled (script already enqueued in head by
		// class-frontend.php::maybe_add_adsense_auto_ads()).
		if ( Settings_Helper::is_enabled( 'adsense_auto_ads' ) && Settings_Helper::get( 'adsense_publisher_id' ) ) {
			self::$script_enqueued = true;
			return;
		}

		$publisher_id = self::$ad_rendered;

		// Printed directly: footer scripts are already out by now (they
		// print at wp_footer 20). The units' adsbygoogle.push() calls queue
		// until this async script arrives.
		wp_print_script_tag(
			array(
				'src'         => add_query_arg( 'client', $publisher_id, 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js' ),
				'async'       => true,
				'crossorigin' => 'anonymous',
			)
		);

		self::$script_enqueued = true;
	}

	/**
	 * Get Publisher ID.
	 *
	 * @param array $data Optional ad data to check for override.
	 * @return string
	 */
	private function get_publisher_id( $data = array() ) {
		// Check for per-ad override first.
		if ( ! empty( $data['publisher_id'] ) ) {
			return sanitize_text_field( $data['publisher_id'] );
		}

		return $this->publisher_id;
	}

	/**
	 * Render ad.
	 *
	 * @param int   $ad_id   Ad ID.
	 * @param array $options Options.
	 * @return string
	 */
	public function render( $ad_id, $options = array() ) {
		$data         = get_post_meta( $ad_id, '_wbam_ad_data', true );
		$slot_id      = isset( $data['slot_id'] ) ? $data['slot_id'] : '';
		$publisher_id = $this->get_publisher_id( $data );

		if ( empty( $slot_id ) || empty( $publisher_id ) ) {
			return '';
		}

		// 'Require consent for AdSense' covers ad units too, not only Auto
		// Ads: without consent there is no unit and no Google script.
		if ( ! Privacy_Helper::has_consent( 'marketing' ) ) {
			return '';
		}

		// Signal to maybe_enqueue_adsense_script() that an AdSense ad has
		// rendered, and with which Publisher ID.
		if ( '' === self::$ad_rendered ) {
			self::$ad_rendered = $publisher_id;
		}

		$ad_format    = isset( $data['ad_format'] ) ? $data['ad_format'] : 'auto';
		$ad_layout    = isset( $data['ad_layout'] ) ? $data['ad_layout'] : '';
		$responsive   = isset( $data['responsive'] ) ? $data['responsive'] : true;
		$fixed_width  = isset( $data['fixed_width'] ) ? absint( $data['fixed_width'] ) : 0;
		$fixed_height = isset( $data['fixed_height'] ) ? absint( $data['fixed_height'] ) : 0;

		$classes = array( 'wbam-ad', 'wbam-ad-adsense' );
		if ( ! empty( $options['class'] ) ) {
			$classes[] = sanitize_html_class( $options['class'] );
		}

		$placement = isset( $options['placement'] ) ? $options['placement'] : '';

		// Build style attribute.
		$style = 'display:block;';
		if ( ! $responsive && $fixed_width && $fixed_height ) {
			$style .= sprintf( 'width:%dpx;height:%dpx;', $fixed_width, $fixed_height );
		}

		// Build ins attributes.
		$ins_attrs = array(
			'class'          => 'adsbygoogle',
			'style'          => $style,
			'data-ad-client' => $publisher_id,
			'data-ad-slot'   => $slot_id,
		);

		// Add format-specific attributes.
		if ( 'auto' === $ad_format && $responsive ) {
			$ins_attrs['data-ad-format']             = 'auto';
			$ins_attrs['data-full-width-responsive'] = 'true';
		} elseif ( 'in-article' === $ad_format ) {
			$ins_attrs['data-ad-format'] = 'fluid';
			$ins_attrs['data-ad-layout'] = 'in-article';
		} elseif ( 'in-feed' === $ad_format ) {
			$ins_attrs['data-ad-format']     = 'fluid';
			$ins_attrs['data-ad-layout-key'] = ! empty( $ad_layout ) ? $ad_layout : '';
		} elseif ( 'multiplex' === $ad_format ) {
			$ins_attrs['data-ad-format'] = 'autorelaxed';
		} elseif ( ! empty( $ad_format ) && 'auto' !== $ad_format ) {
			$ins_attrs['data-ad-format'] = $ad_format;
		}

		// Build HTML.
		$html  = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" data-ad-id="' . esc_attr( $ad_id ) . '" data-placement="' . esc_attr( $placement ) . '">';
		$html .= '<ins';
		foreach ( $ins_attrs as $attr => $value ) {
			if ( ! empty( $value ) ) {
				$html .= sprintf( ' %s="%s"', esc_attr( $attr ), esc_attr( $value ) );
			}
		}
		$html .= '></ins>';
		$html .= '<script>(adsbygoogle = window.adsbygoogle || []).push({});</script>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Render metabox.
	 *
	 * @param int   $ad_id Ad ID.
	 * @param array $data  Data.
	 */
	public function render_metabox( $ad_id, $data ) {
		$slot_id      = isset( $data['slot_id'] ) ? $data['slot_id'] : '';
		$publisher_id = isset( $data['publisher_id'] ) ? $data['publisher_id'] : '';
		$ad_format    = isset( $data['ad_format'] ) ? $data['ad_format'] : 'auto';
		$ad_layout    = isset( $data['ad_layout'] ) ? $data['ad_layout'] : '';
		$responsive   = isset( $data['responsive'] ) ? $data['responsive'] : true;
		$fixed_width  = isset( $data['fixed_width'] ) ? $data['fixed_width'] : '';
		$fixed_height = isset( $data['fixed_height'] ) ? $data['fixed_height'] : '';

		$global_publisher_id = $this->publisher_id;
		?>
		<div class="wbam-field">
			<label for="wbam_adsense_slot_id"><?php esc_html_e( 'Ad Slot ID', 'wb-ads-rotator-with-split-test' ); ?> <span class="required">*</span></label>
			<div class="wbam-field-input">
				<input type="text" id="wbam_adsense_slot_id" name="wbam_data[slot_id]" value="<?php echo esc_attr( $slot_id ); ?>" class="regular-text" placeholder="1234567890" />
				<p class="description"><?php esc_html_e( 'Enter the Ad Slot ID from your AdSense account (e.g., 1234567890).', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>
		</div>

		<div class="wbam-field">
			<label for="wbam_adsense_publisher_id"><?php esc_html_e( 'Publisher ID (Optional)', 'wb-ads-rotator-with-split-test' ); ?></label>
			<div class="wbam-field-input">
				<input type="text" id="wbam_adsense_publisher_id" name="wbam_data[publisher_id]" value="<?php echo esc_attr( $publisher_id ); ?>" class="regular-text" placeholder="ca-pub-1234567890123456" />
				<p class="description">
					<?php
					if ( ! empty( $global_publisher_id ) ) {
						printf(
							/* translators: %s: Publisher ID */
							esc_html__( 'Leave empty to use global Publisher ID: %s', 'wb-ads-rotator-with-split-test' ),
							'<code>' . esc_html( $global_publisher_id ) . '</code>'
						);
					} else {
						esc_html_e( 'Enter your Publisher ID (e.g., ca-pub-1234567890123456) or set it in plugin settings.', 'wb-ads-rotator-with-split-test' );
					}
					?>
				</p>
			</div>
		</div>

		<div class="wbam-field">
			<label for="wbam_adsense_format"><?php esc_html_e( 'Ad Format', 'wb-ads-rotator-with-split-test' ); ?></label>
			<div class="wbam-field-input">
				<select id="wbam_adsense_format" name="wbam_data[ad_format]">
					<option value="auto" <?php selected( $ad_format, 'auto' ); ?>><?php esc_html_e( 'Auto (Responsive)', 'wb-ads-rotator-with-split-test' ); ?></option>
					<option value="horizontal" <?php selected( $ad_format, 'horizontal' ); ?>><?php esc_html_e( 'Horizontal Banner', 'wb-ads-rotator-with-split-test' ); ?></option>
					<option value="vertical" <?php selected( $ad_format, 'vertical' ); ?>><?php esc_html_e( 'Vertical Banner', 'wb-ads-rotator-with-split-test' ); ?></option>
					<option value="rectangle" <?php selected( $ad_format, 'rectangle' ); ?>><?php esc_html_e( 'Rectangle', 'wb-ads-rotator-with-split-test' ); ?></option>
					<option value="in-article" <?php selected( $ad_format, 'in-article' ); ?>><?php esc_html_e( 'In-Article', 'wb-ads-rotator-with-split-test' ); ?></option>
					<option value="in-feed" <?php selected( $ad_format, 'in-feed' ); ?>><?php esc_html_e( 'In-Feed', 'wb-ads-rotator-with-split-test' ); ?></option>
					<option value="multiplex" <?php selected( $ad_format, 'multiplex' ); ?>><?php esc_html_e( 'Multiplex', 'wb-ads-rotator-with-split-test' ); ?></option>
					<option value="fixed" <?php selected( $ad_format, 'fixed' ); ?>><?php esc_html_e( 'Fixed Size', 'wb-ads-rotator-with-split-test' ); ?></option>
				</select>
			</div>
		</div>

		<div class="wbam-field wbam-adsense-layout" <?php echo 'in-feed' !== $ad_format ? 'style="display:none;"' : ''; ?>>
			<label for="wbam_adsense_layout"><?php esc_html_e( 'Layout Key', 'wb-ads-rotator-with-split-test' ); ?></label>
			<div class="wbam-field-input">
				<input type="text" id="wbam_adsense_layout" name="wbam_data[ad_layout]" value="<?php echo esc_attr( $ad_layout ); ?>" class="regular-text" placeholder="-fb+5w+4e-db+86" />
				<p class="description"><?php esc_html_e( 'For in-feed ads, enter the layout key from AdSense.', 'wb-ads-rotator-with-split-test' ); ?></p>
			</div>
		</div>

		<div class="wbam-field wbam-adsense-fixed" <?php echo 'fixed' !== $ad_format ? 'style="display:none;"' : ''; ?>>
			<label><?php esc_html_e( 'Fixed Dimensions', 'wb-ads-rotator-with-split-test' ); ?></label>
			<div class="wbam-field-input wbam-dimensions">
				<input type="number" name="wbam_data[fixed_width]" value="<?php echo esc_attr( $fixed_width ); ?>" placeholder="<?php esc_attr_e( 'Width', 'wb-ads-rotator-with-split-test' ); ?>" aria-label="<?php esc_attr_e( 'Fixed width in pixels', 'wb-ads-rotator-with-split-test' ); ?>" min="0" style="width:100px;" />
				<span>×</span>
				<input type="number" name="wbam_data[fixed_height]" value="<?php echo esc_attr( $fixed_height ); ?>" placeholder="<?php esc_attr_e( 'Height', 'wb-ads-rotator-with-split-test' ); ?>" aria-label="<?php esc_attr_e( 'Fixed height in pixels', 'wb-ads-rotator-with-split-test' ); ?>" min="0" style="width:100px;" />
				<span>px</span>
			</div>
		</div>

		<div class="wbam-field wbam-adsense-responsive" <?php echo 'fixed' === $ad_format ? 'style="display:none;"' : ''; ?>>
			<label>
				<input type="checkbox" name="wbam_data[responsive]" value="1" <?php checked( $responsive ); ?> />
				<?php esc_html_e( 'Enable full-width responsive', 'wb-ads-rotator-with-split-test' ); ?>
			</label>
		</div>

		<script>
		jQuery(function($) {
			$('#wbam_adsense_format').on('change', function() {
				var format = $(this).val();
				$('.wbam-adsense-layout').toggle(format === 'in-feed');
				$('.wbam-adsense-fixed').toggle(format === 'fixed');
				$('.wbam-adsense-responsive').toggle(format !== 'fixed');
			});
		});
		</script>

		<?php if ( empty( $global_publisher_id ) ) : ?>
		<div class="notice notice-warning inline" style="margin-top: 15px;">
			<p>
				<?php
				printf(
					/* translators: %s: Settings link */
					esc_html__( 'No global Publisher ID configured. %s to set it up for all AdSense ads.', 'wb-ads-rotator-with-split-test' ),
					'<a href="' . esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-settings' ) ) . '">' . esc_html__( 'Go to Settings', 'wb-ads-rotator-with-split-test' ) . '</a>'
				);
				?>
			</p>
		</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Save data.
	 *
	 * @param int   $ad_id Ad ID.
	 * @param array $data  Data.
	 * @return array
	 */
	public function save( $ad_id, $data ) {
		return array(
			'slot_id'      => isset( $data['slot_id'] ) ? sanitize_text_field( $data['slot_id'] ) : '',
			'publisher_id' => isset( $data['publisher_id'] ) ? sanitize_text_field( $data['publisher_id'] ) : '',
			'ad_format'    => isset( $data['ad_format'] ) ? sanitize_key( $data['ad_format'] ) : 'auto',
			'ad_layout'    => isset( $data['ad_layout'] ) ? sanitize_text_field( $data['ad_layout'] ) : '',
			'responsive'   => isset( $data['responsive'] ) ? true : false,
			'fixed_width'  => isset( $data['fixed_width'] ) ? absint( $data['fixed_width'] ) : 0,
			'fixed_height' => isset( $data['fixed_height'] ) ? absint( $data['fixed_height'] ) : 0,
		);
	}
}
