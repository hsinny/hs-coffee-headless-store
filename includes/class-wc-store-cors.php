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
		// 允許 headless 站台的 origin 存取 Store API
		add_filter( 'allowed_http_origin', array( $this, 'allow_headless_site_origin' ), 10, 2 );
		add_filter( 'allowed_http_origins', array( $this, 'add_headless_site_to_allowed_origins' ), 10, 1 );

		// 確保 Nonce header 被 expose，讓 headless 站台可以讀取
		add_filter( 'rest_exposed_cors_headers', array( $this, 'expose_nonce_header' ), 10, 1 );
	}

	/**
	 * 取得 headless 站台的 domain
	 *
	 * @return string|null 如果環境常數有效則返回 domain，否則返回 null
	 */
	private function get_headless_domain() {
		$env_constants = get_site_env_constants();

		if ( ! is_array( $env_constants ) || empty( $env_constants['HEADLESS_SITE_DOMAIN'] ) ) {
			return null;
		}

		return $env_constants['HEADLESS_SITE_DOMAIN'];
	}

	/**
	 * 允許 headless 站台的 origin 存取 Store API
	 *
	 * @param string $origin 請求的 origin
	 * @param string $origin_arg 原始 origin 參數
	 * @return string
	 */
	public function allow_headless_site_origin( $origin, $origin_arg ) {
		$headless_domain = $this->get_headless_domain();

		if ( $headless_domain && $origin === $headless_domain ) {
			return $origin;
		}

		return $origin_arg;
	}

	/**
	 * 將 headless 站台加入允許的 origins 列表
	 *
	 * @param array $origins 允許的 origins 列表
	 * @return array
	 */
	public function add_headless_site_to_allowed_origins( $origins ) {
		$headless_domain = $this->get_headless_domain();

		if ( $headless_domain && ! in_array( $headless_domain, $origins, true ) ) {
			$origins[] = $headless_domain;
		}

		return $origins;
	}

	/**
	 * 確保 Nonce header 被 expose 在 CORS 響應中
	 *
	 * @param array $exposed_headers 目前被 expose 的 headers
	 * @return array
	 *
	 * 參考來源：WooCommerce Store API Authentication.php 的 exposed_cors_headers()
	 * 預設只 expose Cart-Token，不會 expose Nonce。
	 */
	public function expose_nonce_header( $exposed_headers ) {
		if ( ! in_array( 'Nonce', $exposed_headers, true ) ) {
			$exposed_headers[] = 'Nonce';
		}
		return $exposed_headers;
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
			$origin          = get_http_origin();
			$headless_domain = $this->get_headless_domain();

		// 檢查環境常數是否有效
		if ( ! $headless_domain ) {
			return $value;
		}

		// 僅當 origin 為 headless site domain 時才允許跨域（Access-Control-Allow-*）
		if ( $origin === $headless_domain ) {
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
