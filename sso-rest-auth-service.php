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
        add_action('rest_api_init', 'register_SSO_rest_routes');
        self::register_routes();
    }

    /**
     * Register the routes for the objects of the controller.
     */
    public function register_routes()
    {
        $version = '1';
        $namespace = 'sso-/v' . $version;
        $base = 'check_credentials';
        register_rest_route($namespace, '/' . $base, array(
            array(
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
            ),
        ));
    }

    public function handle_request(WP_REST_Request $request)
    {

        $request = $request->get_body();
        $requestObj = json_decode($request);
        if (null === $requestObj) {
            $data = array('auth' => array("success" => false));
        } else {
            $user = $requestObj->user->id;
            $mxid = $user;
            $user = substr($user, 1, strpos($user, ':') - 1);
            $password = addslashes($requestObj->user->password);

            $LoginUser = wp_authenticate($user, $password);
            if (!is_wp_error($LoginUser) && !empty($LoginUser)) {
                $data = array('auth' => array(
                    "success" => true,
                    "mxid" => $mxid,
                    "profile" => array(
                        "display_name" => $LoginUser->display_name,
                    ),
                ));
            } else {
                $data = array('auth' => array("success" => false));
            }
        }

        $response = new WP_REST_Response($data);

        $response->set_status(201);
        return $response;
    }
}
new SsoRestAuthService();

