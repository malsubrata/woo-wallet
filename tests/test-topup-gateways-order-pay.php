<?php
/**
 * S1 (1.7.4): "Allowed Payment Gateways" for top-ups also applies when an
 * existing top-up order is paid (order-pay page, Store API order-pay route)
 * and to the REST top-up.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers WOO_Wallet_Helper::get_topup_allowed_gateways
 * @covers Woo_Wallet_Frontend::woocommerce_available_payment_gateways
 * @covers Woo_Wallet_Frontend::remember_store_api_pay_order
 * @covers Woo_Wallet_Frontend::refuse_topup_store_api_gateway
 * @covers ::is_wallet_rechargeable_order
 * @covers WooWallet_Topup_Service::create_order
 */
class Test_Topup_Gateways_Order_Pay extends WP_UnitTestCase {

	/**
	 * Customer id.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Gateway list before the test, restored in tear_down.
	 *
	 * @var array
	 */
	private $saved_gateways;

	/**
	 * Enable bacs, cheque, cod, a stub online gateway and a stub wallet gateway.
	 */
	public function set_up() {
		parent::set_up();
		$this->user_id        = self::factory()->user->create( array( 'role' => 'customer' ) );
		$this->saved_gateways = WC()->payment_gateways()->payment_gateways;

		$gateways = array();
		$saved    = WC()->payment_gateways()->payment_gateways();
		foreach ( array( 'bacs', 'cheque', 'cod' ) as $id ) {
			$gateways[ $id ]          = clone $saved[ $id ];
			$gateways[ $id ]->enabled = 'yes';
		}
		$gateways['tw_online'] = $this->stub( 'tw_online' );
		$gateways['wallet']    = $this->stub( 'wallet' );
		WC()->payment_gateways()->payment_gateways = $gateways;
		delete_option( '_wallet_settings_general' );
		wp_set_current_user( $this->user_id );
		require_once WOO_WALLET_ABSPATH . 'includes/services/class-woo-wallet-topup-service.php';
	}

	/**
	 * Restore gateways, query var and the remembered Store API order.
	 */
	public function tear_down() {
		WC()->payment_gateways()->payment_gateways = $this->saved_gateways;
		set_query_var( 'order-pay', '' );
		if ( method_exists( 'Woo_Wallet_Frontend', 'remember_store_api_pay_order' ) ) { // Lets the suite roll back on pre-1.7.4 code.
			$this->store_api_route( '/wc/store/v1/cart' );
		}
		remove_all_filters( 'woo_wallet_is_wallet_rechargeable_cart' );
		parent::tear_down();
	}

	/**
	 * Enabled stub gateway.
	 *
	 * @param string $id Gateway id.
	 * @return WC_Payment_Gateway
	 */
	private function stub( $id ) {
		$gateway          = new class() extends WC_Payment_Gateway {
			/**
			 * No settings.
			 */
			public function __construct() {}
		};
		$gateway->id      = $id;
		$gateway->title   = $id;
		$gateway->enabled = 'yes';
		return $gateway;
	}

	/**
	 * Saved order with one line.
	 *
	 * @param bool $topup Top-up product line, else a normal product.
	 * @return WC_Order
	 */
	private function order( $topup ) {
		if ( $topup ) {
			$product_id = get_wallet_rechargeable_product()->get_id();
		} else {
			$product = new WC_Product_Simple();
			$product->set_regular_price( 10 );
			$product->save();
			$product_id = $product->get_id();
		}
		$item = new WC_Order_Item_Product();
		$item->set_product_id( $product_id );
		$item->set_quantity( 1 );
		$item->set_subtotal( 10 );
		$item->set_total( 10 );
		$order = new WC_Order();
		$order->set_customer_id( $this->user_id );
		$order->add_item( $item );
		$order->set_total( 10 );
		$order->save();
		return $order;
	}

	/**
	 * Run the gateway filter.
	 *
	 * @return array Offered gateway ids.
	 */
	private function offered() {
		return array_keys( Woo_Wallet_Frontend::instance()->woocommerce_available_payment_gateways( WC()->payment_gateways()->payment_gateways() ) );
	}

	/**
	 * Simulate a REST request reaching a route: the Store API order-pay
	 * handler for `checkout/<id>` routes (any case, as WordPress matches),
	 * no handler otherwise.
	 *
	 * @param string $route REST route.
	 */
	private function store_api_route( $route ) {
		$request = new WP_REST_Request( 'POST', $route );
		$handler = array();
		if ( preg_match( '#/checkout/(\d+)$#i', $route, $m ) ) {
			$route_object = $this->getMockBuilder( 'Automattic\WooCommerce\StoreApi\Routes\V1\CheckoutOrder' )->disableOriginalConstructor()->getMock();
			$handler      = array( 'callback' => array( $route_object, 'get_response' ) );
			$request->set_url_params( array( 'id' => $m[1] ) );
		}
		Woo_Wallet_Frontend::instance()->remember_store_api_pay_order( null, $handler, $request );
	}

