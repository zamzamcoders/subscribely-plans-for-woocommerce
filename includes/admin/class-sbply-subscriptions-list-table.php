<?php
/**
 * Subscriptions list table.
 *
 * @package Sbply
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Lists subscriptions with status views, search and pagination.
 */
class Sbply_Subscriptions_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'subscription',
				'plural'   => 'subscriptions',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Status filter from the request, or '' for all.
	 *
	 * @return string
	 */
	private function get_current_status() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter.
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		return array_key_exists( $status, sbply_get_subscription_statuses() ) ? $status : '';
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'subscription' => __( 'Subscription', 'subscribely-plans-for-woocommerce' ),
			'status'       => __( 'Status', 'subscribely-plans-for-woocommerce' ),
			'customer'     => __( 'Customer', 'subscribely-plans-for-woocommerce' ),
			'product'      => __( 'Product', 'subscribely-plans-for-woocommerce' ),
			'recurring'    => __( 'Recurring total', 'subscribely-plans-for-woocommerce' ),
			'start_date'   => __( 'Start date', 'subscribely-plans-for-woocommerce' ),
			'next_payment' => __( 'Next payment', 'subscribely-plans-for-woocommerce' ),
			'end_date'     => __( 'End date', 'subscribely-plans-for-woocommerce' ),
			'parent_order' => __( 'Order', 'subscribely-plans-for-woocommerce' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'subscription' => array( 'id', true ),
			'next_payment' => array( 'next_payment_date', false ),
		);
	}

	/**
	 * Status views with counts.
	 *
	 * @return array
	 */
	protected function get_views() {
		$store   = WC_Data_Store::load( 'sbply-subscription' );
		$counts  = $store->count_by_status();
		$current = $this->get_current_status();
		$base    = admin_url( 'admin.php?page=' . Sbply_Admin_Subscriptions::PAGE );
		$views   = array();

		$views['all'] = sprintf(
			'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
			esc_url( $base ),
			'' === $current ? ' class="current" aria-current="page"' : '',
			esc_html__( 'All', 'subscribely-plans-for-woocommerce' ),
			esc_html( number_format_i18n( array_sum( $counts ) ) )
		);

		foreach ( sbply_get_subscription_statuses() as $status => $label ) {
			if ( empty( $counts[ $status ] ) ) {
				continue;
			}

			$views[ $status ] = sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
				esc_url( add_query_arg( 'status', $status, $base ) ),
				$status === $current ? ' class="current" aria-current="page"' : '',
				esc_html( $label ),
				esc_html( number_format_i18n( $counts[ $status ] ) )
			);
		}

		return $views;
	}

	/**
	 * Query arguments from the search box: a subscription ID ("123" or "#123"),
	 * or a customer email/username.
	 *
	 * @return array|null Extra query args, or null when the search cannot match anything.
	 */
	private function get_search_args() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list search.
		$search = isset( $_GET['s'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) : '';

		if ( '' === $search ) {
			return array();
		}

		$number = ltrim( $search, '#' );
		if ( ctype_digit( $number ) ) {
			return array( 'include' => array( (int) $number ) );
		}

		$user = is_email( $search ) ? get_user_by( 'email', $search ) : get_user_by( 'login', $search );

		return $user ? array( 'customer_id' => $user->ID ) : null;
	}

	/**
	 * Load the rows for the current page.
	 */
	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'subscription' );

		$per_page = $this->get_items_per_page( 'sbply_subscriptions_per_page', 20 );
		$search   = $this->get_search_args();

		if ( null === $search ) {
			$this->items = array();
			$this->set_pagination_args(
				array(
					'total_items' => 0,
					'per_page'    => $per_page,
				)
			);
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list sorting.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'id';
		$order   = isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) ? 'ASC' : 'DESC';
		// phpcs:enable

		$args = array_merge(
			$search,
			array(
				'status'  => $this->get_current_status(),
				'orderby' => $orderby,
				'order'   => $order,
			)
		);

		$store = WC_Data_Store::load( 'sbply-subscription' );
		$total = $store->count( $args );

		$this->items = sbply_get_subscriptions(
			array_merge(
				$args,
				array(
					'limit'  => $per_page,
					'offset' => ( $this->get_pagenum() - 1 ) * $per_page,
				)
			)
		);

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Message when there are no rows.
	 */
	public function no_items() {
		esc_html_e( 'No subscriptions found.', 'subscribely-plans-for-woocommerce' );
	}

	/**
	 * Subscription ID column.
	 *
	 * @param Sbply_Subscription $item Subscription.
	 * @return string
	 */
	protected function column_subscription( $item ) {
		$out = '<strong>#' . esc_html( (string) $item->get_id() ) . '</strong>';

		if ( '' !== $item->get_plan_name() ) {
			$out .= '<br /><span class="description">' . esc_html( $item->get_plan_name() ) . '</span>';
		}

		return $out;
	}

	/**
	 * Status column.
	 *
	 * @param Sbply_Subscription $item Subscription.
	 * @return string
	 */
	protected function column_status( $item ) {
		return Sbply_Admin_Subscriptions::status_badge( $item->get_status() );
	}

	/**
	 * Customer column.
	 *
	 * @param Sbply_Subscription $item Subscription.
	 * @return string
	 */
	protected function column_customer( $item ) {
		$user = $item->get_customer_id() ? get_userdata( $item->get_customer_id() ) : false;

		if ( ! $user ) {
			$order = $item->get_parent_order();
			return $order ? esc_html( $order->get_formatted_billing_full_name() ) . '<br /><span class="description">' . esc_html__( 'Guest', 'subscribely-plans-for-woocommerce' ) . '</span>' : '&ndash;';
		}

		$name = trim( $user->first_name . ' ' . $user->last_name );

		return sprintf(
			'<a href="%1$s">%2$s</a><br /><span class="description">%3$s</span>',
			esc_url( get_edit_user_link( $user->ID ) ),
			esc_html( '' !== $name ? $name : $user->display_name ),
			esc_html( $user->user_email )
		);
	}

	/**
	 * Product column.
	 *
	 * @param Sbply_Subscription $item Subscription.
	 * @return string
	 */
	protected function column_product( $item ) {
		$product_id = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();
		$product    = wc_get_product( $product_id );
		$quantity   = $item->get_quantity() > 1 ? ' &times; ' . esc_html( (string) $item->get_quantity() ) : '';

		if ( ! $product ) {
			return esc_html__( 'Deleted product', 'subscribely-plans-for-woocommerce' ) . $quantity;
		}

		$edit = get_edit_post_link( $item->get_product_id() );

		return ( $edit ? '<a href="' . esc_url( $edit ) . '">' . esc_html( $product->get_name() ) . '</a>' : esc_html( $product->get_name() ) ) . $quantity;
	}

	/**
	 * Recurring total column.
	 *
	 * @param Sbply_Subscription $item Subscription.
	 * @return string
	 */
	protected function column_recurring( $item ) {
		return wp_kses_post( $item->get_formatted_recurring_total() );
	}

	/**
	 * Start date column.
	 *
	 * @param Sbply_Subscription $item Subscription.
	 * @return string
	 */
	protected function column_start_date( $item ) {
		return Sbply_Admin_Subscriptions::format_date( $item->get_start_date() );
	}

	/**
	 * Next payment column.
	 *
	 * @param Sbply_Subscription $item Subscription.
	 * @return string
	 */
	protected function column_next_payment( $item ) {
		return Sbply_Admin_Subscriptions::format_date( $item->get_next_payment_date() );
	}

	/**
	 * End date column.
	 *
	 * @param Sbply_Subscription $item Subscription.
	 * @return string
	 */
	protected function column_end_date( $item ) {
		if ( ! $item->get_end_date() && $item->has_status( array( 'active', 'pending', 'on-hold' ) ) ) {
			return esc_html__( 'When cancelled', 'subscribely-plans-for-woocommerce' );
		}

		return Sbply_Admin_Subscriptions::format_date( $item->get_end_date() );
	}

	/**
	 * Parent order column.
	 *
	 * @param Sbply_Subscription $item Subscription.
	 * @return string
	 */
	protected function column_parent_order( $item ) {
		$order = $item->get_parent_order();

		if ( ! $order ) {
			return '&ndash;';
		}

		return sprintf(
			'<a href="%1$s">#%2$s</a>',
			esc_url( $order->get_edit_order_url() ),
			esc_html( $order->get_order_number() )
		);
	}
}
