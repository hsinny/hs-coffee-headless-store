<?php
namespace HS_Coffee_Headless_Store;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Flat Rate 運送方式加上免運金額門檻功能
 */

class WC_Store_Flat_Rate_Free_Shipping {

	public function __construct() {
		// 添加設定欄位到 Flat Rate 運送方式
		add_filter( 'woocommerce_shipping_instance_form_fields_flat_rate', array( $this, 'add_free_shipping_threshold_field' ), 10, 1 );

		// 修改運費計算，檢查是否達到免運門檻
		add_action( 'woocommerce_flat_rate_shipping_add_rate', array( $this, 'apply_free_shipping_threshold' ), 10, 2 );
	}

	/**
	 * 添加免運金額門檻設定欄位
	 *
	 * @param array $fields 現有的設定欄位
	 * @return array        修改後的設定欄位
	 */
	public function add_free_shipping_threshold_field( $fields ) {
		$new_fields = array();

		foreach ( $fields as $key => $field ) {
			$new_fields[ $key ] = $field;

			// 在 'cost' 欄位之後添加免運門檻欄位
			if ( 'cost' === $key ) {
				$new_fields['free_shipping_threshold'] = array(
					'title'       => __( '免運金額門檻', 'woocommerce' ),
					'type'        => 'text',
					'class'       => 'wc-shipping-modal-price',
					'placeholder' => wc_format_localized_price( 0 ),
					'description' => __( '當購物車商品小計達到此金額時，將免收運費。留空或設為 0 則不啟用免運功能。', 'woocommerce' ),
					'default'     => '0',
					'desc_tip'    => true,
				);
			}
		}

		return $new_fields;
	}

	/**
	 * 應用免運門檻邏輯
	 *
	 * @param WC_Shipping_Flat_Rate $shipping_method 運送方式實例
	 * @param array                 $rate            運費資料
	 */
	public function apply_free_shipping_threshold( $shipping_method, $rate ) {
		// 獲取免運門檻設定
		$free_shipping_threshold = $shipping_method->get_option( 'free_shipping_threshold', '0' );

		// 如果沒有設定免運門檻或為 0，則不處理
		if ( empty( $free_shipping_threshold ) || floatval( $free_shipping_threshold ) <= 0 ) {
			return;
		}

		// 獲取購物車商品小計（不含運費和稅）
		$cart_subtotal = WC()->cart->get_subtotal();

		// 如果購物車金額達到免運門檻，將運費設為 0
		if ( $cart_subtotal >= floatval( $free_shipping_threshold ) ) {
			$rate['cost'] = 0;

			// 更新運費
			$shipping_method->add_rate( $rate );
		}
	}
}
