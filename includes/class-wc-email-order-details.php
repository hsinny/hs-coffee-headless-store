<?php
namespace HS_Coffee_Headless_Store;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email 訂單詳情顯示
 */
class WC_Email_Order_Details {
	public function __construct() {
		// 修改 Email 中運費為 0 時的顯示方式
		add_filter( 'woocommerce_order_shipping_to_display', array( $this, 'modify_free_shipping_display' ), 10, 3 );

		// 修改 Email 中付款方式的顯示
		add_filter( 'woocommerce_get_order_item_totals', array( $this, 'modify_payment_method_display' ), 10, 3 );
	}

	/**
	 * 修改 Email 中運費為 0 時的顯示方式
	 * 當運費為 0 時，顯示 "(免運) NT$0" 而不是運送方式名稱
	 *
	 * @param string   $shipping     Shipping 顯示值
	 * @param WC_Order $order        訂單物件
	 * @param string   $tax_display  稅額顯示方式
	 * @return string                修改後的 Shipping 顯示值
	 */
	public function modify_free_shipping_display( $shipping, $order, $tax_display ) {
		// 將字串類型的 shipping_total 轉換為數字（double/float）
		$shipping_total_raw = $order->get_shipping_total();
		$shipping_total     = is_string( $shipping_total_raw ) ? (float) trim( $shipping_total_raw ) : (float) $shipping_total_raw;

		// 如果運費為 0（使用 abs 處理浮點數精度問題）
		if ( abs( $shipping_total ) < 0.01 && ! empty( $order->get_shipping_method() ) ) {
			$shipping = '<span>(免運) ' . wc_price( 0, array( 'currency' => $order->get_currency() ) ) . '</span>';
		}

		return $shipping;
	}

	/**
	 * 修改 Email 中付款方式的顯示
	 * 防止付款方式文字在手機版換行，強制一行顯示
	 *
	 * @param array    $total_rows   訂單總計項目陣列
	 * @param WC_Order $order        訂單物件
	 * @param string   $tax_display  稅額顯示方式
	 * @return array                 修改後的訂單總計項目陣列
	 */
	public function modify_payment_method_display( $total_rows, $order, $tax_display ) {
		if ( isset( $total_rows['payment_method'] ) ) {
			$original_value                        = $total_rows['payment_method']['value'];
			$total_rows['payment_method']['value'] = '<div style="min-width: 70px;">' . $original_value . '</div>';
		}

		return $total_rows;
	}
}
