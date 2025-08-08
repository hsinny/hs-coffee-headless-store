<?php

use Helpers\Logger\Wooecpay_Logger;

if ( ! function_exists( 'create_rest_response' ) ) {
	function create_rest_response( $code, $message, $status ) {
		return new WP_REST_Response(
			array(
				'code'    => $code,
				'message' => $message,
				'data'    => array(
					'status' => $status,
				),
			),
			$status
		);
	}
}

function ecpay_log_in_headless( $content, $code = '', $order_id = '' ) {
	$logger = new Wooecpay_Logger();
	return $logger->log( $content, $code, $order_id );
}

function get_selected_shipping_method_id( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return '';
	}

	$shipping_methods   = $order->get_items( 'shipping' );
	$shipping_method    = reset( $shipping_methods );
	$shipping_method_id = $shipping_method->get_method_id();

	return $shipping_method_id;
}

