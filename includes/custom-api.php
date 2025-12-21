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
require_once plugin_dir_path( __FILE__ ) . 'ecpay-shipping-cvs-map-handler.php';

add_action( 'rest_api_init', 'register_ecpay_shipping_cvs_map_endpoint' );

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
