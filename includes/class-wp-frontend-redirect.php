<?php
namespace HS_Coffee_Headless_Store;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 前台訪問控制 class
 *
 * 只允許管理員訪問 WordPress 前台，其他訪客轉址到 headless 前台
 */
class WC_Wp_Frontend_Redirect {

	public function __construct() {
		// 在模板載入前攔截前台訪問
		add_action( 'template_redirect', array( $this, 'redirect_non_admin_frontend' ), 1 );
	}

	/**
	 * 轉址非管理員的前台訪問
	 *
	 * 檢查條件：
	 * 1. 不是管理後台頁面
	 * 2. 不是登入/註冊頁面
	 * 3. 不是 REST API 請求
	 * 4. 不是 AJAX 請求
	 * 5. 不是管理員
	 *
	 * 如果符合條件，轉址到 headless 前台首頁
	 */
	public function redirect_non_admin_frontend() {
		// 如果是管理後台，不處理
		if ( is_admin() ) {
			return;
		}

		// 如果是登入頁面或註冊頁面，不處理（允許管理員登入）
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( strpos( $request_uri, 'wp-login.php' ) !== false || strpos( $request_uri, 'wp-signup.php' ) !== false ) {
			return;
		}

		// 如果是 REST API 請求，不處理（允許 API 訪問）
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		// 如果是 AJAX 請求，不處理
		if ( wp_doing_ajax() ) {
			return;
		}

		// 如果是管理員，允許訪問前台
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		// 取得 headless 前台首頁 URL
		$headless_frontend_url = $this->get_headless_frontend_url();

		// 如果有設定 headless 前台 URL，則轉址
		if ( ! empty( $headless_frontend_url ) ) {
			wp_redirect( $headless_frontend_url, 301 );
			exit;
		}

		// 如果沒有設定 headless 前台 URL，顯示提示訊息
		wp_die(
			'此網站僅作為後端 API 伺服器使用，前台請訪問 headless 前端應用程式。',
			'前台訪問限制',
			array( 'response' => 403 )
		);
	}

	/**
	 * 取得 headless 前台 URL
	 *
	 * @return string Headless 前台 URL
	 */
	private function get_headless_frontend_url() {
		if ( ! function_exists( 'get_site_env_constants' ) ) {
			return '';
		}

		$site_env_constants = get_site_env_constants();
		return ! empty( $site_env_constants['HEADLESS_SITE_DOMAIN'] )
			? esc_url_raw( $site_env_constants['HEADLESS_SITE_DOMAIN'] )
			: '';
	}
}
