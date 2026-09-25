<?php
/**
 * Jetonomy Module.
 *
 * Integrates WB Ad Manager with the Jetonomy discussion/forum plugin
 * (https://store.wbcomdesigns.com/jetonomy/). Registers one distinct
 * placement per template hook exposed by Jetonomy v1.3.0+ so each
 * position is independently selectable on the ad edit screen.
 *
 * Module + its placement class share this file because they share the
 * "is Jetonomy active" dependency and are small.
 *
 * phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Module + placement cohesive pair.
 *
 * @package WB_Ad_Manager
 * @since   2.8.0
 */

namespace WBAM\Modules\Jetonomy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WBAM\Modules\Placements\Placement_Engine;
use WBAM\Modules\Placements\Placement_Interface;

/**
 * Jetonomy module bootstrap.
 */
class Jetonomy_Module {

	/**
	 * Definition of every placement this module registers. Keeping the
	 * metadata in one table makes it trivial to add/remove a position
	 * later - just edit this list, no extra classes needed.
	 *
	 * @return array<int,array{id:string,name:string,description:string,hook:string,args:int}>
	 */
	public static function placements() {
		return array(
			array(
				'id'          => 'jetonomy_sidebar_before',
				'name'        => __( 'Sidebar Top', 'wb-ads-rotator-with-split-test' ),
				'description' => __( 'Display ads at the top of the Jetonomy sidebar, above all widgets.', 'wb-ads-rotator-with-split-test' ),
				'hook'        => 'jetonomy_sidebar_before',
				'args'        => 1,
			),
			array(
				'id'          => 'jetonomy_sidebar_after_about',
				'name'        => __( 'Sidebar After About', 'wb-ads-rotator-with-split-test' ),
				'description' => __( 'Display ads in the sidebar, right after the About card (space pages only).', 'wb-ads-rotator-with-split-test' ),
				'hook'        => 'jetonomy_sidebar_after_about',
				'args'        => 1,
			),
			array(
				'id'          => 'jetonomy_sidebar_after',
				'name'        => __( 'Sidebar Bottom', 'wb-ads-rotator-with-split-test' ),
				'description' => __( 'Display ads at the bottom of the Jetonomy sidebar, below all widgets.', 'wb-ads-rotator-with-split-test' ),
				'hook'        => 'jetonomy_sidebar_after',
				'args'        => 1,
			),
			array(
				'id'          => 'jetonomy_after_post_article',
				'name'        => __( 'After Topic Body', 'wb-ads-rotator-with-split-test' ),
				'description' => __( 'Display ads between the topic article and the replies section.', 'wb-ads-rotator-with-split-test' ),
				'hook'        => 'jetonomy_after_post_article',
				'args'        => 1,
			),
			array(
				'id'          => 'jetonomy_before_replies',
				'name'        => __( 'Before Replies', 'wb-ads-rotator-with-split-test' ),
				'description' => __( 'Display ads above the replies list on a single topic.', 'wb-ads-rotator-with-split-test' ),
				'hook'        => 'jetonomy_before_replies',
				'args'        => 2,
			),
			array(
				'id'          => 'jetonomy_between_replies',
				'name'        => __( 'Between Replies', 'wb-ads-rotator-with-split-test' ),
				'description' => __( 'Display ads after every Nth reply (frequency configurable on each ad).', 'wb-ads-rotator-with-split-test' ),
				'hook'        => 'jetonomy_between_replies',
				'args'        => 3,
			),
			array(
				'id'          => 'jetonomy_after_replies',
				'name'        => __( 'After Replies', 'wb-ads-rotator-with-split-test' ),
				'description' => __( 'Display ads below the replies list, above the reply composer.', 'wb-ads-rotator-with-split-test' ),
				'hook'        => 'jetonomy_after_replies',
				'args'        => 2,
			),
		);
	}

	/**
	 * Initialize the module when Jetonomy is active.
	 *
	 * Placement registration is deferred to the `init` action so the
	 * __() calls inside placements() fire after text-domain loading
	 * (WP 6.7+ warns on early translation loading).
	 */
	public function init() {
		if ( ! self::is_jetonomy_active() ) {
			return;
		}

		add_action( 'init', array( $this, 'register_placements' ), 5 );
		add_action( 'wp_head', array( __CLASS__, 'print_spacing_styles' ) );
	}

	/**
	 * Register every Jetonomy placement with the engine. Fires on init.
	 */
	public function register_placements() {
		$engine = Placement_Engine::get_instance();
		foreach ( self::placements() as $spec ) {
			$engine->register_placement( new Jetonomy_Placement( $spec ) );
		}
	}

	/**
	 * Detect whether Jetonomy is installed and loaded.
	 *
	 * @return bool
	 */
	public static function is_jetonomy_active() {
		return class_exists( '\\Jetonomy\\Jetonomy' ) || defined( 'JETONOMY_VERSION' );
	}

