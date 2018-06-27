<?php
/**
 * Contains code for the setup wizard class.
 *
 * @package     Boxtal\BoxtalWoocommerce\Init
 */

namespace Boxtal\BoxtalWoocommerce\Init;

use Boxtal\BoxtalPhp\ApiClient;
use Boxtal\BoxtalPhp\RestClient;
use Boxtal\BoxtalWoocommerce\Notice\Notice_Controller;
use Boxtal\BoxtalWoocommerce\Util\Auth_Util;

/**
 * Setup_Wizard class.
 *
 * Display setup wizard if needed.
 *
 * @class       Setup_Wizard
 * @package     Boxtal\BoxtalWoocommerce\Init
 * @category    Class
 * @author      API Boxtal
 */
class Setup_Wizard {

	/**
	 * Run class.
	 *
	 * @void
	 */
	public function run() {
		if ( Auth_Util::is_plugin_paired() && Notice_Controller::has_notice( Notice_Controller::$setup_wizard ) ) {
			Notice_Controller::remove_notice( Notice_Controller::$setup_wizard );
		} elseif ( ! Auth_Util::is_plugin_paired() && ! Notice_Controller::has_notice( Notice_Controller::$setup_wizard ) ) {
			$lib    = new ApiClient( null, null );
			$params = array(
				'locale' => get_locale(),
			);
			//phpcs:disable
			$response = $lib->restClient->request(
				RestClient::$GET,
				$lib->getApiUrl() . '/v2/sellershop/module/config',
				$params
			);
            //phpcs:enable

			if ( ! $response->isError() ) {
				$res = json_decode( $response->response );
				if ( property_exists( $res, 'mapsEndpointUrl' ) && property_exists( $res, 'signupPageUrl' ) ) {
                    //phpcs:ignore
                    update_option( 'BW_MAP_URL', $res->mapsEndpointUrl );
                    //phpcs:ignore
                    update_option( 'BW_SIGNUP_URL', $res->signupPageUrl );
					Notice_Controller::add_notice( Notice_Controller::$setup_wizard );
					if ( Notice_Controller::has_notice( Notice_Controller::$setup_failure ) ) {
						Notice_Controller::remove_notice( Notice_Controller::$setup_failure );
					}
				} else {
					Notice_Controller::add_notice( Notice_Controller::$setup_failure );
				}
			} else {
				Notice_Controller::add_notice( Notice_Controller::$setup_failure );
			}
		}
	}
}
