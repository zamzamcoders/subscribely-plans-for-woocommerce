<?php
/**
 * Subscription data store (custom tables).
 *
 * @package Sbply
 */

defined( 'ABSPATH' ) || exit;

/**
 * Persists Sbply_Subscription objects in {prefix}sbply_subscriptions,
 * with meta in {prefix}sbply_subscriptionmeta via the WordPress metadata API.
 */
class Sbply_Subscription_Data_Store extends WC_Data_Store_WP implements WC_Object_Data_Store_Interface {

	/**
	 * Meta type for the metadata API (add_metadata( 'sbply_subscription', … )).
	 *
	 * @var string
	 */
	protected $meta_type = 'sbply_subscription';

	/**
	 * Props stored as plain columns of the same name.
	 *
	 * @var string[]
	 */
	private $columns = array(
		'status',
		'customer_id',
		'parent_order_id',
		'order_item_id',
		'product_id',
		'variation_id',
		'quantity',
		'plan_id',
		'plan_name',
		'billing_period',
		'billing_interval',
		'billing_length',
		'recurring_amount',
		'recurring_tax',
		'currency',
	);

	/**
	 * Date props, stored in "{$prop}_gmt" columns as UTC.
	 *
	 * @var string[]
	 */
	private $date_columns = array(
		'date_created',
		'date_modified',
		'start_date',
		'next_payment_date',
		'end_date',
	);

	/**
	 * Main table name.
	 *
	 * @return string
	 */
	public static function get_table() {
		global $wpdb;
		return $wpdb->prefix . 'sbply_subscriptions';
	}

	/**
	 * Meta table layout, for WC_Data_Store_WP::read_meta().
	 *
	 * The parent derives "{prefix}woocommerce_{type}meta" for non-core types,
	 * which is not where this plugin keeps its meta.
	 *
	 * @return array
	 */
	protected function get_db_info() {
		global $wpdb;

		return array(
			'table'           => $wpdb->prefix . 'sbply_subscriptionmeta',
			'object_id_field' => 'sbply_subscription_id',
			'meta_id_field'   => 'meta_id',
		);
	}

	/**
	 * Create a subscription.
	 *
	 * @param Sbply_Subscription $subscription Subscription.
	 * @throws Exception When the row cannot be inserted.
	 */
	public function create( &$subscription ) {
		global $wpdb;

		if ( ! $subscription->get_date_created( 'edit' ) ) {
			$subscription->set_date_created( time() );
		}
		$subscription->set_date_modified( time() );

		$row = $this->get_row_data( $subscription );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table, written through $wpdb->insert().
		$inserted = $wpdb->insert( self::get_table(), $row );
		if ( ! $inserted ) {
			throw new Exception( esc_html__( 'Could not create the subscription.', 'subscribely-plans-for-woocommerce' ) );
		}

		$subscription->set_id( (int) $wpdb->insert_id );
		$subscription->save_meta_data();
		$subscription->apply_changes();

		/**
		 * Fires after a subscription is created.
		 *
		 * @param int                 $subscription_id Subscription ID.
		 * @param Sbply_Subscription $subscription    Subscription.
		 */
		do_action( 'sbply/subscription_created', $subscription->get_id(), $subscription );
	}

	/**
	 * Read a subscription.
	 *
	 * @param Sbply_Subscription $subscription Subscription with its ID set.
	 * @throws Exception When it does not exist.
	 */
	public function read( &$subscription ) {
		global $wpdb;

		$subscription->set_defaults();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', self::get_table(), $subscription->get_id() ) );

		if ( ! $row ) {
			throw new Exception( esc_html__( 'Invalid subscription.', 'subscribely-plans-for-woocommerce' ) );
		}

		$props = array();
		foreach ( $this->columns as $column ) {
			$props[ $column ] = $row->$column;
		}
		foreach ( $this->date_columns as $prop ) {
			$value          = $row->{$prop . '_gmt'};
			$props[ $prop ] = $value ? wc_string_to_timestamp( $value ) : null;
		}

		$subscription->set_props( $props );
		$subscription->read_meta_data();
		$subscription->set_object_read( true );
	}

	/**
	 * Update a subscription.
	 *
	 * @param Sbply_Subscription $subscription Subscription.
	 */
	public function update( &$subscription ) {
		global $wpdb;

		$changes = $subscription->get_changes();

		if ( $changes ) {
			$subscription->set_date_modified( time() );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
			$wpdb->update( self::get_table(), $this->get_row_data( $subscription ), array( 'id' => $subscription->get_id() ) );
		}

		$subscription->save_meta_data();
		$subscription->apply_changes();

		if ( $changes ) {
			/**
			 * Fires after a subscription is updated.
			 *
			 * @param int                 $subscription_id Subscription ID.
			 * @param Sbply_Subscription $subscription    Subscription.
			 * @param array               $changes         Changed props.
			 */
			do_action( 'sbply/subscription_updated', $subscription->get_id(), $subscription, $changes );
		}
	}

