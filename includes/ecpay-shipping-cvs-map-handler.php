<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __DIR__ ) . '/includes/helpers.php';

use Ecpay\Sdk\Exceptions\RtnException;
use Ecpay\Sdk\Factories\Factory;
use Helpers\Logistic\Wooecpay_Logistic_Helper;

/**
 * 產生 ECPay 超商地圖表單
 *
 * 處理 ECPay 超商地圖表單的建立，用於 headless 前台選擇超商門市
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function generate_ecpay_map_form_for_headless( $request ) {
	$order_id = intval( $request->get_param( 'order_id' ) );
	$order    = wc_get_order( $order_id );

	if ( ! $order_id ) {
		return create_rest_response( 'missing_params', '請提供正確的欄位資料', 400 );
	}

	if ( ! $order ) {
		return create_rest_response( 'order_not_found', '找不到訂單', 404 );
	}

	$logistic_helper = new Wooecpay_Logistic_Helper();
	// 參考 generate_ecpay_map_form()
	// 物流方式
	$shipping_methods   = $order->get_items( 'shipping' );
	$shipping_method    = reset( $shipping_methods );
	$shipping_method_id = $shipping_method->get_method_id(); // eg. Wooecpay_Logistic_CVS_711

	if ( ! $logistic_helper->is_ecpay_cvs_logistics( $shipping_method_id ) ) {
		return create_rest_response( 'invalid_shipping_method', '運送方式非綠界超商取貨', 404 );
	}

	ecpay_log_in_headless( '物流方式-' . print_r( $shipping_method_id, true ), 'A00002', $order_id );

	$api_logistic_info = $logistic_helper->get_ecpay_logistic_api_info( 'map' );
	$client_back_url   = $logistic_helper->get_permalink( WC()->api_request_url( 'headless_wooecpay_logistic_map_callback', true ) );
	// WP Endpoint eg. https://wp.domain/wc-api/wooecpay_logistic_map_callback/?has_block=true

	$merchant_trade_no = $logistic_helper->get_merchant_trade_no( $order->get_id(), get_option( 'wooecpay_logistic_order_prefix' ) );
	$logistics_type    = $logistic_helper->get_logistics_sub_type( $shipping_method_id );

	try {

		$factory = new Factory(
			array(
				'hashKey'    => $api_logistic_info['hashKey'],
				'hashIv'     => $api_logistic_info['hashIv'],
				'hashMethod' => 'md5',
			)
		);

		$auto_submit_form_service = $factory->create( 'AutoSubmitFormWithCmvService' );

		$input = array(
			'MerchantID'       => $api_logistic_info['merchant_id'],
			'MerchantTradeNo'  => $merchant_trade_no,
			'LogisticsType'    => $logistics_type['type'],
			'LogisticsSubType' => $logistics_type['sub_type'],
			'IsCollection'     => 'Y',
			'ServerReplyURL'   => $client_back_url,
		);

		ecpay_log_in_headless( '轉導電子地圖(Headless) ' . print_r( $input, true ), 'A00003', $order_id );

		$map_form = $auto_submit_form_service->generate( $input, $api_logistic_info['action'] );

		return new WP_REST_Response(
			$map_form,
			200,
			array( 'Content-Type' => 'text/html' )
		);
	} catch ( RtnException $e ) {
		ecpay_log_in_headless( '[Exception] (' . $e->getCode() . ')(Headless)' . $e->getMessage(), 'A90003', $order_id );

		return new WP_REST_Response(
			array(
				'status'  => 'error',
				'message' => $e->getMessage(),
			),
			500
		);
	}
}
