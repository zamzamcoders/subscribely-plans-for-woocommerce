<?php
/**
 * "Subscription" tab in the product data box.
 *
 * @package Sbply
 */

defined( 'ABSPATH' ) || exit;

/**
 * Product editor settings: enable subscriptions, purchase options and plans.
 */
class Sbply_Admin_Product {

	/**
	 * Nonce action/field for the tab.
	 */
	const NONCE = 'sbply_product_nonce';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * CSS classes that show the tab only for supported product types.
	 *
	 * @return string[]
	 */
	private function show_if_classes() {
		return array_map(
			function ( $type ) {
				return 'show_if_' . sanitize_html_class( $type );
			},
			Sbply_Plans::get_supported_product_types()
		);
	}

	/**
	 * Register the tab.
	 *
	 * @param array $tabs Product data tabs.
	 * @return array
	 */
	public function add_tab( $tabs ) {
		$tabs['sbply'] = array(
			'label'    => __( 'Subscription', 'subscribely-plans-for-woocommerce' ),
			'target'   => 'sbply_product_data',
			'class'    => $this->show_if_classes(),
			'priority' => 25,
		);

		return $tabs;
	}

	/**
	 * Render the tab panel.
	 */
	public function render_panel() {
		global $product_object;

		$product = $product_object instanceof WC_Product ? $product_object : null;
		$raw     = $product ? $product->get_meta( Sbply_Plans::META_PLANS ) : array();
		$plans   = array();

		foreach ( is_array( $raw ) ? $raw : array() as $row ) {
			$plan = Sbply_Plans::normalize_plan( $row );
			if ( $plan ) {
				$plans[] = $plan;
			}
		}

		echo '<div id="sbply_product_data" class="panel woocommerce_options_panel hidden">';
		wp_nonce_field( self::NONCE, self::NONCE );

		echo '<div class="options_group">';

		woocommerce_wp_checkbox(
			array(
				'id'          => 'sbply_enabled',
				'label'       => __( 'Sell on subscription', 'subscribely-plans-for-woocommerce' ),
				'description' => __( 'Customers pay for this product on a recurring schedule.', 'subscribely-plans-for-woocommerce' ),
				'value'       => $product ? $product->get_meta( Sbply_Plans::META_ENABLED ) : '',
				'cbvalue'     => 'yes',
			)
		);

		woocommerce_wp_select(
			array(
				'id'          => 'sbply_purchase_type',
				'label'       => __( 'Purchase options', 'subscribely-plans-for-woocommerce' ),
				'value'       => $product && Sbply_Plans::allows_one_time_purchase( $product ) ? 'both' : 'subscription',
				'options'     => array(
					'subscription' => __( 'Subscription only', 'subscribely-plans-for-woocommerce' ),
					'both'         => __( 'One-time purchase or subscription', 'subscribely-plans-for-woocommerce' ),
				),
				'desc_tip'    => true,
				'description' => __( 'With "One-time purchase or subscription", the regular price is charged for one-time purchases.', 'subscribely-plans-for-woocommerce' ),
			)
		);

		echo '</div>';

		echo '<div class="options_group sbply-plans-editor">';
		echo '<h4 class="sbply-plans-editor__title">' . esc_html__( 'Subscription plans', 'subscribely-plans-for-woocommerce' ) . '</h4>';
		echo '<p class="sbply-plans-editor__help">' . esc_html__( 'Each plan sets the recurring price and how often it is charged. The first plan is selected by default.', 'subscribely-plans-for-woocommerce' ) . '</p>';

		echo '<table class="widefat sbply-plans-table"><thead><tr>';
		echo '<th>' . esc_html__( 'Plan name', 'subscribely-plans-for-woocommerce' ) . '</th>';
		/* translators: %s: currency symbol. */
		echo '<th>' . esc_html( sprintf( __( 'Price (%s)', 'subscribely-plans-for-woocommerce' ), get_woocommerce_currency_symbol() ) ) . '</th>';
		echo '<th>' . esc_html__( 'Billed every', 'subscribely-plans-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Ends after', 'subscribely-plans-for-woocommerce' ) . '</th>';
		echo '<th class="sbply-plans-table__actions"><span class="screen-reader-text">' . esc_html__( 'Actions', 'subscribely-plans-for-woocommerce' ) . '</span></th>';
		echo '</tr></thead><tbody class="sbply-plans-table__rows">';

		foreach ( $plans as $index => $plan ) {
			$this->render_plan_row( (string) $index, $plan );
		}

		echo '</tbody></table>';

		echo '<p class="sbply-plans-editor__empty"' . ( $plans ? ' hidden' : '' ) . '>' . esc_html__( 'No plans yet. Add at least one plan to sell this product on subscription.', 'subscribely-plans-for-woocommerce' ) . '</p>';
		echo '<p><button type="button" class="button sbply-add-plan">' . esc_html__( 'Add plan', 'subscribely-plans-for-woocommerce' ) . '</button></p>';

		echo '<script type="text/html" id="tmpl-sbply-plan-row">';
		$this->render_plan_row(
			'__INDEX__',
			array(
				'id'       => '',
				'name'     => '',
				'price'    => '',
				'interval' => 1,
				'period'   => 'month',
				'length'   => 0,
			)
		);
		echo '</script>';

		echo '</div></div>';
	}

