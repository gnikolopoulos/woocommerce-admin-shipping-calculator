<?php

/**
 * @wordpress-plugin
 * Plugin Name: Woocommerce Admin Shipping Calculator
 * Description: Plugin for WooCommerce which calculates shipping cost on wp-admin order screen
 * Version: 1.0.0
 * Author: George Nikolopoulos
 * Author URI: https://interactive-design.gr/
 * Requires Plugins:  woocommerce
 */

use Automattic\WooCommerce\Utilities\OrderUtil;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Load JavaScript on the new order screen
 *
 * @link    https://developer.wordpress.org/reference/hooks/admin_enqueue_scripts/
 * @since   1.0.0
 *
 * @param   string    $hook    The current admin page
 *
 * @return  void
 */
add_action('admin_enqueue_scripts', function( $hook ) {
	if (OrderUtil::custom_orders_table_usage_is_enabled()) {
		if ($hook !== 'woocommerce_page_wc-orders') {
			return;
		}

		if (!isset($_GET['action'])) {
			return;
		}

		if ($_GET['action'] !== 'new') {
			return;
		}
	} else {
		if ($hook !== 'post-new.php') {
			return;
		}

		if (!isset($_GET['post_type'])) {
			return;
		}

		if ($_GET['post_type'] !== 'shop_order') {
			return;
		}
	}

  wp_enqueue_script('shipping-calculator_js', plugins_url('js/admin-shipping-calculator.js', __FILE__), [], '1.0.0', false);
	wp_localize_script( 'shipping-calculator_js', 'shipping_calculator', array(
		'url' => admin_url('admin-ajax.php'),
		'nonce' => wp_create_nonce('admin_shipping_calculate')
	));
});

/**
 * Run the calculator just for loggen in users
 * Create a new, temporary array or WC_Order_Item_Product and add the order items to it in order to create a new package
 * and run the shipping calculator functions
 *
 * @link    https://developer.wordpress.org/reference/hooks/wp_ajax_action/
 * @since   1.0.0
 *
 * @return  mixed
 */
add_action('wp_ajax_admin_shipping_calculate', function () {
	if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'admin_shipping_calculate')) {
		wp_send_json_error();
	}

	$products = isset($_POST['products']) ? (array) $_POST['products'] : array();
	$orderItems = array_map(function($productId) {
		return new WC_Order_Item_Product((int) $productId);
	}, $products);

	$package = [
		'destination' => [
			'country' => isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '',
			'state' => isset($_POST['state']) ? sanitize_text_field($_POST['state']) : '',
			'postcode' => isset($_POST['postcode']) ? sanitize_text_field($_POST['postcode']) : ''
		],
		'contents' => array_map(function($orderItem) {
			return [
				'quantity' => (int) $orderItem->get_quantity(),
				'data' => $orderItem->get_product(),
				'line_total' => $orderItem->get_total(),
				'line_tax' => $orderItem->get_total_tax(),
				'line_subtotal' => $orderItem->get_subtotal(),
				'line_subtotal_tax' => $orderItem->get_subtotal_tax()
			];
		}, $orderItems),
		'contents_cost' => array_sum(array_map(function (WC_Order_Item_Product $orderItem) {
			return $orderItem->get_total();
		}, $orderItems))
	];

	$shippingZone = WC_Shipping_Zones::get_zone_matching_package($package);
	/** @var WC_Shipping_Method[] $shippingMethods */
	$shippingMethods = $shippingZone->get_shipping_methods(true);

	$prices = array();
	foreach($shippingMethods as $shippingMethod) {
		/** @var WC_Shipping_Rate[] $rates */
		$rates = $shippingMethod->get_rates_for_package($package);
		foreach($rates as $rate)
		{
			$prices[] = [
				'id' => wp_kses($rate->get_id(), array()),
				'method' => wp_kses($rate->get_method_id(), array()),
				'total' => (float) $rate->get_cost(),
				'tax' => (float) (is_array($rate->get_shipping_tax()) ? array_sum($rate->get_shipping_tax())
					: $rate->get_shipping_tax())
			];
		}
	}

	wp_send_json_success([
		'shipping' => $prices
	]);
});
