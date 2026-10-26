<?php
/**
 * Subscription object.
 *
 * @package Sbply
 */

defined( 'ABSPATH' ) || exit;

/**
 * A customer's subscription to one product on one plan.
 *
 * Standard WC_Data CRUD object: use the getters/setters and save(); the
 * storage lives in Sbply_Subscription_Data_Store (custom tables, so it is
 * independent of whether orders use HPOS or posts).
 */
class Sbply_Subscription extends WC_Data {

	/**
	 * Object type, used in WC_Data hook names.
	 *
	 * @var string
	 */
	protected $object_type = 'sbply_subscription';

	/**
	 * Cache group.
	 *
	 * @var string
	 */
	protected $cache_group = 'sbply_subscriptions';

	/**
	 * Data with defaults.
	 *
	 * @var array
	 */
	protected $data = array(
		'status'            => 'pending',
		'customer_id'       => 0,
		'parent_order_id'   => 0,
		'order_item_id'     => 0,
		'product_id'        => 0,
		'variation_id'      => 0,
		'quantity'          => 1,
		'plan_id'           => '',
		'plan_name'         => '',
		'billing_period'    => 'month',
		'billing_interval'  => 1,
		'billing_length'    => 0,
		'recurring_amount'  => '0',
		'recurring_tax'     => '0',
		'currency'          => '',
		'date_created'      => null,
		'date_modified'     => null,
		'start_date'        => null,
		'next_payment_date' => null,
		'end_date'          => null,
	);

	/**
	 * Constructor.
	 *
	 * @param int|Sbply_Subscription $subscription Subscription ID or object.
	 * @throws Exception When the ID does not exist.
	 */
	public function __construct( $subscription = 0 ) {
		parent::__construct( $subscription );

		if ( is_numeric( $subscription ) && $subscription > 0 ) {
			$this->set_id( $subscription );
		} elseif ( $subscription instanceof self ) {
			$this->set_id( $subscription->get_id() );
		} else {
			$this->set_object_read( true );
		}

		$this->data_store = WC_Data_Store::load( 'sbply-subscription' );

		if ( $this->get_id() > 0 ) {
			$this->data_store->read( $this );
		}
	}

	/**
	 * Prefix for getter/setter filters: sbply_subscription_get_{prop}.
	 *
	 * @return string
	 */
	protected function get_hook_prefix() {
		return 'sbply_subscription_get_';
	}

	/*
	|--------------------------------------------------------------------------
	| Getters
	|--------------------------------------------------------------------------
	*/

	/**
	 * Status slug.
	 *
	 * @param string $context view|edit.
	 * @return string
	 */
	public function get_status( $context = 'view' ) {
		return $this->get_prop( 'status', $context );
	}

	/**
	 * Customer (user) ID.
	 *
	 * @param string $context view|edit.
	 * @return int
	 */
	public function get_customer_id( $context = 'view' ) {
		return $this->get_prop( 'customer_id', $context );
	}

	/**
	 * Order that created the subscription.
	 *
	 * @param string $context view|edit.
	 * @return int
	 */
	public function get_parent_order_id( $context = 'view' ) {
		return $this->get_prop( 'parent_order_id', $context );
	}

	/**
	 * Line item of the parent order that created the subscription.
	 *
	 * @param string $context view|edit.
	 * @return int
	 */
	public function get_order_item_id( $context = 'view' ) {
		return $this->get_prop( 'order_item_id', $context );
	}

	/**
	 * Product ID.
	 *
	 * @param string $context view|edit.
	 * @return int
	 */
	public function get_product_id( $context = 'view' ) {
		return $this->get_prop( 'product_id', $context );
	}

	/**
	 * Variation ID (0 for simple products).
	 *
	 * @param string $context view|edit.
	 * @return int
	 */
	public function get_variation_id( $context = 'view' ) {
		return $this->get_prop( 'variation_id', $context );
	}

	/**
	 * Quantity.
	 *
	 * @param string $context view|edit.
	 * @return int
	 */
	public function get_quantity( $context = 'view' ) {
		return $this->get_prop( 'quantity', $context );
	}

	/**
	 * ID of the plan the subscription was bought on.
	 *
	 * @param string $context view|edit.
	 * @return string
	 */
	public function get_plan_id( $context = 'view' ) {
		return $this->get_prop( 'plan_id', $context );
	}

	/**
	 * Plan name at the time of purchase.
	 *
	 * @param string $context view|edit.
	 * @return string
	 */
	public function get_plan_name( $context = 'view' ) {
		return $this->get_prop( 'plan_name', $context );
	}

	/**
	 * Billing period slug.
	 *
	 * @param string $context view|edit.
	 * @return string
	 */
	public function get_billing_period( $context = 'view' ) {
		return $this->get_prop( 'billing_period', $context );
	}

	/**
	 * Bill every N periods.
	 *
	 * @param string $context view|edit.
	 * @return int
	 */
	public function get_billing_interval( $context = 'view' ) {
		return $this->get_prop( 'billing_interval', $context );
	}