	/**
	 * Order-pay page for a top-up order offers only the allowed list (default:
	 * online gateways), never offline gateways or the wallet. Fails on 1.7.3.
	 */
	public function test_order_pay_topup_offers_only_allowed() {
		set_query_var( 'order-pay', $this->order( true )->get_id() );
		$this->assertSame( array( 'tw_online' ), $this->offered() );
	}

	/**
	 * A saved list is followed on order-pay too.
	 */
	public function test_order_pay_topup_follows_saved_list() {
		update_option( '_wallet_settings_general', array( 'allowed_payment_gateways' => array( 'bacs' ) ) );
		set_query_var( 'order-pay', $this->order( true )->get_id() );
		$this->assertSame( array( 'bacs' ), $this->offered() );
	}

	/**
	 * The wallet gateway is never offered for a top-up, even if a saved list
	 * contains it.
	 */
	public function test_wallet_never_offered_for_topup() {
		update_option( '_wallet_settings_general', array( 'allowed_payment_gateways' => array( 'wallet', 'tw_online' ) ) );
		set_query_var( 'order-pay', $this->order( true )->get_id() );
		$this->assertSame( array( 'tw_online' ), $this->offered() );
	}

	/**
	 * A normal order on order-pay offers every gateway.
	 */
	public function test_order_pay_normal_order_offers_all() {
		set_query_var( 'order-pay', $this->order( false )->get_id() );
		$this->assertSame( array( 'bacs', 'cheque', 'cod', 'tw_online', 'wallet' ), $this->offered() );
	}

	/**
	 * A top-up cart stays restricted when a request carries the `order-pay`
	 * query var of a normal order (or the Store API route of one).
	 */
	public function test_topup_cart_restricted_whatever_order_pay_says() {
		add_filter( 'woo_wallet_is_wallet_rechargeable_cart', '__return_true' );
		$normal = $this->order( false )->get_id();

		set_query_var( 'order-pay', $normal );
		$this->assertSame( array( 'tw_online' ), $this->offered() );

		set_query_var( 'order-pay', '' );
		$this->store_api_route( '/wc/store/v1/checkout/' . $normal );
		$this->assertSame( array( 'tw_online' ), $this->offered() );
	}

	/**
	 * Cart checkout still restricted.
	 */
	public function test_cart_topup_still_restricted() {
		add_filter( 'woo_wallet_is_wallet_rechargeable_cart', '__return_true' );
		$this->assertSame( array( 'tw_online' ), $this->offered() );
	}

	/**
	 * Store API order-pay route (`/wc/store/v1/checkout/<id>`) for a top-up
	 * order is restricted; another route resets it. Fails on 1.7.3.
	 */
	public function test_store_api_order_pay_topup_restricted() {
		$order = $this->order( true );
		$this->store_api_route( '/wc/store/v1/checkout/' . $order->get_id() );
		$this->assertSame( array( 'tw_online' ), $this->offered() );

		$this->store_api_route( '/wc/store/checkout/' . $order->get_id() );
		$this->assertSame( array( 'tw_online' ), $this->offered(), 'Unversioned namespace.' );

		$this->store_api_route( '/WC/Store/v1/CHECKOUT/' . $order->get_id() );
		$this->assertSame( array( 'tw_online' ), $this->offered(), 'Route case does not matter.' );

		$this->store_api_route( '/wc/store/v1/cart' );
		$this->assertContains( 'bacs', $this->offered() );
	}

	/**
	 * A route that only looks like order-pay (no Store API order-pay handler)
	 * is not treated as one.
	 */
	public function test_lookalike_route_without_handler_ignored() {
		$order = $this->order( true );
		Woo_Wallet_Frontend::instance()->remember_store_api_pay_order( null, array( 'callback' => '__return_true' ), new WP_REST_Request( 'POST', '/other/v1/checkout/' . $order->get_id() ) );
		$this->assertContains( 'bacs', $this->offered() );
	}

	/**
	 * Real Store API dispatch with an upper-case route: a top-up order cannot
	 * be paid with a gateway outside the list. Fails on round-2 code.
	 *
	 * @dataProvider store_api_paths
	 * @param string $path Route path template.
	 */
	public function test_store_api_dispatch_refuses_not_allowed_gateway( $path ) {
		$order = $this->order( true );
		$order->set_billing_email( 'topup@example.com' );
		$order->save();
		add_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );

