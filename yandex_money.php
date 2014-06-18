<?php
/*
Plugin Name: Yandex.Money for woocomerce
Plugin URI: http://casepress.org
Version: 1.0.0
Author: http://casepress.org
Author URI: http://casepress.org
Description: 
*/
	
include_once 'yandex/yandex.php';
include_once 'bank/bank.php';
include_once 'terminal/terminal.php';
include_once 'webmoney/webmoney.php';


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
		
		$updated_settings[] = array(
			'name'     => __('Яндекс.Деньги Страница успешной оплаты','yandex_money'),
			'id'       => 'ym_success_pay',
			'type'     => 'select',
			'options'  => $p_arr,
			'css'      => 'min-width:300px;',
			'std'      => '',  // WC < 2.0
			'default'  => '',  // WC >= 2.0
			'desc'     => __( 'Страница перехода при успешной оплаты (successURL)', 'yandex_money' ),
		  );
	  
	  	$updated_settings[] = array(
			'name'     => __('Яндекс.Деньги Страница ошибки оплаты','yandex_money'),
			'id'       => 'ym_fail_pay',
			'type'     => 'select',
			'options'  => $p_arr,
			'css'      => 'min-width:300px;',
			'std'      => '',  // WC < 2.0
			'default'  => '',  // WC >= 2.0
			'desc'     => __( 'Страница перехода при ошибки оплаты (failURL)', 'yandex_money' ),
		  );
	
	
    }
    $updated_settings[] = $section;
  }
  return $updated_settings;
}



add_action('parse_request', 'YMcheckPayment');

function YMcheckPayment()
{
	global $wpdb;
	if ($_REQUEST['yandex_money'] == 'check') {
		//die('1');
		$hash = md5($_POST['action'].';'.$_POST['orderSumAmount'].';'.$_POST['orderSumCurrencyPaycash'].';'.
					$_POST['orderSumBankPaycash'].';'.$_POST['shopId'].';'.$_POST['invoiceId'].';'.
					$_POST['customerNumber'].';'.get_option('ym_shopPassword'));
		if (strtolower($hash) != strtolower($_POST['md5'])) { // !=
			$code = 1;
		} else {
			$order = $wpdb->get_row('SELECT * FROM '.$wpdb->prefix.'posts WHERE ID = '.(int)$_POST['customerNumber']);
			$order_summ = get_post_meta($order->ID,'_order_total',true);
			if (!$order) {
				$code = 200;
			} elseif ($order_summ != $_POST['orderSumAmount']) { // !=
				$code = 100;
			} else {
				$code = 0;
				if ($_POST['action'] == 'paymentAviso') {
					$order_w = new WC_Order( $order->ID );
					$order_w->update_status('processing', __( 'Awaiting BACS payment', 'woocommerce' ));
					$order_w->reduce_order_stock();
					
					$code = 1000;
					header('Content-Type: application/xml');
					include('payment_xml.php');
					die();
				}
				else{
					header('Content-Type: application/xml');
					include('check_xml.php');
					die();
				}
			}
		}
		
		die();
		
	}
}


