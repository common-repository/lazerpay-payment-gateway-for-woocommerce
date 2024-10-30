<?php
/*
	Plugin Name:			Lazerpay Payment Gateway for WooCommerce
	Description:            WooCommerce payment gateway for Lazerpay
	Version:                1.0.0
    Text Domain:            lazerpay-payment-gateway-for-woocommerce
	Author: 				Lazerpay
	Author URI: 			https://www.lazerpay.finance/
	License:        		GPL-2.0+
	License URI:    		http://www.gnu.org/licenses/gpl-2.0.txt
	WC requires at least:   3.8.0
	WC tested up to:        6.6
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'TBZ_WC_LAZERPAY_MAIN_FILE' ) ) {
	define( 'TBZ_WC_LAZERPAY_MAIN_FILE', __FILE__ );
}

if ( ! defined( 'TBZ_WC_LAZERPAY_URL' ) ) {
	define( 'TBZ_WC_LAZERPAY_URL', untrailingslashit( plugins_url( '/', __FILE__ ) ) );
}

if ( ! defined( 'TBZ_WC_LAZERPAY_VERSION' ) ) {
	define( 'TBZ_WC_LAZERPAY_VERSION', '1.0.0' );
}

/**
 * Initialize Lazerpay WooCommerce payment gateway.
 */
function tbz_wc_lazerpay_init() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	require_once __DIR__ . '/includes/class-wc-lazerpay-gateway.php';

	add_filter( 'woocommerce_payment_gateways', 'tbz_wc_add_lazerpay_gateway' );

}
add_action( 'plugins_loaded', 'tbz_wc_lazerpay_init' );


/**
* Add Settings link to the plugin entry in the plugins menu
**/
function tbz_wc_lazerpay_plugin_action_links( $links ) {

    $settings_link = array(
    	'settings' => '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=lazerpay' ) ) . '" title="View Settings">Settings</a>'
    );

    return array_merge( $settings_link, $links );

}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'tbz_wc_lazerpay_plugin_action_links' );


/**
* Add Lazerpay Gateway to WC
**/
function tbz_wc_add_lazerpay_gateway( $methods ) {

	$methods[] = 'WC_Lazerpay_Gateway';

	return $methods;

}

/**
* Display the test mode notice
**/
function tbz_wc_lazerpay_test_mode_notice(){

	$settings = get_option( 'woocommerce_lazerpay_settings' );

	$test_mode = isset( $settings['test_mode'] ) ? $settings['test_mode'] : '';

	if ( 'yes' === $test_mode ) {
		/* translators: 1. Lazerpay settings page URL link. */
		echo '<div class="error"><p>' . sprintf( __( 'Lazerpay test mode is still enabled, Click <strong><a href="%s">here</a></strong> to disable it when you want to start accepting live payment on your site.', 'lazerpay-payment-gateway-for-woocommerce' ), esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=lazerpay' ) ) ) . '</p></div>';
	}
}
add_action( 'admin_notices', 'tbz_wc_lazerpay_test_mode_notice' );
