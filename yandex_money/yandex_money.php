<?php

/**
 * Plugin Name: yandexmoney_wp_woocommerce
 * Plugin URI: https://github.com/yandex-money/yandex-money-cms-wp-woocomerce
 * Description: Yandex.Kassa
 */

include_once 'yandex/yandex.php';
include_once 'bank/bank.php';
include_once 'terminal/terminal.php';
include_once 'webmoney/webmoney.php';

/*
add_filter( 'woocommerce_general_settings', 'add_order_ym_shopPassword' );
function add_order_ym_shopPassword( $settings ) {
  $updated_settings = array();
  foreach ( $settings as $section ) {
    if ( isset( $section['id'] ) && 'general_options' == $section['id'] &&
       isset( $section['type'] ) && 'sectionend' == $section['type'] ) {
      $updated_settings[] = array(
        'name'     => __('Яндекс.Деньги shopPassword','yandex_money'),
        'id'       => 'ym_shopPassword',
        'type'     => 'text',
        'css'      => 'min-width:300px;',
        'std'      => '',  // WC < 2.0
        'default'  => '',  // WC >= 2.0
        'desc'     => __( '<br/>Необходим для корректной работы paymentAvisoURL и checkURL. shopPassword устанавливается при регистрации магазина в системе Яндекс.Деньги', 'yandex_money' ),
      );
	  
		$pages = get_pages(); 
		$p_arr = array();
		foreach ( $pages as $page ) 
			$p_arr[$page->ID] = $page->post_title;
		
    }
    $updated_settings[] = $section;
  }
  return $updated_settings;
}
*/



add_action('admin_menu', 'register_yandexMoney_submenu_page');

function register_yandexMoney_submenu_page() {
	add_submenu_page( 'woocommerce', 'Яндекс.Деньги Настройка', 'Яндекс.Деньги Настройка', 'manage_options', 'yandex_money_menu', 'yandexMoney_submenu_page_callback' ); 
}

function yandexMoney_submenu_page_callback() {
?>
<div class="wrap">
<h2>Настройки Яндекс.Деньги</h2>

<form method="post" action="options.php">
<?php wp_nonce_field('update-options'); ?>

<table class="form-table">

<tr valign="top">
<th scope="row">paymentAvisoUrl and checkUrl<br/><span style="line-height: 1;font-weight: normal;font-style: italic;font-size: 12px;">Генерируются автоматически для Вашего сайта<span></th>
<td><code><?php
 echo 'https://'.$_SERVER['HTTP_HOST']. '/?yandex_money=check'; 
 ?></code></td>
</tr>

<tr valign="top">
<th scope="row">Демо режим<br/><span style="line-height: 1;font-weight: normal;font-style: italic;font-size: 12px;">Включить демо режим для тестирования<span></th>
<td><input type="checkbox" name="ym_Demo" <?php echo get_option('ym_Demo')=='on'?'checked="checked"':''; ?>" /></td>
</tr>

<tr valign="top">
<th scope="row">Scid<br/><span style="line-height: 1;font-weight: normal;font-style: italic;font-size: 12px;">Номер витрины магазина ЦПП<span></th>
<td><input type="text" name="ym_Scid" value="<?php echo get_option('ym_Scid'); ?>" /></td>
</tr>
 
<tr valign="top">
<th scope="row">ShopID<br/><span style="line-height: 1;font-weight: normal;font-style: italic;font-size: 12px;">Номер магазина ЦПП<span></th>
<td><input type="text" name="ym_ShopID" value="<?php echo get_option('ym_ShopID'); ?>" /></td>
</tr>

<tr valign="top">
<th scope="row">shopPassword<br/><span style="line-height: 1;font-weight: normal;font-style: italic;font-size: 12px;">Устанавливается при регистрации магазина в системе Яндекс.Деньги<span></th>
<td><input type="text" name="ym_shopPassword" value="<?php echo get_option('ym_shopPassword'); ?>" /></td>
</tr>

</table>

<input type="hidden" name="action" value="update" />
<input type="hidden" name="page_options" value="ym_Scid,ym_ShopID,ym_shopPassword,ym_Demo" />

<p class="submit">
<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
</p>

</form>
</div>
<?php
}

add_action('parse_request', 'YMcheckPayment');

function YMcheckPayment()
{
	global $wpdb;
	// if ($_REQUEST['yandex_money'] == 'hash') {
		// echo md5('');
	// }
	if ($_REQUEST['yandex_money'] == 'check') {
		$hash = md5($_POST['action'].';'.$_POST['orderSumAmount'].';'.$_POST['orderSumCurrencyPaycash'].';'.
					$_POST['orderSumBankPaycash'].';'.$_POST['shopId'].';'.$_POST['invoiceId'].';'.
					$_POST['customerNumber'].';'.get_option('ym_shopPassword'));
		header('Content-Type: application/xml');
		if (strtolower($hash) != strtolower($_POST['md5']) and (isset($_POST['md5']))) { // !=
			$code = 1;
			echo '<?xml version="1.0" encoding="UTF-8"?><checkOrderResponse performedDatetime="'. $_POST['requestDatetime'] .'" code="'.$code.'"'. ' invoiceId="'. $_POST['invoiceId'] .'" shopId="'. get_option('ym_ShopID') .'" message="bad md5"/>';
		} else {
			$order = $wpdb->get_row('SELECT * FROM '.$wpdb->prefix.'posts WHERE ID = '.(int)$_POST['customerNumber']);
			$order_summ = get_post_meta($order->ID,'_order_total',true);
			if (!$order) {
				$code = 200;
				$answer = '<?xml version="1.0" encoding="UTF-8"?><checkOrderResponse performedDatetime="'. $_POST['requestDatetime'] .'" code="'.$code.'"'. ' invoiceId="'. $_POST['invoiceId'] .'" shopId="'. get_option('ym_ShopID') .'" message="wrong customerNumber"/>';
			} elseif ($order_summ != $_POST['orderSumAmount']) { // !=
				$code = 100;
				$answer = '<?xml version="1.0" encoding="UTF-8"?><checkOrderResponse performedDatetime="'. $_POST['requestDatetime'] .'" code="'.$code.'"'. ' invoiceId="'. $_POST['invoiceId'] .'" shopId="'. get_option('ym_ShopID') .'" message="wrong orderSumAmount"/>';
			} else {
				$code = 0;
				if ($_POST['action'] == 'paymentAviso') {
					$order_w = new WC_Order( $order->ID );
					$order_w->update_status('processing', __( 'Awaiting BACS payment', 'woocommerce' ));
					$order_w->reduce_order_stock();
					$answer = '<?xml version="1.0" encoding="UTF-8"?><paymentAvisoResponse performedDatetime="'.date('c').'" code="'.$code.'" invoiceId="'.$_POST['invoiceId'].'" shopId="'.get_option('ym_ShopID').'" />';
				}
				else{
					$answer = '<?xml version="1.0" encoding="UTF-8"?><checkOrderResponse performedDatetime="'.date('c').'" code="'.$code.'" invoiceId="'.$_POST['invoiceId'].'" shopId="'.get_option('ym_ShopID').'" />';
				}
			}
		}
		
		die($answer);
		
	}
}


