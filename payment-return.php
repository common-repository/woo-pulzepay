<?php
/*
Module:: WooCommerce PulzePay

return url : http://wordpress-path/wp-content/plugins/woo-pulzepay/payment-return.php
*/

include_once('../../../wp-load.php');

if (!isset($_POST['status']) || empty($_POST['status'])) {
    echo 'Error: Invalid parameters <br><br><strong>'. get_bloginfo('name') .'</strong>';
    exit;
}

$the_status = $_POST['status'];

//check for valid hashcode
$myopt = get_option('woocommerce_pulzepay_settings');

$params = array(
    'trans_id' 			=> $_POST['trans_id'],
    'ref_code'  		=> $_POST['ref_code'],
    'status'    		=> $the_status,
    'msisdn'			=> "",
    'payment_code'		=> $_POST['payment_code'],
);

$hash_code = generate_hashcode($params, $myopt['salt']);

if ($hash_code != $_POST['hash_code']) {
    echo 'Error: Invalid hash code <br><br><strong>'. get_bloginfo('name') .'</strong>';
    exit;
}



if ($the_status == 200) {
    $arrTmp = explode('_', $_POST['trans_id']);

    $order_id = $arrTmp[0];
    $order = new WC_Order( $order_id );

    //store ref_code
    //this is return, no need update DB
    update_post_meta($order_id,'ref_code', $_POST['ref_code']);
    $order->payment_complete();

    //header("Location: ". $order->get_checkout_order_received_url() );
    wp_redirect($order->get_checkout_order_received_url());
    exit;
} else {
    $arrTmp = explode('_', $_POST['trans_id']);

    $order_id = $arrTmp[0];
    $order = new WC_Order( $order_id );

    //header("Location: ". $order->get_checkout_payment_url(false) );
    wp_redirect($order->get_checkout_payment_url(false));
    exit;
}




function generate_hashcode($params,$secret_key) {
    $query_string = http_build_query($params, null, '&');
    if(!defined('PHP_QUERY_RFC3986')) define('PHP_QUERY_RFC3986', 2);

    $hash_code = hash_hmac('sha256', $query_string, $secret_key);

    return $hash_code;
}

?>