<?php
if (!defined('ABSPATH'))
    exit;

if (!class_exists('UtilityPaydunkPayment')) {

    class UtilityPaydunkPayment
    {
	
		public static function getOrderIdFromUrl() {
			
			if(isset($_GET['order-received'])) {
				$order_id = $_GET['order-received'];
			}
			//no more get parameters in the url
			else {
				$url = $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
				$template_name = strpos($url,'/order-received/') === false ? '/view-order/' : '/order-received/';
				if (strpos($url,$template_name) !== false) {
					$start = strpos($url,$template_name);
					$first_part = substr($url, $start+strlen($template_name));
					$order_id = substr($first_part, 0, strpos($first_part, '/'));
				}
			}
			
			//yes, I can retrieve the order via the order id
			return $order_id;			
		}
		
        /**
         * @return PaydunkPaymentGateway
         */
        public static function getPaydunkPaymentGateway()
        {
            global $woocommerce;
            $gateways = $woocommerce->payment_gateways;

            foreach($gateways->payment_gateways as $item) {
                if ($item->id == PAYDUNK_PAYMENT_ID) {
                    return $item;
                }
            }

            return false;
        }

        public static function add_paydunk_required_form()
        {

            $paydunk = UtilityPaydunkPayment::getPaydunkPaymentGateway();
            if (!$paydunk || !$paydunk->is_available())
                return;

            if ((is_cart() && $paydunk->get_option('show_on_cart') == 'yes') || is_checkout()) {

                require_once PAYDUNK_PLUGIN_DIRNAME . "/html_templates/paydunk-payment-form.php";
				
				
            }
        }

        public static function generate_paydunk_script()
        {
            $paydunk = UtilityPaydunkPayment::getPaydunkPaymentGateway();
            if (!$paydunk || !$paydunk->is_available())
                return;

            if ((is_cart() && $paydunk->get_option('show_on_cart') == 'yes') || is_checkout()) {

                require_once PAYDUNK_PLUGIN_DIRNAME . "/html_templates/paydunk-client-script.php";
            }				
        }

        public static function show_paydunk_button_on_cart()
        {
            if (!is_cart()) return;
		
			$paydunk = UtilityPaydunkPayment::getPaydunkPaymentGateway();		
			$add_free_shipping_msg = "<p></p>";			
			if ($paydunk->get_option('force_free_shipping') == 'yes') {
				$add_free_shipping_msg = wpautop(__('Free shipping for all order paid using Paydunk.', 'paydunk'));   
            }
		            
            if ($paydunk && $paydunk->is_available() && $paydunk->get_option('show_on_cart') == 'yes') {

                require_once PAYDUNK_PLUGIN_DIRNAME . "/html_templates/paydunk-pay-button-oncart.php";
            }
				
        }

        public static function call_paydunk_put_api($transaction_uuid, $data = false)
        {
            try {
                $data_to_paydunk = http_build_query($data);
                $endpoint = PAYDUNK_API_URL_END_POINT . $transaction_uuid;
                $header = array(
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json',
                );

                $curl = curl_init($endpoint);
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data_to_paydunk);
                curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1); // should be 1, verify certificate
                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2); // should be 2, check existence of CN and verify that it matches hostname
                curl_setopt($curl, CURLOPT_FORBID_REUSE, 1);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($curl, CURLOPT_FRESH_CONNECT, 1);

                $response = curl_exec($curl);

                $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                $error = (curl_errno($curl) > 0)? curl_error($curl) : false;

                curl_close($curl);

                $errorMessage = array();
                if ($error !== false || ($httpCode != 200 && $httpCode != 204)) {
                    $errorMessage = array(
                        'http_code' => $httpCode,
                        'http_content' => $response,
                        'http_error' => $error,
                        'end_point' => $endpoint,
                        'data' => $data_to_paydunk
                    );

                    PaydunkPaymentGateway::log(json_encode($errorMessage));
                }

                return !empty($errorMessage)? json_encode($errorMessage) : false;

            } catch(Exception $ex) {
                PaydunkPaymentGateway::log($ex->getMessage());
                return $ex->getMessage();
            }
        }

        /**
         * @param WC_Order $order
         * @return WC_Order
         */
        public static function force_free_shipping($order)
        {
            $order->remove_order_items('shipping');
            $free_shipping = new WC_Shipping_Free_Shipping();
            $shipping_rate = new WC_Shipping_Rate(
                $free_shipping->id,
                $free_shipping->method_title,
                0,
                array(),
                $free_shipping->id
            );
            $order->add_shipping($shipping_rate);

            $order->calculate_shipping();
            $order->calculate_totals();

            return $order;
        }
		
		/**
         * @param serialize
         */
		public static function store_cart() {
					
			$cart = serialize(WC()->cart);
			WC()->session->set(PAYDUNK_PAYMENT_ON_CART_SESSION_KEY + '_Cart', $cart);
			error_log('store_cart');	
			error_log($cart);							
		}
		
		/**
         * @param serialize
         */
		public static function store_cart_to_order( $order_id ) {
					
			$cart = WC()->session->get(PAYDUNK_PAYMENT_ON_CART_SESSION_KEY + '_Cart');
			update_post_meta($order_id, PAYDUNK_PAYMENT_ON_CART_SESSION_KEY + '_Cart', $cart);
			error_log('store_cart_to_order');	
			error_log($cart);							
		}
		
		/**
         * @param unserialize
         */
		public static function get_stored_cart( $order_id ) {				
			error_log('get_stored_cart');
			$cart = get_post_meta( $order_id, 'cart_details', true );		
			error_log(print_r($cart, true));	
			return unserialize($cart);
		}
		
        /**
         * @param $is_from_cart true to set or false to uset the session
         */
        public static function set_payment_from_cart($is_from_cart)
        {
            if ($is_from_cart) {
                WC()->session->set(PAYDUNK_PAYMENT_ON_CART_SESSION_KEY, 'true');
            } else {
                WC()->session->__unset(PAYDUNK_PAYMENT_ON_CART_SESSION_KEY);
            }
        }

        public static function is_payment_from_cart()
        {
            $is_payment_from_cart = WC()->session->get(PAYDUNK_PAYMENT_ON_CART_SESSION_KEY);
            return !empty($is_payment_from_cart);
        }

        public static function need_confirmation_page()
        {
            $paydunk = UtilityPaydunkPayment::getPaydunkPaymentGateway();
            if ($paydunk && $paydunk->is_available()
                && $paydunk->get_option('show_on_cart') == 'yes'                
                && UtilityPaydunkPayment::is_payment_from_cart()) {

                return true;
            } else {
                return false;
            }
        }
    }
}
