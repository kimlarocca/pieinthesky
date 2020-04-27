<?php
use PayPal\Api\Address;
use PayPal\Api\Amount;
use PayPal\Api\CreditCard;
use PayPal\Api\Details;
use PayPal\Api\FundingInstrument;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\Transaction;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Rest\ApiContext;

if (!defined('ABSPATH'))
    exit;

if (!class_exists('VisitorPaydunkPayment')) {
    class VisitorPaydunkPayment
    {
        public function __construct()
        {
            add_filter('woocommerce_order_button_html', array($this, 'set_paydunk_button'));

            add_action('wp_loaded', array($this, 'accept_payment_from_paydunk'));
            add_action('wp_head', array("UtilityPaydunkPayment", 'generate_paydunk_script'));
            add_action('woocommerce_after_checkout_form', array("UtilityPaydunkPayment", 'add_paydunk_required_form'));
            add_action('woocommerce_proceed_to_checkout', array("UtilityPaydunkPayment", 'add_paydunk_required_form'));
            add_action('woocommerce_proceed_to_checkout', array("UtilityPaydunkPayment", 'show_paydunk_button_on_cart'));

            add_filter('woocommerce_after_checkout_validation', array($this, 'revalidate_billing_and_shipping'));

            add_action('wp_ajax_nopriv_paydunk_create_order_from_cart', array($this, 'create_order_from_cart'));
            add_action('wp_ajax_paydunk_create_order_from_cart', array($this, 'create_order_from_cart'));
            add_filter('woocommerce_available_payment_gateways', array($this, 'set_paydunk_as_default_payment_gateway'));
            add_filter('woocommerce_thankyou_order_received_text', array($this, 'set_thank_you_page'), 10, 2);
            add_action('woocommerce_thankyou_' . PAYDUNK_PAYMENT_ID, array($this, 'thank_you_page_action'));
			
			add_filter( 'the_title', array($this, 'paydunk_title_order_confirmation'), 10, 2 );
			
			remove_action( 'woocommerce_thankyou', array( WC()->session, 'destroy_session' ) );
        }
		
		function paydunk_title_order_confirmation( $title, $id ) {
			global $post;			
			if ( is_order_received_page() && get_the_ID() === $id ) {
				$order_id = UtilityPaydunkPayment::getOrderIdFromUrl();				
				if (empty($_GET['paydunk_confirmed']) && UtilityPaydunkPayment::need_confirmation_page() &&
						get_post_meta( $order_id, 'confimation_need', true ) === 'yes' ) {
					$title = "Confirm Order";
				}
			}			
			return $title;
		}

        function set_paydunk_button($button)
        {
            global $woocommerce;
            if ($woocommerce->session->chosen_payment_method == PAYDUNK_PAYMENT_ID) {
                $html = new DOMDocument();
                $html->loadHTML($button);
                $element = $html->getElementById('place_order');
                $class = $element->getAttribute('class');
                $element->setAttribute('class', $class . ' paydunk-order-button');
                $button = $html->saveHTML($element);
            }

            return $button;
        }

        function revalidate_billing_and_shipping($fields)
        {
            if (!empty($fields['payment_method']) && $fields['payment_method'] == PAYDUNK_PAYMENT_ID) {
                wc_clear_notices();
            }

            return $fields;
        }

        function create_order_from_cart()
        {
            $return_value = array('result' => 'error', 'process_by' => '');

            $paydunk = UtilityPaydunkPayment::getPaydunkPaymentGateway();
            if ($paydunk && $paydunk->is_available() && $paydunk->get_option('show_on_cart') == 'yes') {

                try {
                    $total_item = WC()->cart->get_cart_item_quantities();
                    if (WC()->cart->check_cart_items() == false || empty($total_item)) {

                        WC()->cart->empty_cart(true);
                        throw new Exception(__("Cart item not available or cart session is experied.", "paydunk"));
                    }
					
					UtilityPaydunkPayment::store_cart();
					
                    $checkout = WC_Checkout::instance();

                    $_POST['_wpnonce'] = wp_create_nonce('woocommerce-process_checkout');
                    $_POST['payment_method'] = PAYDUNK_PAYMENT_ID;
                    $_POST['billing_country'] = PAYDUNK_COUNTRY;
					$_POST['order_status'] = 'pending';
									
                    UtilityPaydunkPayment::set_payment_from_cart(true);
                    $checkout->process_checkout();

                } catch (Exception $ex) {
                    UtilityPaydunkPayment::set_payment_from_cart(false);
                    PaydunkPaymentGateway::log($ex->getMessage());
                    wc_add_notice($ex->getMessage(), "error");
                    $return_value = array(
                        'result' => 'error',
                        'process_by' => $ex->getMessage()
                    );
                }
            }

            echo json_encode($return_value);
            //wp_die();
        }

        function accept_payment_from_paydunk()
        {
            if (empty($_GET["method"]) && (empty($_GET["accept"]) || $_GET["accept"] != PAYDUNK_PAYMENT_ID))
                return;

            $method = $_GET["method"];
            switch ($method) {
                case "process":
                    $this->process_paydunk_payment();
                    break;
                case "redirect":
                    $this->redirect_paydunk_payment();
                    break;
            }
        }

        private function process_paydunk_payment()
        {
            if (!empty($_POST["transaction_uuid"]) && !empty($_POST["order_number"])) {
                $expiration_date = $_POST["expiration_date"]; // string - expiration date on card. Format: MM/YY
                $card_number = $_POST["card_number"]; //- string - credit card number
                $cvv = $_POST["cvv"]; // string - 3-4 digit code found on back of card

                $shipping_email = $_POST["shipping_email"]; // string - email
                $shipping_address_1 = $_POST["shipping_address_1"]; // string - address line one
                $shipping_address_2 = $_POST["shipping_address_2"]; // string (optional) - address line two
                $shipping_city = $_POST["shipping_city"]; // string - city name
                $shipping_state = $_POST["shipping_state"]; // string - state abbreviation
                $shipping_zip = $_POST["shipping_zip"]; // string - 5 digit postal code
                $shipping_name = @$_POST["shipping_name"]; // string - full name
                $shipping_name = $this->construct_to_first_lastname($shipping_name);
                $shipping_first_name = @$_POST["shipping_first_name"];
                $shipping_last_name = @$_POST["shipping_last_name"];
                $shipping_address = array(
                    'first_name' => $shipping_first_name ? $shipping_first_name : $shipping_name[0],
                    'last_name' => $shipping_last_name ? $shipping_last_name : $shipping_name[1],
                    'address_1' => $shipping_address_1,
                    'address_2' => $shipping_address_2,
                    'city' => $shipping_city,
                    'state' => $shipping_state,
                    'country' => PAYDUNK_COUNTRY,
                    'postcode' => $shipping_zip,
                    'email' => $shipping_email
                );

                $email = $_POST["email"]; // string - email address of paydunk user associated with transaction
                $billing_email = $_POST["billing_email"]; // string - email
                $billing_phone = $_POST["billing_phone"]; // string - phone
                $billing_address_1 = $_POST["billing_address_1"]; // string - address line one
                $billing_address_2 = $_POST["billing_address_2"]; // string (optional) - address line two
                $billing_city = $_POST["billing_city"]; // string - city name
                $billing_state = $_POST["billing_state"]; // string - state abbreviation
                $billing_zip = $_POST["billing_zip"]; // string - 5 digit postal code
                $billing_name = @$_POST["billing_name"]; // string - full name
                $billing_name = $this->construct_to_first_lastname($billing_name);
                $billing_first_name = @$_POST["billing_first_name"];
                $billing_last_name = @$_POST["billing_last_name"];
                $billing_address = array(
                    'first_name' => $billing_first_name ? $billing_first_name : $billing_name[0],
                    'last_name' => $billing_last_name ? $billing_last_name : $billing_name[1],
                    'address_1' => $billing_address_1,
                    'address_2' => $billing_address_2,
                    'city' => $billing_city,
                    'state' => $billing_state,
                    'country' => PAYDUNK_COUNTRY,
                    'postcode' => $billing_zip,
                    'phone' => $billing_phone,
                    'email' => !empty($email) ? $email : $billing_email
                );

                $transaction_uuid = $_POST["transaction_uuid"]; // string - 36 digit uuid number of the transaction
                $order_number = $_POST["order_number"]; // string - order number created by merchant site in order to perform transaction

                $order = new WC_Order($order_number);
                if (!$order)
                    wp_die();

                update_post_meta($order_number, 'paydunk_transaction_uuid', $transaction_uuid);
                $order->billing_first_name = $billing_first_name;
                $order->billing_last_name = $billing_last_name;
                $order->shipping_first_name = $shipping_first_name;
                $order->shipping_last_name = $shipping_last_name;
                $order->billing_email = !empty($email) ? $email : $billing_email;
                $order->set_address($billing_address, 'billing');
                $order->set_address($shipping_address, 'shipping');

                $paydunk = UtilityPaydunkPayment::getPaydunkPaymentGateway();
                if (!$paydunk) {
                    $order->update_status('failed');
                    PaydunkPaymentGateway::log("Paydunk payment method not found.");
                    wp_die();
                }

                if ($paydunk->get_option('force_free_shipping') == 'yes') {
                    $order = UtilityPaydunkPayment::force_free_shipping($order);
                }
				else {
					try {
						// restore cart session.
						$cart = UtilityPaydunkPayment::get_stored_cart($order_number);
						
						if (isset($cart)) {		
						
							// Update customer location to posted location so we can correctly check available shipping methods
							WC()->customer->set_country( PAYDUNK_COUNTRY );
							WC()->customer->set_state( $shipping_state );
							WC()->customer->set_postcode( $shipping_zip );
				
							WC()->customer->set_shipping_country( PAYDUNK_COUNTRY );
							WC()->customer->set_shipping_state( $shipping_state );
							WC()->customer->set_shipping_postcode( $shipping_zip );	
							
							// restore shipping session
							$shipping_methods = get_post_meta( $order_number, 'cart_details_shipping_methods');	
							do_action('woocommerce_shipping_init');
							WC()->shipping  = unserialize(get_post_meta( $order_number, 'cart_shipping', true )); 
							$cart->calculate_shipping();
							
							// re-calculate the shipping total
							$order->remove_order_items('shipping');
							$shipping_added = false;
							$ship_packages = WC()->shipping->get_packages();
							foreach ( $ship_packages as $package_key => $package ) {					
								if (isset($shipping_methods) && isset($shipping_methods[ $package_key ]) && isset($package['rates']) &&
									isset( $package['rates'][ $shipping_methods[ $package_key ] ] ) ) {
									$item_id = $order->add_shipping( $package['rates'][ $shipping_methods[ $package_key ] ] );
									$shipping_added = true;
								}
							}
							
							if ($shipping_added == false) {
								foreach ( $ship_packages as $package_key => $package ) {
									$item_id = $order->add_shipping( $package['rates'][ key($package['rates']) ] );
									break;
								}
							}							
						}
					} catch (Exception $e) {
						error_log('Caught exception: ',  $e->getMessage());
					}
				}
								
                $order->calculate_shipping();
                $order->calculate_totals();

                $process_payment_by = $paydunk->get_option('process_payment_by');
				 
                if ( get_post_meta( $order_number, 'confimation_need', true ) === 'yes' ) {

                    $order->add_order_note(__('Waiting for user confirmation action.', 'paydunk'));
					 
                    $status = 'success';

                    $all_payment_info = compact('card_number',
                        'expiration_date', 'cvv', 'order_number', 'billing_first_name', 'billing_name',
                        'billing_last_name', 'billing_address_1', 'billing_address_2', 'billing_city',
                        'billing_state', 'billing_zip', 'billing_phone', 'email', 'billing_email',
                        'shipping_first_name', 'shipping_name', 'shipping_last_name', 'shipping_address_1',
                        'shipping_address_2', 'shipping_city', 'shipping_state', 'shipping_zip', 'transaction_uuid');

                    update_post_meta($order->id, PAYDUNK_PAYMENT_INFO, $all_payment_info);

                } else {

                    $status = $this->finalize_payment_processing(
                        $process_payment_by, $paydunk, $order, $card_number, $expiration_date, $cvv, $order_number,
                        $billing_first_name, $billing_name, $billing_last_name, $billing_address_1, $billing_address_2,
                        $billing_city, $billing_state, $billing_zip, $billing_phone, $email, $billing_email,
                        $shipping_first_name, $shipping_name, $shipping_last_name, $shipping_address_1,
                        $shipping_address_2, $shipping_city, $shipping_state, $shipping_zip, $transaction_uuid
                    );
                }

                $paydunk_client_id = $paydunk->get_option('app_id');
                $paydunk_client_secret = $paydunk->get_option('app_secret');
                $params = array(
                    "client_id" => $paydunk_client_id,
                    "client_secret" => $paydunk_client_secret,
                    "status" => $status
                );

                $put_to_paydunk_error = UtilityPaydunkPayment::call_paydunk_put_api($transaction_uuid, $params);
                if (!empty($put_to_paydunk_error)) {
                    $order->add_order_note(__('Can\'t send confirmation message to paydunk for payment process status: ')
                        . $status . ". Additional message: " . $put_to_paydunk_error);
                };

                wp_die();
            }
        }

        private function redirect_paydunk_payment()
        {
            $returl_url = WC()->session->get(PAYDUNK_RETURN_URL_SESSION_KEY);
            if (!empty($returl_url)) {
                wp_redirect($returl_url);
                exit;
            }
        }

        function set_paydunk_as_default_payment_gateway($gateways)
        {
            if (!empty($_GET['process_by']) && $_GET['process_by'] == PAYDUNK_PAYMENT_ID) {

                foreach ($gateways as $key => $value) {
                    if ($key == PAYDUNK_PAYMENT_ID)
                        $value->chosen = true;
                    else
                        $value->chosen = false;
                }
            }

            return $gateways;
        }

        function thank_you_page_action($order_id)
        {
            if (!empty($_GET['paydunk_confirmed']) && UtilityPaydunkPayment::need_confirmation_page()) {

                $card_number = '';
                $expiration_date = '';
                $cvv = '';
                $order_number = '';
                $billing_first_name = '';
                $billing_name = '';
                $billing_last_name = '';
                $billing_address_1 = '';
                $billing_address_2 = '';
                $billing_city = '';
                $billing_state = '';
                $billing_zip = '';
                $billing_phone = '';
                $email = '';
                $billing_email = '';
                $shipping_first_name = '';
                $shipping_name = '';
                $shipping_last_name = '';
                $shipping_address_1 = '';
                $shipping_address_2 = '';
                $shipping_city = '';
                $shipping_state = '';
                $shipping_zip = '';
                $transaction_uuid = '';

                $all_payment_info = get_post_meta($order_id, PAYDUNK_PAYMENT_INFO, true);
                if (is_array($all_payment_info) && extract($all_payment_info) > 0) {

                    $order = new WC_Order($order_id);

                    $paydunk = UtilityPaydunkPayment::getPaydunkPaymentGateway();
                    if (!$paydunk) {
                        $order->update_status('failed');
                        PaydunkPaymentGateway::log("Paydunk payment method not found.");
                        return;
                    }

                    $process_payment_by = $paydunk->get_option('process_payment_by');

                    if ($order->get_status() == 'pending') {
                        $this->finalize_payment_processing(
                            $process_payment_by, $paydunk, $order, $card_number, $expiration_date, $cvv, $order_number,
                            $billing_first_name, $billing_name, $billing_last_name, $billing_address_1, $billing_address_2,
                            $billing_city, $billing_state, $billing_zip, $billing_phone, $email, $billing_email,
                            $shipping_first_name, $shipping_name, $shipping_last_name, $shipping_address_1,
                            $shipping_address_2, $shipping_city, $shipping_state, $shipping_zip, $transaction_uuid
                        );

                        delete_post_meta($order_id, PAYDUNK_PAYMENT_INFO);						
                    }										
                }
            }
        }

        /**
         * @param $text
         * @param WC_Order $order
         * @return mixed
         */
        function set_thank_you_page($text, $order)
        {
            $paydunk = UtilityPaydunkPayment::getPaydunkPaymentGateway();
            if ($paydunk && $paydunk->is_available() && $order->payment_method == PAYDUNK_PAYMENT_ID) {
                $text = $paydunk->get_option('instructions');
            }

            if (UtilityPaydunkPayment::need_confirmation_page() && empty($_GET['paydunk_confirmed']) 
						&& get_post_meta( $order->id, 'confimation_need', true ) === 'yes' ) {
                $text = $paydunk->get_option('instructions');
                $text = sprintf(
                    '<a href="%s" class="button alt paydunk-payment-confirmation-button">%s</a>',
                    WC()->session->get(PAYDUNK_RETURN_URL_SESSION_KEY) . '&paydunk_confirmed=true',
                    __('Confirm This Payment', 'paydunk')
                );
				
				WC()->customer->set_shipping_location($order->shipping_country, 
													  $order->shipping_state,
													  $order->shipping_postcode);							
            }						

            return $text;
        }

        private function construct_to_first_lastname($fullname)
        {
            if (empty($fullname))
                return array('', '');

            $parts = explode(" ", $fullname);
            if (count($parts) >= 2) {
                $lastname = array_pop($parts);
                $firstname = implode(" ", $parts);
                return array($firstname, $lastname);
            } else {
                return array($fullname, ".");
            }
        }

        /**
         * @param $text
         * @param $max_length
         * @return string
         */
        function truncate_text($text, $max_length)
        {
            $text = htmlspecialchars($text);
            $text = (strlen($text) > $max_length) ? (substr($text, 0, $max_length - 3) . '...') : $text;
            return $text;
        }

        /**
         * @param PaydunkPaymentGateway $paydunk
         * @param WC_Order $order
         * @param $card_number
         * @param $expiration_date
         * @param $cvv
         * @param $order_number
         * @param $billing_first_name
         * @param $billing_name
         * @param $billing_last_name
         * @param $billing_address_1
         * @param $billing_address_2
         * @param $billing_city
         * @param $billing_state
         * @param $billing_zip
         * @param $billing_phone
         * @param $email
         * @param $billing_email
         * @param $shipping_first_name
         * @param $shipping_name
         * @param $shipping_last_name
         * @param $shipping_address_1
         * @param $shipping_address_2
         * @param $shipping_city
         * @param $shipping_state
         * @param $shipping_zip
         * @param $transaction_uuid
         * @return string
         */
        private function process_using_authorize_net($paydunk, $order, $card_number, $expiration_date, $cvv, $order_number,
                                                     $billing_first_name, $billing_name, $billing_last_name, $billing_address_1,
                                                     $billing_address_2, $billing_city, $billing_state, $billing_zip,
                                                     $billing_phone, $email, $billing_email, $shipping_first_name,
                                                     $shipping_name, $shipping_last_name, $shipping_address_1,
                                                     $shipping_address_2, $shipping_city, $shipping_state, $shipping_zip,
                                                     $transaction_uuid)
        {
            $authorize_login_id = $paydunk->get_option('authorize_login_id');
            $authorize_transaction_key = $paydunk->get_option('authorize_transaction_key');
            $test_mode = ($paydunk->get_option('testmode') == 'yes');

            $sale = new AuthorizeNetAIM($authorize_login_id, $authorize_transaction_key);
            $sale->setSandbox($test_mode);
            $sale->setCustomField('x_amount', $order->get_total());
            $sale->setCustomField('x_card_num', $card_number);
            $sale->setCustomField('x_exp_date', $expiration_date);
            $sale->setCustomField('x_card_code', $cvv);
            $sale->setCustomField('x_currency_code', $order->get_order_currency());
            $sale->setCustomField('x_invoice_num', $paydunk->get_option('authorize_invoice_number_prefix') . $order_number);
            $sale->setCustomField('x_description', get_bloginfo() . " - " . __("This invoice paid using Paydunk."));

            $sale->setCustomField('x_first_name', $this->truncate_text($billing_first_name ? $billing_first_name : $billing_name[0], 50));
            $sale->setCustomField('x_last_name', $this->truncate_text($billing_last_name ? $billing_last_name : $billing_name[1], 50));
            $sale->setCustomField('x_address', $this->truncate_text($billing_address_1 . ' ' . $billing_address_2, 60));
            $sale->setCustomField('x_city', $this->truncate_text($billing_city, 40));
            $sale->setCustomField('x_state', $this->truncate_text($billing_state, 40));
            $sale->setCustomField('x_zip', $billing_zip);
            $sale->setCustomField('x_country', PAYDUNK_COUNTRY);
            $sale->setCustomField('x_phone', $billing_phone);
            $sale->setCustomField('x_email', !empty($email) ? $email : $billing_email);

            $sale->setCustomField('x_ship_to_first_name', $this->truncate_text($shipping_first_name ? $shipping_first_name : $shipping_name[0], 50));
            $sale->setCustomField('x_ship_to_last_name', $this->truncate_text($shipping_last_name ? $shipping_last_name : $shipping_name[1], 50));
            $sale->setCustomField('x_ship_to_address', $this->truncate_text($shipping_address_1 . ' ' . $shipping_address_2, 60));
            $sale->setCustomField('x_ship_to_city', $this->truncate_text($shipping_city, 40));
            $sale->setCustomField('x_ship_to_state', $this->truncate_text($shipping_state, 40));
            $sale->setCustomField('x_ship_to_zip', $shipping_zip);
            $sale->setCustomField('x_ship_to_country', PAYDUNK_COUNTRY);

            $order_items = $order->get_items();
            foreach ($order_items as $key => $item) {
                $sale->addLineItem($item['product_id'], $this->truncate_text($item['name'], 30), '', $item['qty'],
                    ($item['line_subtotal'] / $item['qty']), $item['line_subtotal_tax']);
            }
            $response = $sale->authorizeAndCapture();
            if ($response->approved) {
                $status = "success";
                if (!empty($response->transaction_id)) {
                    update_post_meta($order_number, 'authorize_transaction_id', $response->transaction_id);
                    $order->payment_complete($response->transaction_id);
                } else {
                    $order->payment_complete($transaction_uuid);
                }
                global $woocommerce;
                $woocommerce->cart->empty_cart();

                return $status;
            } else {
                $status = "error";
                $order->update_status('failed', $response->error_message);
                PaydunkPaymentGateway::log($response->error_message);
                PaydunkPaymentGateway::log(json_encode($order_items));
                return $status;
            }
        }


        /**
         * @param PaydunkPaymentGateway $paydunk
         * @param WC_Order $order
         * @param $card_number
         * @param $expiration_date
         * @param $cvv
         * @param $order_number
         * @param $billing_first_name
         * @param $billing_name
         * @param $billing_last_name
         * @param $billing_address_1
         * @param $billing_address_2
         * @param $billing_city
         * @param $billing_state
         * @param $billing_zip
         * @param $billing_phone
         * @param $email
         * @param $billing_email
         * @param $shipping_first_name
         * @param $shipping_name
         * @param $shipping_last_name
         * @param $shipping_address_1
         * @param $shipping_address_2
         * @param $shipping_city
         * @param $shipping_state
         * @param $shipping_zip
         * @param $transaction_uuid
         * @return string
         */
        private function process_using_paypal_direct_cc($paydunk, $order, $card_number, $expiration_date, $cvv, $order_number,
                                                                 $billing_first_name, $billing_name, $billing_last_name, $billing_address_1,
                                                                 $billing_address_2, $billing_city, $billing_state, $billing_zip,
                                                                 $billing_phone, $email, $billing_email, $shipping_first_name,
                                                                 $shipping_name, $shipping_last_name, $shipping_address_1,
                                                                 $shipping_address_2, $shipping_city, $shipping_state, $shipping_zip,
                                                                 $transaction_uuid)
        {

            $card_validator = new \Kalicode\CreditCardValidator();
            switch($card_validator->getCardType($card_number)) {
                case \Kalicode\CreditCardValidator::AMERICAN_EXPRESS:
                    $card_type = 'amex';
                    break;
                case \Kalicode\CreditCardValidator::VISA:
                    $card_type = 'visa';
                    break;
                case \Kalicode\CreditCardValidator::DISCOVER:
                    $card_type = 'discover';
                    break;
                case \Kalicode\CreditCardValidator::MASTERCARD:
                    $card_type = 'mastercard';
                    break;
                default:
                    $card_type = '';
                    break;
            }

            if (empty($card_type)) {
                $order->add_order_note("Paypal Can't accept $card_validator card.");
                return 'error';
            }

            $billing_address = new Address();
            $billing_address->setCity($billing_city)
                ->setCountryCode(PAYDUNK_COUNTRY)
                ->setLine1($billing_address_1)
                ->setLine2($billing_address_2)
                ->setPhone($billing_phone)
                ->setPostalCode($billing_zip)
                ->setState($billing_state);

            $card = new CreditCard();
            $card->setType($card_type)
                ->setNumber($card_number)
                ->setExpireMonth(substr($expiration_date, 0, 2))
                ->setExpireYear(20 . substr($expiration_date, 3, 2))
                ->setCvv2($cvv)
                ->setFirstName($this->truncate_text($billing_first_name ? $billing_first_name : $billing_name[0], 50))
                ->setLastName($this->truncate_text($billing_last_name ? $billing_last_name : $billing_name[1], 50))
                ->setBillingAddress($billing_address);

            $fi = new FundingInstrument();
            $fi->setCreditCard($card);

            $payer = new Payer();
            $payer->setPaymentMethod("credit_card")
                ->setFundingInstruments(array($fi));

            $items = array();
            $order_items = $order->get_items();
            foreach ($order_items as $key => $item) {
                $lineItem = new Item();
                $lineItem->setSku(!empty($item['sku'])? $item['sku'] : $item['product_id'])
                    ->setName($this->truncate_text($item['name'], 30))
                    ->setCurrency(get_woocommerce_currency())
                    ->setQuantity($item['qty'])
                    ->setTax($item['line_subtotal_tax'])
                    ->setPrice($item['line_subtotal']);
            }

            $shipping = new PayPal\Api\ShippingAddress();
            $shipping->setState($shipping_state)
                ->setPostalCode($shipping_zip)
                ->setCountryCode(PAYDUNK_COUNTRY)
                ->setCity($shipping_city)
                ->setLine1($shipping_address_1)
                ->setLine2($shipping_address_2)
                ->setRecipientName(sprintf(
                    "%s %s",
                    $this->truncate_text($shipping_first_name ? $shipping_first_name : $shipping_name[0], 50),
                    $this->truncate_text($shipping_last_name ? $shipping_last_name : $shipping_name[1], 50)
                ));

            $itemList = new ItemList();
            $itemList->setItems($items);
            $itemList->setShippingAddress($shipping);

            $details = new Details();
            $details->setShipping($order->get_total_shipping())
                ->setTax($order->get_shipping_tax() + $order->get_total_tax())
                ->setSubtotal($order->get_subtotal());

            $amount = new Amount();
            $amount->setCurrency(get_woocommerce_currency())
                ->setTotal($order->get_total())
                ->setDetails($details);

            $transaction = new Transaction();
            $transaction->setAmount($amount)
                ->setItemList($itemList)
                ->setDescription("Payment for order number #$order_number")
                ->setInvoiceNumber($paydunk->get_option('paypal_invoice_number_prefix') . $order_number);

            $payment = new Payment();
            $payment->setIntent("sale")->setPayer($payer)->setTransactions(array($transaction));

            try {
                $authToken = new OAuthTokenCredential(
                    $paydunk->get_option('paypal_client_id'),
                    $paydunk->get_option('paypal_client_secret')
                );

                $apiContext = new ApiContext($authToken, 'Request' . time());
		$apiContext->setConfig(
			array(
				'mode' => 'live',
				'log.LogEnabled' => true,
				'log.FileName' => '../PayPal.log',
				'log.LogLevel' => 'DEBUG', // PLEASE USE `FINE` LEVEL FOR LOGGING IN LIVE ENVIRONMENTS
				'cache.enabled' => true,
				// 'http.CURLOPT_CONNECTTIMEOUT' => 30
				// 'http.headers.PayPal-Partner-Attribution-Id' => '123123123'
			)
		);

                $payment->create($apiContext);

                $status = "success";
                $paypal_transaction_id = $payment->getId();
                if (!empty($paypal_transaction_id)) {
                    update_post_meta($order_number, 'paypal_transaction_id', $paypal_transaction_id);
                    $order->payment_complete($paypal_transaction_id);
                } else {
                    $order->payment_complete($transaction_uuid);
                }

                global $woocommerce;
                $woocommerce->cart->empty_cart();

            } catch (Exception $ex) {
                $status = "error";
                $order->update_status('failed', $ex->getMessage());
                PaydunkPaymentGateway::log($ex->getMessage());
                PaydunkPaymentGateway::log(json_encode($ex->getData()));
                PaydunkPaymentGateway::log(json_encode($order_items));
            }

            return $status;
        }

        /**
         * @param $process_payment_by
         * @param $paydunk
         * @param $order
         * @param $card_number
         * @param $expiration_date
         * @param $cvv
         * @param $order_number
         * @param $billing_first_name
         * @param $billing_name
         * @param $billing_last_name
         * @param $billing_address_1
         * @param $billing_address_2
         * @param $billing_city
         * @param $billing_state
         * @param $billing_zip
         * @param $billing_phone
         * @param $email
         * @param $billing_email
         * @param $shipping_first_name
         * @param $shipping_name
         * @param $shipping_last_name
         * @param $shipping_address_1
         * @param $shipping_address_2
         * @param $shipping_city
         * @param $shipping_state
         * @param $shipping_zip
         * @param $transaction_uuid
         * @return string
         */
        private function finalize_payment_processing($process_payment_by, $paydunk, $order, $card_number,
                                                     $expiration_date, $cvv, $order_number, $billing_first_name,
                                                     $billing_name, $billing_last_name, $billing_address_1,
                                                     $billing_address_2, $billing_city, $billing_state, $billing_zip,
                                                     $billing_phone, $email, $billing_email, $shipping_first_name,
                                                     $shipping_name, $shipping_last_name, $shipping_address_1,
                                                     $shipping_address_2, $shipping_city, $shipping_state,
                                                     $shipping_zip, $transaction_uuid)
        {

            $status = 'cancelled';
            if ($process_payment_by == PAYDUNK_AUTHORIZE_NET_CODE) {

                $status = $this->process_using_authorize_net($paydunk, $order, $card_number,
                    $expiration_date, $cvv, $order_number, $billing_first_name, $billing_name,
                    $billing_last_name, $billing_address_1, $billing_address_2, $billing_city,
                    $billing_state, $billing_zip, $billing_phone, $email, $billing_email,
                    $shipping_first_name, $shipping_name, $shipping_last_name, $shipping_address_1,
                    $shipping_address_2, $shipping_city, $shipping_state, $shipping_zip, $transaction_uuid
                );

            }

            if ($process_payment_by == PAYDUNK_PAYPAL_CODE) {

                $status = $this->process_using_paypal_direct_cc($paydunk, $order, $card_number,
                    $expiration_date, $cvv, $order_number, $billing_first_name, $billing_name,
                    $billing_last_name, $billing_address_1, $billing_address_2, $billing_city,
                    $billing_state, $billing_zip, $billing_phone, $email, $billing_email,
                    $shipping_first_name, $shipping_name, $shipping_last_name, $shipping_address_1,
                    $shipping_address_2, $shipping_city, $shipping_state, $shipping_zip, $transaction_uuid);

            }
							
			delete_post_meta($order_id, 'confimation_need', 'yes' );
			delete_post_meta($order_id, 'cart_details');
			delete_post_meta($order_id, 'cart_details_shipping_methods');
			delete_post_meta($order_id, 'cart_shipping');
			
            return $status;
        }
    }
}