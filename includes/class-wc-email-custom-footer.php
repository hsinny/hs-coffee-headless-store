<?php
namespace HS_Coffee_Headless_Store;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Email_Custom_Footer {

	const OPTION_OFFICIAL_LINE_ID  = 'hs_official_line_id';
	const OPTION_OFFICIAL_LINE_URL = 'hs_official_line_url';

	public function __construct() {
		// 將 {official_line_id} 與 {official_line_url} 佔位符替換為設定值
		add_filter( 'woocommerce_email_format_string', array( $this, 'replace_email_placeholders' ), 10, 2 );

		// 在 WooCommerce → 設定 → 電子郵件 增加設定欄位
		add_filter( 'woocommerce_email_settings', array( $this, 'register_email_setting_field' ) );

		// 在信件 footer 顯示 LINE 資訊
		add_action( 'woocommerce_email_footer', array( $this, 'add_line_info_to_email_footer' ), 10, 1 );
	}

	function add_line_info_to_email_footer( $email ) {
		$line_id  = get_option( self::OPTION_OFFICIAL_LINE_ID, '' );
		$line_url = get_option( self::OPTION_OFFICIAL_LINE_URL, '' );

		if ( empty( $line_id ) && empty( $line_url ) ) {
			return;
		}

		echo '<div style="text-align:center; font-size:14px; color:#777;">';

		// 若有 URL，顯示為可點擊連結（以 LINE ID 作為連結文字；若無 ID 則顯示「官方 LINE」）
		if ( ! empty( $line_url ) ) {
			$link_text = ! empty( $line_id ) ? esc_html( $line_id ) : '官方 LINE';
			echo '若有任何問題，歡迎透過小院店家 LINE (';
			echo '<a href="' . esc_url( $line_url ) . '" target="_blank" rel="noopener noreferrer"><strong>' . $link_text . '</strong></a>';
			echo ') 與我們聯繫';
		} else {
			// 只有 ID，維持原本顯示
			echo '若有任何問題，歡迎透過小院店家 LINE (<strong>' . esc_html( $line_id ) . '</strong>) 與我們聯繫。';
		}

		echo '</div>';
	}

	/**
	 * 將 {official_line_id} 與 {official_line_url} 佔位符替換為後台設定值
	 */
	public function replace_email_placeholders( $content, $email ) {
		$line_id  = get_option( self::OPTION_OFFICIAL_LINE_ID, '' );
		$line_url = get_option( self::OPTION_OFFICIAL_LINE_URL, '' );

		if ( ! empty( $line_id ) ) {
			$content = str_replace( '{official_line_id}', $line_id, $content );
		}
		if ( ! empty( $line_url ) ) {
			// 置換時使用原始 URL（前端 template 若需輸出 HTML link 可自行組成）
			$content = str_replace( '{official_line_url}', esc_url( $line_url ), $content );
		}

		return $content;
	}

	/**
	 * 在 WooCommerce 電子郵件設定頁新增欄位（新增 LINE 連結欄位）
	 */
	public function register_email_setting_field( $settings ) {
		$field = array(
			'title'    => '官方 LINE ID {official_line_id}',
			'desc'     => '可在 Email 主旨/標題/附加內容使用 {official_line_id} 佔位符。',
			'id'       => self::OPTION_OFFICIAL_LINE_ID,
			'type'     => 'text',
			'default'  => '',
			'desc_tip' => true,
			'css'      => 'min-width: 300px;',
		);

		$field_url = array(
			'title'    => '官方 LINE 連結 {official_line_url}',
			'desc'     => '可在 Email 內容使用 {official_line_url} 佔位符；請輸入完整 URL（例如：https://line.me/xxxxx）。',
			'id'       => self::OPTION_OFFICIAL_LINE_URL,
			'type'     => 'text',
			'default'  => '',
			'desc_tip' => true,
			'css'      => 'min-width: 300px;',
		);

		$fields = array( $field, $field_url );

		// 欄位位置：優先，直接插在「頁尾文字」(woocommerce_email_footer_text) 後面
		foreach ( $settings as $index => $setting ) {
			if ( isset( $setting['id'] ) && 'woocommerce_email_footer_text' === $setting['id'] ) {
				array_splice( $settings, $index + 1, 0, $fields );
				return $settings;
			}
		}

		// 備援：附加到設定陣列末端
		return array_merge( $settings, $fields );
	}
}
