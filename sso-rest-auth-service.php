<?php
/**
 * Plugin Name:      rw sso REST Auth Service
 * Plugin URI:       https://github.com/rpi-virtuell/rw-sso-rest-auth-service
 * Description:      Server Part of Single sign on tool
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
        add_action('rest_api_init', array($this, 'register_routes'));
        register_activation_hook(__FILE__, array($this, 'create_login_token_table'));
        register_deactivation_hook(__FILE__, array($this, 'delete_login_token_table'));
    }

    public function create_login_token_table()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'login_token';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                `login_token`  varchar(36) NOT NULL ,
                `user_id`  int NULL ,
                PRIMARY KEY (`user_id`), INDEX (`login_token`)
                ) $charset_collate;";


        $wpdb->query($sql);
    }

    public function delete_login_token_table()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'login_token';

        $sql = "DROP TABLE IF EXISTS `$table_name`;";

        $wpdb->query($sql);
    }

    public function get_login_token_by_user($user_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'login_token';

        return $wpdb->get_var("SELECT login_token FROM `$table_name` WHERE user_id = `$user_id` ;");

    }

    public function get_user_by_login_token($login_token)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'login_token';

        return $wpdb->get_var("SELECT user_id FROM `$table_name` WHERE login_token = `$login_token`;");
    }

    public function delete_login_token($user_id)
    {

        global $wpdb;

        $table_name = $wpdb->prefix . 'login_token';

        $sql = "DELETE FROM `$table_name` WHERE user_id = `$user_id`;";

        $wpdb->query($sql);

    }

    public function replace_login_token($user_id)
    {
        global $wpdb;

        $login_token = wp_generate_uuid4();

        return $wpdb->replace(
            $wpdb->prefix . 'login_token',
            array(
                'login_token' => $login_token,
                'user_id' => $user_id,
            ),
            array(
                '%d',
                '%s',
            )
        );
    }

    /**
     * Register the routes for the objects of the controller.
     */
    public function register_routes()
    {
        $version = '1';
        $namespace = 'sso/v' . $version;
        register_rest_route($namespace, '/' . 'check_credentials', array(
                'methods' => 'POST',
                'callback' => array($this, 'check_credentials'),
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

        register_rest_route($namespace, '/' . 'get_remote_users', array(
                'methods' => 'POST',
                'callback' => array($this, 'get_remote_users'),
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

        register_rest_route($namespace, '/' . 'get_remote_user', array(
                'methods' => 'POST',
                'callback' => array($this, 'get_remote_user'),
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
        register_rest_route($namespace, '/' . 'check_login_token', array(
                'methods' => 'POST',
                'callback' => array($this, 'check_login_token'),
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

    public function check_login_token(WP_REST_Request $request)
    {
        if (!in_array($this->ipAddress(), ALLOWED_SSO_CLIENTS)) {
            $response = new WP_REST_Response();
            $response->set_status(403);
            return $response;
        }
        $requestObj = $request->get_params();

        $data = array("success" => false);
        if (null != $requestObj) {
            $login_token = $requestObj['login_token'];
            $user_id = $this->get_user_by_login_token($login_token);
            $user = get_user_by('id', $user_id);
            if (!empty($user_id)) {
                $data = array('success' => true,
                    'user_login' => $user->user_login);
            }
        }
        $response = new WP_REST_Response($data);

        $response->set_status(201);
        return $response;
    }

    public function check_credentials(WP_REST_Request $request)
    {
        if (!in_array($this->ipAddress(), ALLOWED_SSO_CLIENTS)) {
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
            $origin_url = $requestObj['origin_url'];

            $LoginUser = wp_authenticate($username, $password);
            if (is_a($LoginUser, 'WP_User')) {
                $this->replace_login_token($LoginUser->ID);
                $data = array(
                    "success" => true,
                    "profile" => array(
                        "display_name" => $LoginUser->display_name,
                        'first_name' => $LoginUser->first_name,
                        'last_name' => $LoginUser->last_name,
                        'user_login' => $LoginUser->user_login,
                        'user_email' => $LoginUser->user_email,
                        'login_token' => $this->get_login_token_by_user($LoginUser->ID)
                    )
                );
                if (!empty(get_user_meta($LoginUser->ID, 'rw_website_urls', true))) {
                    $website_urls = get_user_meta($LoginUser->ID, 'rw_website_urls', true);
                } else {
                    $website_urls = array();
                }
                update_user_meta($LoginUser->ID, 'rw_last_visited_url', $origin_url);
                update_user_meta($LoginUser->ID, 'rw_last_visited_timestamp', wp_date(get_option('date_format')));
                array_push($website_urls, $origin_url);
                $website_urls = array_unique($website_urls);
                update_user_meta($LoginUser->ID, 'rw_website_urls', $website_urls);
            } else {
                $data = array("success" => false);
            }
        }

        $response = new WP_REST_Response($data);

        $response->set_status(201);
        return $response;
    }

    public function get_remote_user(WP_REST_Request $request)
    {
        $data = array("success" => false);
        if (!in_array($this->ipAddress(), ALLOWED_SSO_CLIENTS)) {
            $response = new WP_REST_Response();
            $response->set_status(403);
            return $response;
        }
        $requestObj = $request->get_params();

        if (null === $requestObj) {
            $response = new WP_REST_Response();
            $response->set_status(406);
            return $response;
        } else {
            $user_login = $requestObj['user_login'];
            $user = get_user_by('login', $user_login);
            if (is_a($user, 'WP_User')) {
                $user = array(
                    "display_name" => $user->display_name,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'user_login' => $user->user_login,
                    'user_email' => $user->user_email,
                    'avatar' => get_avatar($user->ID));
            }
            if ($user) {
                $data = array("success" => true, "user" => $user);
            }
        }
        $response = new WP_REST_Response($data);

        $response->set_status(201);
        return $response;
    }

    public function get_remote_users(WP_REST_Request $request)
    {
        $data = array("success" => false);
        if (!in_array($this->ipAddress(), ALLOWED_SSO_CLIENTS)) {
            $response = new WP_REST_Response();
            $response->set_status(403);
            return $response;
        }
        $requestObj = $request->get_params();

        if (null === $requestObj) {
            $response = new WP_REST_Response();
            $response->set_status(406);
            return $response;
        } else {
            $searchquery = $requestObj['search_query'];
            $users = get_users(array('search' => '*' . $searchquery . '*', 'search_columns' => array('user_login', 'user_email')));
            $userlist = array();
            foreach ($users as $user) {
                if (is_a($user, 'WP_User')) {

                    $userlist[] = array(
                        "display_name" => $user->display_name,
                        'user_login' => $user->user_login,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'avatar' => get_avatar($user->ID));
                }
            }
            if (count($userlist) > 0) {
                $data = array("success" => true, "users" => $userlist);
            }
        }

        $response = new WP_REST_Response($data);

        $response->set_status(201);
        return $response;
    }

    function ipAddress()
    {
        if (isset($_SERVER['REMOTE_ADDR'])) :
            $ip_address = $_SERVER['REMOTE_ADDR'];
        else :
            $ip_address = "undefined";
        endif;
        return $ip_address;
    }
}

new SsoRestAuthService();

