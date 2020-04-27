<?php
if (!defined('ABSPATH'))
    exit;
	
if (!class_exists('PaydunkPaymentGateway')) {
    class PaydunkPaymentGateway extends WC_Payment_Gateway
    {

        /** @var WC_Logger Logger instance */
        public static $log = false;

        public function __construct()
        {
            $this->id = PAYDUNK_PAYMENT_ID;
            $this->icon = apply_filters('paydunk_icon', '');
            $this->has_fields = false;
            $this->method_title = __('Paydunk', 'paydunk');
            $this->method_description = __('Allow payments via Paydunk - the safer, faster way to checkout online!', 'paydunk');
            $this->order_button_text = __('Proceed with Paydunk', 'paydunk');

            // Load the form fields.
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();

            // Define user set variables.
            $this->title = $this->settings['title'];
            $this->description = $this->settings['description'];
            $this->testmode = $this->settings['testmode'];
            $this->instructions = $this->get_option('instructions');
            $this->enable_for_methods = $this->get_option('enable_for_methods', array());

            // Actions.
            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
            } else {
                add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
            }
			 
			if (method_exists('WC_Emails', 'instance')) {
				$instance = WC_Emails::instance();			
				remove_action( 'woocommerce_order_status_pending_to_on-hold_notification', array( $instance->emails['WC_Email_New_Order'], 'trigger' ), 30 );
				remove_action( 'woocommerce_order_status_pending_to_processing_notification', array( $instance->emails['WC_Email_New_Order'], 'trigger' ), 30 );				
				add_action( 'woocommerce_order_status_on-hold_to_processing_notification', array( $instance->emails['WC_Email_New_Order'], 'trigger' ), 30 );			
			}
        }

        /* Initialise Gateway Settings Form Fields. */
        public function init_form_fields()
        {
            global $woocommerce;

            $shipping_methods = array();

            if (is_admin()) {
                foreach ($woocommerce->shipping->load_shipping_methods() as $method) {
                    $shipping_methods[$method->id] = $method->get_title();
                }
            }

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'paydunk'),
                    'type' => 'checkbox',
                    'label' => __('Enable Paydunk payment gateway', 'paydunk'),
                    'default' => 'no',
                    'desc_tip' => true,
                ),
                'title' => array(
                    'title' => __('Title', 'paydunk'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'paydunk'),
                    'desc_tip' => true,
                    'default' => __('Paydunk Payment Gateway', 'paydunk')
                ),
                'description' => array(
                    'title' => __('Description', 'paydunk'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'paydunk'),
                    'default' => __('The Safer, Faster Way to Checkout Online!', 'paydunk'),
                    'desc_tip' => true,
                ),
                'show_on_cart' => array(
                    'title' => __('Show On Cart Page', 'paydunk'),
                    'type' => 'checkbox',
                    'label' => __('Show Paydunk payment button on cart page', 'paydunk'),
                    'description' => __('This option will show or hide the Paydunk payment button on the cart page.', 'paydunk'),
                    'default' => 'yes',
                    'desc_tip' => true,
                ),
                'app_id' => array(
                    'title' => __('Paydunk APP ID', 'paydunk'),
                    'type' => 'text',
                    'description' => __('Paydunk APP ID from https://developers.paydunk.com', 'paydunk'),
                    'default' => '',
                    'desc_tip' => true,

                ),
                'app_secret' => array(
                    'title' => __('Paydunk APP SECRET', 'paydunk'),
                    'type' => 'text',
                    'description' => __('Paydunk APP SECRET from https://developers.paydunk.com', 'paydunk'),
                    'default' => '',
                    'desc_tip' => true,

                ),
                'payment_acceptant_url' => array(
                    'title' => __('Paydunk Acceptance URL', 'paydunk'),
                    'type' => 'text',
                    'description' => __('Required. Use this URL for the "Paydunk Acceptance URL" when you create an application at <a href="https://developers.paydunk.com" target="_blank">http://developers.paydunk.com</a>.', 'paydunk'),
                    'default' => PAYDUNK_PROCESS_PAYMENT_URL,
                    'class' => 'readonly',
                    'css' => 'width: 100%',

                ),
                'payment_redirect_url' => array(
                    'title' => __('Paydunk Redirect URL', 'paydunk'),
                    'type' => 'text',
                    'description' => __('Optional. Use this URL for the "Redirect URL" when you create an application at <a href="https://developers.paydunk.com" target="_blank">http://developers.paydunk.com</a>.', 'paydunk'),
                    'default' => PAYDUNK_REDIRECT_PAYMENT_URL,
                    'class' => 'readonly',
                    'css' => 'width: 100%',

                ),
                'process_payment_by' => array(
                    'title' => __('Process Payment Using', 'paydunk'),
                    'type' => 'select',
                    'default' => PAYDUNK_AUTHORIZE_NET_CODE,
                    'description' => __('Select the payment gateway Paydunk will use to process orders.', 'paydunk'),
                    'options' => array(
                        PAYDUNK_AUTHORIZE_NET_CODE => 'Authorize.Net',
                        PAYDUNK_PAYPAL_CODE => 'PayPal Payments Pro',
                        //PAYDUNK_STRIPE_CODE => 'Stripe Payment - Not Implemented'
                    ),
                    'desc_tip' => true,
                ),
                'authorize_login_id' => array(
                    'title' => __('Authorize.Net Login ID', 'paydunk'),
                    'type' => 'text',
                    'class' => PAYDUNK_AUTHORIZE_NET_CODE,
                    'description' => __('Your Authorize.Net API Login ID from http://authorize.net.', 'paydunk'),
                    'default' => '',
                    'desc_tip' => true,

                ),
                'authorize_transaction_key' => array(
                    'title' => __('Authorize.Net Transaction Key', 'paydunk'),
                    'type' => 'text',
                    'class' => PAYDUNK_AUTHORIZE_NET_CODE,
                    'description' => __('Your Authorize.Net API Transaction Key from http://authorize.net.', 'paydunk'),
                    'default' => '',
                    'desc_tip' => true,

                ),
                'authorize_invoice_number_prefix' => array(
                    'title' => __('Invoice Number Prefix', 'paydunk'),
                    'type' => 'text',
                    'class' => PAYDUNK_AUTHORIZE_NET_CODE,
                    'description' => __('Invoice number Prefix for Authorize.Net.', 'paydunk'),
                    'default' => '',
                    'desc_tip' => true,

                ),
                'paypal_client_id' => array(
                    'title' => __('Paypal Client ID', 'paydunk'),
                    'type' => 'text',
                    'class' => PAYDUNK_PAYPAL_CODE,
                    'description' => __('Your Paypal Client ID from https://developer.paypal.com.', 'paydunk'),
                    'default' => '',
                    'desc_tip' => true,

                ),
                'paypal_client_secret' => array(
                    'title' => __('Paypal Secret', 'paydunk'),
                    'type' => 'text',
                    'class' => PAYDUNK_PAYPAL_CODE,
                    'description' => __('Your Paypal Secret from https://developer.paypal.com', 'paydunk'),
                    'default' => '',
                    'desc_tip' => true,

                ),
                'paypal_invoice_number_prefix' => array(
                    'title' => __('Invoice Number Prefix', 'paydunk'),
                    'type' => 'text',
                    'class' => PAYDUNK_PAYPAL_CODE,
                    'description' => __('Invoice number Prefix for Paypal.', 'paydunk'),
                    'default' => '',
                    'desc_tip' => true,

                ),
                'testmode' => array(
                    'title' => __('Test Mode', 'paydunk'),
                    'label' => __('Enable Sandbox/Test Mode', 'paydunk'),
                    'type' => 'checkbox',
                    'description' => __('Change the gateway to sandbox/test mode. No payments will actually be processed.', 'paydunk'),
                    'default' => 'yes',
                    'desc_tip' => true,
                ),
                'instructions' => array(
                    'title' => __('Instructions', 'paydunk'),
                    'type' => 'textarea',
                    'description' => __('Instructions or message that will be added to the thank you page.', 'paydunk'),
                    'default' => __('Thank you for using Paydunk.', 'paydunk'),
                    'desc_tip' => true,
                ),
                'force_free_shipping' => array(
                    'title' => __('Force Free Shipping', 'paydunk'),
                    'label' => __('Force free shipping for all orders when using Paydunk', 'paydunk'),
                    'type' => 'checkbox',
                    'description' => __('When enabled, this option will force free shipping for all orders paid using Paydunk.', 'paydunk'),
                    'default' => 'yes',
                    'desc_tip' => true,
                ),
				'skip_confirm_for_free_shipping' => array(
                    'title' => __('Skip Confirmation Page For Free Shipping', 'paydunk'),
                    'label' => __('Skip the final confirmation page if free shipping is offered when using Paydunk.', 'paydunk'),
                    'type' => 'checkbox',
                    'description' => __('When enabled, this option will skip the final confirmation page for all orders paid using Paydunk that have free shipping.', 'paydunk'),
                    'default' => '',
                    'desc_tip' => true,
                ),
				'skip_confirm_for_flat_rate_shipping' => array(
                    'title' => __('Skip Confirmation Page For Flat Rate Shipping', 'paydunk'),
                    'label' => __('Skip the final confirmation page if flat rate shipping is offered when using Paydunk.', 'paydunk'),
                    'type' => 'checkbox',
                    'description' => __('When enabled, this option will skip the final confirmation page for all orders paid using Paydunk that have flat rate shipping.', 'paydunk'),
                    'default' => 'yes',
                    'desc_tip' => true,
                ),
                'enable_for_methods' => array(
                    'title' => __('Enable Shipping Methods', 'paydunk'),
                    'type' => 'multiselect',
                    'class' => 'chosen_select',
                    'css' => 'width: 450px;',
                    'default' => '',
                    'description' => __('If paydunk is only available for certain methods, set it up here. Leave blank to enable for all methods.', 'paydunk'),
                    'options' => $shipping_methods,
                    'desc_tip' => true,
                )
            );

        }

        function process_payment($order_id)
        {
            $order = new WC_Order($order_id);
			
			if (!($this->get_option('force_free_shipping') == 'yes' &&  // check if free shipping and skip enabled
					  $this->get_option('skip_confirm_for_free_shipping') == 'yes') &&
					!($this->get_option('skip_confirm_for_flat_rate_shipping') == 'yes' &&
					  $order->has_shipping_method('flat_rate'))) {  // check if flat rate and skip enabled
				update_post_meta($order_id, 'confimation_need', 'yes');
			}
			
			update_post_meta($order_id, 'cart_details', WC()->session->get(PAYDUNK_PAYMENT_ON_CART_SESSION_KEY + '_Cart'));			
			update_post_meta($order_id, 'cart_details_shipping_methods', WC()->session->get( 'chosen_shipping_methods' ));
			update_post_meta($order_id, 'cart_shipping', serialize(WC()->shipping));
										
            $client_id = $this->get_option('app_id');
            $total = number_format(doubleval($order->get_total()), 2);
            $tax = number_format(doubleval($order->get_total_tax()), 2);
            $shipping = number_format(doubleval($order->get_total_shipping()), 2);

			if ( $this->get_option('force_free_shipping') == 'yes' ) {
				$total = $total - $shipping;
				$shipping = 0;				
			}
			
            // store order receipt page url into session.
            WC()->session->set(PAYDUNK_RETURN_URL_SESSION_KEY, $this->get_return_url($order));

			// Return paydunk payment screen redirect
            return array(
                'result' => 'success',
                'redirect' => "javascript:processUsingPaydunk('$client_id', '$order_id', '$total', '$tax', '$shipping');"
            );
        }

        function payment_fields()
        {
            $description = $this->get_description();
            if ($description) {
                echo wpautop(wptexturize($description));
            }

            if (file_exists(PAYDUNK_PLUGIN_DIRNAME . "/html_templates/paydunk-video.php")) {
                require_once PAYDUNK_PLUGIN_DIRNAME . "/html_templates/paydunk-video.php";
            }            
        }

        /**
         * Check If The Gateway Is Available For Use
         *
         * @return bool
         */
        public function is_available()
        {
            $order = null;

            if (!empty($this->enable_for_methods)) {

                // Only apply if all packages are being shipped via local pickup
                $chosen_shipping_methods_session = WC()->session->get('chosen_shipping_methods');

                if (isset($chosen_shipping_methods_session)) {
                    $chosen_shipping_methods = array_unique($chosen_shipping_methods_session);
                } else {
                    $chosen_shipping_methods = array();
                }

                $check_method = false;

                if (is_object($order)) {
                    if ($order->shipping_method) {
                        $check_method = $order->shipping_method;
                    }

                } elseif (empty($chosen_shipping_methods) || sizeof($chosen_shipping_methods) > 1) {
                    $check_method = false;
                } elseif (sizeof($chosen_shipping_methods) == 1) {
                    $check_method = $chosen_shipping_methods[0];
                }

                if (!$check_method) {
                    return false;
                }

                $found = false;

                foreach ($this->enable_for_methods as $method_id) {
                    if (strpos($check_method, $method_id) === 0) {
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    return false;
                }
            }

            return parent::is_available();
        }

        /**
         * Logging method
         * @param  string $message
         */
        public static function log($message)
        {
            if (empty(self::$log)) {
                self::$log = new WC_Logger();
            }
            self::$log->add(PAYDUNK_PAYMENT_ID, $message);
        }
    }
}
