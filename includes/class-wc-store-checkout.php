<?php
namespace HS_Coffee_Headless_Store;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // 防止直接存取
}

class WC_Store_Checkout {

	public function __construct() {
		add_filter( 'rest_post_dispatch', array( $this, 'add_cvs_meta_data_to_draft_checkout_response' ), 10, 3 );
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
}
