<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __DIR__ ) . '/includes/helpers.php';

/**
 * 從 WooCommerce 中取得訂單詳細資訊，並合併額外的訂單資料後回傳。
 * (為解決 WooCommerce Store Order API 需要帶 ordder_key 問題，及補上 UI 需要顯示的其他欄位)
 *
 * @param WP_REST_Request $request
 *                        - order_id (int)
 *                        - billing_email (string)
 *
 * @return WP_REST_Response
 */
function get_order_details( $request ) {
	$order_id      = absint( $request['order_id'] );
	$billing_email = sanitize_email( $request['billing_email'] );

	if ( ! $order_id || ! $billing_email ) {
		return new WP_Error(
			'headless_missing_params',
			'請提供訂單編號與訂購Email',
			array( 'status' => 400 )
		);
	}

	$order = wc_get_order( $order_id );

	if ( ! $order ) {
		return new WP_Error(
			'headless_order_not_found',
			'找不到訂單，請確認輸入的訂單編號',
			array( 'status' => 404 )
		);
	}

	$order_key = $order->get_order_key();

	$response_data = fetch_woo_store_order( $order_id, $order_key, $billing_email );

	if ( is_wp_error( $response_data ) ) {
		return $response_data; // WordPress REST API 會自動輸出錯誤格式
	}

	if ( is_null( $response_data ) ) {
		return new WP_Error(
			'headless_order_fetch_failed',
			'無法取得訂單詳細資訊',
			array( 'status' => 500 )
		);
	}

	$additional_data = get_order_additional_details( $order_id, $request );
	$response_data   = array_merge( $response_data, $additional_data );

	return new WP_REST_Response( $response_data, 200 );
}

/**
 * 向 WooCommerce Store API 發送 GET 請求，取得訂單資訊。
 *
 * @param int    $order_id
 * @param string $order_key
 * @param string $billing_email
 *
 * @return array|null 如果回應有效，則返回包含訂單詳細資訊的關聯陣列；否則返回 null。
 */
function fetch_woo_store_order( $order_id, $order_key, $billing_email ) {
	$response    = wp_remote_get( site_url( '/wp-json/wc/store/v1/order/' . $order_id . '?key=' . $order_key . '&billing_email=' . $billing_email ) );
	$status_code = wp_remote_retrieve_response_code( $response );
	$body        = wp_remote_retrieve_body( $response );
	$body_data   = json_decode( $body, true );

	// Handle HTTP errors（例如無法連線）
	if ( is_wp_error( $response ) ) {
		return new WP_Error(
			$body_data['code'] ?? 'headless_store_api_request_failed',
			$body_data['message'] ?? '無法呼叫內部 WooCommerce Store API: ' . $response->get_error_message(),
			array(
				'status' => $status_code,
			),
		);
	}

	// 非 2xx 狀態碼
	if ( $status_code < 200 || $status_code >= 300 ) {
		// eg. { code: string, message: string, data: { status: number } }
		return new WP_Error(
			$body_data['code'] ?? 'headless_store_api_error',
			$body_data['message'] ?? '未知錯誤',
			array(
				'status' => $status_code,
			),
		);
	}

	// Validate response structure
	if ( ! is_array( $body_data ) ) {
		return null;
	}

	// 正常情況下回傳 JSON
	return $body_data;
}

/**
 * 取得 WooCommerce 訂單的額外詳情。
 *
 * @param int             $order_id
 * @param WP_REST_Request $request
 *
 * @return array|WP_Error 包含訂單額外詳情的陣列，或是 WP_Error 物件(發生錯誤時)。
 *
 * @throws Exception 若取得訂單詳情時發生問題。
 */
function get_order_additional_details( $order_id, $request ) {
	// 需建立一個 WP_REST_Request 物件，再設定參數，才不會 Error
	$request = new WP_REST_Request( 'GET' );

	$controller = new WC_REST_Orders_Controller();
	$request->set_param( 'id', $order_id );
	$response = $controller->get_item( $request );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$response_data = $response->get_data();

	$data = array(
		'number'           => $response_data['number'],
		'customer_id'      => $response_data['customer_id'],
		'customer_note'    => $response_data['customer_note'],
		'transaction_id'   => $response_data['transaction_id'],
		'date_created'     => $response_data['date_created'],
		'date_paid'        => $response_data['date_paid'],
		'date_completed'   => $response_data['date_completed'],
		'refunds'          => $response_data['refunds'],
		'shipping_methods' => map_shipping_methods( $response_data['shipping_lines'] ),
		'payment_method'   => map_payment_method( $response_data ),
	);

	// CVS 運送方式的詳細資訊
	$shipping_method = reset( $data['shipping_methods'] );
	if ( $shipping_method && strpos( $shipping_method['method_id'], 'Wooecpay_Logistic_CVS' ) !== false ) {
		$data['shipping_methods'][0]['cvs_details'] = WC_Store_Order_Additional_Info::get_cvs_details( $response_data );
	}

	return $data;
}

/**
 * 從回應資料中映射運送方式。
 *
 * @param array $shipping_lines WooCommerce 訂單回應中的運送方式資料。
 * @return array 映射後的運送方式。
 */
function map_shipping_methods( $shipping_lines ) {
	return array_map(
		function ( $shipping ) {
			return array(
				'method_id'    => $shipping['method_id'],
				'method_title' => $shipping['method_title'],
			);
		},
		$shipping_lines
	);
}

/**
 * 從回應資料中映射付款方式。
 *
 * @param array $response_data WooCommerce 訂單回應資料。
 * @return array 映射後的付款方式。
 */
function map_payment_method( $response_data ) {
	return array(
		'method_id'    => $response_data['payment_method'],
		'method_title' => $response_data['payment_method_title'],
	);
}
