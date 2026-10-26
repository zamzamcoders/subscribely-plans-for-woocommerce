<?php
/**
 * Database tables and version bookkeeping.
 *
 * @package Sbply
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades the subscription tables.
 */
class Sbply_Install {

	/**
	 * Bump whenever the schema in get_schema() changes; dbDelta() applies the difference.
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Option holding the schema version currently installed.
	 */
	const DB_VERSION_OPTION = 'sbply_db_version';

	/**
	 * Run on activation, and on load when the stored schema version is behind.
	 */
	public static function install() {
		self::create_tables();

		// Autoloaded: maybe_upgrade() reads it on every request.
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, true );
		add_option( 'sbply_install_time', time(), '', false );
	}

	/**
	 * Install or upgrade when the code ships a newer schema than the database has.
	 *
	 * Activation hooks do not run on plugin updates, so this is what applies a
	 * schema change for a site that updated in place.
	 */
	public static function maybe_upgrade() {
		if ( version_compare( (string) get_option( self::DB_VERSION_OPTION, '0' ), self::DB_VERSION, '<' ) ) {
			self::install();
		}
	}

	/**
	 * Create or update the tables via dbDelta().
	 */
	private static function create_tables() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( self::get_schema() );
	}

	/**
	 * Table definitions.
	 *
	 * order_item_id is nullable on purpose: it is unique for subscriptions that
	 * came from a checkout line item, while subscriptions created another way
	 * (by an admin, by an import) leave it NULL, which a UNIQUE index allows
	 * any number of times.
	 *
	 * @return string
	 */
	private static function get_schema() {
		global $wpdb;

		$collate = $wpdb->get_charset_collate();

		return "CREATE TABLE {$wpdb->prefix}sbply_subscriptions (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  status varchar(20) NOT NULL default 'pending',
  customer_id bigint(20) unsigned NOT NULL default 0,
  parent_order_id bigint(20) unsigned NOT NULL default 0,
  order_item_id bigint(20) unsigned NULL default NULL,
  product_id bigint(20) unsigned NOT NULL default 0,
  variation_id bigint(20) unsigned NOT NULL default 0,
  quantity int(11) NOT NULL default 1,
  plan_id varchar(64) NOT NULL default '',
  plan_name varchar(200) NOT NULL default '',
  billing_period varchar(10) NOT NULL default 'month',
  billing_interval smallint(5) unsigned NOT NULL default 1,
  billing_length smallint(5) unsigned NOT NULL default 0,
  recurring_amount decimal(26,8) NOT NULL default 0,
  recurring_tax decimal(26,8) NOT NULL default 0,
  currency varchar(10) NOT NULL default '',
  date_created_gmt datetime NULL default NULL,
  date_modified_gmt datetime NULL default NULL,
  start_date_gmt datetime NULL default NULL,
  next_payment_date_gmt datetime NULL default NULL,
  end_date_gmt datetime NULL default NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY order_item_id (order_item_id),
  KEY customer_id (customer_id),
  KEY parent_order_id (parent_order_id),
  KEY status_next_payment (status,next_payment_date_gmt)
) $collate;
CREATE TABLE {$wpdb->prefix}sbply_subscriptionmeta (
  meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  sbply_subscription_id bigint(20) unsigned NOT NULL default 0,
  meta_key varchar(255) NULL default NULL,
  meta_value longtext NULL,
  PRIMARY KEY  (meta_id),
  KEY sbply_subscription_id (sbply_subscription_id),
  KEY meta_key (meta_key(191))
) $collate;";
	}
}
