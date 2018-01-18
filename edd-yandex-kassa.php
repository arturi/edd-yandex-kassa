<?php /*

**************************************************************************

Plugin Name: EDD Yandex Kassa
Description: Adds Yandex.Kassa payment gateway support to Easy Digital Downloads
Version: 0.1.0
Author: Artur Paikin, Vadim Sigaev 
Author URI: http://unebaguette.com
License: MIT

**************************************************************************/

add_action('init', 'yandex_result_hooks');

function yandex_result_hooks () {
	if ( isset($_GET['yk-success']) ) {
		edd_empty_cart();
		edd_send_to_success_page();
		die();
	} else if ( isset($_GET['yk-fail']) ) {
		wp_redirect(edd_get_failed_transaction_uri());
		die();
	}
}

add_action('init', 'yandex_url_hooks');

function yandex_url_hooks () {
	if ( $_GET['yk-check'] || $_GET['yk-aviso'] ) {
		$ya_mode = $_GET['yk-check'] ? 'check' : 'aviso';
		$payment_id = intval($_POST['orderNumber']);
		
		if ( !is_integer($payment_id) ) {
			wp_die('Bad payment');
		}

		$payment_amount = edd_get_payment_amount($payment_id);

		if ( intval($payment_amount) !== intval($_POST['orderSumAmount']) ) {
			wp_die('Bad amount');
		}

		$code = 0;

		// Проверка shopPassowd'а!
		
		// $hash = md5($_POST['action'].';'.$_POST['orderSumAmount'].';'.$_POST['orderSumCurrencyPaycash'].';'.$_POST['orderSumBankPaycash'].';'.edd_get_option('shopId',false).';'.$_POST['invoiceId'].';'.$_POST['customerNumber'].';'.edd_get_option('shopPassword',false));		
		// if (strtolower($hash) != strtolower($_POST['md5'])){ 
		// 	$code = 1;
		// }
		// else {
		// 	$code = 0;
		// }

		echo '<?xml version="1.0" encoding="UTF-8"?>';
		if ( $ya_mode == 'check' ) {
			echo '<checkOrderResponse performedDatetime="'. $_POST['requestDatetime'] .'" code="'.$code.'"'. ' invoiceId="'. $_POST['invoiceId'] .'" shopId="'. edd_get_option('ya_shop_id', false) .'"/>';
		} else if ( $ya_mode == 'aviso' ) {
			echo '<paymentAvisoResponse performedDatetime="'. $_POST['requestDatetime'] .'" code="'.$code.'" invoiceId="'. $_POST['invoiceId'] .'" shopId="'. edd_get_option('ya_shop_id', false) .'"/>';

			edd_update_payment_status($payment_id, 'completed');
		}
		die();
	}
}

// Register EDD Gateway
function pw_edd_register_gateway ($gateways) {
	$gateways['yandex'] = array('admin_label' => 'Yandex.Kassa', 'checkout_label' => __('Yandex.Kassa', 'pw_edd'));
	return $gateways;
}
add_filter('edd_payment_gateways', 'pw_edd_register_gateway');

add_action('edd_yandex_cc_form', '__return_false');

// Add EDD Payment Gateway Settings
function pw_edd_add_settings ($settings) {
	$yandex_gateway_settings = array(
		array(
			'id' => 'yandex_gateway_settings',
			'name' => '<strong>' . __('Yandex.Kassa Settings', 'pw_edd') . '</strong>',
			'desc' => __('Configure the gateway settings', 'pw_edd'),
			'type' => 'header'
		),
		array(
			'id' => 'ya_shop_id',
			'name' => __('Shop ID', 'pw_edd'),
			'desc' => __('Enter your Yandex.Kassa Shop ID', 'pw_edd'),
			'type' => 'text',
			'size' => 'regular'
		),
		array(
			'id' => 'ya_scid',
			'name' => __('SCID', 'pw_edd'),
			'desc' => __('Enter your Yandex.Kassa SCID', 'pw_edd'),
			'type' => 'text',
			'size' => 'regular'
		)

	);

	return array_merge($settings, $yandex_gateway_settings);
}
add_filter('edd_settings_gateways', 'pw_edd_add_settings');

// Process payment
function gateway_function_to_process_payment($purchase_data) {
	// payment processing happens here
	var_dump( edd_is_test_mode() );

	if (edd_is_test_mode()) {
		$yandex_redirect  = 'https://demomoney.yandex.ru/eshop.xml?';
	} else {
		$yandex_redirect  = 'https://money.yandex.ru/eshop.xml?';
	}

	$purchase_summary = edd_get_purchase_summary($purchase_data);

	$payment_data = array(
		'price' => $purchase_data['price'],
		'date' => $purchase_data['date'],
		'user_email' => $purchase_data['user_email'],
		'purchase_key' => $purchase_data['purchase_key'],
		'currency' => edd_get_currency(),
		'downloads' => $purchase_data['downloads'],
		'cart_details' => $purchase_data['cart_details'],
		'user_info' => $purchase_data['user_info'],
		'status' => 'pending'
	);

	// Record the pending payment
	$payment = edd_insert_payment($payment_data);

	// Setup Yandex.Kassa arguments
	$yandex_args = array(
		'ShopID'        =>  edd_get_option('ya_shop_id', false),
		'scid'          =>  edd_get_option('ya_scid', false),
		'cps_email'     =>  $purchase_data['user_email'],
		'Sum'           =>  $purchase_data['price'],
		'orderNumber'   =>  $payment,
		'CustName'      =>  $purchase_data['user_info']['first_name'],
		'paymentType'   =>  'AC'
	);

	$yandex_redirect .= http_build_query( $yandex_args );
	wp_redirect( $yandex_redirect );
}
add_action('edd_gateway_yandex', 'gateway_function_to_process_payment');

?>
