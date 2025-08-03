<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __DIR__ ) . '/includes/helpers.php';

use Ecpay\Sdk\Exceptions\RtnException;
use Ecpay\Sdk\Factories\Factory;
use Helpers\Payment\Wooecpay_Payment_Helper;

function create_ecpay_payment_order( $request ) {
	$payment_helper = new Wooecpay_Payment_Helper();

	// 檢查訂單是否存在
	$order_id = intval( $request->get_param( 'order_id' ) ); // 加上清理輸入資料
	$order    = wc_get_order( $order_id );
	if ( ! $order ) {
		create_rest_response( 'order_not_found', '找不到訂單', 404 );
	}

	// 驗證 order_key 是否符合該訂單（避免偽造）
	$order_key = sanitize_text_field( $request->get_param( 'order_key' ) );
	if ( $order->get_order_key() !== $order_key ) {
		create_rest_response( 'order_verify_failed', '訂單驗證失敗', 403 );
	}

	// 檢查付款狀態
	$payment_status = $order->get_status();
	if ( 'processing' === $payment_status || 'completed' === $payment_status ) {
		create_rest_response( 'order_been_paid', '訂單已付款', 409 );
	}

	ecpay_log_in_headless( '前往付款(Headless)', 'A00001', $order_id );

	$api_payment_info  = $payment_helper->get_ecpay_payment_api_info( 'AioCheckOut' );
	$merchant_trade_no = $payment_helper->get_merchant_trade_no( $order_id, get_option( 'wooecpay_payment_order_prefix' ) );
	$item_name         = $payment_helper->get_item_name( $order );

	$hostname = $_SERVER['HTTP_HOST'];
	$client_back_url_path = '/checkout/order-received/' . $order_id . '/?order_key=' . $order_key . '&billing_email=' . $order->get_billing_email();
	if ( strpos( $hostname, 'localhost' ) !== false ) {
		$return_url      = 'https://0d6575e7ef21.ngrok-free.app/wc-api/wooecpay_payment_callback/';
		$client_back_url = 'https://localhost:5173' . $client_back_url_path;
	} else {
		$return_url      = WC()->api_request_url( 'wooecpay_payment_callback', true );
		$client_back_url = 'https://yuancoffee.com' . $client_back_url_path;
	}

	// 紀錄訂單其他資訊
	$order->update_meta_data( '_wooecpay_payment_order_prefix', get_option( 'wooecpay_payment_order_prefix' ) ); // 前綴
	$order->update_meta_data( '_wooecpay_payment_merchant_trade_no', $merchant_trade_no ); //MerchantTradeNo
	$order->update_meta_data( '_wooecpay_query_trade_tag', 0 );

	// 防止 hook 重複執行導致訂單歷程重複寫入
	if ( ! get_transient( 'wooecpay_receipt_page_executed_' . $order_id ) ) {
		$order->add_order_note( sprintf( __( 'Ecpay Payment Merchant Trade No %s', 'ecpay-ecommerce-for-woocommerce' ), $merchant_trade_no ) );
		set_transient( 'wooecpay_receipt_page_executed_' . $order_id, true, 3600 );
	} else {
		delete_transient( 'wooecpay_receipt_page_executed_' . $order_id );
	}

	$order->save();

	// 紀錄訂單付款資訊進 DB
	$payment_helper->insert_ecpay_orders_payment_status( $order_id, $order->get_payment_method(), $merchant_trade_no );

	// 組合AIO參數
	try {
		$factory = new Factory(
			array(
				'hashKey' => $api_payment_info['hashKey'],
				'hashIv'  => $api_payment_info['hashIv'],
			)
		);

		$auto_submit_form_service = $factory->create( 'AutoSubmitFormWithCmvService' );

		$input = array(
			'MerchantID'        => $api_payment_info['merchant_id'], // 特店編號
			'MerchantTradeNo'   => $merchant_trade_no,
			'MerchantTradeDate' => date_i18n( 'Y/m/d H:i:s' ),
			'PaymentType'       => 'aio',
			'TotalAmount'       => (int) ceil( $order->get_total() ),
			'TradeDesc'         => 'woocommerce_v2',
			'ItemName'          => $item_name,
			'ChoosePayment'     => $payment_helper->get_ChoosePayment( $order->get_payment_method() ), // method_id: Wooecpay_Gateway_Credit => payment_type: Credit
			'EncryptType'       => 1,
			'ReturnURL'         => $return_url,
			'ClientBackURL'     => $client_back_url,
			'PaymentInfoURL'    => $return_url,
			'NeedExtraPaidInfo' => 'Y',
		);

		$input = $payment_helper->add_type_info( $input, $order, has_block( 'woocommerce/checkout' ) );

		ecpay_log_in_headless( '轉導 AIO 付款頁(Headless) ' . print_r( $input, true ), 'A00004', $order_id );

		$generate_form = $auto_submit_form_service->generate( $input, $api_payment_info['action'] );

		wc_load_cart();
		if ( WC()->cart ) {
			WC()->cart->empty_cart();
		}

		return new WP_REST_Response(
			$generate_form,
			200,
			array( 'Content-Type' => 'text/html' )
		);

	} catch ( RtnException $e ) {
		ecpay_log_in_headless( '[Exception] (' . $e->getCode() . ')(Headless)' . $e->getMessage(), 'A90004', $order_id );
		// echo wp_kses_post( '(' . $e->getCode() . ')' . $e->getMessage() ) . PHP_EOL; // 綠界外掛原始碼

		return new WP_REST_Response(
			array(
				'status'  => 'error',
				'message' => $e->getMessage(),
			),
			500
		);
	}
}
