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
require_once plugin_dir_path( __DIR__ ) . '/includes/helpers.php';

add_action( 'rest_api_init', 'configure_cors_headers', 15 );
add_action( 'rest_api_init', 'register_ecpay_shipping_cvs_map_endpoint' );

/**
 * 設定 CORS headers
 *
 * 移除預設的 CORS headers，針對 REST API 和自訂 API route 設定 CORS
 * WooCommerce Store API 會自己處理 CORS
 */
function configure_cors_headers() {
	// 移除 WordPress 預設 Rest API 的 CORS headers
	remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );

	// 針對 REST API 和自訂 API route 設定 CORS headers (除了 WooCommerce Store API)
	add_filter(
		'rest_pre_serve_request',
		function ( $value, $result, $request, $server ) {
			$route = $request->get_route();

			// WooCommerce Store API（/wc/store/ 開頭）讓預設流程處理，已自帶 CORS 支援
			if ( strpos( $route, '/wc/store/' ) === 0 ) {
				return $value;
			}

			// 針對 REST API 和自訂 API route 設定 CORS
			$origin        = get_http_origin();
			$env_constants = get_site_env_constants();

			// 僅當 origin 為 headless site domain 時才允許跨域（Access-Control-Allow-*）
			if ( $origin === $env_constants['HEADLESS_SITE_DOMAIN'] ) {
				$server->send_header( 'Access-Control-Allow-Origin', esc_url_raw( $origin ) );
				$server->send_header( 'Access-Control-Allow-Methods', 'OPTIONS, GET, POST, PUT, PATCH, DELETE' );
				$server->send_header( 'Access-Control-Allow-Credentials', 'true' );
				$server->send_header( 'Access-Control-Allow-Headers', 'Authorization, X-WP-Nonce, Content-Type, Cart-Token, Nonce, Content-Disposition, Content-MD5' );
				$server->send_header( 'Access-Control-Expose-Headers', 'X-WP-Total, X-WP-TotalPages, Link, Cart-Token, Nonce' );
			}
			// 關鍵：無論是否有 origin，都要設定 Vary: Origin
			// 讓快取系統知道要根據不同的 origin 快取不同的版本
			$server->send_header( 'Vary', 'Origin', false );

			return $value;
		},
		10,
		4
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
