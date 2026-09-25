<?php
/**
 * WooCommerce credit orders: the order says the wallet was credited, and a
 * refunded or cancelled order takes the credits back, once.
 *
 * The SDK adapter only listens for completed/processing, so a refunded order
 * kept its credits, and nothing on the order told the owner why it completed.
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

	public function test_refunded_order_takes_the_credits_back_once(): void {
		Credits_Bridge::get_instance()->reverse_woo_order_credits( self::ORDER_ID, $this->order );
		// Cancelled after refunded, or a replayed hook: nothing more.
		Credits_Bridge::get_instance()->reverse_woo_order_credits( self::ORDER_ID, $this->order );

		$this->assertSame( 0.0, (float) Credits_Bridge::get_balance( $this->advertiser->id ) );
		$this->assertCount( 1, $this->order->notes );
	}

	public function test_an_order_the_adapter_never_credited_is_left_alone(): void {
		Credits_Bridge::get_instance()->reverse_woo_order_credits( self::ORDER_ID + 1, $this->order );

		$this->assertSame( 50.0, (float) Credits_Bridge::get_balance( $this->advertiser->id ) );
	}
}
