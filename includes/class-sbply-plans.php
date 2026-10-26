<?php
/**
 * Subscription plans attached to products.
 *
 * @package Sbply
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads, validates and formats the subscription plans of a product.
 *
 * A plan is a plain array:
 *
 *     array(
 *         'id'       => 'k3f9a2xq', // Stable key, stored on cart items and subscriptions.
 *         'name'     => 'Monthly',  // Optional label shown to customers.
 *         'price'    => '18.00',    // Recurring price, same tax basis as product prices.
 *         'interval' => 1,          // Bill every N periods (1–MAX_INTERVAL).
 *         'period'   => 'month',    // day|week|month|year.
 *         'length'   => 12,         // Billing cycles before it ends; 0 = until cancelled.
 *     )
 */
class Sbply_Plans {

	/**
	 * Largest "every N periods" a plan can use.
	 */
	const MAX_INTERVAL = 6;

	/**
	 * Largest number of billing cycles a plan can be limited to.
	 */
	const MAX_LENGTH = 60;

	/**
	 * Product meta: 'yes' when the product is sold on subscription.
	 */
	const META_ENABLED = '_sbply_enabled';

	/**
	 * Product meta: 'subscription' (subscription only) or 'both' (one-time or subscription).
	 */
	const META_PURCHASE_TYPE = '_sbply_purchase_type';

	/**
	 * Product meta: list of the product's own plans.
	 */
	const META_PLANS = '_sbply_plans';

	/**
	 * Product types that can be sold on subscription.
	 *
	 * @return string[]
	 */
	public static function get_supported_product_types() {
		/**
		 * Filter the product types that can carry subscription plans.
		 *
		 * @param string[] $types Product type slugs.
		 */
		return (array) apply_filters( 'sbply/supported_product_types', array( 'simple' ) );
	}

	/**
	 * Whether the product is sold on subscription (enabled and has at least one plan).
	 *
	 * @param WC_Product|null $product Product.
	 * @return bool
	 */
	public static function is_subscription_product( $product ) {
		$result = $product instanceof WC_Product
			&& in_array( $product->get_type(), self::get_supported_product_types(), true )
			&& 'yes' === $product->get_meta( self::META_ENABLED )
			&& ! empty( self::get_plans( $product ) );

		/**
		 * Filter whether a product is sold on subscription.
		 *
		 * @param bool            $result  Whether it is.
		 * @param WC_Product|null $product Product.
		 */
		return (bool) apply_filters( 'sbply/is_subscription_product', $result, $product );
	}

	/**
	 * Whether customers may also buy the product once, without subscribing.
	 *
	 * @param WC_Product $product Product.
	 * @return bool
	 */
	public static function allows_one_time_purchase( $product ) {
		return 'both' === $product->get_meta( self::META_PURCHASE_TYPE );
	}

	/**
	 * The product's valid plans, in display order.
	 *
	 * @param WC_Product $product Product.
	 * @return array[]
	 */
	public static function get_plans( $product ) {
		$plans = array();
		$raw   = $product->get_meta( self::META_PLANS );

		foreach ( is_array( $raw ) ? $raw : array() as $row ) {
			$plan = self::normalize_plan( $row );
			if ( $plan ) {
				$plans[] = $plan;
			}
		}

		/**
		 * Filter the plans offered for a product.
		 *
		 * Each plan must keep the shape documented on Sbply_Plans and a
		 * unique 'id'.
		 *
		 * @param array[]    $plans   Plans.
		 * @param WC_Product $product Product.
		 */
		return (array) apply_filters( 'sbply/product_plans', $plans, $product );
	}

	/**
	 * Find one of the product's plans by ID.
	 *
	 * @param WC_Product $product Product.
	 * @param string     $plan_id Plan ID.
	 * @return array|null
	 */
	public static function get_plan( $product, $plan_id ) {
		if ( '' === (string) $plan_id ) {
			return null;
		}

		foreach ( self::get_plans( $product ) as $plan ) {
			if ( $plan['id'] === $plan_id ) {
				return $plan;
			}
		}

		return null;
	}

