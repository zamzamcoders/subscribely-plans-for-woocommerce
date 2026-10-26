<?php
/**
 * Subscriptions box on the order edit screen.
 *
 * @package Sbply
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lists the subscriptions an order created. Works with HPOS and post-based orders.
 */
class Sbply_Admin_Order {

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 10, 2 );
	}

	/**
	 * Add the box when the order has subscriptions.
	 *
	 * @param string           $screen_id     Screen ID.
	 * @param WP_Post|WC_Order $post_or_order Post (legacy orders) or order (HPOS).
	 */
	public function add_meta_box( $screen_id, $post_or_order = null ) {
		$order_screen = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';

		if ( $screen_id !== $order_screen ) {
			return;
		}

		$order = $this->get_order( $post_or_order );
		if ( ! $order || 'yes' !== $order->get_meta( Sbply_Order_Handler::ORDER_META ) ) {
			return;
		}

		add_meta_box(
			'sbply-order-subscriptions',
			__( 'Subscriptions', 'subscribely-plans-for-woocommerce' ),
			array( $this, 'render' ),
			$order_screen,
			'normal',
			'default'
		);
	}

	/**
	 * Order from the meta box argument.
	 *
	 * @param WP_Post|WC_Order|null $post_or_order Post or order.
	 * @return WC_Order|false
	 */
	private function get_order( $post_or_order ) {
		if ( $post_or_order instanceof WC_Order ) {
			return $post_or_order;
		}

		$order = $post_or_order instanceof WP_Post ? wc_get_order( $post_or_order->ID ) : false;
		return $order instanceof WC_Order ? $order : false;
	}

	/**
	 * Render the box.
	 *
	 * @param WP_Post|WC_Order $post_or_order Post or order.
	 */
	public function render( $post_or_order ) {
		$order = $this->get_order( $post_or_order );
		if ( ! $order ) {
			return;
		}

		$subscriptions = sbply_get_subscriptions(
			array(
				'parent_order_id' => $order->get_id(),
				'order'           => 'ASC',
			)
		);

		if ( ! $subscriptions ) {
			echo '<p>' . esc_html__( 'No subscriptions found for this order.', 'subscribely-plans-for-woocommerce' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped sbply-order-subscriptions"><thead><tr>';
		echo '<th>' . esc_html__( 'Subscription', 'subscribely-plans-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'subscribely-plans-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Product', 'subscribely-plans-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Recurring total', 'subscribely-plans-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Next payment', 'subscribely-plans-for-woocommerce' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $subscriptions as $subscription ) {
			$product = wc_get_product( $subscription->get_variation_id() ? $subscription->get_variation_id() : $subscription->get_product_id() );
			$url     = add_query_arg(
				array(
					'page' => Sbply_Admin_Subscriptions::PAGE,
					's'    => $subscription->get_id(),
				),
				admin_url( 'admin.php' )
			);

			echo '<tr>';
			printf( '<td><a href="%1$s">#%2$s</a></td>', esc_url( $url ), esc_html( (string) $subscription->get_id() ) );
			echo '<td>' . wp_kses_post( Sbply_Admin_Subscriptions::status_badge( $subscription->get_status() ) ) . '</td>';
			echo '<td>' . esc_html( $product ? $product->get_name() : __( 'Deleted product', 'subscribely-plans-for-woocommerce' ) ) . '</td>';
			echo '<td>' . wp_kses_post( $subscription->get_formatted_recurring_total() ) . '</td>';
			echo '<td>' . wp_kses_post( Sbply_Admin_Subscriptions::format_date( $subscription->get_next_payment_date() ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}
}
