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
	$shipping_method_id = $request->get_param( 'shipping_method_id' ); // eg. Wooecpay_Logistic_CVS_711
	$client_back_url    = $request->get_param( 'client_back_url' );

	if ( ! $shipping_method_id || ! $client_back_url ) {
		return create_rest_response( 'missing_params', '請提供正確的欄位資料', 400 );
	}

	$logistic_helper = new Wooecpay_Logistic_Helper();
	// 參考 generate_ecpay_map_form()
	// 物流方式
	if ( ! $logistic_helper->is_ecpay_cvs_logistics( $shipping_method_id ) ) {
		return create_rest_response( 'invalid_shipping_method', '運送方式非綠界超商取貨', 404 );
	}

	ecpay_log_in_headless( '物流方式-' . print_r( $shipping_method_id, true ), 'A00002' );

	$api_logistic_info = $logistic_helper->get_ecpay_logistic_api_info( 'map' );
	$server_reply_url  = $logistic_helper->get_permalink( WC()->api_request_url( 'headless_wooecpay_logistic_map_callback', true ) );
	// WP Endpoint eg. https://wp.domain/wc-api/wooecpay_logistic_map_callback/?has_block=true

	// $merchant_trade_no = $logistic_helper->get_merchant_trade_no( $order->get_id(), get_option( 'wooecpay_logistic_order_prefix' ) );
	$logistics_type = $logistic_helper->get_logistics_sub_type( $shipping_method_id );

	$extra_data = json_encode(
		array(
			'client_back_url'    => $client_back_url,
			'payment_method_id'  => $request->get_param( 'payment_method_id' ),
			'shipping_method_id' => $request->get_param( 'shipping_method_id' ),
		)
	);

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
			// 'MerchantTradeNo'  => $merchant_trade_no, // 文件上非必填，但未來物流訂單應該要對應後台店家訂單編號，目前物流單號為後台手動建立
			'LogisticsType'    => $logistics_type['type'],
			'LogisticsSubType' => $logistics_type['sub_type'],
			'IsCollection'     => 'Y',
			'ServerReplyURL'   => $server_reply_url,
			'ExtraData'        => $extra_data, // 供廠商傳遞保留的資訊，在回傳 response 參數中，會原值回傳
		);

		ecpay_log_in_headless( '轉導電子地圖(Headless) ' . print_r( $input, true ), 'A00003' );

		$map_form = $auto_submit_form_service->generate( $input, $api_logistic_info['action'] );

		return new WP_REST_Response(
			$map_form,
			200,
			array( 'Content-Type' => 'text/html' )
		);
	} catch ( RtnException $e ) {
		ecpay_log_in_headless( '[Exception] (' . $e->getCode() . ')(Headless)' . $e->getMessage(), 'A90003' );

		return new WP_REST_Response(
			array(
				'status'  => 'error',
				'message' => $e->getMessage(),
			),
			500
		);
	}
}

/**
 * 註冊自定義 WooCommerce API Endpoint
 *
 * 綠界回傳超商地圖門市選擇結果，並轉導到 headless 前台
 * Endpoint: https://wp.domain/wc-api/headless_wooecpay_logistic_map_callback
 *
 * 邏輯參考
 * ecpay-ecommerce-for-woocommerce/includes/services/payment/ecpay-gateway-base.php
 * function receipt_page ()
 */
add_action( 'woocommerce_api_headless_wooecpay_logistic_map_callback', 'cvs_map_response_for_handless' );

function cvs_map_response_for_handless() {
	// 客製轉導回前台 url
	$extra_data         = json_decode( stripslashes( $_POST['ExtraData'] ), true );
	$client_back_url    = isset( $extra_data['client_back_url'] ) ? $extra_data['client_back_url'] : ''; // 前端傳遞的 url
	$shipping_method_id = isset( $extra_data['shipping_method_id'] ) ? $extra_data['shipping_method_id'] : '';

	// 根據 MerchantTradeNo 獲取對應的 WooCommerce 訂單 ID
	$logistic_helper = new Wooecpay_Logistic_Helper();

	ecpay_log_in_headless( '選擇超商結果回傳(Headless) ' . print_r( $_POST, true ), 'B00005' );

	// 判斷是否為超商取貨
	if ( $logistic_helper->is_ecpay_cvs_logistics( $shipping_method_id ) ) {
		// 判斷是否有回傳資訊
		if ( isset( $_POST['CVSStoreID'] ) ) {

			// 是否啟用超商離島物流
			if ( in_array( 'Wooecpay_Logistic_CVS_711', get_option( 'wooecpay_enabled_logistic_outside', array() ) ) ) {

				// 門市檢查
				$is_valid = $logistic_helper->check_cvs_is_valid( $shipping_method_id, $_POST['CVSOutSide'] );
				if ( ! $is_valid ) {
					cvs_store_selection_error_redirect( $client_back_url, 'cvs_store_selection_invalid', 400 );
				}
			}

			$CVSStoreID   = sanitize_text_field( $_POST['CVSStoreID'] );
			$CVSStoreName = sanitize_text_field( $_POST['CVSStoreName'] );
			$CVSAddress   = sanitize_text_field( $_POST['CVSAddress'] );
			$CVSTelephone = sanitize_text_field( $_POST['CVSTelephone'] );

			// 驗證 (限制字串長度，避免超出預期範圍)
			if ( mb_strlen( $CVSStoreName, 'utf-8' ) > 10 ) {
				$CVSStoreName = mb_substr( $CVSStoreName, 0, 10, 'utf-8' );
			}
			if ( mb_strlen( $CVSAddress, 'utf-8' ) > 60 ) {
				$CVSAddress = mb_substr( $CVSAddress, 0, 60, 'utf-8' );
			}
			if ( strlen( $CVSTelephone ) > 20 ) {
				$CVSTelephone = substr( $CVSTelephone, 0, 20 );
			}
			if ( strlen( $CVSStoreID ) > 10 ) {
				$CVSStoreID = substr( $CVSTelephone, 0, 10 );
			}
		}
	}

	// 轉導回 Headless 前台
	$redirect_headless_url = add_query_arg(
		array(
			'action'             => 'cvs_store_selection',
			'status'             => 'success',
			'cvs_store_id'       => $CVSStoreID,
			'cvs_store_name'     => $CVSStoreName,
			'cvs_store_address'  => $CVSAddress,
			'payment_method_id'  => $extra_data['payment_method_id'],
			'shipping_method_id' => $extra_data['shipping_method_id'],
		),
		$client_back_url
	);

	wp_redirect( $redirect_headless_url, 302 );
}

function cvs_store_selection_error_redirect( $redirect_headless_url, $error_code, $status_code ) {
	wp_redirect( $redirect_headless_url . '/?action=cvs_store_selection&statue=error&error_code=' . $error_code . '&statusCode=' . $status_code, 302 );
}
