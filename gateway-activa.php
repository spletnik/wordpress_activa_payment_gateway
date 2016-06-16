<?php
/**
 * Plugin Name: WooCommerce activa Gateway
 * Plugin URI: http://woothemes.com/products/activa-payment-gateway/
 * Description: Receive payments using the South African activa payments provider.
 * Author: WooThemes
 * Author URI: http://woothemes.com/
 * Version: 1.2.7
 *
 * Copyright (c) 2015 WooThemes
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Required functions
 */
if (!function_exists('woothemes_queue_update'))
    require_once('woo-includes/woo-functions.php');

/**
 * Plugin updates
 */
woothemes_queue_update(plugin_basename(__FILE__), '557bf07293ad916f20c207c6c9cd15ff', '18596');

load_plugin_textdomain('woocommerce-gateway-activa', false, trailingslashit(dirname(plugin_basename(__FILE__))));

add_action('plugins_loaded', 'woocommerce_activa_init', 0);

/**
 * Initialize the gateway.
 *
 * @since 1.0.0
 */
function woocommerce_activa_init() {

    if (!class_exists('WC_Payment_Gateway')) return;

    require_once(plugin_basename('classes/class-wc-gateway-activa.php'));

    add_filter('woocommerce_payment_gateways', 'woocommerce_activa_add_gateway');

} // End woocommerce_activa_init()

/**
 * Add the gateway to WooCommerce
 *
 * @since 1.0.0
 */
function woocommerce_activa_add_gateway($methods) {
    $methods[] = 'WC_Gateway_activa';
    return $methods;
} // End woocommerce_activa_add_gateway()