	/**
	 * Inline spacing styles so ad wrappers don't collide with Jetonomy
	 * cards, reply borders, or sidebar widgets. Emitted once per page.
	 */
	public static function print_spacing_styles() {
		echo '<style id="wbam-jetonomy-spacing">'
			. '.wbam-jetonomy-ad{margin:16px 0;padding:4px;box-sizing:border-box;}'
			. '.wbam-jetonomy-ad img{max-width:100%;height:auto;display:block;margin:0 auto;}'
			. '.wbam-jetonomy-sidebar_before,.wbam-jetonomy-sidebar_after_about,.wbam-jetonomy-sidebar_after{margin:0 0 16px 0;}'
			. '.wbam-jetonomy-between_replies{margin:12px 0;}'
			. '</style>';
	}
}

/**
 * Placement for a single Jetonomy position.
 *
 * One instance per row in Jetonomy_Module::placements(). Using a single
 * class parameterised by spec keeps the file short while still exposing
 * each position as an independent checkbox in the ad admin UI.
 */
class Jetonomy_Placement implements Placement_Interface {

	/**
	 * Placement spec: id, name, description, hook, args.
	 *
	 * @var array
	 */
	private $spec;

	/**
	 * @param array $spec Placement definition (see Jetonomy_Module::placements()).
	 */
	public function __construct( array $spec ) {
		$this->spec = $spec;
	}

	public function get_id() {
		return $this->spec['id'];
	}

	public function get_name() {
		return $this->spec['name'];
	}

	public function get_description() {
		return $this->spec['description'];
	}

	public function get_group() {
		return 'jetonomy';
	}

	public function is_available() {
		return Jetonomy_Module::is_jetonomy_active();
	}

	public function show_in_selector() {
		return true;
	}

	/**
	 * Wire the Jetonomy action hook to our render callback.
	 */
	public function register() {
		add_action( $this->spec['hook'], array( $this, 'render' ), 10, $this->spec['args'] );
	}

	/**
	 * Render all ads assigned to this placement.
	 *
	 * For `jetonomy_between_replies` we apply frequency logic so the ad
	 * fires every Nth reply rather than after every single one.
	 *
	 * @param mixed ...$args Passed through from Jetonomy's do_action().
	 */
	public function render( ...$args ) {
		$engine = Placement_Engine::get_instance();
		$ads    = $engine->get_ads_for_placement( $this->get_id() );

		if ( empty( $ads ) ) {
			return;
		}

		// Between-replies frequency: $args = [$reply, $index, $post].
		$is_between = 'jetonomy_between_replies' === $this->spec['id'];
		$index_one  = $is_between ? ( (int) ( $args[1] ?? 0 ) ) + 1 : 0;

		foreach ( $ads as $ad_id ) {
			if ( $is_between ) {
				$data   = get_post_meta( $ad_id, '_wbam_ad_data', true );
				$every  = isset( $data['jetonomy_every'] ) ? max( 1, absint( $data['jetonomy_every'] ) ) : 5;
				$repeat = ! empty( $data['jetonomy_repeat'] );
				$show   = $repeat ? ( 0 === $index_one % $every ) : ( $index_one === $every );

				if ( ! $show ) {
					continue;
				}
			}

			$html = $engine->render_ad( $ad_id, array( 'placement' => $this->get_id() ) );
			if ( empty( $html ) ) {
				continue;
			}

			printf(
				'<div class="wbam-jetonomy-ad wbam-jetonomy-%s">%s</div>',
				esc_attr( str_replace( 'jetonomy_', '', $this->spec['id'] ) ),
				$html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered upstream.
			);
		}
	}

	/**
	 * Render admin options. Only `jetonomy_between_replies` needs extra
	 * inputs (frequency + repeat). Other positions have no extra config.
	 *
	 * @param int   $ad_id Ad ID.
	 * @param array $data  Saved ad data.
	 */
	public function render_options( $ad_id, $data ) {
		if ( 'jetonomy_between_replies' !== $this->spec['id'] ) {
			return;
		}

		$every  = isset( $data['jetonomy_every'] ) ? absint( $data['jetonomy_every'] ) : 5;
		$repeat = ! empty( $data['jetonomy_repeat'] );
		?>
		<div class="wbam-placement-extra">
			<label for="wbam_jetonomy_every"><?php esc_html_e( 'Show after every', 'wb-ads-rotator-with-split-test' ); ?></label>
			<input type="number" id="wbam_jetonomy_every" name="wbam_data[jetonomy_every]" value="<?php echo esc_attr( $every ); ?>" min="1" max="50" class="small-text" />
			<?php esc_html_e( 'replies', 'wb-ads-rotator-with-split-test' ); ?>
			<label>
				<input type="checkbox" name="wbam_data[jetonomy_repeat]" value="1" <?php checked( $repeat ); ?> />
				<?php esc_html_e( 'Repeat every N replies (instead of only once)', 'wb-ads-rotator-with-split-test' ); ?>
			</label>
		</div>
		<?php
	}

	/**
	 * Sanitize and return placement-specific settings.
	 *
	 * @param int   $ad_id Ad ID.
	 * @param array $data  Raw posted data.
	 * @return array
	 */
	public function save_options( $ad_id, $data ) {
		if ( 'jetonomy_between_replies' !== $this->spec['id'] ) {
			return array();
		}

		return array(
			'jetonomy_every'  => isset( $data['jetonomy_every'] ) ? max( 1, absint( $data['jetonomy_every'] ) ) : 5,
			'jetonomy_repeat' => ! empty( $data['jetonomy_repeat'] ),
		);
	}
}