	/**
	 * The plan selected when the customer did not pick one.
	 *
	 * @param WC_Product $product Product.
	 * @return array|null
	 */
	public static function get_default_plan( $product ) {
		$plans = self::get_plans( $product );
		return $plans ? $plans[0] : null;
	}

	/**
	 * The plan with the lowest recurring price.
	 *
	 * @param WC_Product $product Product.
	 * @return array|null
	 */
	public static function get_cheapest_plan( $product ) {
		$cheapest = null;

		foreach ( self::get_plans( $product ) as $plan ) {
			if ( null === $cheapest || (float) $plan['price'] < (float) $cheapest['price'] ) {
				$cheapest = $plan;
			}
		}

		return $cheapest;
	}

	/**
	 * Validate and normalize a raw plan array.
	 *
	 * @param mixed $raw Raw plan data.
	 * @return array|null Null when the plan is unusable.
	 */
	public static function normalize_plan( $raw ) {
		if ( ! is_array( $raw ) ) {
			return null;
		}

		// Capped to the subscriptions table's column sizes, or the insert would fail after payment.
		$id     = isset( $raw['id'] ) && is_scalar( $raw['id'] ) ? substr( sanitize_key( $raw['id'] ), 0, 64 ) : '';
		$price  = isset( $raw['price'] ) ? wc_format_decimal( $raw['price'] ) : '';
		$period = isset( $raw['period'] ) ? sanitize_key( $raw['period'] ) : '';

		if ( '' === $id || '' === $price || ! is_numeric( $price ) || (float) $price < 0 ) {
			return null;
		}

		if ( ! array_key_exists( $period, sbply_get_billing_periods() ) ) {
			return null;
		}

		$interval = isset( $raw['interval'] ) ? (int) $raw['interval'] : 1;
		$length   = isset( $raw['length'] ) ? (int) $raw['length'] : 0;

		return array(
			'id'       => $id,
			'name'     => isset( $raw['name'] ) && is_scalar( $raw['name'] ) ? mb_substr( sanitize_text_field( $raw['name'] ), 0, 200 ) : '',
			'price'    => $price,
			'interval' => min( self::MAX_INTERVAL, max( 1, $interval ) ),
			'period'   => $period,
			'length'   => min( self::MAX_LENGTH, max( 0, $length ) ),
		);
	}

	/**
	 * Generate a new plan ID.
	 *
	 * @return string
	 */
	public static function generate_plan_id() {
		return strtolower( wp_generate_password( 8, false, false ) );
	}

	/**
	 * Recurring price of a plan as shown in the store: "$18.00 / month for 12 months".
	 *
	 * Uses wc_get_price_to_display() so it follows the store's
	 * "display prices including/excluding tax" setting like any other price.
	 *
	 * @param WC_Product $product Product.
	 * @param array      $plan    Plan.
	 * @return string HTML.
	 */
	public static function get_plan_price_html( $product, $plan ) {
		$price = wc_price( wc_get_price_to_display( $product, array( 'price' => $plan['price'] ) ) );

		return sbply_format_recurring_price( $price, $plan['interval'], $plan['period'], $plan['length'] );
	}

	/**
	 * Plain-text summary of a plan, for places without HTML.
	 *
	 * @param WC_Product $product Product.
	 * @param array      $plan    Plan.
	 * @return string
	 */
	public static function get_plan_summary( $product, $plan ) {
		$summary = wp_strip_all_tags( html_entity_decode( self::get_plan_price_html( $product, $plan ), ENT_QUOTES, get_bloginfo( 'charset' ) ) );

		if ( '' !== $plan['name'] ) {
			/* translators: 1: plan name, 2: recurring price, e.g. "$18.00 / month". */
			$summary = sprintf( __( '%1$s: %2$s', 'subscribely-plans-for-woocommerce' ), $plan['name'], $summary );
		}

		return $summary;
	}
}
