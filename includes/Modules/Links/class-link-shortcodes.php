<?php
/**
 * Link Shortcodes Class
 *
 * Handles shortcodes for displaying links.
 *
 * @package WB_Ad_Manager
 * @since   2.1.0
 */

namespace WBAM\Modules\Links;

use WBAM\Core\Singleton;

/**
 * Link Shortcodes class.
 */
class Link_Shortcodes {

	use Singleton;

	/**
	 * Initialize shortcodes.
	 */
	public function init() {
		add_shortcode( 'wbam_link', array( $this, 'render_link' ) );
		add_shortcode( 'wbam_links', array( $this, 'render_links_list' ) );
		add_shortcode( 'wbam_link_url', array( $this, 'render_link_url' ) );
	}

	/**
	 * Render a single link.
	 *
	 * Shortcode: [wbam_link id="123" text="Click here"]
	 * Shortcode: [wbam_link slug="my-affiliate" text="Click here"]
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Shortcode content.
	 * @return string
	 */
	public function render_link( $atts, $content = '' ) {
		/**
		 * Filter default attributes for the wbam_link shortcode.
		 *
		 * @since 2.3.0
		 * @param array $defaults Default shortcode attributes.
		 */
		$defaults = apply_filters(
			'wbam_link_shortcode_defaults',
			array(
				'id'        => 0,
				'slug'      => '',
				'text'      => '',
				'class'     => '',
				'nofollow'  => '',
				'sponsored' => '',
				'new_tab'   => '',
			)
		);

		$atts = shortcode_atts( $defaults, $atts, 'wbam_link' );

		$link_manager = Link_Manager::get_instance();

		// Get link by ID or slug.
		if ( ! empty( $atts['id'] ) ) {
			$link = $link_manager->get( (int) $atts['id'] );
		} elseif ( ! empty( $atts['slug'] ) ) {
			$link = $link_manager->get_by_slug( sanitize_title( $atts['slug'] ) );
		} else {
			return '';
		}

		if ( ! $link || ! $link->is_active() ) {
			return '';
		}

		// Use shortcode content or text attribute or link name.
		$text = ! empty( $content ) ? $content : ( ! empty( $atts['text'] ) ? $atts['text'] : $link->name );

		// Get link attributes.
		$link_attrs = $link->get_attributes();

		// Add custom class.
		if ( ! empty( $atts['class'] ) ) {
			$link_attrs['class'] .= ' ' . esc_attr( $atts['class'] );
		}

		// Override rel attributes if specified.
		if ( '' !== $atts['nofollow'] || '' !== $atts['sponsored'] ) {
			$rel = array();

			$nofollow = '' !== $atts['nofollow'] ? filter_var( $atts['nofollow'], FILTER_VALIDATE_BOOLEAN ) : $link->nofollow;
			$sponsored = '' !== $atts['sponsored'] ? filter_var( $atts['sponsored'], FILTER_VALIDATE_BOOLEAN ) : $link->sponsored;

			if ( $nofollow ) {
				$rel[] = 'nofollow';
			}
			if ( $sponsored ) {
				$rel[] = 'sponsored';
			}

			$link_attrs['rel'] = implode( ' ', $rel );
		}

		// Override target if specified.
		if ( '' !== $atts['new_tab'] ) {
			$new_tab = filter_var( $atts['new_tab'], FILTER_VALIDATE_BOOLEAN );
			$link_attrs['target'] = $new_tab ? '_blank' : '_self';
		}

		/**
		 * Filter link HTML attributes before rendering.
		 *
		 * @since 2.3.0
		 * @param array $link_attrs HTML attributes for the link.
		 * @param Link  $link       Link object.
		 * @param array $atts       Shortcode attributes.
		 */
		$link_attrs = apply_filters( 'wbam_link_shortcode_attributes', $link_attrs, $link, $atts );

		// Build HTML.
		$html_attrs = array();
		foreach ( $link_attrs as $name => $value ) {
			if ( '' !== $value ) {
				$html_attrs[] = sprintf( '%s="%s"', esc_attr( $name ), esc_attr( $value ) );
			}
		}

		$output = sprintf( '<a %s>%s</a>', implode( ' ', $html_attrs ), esc_html( $text ) );

		/**
		 * Filter the final link shortcode output.
		 *
		 * @since 2.3.0
		 * @param string $output Link HTML output.
		 * @param Link   $link   Link object.
		 * @param array  $atts   Shortcode attributes.
		 * @param string $text   Link text.
		 */
		return apply_filters( 'wbam_link_shortcode_output', $output, $link, $atts, $text );
	}

