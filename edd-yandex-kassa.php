<?php

// registers the gateway
function pw_edd_register_gateway($gateways) {
	$gateways['yandex'] = array('admin_label' => 'Yandex.Kassa', 'checkout_label' => __('Yandex.Kassa', 'pw_edd'));
	return $gateways;
}
add_filter('edd_payment_gateways', 'pw_edd_register_gateway');

// remove the default CC form
add_action('edd_yandex_cc_form', '__return_false');

// adds the settings to the Payment Gateways section
function pw_edd_add_settings($settings) {

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

// process payment
function gateway_function_to_process_payment($purchase_data) {
	// payment processing happens here
	// if (edd_is_test_mode()) {
	//
	// } else {
	//
	// }

	$purchase_summary = edd_get_purchase_summary($purchase_data);
	// var_dump($purchase_data);

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

	// echo $purchase_data['purchase_key'];

	// Record the pending payment
	$payment = edd_insert_payment($payment_data);

	// Setup Yandex.Kassa arguments
	$yandex_args = array(
		'ShopID'        =>  edd_get_option('ya_shop_id', false),
		'scid'          =>  edd_get_option('ya_scid', false),
		'cps_email'     =>  $purchase_data['user_email'],
		'Sum'           =>  $purchase_data['price'],
		'orderNumber'   =>  $purchase_data['purchase_key'],
		'orderDetails'  =>  $purchase_data['cart_details'],
		'CustName'      =>  $purchase_data['user_info']['first_name'],
		'paymentType'   =>  'AC'
	);

	// Build query
	$yandex_redirect  = 'https://money.yandex.ru/eshop.xml?';
	$yandex_redirect .= http_build_query( $yandex_args );

	// Redirect
	// wp_redirect( $yandex_redirect );

	// if the merchant payment is complete, set a flag
	$merchant_payment_confirmed = false;

	if ($merchant_payment_confirmed) { // this is used when processing credit cards on site

		// once a transaction is successful, set the purchase to complete
		edd_update_payment_status($payment, 'complete');

		// go to the success page
		edd_send_to_success_page();

	} else {
		$fail = true; // payment wasn't recorded
	}
}
add_action('edd_gateway_yandex', 'gateway_function_to_process_payment');

?>