	/**
	 * Delete a subscription and its meta.
	 *
	 * @param Sbply_Subscription $subscription Subscription.
	 * @param array               $args         Unused; kept for the interface.
	 */
	public function delete( &$subscription, $args = array() ) {
		global $wpdb;

		$id = $subscription->get_id();
		if ( ! $id ) {
			return;
		}

		/**
		 * Fires before a subscription is deleted.
		 *
		 * @param int                 $subscription_id Subscription ID.
		 * @param Sbply_Subscription $subscription    Subscription.
		 */
		do_action( 'sbply/before_delete_subscription', $id, $subscription );

		$db_info = $this->get_db_info();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tables.
		$wpdb->delete( self::get_table(), array( 'id' => $id ) );
		$wpdb->delete( $db_info['table'], array( $db_info['object_id_field'] => $id ) );
		// phpcs:enable

		wp_cache_delete( $id, $this->meta_type . '_meta' );
		$subscription->set_id( 0 );

		/**
		 * Fires after a subscription is deleted.
		 *
		 * @param int $subscription_id Subscription ID.
		 */
		do_action( 'sbply/subscription_deleted', $id );
	}

	/**
	 * Column values for insert/update.
	 *
	 * @param Sbply_Subscription $subscription Subscription.
	 * @return array
	 */
	private function get_row_data( $subscription ) {
		$row = array();

		foreach ( $this->columns as $column ) {
			$row[ $column ] = $subscription->{"get_{$column}"}( 'edit' );
		}

		// No line item → NULL, so the UNIQUE index only applies to real line items.
		if ( empty( $row['order_item_id'] ) ) {
			$row['order_item_id'] = null;
		}

		foreach ( $this->date_columns as $prop ) {
			$date                  = $subscription->{"get_{$prop}"}( 'edit' );
			$row[ $prop . '_gmt' ] = $date ? gmdate( 'Y-m-d H:i:s', $date->getTimestamp() ) : null;
		}

		return $row;
	}

	/**
	 * Find subscription IDs.
	 *
	 * @param array $args {
	 *     Optional. Query arguments.
	 *
	 *     @type string|string[] $status          Status slug(s). Default all.
	 *     @type int             $customer_id     Customer user ID.
	 *     @type int             $parent_order_id Parent order ID.
	 *     @type int             $order_item_id   Parent order line item ID.
	 *     @type int[]           $include         Only these subscription IDs.
	 *     @type string          $orderby         id|date_created|next_payment_date. Default id.
	 *     @type string          $order           ASC|DESC. Default DESC.
	 *     @type int             $limit           Max rows; -1 for all. Default -1.
	 *     @type int             $offset          Rows to skip. Default 0.
	 * }
	 * @return int[]
	 */
	public function query( $args = array() ) {
		global $wpdb;

		list( $where, $values ) = $this->build_where( $args );

		$orderby_map = array(
			'id'                => 'id',
			'date_created'      => 'date_created_gmt',
			'next_payment_date' => 'next_payment_date_gmt',
		);
		$orderby     = isset( $args['orderby'], $orderby_map[ $args['orderby'] ] ) ? $orderby_map[ $args['orderby'] ] : 'id';
		$order       = isset( $args['order'] ) && 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$limit       = isset( $args['limit'] ) ? (int) $args['limit'] : -1;
		$offset      = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;

		// Placeholder order: table, WHERE values, ORDER BY column, then LIMIT/OFFSET.
		$sql    = "SELECT id FROM %i WHERE {$where} ORDER BY %i {$order}";
		$values = array_merge( array( self::get_table() ), $values, array( $orderby ) );

		if ( $limit > 0 ) {
			$sql     .= ' LIMIT %d OFFSET %d';
			$values[] = $limit;
			$values[] = $offset;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; $where holds only fixed fragments and placeholders, $order is ASC|DESC.
		$ids = $wpdb->get_col( $wpdb->prepare( $sql, $values ) );

		return array_map( 'intval', $ids );
	}

	/**
	 * Count subscriptions matching the same arguments as query().
	 *
	 * @param array $args Query arguments (limit/offset/order are ignored).
	 * @return int
	 */
	public function count( $args = array() ) {
		global $wpdb;

		list( $where, $values ) = $this->build_where( $args );

		$sql    = "SELECT COUNT(*) FROM %i WHERE {$where}";
		$values = array_merge( array( self::get_table() ), $values );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; $where holds only fixed fragments and placeholders.
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) );
	}

	/**
	 * Number of subscriptions per status.
	 *
	 * @return array<string,int>
	 */
	public function count_by_status() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT status, COUNT(*) AS total FROM %i GROUP BY status', self::get_table() ) );

		$counts = array();
		foreach ( $rows as $row ) {
			$counts[ $row->status ] = (int) $row->total;
		}

		return $counts;
	}

	/**
	 * WHERE clause and placeholder values for query()/count().
	 *
	 * @param array $args Query arguments.
	 * @return array{0:string,1:array}
	 */
	private function build_where( $args ) {
		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$statuses = array_map( 'sanitize_key', (array) $args['status'] );
			$where[]  = 'status IN (' . implode( ',', array_fill( 0, count( $statuses ), '%s' ) ) . ')';
			$values   = array_merge( $values, $statuses );
		}

		foreach ( array( 'customer_id', 'parent_order_id', 'order_item_id' ) as $column ) {
			if ( isset( $args[ $column ] ) && '' !== $args[ $column ] ) {
				$where[]  = "{$column} = %d";
				$values[] = absint( $args[ $column ] );
			}
		}

		if ( isset( $args['include'] ) ) {
			$ids = array_filter( array_map( 'absint', (array) $args['include'] ) );
			if ( ! $ids ) {
				$where[] = '1=0';
			} else {
				$where[] = 'id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')';
				$values  = array_merge( $values, $ids );
			}
		}

		return array( implode( ' AND ', $where ), $values );
	}
}
