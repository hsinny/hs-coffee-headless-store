<?php
namespace HS_Coffee_Headless_Store;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email 管理類別
 *
 * 負責管理所有自訂 Email 通知信的註冊和觸發邏輯
 */
class WC_Email_Manager {
	public function __construct() {
		// 註冊自訂 Email 類別
		add_filter( 'woocommerce_email_classes', array( $this, 'register_email_classes' ) );

		// 將自訂 Email actions 加入 WooCommerce email actions 列表
		add_filter( 'woocommerce_email_actions', array( $this, 'add_email_actions' ) );
	}

	/**
	 * 註冊自訂 Email 類別
	 *
	 * @param array $emails 現有的 Email 類別列表
	 * @return array        更新後的 Email 類別列表
	 */
	public function register_email_classes( $emails ) {
		$emails['WC_Email_Customer_Preparing_Order'] = include HS_COFFEE_HEADLESS_STORE_PLUGIN_DIR . 'includes/class-wc-email-customer-preparing-order.php';

		return $emails;
	}

	/**
	 * 將自訂 Email actions 加入 WooCommerce email actions 列表
	 *
	 * @param array $actions 現有的 Email actions 列表
	 * @return array         更新後的 Email actions 列表
	 */
	public function add_email_actions( $actions ) {
		$actions[] = 'woocommerce_order_status_processing_to_preparing'; // 處理中 => 備貨中

		return $actions;
	}
}
