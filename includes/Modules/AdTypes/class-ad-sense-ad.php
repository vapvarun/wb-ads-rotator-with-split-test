<?php
/**
 * Google AdSense Ad Type
 *
 * @package WB_Ad_Manager
 * @since   1.0.0
 */

namespace WBAM\Modules\AdTypes;

/**
 * AdSense Ad class.
 */
class AdSense_Ad implements Ad_Type_Interface {

	/**
	 * Flag to track if AdSense script has been enqueued.
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
		$settings             = get_option( 'wbam_settings', array() );
		$this->publisher_id   = isset( $settings['adsense_publisher_id'] ) ? $settings['adsense_publisher_id'] : '';

		// Enqueue AdSense script in footer if we have ads.
		add_action( 'wp_footer', array( $this, 'maybe_enqueue_adsense_script' ), 5 );
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
		return __( 'Google AdSense', 'wb-ad-manager' );
	}

	/**
	 * Get description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Display Google AdSense ads with automatic script management.', 'wb-ad-manager' );
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
	 * Only loads if AdSense ads are being displayed on the page.
	 */
	public function maybe_enqueue_adsense_script() {
		if ( ! self::$script_enqueued ) {
			return;
		}

		// Skip if Auto Ads is enabled (script already in head).
		$settings = get_option( 'wbam_settings', array() );
		if ( ! empty( $settings['adsense_auto_ads'] ) && ! empty( $settings['adsense_publisher_id'] ) ) {
			return;
		}

		$publisher_id = $this->get_publisher_id();
		if ( empty( $publisher_id ) ) {
			return;
		}

		// Output the AdSense script once.
		printf(
			'<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=%s" crossorigin="anonymous"></script>',
			esc_attr( $publisher_id )
		);
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

		// Mark that we need the AdSense script.
		self::$script_enqueued = true;

		$ad_format   = isset( $data['ad_format'] ) ? $data['ad_format'] : 'auto';
		$ad_layout   = isset( $data['ad_layout'] ) ? $data['ad_layout'] : '';
		$responsive  = isset( $data['responsive'] ) ? $data['responsive'] : true;
		$fixed_width = isset( $data['fixed_width'] ) ? absint( $data['fixed_width'] ) : 0;
		$fixed_height = isset( $data['fixed_height'] ) ? absint( $data['fixed_height'] ) : 0;

		$classes = array( 'wbam-ad', 'wbam-ad-adsense' );
		if ( ! empty( $options['class'] ) ) {
			$classes[] = sanitize_html_class( $options['class'] );
		}

		// Build style attribute.
		$style = 'display:block;';
		if ( ! $responsive && $fixed_width && $fixed_height ) {
			$style .= sprintf( 'width:%dpx;height:%dpx;', $fixed_width, $fixed_height );
		}

		// Build ins attributes.
		$ins_attrs = array(
			'class'                  => 'adsbygoogle',
			'style'                  => $style,
			'data-ad-client'         => $publisher_id,
			'data-ad-slot'           => $slot_id,
		);

		// Add format-specific attributes.
		if ( 'auto' === $ad_format && $responsive ) {
			$ins_attrs['data-ad-format']      = 'auto';
			$ins_attrs['data-full-width-responsive'] = 'true';
		} elseif ( 'in-article' === $ad_format ) {
			$ins_attrs['data-ad-format']  = 'fluid';
			$ins_attrs['data-ad-layout']  = 'in-article';
		} elseif ( 'in-feed' === $ad_format ) {
			$ins_attrs['data-ad-format']       = 'fluid';
			$ins_attrs['data-ad-layout-key']   = ! empty( $ad_layout ) ? $ad_layout : '';
		} elseif ( 'multiplex' === $ad_format ) {
			$ins_attrs['data-ad-format']  = 'autorelaxed';
		} elseif ( ! empty( $ad_format ) && 'auto' !== $ad_format ) {
			$ins_attrs['data-ad-format'] = $ad_format;
		}

		// Build HTML.
		$html = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" data-ad-id="' . esc_attr( $ad_id ) . '">';
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
			<label for="wbam_adsense_slot_id"><?php esc_html_e( 'Ad Slot ID', 'wb-ad-manager' ); ?> <span class="required">*</span></label>
			<div class="wbam-field-input">
				<input type="text" id="wbam_adsense_slot_id" name="wbam_data[slot_id]" value="<?php echo esc_attr( $slot_id ); ?>" class="regular-text" placeholder="1234567890" />
				<p class="description"><?php esc_html_e( 'Enter the Ad Slot ID from your AdSense account (e.g., 1234567890).', 'wb-ad-manager' ); ?></p>
			</div>
		</div>

		<div class="wbam-field">
			<label for="wbam_adsense_publisher_id"><?php esc_html_e( 'Publisher ID (Optional)', 'wb-ad-manager' ); ?></label>
			<div class="wbam-field-input">
				<input type="text" id="wbam_adsense_publisher_id" name="wbam_data[publisher_id]" value="<?php echo esc_attr( $publisher_id ); ?>" class="regular-text" placeholder="ca-pub-1234567890123456" />
				<p class="description">
					<?php
					if ( ! empty( $global_publisher_id ) ) {
						printf(
							/* translators: %s: Publisher ID */
							esc_html__( 'Leave empty to use global Publisher ID: %s', 'wb-ad-manager' ),
							'<code>' . esc_html( $global_publisher_id ) . '</code>'
						);
					} else {
						esc_html_e( 'Enter your Publisher ID (e.g., ca-pub-1234567890123456) or set it in plugin settings.', 'wb-ad-manager' );
					}
					?>
				</p>
			</div>
		</div>

		<div class="wbam-field">
			<label for="wbam_adsense_format"><?php esc_html_e( 'Ad Format', 'wb-ad-manager' ); ?></label>
			<div class="wbam-field-input">
				<select id="wbam_adsense_format" name="wbam_data[ad_format]">
					<option value="auto" <?php selected( $ad_format, 'auto' ); ?>><?php esc_html_e( 'Auto (Responsive)', 'wb-ad-manager' ); ?></option>
					<option value="horizontal" <?php selected( $ad_format, 'horizontal' ); ?>><?php esc_html_e( 'Horizontal Banner', 'wb-ad-manager' ); ?></option>
					<option value="vertical" <?php selected( $ad_format, 'vertical' ); ?>><?php esc_html_e( 'Vertical Banner', 'wb-ad-manager' ); ?></option>
					<option value="rectangle" <?php selected( $ad_format, 'rectangle' ); ?>><?php esc_html_e( 'Rectangle', 'wb-ad-manager' ); ?></option>
					<option value="in-article" <?php selected( $ad_format, 'in-article' ); ?>><?php esc_html_e( 'In-Article', 'wb-ad-manager' ); ?></option>
					<option value="in-feed" <?php selected( $ad_format, 'in-feed' ); ?>><?php esc_html_e( 'In-Feed', 'wb-ad-manager' ); ?></option>
					<option value="multiplex" <?php selected( $ad_format, 'multiplex' ); ?>><?php esc_html_e( 'Multiplex', 'wb-ad-manager' ); ?></option>
					<option value="fixed" <?php selected( $ad_format, 'fixed' ); ?>><?php esc_html_e( 'Fixed Size', 'wb-ad-manager' ); ?></option>
				</select>
			</div>
		</div>

		<div class="wbam-field wbam-adsense-layout" <?php echo 'in-feed' !== $ad_format ? 'style="display:none;"' : ''; ?>>
			<label for="wbam_adsense_layout"><?php esc_html_e( 'Layout Key', 'wb-ad-manager' ); ?></label>
			<div class="wbam-field-input">
				<input type="text" id="wbam_adsense_layout" name="wbam_data[ad_layout]" value="<?php echo esc_attr( $ad_layout ); ?>" class="regular-text" placeholder="-fb+5w+4e-db+86" />
				<p class="description"><?php esc_html_e( 'For in-feed ads, enter the layout key from AdSense.', 'wb-ad-manager' ); ?></p>
			</div>
		</div>

		<div class="wbam-field wbam-adsense-fixed" <?php echo 'fixed' !== $ad_format ? 'style="display:none;"' : ''; ?>>
			<label><?php esc_html_e( 'Fixed Dimensions', 'wb-ad-manager' ); ?></label>
			<div class="wbam-field-input wbam-dimensions">
				<input type="number" name="wbam_data[fixed_width]" value="<?php echo esc_attr( $fixed_width ); ?>" placeholder="<?php esc_attr_e( 'Width', 'wb-ad-manager' ); ?>" min="0" style="width:100px;" />
				<span>×</span>
				<input type="number" name="wbam_data[fixed_height]" value="<?php echo esc_attr( $fixed_height ); ?>" placeholder="<?php esc_attr_e( 'Height', 'wb-ad-manager' ); ?>" min="0" style="width:100px;" />
				<span>px</span>
			</div>
		</div>

		<div class="wbam-field wbam-adsense-responsive" <?php echo 'fixed' === $ad_format ? 'style="display:none;"' : ''; ?>>
			<label>
				<input type="checkbox" name="wbam_data[responsive]" value="1" <?php checked( $responsive ); ?> />
				<?php esc_html_e( 'Enable full-width responsive', 'wb-ad-manager' ); ?>
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
					esc_html__( 'No global Publisher ID configured. %s to set it up for all AdSense ads.', 'wb-ad-manager' ),
					'<a href="' . esc_url( admin_url( 'edit.php?post_type=wbam-ad&page=wbam-settings' ) ) . '">' . esc_html__( 'Go to Settings', 'wb-ad-manager' ) . '</a>'
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
