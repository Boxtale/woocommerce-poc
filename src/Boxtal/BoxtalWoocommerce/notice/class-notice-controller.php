<?php
/**
 * Contains code for the notice controller class.
 *
 * @package     Boxtal\BoxtalWoocommerce\Notice
 */

namespace Boxtal\BoxtalWoocommerce\Notice;
use Boxtal\BoxtalPhp\ApiClient;
use Boxtal\BoxtalPhp\RestClient;
use Boxtal\BoxtalWoocommerce\Util\Auth_Util;

/**
 * Notice controller class.
 *
 * Controller for notices.
 *
 * @class       Notice_Controller
 * @package     Boxtal\BoxtalWoocommerce\Notice
 * @category    Class
 * @author      API Boxtal
 */
class Notice_Controller {

	/**
	 * Array of notices - name => callback.
	 *
	 * @var array
	 */
	private static $core_notices = array( 'update', 'setup-wizard', 'pairing', 'pairing-update' );

	/**
	 * Construct function.
	 *
	 * @param array $plugin plugin array.
	 * @void
	 */
	public function __construct( $plugin ) {
		$this->plugin_url     = $plugin['url'];
		$this->plugin_version = $plugin['version'];
		$this->ajax_nonce     = wp_create_nonce( 'boxtale_woocommerce_notice' );
	}

	/**
	 * Run class.
	 *
	 * @void
	 */
	public function run() {
		$notices = self::get_notices();

		if ( ! empty( $notices ) ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'notice_scripts' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'notice_styles' ) );
			add_action( 'wp_ajax_hide_notice', array( $this, 'hide_notice_callback' ) );

			foreach ( $notices as $notice ) {
				add_action( 'admin_notices', array( $notice, 'render' ) );

				if ('pairing-update' === $notice->type) {
                    add_action( 'wp_ajax_pairing_update_validate', array( $this, 'pairing_update_validate_callback' ) );
                }
			}
		}
	}

	/**
	 * Enqueue notice scripts
	 *
	 * @void
	 */
	public function notice_scripts() {
		wp_enqueue_script( 'bw_notices', $this->plugin_url . 'Boxtal/BoxtalWoocommerce/assets/js/notices.min.js', array(), $this->plugin_version );
		wp_localize_script( 'bw_notices', 'ajax_nonce', $this->ajax_nonce );
	}

	/**
	 * Enqueue notice styles
	 *
	 * @void
	 */
	public function notice_styles() {
		wp_enqueue_style( 'bw_notices', $this->plugin_url . 'Boxtal/BoxtalWoocommerce/assets/css/notices.css', array(), $this->plugin_version );
	}

	/**
	 * Get notices.
	 *
	 * @return mixed $notices instances of notice.
	 */
	public static function get_notices() {
		$notices          = get_option( 'BW_NOTICES', array() );
		$notice_instances = array();
		foreach ( $notices as $key ) {
			$classname = 'Boxtal\BoxtalWoocommerce\Notice\\';
			if ( ! in_array( $key, self::$core_notices, true ) ) {
				$notice = get_transient( $key );
				if ( false !== $notice && isset( $notice['type'] ) ) {
					$classname .= ucfirst( $notice['type'] ) . '_Notice';
					if ( class_exists( $classname, true ) ) {
						$class              = new $classname( $key, $notice );
						$notice_instances[] = $class;
					}
				} else {
					self::remove_notice( $key );
				}
			} else {
				$classname .= ucwords( str_replace( '-', '_', $key ) ) . '_Notice';
				if ( class_exists( $classname, true ) ) {
					$extra = get_option( 'BW_NOTICE_' . $key );
					if ( false !== $extra ) {
						$class = new $classname( $key, $extra );
					} else {
						$class = new $classname( $key );
					}
					$notice_instances[] = $class;
				}
			}
		}
		return $notice_instances;
	}

	/**
	 * Add notice.
	 *
	 * @param string $type type of notice.
	 * @param mixed  $args additional args.
	 * @void
	 */
	public static function add_notice( $type, $args = array() ) {
		if ( ! in_array( $type, self::$core_notices, true ) ) {
			$key           = uniqid( 'bw_', false );
			$value         = $args;
			$value['type'] = $type;
			set_transient( $key, $value, DAY_IN_SECONDS );
		} else {
			$key = $type;
			if ( ! empty( $args ) ) {
				update_option( 'BW_NOTICE_' . $key, $args );
			}
		}
		$notices = get_option( 'BW_NOTICES', array() );
		if ( ! in_array( $key, $notices, true ) ) {
			$notices[] = $key;
			update_option( 'BW_NOTICES', $notices );
		}
	}

	/**
	 * Remove notice.
	 *
	 * @param string $key notice key.
	 * @void
	 */
	public static function remove_notice( $key ) {
		$notices = get_option( 'BW_NOTICES', array() );
        // phpcs:ignore
		if ( ( $index = array_search( $key, $notices, true ) ) !== false ) {
			unset( $notices[ $index ] );
		}
		update_option( 'BW_NOTICES', $notices );
	}

	/**
	 * Whether there are active notices.
	 *
	 * @void
	 */
	public static function has_notices() {
		$notices = self::get_notices();
		return ! empty( $notices );
	}

	/**
	 * Ajax callback. Hide notice.
	 *
	 * @void
	 */
	public function hide_notice_callback() {
		check_ajax_referer( 'boxtale_woocommerce_notice', 'security' );
		header( 'Content-Type: application/json; charset=utf-8' );
		if ( ! isset( $_REQUEST['notice_id'] ) ) {
			wp_send_json( true );
		}
		$notice_id = sanitize_text_field( wp_unslash( $_REQUEST['notice_id'] ) );
		self::remove_notice( $notice_id );
		wp_send_json( true );
	}

    /**
     * Ajax callback. Validate pairing update.
     *
     * @void
     */
    public function pairing_update_validate_callback() {
        check_ajax_referer( 'boxtale_woocommerce_notice', 'security' );
        header( 'Content-Type: application/json; charset=utf-8' );
        if ( ! isset( $_REQUEST['input'] ) ) {
            wp_send_json_error('missing input');
        }
        $input = sanitize_text_field( wp_unslash( $_REQUEST['input'] ) );

        $lib = new ApiClient(Auth_Util::get_access_key(), Auth_Util::get_secret_key());
        $response = $lib->restClient->request(RestClient::$POST, get_option('BW_PAIRING_UPDATE'), array('input' => $input));

        if (!$response->isError()) {
            Auth_Util::end_pairing_update();
            Notice_Controller::remove_notice( 'pairing-update' );
            Notice_Controller::add_notice( 'pairing', array( 'result' => 1 ) );
            wp_send_json( true );
        } else {
            wp_send_json_error('pairing validation failed');
        }
    }

	/**
	 * Remove all notices.
	 *
	 * @void
	 */
	public static function remove_all_notices() {
		update_option( 'BW_NOTICES', array() );
	}
}
