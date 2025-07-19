<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __DIR__ ) . '/includes/helpers.php';

/**
 * 取得單一訂單特定欄位，補充 Store Order API 缺的資料
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function get_order_additional_info( $request ) {
	$order_id      = intval( $request['order_id'] );
	$order_key     = sanitize_text_field( $request['key'] );
	$billing_email = sanitize_email( $request['billing_email'] );

	if ( ! $order_id || ! $order_key || ! $billing_email ) {
		return create_rest_response( 'missing_params', '請提供正確的欄位資料', 400 );
	}

	$order = wc_get_order( $order_id );

	if ( ! $order ) {
		return create_rest_response( 'order_not_found', '找不到訂單', 404 );
	}

	if (
			$order->get_order_key() !== $order_key ||
			strtolower( $order->get_billing_email() ) !== strtolower( $billing_email )
		) {
			return create_rest_response( 'order_verify_failed', '驗證失敗', 403 );
	}

	$response = fetch_order_data( $order_id, $request );

	if ( is_wp_error( $response ) ) {
		error_log( 'Order additional info fetch failed: ' . $response->get_error_message() );
		return create_rest_response( 'order_fetch_failed', $response->get_error_message(), 500 );
	}

  return $response;
}

/**
 * 使用 WooCommerce REST API 獲取訂單資料，自訂 response data
 *
 * @param int $order_id
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function fetch_order_data( $order_id, $request ) {
	$controller = new WC_REST_Orders_Controller();
  $request->set_param( 'id', $order_id );
  $response = $controller->get_item( $request );

	if ( is_wp_error( $response ) ) {
			return $response;
	}

	$response_data = $response->get_data();

	$data = array(
		'number'               => $response_data['number'],
		'customer_id'          => $response_data['customer_id'],
		'customer_note'        => $response_data['customer_note'],
		'transaction_id'       => $response_data['transaction_id'],
		'date_created'         => $response_data['date_created'],
		'payment_method'       => $response_data['payment_method'],
		'payment_method_title' => $response_data['payment_method_title'],
		'date_paid'            => $response_data['date_paid'],
		'date_completed'       => $response_data['date_completed'],
		'refunds'              => $response_data['refunds'],
		'shipping_methods'     => array_map(
			function ( $shipping ) {
				return array(
					'method_id'    => $shipping['method_id'],
					'method_title' => $shipping['method_title'],
				);
			},
			$response_data['shipping_lines']
		),
	);
	
	return new WP_REST_Response( $data, 200 );
}