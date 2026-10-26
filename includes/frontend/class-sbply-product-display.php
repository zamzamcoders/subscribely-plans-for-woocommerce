<?php
/**
 * Subscription prices and plan picker on product pages.
 *
 * @package Sbply
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shows recurring prices and lets the customer choose between buying once and subscribing.
 */
class Sbply_Product_Display {

	/**
	 * Name of the add-to-cart form field carrying the chosen plan ID ('' = one-time).
	 */
	const FIELD = 'sbply_plan';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_filter( 'woocommerce_get_price_html', array( $this, 'price_html' ), 20, 2 );
		add_filter( 'woocommerce_is_purchasable', array( $this, 'is_purchasable' ), 10, 2 );
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_plan_picker' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Recurring price text wherever the product price is shown.
	 *
	 * Subscription-only products show their plan price instead of the regular
	 * price; products that also sell one-time keep the regular price and add
	 * the subscription offer after it.
	 *
	 * @param string     $price_html Price HTML.
	 * @param WC_Product $product    Product.
	 * @return string
	 */
	public function price_html( $price_html, $product ) {
		if ( ! Sbply_Plans::is_subscription_product( $product ) ) {
			return $price_html;
		}

		$plans    = Sbply_Plans::get_plans( $product );
		$cheapest = Sbply_Plans::get_cheapest_plan( $product );
		$offer    = Sbply_Plans::get_plan_price_html( $product, 1 === count( $plans ) ? $plans[0] : $cheapest );

		if ( count( $plans ) > 1 ) {
			/* translators: %s: lowest recurring price, e.g. "$18.00 / month". */
			$offer = sprintf( esc_html__( 'From %s', 'subscribely-plans-for-woocommerce' ), $offer );
		}

		if ( ! Sbply_Plans::allows_one_time_purchase( $product ) ) {
			return '<span class="sbply-price">' . $offer . '</span>';
		}

		if ( '' === $price_html ) {
			return '<span class="sbply-price">' . $offer . '</span>';
		}

		return $price_html . ' <span class="sbply-price sbply-price--alt">' . sprintf(
			/* translators: %s: recurring price, e.g. "$18.00 / month". */
			esc_html__( 'or subscribe: %s', 'subscribely-plans-for-woocommerce' ),
			$offer
		) . '</span>';
	}

	/**
	 * Subscription-only products are purchasable through their plans even
	 * when the regular price field is left empty.
	 *
	 * @param bool       $purchasable Whether WooCommerce considers it purchasable.
	 * @param WC_Product $product     Product.
	 * @return bool
	 */
	public function is_purchasable( $purchasable, $product ) {
		if ( $purchasable || '' !== $product->get_price() ) {
			return $purchasable;
		}

		if ( ! Sbply_Plans::is_subscription_product( $product ) || Sbply_Plans::allows_one_time_purchase( $product ) ) {
			return $purchasable;
		}

		// Same remaining checks WC_Product::is_purchasable() makes besides the price.
		return $product->exists() && ( 'publish' === $product->get_status() || current_user_can( 'edit_post', $product->get_id() ) );
	}

	/**
	 * Plan choices above the add-to-cart button.
	 */
	public function render_plan_picker() {
		global $product;

		if ( ! Sbply_Plans::is_subscription_product( $product ) ) {
			return;
		}

		$plans    = Sbply_Plans::get_plans( $product );
		$one_time = Sbply_Plans::allows_one_time_purchase( $product );

		// A subscription-only product with one plan needs no choice; the price already says it all.
		if ( ! $one_time && 1 === count( $plans ) ) {
			printf( '<input type="hidden" name="%s" value="%s" />', esc_attr( self::FIELD ), esc_attr( $plans[0]['id'] ) );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only re-selects the customer's choice after a failed add to cart; WooCommerce's add-to-cart form carries no nonce.
		$posted   = isset( $_POST[ self::FIELD ] ) ? sanitize_key( wp_unslash( $_POST[ self::FIELD ] ) ) : null;
		$selected = null !== $posted ? $posted : ( $one_time ? '' : $plans[0]['id'] );

		echo '<fieldset class="sbply-plans">';
		echo '<legend class="sbply-plans__legend">' . esc_html__( 'Purchase options', 'subscribely-plans-for-woocommerce' ) . '</legend>';

		if ( $one_time ) {
			$this->render_option(
				'',
				__( 'One-time purchase', 'subscribely-plans-for-woocommerce' ),
				wc_price( wc_get_price_to_display( $product ) ),
				$selected
			);
		}

		foreach ( $plans as $plan ) {
			$label = '' !== $plan['name'] ? $plan['name'] : __( 'Subscribe', 'subscribely-plans-for-woocommerce' );
			$this->render_option( $plan['id'], $label, Sbply_Plans::get_plan_price_html( $product, $plan ), $selected );
		}

		echo '</fieldset>';
	}

	/**
	 * One radio option.
	 *
	 * @param string $value      Plan ID, or '' for one-time.
	 * @param string $label      Option label.
	 * @param string $price_html Price HTML.
	 * @param string $selected   Selected value.
	 */
	private function render_option( $value, $label, $price_html, $selected ) {
		printf(
			'<label class="sbply-plans__option"><input type="radio" name="%1$s" value="%2$s" %3$s /> <span class="sbply-plans__label">%4$s</span> <span class="sbply-plans__price">%5$s</span></label>',
			esc_attr( self::FIELD ),
			esc_attr( $value ),
			checked( $selected, $value, false ),
			esc_html( $label ),
			wp_kses_post( $price_html )
		);
	}

	/**
	 * Front-end styles on product pages.
	 */
	public function assets() {
		if ( ! is_product() ) {
			return;
		}

		wp_enqueue_style( 'sbply-frontend', SBPLY_URL . 'assets/css/sbply-frontend.css', array(), SBPLY_VERSION );
	}
}
