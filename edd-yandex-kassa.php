<?php
 
/**
 *
 * Plugin Name: Yandex.Kassa gateway for Easy Digital Downloads
 * Description: Adds support for Yandex.Kassa gateway to Easy Digital Downloads. Sends receipts to the tax office (54-ФЗ). Address shortcuts: /kassa-check, /kassa-aviso, /kassa-success, /kassa-fail
 * Version: 0.7.5
 * Author: Sheens, Artur Paikin (Baguette)
 * Author URI: https://sheensay.ru/?p=3691, http://unebaguette.com
 *
 * ************************************************************************ */

defined( 'ABSPATH' ) or exit;

function log_to_browser_console($obj) {
	$js = json_encode($obj);
	print_r('<script>console.log('.$js.')</script>');
}
 
/**
 *  Activation
 */
register_activation_hook( __FILE__, 'rewrite_yandex_kassa_urls__activation' );
 
function rewrite_yandex_kassa_urls__activation() {
    flush_rewrite_rules();
}
 
/**
 * WP Rewrite
 */
add_filter( 'rewrite_rules_array', 'rewrite_yandex_kassa_urls__rewrite' );
 
function rewrite_yandex_kassa_urls__rewrite( $rules ) {
    $new = array(
        'kassa-check$' => 'index.php?yk-check=1',
        'kassa-aviso$' => 'index.php?yk-aviso=1',
        'kassa-success$' => 'index.php?yk-success=1',
        'kassa-fail$' => 'index.php?yk-fail=1',
    );
 
    return $new + $rules;
}
 
add_filter( 'query_vars', 'rewrite_yandex_kassa_urls__rewrite_vars' );
 
function rewrite_yandex_kassa_urls__rewrite_vars( $vars ) {
 
    array_push( $vars, 'yk-check', 'yk-aviso', 'yk-success', 'yk-fail' );
    return $vars;
}
 
/**
 * Yandex Kassa
 */
add_action( 'wp', 'yandex_result_hooks' );
 
function yandex_result_hooks() {
 
    if ( get_query_var( 'yk-success' ) ) {
        edd_empty_cart();
        edd_send_to_success_page();
        exit;
    } else if ( get_query_var( 'yk-fail') )  {
        wp_redirect( edd_get_failed_transaction_uri() );
        exit;
    }
}
 
add_action( 'wp', 'yandex_url_hooks' );
 
function yandex_url_hooks() {
    if ( get_query_var( 'yk-check') || get_query_var( 'yk-aviso') ) {
        $ya_mode = get_query_var( 'yk-check') ? 'check' : 'aviso';
        $payment_id = intval( $_POST['orderNumber'] );
 
        if ( !is_integer( $payment_id ) ) {
            wp_die( 'Bad payment' );
        }
 
        $payment_amount = edd_get_payment_amount( $payment_id );
 
        if ( intval( $payment_amount ) !== intval( $_POST['orderSumAmount'] ) ) {
            wp_die( 'Bad amount' );
        }
 
        $code = 0;
 
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        if ( $ya_mode == 'check' ) {
            echo '<checkOrderResponse performedDatetime="' . $_POST['requestDatetime'] . '" code="' . $code . '"' . ' invoiceId="' . $_POST['invoiceId'] . '" shopId="' . edd_get_option( 'ya_shop_id', false ) . '"/>';
        } else if ( $ya_mode == 'aviso' ) {
            echo '<paymentAvisoResponse performedDatetime="' . $_POST['requestDatetime'] . '" code="' . $code . '" invoiceId="' . $_POST['invoiceId'] . '" shopId="' . edd_get_option( 'ya_shop_id', false ) . '"/>';
 
            edd_update_payment_status( $payment_id, 'completed' );
        }
        exit;
    }
}
 
// Register the Yandex.Kassa payment gateway in Easy Digital Download
add_filter( 'edd_payment_gateways', 'sh_edd_register_gateway' );
 
function sh_edd_register_gateway( $gateways ) {
    $gateways['yandex'] = array( 'admin_label' => 'Yandex Kassa', 'checkout_label' => __( 'Yandex Kassa', 'sh_edd' ) );
    return $gateways;
}
 
add_action( 'edd_yandex_cc_form', '__return_false' );
 
// Add settings for Yandex.Kassa payment gateway to Easy Digital Download
add_filter( 'edd_settings_gateways', 'sh_edd_add_settings' );
 
function sh_edd_add_settings( $settings ) {
    $yandex_gateway_settings = array(
        array(
            'id' => 'yandex_gateway_settings',
            'name' => '<strong>' . __( 'Yandex.Kassa Settings', 'sh_edd' ) . '</strong>',
            'desc' => __( 'Configure the gateway settings', 'sh_edd' ),
            'type' => 'header'
        ),
        array(
            'id' => 'ya_shop_id',
            'name' => __( 'Shop ID' ),
            'desc' => __( 'Введите Shop ID от Яндекс Кассы' ),
            'type' => 'text',
            'size' => 'regular'
        ),
        array(
            'id' => 'ya_scid',
            'name' => __( 'SCID' ),
            'desc' => __( 'Введите SCID от Яндекс Кассы' ),
            'type' => 'text',
            'size' => 'regular'
        )
    );
 
    return array_merge( $settings, $yandex_gateway_settings );
}
 