	/**
	 * One editable plan row.
	 *
	 * @param string $index Row index used in field names.
	 * @param array  $plan  Plan values.
	 */
	private function render_plan_row( $index, $plan ) {
		$name = 'sbply_plans[' . $index . ']';

		echo '<tr class="sbply-plan-row">';

		echo '<td>';
		printf( '<input type="hidden" name="%s[id]" value="%s" />', esc_attr( $name ), esc_attr( $plan['id'] ) );
		printf(
			'<input type="text" name="%1$s[name]" value="%2$s" placeholder="%3$s" aria-label="%4$s" maxlength="200" />',
			esc_attr( $name ),
			esc_attr( $plan['name'] ),
			esc_attr__( 'e.g. Monthly', 'subscribely-plans-for-woocommerce' ),
			esc_attr__( 'Plan name', 'subscribely-plans-for-woocommerce' )
		);
		echo '</td>';

		echo '<td>';
		printf(
			'<input type="text" class="wc_input_price" name="%1$s[price]" value="%2$s" aria-label="%3$s" />',
			esc_attr( $name ),
			esc_attr( '' === $plan['price'] ? '' : wc_format_localized_price( $plan['price'] ) ),
			esc_attr__( 'Recurring price', 'subscribely-plans-for-woocommerce' )
		);
		echo '</td>';

		echo '<td class="sbply-plan-row__billing">';
		printf( '<select name="%1$s[interval]" aria-label="%2$s">', esc_attr( $name ), esc_attr__( 'Billing interval', 'subscribely-plans-for-woocommerce' ) );
		for ( $i = 1; $i <= Sbply_Plans::MAX_INTERVAL; $i++ ) {
			printf( '<option value="%1$d" %2$s>%1$d</option>', (int) $i, selected( (int) $plan['interval'], $i, false ) );
		}
		echo '</select>';

		printf( '<select name="%1$s[period]" aria-label="%2$s">', esc_attr( $name ), esc_attr__( 'Billing period', 'subscribely-plans-for-woocommerce' ) );
		foreach ( sbply_get_billing_periods() as $value => $label ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $plan['period'], $value, false ), esc_html( $label ) );
		}
		echo '</select>';
		echo '</td>';

		echo '<td>';
		printf( '<select name="%1$s[length]" aria-label="%2$s">', esc_attr( $name ), esc_attr__( 'Subscription length', 'subscribely-plans-for-woocommerce' ) );
		printf( '<option value="0" %1$s>%2$s</option>', selected( (int) $plan['length'], 0, false ), esc_html__( 'Never (until cancelled)', 'subscribely-plans-for-woocommerce' ) );
		for ( $i = 1; $i <= Sbply_Plans::MAX_LENGTH; $i++ ) {
			printf(
				'<option value="%1$d" %2$s>%3$s</option>',
				(int) $i,
				selected( (int) $plan['length'], $i, false ),
				/* translators: %s: number of payments. */
				esc_html( sprintf( _n( '%s payment', '%s payments', $i, 'subscribely-plans-for-woocommerce' ), number_format_i18n( $i ) ) )
			);
		}
		echo '</select>';
		echo '</td>';

		printf(
			'<td class="sbply-plans-table__actions"><button type="button" class="button-link button-link-delete sbply-remove-plan" aria-label="%s">&times;</button></td>',
			esc_attr__( 'Remove plan', 'subscribely-plans-for-woocommerce' )
		);

		echo '</tr>';
	}

	/**
	 * Save the tab.
	 *
	 * @param WC_Product $product Product being saved.
	 */
	public function save( $product ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_product', $product->get_id() ) ) {
			return;
		}

		$enabled = isset( $_POST['sbply_enabled'] ) && 'yes' === sanitize_key( wp_unslash( $_POST['sbply_enabled'] ) );
		$type    = isset( $_POST['sbply_purchase_type'] ) && 'both' === sanitize_key( wp_unslash( $_POST['sbply_purchase_type'] ) ) ? 'both' : 'subscription';

		// Each field is sanitized and validated by Sbply_Plans::normalize_plan() below.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$rows  = isset( $_POST['sbply_plans'] ) && is_array( $_POST['sbply_plans'] ) ? wp_unslash( $_POST['sbply_plans'] ) : array();
		$plans = array();
		$ids   = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$row['price'] = isset( $row['price'] ) ? wc_format_decimal( $row['price'] ) : '';

			// Keep a row's ID across saves so cart items and subscriptions still match it.
			$id = isset( $row['id'] ) ? sanitize_key( $row['id'] ) : '';
			if ( '' === $id || isset( $ids[ $id ] ) ) {
				$id = Sbply_Plans::generate_plan_id();
			}
			$row['id'] = $id;

			$plan = Sbply_Plans::normalize_plan( $row );
			if ( ! $plan ) {
				continue;
			}

			$ids[ $plan['id'] ] = true;
			$plans[]            = $plan;
		}

		if ( $enabled && ! $plans ) {
			WC_Admin_Meta_Boxes::add_error( __( 'Subscription was not enabled: add at least one plan with a price.', 'subscribely-plans-for-woocommerce' ) );
			$enabled = false;
		}

		$product->update_meta_data( Sbply_Plans::META_ENABLED, $enabled ? 'yes' : 'no' );
		$product->update_meta_data( Sbply_Plans::META_PURCHASE_TYPE, $type );
		$product->update_meta_data( Sbply_Plans::META_PLANS, $plans );
	}

	/**
	 * Assets on the product editor.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function assets( $hook_suffix ) {
		$screen = get_current_screen();

		if ( ! $screen || 'product' !== $screen->id || ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		wp_enqueue_style( 'sbply-admin', SBPLY_URL . 'assets/css/sbply-admin.css', array(), SBPLY_VERSION );
		wp_enqueue_script( 'sbply-admin-product', SBPLY_URL . 'assets/js/sbply-admin-product.js', array(), SBPLY_VERSION, true );
	}
}