		$request = new WP_REST_Request( 'POST', sprintf( $path, $order->get_id() ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'key'             => $order->get_order_key(),
					'billing_email'   => 'topup@example.com',
					'billing_address' => array(
						'first_name' => 'T',
						'last_name'  => 'U',
						'address_1'  => '1 St',
						'city'       => 'London',
						'postcode'   => 'SW1A 1AA',
						'country'    => 'GB',
						'email'      => 'topup@example.com',
						'phone'      => '0123456789',
					),
					'payment_method'  => 'bacs',
				)
			)
		);
		$response = rest_get_server()->dispatch( $request );
		remove_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );

		$data = $response->get_data();
		$this->assertSame( 400, $response->get_status(), wp_json_encode( $data ) );
		$this->assertContains( $data['code'], array( 'woocommerce_rest_checkout_payment_method_disabled', 'woo_wallet_topup_payment_method_not_allowed' ), wp_json_encode( $data ) );
		$order = wc_get_order( $order->get_id() );
		$this->assertTrue( $order->needs_payment() );
		$this->assertSame( 'pending', $order->get_status() );
	}

	/**
	 * Route spellings WordPress dispatches to the Store API order-pay route.
	 *
	 * @return array
	 */
	public function store_api_paths() {
		return array( array( '/wc/store/v1/checkout/%d' ), array( '/wc/store/v1/CHECKOUT/%d' ), array( '/WC/STORE/V1/Checkout/%d' ) );
	}

	/**
	 * Payment-time guard: a top-up order whose gateway is outside the list is
	 * refused even when the order-pay route was not recognised; an allowed
	 * gateway and a normal order pass.
	 */
	public function test_store_api_payment_guard() {
		$frontend = Woo_Wallet_Frontend::instance();

		$normal = $this->order( false );
		$normal->set_payment_method( 'bacs' );
		$frontend->refuse_topup_store_api_gateway( $normal );

		$topup = $this->order( true );
		$topup->set_payment_method( 'tw_online' );
		$frontend->refuse_topup_store_api_gateway( $topup );

		foreach ( array( 'bacs', 'wallet', '' ) as $method ) {
			$topup->set_payment_method( $method );
			try {
				$frontend->refuse_topup_store_api_gateway( $topup );
				$this->fail( "Gateway '$method' was not refused." );
			} catch ( Automattic\WooCommerce\StoreApi\Exceptions\RouteException $e ) {
				$this->assertSame( 'woo_wallet_topup_payment_method_not_allowed', $e->getErrorCode() );
			}
		}
	}

	/**
	 * Top-up product cannot be loaded → order-pay gateway filter does not
	 * fatal (is_wallet_rechargeable_order guards the lookup).
	 */
	public function test_missing_topup_product_does_not_fatal() {
		$order = $this->order( true );
		add_filter( 'woo_wallet_rechargeable_product_id', '__return_zero' );
		set_query_var( 'order-pay', $order->get_id() );
		$offered = $this->offered();
		remove_filter( 'woo_wallet_rechargeable_product_id', '__return_zero' );
		$this->assertCount( 5, $offered );
	}

	/**
	 * Store API order-pay for a normal order is untouched.
	 */
	public function test_store_api_order_pay_normal_order_untouched() {
		$this->store_api_route( '/wc/store/v1/checkout/' . $this->order( false )->get_id() );
		$this->assertCount( 5, $this->offered() );
	}

	/**
	 * Customer's top-up order count.
	 *
	 * @return int
	 */
	private function order_count() {
		return count( wc_get_orders( array( 'customer_id' => $this->user_id, 'limit' => -1, 'return' => 'ids' ) ) );
	}

	/**
	 * REST top-up with a gateway outside the list → 400, no order. Fails on 1.7.3.
	 *
	 * @dataProvider refused_methods
	 * @param string $method Payment method id.
	 */
	public function test_rest_topup_refuses_not_allowed_gateway( $method ) {
		$result = WooWallet_Topup_Service::create_order( $this->user_id, 25, $method );
		$this->assertFalse( $result['is_valid'] );
		$this->assertSame( 'rest_payment_method_not_allowed', $result['code'] );
		$this->assertSame( 400, $result['status'] );
		$this->assertSame( 0, $this->order_count() );
	}

	/**
	 * Refused ids: offline, wallet, unknown.
	 *
	 * @return array
	 */
	public function refused_methods() {
		return array( array( 'bacs' ), array( 'cod' ), array( 'wallet' ), array( 'nope' ) );
	}

	/**
	 * REST top-up with an allowed gateway → order created with that method.
	 */
	public function test_rest_topup_allowed_gateway_creates_order() {
		$result = WooWallet_Topup_Service::create_order( $this->user_id, 25, 'tw_online' );
		$this->assertTrue( $result['is_valid'] );
		$this->assertSame( 'tw_online', wc_get_order( $result['order_id'] )->get_payment_method() );
	}

	/**
	 * REST top-up without a payment method → order created with none; the
	 * customer picks on the (restricted) order-pay page.
	 */
	public function test_rest_topup_without_method_creates_order() {
		$result = WooWallet_Topup_Service::create_order( $this->user_id, 25 );
		$this->assertTrue( $result['is_valid'] );
		$this->assertSame( '', wc_get_order( $result['order_id'] )->get_payment_method() );
	}
}