// Yandex.Kassa actual payment processing
add_action( 'edd_gateway_yandex', 'gateway_function_to_process_payment' );
 
function gateway_function_to_process_payment( $purchase_data ) {
    // var_dump( edd_is_test_mode() );
    // log_to_browser_console($purchase_data);
 
    if ( edd_is_test_mode() ) {
        $yandex_redirect = 'https://demomoney.yandex.ru/eshop.xml?';
    } else {
        $yandex_redirect = 'https://money.yandex.ru/eshop.xml?';
    }
 
    $purchase_summary = edd_get_purchase_summary( $purchase_data );
 
    $payment_data = array(
        'price'         => $purchase_data['price'],
        'date'          => $purchase_data['date'],
        'user_email'    => $purchase_data['user_email'],
        'purchase_key'  => $purchase_data['purchase_key'],
        'currency'      => edd_get_currency(),
        'downloads'     => $purchase_data['downloads'],
        'cart_details'  => $purchase_data['cart_details'],
        'user_info'     => $purchase_data['user_info'],
        'status'        => 'pending'
    );
 
    // Record the pending payment
	$payment = edd_insert_payment( $payment_data );
		
    // Create Kassa receipt as documented in
    // https://github.com/yandex-money/yandex-money-joinup/blob/master/demo/54-fz.md

    // $edd_total_price = $purchase_data['subtotal'];
    $edd_total_fees = 0;

    foreach ($purchase_data['fees'] as $k => $v) {
        // log_to_browser_console('fee amount: ' . $v['amount']);
        $edd_total_fees = $edd_total_fees + $v['amount'];
    }

    $edd_total_fees = abs($edd_total_fees);
    $remaining_discount = $edd_total_fees;

    // log_to_browser_console('total fees: ' . $edd_total_fees);

    // $edd_total_fees_percentage = $edd_total_fees / $edd_total_price;
    // $edd_total_fees_percentage = round($edd_total_fees_percentage, 2);

    $ym_merchant_receipt_items = array();
    
    $total_all_items_price = 0;

    foreach ($purchase_data['cart_details'] as $k => $v) {
        // $item_percentage_with_fee = 1 + $edd_total_fees_percentage;
        // $item_price_after_fee = $v['item_price'] * $item_percentage_with_fee;
        // $item_price_after_fee = round($item_price_after_fee, 2);
        
        $item_price = $v['item_price'];
        $item_price_after_fee = $v['item_price'];
        // log_to_browser_console('remaining fee: ' . $remaining_discount);
        if ($remaining_discount > 0) {
            if ($item_price >= $remaining_discount) {
                $item_price_after_fee = $item_price_after_fee - $remaining_discount;
                $remaining_discount = 0;
            } else {
                $diff = $remaining_discount - ($remaining_discount - $item_price);
                $item_price_after_fee = $item_price - $diff;
                $remaining_discount = $remaining_discount - $diff;
            }
        }
        $total_all_items_price = $total_all_items_price + $item_price_after_fee;

        // log_to_browser_console('item price before fee: ' . $item_price);
        // log_to_browser_console('item percentage with fee: ' . $item_percentage_with_fee);
        // log_to_browser_console('item price after fee: ' . $item_price_after_fee);

        $ym_merchant_receipt_items[] = array(
            'quantity' 	=> $v['quantity'], 
            'price' 	=> array('amount' => $item_price_after_fee),
            'text' 		=> $v['name'],
            'tax'       => 1
        );
    }

    // log_to_browser_console('total all item price sum: ' . $total_all_items_price);
    // log_to_browser_console('total sum: ' . $purchase_data['price']);

    $ym_merchant_receipt = array(
        'customerContact'   => $purchase_data['user_email'],
        'items'             => $ym_merchant_receipt_items
    );

    // Convert to JSON string
    $ym_merchant_receipt_json = json_encode($ym_merchant_receipt, JSON_UNESCAPED_UNICODE);
 
    // Setup Yandex.Kassa arguments
    $yandex_args = array(
        'ShopID' => edd_get_option( 'ya_shop_id', false ),
        'scid' => edd_get_option( 'ya_scid', false ),
        'cps_email' => $purchase_data['user_email'],
        'Sum' => $purchase_data['price'],
        'orderNumber' => $payment,
        'CustName' => $purchase_data['user_info']['first_name'],
			'paymentType' => 'AC',
			'ym_merchant_receipt' => $ym_merchant_receipt_json
		);
		
	// log_to_browser_console($yandex_args);
	$yandex_redirect .= http_build_query( $yandex_args );
    // log_to_browser_console($yandex_redirect);
    
    // finally send the request to Yandex.Kassa
    wp_redirect( $yandex_redirect );
}
