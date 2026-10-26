<?php
/**
 * Creates subscription records from orders and follows the parent order's status.
 *
 * @package Sbply
 */

defined( 'ABSPATH' ) || exit;

/**
 * Order → subscription lifecycle.
 *
 * - At checkout, every line item carrying a plan gets a pending subscription.
 * - When the order is paid (processing/completed), its pending subscriptions start.
 * - When the order is cancelled before payment, its pending subscriptions are cancelled.
 *
 * Creation is idempotent per line item (also enforced by a UNIQUE index), so a
 * repeated hook or a retried request never creates a second subscription.
 */
class Sbply_Order_Handler {

	/**
	 * Order meta flag: 'yes' once subscriptions exist for the order.
	 */
	const ORDER_META = '_sbply_has_subscriptions';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'on_classic_checkout' ), 20, 3 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'create_subscriptions' ), 20 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'on_order_status_changed' ), 10, 4 );
	}

	/**
	 * Classic checkout passes the order as its third argument.
	 *
	 * @param int      $order_id    Order ID.
	 * @param array    $posted_data Posted checkout data.
	 * @param WC_Order $order       Order.
	 */
	public function on_classic_checkout( $order_id, $posted_data, $order ) {
		$this->create_subscriptions( $order instanceof WC_Order ? $order : wc_get_order( $order_id ) );
	}

	/**
	 * Create a pending subscription for each plan line item of the order that has none yet.
	 *
	 * @param WC_Order $order Order.
	 * @return Sbply_Subscription[] Subscriptions created now.
	 */
	public function create_subscriptions( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return array();
		}

		$created = array();
		$store   = WC_Data_Store::load( 'sbply-subscription' );

		foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
			$plan = Sbply_Plans::normalize_plan( $item->get_meta( Sbply_Cart::ITEM_META ) );

			if ( ! $plan || $store->count( array( 'order_item_id' => $item_id ) ) ) {
				continue;
			}

			$quantity     = max( 1, (int) $item->get_quantity() );
			$subscription = new Sbply_Subscription();
			$subscription->set_props(
				array(
					'status'           => 'pending',
					'customer_id'      => $order->get_customer_id(),
					'parent_order_id'  => $order->get_id(),
					'order_item_id'    => $item_id,
					'product_id'       => $item->get_product_id(),
					'variation_id'     => $item->get_variation_id(),
					'quantity'         => $quantity,
					'plan_id'          => $plan['id'],
					'plan_name'        => $plan['name'],
					'billing_period'   => $plan['period'],
					'billing_interval' => $plan['interval'],
					'billing_length'   => $plan['length'],
					// Subtotal, not total: a coupon on the first order is not a recurring discount.
					'recurring_amount' => $item->get_subtotal(),
					'recurring_tax'    => $item->get_subtotal_tax(),
					'currency'         => $order->get_currency(),
				)
			);

			try {
				$subscription->save();
			} catch ( Exception $e ) {
				// Most likely a concurrent request created it first (UNIQUE order_item_id).
				wc_get_logger()->warning(
					sprintf( 'Could not create subscription for order #%d item #%d: %s', $order->get_id(), $item_id, $e->getMessage() ),
					array( 'source' => 'sbply' )
				);
				continue;
			}

			$created[] = $subscription;

			$order->add_order_note(
				sprintf(
					/* translators: 1: subscription ID, 2: product name. */
					__( 'Subscription #%1$d created for %2$s.', 'subscribely-plans-for-woocommerce' ),
					$subscription->get_id(),
					$item->get_name()
				)
			);
		}

		if ( $created && 'yes' !== $order->get_meta( self::ORDER_META ) ) {
			$order->update_meta_data( self::ORDER_META, 'yes' );
			$order->save_meta_data();
		}

		return $created;
	}

	/**
	 * Follow the parent order's status.
	 *
	 * @param int      $order_id Order ID.
	 * @param string   $from     Old status.
	 * @param string   $to       New status.
	 * @param WC_Order $order    Order.
	 */
	public function on_order_status_changed( $order_id, $from, $to, $order ) {
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order ) {
			return;
		}

		if ( in_array( $to, wc_get_is_paid_statuses(), true ) ) {
			// Also covers orders that never went through a checkout hook (e.g. created by another plugin).
			$this->create_subscriptions( $order );
			$this->activate_pending( $order );
		} elseif ( 'cancelled' === $to ) {
			$this->cancel_pending( $order );
		}
	}

	/**
	 * Start the order's pending subscriptions.
	 *
	 * @param WC_Order $order Order.
	 */
	private function activate_pending( $order ) {
		$subscriptions = sbply_get_subscriptions(
			array(
				'parent_order_id' => $order->get_id(),
				'status'          => 'pending',
			)
		);

		foreach ( $subscriptions as $subscription ) {
			$subscription->activate();

			$order->add_order_note(
				/* translators: %d: subscription ID. */
				sprintf( __( 'Subscription #%d activated.', 'subscribely-plans-for-woocommerce' ), $subscription->get_id() )
			);
		}
	}

	/**
	 * Cancel the order's subscriptions that never started.
	 *
	 * @param WC_Order $order Order.
	 */
	private function cancel_pending( $order ) {
		$subscriptions = sbply_get_subscriptions(
			array(
				'parent_order_id' => $order->get_id(),
				'status'          => 'pending',
			)
		);

		foreach ( $subscriptions as $subscription ) {
			$subscription->update_status( 'cancelled' );

			$order->add_order_note(
				/* translators: %d: subscription ID. */
				sprintf( __( 'Subscription #%d cancelled because the order was cancelled.', 'subscribely-plans-for-woocommerce' ), $subscription->get_id() )
			);
		}
	}
}
