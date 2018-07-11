<?php
/**
 * Contains code for the admin order page class.
 *
 * @package     Boxtal\BoxtalWoocommerce\Tracking
 */

namespace Boxtal\BoxtalWoocommerce\Tracking;

use Boxtal\BoxtalWoocommerce\Util\Order_Util;

/**
 * Admin_Order_Page class.
 *
 * Adds tracking info to order page.
 *
 * @class       Admin_Order_Page
 * @package     Boxtal\BoxtalWoocommerce\Tracking
 * @category    Class
 * @author      API Boxtal
 */
class Admin_Order_Page {

	/**
	 * Construct function.
	 *
	 * @param array $plugin plugin array.
	 * @void
	 */
	public function __construct( $plugin ) {
		$this->plugin_url     = $plugin['url'];
		$this->plugin_version = $plugin['version'];
	}

	/**
	 * Run class.
	 *
	 * @void
	 */
	public function run() {
		$controller = new Controller(
			array(
				'url'     => $this->plugin_url,
				'version' => $this->plugin_version,
			)
		);
		add_action( 'admin_enqueue_scripts', array( $controller, 'tracking_styles' ) );
		add_filter( 'add_meta_boxes_shop_order', array( $this, 'add_tracking_to_admin_order_page' ), 10, 2 );
		add_filter( 'woocommerce_admin_order_preview_end', array( $this, 'order_view_modal' ) );
	}

	/**
	 * Add tracking info to front order page.
	 *
	 * @void
	 */
	public function add_tracking_to_admin_order_page() {
		if ( function_exists( 'wc_get_order_types' ) ) {
			foreach ( wc_get_order_types( 'order-meta-boxes' ) as $type ) {
				add_meta_box( 'boxtal-order-tracking', __( 'Boxtal - Shipment tracking', 'boxtal-woocommerce' ), array( $this, 'order_edit_page' ), $type, 'normal', 'high' );
			}
		} else {
			add_meta_box( 'boxtal-order-tracking', __( 'Boxtal - Shipment tracking', 'boxtal-woocommerce' ), array( $this, 'order_edit_page' ), 'shop_order', 'normal', 'high' );
		}
	}

	/**
	 * Order edit page output.
	 *
	 * @void
	 */
	public function order_edit_page() {
		$controller = new Controller(
			array(
				'url'     => $this->plugin_url,
				'version' => $this->plugin_version,
			)
		);
		$tracking   = $controller->get_order_tracking( Order_Util::get_id( Order_Util::admin_get_order() ) );
		include realpath( plugin_dir_path( __DIR__ ) ) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'html-admin-order-edit-page-tracking.php';
	}

	/**
	 * Order view modal.
	 *
	 * @void
	 */
	public function order_view_modal() {
		$controller = new Controller(
			array(
				'url'     => $this->plugin_url,
				'version' => $this->plugin_version,
			)
		);
		$order_id   = Order_Util::get_id( Order_Util::admin_get_order() );
		$tracking   = $controller->get_order_tracking( $order_id );
		include realpath( plugin_dir_path( __DIR__ ) ) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'html-admin-order-view-modal-tracking.php';
	}
}
