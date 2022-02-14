<?php
/**
 * Plugin Name:      rw sso REST Auth Service
 * Plugin URI:       https://github.com/rpi-virtuell/rw-sso-rest-auth-service
 * Description:      Server Authentication tool to compare Wordpress login Data with a Remote Login Server
 * Author:           Daniel Reintanz
 * Version:          1.0.0
 * Licence:          GPLv3
 * GitHub Plugin URI: https://github.com/rpi-virtuell/rw-sso-rest-auth-service
 * GitHub Branch:     master
 */

class SsoRestAuthService
{
    /**
     * Plugin constructor.
     *
     * @since   0.1
     * @access  public
     * @uses    plugin_basename
     * @action  rw_remote_auth_server_init
     */
    public function __construct()
    {
        if (!defined('ALLOWED_SSO_CLIENTS'))
            define('ALLOWED_SSO_CLIENTS', array($_SERVER['SERVER_ADDR']));
        add_action('rest_api_init', array($this,'register_routes'));
    }

    /**
     * Register the routes for the objects of the controller.
     */
    public function register_routes()
    {
        $version = '1';
        $namespace = 'sso/v' . $version;
        $base = 'check_credentials';
        register_rest_route($namespace, '/' . $base, array(
                'methods' => 'POST',
                'callback' => array($this, 'handle_request'),
                'args' => array(
                    'page' => array(
                        'required' => false
                    ),
                    'per_page' => array(
                        'required' => false
                    ),
                ),
            )
        );
    }

    public function handle_request(WP_REST_Request $request)
    {
        if (!in_array($this->ipAddress(), ALLOWED_SSO_CLIENTS)){
            $response = new WP_REST_Response();
            $response->set_status(403);
            return $response;
        }
        $requestObj = $request->get_params();

        if (null === $requestObj) {
            $data = array("success" => false);
        } else {
            $username = $requestObj['username'];
            $password = $requestObj['password'];

            $LoginUser = wp_authenticate($username, $password);
            if (!is_wp_error($LoginUser) && !empty($LoginUser)) {
                $data = array(
                    "success" => true,
                    "profile" => array(
                        "display_name" => $LoginUser->display_name,
                        'first_name' => $LoginUser->first_name,
                        'last_name' =>  $LoginUser->last_name,
                        'user_login' => $LoginUser->user_login,
                        'user_email' => $LoginUser->user_email
                    ),
                );
            } else {
                $data = array("success" => false);
            }
        }

        $response = new WP_REST_Response($data);

        $response->set_status(201);
        return $response;
    }
    function ipAddress() {
        if (isset($_SERVER['REMOTE_ADDR'])) :
            $ip_address = $_SERVER['REMOTE_ADDR'];
        else :
            $ip_address = "undefined";
        endif;
        return $ip_address;
    }
}
new SsoRestAuthService();

