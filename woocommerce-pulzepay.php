<?php
/*
Plugin Name: WooCommerce PulzePay
Plugin URI: http://www.pulzepay.com/plugins/woocommerce/woocommerce-pulzepay-gateway.zip
Description: WooCommerce PulzePay custom plugin.
Author: Sia How / Lee Teck Khen / Reeve Perk
Author URI: http://www.pulzepay.com/
Version: 1.0.7

Copyright: © 2017-2021  (email : technical@elite.com.my)
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

/**
 * Check if WooCommerce is active
 */
 add_action('plugins_loaded', 'woocommerce_pulzepay_init', 0);

 function woocommerce_pulzepay_init(){
    if(!class_exists('WC_Payment_Gateway')) return;

    class WC_PulzePay extends WC_Payment_Gateway{
        public function __construct(){
            $this -> id = 'pulzepay';
            $this->icon = plugins_url('images/pulzepay.png', __FILE__);
            $this -> medthod_title = 'PulzePay';
            $this -> method_description = 'PulzePay is most popular payment gateway for online shopping in Malaysia';
            $this -> has_fields = false;

            $this -> init_form_fields();
            $this -> init_settings();

            $this -> title = "PulzePay";
            //$this -> settings['description']
            $this -> description = "PulzePay authorizes visa, master card and internet banking FPX through PulzePay Secure Servers." ;

            $this -> merchant_id = $this -> settings['merchant_id'];
            $this -> salt = $this -> settings['salt'];
            $this -> liveurl = 'https://api.pulzepay.com/web.php';

            $this -> msg['message'] = "";
            $this -> msg['class'] = "";

            //add_action('init', array(&$this, 'check_pulzepay_response'));
            if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
            } else {
                add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
            }
            add_action('woocommerce_receipt_pulzepay', array(&$this, 'receipt_page'));
        }


        function init_form_fields(){

           $this -> form_fields = array(
                    'enabled' => array(
                        'title' => __('Enable/Disable', 'pulzepay'),
                        'type' => 'checkbox',
                        'label' => __('Enable PulzePay Payment Module.', 'pulzepay'),
                        'default' => 'no'),
                    'title' => array(
                        'title' => __('Title:', 'pulzepay'),
                        'type'=> 'hidden',
                        'description' => __('PulzePay', 'pulzepay'),
                        'default' => __('PulzePay', 'pulzepay')),

                    'description' => array(
                        'title' => __('Description:', 'pulzepay'),
                        'type' => 'hidden',
                        'description' => __('PulzePay authorizes visa, master card and internet banking FPX through PulzePay Secure Servers.', 'pulzepay'),
                        'default' => __('PulzePay authorizes visa, master card and internet banking FPX through PulzePay Secure Servers.', 'pulzepay')),
                    'merchant_id' => array(
                        'title' => __('Service ID', 'pulzepay'),
                        'type' => 'text',
                        'description' => __('This id(Service ID) available at PulzePay Merchant Panel.')),
                    'salt' => array(
                        'title' => __('Secret key', 'pulzepay'),
                        'type' => 'text',
                        'description' =>  __('The Secret key PulzePay available at PulzePay Merchant Panel' , 'pulzepay'),
                    ),
            );
        }

        public function admin_options(){
            echo '<h3>'.__('PulzePay Payment Gateway', 'pulzepay').'</h3>';
            echo '<p>'.__('PulzePay is most popular payment gateway for online shopping in Malaysia', 'pulzepay').'</p>';
            echo '<table class="form-table">';
            // Generate the HTML For the settings form.
            $this -> generate_settings_html();
            echo '</table>';

        }


        /**
        *  There are no payment fields for PulzePay, but we want to show the description if set.
        **/
        function payment_fields(){
            if($this -> description) echo wpautop(wptexturize($this -> description));
        }

        /**
        * Receipt Page
        **/
        function receipt_page($order){
            echo '<p>'.__('Thank you for your order, please click the button below to pay with PulzePay.', 'pulzepay').'</p>';
            echo $this -> generate_pulzepay_form($order);
        }


        /**
        * Generate PulzePay button link
        **/
        public function generate_pulzepay_form($order_id){
            global $woocommerce;
            $order = new WC_Order( $order_id );
            $txnid = $order_id.'_'.date("ymds");

            $productinfo = "Order: $order_id";

            //2020-08-25
            $redirect_url = '';

            $this->currency = get_woocommerce_currency();
            $payu_args = array(
                'serv_id' => $this -> merchant_id,
                'trans_id' => $txnid,
                'amount' => $order -> get_total(),
                'productinfo' => $productinfo,
                'firstname' => $order -> get_billing_first_name(),
                'lastname' => $order -> get_billing_last_name(),
                'address1' => $order -> get_billing_address_1(),
                'address2' => $order -> get_billing_address_2(),
                'city' => $order -> get_billing_city(),
                'state' => $order -> get_billing_state(),
                'country' => $order -> get_billing_country(),
                'zipcode' => $order -> get_billing_postcode(),
                'email' => $order -> get_billing_email(),
                'phone' => $order -> get_billing_phone(),
                'surl' => $redirect_url,
                'furl' => $redirect_url,
                'curl' => $redirect_url,
                'secret_key' =>  $this -> salt,
                'currency' => $this->currency ,
                'return_url'=>$this->get_return_url( $order ),
              );

            //2020-03-17, start
            // add pulzepay param
            $price_code = ($order -> get_total()) * 100;

            $hash_data = array(
                 'serv_id'   => $this->merchant_id,
                 'trans_id'   => $txnid,
                 'price_code'  => $price_code,
                 'currency'   => $this->currency,
                 'trans_desc'  => $productinfo,
            );

            $query_string = http_build_query($hash_data, null, '&');

            if(!defined('PHP_QUERY_RFC3986')) define('PHP_QUERY_RFC3986', 2);
            $hash_code = hash_hmac('sha256', $query_string, $this->salt);


            $payu_args['m'] = 'web';
            $payu_args['command'] = 'show_main';
            $payu_args['price_code'] = $price_code;
            $payu_args['trans_desc'] = $productinfo;
            $payu_args['hash_code'] = $hash_code;
            
            # new fields
            $payu_args['buyer_name'] = $order -> get_billing_first_name() . ' ' . $order -> get_billing_last_name();
            $payu_args['buyer_email'] = $order -> get_billing_email();
            $payu_args['buyer_phone'] = $order -> get_billing_phone();            

            if (empty($payu_args['country'])) { $payu_args['country']='MY'; }
            //end

            $pulzepay_args_array = array();

            foreach($payu_args as $key => $value){
                $pulzepay_args_array[] = "<input type='text' name='$key' value='$value'/>";
            }


            return '<form action="'.$this -> liveurl.'" method="post" id="pulzepay_payment_form">
                ' . implode('', $pulzepay_args_array) . '
                        <input type="submit" class="button-alt" id="submit_pulzepay_payment_form" value="'.__('Pay via PulzePay', 'pulzepay').'" /> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'pulzepay').'</a>
                        <script type="text/javascript">
            jQuery(function(){
            jQuery("body").block(
                    {
                        message: "<img src=\"'.$woocommerce->plugin_url().'/assets/ajax-loader.gif\" alt=\"Redirecting…\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to Payment Gateway to make payment.', 'pulzepay').'",
                            overlayCSS:
                    {
                        background: "#fff",
                            opacity: 0.6
                },
                css: {
                    padding:        20,
                        textAlign:      "center",
                        color:          "#555",
                        border:         "3px solid #aaa",
                        backgroundColor:"#fff",
                        cursor:         "wait",
                        lineHeight:"32px"
                }
                });
                jQuery("#submit_pulzepay_payment_form").click();});</script>
                        </form>';


        }

        /**
        * Process the payment and return the result
        **/
        function process_payment($order_id){
            global $woocommerce;
            $order = new WC_Order( $order_id );

            return array(
                'result' => 'success',
                'redirect' => add_query_arg( array('order'=>$order_id, 'key'=>$order->order_key),
                                $order->get_checkout_payment_url( true ) ));
        }

        function showMessage($content){
            return '<div class="box '.$this -> msg['class'].'-box">'.$this -> msg['message'].'</div>'.$content;
        }

    }  //end class


    /**
    * Add the Gateway to WooCommerce
    **/
    function woocommerce_add_pulzepay_gateway($methods) {
        $methods[] = 'WC_PulzePay';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_pulzepay_gateway' );
}
