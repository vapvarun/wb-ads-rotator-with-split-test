<?php
/**
 * WooCommerce credit orders: the order says the wallet was credited. Since SDK
 * 1.8.0 the adapter itself takes credits back on a refund or cancel (capped at
 * the unspent balance); the plugin only books that reversal as revenue.
 * WooCommerce is not loaded in this suite, so the order is a stand-in with the
 * WC_Order methods the handlers call; the hooks pass the order object.
 *
 * @package WBAM\Tests
 */

namespace WBAM\Tests\Pro;

use WBAM_Pro\Core\Credits_Bridge;
use WBAM_Pro\Modules\Advertisers\Advertiser_Manager;
use Wbcom\Credits\Gateways\Processed_Events;

class Test_Woo_Credit_Order_Lifecycle extends Pro_Test_Case {

	private const ORDER_ID   = 424242;
	private const PRODUCT_ID = 9001;

	private object $advertiser;
	private object $order;

	public function set_up(): void {
		parent::set_up();

		$user             = (int) self::factory()->user->create();
		$this->advertiser = Advertiser_Manager::get_instance()->get_or_create( $user );

		update_option( 'wbam-pro_credit_mappings', array( 'woocommerce' => array( self::PRODUCT_ID => 25 ) ) );

		$this->order = new class( $user, self::PRODUCT_ID ) {
			public array $notes = array();
			private int $user;
			private int $product;

			public function __construct( int $user, int $product ) {
				$this->user    = $user;
				$this->product = $product;
			}

			public function get_customer_id() {
				return $this->user;
			}

			public function get_items() {
				$product = $this->product;
				return array(
					new class( $product ) {
						private int $product;

						public function __construct( int $product ) {
							$this->product = $product;
						}

						public function get_product_id() {
							return $this->product;
						}

						public function get_quantity() {
							return 2;
						}
					},
				);
			}

			public function add_order_note( $note ) {
				$this->notes[] = $note;
			}
		};

		// What the SDK adapter does when the order is paid: claim, then top up.
		Processed_Events::maybe_create_table( 'wbam' );
		Processed_Events::claim( 'wbam-pro', 'adapter:woocommerce', 'woo:order:' . self::ORDER_ID );
		\Wbcom\Credits\Credits::topup( 'wbam-pro', $user, 5000, 'Credits from WooCommerce order #' . self::ORDER_ID );
	}

	public function tear_down(): void {
		delete_option( 'wbam-pro_credit_mappings' );
		Processed_Events::reset_for_tests( 'wbam-pro', 'adapter:woocommerce' );
		parent::tear_down();
	}

	public function test_paid_order_gets_one_wallet_note(): void {
		Credits_Bridge::get_instance()->note_woo_order_credited( self::ORDER_ID, $this->order );
		Credits_Bridge::get_instance()->note_woo_order_credited( self::ORDER_ID, $this->order );

		$this->assertCount( 1, $this->order->notes, 'completed and processing both fire; one note.' );
		$this->assertStringContainsString( '50.00', $this->order->notes[0] );
	}

	/**
	 * SDK 1.8.0's WooCommerce adapter takes the credits back itself on a
	 * refund or cancel (capped at the unspent balance). A second reversal
	 * here deducted the same order twice.
	 */
	public function test_the_plugin_no_longer_reverses_woo_orders_itself(): void {
		$bridge = Credits_Bridge::get_instance();
		$this->assertFalse( has_action( 'woocommerce_order_status_refunded', array( $bridge, 'reverse_woo_order_credits' ) ) );
		$this->assertFalse( has_action( 'woocommerce_order_status_cancelled', array( $bridge, 'reverse_woo_order_credits' ) ) );
		$this->assertFalse( method_exists( $bridge, 'reverse_woo_order_credits' ) );
	}

	/**
	 * The adapter announces its reversal on wbcom_credits_refunded; that is
	 * booked once as a top-up refund, less revenue.
	 */
	public function test_an_adapter_refund_is_booked_as_a_topup_refund(): void {
		global $wpdb;
		$user   = (int) $this->order->get_customer_id();
		$ledger = \Wbcom\Credits\Credits::adjust( 'wbam-pro', $user, -2000, 'Refund of WooCommerce order' );
		$this->assertNotFalse( $ledger );

		$context = array(
			'gateway'    => 'woocommerce',
			'session_id' => 'woo:order:' . self::ORDER_ID,
			'ledger_id'  => (int) $ledger,
			'reason'     => 'gateway_refund',
		);
		do_action( 'wbcom_credits_refunded', 'wbam-pro', $user, 2000, $context );
		do_action( 'wbcom_credits_refunded', 'wbam-pro', $user, 2000, $context ); // Replayed.

		$table = \WBAM_Pro\Core\Revenue_Ledger::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- test assertion.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT source, amount FROM {$table} WHERE ledger_id = %d", (int) $ledger ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$this->assertCount( 1, $rows );
		$this->assertSame( \WBAM_Pro\Core\Revenue_Ledger::SOURCE_TOPUP_REFUND, $rows[0]->source );
		$this->assertSame( -2000, (int) $rows[0]->amount );
	}

	/** Stripe and PayPal also fire the generic hook: booked once, by the gateway listener. */
	public function test_a_gateway_refund_on_the_generic_hook_is_not_booked_twice(): void {
		global $wpdb;
		$user   = (int) $this->order->get_customer_id();
		$ledger = \Wbcom\Credits\Credits::adjust( 'wbam-pro', $user, -1000, 'Stripe refund' );

		do_action( 'wbcom_credits_refunded', 'wbam-pro', $user, 1000, array( 'gateway' => 'stripe', 'ledger_id' => (int) $ledger ) );

		$table = \WBAM_Pro\Core\Revenue_Ledger::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- test assertion.
		$this->assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE ledger_id = %d", (int) $ledger ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
