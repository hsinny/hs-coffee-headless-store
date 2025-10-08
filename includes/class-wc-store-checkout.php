<?php
namespace HS_Coffee_Headless_Store;

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // 防止直接存取
}

class WC_Store_Checkout {

	public function __construct() {
		add_filter( 'woocommerce_default_address_fields', array( $this, 'headless_modify_default_address_fields' ), 10, 1 );
		// add_filter( 'rest_post_dispatch', array( $this, 'add_cvs_meta_data_to_draft_checkout_response' ), 10, 3 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'headless_validate_order_fields' ), 10, 2 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'headless_save_ecpay_logistic_fields' ), 20, 2 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'send_new_order_email' ), 10, 1 );
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
		if ( $request->get_method() !== 'POST' ) {
			return;
		}

		$payload = $request->get_json_params();

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

		// CVS：驗證收件欄位
		if ( strpos( $shipping_method_id, 'Wooecpay_Logistic_CVS' ) === true ) {
			$required_fields = array(
				'receiver_store_id'      => 'receiver_store_id 收件門市代號',
				'receiver_store_name'    => 'receiver_store_name 收件門市名稱',
				'receiver_store_address' => 'receiver_store_address 收件門市地址',
				'receiver_name'          => 'receiver_name 收件人姓名',
				'receiver_cellphone'     => 'receiver_cellphone 收件人手機',
				'receiver_email'         => 'receiver_email 收件人電子郵件',
			);

			// 檢查 $payload 中必填欄位是否為空
			foreach ( $required_fields as $field_key => $field_label ) {
				$field_value = $payload[ $field_key ] ?? null;

				if ( empty( $field_value ) ) {
					throw new RouteException(
						'headless_checkout_woocommerce_rest_invalid_address',
						$field_label . '的欄位必填',
						400
					);
				}
			}
		}

		// 郵寄：驗證地址欄位
		if ( strpos( $shipping_method_id, 'flat_rate' ) === true ) {
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

			// 檢查 $payload 中必填欄位是否為空
			foreach ( $required_fields as $field_key => $field_label ) {
				$field_value = $payload[ $field_key ] ?? null;

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

	/**
	 * 更新訂單中 ECPay 物流欄位
	 *
	 * @param WC_Order $order   The WooCommerce order object.
	 * @param array    $request The request data passed during the checkout process.
	 *
	 * @return void
	 */
	function headless_save_ecpay_logistic_fields( $order, $request ) {
		$shipping_method_id = get_selected_shipping_method_id( $order );

		if ( $request->get_method() !== 'POST' || strpos( $shipping_method_id, 'Wooecpay_Logistic_CVS' ) === false ) {
			return;
		}

		$payload = $request->get_json_params();
		// CVS 綠界物流
		$receiver_store_id      = sanitize_text_field( $payload['receiver_store_id'] );
		$receiver_store_name    = sanitize_text_field( $payload['receiver_store_name'] );
		$receiver_store_address = sanitize_text_field( $payload['receiver_store_address'] );
		$receiver_name          = sanitize_text_field( $payload['receiver_name'] );
		$receiver_cellphone     = sanitize_text_field( $payload['receiver_cellphone'] );
		$receiver_email         = sanitize_text_field( $payload['receiver_email'] ); // 文件有該欄位，但綠界建立物流單號沒有用到

		$order->set_shipping_company( '' );
		$order->set_shipping_address_2( '' );
		$order->set_shipping_city( '' );
		$order->set_shipping_state( '' );
		$order->set_shipping_postcode( '' );
		$order->set_shipping_address_1( $receiver_store_address );

		$order->update_meta_data( '_ecpay_logistic_cvs_store_id', $receiver_store_id );
		$order->update_meta_data( '_ecpay_logistic_cvs_store_name', $receiver_store_name );
		$order->update_meta_data( '_ecpay_logistic_cvs_store_address', $receiver_store_address );
		$order->update_meta_data( 'wooecpay_shipping_phone', $receiver_cellphone );
		// 透過後台建立綠界物流單號，ecpay 會拿 shipping 欄位作為 receiver_name

		$order->add_order_note( sprintf( __( 'CVS store %1$s (%2$s, %3$s)', 'ecpay-ecommerce-for-woocommerce' ), $shipping_method_id, $receiver_store_name, $receiver_store_id ) );
	}

	/**
	 * 發送新訂單的通知電子郵件。
	 *
	 * 在處理新訂單成立時，觸發寄送新訂單通知信。
	 *
	 * @param WC_Order $order 要發送電子郵件的 WooCommerce 訂單物件。
	 *
	 * @return void
	 */
	function send_new_order_email( $order ) {
		$mailer = WC()->mailer();
		$mails  = $mailer->get_emails();

		$order_id = $order->get_id();

		if ( ! empty( $mails['WC_Email_New_Order'] ) ) {
			$mails['WC_Email_New_Order']->trigger( $order_id );
		}
	}
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
	// public function add_cvs_meta_data_to_draft_checkout_response( $response, $server, $request ) {
	// if ( $request->get_route() === '/wc/store/v1/checkout' && $request->get_method() === 'GET' ) {

	// $data     = $response->get_data();
	// $order_id = $data['order_id'] ?? 0;
	// $order    = wc_get_order( $order_id );

	// Validate the order object
	// if ( ! $order instanceof \WC_Order ) {
	// return $response;
	// }

	// $shipping_method_id = get_selected_shipping_method_id( $order );

	// if ( strpos( $shipping_method_id, 'Wooecpay_Logistic_CVS' ) !== false ) {
	// $cvs_meta_data = array(
	// 'cvs_store_id'      => $order->get_meta( '_ecpay_logistic_cvs_store_id' ),
	// 'cvs_store_name'    => $order->get_meta( '_ecpay_logistic_cvs_store_name' ),
	// 'cvs_store_address' => $order->get_meta( '_ecpay_logistic_cvs_store_address' ),
	// );

	// Add meta data to the response
	// $data['meta_data'] = $cvs_meta_data;
	// $response->set_data( $data );
	// }
	// }

	// return $response;
	// }
