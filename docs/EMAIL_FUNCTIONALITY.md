# 自訂 Email 通知功能 - 技術文件

## 目錄

1. [功能概述](#功能概述)
2. [架構設計](#架構設計)
3. [檔案結構](#檔案結構)
4. [核心類別說明](#核心類別說明)
5. [工作流程](#工作流程)
6. [擴充指南](#擴充指南)
7. [注意事項](#注意事項)

---

## 功能概述

實作當訂單狀態從「處理中」變更為「備貨中」時，自動發送 Email 通知給顧客。

### 主要功能

- 自訂 Email 類別註冊與管理
- 訂單狀態變更時自動觸發 Email
- 支援 HTML 和純文字格式
- 可自訂 Email 主題、標題和內容
- 與 WooCommerce Email 系統完整整合

---


## 架構設計

### 設計原則

採用**職責分離（Separation of Concerns）**原則，將 Email 管理邏輯與訂單狀態管理邏輯分開：

- **WC_Order_Custom_Statuses**：僅負責訂單狀態的註冊與管理
- **WC_Email_Manager**：負責所有 Email 相關的註冊與觸發邏輯
- **自訂 Email 類別**：具體的 Email 實作類別（如 `WC_Email_Customer_Preparing_Order`）

### 架構圖

```

┌─────────────────────────────────────────┐
│     hs-coffee-headless-store.php        │
│          (主外掛檔案)                     │
└──────────────┬──────────────────────────┘
               │
       ┌───────┴────────┐
       │                │
       ▼                ▼
┌──────────────┐  ┌──────────────────┐
│ WC_Email_    │  │ WC_Order_Custom  │
│ Manager      │  │ Statuses         │
│              │  │                  │
│ - 註冊 Email  │  │ - 註冊訂單狀態     │
│ - 管理 Actions│  │ - 管理狀態列表     │
└──────┬───────┘  └──────────────────┘
       │
       ▼
┌────────────────────────────────────┐
│ 自訂 Email 類別                    │
│                                    │
│ - Email 實作                        │
│ - 觸發邏輯                          │
│ - 模板載入                          │
└──────┬─────────────────────────────┘
       │
       ▼
┌──────────────────────────────┐
│ Email Templates              │
│                              │
│ - HTML 模板                   │
│ - Plain Text 模板             │
└──────────────────────────────┘
```

---

## 檔案結構

```
hs-coffee-headless-store/
├── hs-coffee-headless-store.php          # 主外掛檔案
├── includes/
│   ├── class-wc-email-manager.php                   # Email 管理類別
│   ├── class-wc-email-*.php                         # 自訂 Email 類別
│   └── class-wc-order-custom-statuses.php           # 訂單狀態管理
└── templates/
    └── emails/
        ├── *.php                                    # HTML 模板
        └── plain/
            └── *.php                                # 純文字模板
```

---

## 核心類別說明

### 1. WC_Email_Manager

**位置**：`includes/class-wc-email-manager.php`

**職責**：管理所有自訂 Email 的註冊與觸發邏輯

#### 主要方法

##### `__construct()`
初始化 Email 管理器，註冊必要的 WordPress hooks：
- `add_filter( 'woocommerce_email_classes', array( $this, 'register_email_classes' ) )`：註冊自訂 Email 類別
- `add_filter( 'woocommerce_email_actions', array( $this, 'add_email_actions' ) )`：註冊 Email 觸發 actions

##### `register_email_classes( $emails )`
將自訂 Email 類別檔案 (eg.備貨中) 載入並加入 WooCommerce 的 Email 類別列表，使其可在後台設定中顯示和管理。

##### `add_email_actions( $actions )`
註冊訂單狀態變更的 action，當訂單狀態從「處理中」變更為「備貨中」時，WooCommerce 會自動觸發對應的 notification action。Action 名稱格式為 `woocommerce_order_status_{from}_to_{to}`，不需要包含 `_notification` 後綴，WooCommerce 會自動加上並觸發。

---

### 2. 自訂 Email 類別 (e.g. WC_Email_Customer_Preparing_Order)

所有自訂 Email 類別都必須繼承 `WC_Email`（WooCommerce 核心類別），並實作必要的方法。詳細的實作方式請參考「擴充指南」章節。

---

## 工作流程

### Email 發送流程

```
1. 訂單狀態變更
   └─> processing → preparing

2. WooCommerce 觸發 Action
   └─> woocommerce_order_status_processing_to_preparing

3. WooCommerce 自動加上 _notification
   └─> woocommerce_order_status_processing_to_preparing_notification

4. 自訂 Email 類別的 trigger() 方法被呼叫
   ├─> 設定語言環境
   ├─> 取得訂單資訊
   ├─> 設定收件人、佔位符
   └─> 發送 Email

5. 載入模板並渲染
   ├─> HTML 版本模板
   └─> Plain 版本模板
```

### 初始化流程

```
1. WordPress 載入外掛
   └─> hs-coffee-headless-store.php

2. 載入必要檔案
   ├─> class-wc-email-manager.php
   ├─> 所有自訂 Email 類別檔案
   └─> class-wc-order-custom-statuses.php

3. plugins_loaded hook 觸發
   └─> hs_coffee_headless_store_init()

4. 實例化類別
   ├─> new WC_Email_Manager()
   │   ├─> 註冊 woocommerce_email_classes filter
   │   └─> 註冊 woocommerce_email_actions filter
   └─> new WC_Order_Custom_Statuses()
       └─> 註冊自訂訂單狀態
```

---

## 擴充指南

### 新增自訂 Email 類別

#### 步驟 1：建立 Email 類別檔案

在 `includes/` 目錄下建立新的 Email 類別檔案，繼承 `WC_Email` 類別。 (eg. WC_Email_Customer_Preparing_Order)

**類別基本結構**：

##### `__construct()`
初始化 Email 類別，設定以下屬性：
- `$id`：Email 唯一識別碼
- `$customer_email`：是否為顧客 Email（`true` 或 `false`）
- `$title`：Email 標題（後台顯示用）
- `$template_html`：HTML 模板路徑（相對於 `templates/` 目錄）
- `$template_plain`：純文字模板路徑（相對於 `templates/` 目錄）
- `$placeholders`：佔位符陣列（如 `{order_date}`, `{order_number}`）

並註冊觸發 action：`add_action( 'woocommerce_order_status_{from}_to_{to}_notification', array( $this, 'trigger' ), 10, 2 )`

##### `get_default_subject()`
回傳預設 Email 主旨，可使用佔位符（如 `{order_number}`）。

##### `get_default_heading()`
回傳預設 Email 標題，可使用佔位符。

##### `trigger( $order_id, $order = false )`
當訂單狀態變更時被自動呼叫，負責：
1. 呼叫 `setup_locale()` 設定語言環境
2. 取得訂單物件（如果未提供）
3. 設定收件人、佔位符值
4. 檢查 Email 是否啟用且有收件人
5. 發送 Email
6. 呼叫 `restore_locale()` 還原語言環境

##### `get_content_html()`
載入 HTML 模板檔案並傳入必要變數（訂單、標題、額外內容等），渲染後回傳 HTML 字串。使用 `wc_get_template_html()` 載入模板。

##### `get_content_plain()`
載入純文字模板檔案並傳入必要變數，渲染後回傳純文字字串。

##### `get_default_additional_content()`
回傳顯示在 Email 主要內容下方、footer 上方的預設文字，可在後台設定中覆蓋。

**檔案結尾**：必須回傳類別實例 `return new \HS_Coffee_Headless_Store\WC_Email_XXX();`

#### 步驟 2：在 WC_Email_Manager 中註冊

修改 `class-wc-email-manager.php`：

- 在 `register_email_classes()` 方法中加入新的 Email 類別：
  ```php
  $emails['WC_Email_XXX'] = include HS_COFFEE_HEADLESS_STORE_PLUGIN_DIR . 'includes/class-wc-email-xxx.php';
  ```

- 在 `add_email_actions()` 方法中加入對應的 action（格式：`woocommerce_order_status_{from}_to_{to}`，不需要 `_notification` 後綴）

#### 步驟 3：建立 Email 模板

在 `templates/emails/` 目錄下建立：
- HTML 版本模板檔案（如 `customer-xxx-order.php`）
- `plain/` 子目錄下的純文字版本模板檔案（如 `plain/customer-xxx-order.php`）

模板中可使用以下變數：`$order`、`$email_heading`、`$additional_content`、`$sent_to_admin`、`$plain_text`、`$email`

---

---

## 注意事項

### 1. 語言環境處理

在 `trigger()` 方法中必須先呼叫 `setup_locale()` 設定語言環境，發送完成後呼叫 `restore_locale()` 還原，以確保多語言環境下 Email 內容正確顯示。

### 2. 佔位符替換

佔位符格式為 `{placeholder_name}`，在 `trigger()` 方法中設定佔位符值後，會自動在 Email 主旨和標題中替換。

### 3. Email 啟用檢查

發送前需檢查 Email 是否啟用（`$this->is_enabled()`）以及是否有收件人（`$this->get_recipient()`），只有在啟用且有收件人時才發送。

---

## 相關檔案

- `includes/class-wc-email-manager.php` - Email 管理類別
- `includes/class-wc-email-*.php` - 自訂 Email 類別檔案
- `includes/class-wc-order-custom-statuses.php` - 訂單狀態管理類別
- `templates/emails/*.php` - HTML Email 模板
- `templates/emails/plain/*.php` - 純文字 Email 模板

---

## 參考資料

- [WooCommerce Email API Documentation](https://woocommerce.github.io/code-reference/classes/WC-Email.html)
- [WooCommerce Custom Emails Guide](https://woocommerce.com/document/custom-email-actions/)

