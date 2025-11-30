<?php
namespace HS_Coffee_Headless_Store;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Order_Custom_Statuses {
	public function __construct() {
		// 註冊自訂訂單狀態
		add_action( 'init', array( $this, 'register_custom_order_statuses' ) );

		// 將狀態加入 WooCommerce 訂單狀態列表
		add_filter( 'wc_order_statuses', array( $this, 'add_custom_order_statuses' ) );
	}

	/**
	 * 註冊自訂訂單狀態
	 *
	 * 註冊「備貨中」和「已出貨」兩個自訂訂單狀態
	 * 狀態順序：已處理 (processing) => 備貨中 (preparing) => 已出貨 (shipped)
	 *
	 * @return void
	 */
	public function register_custom_order_statuses() {
		// 註冊「備貨中」狀態
		register_post_status(
			'wc-preparing',
			array(
				'label'                     => '備貨中',
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
			)
		);

		// 註冊「已出貨」狀態
		register_post_status(
			'wc-shipped',
			array(
				'label'                     => '已出貨',
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
			)
		);
	}

	/**
	 * 將自訂狀態加入 WooCommerce 訂單狀態列表
	 *
	 * 將「備貨中」和「已出貨」狀態加入訂單狀態下拉選單
	 * 並確保它們顯示在「已處理」狀態之後
	 *
	 * @param array $order_statuses 現有的訂單狀態列表
	 * @return array 更新後的訂單狀態列表
	 */
	public function add_custom_order_statuses( $statuses ) {
		$new_statuses = array(
			'wc-preparing' => '備貨中',
			'wc-shipped'   => '已出貨',
		);
		return array_merge( $statuses, $new_statuses );
	}
}
