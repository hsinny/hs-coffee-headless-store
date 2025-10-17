<?php
namespace HS_Coffee_Headless_Store;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // 防止直接存取
}
class WC_Store_Payment {
	public function __construct() {

		add_filter( 'woocommerce_cod_process_payment_order_status', array( $this, 'headless_process_cod_payment_orderd_status' ), 10, 2 );
	}

	/**
	 * 在 headless (Store API) 使用 COD 貨到付款時強制狀態為 processing
	 *
	 * @param string    $status 預設狀態 (pendding)
	 * @param \WC_Order $order
	 * @return string
	 */
	function headless_process_cod_payment_orderd_status( $status, $order ) {
		if ( ! $order || 'cod' !== $order->get_payment_method() ) {
			return $status;
		}

		$created_via = $order->get_created_via();

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST && 'store-api' === $created_via ) {
			if ( class_exists( '\Automattic\WooCommerce\Enums\OrderStatus' ) ) {
				return \Automattic\WooCommerce\Enums\OrderStatus::PROCESSING;
			}
			return 'processing';
		}

		return $status;
	}
}
