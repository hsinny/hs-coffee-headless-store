<?php
/**
 * Plugin Name: HS Coffee Headless Store
 * Description: Headless E-Commerce 客製化解決方案
 * Version: 1.0.0
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

use HS_Coffee_Headless_Store\WC_Store_Checkout;
use HS_Coffee_Headless_Store\WC_Store_Payment;

// 等所有外掛都載入後，再執行我方外掛中的初始化邏輯，避免還沒載入其他依賴外掛就去呼叫會出錯的函式
add_action( 'plugins_loaded', 'init_headless_checkout_hooks' );

function init_headless_checkout_hooks() {
	new WC_Store_Checkout();
	new WC_Store_Payment(); // 確保掛上 COD 狀態過濾器
}
