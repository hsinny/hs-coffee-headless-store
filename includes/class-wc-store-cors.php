<?php
namespace HS_Coffee_Headless_Store;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __DIR__ ) . '/includes/helpers.php';

/**
 * Headless 站台 CORS 處理類別
 *
 * 負責設定 CORS headers 以允許 headless 站台存取 REST API 和自訂 API route
 */
class WC_Store_CORS {
	public function __construct() {
		// 設定 CORS headers（優先級 15，確保在 WooCommerce Store API 之後執行）
		add_action( 'rest_api_init', array( $this, 'configure_cors_headers' ), 15 );
	}

	/**
	 * 設定 CORS headers
	 *
	 * 移除預設的 CORS headers，針對 REST API 和自訂 API route 設定 CORS (除了 WooCommerce Store API)
	 * WooCommerce Store API 會自己處理 CORS
	 */
	public function configure_cors_headers() {
		// 移除 WordPress 預設 Rest API 的 CORS headers
		remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );

		// 針對 REST API 和自訂 API route 設定 CORS headers (除了 WooCommerce Store API)
		add_filter( 'rest_pre_serve_request', array( $this, 'handle_cors_headers' ), 10, 4 );
	}

	/**
	 * 處理 CORS headers 的 callback
	 *
	 * @param bool             $value 是否繼續處理請求
	 * @param WP_REST_Response $result REST API 回應
	 * @param WP_REST_Request  $request REST API 請求
	 * @param WP_REST_Server   $server REST API 伺服器
	 * @return bool
	 */
	public function handle_cors_headers( $value, $result, $request, $server ) {
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
	}
}
