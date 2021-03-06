<?php
/**
 * Plugin Name:      rw sso REST Auth Service
 * Plugin URI:       https://github.com/rpi-virtuell/rw-sso-rest-auth-service
 * Description:      Server Part of Single sign on tool
 * Author:           Daniel Reintanz
 * Version:          1.1.0
 * Licence:          GPLv3
 * GitHub Plugin URI: https://github.com/rpi-virtuell/rw-sso-rest-auth-service
 * GitHub Branch:     master
 */

class SsoRestAuthService
{
    /**
     * Plugin constructor.
     *
     * @since   1.0
     * @access  public
     * @uses    plugin_basename
     * @action  rw_remote_auth_server_init
     */
    public function __construct()
    {
        add_action('init', array($this, 'check_token'));
        add_action('init', array($this, 'login_user'));
        add_action('init', array($this, 'logout_through_remote'));
        if (!defined('ALLOWED_SSO_CLIENTS'))
            define('ALLOWED_SSO_CLIENTS', array($_SERVER['SERVER_ADDR']));
        add_action('wp_login', array($this, 'create_token'), 10, 2);
        add_action('rest_api_init', array($this, 'register_routes'));
        register_activation_hook(__FILE__, array($this, 'create_login_token_table'));
        register_deactivation_hook(__FILE__, array($this, 'delete_login_token_table'));
        add_action('admin_notices', array($this, 'backend_notifier'));
    }

    /**
     * Sends a backend notification which checks if the table login_token is present and notifies the user if it isn't
     * @since 1.0.1
     * @access public
     * @action admin_notices
     */
    public function backend_notifier()
    {

        global $wpdb;

        $table_name = $wpdb->prefix . 'login_token';

        if (empty($wpdb->get_var("SHOW TABLES LIKE '$table_name';"))) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php _e('WARNING: TABLE ' . $table_name . " WAS NOT CREATED! PLEASE REACTIVATE THE PLUGIN : rw sso REST Auth Service "); ?> </p>
            </div>
            <?php
        }

    }


    /**
     * Triggers a logout if this link attributes are passed and redirects to given link afterwards
     * @since 1.0.1
     * @access public
     * @action init
     */
    public function logout_through_remote()
    {
        if (isset($_GET['action']) && $_GET['action'] == 'remote_logout' && isset($_GET['redirect_to'])) {
            if (is_user_logged_in()) {
                $this->delete_login_token(get_current_user_id());
                wp_logout();
            }
            wp_redirect($_GET['redirect_to']);
            die();
        }
    }

    /**
     * Login via login token which is compared to token saved in database
     * @since 1.0.1
     * @access public
     * @action init
     */
    public function login_user()
    {
        if (isset($_GET['login_token'])) {
            $userId = $this->get_user_by_login_token($_GET['login_token']);
            wp_set_current_user($userId);
            wp_set_auth_cookie($userId);
            exit();
        }
    }

    /**
     * Create login_token table on plugin activation
     * @since 1.0.1
     * @access public
     * @action activation_hook
     */
    public function create_login_token_table()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'login_token';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                `login_token`  char(36) NOT NULL ,
                `user_id`  int NULL ,
                PRIMARY KEY (`user_id`), INDEX (`login_token`)
                ) $charset_collate;";
        $wpdb->query($sql);
    }

    /**
     * Delete login_token table on plugin deactivation
     * @since 1.0.1
     * @access public
     * @action deactivation_hook
     */
    public function delete_login_token_table()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'login_token';

        $sql = "DROP TABLE IF EXISTS `$table_name`;";

        $wpdb->query($sql);
    }

    /**
     * Gets the login_token by the user_id via database query
     * @param $user_id
     * @return string|null
     * @since 1.0.1
     * @access public
     */
    public function get_login_token_by_user($user_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'login_token';

        return $wpdb->get_var("SELECT login_token FROM `$table_name` WHERE user_id = $user_id ;");
    }

    /**
     * Gets the user_id by the login_token via database query
     * @param $login_token
     * @return string|null
     * @since  1.0.1
     * @access public
     */
    public function get_user_by_login_token($login_token)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'login_token';

        return $wpdb->get_var("SELECT user_id FROM `$table_name` WHERE login_token = '$login_token';");
    }

    /**
     * Delete the login_token by passed user_id via database query
     * @param $user_id
     * @return bool|int
     * @since 1.0.1
     * @access public
     */
    public function delete_login_token($user_id)
    {

        global $wpdb;

        $table_name = $wpdb->prefix . 'login_token';

        $sql = "DELETE FROM `$table_name` WHERE user_id = $user_id;";

        return $wpdb->query($sql);

    }

    /**
     * Replace the login_token connected to the given user_id
     * @param $user_id
     * @return bool|int
     * @since 1.0.1
     * @access public
     */
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
                '%s',
                '%d',
            )
        );
    }

    /**
     * Register the routes used by the methods of the client plugin
     * @since 1.0
     * @access public
     * @action rest_api_init
     *
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

    /**
     * Create a new login token
     * @action wp_login
     * @param $user_login
     * @param WP_User $user
     * @since 1.0.1
     * @access public
     */
    public function create_token($user_login, WP_User $user)
    {
        $this->replace_login_token($user->ID);
    }

    public function check_token()
    {
        if (isset($_GET['action']) && $_GET['action'] == 'check_token') {
            if (is_user_logged_in()) {
                $usertoken = $this->get_login_token_by_user(get_current_user_id());
            } else {
                $usertoken = '';
            }
            ?>
            var rw_sso_login_token = '<?php echo $usertoken ?>';
            <?php
            die();
        }
    }

    /**
     * REST route logic which checks if the sent user_token is present in the database
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     * @since 1.0.1
     * @access public
     */
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

    /**
     * REST route logic which checks the login credentials passed by POST
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     * @since 1.0.1
     * @access public
     */
    public function check_credentials(WP_REST_Request $request)
    {
        $response = new WP_REST_Response();

        if (!in_array($this->ipAddress(), ALLOWED_SSO_CLIENTS)) {
            $data = array(
                "success" => false,
                "error_message" => "Acces Denied! Client Server not authorized.");
            $response->set_status(403);
            $response->set_data($data);
                return $response;
        }
        $requestObj = $request->get_params();

        if (null === $requestObj) {
            $data = array(
                "success" => false,
                "error_message" => "REST Call Error! Required Data could not be found in passed call body.");
            $response->set_status(404);
        } else {
            //convert moodle curl request to array
            if (isset($requestObj[0]) && is_a(json_decode($requestObj[0]), 'stdClass')) {
                $Obj = json_decode($requestObj[0]);
                $requestObj['username'] = $Obj->username;
                $requestObj['password'] = $Obj->password;
                $requestObj['origin_url'] = $Obj->origin;
            }

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
                $response->set_status(201);
            } else {
                $data = array(
                    "success" => false,
                    "error" => "Authentication Error!",
                    "error_message" => $LoginUser->errors);

                $response->set_status(404);

            }
        }
        $response->set_data($data);
        return $response;
    }

    /**
     * REST route logic which gets user data by user login
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     * @since 1.0
     * @access public
     */
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

    /**
     * REST route logic which gets the data of multiple users by user login
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     * @since 1.0
     * @access public
     */
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

    /**
     * get whitelisted IPs
     * @return mixed|string
     * @since 1.0
     */
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

