<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check if Ecpay Plugin is active
if ( ! in_array( 'ecpay-ecommerce-for-woocommerce/ecpay-ecommerce-for-woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
	return;
}

// 匯入處理函式
require_once plugin_dir_path( __FILE__ ) . 'ecpay-payment-order-handler.php';
require_once plugin_dir_path( __FILE__ ) . 'ecpay-shipping-cvs-map-handler.php';
require_once plugin_dir_path( __FILE__ ) . 'get-order-additional-info-handler.php';

add_action( 'rest_api_init', 'register_create_ecpay_payment_order_endpoint' );
add_action( 'rest_api_init', 'register_ecpay_shipping_cvs_map_endpoint' );
add_action( 'rest_api_init', 'register_get_order_additional_info_endpoint' );

function register_create_ecpay_payment_order_endpoint() {
	register_rest_route(
		'wc/custom/v1',
		'/ecpay/payment/order/(?P<order_id>[\d]+)',
		array(
			'methods'             => 'POST',
			'callback'            => 'create_ecpay_payment_order',
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' ); // 限制存取，需帶金鑰
			},
		)
	);
}

function register_ecpay_shipping_cvs_map_endpoint() {
	register_rest_route(
		'wc/custom/v1',
		'/ecpay/shipping/cvs-map',
		array(
			'methods'             => 'POST',
			'callback'            => 'generate_ecpay_map_form_for_headless',
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			},
		)
	);
}

function register_get_order_additional_info_endpoint() {
	register_rest_route(
		'wc/custom/v1',
		'/order-additional-info/(?P<order_id>[\d]+)',
		array(
			'methods'             => 'GET',
			'callback'            => 'get_order_additional_info',
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' ); // 限制存取，需帶金鑰
			},
		)
	);
}
