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

add_action( 'rest_api_init', 'register_create_ecpay_payment_order_endpoint' );
