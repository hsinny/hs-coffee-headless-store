<?php
namespace HS_Coffee_Headless_Store;

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // 防止直接存取
}

class WC_Store_Checkout {

	public function __construct() {
		add_filter( 'woocommerce_default_address_fields', array( $this, 'headless_modify_default_address_fields' ), 10, 1 );
		add_filter( 'rest_post_dispatch', array( $this, 'add_cvs_meta_data_to_draft_checkout_response' ), 10, 3 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'headless_validate_order_fields' ), 10, 2 );
	}

	/**
	 * 修改 WooCommerce 結帳中的預設位址欄位。
	 *
	 * 調整特定位址欄位的必填狀態，
	 * 使其在 WooCommerce 結帳流程中變成選用狀態，以便通過結帳欄位驗證
	 */
	function headless_modify_default_address_fields( $fields ) {
		if ( isset( $fields['postcode'] ) ) {
			$fields['postcode']['required'] = false;
		}
		if ( isset( $fields['city'] ) ) {
			$fields['city']['required'] = false;
		}
		if ( isset( $fields['state'] ) ) {
			$fields['state']['required'] = false;
		}
		if ( isset( $fields['address_1'] ) ) {
			$fields['address_1']['required'] = false;
		}
		return $fields;
	}

	/**
	 * 在 WooCommerce Store API 的 Create Checkout 回應中加入自訂的 meta 資料。
	 *
	 * 將使用者選擇的 CVS 超商門市資料，附加到草稿結帳回應中，
	 * 以便在外站選取完門市返回前台時，可顯示選擇的超商資訊。
	 *
	 * @param array    $response 來自 WooCommerce Checkout API 的原始回應。
	 * @param WC_Order $order   與結帳相關的 WooCommerce 訂單物件。
	 * @return array            修改後的回應，加入了 meta data。
	 */
	public function add_cvs_meta_data_to_draft_checkout_response( $response, $server, $request ) {
		if ( $request->get_route() === '/wc/store/v1/checkout' && $request->get_method() === 'GET' ) {

			$data     = $response->get_data();
			$order_id = $data['order_id'] ?? 0;
			$order    = wc_get_order( $order_id );

				// Validate the order object
			if ( ! $order instanceof \WC_Order ) {
				return $response;
			}

			$shipping_method_id = get_selected_shipping_method_id( $order );

			if ( strpos( $shipping_method_id, 'Wooecpay_Logistic_CVS' ) !== false ) {
				$cvs_meta_data = array(
					'cvs_store_id'      => $order->get_meta( '_ecpay_logistic_cvs_store_id' ),
					'cvs_store_name'    => $order->get_meta( '_ecpay_logistic_cvs_store_name' ),
					'cvs_store_address' => $order->get_meta( '_ecpay_logistic_cvs_store_address' ),
				);

				// Add meta data to the response
				$data['meta_data'] = $cvs_meta_data;
				$response->set_data( $data );
			}
		}

		return $response;
	}

	/**
	 * 結帳過程中驗證訂單欄位
	 *
	 * 確保前台提供資料與後台資料相符、確保郵寄時，有提供完整運送地址
	 * 如果驗證失敗，拋出 RouteException。
	 *
	 * @param WC_Order        $order   The WooCommerce order object.
	 * @param WP_REST_Request $request The REST API request object containing order data.
	 *
	 * @throws RouteException if any validation fails.
	 */
	function headless_validate_order_fields( $order, $request ) {
		$payload  = $request->get_json_params();
		$order_id = intval( $request->get_param( 'order_id' ) ); // 加上清理輸入資料 check 是否需要
		$order    = wc_get_order( $order_id );
		if ( ! $order ) {
			throw new RouteException( 'headless_order_not_found', '找不到訂單', 404 );
		}

		// 先比對前端和後端值是否一致，驗證失敗返回 error 給前端中斷結帳
		$frontend_total = $payload['total_amount'];
		$order_total    = $order->get_total();
		if ( $frontend_total !== $order_total ) {
			throw new RouteException( 'headless_checkout_total_mismatch', '訂單金額不一致，請重新整理頁面再操作一次', 400 );
		}

		$frontend_shipping_method_id = $payload['shipping_method_id'];
		$shipping_method_id          = get_selected_shipping_method_id( $order );
		if ( $frontend_shipping_method_id !== $shipping_method_id ) {
			throw new RouteException( 'headless_checkout_shipping_mismatch', '運送方式不一致，請重新整理頁面再操作一次', 400 );
		}

		if ( strpos( $shipping_method_id, 'Wooecpay_Logistic_CVS' ) !== false ) {
			$frontend_receiver_store_id = $payload['receiver_store_id'];
			$CVSStoreID                 = $order->get_meta( '_ecpay_logistic_cvs_store_id' );
			if ( $frontend_receiver_store_id !== $CVSStoreID ) {
				throw new RouteException( 'headless_checkout_cvs_store_mismatch', '取貨門市不一致，請重新整理頁面再操作一次', 400 );
			}
		}

		// 郵寄寄送，須驗證的地址欄位
		if ( strpos( $shipping_method_id, 'flat_rate' ) !== false ) {
			$required_fields = array(
				'billing_address_1'  => __( 'Billing Address 1', 'woocommerce' ),
				'billing_city'       => __( 'Billing City', 'woocommerce' ),
				'billing_postcode'   => __( 'Billing Postcode', 'woocommerce' ),
				'billing_country'    => __( 'Billing Country', 'woocommerce' ),
				'shipping_address_1' => __( 'Shipping Address 1', 'woocommerce' ),
				'shipping_city'      => __( 'Shipping City', 'woocommerce' ),
				'shipping_postcode'  => __( 'Shipping Postcode', 'woocommerce' ),
				'shipping_country'   => __( 'Shipping Country', 'woocommerce' ),
			);

			// 檢查必填欄位是否為空
			foreach ( $required_fields as $field_key => $field_label ) {
				$field_value = $order->{"get_$field_key"}();

				if ( empty( $field_value ) ) {
					throw new RouteException(
						'headless_checkout_woocommerce_rest_invalid_address',
						$field_label . '的欄位必填',
						400
					);
				}
			}
		}
	}

}
