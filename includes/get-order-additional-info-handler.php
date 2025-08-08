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

	// CVS 運送資訊
	$shipping_method = reset( $data['shipping_methods'] );
	if ( $shipping_method && strpos( $shipping_method['method_id'], 'Wooecpay_Logistic_CVS' ) !== false ) {
		$data['shipping_methods'][0]['cvs_details'] = get_cvs_details( $response_data );
	}

	// Payment
	$data['payment_method'] = array(
		'method_id'    => $response_data['payment_method'],
		'method_title' => $response_data['payment_method_title'],
	);

	// 銀行轉帳
	if ( 'bacs' === $response_data['payment_method'] ) {
		$data['payment_method']['bank_detail'] = get_bank_details();
	}

	return new WP_REST_Response( $data, 200 );
}

function get_cvs_details( $response_data ) {
	if ( ! $response_data['meta_data'] ) {
		return;
	}
	$meta_data = $response_data['meta_data'];

	$cvs_data = array(
		'cvs_store_id'       => '',
		'cvs_store_name'     => '',
		'cvs_store_address'  => '',
		'cvs_receiver_phone' => '',
		'cvs_receiver_name'  => $response_data['shipping']['last_name'] . $response_data['shipping']['first_name'],
	);

	foreach ( $meta_data as $meta ) {
		$key = $meta->get_data()['key'];
		switch ( $key ) {
			case '_ecpay_logistic_cvs_store_id':
				$cvs_data['cvs_store_id'] = $meta->value;
				break;
			case '_ecpay_logistic_cvs_store_name':
				$cvs_data['cvs_store_name'] = $meta->value;
				break;
			case '_ecpay_logistic_cvs_store_address':
				$cvs_data['cvs_store_address'] = $meta->value;
				break;
			case 'wooecpay_shipping_phone':
				$cvs_data['cvs_receiver_phone'] = $meta->value;
				break;
		}
	}
	return $cvs_data;
}

function get_bank_details() {
	$bank_accounts = get_option( 'woocommerce_bacs_accounts' );

	if ( empty( $bank_accounts ) || ! is_array( $bank_accounts ) ) {
		return array();
	}

	$first_account = reset( $bank_accounts );

	return array(
		'bank_name'      => $first_account['bank_name'] ?? '',
		'account_number' => $first_account['account_number'] ?? '',
		'account_name'   => $first_account['account_name'] ?? '',
	);
}
