<?php
/**
 * WooCommerce → Subscriptions admin screen.
 *
 * @package Sbply
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Subscriptions screen and renders the list.
 */
class Sbply_Admin_Subscriptions {

	/**
	 * Admin page slug.
	 */
	const PAGE = 'sbply-subscriptions';

	/**
	 * Hook suffix returned by add_submenu_page().
	 *
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * List table, built on load-{page} so screen options and columns exist early.
	 *
	 * @var Sbply_Subscriptions_List_Table|null
	 */
	private $table = null;

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 60 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Add WooCommerce → Subscriptions.
	 */
	public function add_menu() {
		$this->hook_suffix = add_submenu_page(
			'woocommerce',
			__( 'Subscriptions', 'subscribely-plans-for-woocommerce' ),
			__( 'Subscriptions', 'subscribely-plans-for-woocommerce' ),
			'manage_woocommerce',
			self::PAGE,
			array( $this, 'render' )
		);

		if ( $this->hook_suffix ) {
			add_action( 'load-' . $this->hook_suffix, array( $this, 'load' ) );
		}
	}

	/**
	 * Prepare the list table before output starts.
	 */
	public function load() {
		require_once SBPLY_PATH . 'includes/admin/class-sbply-subscriptions-list-table.php';

		add_screen_option(
			'per_page',
			array(
				'default' => 20,
				'option'  => 'sbply_subscriptions_per_page',
			)
		);

		$this->table = new Sbply_Subscriptions_List_Table();
	}

	/**
	 * Render the screen.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to view subscriptions.', 'subscribely-plans-for-woocommerce' ), '', array( 'response' => 403 ) );
		}

		$this->table->prepare_items();

		echo '<div class="wrap sbply-subscriptions">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Subscriptions', 'subscribely-plans-for-woocommerce' ) . '</h1>';
		echo '<hr class="wp-header-end" />';

		$this->table->views();

		echo '<form method="get">';
		printf( '<input type="hidden" name="page" value="%s" />', esc_attr( self::PAGE ) );
		$this->table->search_box( __( 'Search subscriptions', 'subscribely-plans-for-woocommerce' ), 'sbply-subscription' );
		$this->table->display();
		echo '</form>';

		echo '</div>';
	}

	/**
	 * Styles on the Subscriptions screen and order screens.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function assets( $hook_suffix ) {
		$order_screens = function_exists( 'wc_get_page_screen_id' ) ? array( wc_get_page_screen_id( 'shop-order' ), 'shop_order' ) : array( 'shop_order' );
		$screen        = get_current_screen();

		if ( $hook_suffix === $this->hook_suffix || ( $screen && in_array( $screen->id, $order_screens, true ) ) ) {
			wp_enqueue_style( 'sbply-admin', SBPLY_URL . 'assets/css/sbply-admin.css', array(), SBPLY_VERSION );
		}
	}

	/**
	 * Status badge HTML.
	 *
	 * @param string $status Status slug.
	 * @return string
	 */
	public static function status_badge( $status ) {
		return sprintf(
			'<mark class="sbply-status sbply-status--%1$s"><span>%2$s</span></mark>',
			esc_attr( sanitize_html_class( $status ) ),
			esc_html( sbply_get_status_label( $status ) )
		);
	}

	/**
	 * Date in the site's date format, or a dash.
	 *
	 * @param WC_DateTime|null $date Date.
	 * @return string
	 */
	public static function format_date( $date ) {
		return $date ? esc_html( wc_format_datetime( $date ) ) : '&ndash;';
	}
}

/**
 * Persist the per-page screen option.
 *
 * @param mixed  $status Value to save, or false to skip.
 * @param string $option Option name.
 * @param mixed  $value  Submitted value.
 * @return mixed
 */
function sbply_set_screen_option( $status, $option, $value ) {
	return 'sbply_subscriptions_per_page' === $option ? min( 200, max( 1, absint( $value ) ) ) : $status;
}

add_filter( 'set_screen_option_sbply_subscriptions_per_page', 'sbply_set_screen_option', 10, 3 );
