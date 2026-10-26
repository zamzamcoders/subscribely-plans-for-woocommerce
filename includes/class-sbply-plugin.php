<?php
/**
 * Main plugin container.
 *
 * @package Sbply
 */

defined( 'ABSPATH' ) || exit;

/**
 * Singleton that wires all Sbply components together.
 */
final class Sbply_Plugin {

	/**
	 * Single instance.
	 *
	 * @var Sbply_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Cart integration.
	 *
	 * @var Sbply_Cart
	 */
	public $cart;

	/**
	 * Order → subscription lifecycle.
	 *
	 * @var Sbply_Order_Handler
	 */
	public $orders;

	/**
	 * Get (and boot on first call) the plugin.
	 *
	 * @return Sbply_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Load files and register components.
	 */
	private function __construct() {
		$this->register_tables();
		$this->includes();

		Sbply_Install::maybe_upgrade();

		add_filter( 'woocommerce_data_stores', array( $this, 'register_data_store' ) );

		new Sbply_Product_Display();
		$this->cart   = new Sbply_Cart();
		$this->orders = new Sbply_Order_Handler();

		if ( is_admin() ) {
			new Sbply_Admin_Product();
			new Sbply_Admin_Subscriptions();
			new Sbply_Admin_Order();
		}

		/**
		 * Fires once Sbply has booted. Add-ons hook here to load their modules.
		 *
		 * @param Sbply_Plugin $plugin Plugin container.
		 */
		do_action( 'sbply/loaded', $this );
	}

	/**
	 * Expose the meta table to the WordPress metadata API.
	 *
	 * add_metadata( 'sbply_subscription', … ) looks the table up as
	 * $wpdb->sbply_subscriptionmeta, with the object column
	 * sbply_subscription_id.
	 */
	private function register_tables() {
		global $wpdb;

		$wpdb->sbply_subscriptionmeta = $wpdb->prefix . 'sbply_subscriptionmeta';
	}

	/**
	 * Require component files.
	 */
	private function includes() {
		$dir = SBPLY_PATH . 'includes/';

		require_once $dir . 'class-sbply-install.php';
		require_once $dir . 'sbply-functions.php';
		require_once $dir . 'class-sbply-plans.php';
		require_once $dir . 'class-sbply-subscription.php';
		require_once $dir . 'data-stores/class-sbply-subscription-data-store.php';
		require_once $dir . 'frontend/class-sbply-product-display.php';
		require_once $dir . 'class-sbply-cart.php';
		require_once $dir . 'class-sbply-order-handler.php';

		if ( is_admin() ) {
			require_once $dir . 'admin/class-sbply-admin-product.php';
			require_once $dir . 'admin/class-sbply-admin-subscriptions.php';
			require_once $dir . 'admin/class-sbply-admin-order.php';
		}
	}

	/**
	 * Register the subscription data store with WC_Data_Store::load().
	 *
	 * @param array $stores Data store class names keyed by object type.
	 * @return array
	 */
	public function register_data_store( $stores ) {
		$stores['sbply-subscription'] = 'Sbply_Subscription_Data_Store';
		return $stores;
	}
}
