<?php
/**
 * Plugin Name:       Subscribely Plans for WooCommerce
 * Plugin URI:        https://github.com/zamzamcoders/zamzamsubscriptions
 * Description:       Sell WooCommerce products on recurring subscription plans, alongside or instead of one-time purchases.
 * Version:           1.0.0
 * Author:            ZamZamcoders
 * Author URI:        https://profiles.wordpress.org/zamzamcoders/
 * Text Domain:       subscribely-plans-for-woocommerce
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * WC requires at least: 8.2
 * WC tested up to: 11.1
 *
 * @package Sbply
 */

defined( 'ABSPATH' ) || exit;

define( 'SBPLY_VERSION', '1.0.0' );
define( 'SBPLY_FILE', __FILE__ );
define( 'SBPLY_PATH', plugin_dir_path( __FILE__ ) );
define( 'SBPLY_URL', plugin_dir_url( __FILE__ ) );

// Declare WooCommerce HPOS + Cart/Checkout Blocks compatibility.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

register_activation_hook(
	__FILE__,
	function () {
		require_once SBPLY_PATH . 'includes/class-sbply-install.php';
		Sbply_Install::install();
	}
);

/*
 * Translations are loaded by WordPress itself (just-in-time loading for the
 * text domain since 4.6), so no load_plugin_textdomain() call is needed.
 */

/**
 * Boot the plugin once all plugins are loaded so WooCommerce is available.
 */
function sbply_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>';
				esc_html_e( 'ZamZam Subscriptions requires WooCommerce to be installed and active.', 'subscribely-plans-for-woocommerce' );
				echo '</p></div>';
			}
		);
		return;
	}

	require_once SBPLY_PATH . 'includes/class-sbply-plugin.php';
	Sbply_Plugin::instance();
}

add_action( 'plugins_loaded', 'sbply_init', 10 );

/**
 * Add a Subscriptions link to the plugin's row on the Plugins screen.
 *
 * @param string[] $links Existing action links.
 * @return string[]
 */
function sbply_plugin_action_links( $links ) {
	if ( ! class_exists( 'WooCommerce' ) || ! current_user_can( 'manage_woocommerce' ) ) {
		return $links;
	}

	$link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'admin.php?page=sbply-subscriptions' ) ),
		esc_html__( 'Subscriptions', 'subscribely-plans-for-woocommerce' )
	);

	return array_merge( array( 'sbply-subscriptions' => $link ), $links );
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'sbply_plugin_action_links' );

/**
 * Main accessor to the plugin container, for add-ons and themes.
 *
 * @return Sbply_Plugin|null
 */
function sbply() {
	return class_exists( 'Sbply_Plugin' ) ? Sbply_Plugin::instance() : null;
}
