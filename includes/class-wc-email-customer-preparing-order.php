<?php
namespace HS_Coffee_Headless_Store;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 通知顧客備貨中
 *
 * 當訂單狀態變更為「備貨中」時，發送 Email 通知給顧客
 */
class WC_Email_Customer_Preparing_Order extends \WC_Email {

	public function __construct() {
		$this->id             = 'customer_preparing_order';
		$this->customer_email = true;
		$this->title          = '備貨中的訂單';
		$this->template_html  = 'emails/customer-preparing-order.php';
		$this->template_plain = 'emails/plain/customer-preparing-order.php';
		$this->placeholders   = array(
			'{order_date}'   => '',
			'{order_number}' => '',
		);

		// Triggers for this email.
		add_action( 'woocommerce_order_status_processing_to_preparing_notification', array( $this, 'trigger' ), 10, 2 );

		// Call parent constructor.
		parent::__construct();
	}

	/**
	 * Get email subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return '商品備貨中 | 訂單編號：{order_number}';
	}

	/**
	 * Get email heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return '我們已開始製作您的咖啡商品。';
	}

	/**
	 * 觸發寄送 Email
	 *
	 * @param int            $order_id
	 * @param WC_Order|false $order
	 */
	public function trigger( $order_id, $order = false ) {
		$this->setup_locale();

		if ( $order_id && ! is_a( $order, 'WC_Order' ) ) {
			$order = wc_get_order( $order_id );
		}

		if ( is_a( $order, 'WC_Order' ) ) {
			$this->object                         = $order;
			$this->recipient                      = $this->object->get_billing_email();
			$this->placeholders['{order_date}']   = wc_format_datetime( $this->object->get_date_created() );
			$this->placeholders['{order_number}'] = $this->object->get_order_number();
		}

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}

	/**
	 * Get content html.
	 *
	 * @return string
	 */
	public function get_content_html() {
		$args = array(
			'order'              => $this->object,
			'email_heading'      => $this->get_heading(),
			'additional_content' => $this->get_additional_content(),
			'sent_to_admin'      => false,
			'plain_text'         => false,
			'email'              => $this,
		);

		return wc_get_template_html( $this->template_html, $args, '', HS_COFFEE_HEADLESS_STORE_PLUGIN_DIR . 'templates/' );
	}

	/**
	 * Get content plain.
	 *
	 * @return string
	 */
	public function get_content_plain() {
		return wc_get_template_html(
			$this->template_plain,
			array(
				'order'              => $this->object,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => false,
				'plain_text'         => true,
				'email'              => $this,
			),
			'',
			HS_COFFEE_HEADLESS_STORE_PLUGIN_DIR . 'templates/'
		);
	}

	/**
	 * Default content to show below main email content. (before footer)
	 *
	 * @return string
	 */
	public function get_default_additional_content() {
		return '我們已開始製作您的咖啡商品。如有任何問題，歡迎與我們聯繫。';
	}
}

return new \HS_Coffee_Headless_Store\WC_Email_Customer_Preparing_Order();
