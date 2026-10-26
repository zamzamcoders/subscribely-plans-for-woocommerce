<?php
/**
 * Cart and checkout integration.
 *
 * @package Sbply
 */

defined( 'ABSPATH' ) || exit;

/**
 * Carries the chosen plan through cart, checkout and order line items.
 *
 * Works for both the classic and the block (Store API) cart and checkout:
 * every hook used here is fired by both.
 */
class Sbply_Cart {

	/**
	 * Cart item key holding the chosen plan ID.
	 */
	const CART_KEY = 'sbply_plan_id';

	/**
	 * Order item meta holding a snapshot of the plan at purchase time.
	 */
	const ITEM_META = '_sbply_plan';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 2 );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_plan_prices' ), 20 );
		add_action( 'woocommerce_check_cart_items', array( $this, 'check_cart_items' ) );
		add_filter( 'woocommerce_get_item_data', array( $this, 'cart_item_data' ), 10, 2 );

		add_filter( 'woocommerce_checkout_registration_required', array( $this, 'require_account' ) );
		add_filter( 'woocommerce_checkout_registration_enabled', array( $this, 'enable_registration' ) );

		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 3 );
		add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( $this, 'format_order_item_meta' ), 10, 2 );
	}

	/**
	 * Plan ID posted with the add-to-cart form, or null when none was posted.
	 *
	 * @return string|null
	 */
	private function get_posted_plan_id() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce's add-to-cart form carries no nonce; the value is validated against the product's plans.
		if ( ! isset( $_POST[ Sbply_Product_Display::FIELD ] ) ) {
			return null;
		}
		return sanitize_key( wp_unslash( $_POST[ Sbply_Product_Display::FIELD ] ) );
		// phpcs:enable
	}

	/**
	 * Reject a posted plan that does not belong to the product.
	 *
	 * @param bool $passed     Whether validation passed so far.
	 * @param int  $product_id Product ID.
	 * @return bool
	 */
	public function validate_add_to_cart( $passed, $product_id ) {
		$product = wc_get_product( $product_id );

		if ( ! $passed || ! Sbply_Plans::is_subscription_product( $product ) ) {
			return $passed;
		}

		$plan_id = $this->get_posted_plan_id();

		if ( '' === $plan_id && ! Sbply_Plans::allows_one_time_purchase( $product ) ) {
			wc_add_notice( __( 'Please choose a subscription plan.', 'subscribely-plans-for-woocommerce' ), 'error' );
			return false;
		}

		if ( null !== $plan_id && '' !== $plan_id && ! Sbply_Plans::get_plan( $product, $plan_id ) ) {
			wc_add_notice( __( 'The selected subscription plan is no longer available.', 'subscribely-plans-for-woocommerce' ), 'error' );
			return false;
		}

		return $passed;
	}

	/**
	 * Store the chosen plan on the cart item.
	 *
	 * When nothing was posted (shop-page buttons, the Store API) a
	 * subscription-only product gets its first plan, and a product that also
	 * sells one-time is added as a one-time purchase.
	 *
	 * @param array $cart_item_data Extra cart item data.
	 * @param int   $product_id     Product ID.
	 * @param int   $variation_id   Variation ID.
	 * @return array
	 */
	public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
		$product = wc_get_product( $variation_id ? $variation_id : $product_id );

		if ( ! Sbply_Plans::is_subscription_product( $product ) ) {
			return $cart_item_data;
		}

		$plan_id = $this->get_posted_plan_id();
		$plan    = null === $plan_id || '' === $plan_id ? null : Sbply_Plans::get_plan( $product, $plan_id );

		if ( ! $plan && ! Sbply_Plans::allows_one_time_purchase( $product ) ) {
			$plan = Sbply_Plans::get_default_plan( $product );
		}

		if ( $plan ) {
			$cart_item_data[ self::CART_KEY ] = $plan['id'];
		}

		return $cart_item_data;
	}

	/**
	 * The plan of a cart item, re-read from the product so price edits apply.
	 *
	 * @param array $cart_item Cart item.
	 * @return array|null
	 */
	public static function get_cart_item_plan( $cart_item ) {
		if ( empty( $cart_item[ self::CART_KEY ] ) || empty( $cart_item['data'] ) ) {
			return null;
		}

		return Sbply_Plans::get_plan( $cart_item['data'], $cart_item[ self::CART_KEY ] );
	}

	/**
	 * Charge the plan price for subscription cart items.
	 *
	 * @param WC_Cart $cart Cart.
	 */
	public function apply_plan_prices( $cart ) {
		foreach ( $cart->get_cart() as $cart_item ) {
			$plan = self::get_cart_item_plan( $cart_item );
			if ( $plan ) {
				$cart_item['data']->set_price( $plan['price'] );
			}
		}
	}

	/**
	 * Remove cart items whose plan was deleted since they were added.
	 */
	public function check_cart_items() {
		$cart = WC()->cart;

		foreach ( $cart->get_cart() as $key => $cart_item ) {
			if ( ! empty( $cart_item[ self::CART_KEY ] ) && ! self::get_cart_item_plan( $cart_item ) ) {
				$cart->remove_cart_item( $key );
				wc_add_notice(
					sprintf(
						/* translators: %s: product name. */
						__( '%s was removed from your cart because its subscription plan is no longer available.', 'subscribely-plans-for-woocommerce' ),
						$cart_item['data']->get_name()
					),
					'error'
				);
			}
		}
	}

	/**
	 * Show the plan under the cart item name.
	 *
	 * @param array $item_data Display data.
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	public function cart_item_data( $item_data, $cart_item ) {
		$plan = self::get_cart_item_plan( $cart_item );

		if ( $plan ) {
			$item_data[] = array(
				'key'     => __( 'Subscription', 'subscribely-plans-for-woocommerce' ),
				'value'   => Sbply_Plans::get_plan_summary( $cart_item['data'], $plan ),
				'display' => Sbply_Plans::get_plan_price_html( $cart_item['data'], $plan ),
			);
		}

		return $item_data;
	}

	/**
	 * Whether the cart holds at least one subscription.
	 *
	 * @return bool
	 */
	public static function cart_contains_subscription() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( self::get_cart_item_plan( $cart_item ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Subscriptions belong to an account the customer can manage them from,
	 * so guest checkout is off while the cart holds one.
	 *
	 * @param bool $required Whether an account is required.
	 * @return bool
	 */
	public function require_account( $required ) {
		return $required || self::cart_contains_subscription();
	}

	/**
	 * Allow creating an account at checkout while the cart holds a subscription.
	 *
	 * @param bool $enabled Whether registration at checkout is enabled.
	 * @return bool
	 */
	public function enable_registration( $enabled ) {
		return $enabled || self::cart_contains_subscription();
	}

	/**
	 * Snapshot the plan onto the order line item.
	 *
	 * @param WC_Order_Item_Product $item          Line item.
	 * @param string                $cart_item_key Cart item key.
	 * @param array                 $values        Cart item.
	 */
	public function add_order_item_meta( $item, $cart_item_key, $values ) {
		$plan = self::get_cart_item_plan( $values );

		if ( $plan ) {
			$item->update_meta_data( self::ITEM_META, $plan );
		}
	}

	/**
	 * Show the plan on order items (thank-you page, emails, admin, My Account).
	 *
	 * @param array         $formatted_meta Formatted meta, keyed by meta ID.
	 * @param WC_Order_Item $item           Order item.
	 * @return array
	 */
	public function format_order_item_meta( $formatted_meta, $item ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return $formatted_meta;
		}

		$plan = Sbply_Plans::normalize_plan( $item->get_meta( self::ITEM_META ) );
		if ( ! $plan ) {
			return $formatted_meta;
		}

		$order    = $item->get_order();
		$currency = $order ? $order->get_currency() : '';
		$value    = sbply_format_recurring_price(
			wc_price( (float) $plan['price'], array( 'currency' => $currency ) ),
			$plan['interval'],
			$plan['period'],
			$plan['length']
		);

		$formatted_meta['sbply_plan'] = (object) array(
			'key'           => 'sbply_plan',
			'value'         => wp_strip_all_tags( $value ),
			'display_key'   => __( 'Subscription', 'subscribely-plans-for-woocommerce' ),
			'display_value' => wpautop( $value ),
		);

		return $formatted_meta;
	}
}
