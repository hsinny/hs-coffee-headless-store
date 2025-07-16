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