	/**
	 * Number of billing cycles; 0 = until cancelled.
	 *
	 * @param string $context view|edit.
	 * @return int
	 */
	public function get_billing_length( $context = 'view' ) {
		return $this->get_prop( 'billing_length', $context );
	}

	/**
	 * Recurring amount per cycle, excluding tax.
	 *
	 * @param string $context view|edit.
	 * @return string
	 */
	public function get_recurring_amount( $context = 'view' ) {
		return $this->get_prop( 'recurring_amount', $context );
	}

	/**
	 * Recurring tax per cycle.
	 *
	 * @param string $context view|edit.
	 * @return string
	 */
	public function get_recurring_tax( $context = 'view' ) {
		return $this->get_prop( 'recurring_tax', $context );
	}

	/**
	 * Currency code.
	 *
	 * @param string $context view|edit.
	 * @return string
	 */
	public function get_currency( $context = 'view' ) {
		return $this->get_prop( 'currency', $context );
	}

	/**
	 * Creation date.
	 *
	 * @param string $context view|edit.
	 * @return WC_DateTime|null
	 */
	public function get_date_created( $context = 'view' ) {
		return $this->get_prop( 'date_created', $context );
	}

	/**
	 * Last modified date.
	 *
	 * @param string $context view|edit.
	 * @return WC_DateTime|null
	 */
	public function get_date_modified( $context = 'view' ) {
		return $this->get_prop( 'date_modified', $context );
	}

	/**
	 * Date the subscription became active.
	 *
	 * @param string $context view|edit.
	 * @return WC_DateTime|null
	 */
	public function get_start_date( $context = 'view' ) {
		return $this->get_prop( 'start_date', $context );
	}

	/**
	 * Date the next payment is due; null when no further payment is scheduled.
	 *
	 * @param string $context view|edit.
	 * @return WC_DateTime|null
	 */
	public function get_next_payment_date( $context = 'view' ) {
		return $this->get_prop( 'next_payment_date', $context );
	}

	/**
	 * Date the subscription ends; null when it runs until cancelled.
	 *
	 * @param string $context view|edit.
	 * @return WC_DateTime|null
	 */
	public function get_end_date( $context = 'view' ) {
		return $this->get_prop( 'end_date', $context );
	}

	/*
	|--------------------------------------------------------------------------
	| Setters
	|--------------------------------------------------------------------------
	*/

	/**
	 * Set status. Unknown statuses are ignored.
	 *
	 * @param string $status Status slug.
	 */
	public function set_status( $status ) {
		$status = sanitize_key( $status );

		if ( array_key_exists( $status, sbply_get_subscription_statuses() ) ) {
			$this->set_prop( 'status', $status );
		}
	}

	/**
	 * Set customer ID.
	 *
	 * @param int $value User ID.
	 */
	public function set_customer_id( $value ) {
		$this->set_prop( 'customer_id', absint( $value ) );
	}

	/**
	 * Set parent order ID.
	 *
	 * @param int $value Order ID.
	 */
	public function set_parent_order_id( $value ) {
		$this->set_prop( 'parent_order_id', absint( $value ) );
	}

	/**
	 * Set parent order line item ID.
	 *
	 * @param int $value Order item ID.
	 */
	public function set_order_item_id( $value ) {
		$this->set_prop( 'order_item_id', absint( $value ) );
	}

	/**
	 * Set product ID.
	 *
	 * @param int $value Product ID.
	 */
	public function set_product_id( $value ) {
		$this->set_prop( 'product_id', absint( $value ) );
	}

	/**
	 * Set variation ID.
	 *
	 * @param int $value Variation ID.
	 */
	public function set_variation_id( $value ) {
		$this->set_prop( 'variation_id', absint( $value ) );
	}

	/**
	 * Set quantity.
	 *
	 * @param int $value Quantity.
	 */
	public function set_quantity( $value ) {
		$this->set_prop( 'quantity', max( 1, (int) $value ) );
	}

	/**
	 * Set plan ID.
	 *
	 * @param string $value Plan ID.
	 */
	public function set_plan_id( $value ) {
		$this->set_prop( 'plan_id', sanitize_key( $value ) );
	}

	/**
	 * Set plan name.
	 *
	 * @param string $value Plan name.
	 */
	public function set_plan_name( $value ) {
		$this->set_prop( 'plan_name', sanitize_text_field( $value ) );
	}

	/**
	 * Set billing period. Unknown periods are ignored.
	 *
	 * @param string $value Period slug.
	 */
	public function set_billing_period( $value ) {
		if ( array_key_exists( $value, sbply_get_billing_periods() ) ) {
			$this->set_prop( 'billing_period', $value );
		}
	}

	/**
	 * Set billing interval.
	 *
	 * @param int $value Bill every N periods.
	 */
	public function set_billing_interval( $value ) {
		$this->set_prop( 'billing_interval', min( Sbply_Plans::MAX_INTERVAL, max( 1, (int) $value ) ) );
	}

	/**
	 * Set billing length.
	 *
	 * @param int $value Billing cycles; 0 = until cancelled.
	 */
	public function set_billing_length( $value ) {
		$this->set_prop( 'billing_length', max( 0, (int) $value ) );
	}

