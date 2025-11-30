<?php
/**
 * Plugin Name: HS Coffee Headless Store
 * Description: Headless E-Commerce 客製化解決方案
 * Version: 1.2.0
 * Author: Hsinny Liu
 */

// Exit if accessed directly 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check if WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
	return;
}

// 定義外掛路徑
define( 'HS_COFFEE_HEADLESS_STORE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// 載入外掛功能
require_once HS_COFFEE_HEADLESS_STORE_PLUGIN_DIR . 'includes/custom-api.php';
require_once HS_COFFEE_HEADLESS_STORE_PLUGIN_DIR . 'includes/class-wc-store-checkout.php';
require_once HS_COFFEE_HEADLESS_STORE_PLUGIN_DIR . 'includes/class-wc-store-payment.php';
require_once HS_COFFEE_HEADLESS_STORE_PLUGIN_DIR . 'includes/class-wc-email-manager.php';
require_once HS_COFFEE_HEADLESS_STORE_PLUGIN_DIR . 'includes/class-wc-email-custom-footer.php';
require_once HS_COFFEE_HEADLESS_STORE_PLUGIN_DIR . 'includes/class-wp-frontend-redirect.php';
require_once HS_COFFEE_HEADLESS_STORE_PLUGIN_DIR . 'includes/class-wc-store-flat-rate-free-shipping.php';
require_once HS_COFFEE_HEADLESS_STORE_PLUGIN_DIR . 'includes/class-wc-store-ecpay-payment-order.php';
require_once HS_COFFEE_HEADLESS_STORE_PLUGIN_DIR . 'includes/class-wc-order-custom-statuses.php';

use HS_Coffee_Headless_Store\WC_Store_Checkout;
use HS_Coffee_Headless_Store\WC_Store_Payment;
use HS_Coffee_Headless_Store\WC_Email_Custom_Footer;
use HS_Coffee_Headless_Store\WC_Wp_Frontend_Redirect;
use HS_Coffee_Headless_Store\WC_Store_Flat_Rate_Free_Shipping;
use HS_Coffee_Headless_Store\WC_Order_Custom_Statuses;
use HS_Coffee_Headless_Store\WC_Email_Manager;

// 等所有外掛都載入後，再執行我方外掛中的初始化邏輯，避免還沒載入其他依賴外掛就去呼叫會出錯的函式
add_action( 'plugins_loaded', 'init_headless_checkout_hooks' );

function init_headless_checkout_hooks() {
	new WC_Store_Checkout();
	new WC_Store_Payment(); // 確保掛上 COD 狀態過濾器
	new WC_Email_Manager(); // 管理自訂 Email 通知信
	new WC_Email_Custom_Footer(); // 啟用自訂 Email 佔位符
	new WC_Wp_Frontend_Redirect(); // 前台訪問控制：只允許管理員訪問 WP 前台
	new WC_Store_Flat_Rate_Free_Shipping();
	new WC_Order_Custom_Statuses(); // 註冊自訂訂單狀態

	// 初始化 ECPay 付款訂單類別並註冊路由
	$ecpay_payment_order = new WC_Store_Ecpay_Payment_Order();
	$ecpay_payment_order->register_routes();
}