	/**
	 * Render just the link URL.
	 *
	 * Shortcode: [wbam_link_url id="123"]
	 * Shortcode: [wbam_link_url slug="my-affiliate"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_link_url( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'   => 0,
				'slug' => '',
			),
			$atts,
			'wbam_link_url'
		);

		$link_manager = Link_Manager::get_instance();

		// Get link by ID or slug.
		if ( ! empty( $atts['id'] ) ) {
			$link = $link_manager->get( (int) $atts['id'] );
		} elseif ( ! empty( $atts['slug'] ) ) {
			$link = $link_manager->get_by_slug( sanitize_title( $atts['slug'] ) );
		} else {
			return '';
		}

		if ( ! $link || ! $link->is_active() ) {
			return '';
		}

		$url = esc_url( $link->get_url() );

		/**
		 * Filter the link URL shortcode output.
		 *
		 * @since 2.3.0
		 * @param string $url  The link URL.
		 * @param Link   $link Link object.
		 * @param array  $atts Shortcode attributes.
		 */
		return apply_filters( 'wbam_link_url_shortcode_output', $url, $link, $atts );
	}

	/**
	 * Render a list of links.
	 *
	 * Shortcode: [wbam_links category="1" type="affiliate" limit="10"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_links_list( $atts ) {
		/**
		 * Filter default attributes for the wbam_links shortcode.
		 *
		 * @since 2.3.0
		 * @param array $defaults Default shortcode attributes.
		 */
		$defaults = apply_filters(
			'wbam_links_shortcode_defaults',
			array(
				'category'   => 0,
				'type'       => '',
				'limit'      => 10,
				'orderby'    => 'name',
				'order'      => 'ASC',
				'class'      => '',
				'item_class' => '',
				'format'     => 'list',
			)
		);

		$atts = shortcode_atts( $defaults, $atts, 'wbam_links' );

		$link_manager = Link_Manager::get_instance();

		$args = array(
			'status'      => 'active',
			'category_id' => (int) $atts['category'],
			'link_type'   => sanitize_text_field( $atts['type'] ),
			'limit'       => (int) $atts['limit'],
			'orderby'     => sanitize_text_field( $atts['orderby'] ),
			'order'       => sanitize_text_field( $atts['order'] ),
		);

		/**
		 * Filter query arguments for the wbam_links shortcode.
		 *
		 * @since 2.3.0
		 * @param array $args Query arguments.
		 * @param array $atts Shortcode attributes.
		 */
		$args = apply_filters( 'wbam_links_shortcode_query_args', $args, $atts );

		$links = $link_manager->get_links( $args );

		if ( empty( $links ) ) {
			return '';
		}

		$container_class = 'wbam-links-list';
		if ( ! empty( $atts['class'] ) ) {
			$container_class .= ' ' . esc_attr( $atts['class'] );
		}

		$item_class = 'wbam-links-item';
		if ( ! empty( $atts['item_class'] ) ) {
			$item_class .= ' ' . esc_attr( $atts['item_class'] );
		}

		$output = '';

		switch ( $atts['format'] ) {
			case 'inline':
				$output .= '<span class="' . esc_attr( $container_class ) . '">';
				$items = array();
				foreach ( $links as $link ) {
					$items[] = '<span class="' . esc_attr( $item_class ) . '">' . $link->get_html() . '</span>';
				}
				$output .= implode( ', ', $items );
				$output .= '</span>';
				break;

			case 'grid':
				$output .= '<div class="' . esc_attr( $container_class ) . ' wbam-links-grid">';
				foreach ( $links as $link ) {
					$output .= '<div class="' . esc_attr( $item_class ) . '">';
					$output .= $link->get_html();
					if ( ! empty( $link->description ) ) {
						$output .= '<p class="wbam-link-description">' . esc_html( $link->description ) . '</p>';
					}
					$output .= '</div>';
				}
				$output .= '</div>';
				break;

			case 'list':
			default:
				$output .= '<ul class="' . esc_attr( $container_class ) . '">';
				foreach ( $links as $link ) {
					$output .= '<li class="' . esc_attr( $item_class ) . '">' . $link->get_html() . '</li>';
				}
				$output .= '</ul>';
				break;
		}

		/**
		 * Filter the links list shortcode output.
		 *
		 * @since 2.3.0
		 * @param string $output Links list HTML output.
		 * @param array  $links  Array of Link objects.
		 * @param array  $atts   Shortcode attributes.
		 */
		return apply_filters( 'wbam_links_shortcode_output', $output, $links, $atts );
	}
}