	/**
	 * Set recurring amount (excluding tax).
	 *
	 * @param string|float $value Amount.
	 */
	public function set_recurring_amount( $value ) {
		$this->set_prop( 'recurring_amount', wc_format_decimal( $value ) );
	}

	/**
	 * Set recurring tax.
	 *
	 * @param string|float $value Amount.
	 */
	public function set_recurring_tax( $value ) {
		$this->set_prop( 'recurring_tax', wc_format_decimal( $value ) );
	}

	/**
	 * Set currency.
	 *
	 * @param string $value Currency code.
	 */
	public function set_currency( $value ) {
		$this->set_prop( 'currency', strtoupper( sanitize_key( $value ) ) );
	}

	/**
	 * Set creation date.
	 *
	 * @param string|int|WC_DateTime|null $date Date; strings without a timezone are site time, ints are UTC timestamps.
	 */
	public function set_date_created( $date = null ) {
		$this->set_date_prop( 'date_created', $date );
	}

	/**
	 * Set last modified date.
	 *
	 * @param string|int|WC_DateTime|null $date Date.
	 */
	public function set_date_modified( $date = null ) {
		$this->set_date_prop( 'date_modified', $date );
	}

	/**
	 * Set start date.
	 *
	 * @param string|int|WC_DateTime|null $date Date.
	 */
	public function set_start_date( $date = null ) {
		$this->set_date_prop( 'start_date', $date );
	}

	/**
	 * Set next payment date.
	 *
	 * @param string|int|WC_DateTime|null $date Date.
	 */
	public function set_next_payment_date( $date = null ) {
		$this->set_date_prop( 'next_payment_date', $date );
	}

	/**
	 * Set end date.
	 *
	 * @param string|int|WC_DateTime|null $date Date.
	 */
	public function set_end_date( $date = null ) {
		$this->set_date_prop( 'end_date', $date );
	}

	/*
	|--------------------------------------------------------------------------
	| Behaviour
	|--------------------------------------------------------------------------
	*/

	/**
	 * Whether the subscription has the given status.
	 *
	 * @param string|string[] $status Status slug(s).
	 * @return bool
	 */
	public function has_status( $status ) {
		return in_array( $this->get_status(), (array) $status, true );
	}

	/**
	 * Recurring total per cycle, including tax.
	 *
	 * @return float
	 */
	public function get_recurring_total() {
		return (float) $this->get_recurring_amount() + (float) $this->get_recurring_tax();
	}

	/**
	 * Formatted recurring total: "$18.00 / month for 12 months".
	 *
	 * @return string HTML.
	 */
	public function get_formatted_recurring_total() {
		return sbply_format_recurring_price(
			wc_price( $this->get_recurring_total(), array( 'currency' => $this->get_currency() ) ),
			$this->get_billing_interval(),
			$this->get_billing_period(),
			$this->get_billing_length()
		);
	}

	/**
	 * Parent order, if it still exists.
	 *
	 * @return WC_Order|false
	 */
	public function get_parent_order() {
		$order = $this->get_parent_order_id() ? wc_get_order( $this->get_parent_order_id() ) : false;
		return $order instanceof WC_Order ? $order : false;
	}

	/**
	 * Start the subscription now: set the start date and schedule the dates
	 * that follow from the plan.
	 *
	 * @param int|null $timestamp Start time as a UTC timestamp; defaults to now.
	 */
	public function activate( $timestamp = null ) {
		$start    = null === $timestamp ? time() : (int) $timestamp;
		$interval = $this->get_billing_interval();
		$period   = $this->get_billing_period();
		$length   = $this->get_billing_length();

		$this->set_start_date( $start );

		$next = sbply_add_periods( $start, $interval, $period );
		$end  = $length > 0 ? sbply_add_periods( $start, $interval * $length, $period ) : null;

		// A plan that runs for a single cycle ends before any renewal is due.
		$this->set_next_payment_date( null !== $end && $next >= $end ? null : $next );
		$this->set_end_date( $end );

		$this->update_status( 'active' );
	}

	/**
	 * Change status, save, and fire the status-change actions.
	 *
	 * @param string $new_status Status slug.
	 */
	public function update_status( $new_status ) {
		$old_status = $this->get_status();

		$this->set_status( $new_status );
		$this->save();

		$new_status = $this->get_status();

		if ( $old_status === $new_status ) {
			return;
		}

		/**
		 * Fires when a subscription moves to a status.
		 *
		 * @param Sbply_Subscription $subscription Subscription.
		 * @param string               $old_status   Previous status.
		 */
		do_action( "sbply/subscription_status_{$new_status}", $this, $old_status );

		/**
		 * Fires whenever a subscription changes status.
		 *
		 * @param Sbply_Subscription $subscription Subscription.
		 * @param string               $old_status   Previous status.
		 * @param string               $new_status   New status.
		 */
		do_action( 'sbply/subscription_status_changed', $this, $old_status, $new_status );
	}
}
