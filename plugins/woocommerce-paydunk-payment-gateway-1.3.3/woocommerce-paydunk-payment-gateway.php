<?php
/**
 * Plugin Name: WooCommerce Paydunk Payment Gateway
 * Description: The Paydunk payment gateway for WooCommerce. This plugin is dependent on <a href="https://www.paydunk.com" target="_blank">Paydunk</a> and <a href="http://www.authorize.net/" target="_blank">Authorize.Net</a> or <a href="https://paypal.com" target="_blank"> PayPal</a>.
 * Version: 1.3.3
 * Author: Leoware IT Solution
 * Author URI: http://www.leowareit.com
 * Requires at least: 3.0.3
 * Tested up to: 4.3
 * Stable tag: 1.0.6
 * Text Domain: paydunk
 * Domain Path: /languages/
 */
 

if (!defined('ABSPATH'))
    exit;
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))))
    return;

define('PAYDUNK_PLUGIN_FILE', __FILE__);
define('PAYDUNK_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('PAYDUNK_PLUGIN_DIRNAME', dirname(__FILE__));
define('PAYDUNK_PROCESS_PAYMENT_URL', site_url('?accept=paydunk&method=process'));
define('PAYDUNK_REDIRECT_PAYMENT_URL', site_url('?accept=paydunk&method=redirect'));
define('PAYDUNK_PAYMENT_ID', 'paydunk');
define('PAYDUNK_COUNTRY', 'US'); // TODO hardcoded. not the best way.
define('PAYDUNK_AUTHORIZE_NET_CODE', 'authorize_net');
define('PAYDUNK_PAYPAL_CODE', 'paypal');
define('PAYDUNK_STRIPE_CODE', 'stripe');
define('PAYDUNK_API_URL_END_POINT', 'https://api.paydunk.com/api/v1/transactions/');
define('PAYDUNK_RETURN_URL_SESSION_KEY', 'paydunk_return_url');
define('PAYDUNK_PAYMENT_ON_CART_SESSION_KEY', 'paydunk_payment_on_cart');
define('PAYDUNK_PAYMENT_INFO', 'paydunk_payment_info');

if (!class_exists('Woocommerce_Paydunk_Payment_Gateway')) {

    class Woocommerce_Paydunk_Payment_Gateway
    {
        public $admin_paydunk;
        public $visitor_paydunk;

        public function __construct()
        {
            add_action('init', array(&$this, 'load_init'));
            add_action('admin_enqueue_scripts', array(&$this, 'load_admin_scripts'));
            add_action('wp_enqueue_scripts', array(&$this, 'load_public_scripts'));

            add_filter('woocommerce_payment_gateways', array($this, 'add_paydunk_payment_gateway'));
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_paydunk_setup_link'));
        }

        public function load_init()
        {
            load_plugin_textdomain('paydunk', false, dirname(plugin_basename(__FILE__)) . '/languages/');

            $this->visitor_paydunk = new VisitorPaydunkPayment();
            if (is_admin()) {
                $this->admin_paydunk = new AdminPaydunkPayment();
            }
        }

        public function load_admin_scripts()
        {
            wp_enqueue_script(
                'paydunk_js',
                plugins_url('/js/admin-paydunk-payment.js', PAYDUNK_PLUGIN_FILE),
                array('jquery')
            );

            wp_enqueue_style(
                'paydunk_css',
                plugins_url('/css/admin-paydunk-payment.css', PAYDUNK_PLUGIN_FILE)
            );
        }

        public function load_public_scripts()
        {
            wp_enqueue_script(
                'paydunk_js',
                plugins_url('/js/visitor-paydunk-payment.js', PAYDUNK_PLUGIN_FILE),
                array('jquery', 'jquery-blockui')
            );

            wp_enqueue_style(
                'paydunk_css',
                plugins_url('/css/visitor-paydunk-payment.css', PAYDUNK_PLUGIN_FILE)
            );
        }

        public function add_paydunk_payment_gateway($methods)
        {
            $methods[] = "PaydunkPaymentGateway";
            return $methods;
        }

        public function add_paydunk_setup_link($links)
        {
            $settings = array(
                'settings' => sprintf(
                    '<a href="%s">%s</a>',
                    admin_url('admin.php?page=wc-settings&tab=checkout&section=paydunkpaymentgateway'),
                    __('Settings', 'paydunk')
                )
            );
            return array_merge($settings, $links);
        }
    }
}

require_once PAYDUNK_PLUGIN_DIRNAME . "/vendor/autoload.php";
new Woocommerce_Paydunk_Payment_Gateway();
