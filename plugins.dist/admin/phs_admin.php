<?php
namespace phs\plugins\admin;

use phs\PHS;
use phs\PHS_Api;
use phs\PHS_Crypt;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Roles;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Plugin;
use phs\system\core\models\PHS_Model_Plugins;
use phs\plugins\accounts\models\PHS_Model_Accounts;
use phs\system\core\events\layout\PHS_Event_Layout;
use phs\system\core\events\layout\PHS_Event_Template;

class PHS_Plugin_Admin extends PHS_Plugin
{
    public const H_ADMIN_LEFT_MENU_ADMIN_AFTER_USERS = 'phs_admin_left_menu_admin_after_users';

    public const LOG_API_MONITOR = 'api_monitor.log';

    public const EXPORT_TO_FILE = 1, EXPORT_TO_OUTPUT = 2, EXPORT_TO_BROWSER = 3;

    public const LOG_ROTATE_DAILY = 1, LOG_ROTATE_WEEKELY = 2, LOG_ROTATE_MONTHLY = 3, LOG_ROTATE_YEARLY = 4;

    private static array $LOG_ROTATE_ARR = [
        self::LOG_ROTATE_DAILY   => 'Daily',
        self::LOG_ROTATE_WEEKELY => 'Weekly',
        self::LOG_ROTATE_MONTHLY => 'Monthly',
        self::LOG_ROTATE_YEARLY  => 'Yearly',
    ];

    /**
     * @inheritdoc
     */
    public function get_settings_structure()
    {
        return [
            'themes_settings_group' => [
                'display_name' => $this->_pt('Theme settings'),
                'display_hint' => $this->_pt('How should themes inheritance be used.'),
                'group_fields' => [
                    'default_theme_in_admin' => [
                        'display_name' => $this->_pt('Default theme in admin'),
                        'display_hint' => $this->_pt('Should framework use default theme in admin section?'),
                        'type'         => PHS_Params::T_BOOL,
                        'default'      => false,
                    ],
                    'current_theme_as_default_in_admin' => [
                        'display_name' => $this->_pt('Current theme as default'),
                        'display_hint' => $this->_pt('If using default theme in admin section, should we set current theme as default (helps with loading resources from current theme if needed in admin interface)'),
                        'type'         => PHS_Params::T_BOOL,
                        'default'      => false,
                    ],
                ],
            ],
            'cors_settings_group' => [
                'display_name' => $this->_pt('API CORS settings'),
                'display_hint' => $this->_pt('<strong>WARNING</strong>: High risk security settings! Change them if you know what you are doing!'),
                'group_fields' => [
                    'allow_cors_api_calls' => [
                        'display_name'           => $this->_pt('Allow CORS calls'),
                        'display_hint'           => $this->_pt('Is platform accepting CORS calls? Usually front-end applications from other domains.'),
                        'type'                   => PHS_Params::T_BOOL,
                        'default'                => false,
                        'only_main_tenant_value' => true,
                    ],
                    'cors_origins' => [
                        'display_name'           => $this->_pt('CORS Origin'),
                        'display_hint'           => $this->_pt('Comma separated domains from which platform accepts CORS calls. * - accept from all, empty - platform will send as origin what was sent in request (Access-Control-Allow-Origin)'),
                        'type'                   => PHS_Params::T_NOHTML,
                        'default'                => '',
                        'only_main_tenant_value' => true,
                    ],
                    'cors_methods' => [
                        'display_name'           => $this->_pt('CORS Methods'),
                        'display_hint'           => $this->_pt('Comma separated HTTP methods which platform accepts for CORS calls. * - accept all methods, empty - doesn\'t respond with this header (Access-Control-Allow-Methods)'),
                        'type'                   => PHS_Params::T_NOHTML,
                        'default'                => '',
                        'only_main_tenant_value' => true,
                    ],
                    'cors_headers' => [
                        'display_name'           => $this->_pt('CORS Headers'),
                        'display_hint'           => $this->_pt('Comma separated HTTP headers which platform accepts for CORS calls. * - accept all headers, empty - doesn\'t respond with this header (Access-Control-Allow-Headers)'),
                        'type'                   => PHS_Params::T_NOHTML,
                        'default'                => '',
                        'only_main_tenant_value' => true,
                    ],
                    'cors_max_age' => [
                        'display_name'           => $this->_pt('CORS Max Age (seconds)'),
                        'display_hint'           => $this->_pt('Preflight requests can be cached. This represents how much time should a client cache OPTIONS response. -1 - do not send this header (Access-Control-Max-Age)'),
                        'type'                   => PHS_Params::T_INT,
                        'default'                => -1,
                        'only_main_tenant_value' => true,
                    ],
                ],
            ],
            'api_settings_group' => [
                'display_name' => $this->_pt('API settings'),
                'display_hint' => $this->_pt('Settings related to REST API calls made to this platform.'),
                'group_fields' => [
                    'allow_api_calls' => [
                        'display_name' => $this->_pt('Allow API calls'),
                        'display_hint' => $this->_pt('Are API calls allowed to this platform?'),
                        'type'         => PHS_Params::T_BOOL,
                        'default'      => false,
                    ],
                    'allow_api_calls_over_http' => [
                        'display_name' => $this->_pt('Allow HTTP API calls'),
                        'display_hint' => $this->_pt('Allow API calls over HTTP? If this checkbox is not ticked only HTTPS calls will be accepted.'),
                        'type'         => PHS_Params::T_BOOL,
                        'default'      => false,
                    ],
                    'api_can_simulate_web' => [
                        'display_name' => $this->_pt('API calls WEB emulation'),
                        'display_hint' => $this->_pt('Allow API calls to simulate a normal web call by interpreting JSON body as POST variables. (should send %s=1 as query parameter)', PHS_Api::PARAM_WEB_SIMULATION),
                        'type'         => PHS_Params::T_BOOL,
                        'default'      => false,
                    ],
                    'allow_bearer_token_authentication' => [
                        'display_name' => $this->_pt('Allow bearer token authentication'),
                        'display_hint' => $this->_pt('Allow API authentication using bearer token mechanism. To obtain bearer tokens 3rd party should call login script using API call and ask a bearer token in response.'),
                        'type'         => PHS_Params::T_BOOL,
                        'default'      => false,
                    ],
                    'monitor_api_incoming_calls' => [
                        'display_name' => $this->_pt('Monitor incoming calls'),
                        'display_hint' => $this->_pt('Incoming API calls will be saved in a special monitoring table.'),
                        'type'         => PHS_Params::T_BOOL,
                        'default'      => false,
                    ],
                    'monitor_api_incoming_cors_calls' => [
                        'display_name' => $this->_pt('Monitor incoming CORS calls'),
                        'display_hint' => $this->_pt('Should framework also log incoming CORS OPTIONS method calls?'),
                        'type'         => PHS_Params::T_BOOL,
                        'default'      => false,
                    ],
                    'monitor_api_outgoing_calls' => [
                        'display_name' => $this->_pt('Monitor outgoing API calls'),
                        'display_hint' => $this->_pt('Gives option to programatically add outgoing calls to API monitoring report table.'),
                        'type'         => PHS_Params::T_BOOL,
                        'default'      => false,
                    ],
                    'monitor_api_full_request_body' => [
                        'display_name' => $this->_pt('Log full request body'),
                        'display_hint' => $this->_pt('Log full request body as JSON.'),
                        'type'         => PHS_Params::T_BOOL,
                        'default'      => false,
                    ],
                    'monitor_api_full_response_body' => [
                        'display_name' => $this->_pt('Log full response body'),
                        'display_hint' => $this->_pt('Log full response body as JSON.'),
                        'type'         => PHS_Params::T_BOOL,
                        'default'      => false,
                    ],
                ],
            ],
            'agent_jobs_group' => [
                'display_name' => $this->_pt('Agent Jobs Settings'),
                'display_hint' => $this->_pt('Settings related to agent jobs details on this platform.'),
                'group_fields' => [
                    'monitor_agent_jobs' => [
                        'display_name' => $this->_pt('Monitor agent jobs'),
                        'display_hint' => $this->_pt('Monitor start, success or failure of agent jobs running on this platform'),
                        'type'         => PHS_Params::T_BOOL,
                        'default'      => false,
                    ],
                    'agent_jobs_allowance_interval' => [
                        'display_name' => $this->_pt('Jobs allowance interval'),
                        'display_hint' => $this->_pt('(seconds) When calculating next time agent job has to run, substract this time iterval. This should be big for slow servers. Crontab interval should be biger than this value!'),
                        'type'         => PHS_Params::T_INT,
                        'default'      => 300,
                    ],
                ],
            ],
            'logs_settings_group' => [
                'display_name' => $this->_pt('System Logs Settings'),
                'display_hint' => $this->_pt('Settings related to how system should handle logs'),
                'group_fields' => [
                    'log_add_loggedin_user' => [
                        'display_name' => 'Log logged-in user',
                        'display_hint' => 'Should logged-in user be added in generated logs?',
                        'type'         => PHS_params::T_BOOL,
                        'default'      => false,
                    ],
                    'logs_rotation_enabled' => [
                        'display_name' => 'Logs rotation enabled',
                        'display_hint' => 'Tells if system should rotate log files',
                        'type'         => PHS_params::T_BOOL,
                        'default'      => false,
                    ],
                    'log_rotate_policy' => [
                        'display_name' => 'Log rotate policy',
                        'display_hint' => 'How should system handle log files rotation (if enabled)',
                        'type'         => PHS_params::T_INT,
                        'values_arr'   => self::$LOG_ROTATE_ARR,
                        'default'      => self::LOG_ROTATE_MONTHLY,
                    ],
                ],
            ],
        ];
    }

    public function use_default_theme_in_admin() : bool
    {
        return ($settings_arr = $this->get_plugin_settings()) && !empty($settings_arr['default_theme_in_admin']);
    }

    public function use_current_theme_as_default_in_admin() : bool
    {
        return ($settings_arr = $this->get_plugin_settings()) && !empty($settings_arr['current_theme_as_default_in_admin']);
    }

    public function monitor_agent_jobs() : bool
    {
        return ($settings_arr = $this->get_plugin_settings())
               && !empty($settings_arr['monitor_agent_jobs']);
    }

    public function agent_jobs_allowance_interval() : int
    {
        return ($settings_arr = $this->get_plugin_settings()) && !empty($settings_arr['agent_jobs_allowance_interval'])
            ? (int)$settings_arr['agent_jobs_allowance_interval']
            : 60;
    }

    public function monitor_api_incoming_calls() : bool
    {
        return ($settings_arr = $this->get_plugin_settings())
               && !empty($settings_arr['monitor_api_incoming_calls']);
    }

    public function monitor_api_incoming_cors_calls() : bool
    {
        return ($settings_arr = $this->get_plugin_settings())
               && !empty($settings_arr['monitor_api_incoming_cors_calls']);
    }

    public function monitor_api_outgoing_calls() : bool
    {
        return ($settings_arr = $this->get_plugin_settings())
               && !empty($settings_arr['monitor_api_outgoing_calls']);
    }

    public function monitor_api_full_request_body() : bool
    {
        return ($settings_arr = $this->get_plugin_settings())
               && !empty($settings_arr['monitor_api_full_request_body']);
    }

    public function monitor_api_full_response_body() : bool
    {
        return ($settings_arr = $this->get_plugin_settings())
               && !empty($settings_arr['monitor_api_full_response_body']);
    }

    public function is_log_rotation_enabled() : bool
    {
        return ($settings_arr = $this->get_plugin_settings()) && !empty($settings_arr['logs_rotation_enabled']);
    }

    public function log_add_loggedin_user() : bool
    {
        return ($settings_arr = $this->get_plugin_settings()) && !empty($settings_arr['log_add_loggedin_user']);
    }

    public function log_rotation_policy() : int
    {
        return ($settings_arr = $this->get_plugin_settings()) && !empty($settings_arr['log_rotate_policy'])
            ? (int)$settings_arr['log_rotate_policy']
            : 0;
    }

    /**
     * @inheritdoc
     */
    public function get_roles_definition()
    {
        $return_arr = [
            PHS_Roles::ROLE_GUEST => [
                'name'        => 'Guests',
                'description' => 'Role used by non-logged visitors',
                'role_units'  => [
                    PHS_Roles::ROLEU_CONTACT_US => [
                        'name'        => 'Contact Us',
                        'description' => 'Allow user to use contact us form',
                    ],
                    PHS_Roles::ROLEU_REGISTER => [
                        'name'        => 'Register',
                        'description' => 'Allow user to use registration form',
                    ],
                ],
            ],

            PHS_Roles::ROLE_MEMBER => [
                'name'        => 'Member accounts',
                'description' => 'Default functionality role (what normal members can do)',
                'role_units'  => [
                    PHS_Roles::ROLEU_CONTACT_US => [
                        'name'        => 'Contact Us',
                        'description' => 'Allow user to use contact us form',
                    ],
                ],
            ],

            PHS_Roles::ROLE_OPERATOR => [
                'name'        => 'Operator accounts',
                'description' => 'Role assigned to operator accounts.',
                'role_units'  => [
                    // Roles...
                    PHS_Roles::ROLEU_LIST_ROLES => [
                        'name'        => 'List roles',
                        'description' => 'Allow user to view defined roles',
                    ],

                    // Plugins...
                    PHS_Roles::ROLEU_LIST_PLUGINS => [
                        'name'        => 'List plugins',
                        'description' => 'Allow user to list plugins',
                    ],

                    // Agent...
                    PHS_Roles::ROLEU_LIST_AGENT_JOBS => [
                        'name'        => 'List agent jobs',
                        'description' => 'Allow user to list agent jobs',
                    ],

                    // API keys...
                    PHS_Roles::ROLEU_LIST_API_KEYS => [
                        'name'        => 'List API keys',
                        'description' => 'Allow user to list API keys',
                    ],

                    // Logs...
                    PHS_Roles::ROLEU_VIEW_LOGS => [
                        'name'        => 'View system logs',
                        'description' => 'Allow user to view system logs',
                    ],

                    // Accounts...
                    PHS_Roles::ROLEU_LIST_ACCOUNTS => [
                        'name'        => 'List accounts',
                        'description' => 'Allow user to list accounts',
                    ],
                ],
            ],

            PHS_Roles::ROLE_TENANT_ADMIN => [
                'name'        => 'Tenant admin',
                'description' => 'Role assigned to accounts that can administrate tenants.',
                'role_units'  => [
                    // Roles...
                    PHS_Roles::ROLEU_TENANTS_MANAGE => [
                        'name'        => 'Manage tenants',
                        'description' => 'Allow user to define or edit tenants',
                    ],
                    PHS_Roles::ROLEU_TENANTS_LIST => [
                        'name'        => 'List tenants',
                        'description' => 'Allow user to view defined tenants',
                    ],
                ],
            ],

            PHS_Roles::ROLE_ADMIN => [
                'name'        => 'Admin accounts',
                'description' => 'Role assigned to admin accounts.',
                'role_units'  => [
                    // Roles...
                    PHS_Roles::ROLEU_MANAGE_ROLES => [
                        'name'        => 'Manage roles',
                        'description' => 'Allow user to define or edit roles',
                    ],
                    PHS_Roles::ROLEU_LIST_ROLES => [
                        'name'        => 'List roles',
                        'description' => 'Allow user to view defined roles',
                    ],

                    // Plugins...
                    PHS_Roles::ROLEU_MANAGE_PLUGINS => [
                        'name'        => 'Manage plugins',
                        'description' => 'Allow user to manage plugins',
                    ],
                    PHS_Roles::ROLEU_LIST_PLUGINS => [
                        'name'        => 'List plugins',
                        'description' => 'Allow user to list plugins',
                    ],
                    PHS_Roles::ROLEU_EXPORT_PLUGINS_SETTINGS => [
                        'name'        => 'Export plugin settings',
                        'description' => 'Allow user to export plugins settings',
                    ],
                    PHS_Roles::ROLEU_IMPORT_PLUGINS_SETTINGS => [
                        'name'        => 'Import plugin settings',
                        'description' => 'Allow user to import plugins settings',
                    ],

                    // Agent...
                    PHS_Roles::ROLEU_MANAGE_AGENT_JOBS => [
                        'name'        => 'Manage agent jobs',
                        'description' => 'Allow user to manage agent jobs',
                    ],
                    PHS_Roles::ROLEU_LIST_AGENT_JOBS => [
                        'name'        => 'List agent jobs',
                        'description' => 'Allow user to list agent jobs',
                    ],

                    // API Settings
                    PHS_Roles::ROLEU_MANAGE_API_KEYS => [
                        'name'        => 'Manage API keys',
                        'description' => 'Allow user to manage API keys',
                    ],
                    PHS_Roles::ROLEU_LIST_API_KEYS => [
                        'name'        => 'List API keys',
                        'description' => 'Allow user to list API keys',
                    ],
                    PHS_Roles::ROLEU_API_MONITORING_REPORT => [
                        'name'        => 'API monitoring report',
                        'description' => 'Allow user to view API monitoring report',
                    ],

                    // Logs...
                    PHS_Roles::ROLEU_VIEW_LOGS => [
                        'name'        => 'View system logs',
                        'description' => 'Allow user to view system logs',
                    ],

                    // Accounts...
                    PHS_Roles::ROLEU_MANAGE_ACCOUNTS => [
                        'name'        => 'Manage accounts',
                        'description' => 'Allow user to manage accounts',
                    ],
                    PHS_Roles::ROLEU_LIST_ACCOUNTS => [
                        'name'        => 'List accounts',
                        'description' => 'Allow user to list accounts',
                    ],
                    PHS_Roles::ROLEU_LOGIN_SUBACCOUNT => [
                        'name'        => 'Login sub-account',
                        'description' => 'Allow user to login as other user',
                    ],
                    PHS_Roles::ROLEU_EXPORT_ACCOUNTS => [
                        'name'        => 'Accounts Export',
                        'description' => 'Allow user to export user accounts',
                    ],
                    PHS_Roles::ROLEU_IMPORT_ACCOUNTS => [
                        'name'        => 'Accounts Import',
                        'description' => 'Allow user to import user accounts',
                    ],
                ],
            ],
        ];

        $return_arr[PHS_Roles::ROLE_OPERATOR]['role_units'] = self::validate_array($return_arr[PHS_Roles::ROLE_OPERATOR]['role_units'],
            self::validate_array($return_arr[PHS_Roles::ROLE_MEMBER]['role_units'], $return_arr[PHS_Roles::ROLE_GUEST]['role_units']));

        $return_arr[PHS_Roles::ROLE_ADMIN]['role_units'] = self::validate_array($return_arr[PHS_Roles::ROLE_ADMIN]['role_units'],
            self::validate_array($return_arr[PHS_Roles::ROLE_MEMBER]['role_units'], $return_arr[PHS_Roles::ROLE_GUEST]['role_units']));

        return $return_arr;
    }

    // region Can_* section
    /**
     * @param null|int|array $user_data
     *
     * @return bool
     */
    public function can_admin_manage_roles($user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_MANAGE_ROLES, null, $user_data);
    }

    /**
     * @param null|int|array $user_data
     *
     * @return bool
     */
    public function can_admin_list_roles($user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_LIST_ROLES, null, $user_data);
    }

    /**
     * @param null|int|array $user_data
     *
     * @return bool
     */
    public function can_admin_manage_plugins($user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_MANAGE_PLUGINS, null, $user_data);
    }

    /**
     * @param null|int|array $user_data
     *
     * @return bool
     */
    public function can_admin_list_plugins($user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_LIST_PLUGINS, null, $user_data);
    }

    /**
     * @param null|int|array $user_data
     *
     * @return bool
     */
    public function can_admin_import_plugins_settings($user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_IMPORT_PLUGINS_SETTINGS, null, $user_data);
    }

    /**
     * @param null|int|array $user_data
     *
     * @return bool
     */
    public function can_admin_export_plugins_settings($user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_EXPORT_PLUGINS_SETTINGS, null, $user_data);
    }

    /**
     * @param null|int|array $user_data
     *
     * @return bool
     */
    public function can_admin_manage_accounts($user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_MANAGE_ACCOUNTS, null, $user_data);
    }

    /**
     * @param null|int|array $user_data
     *
     * @return bool
     */
    public function can_admin_list_accounts($user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_LIST_ACCOUNTS, null, $user_data);
    }

    /**
     * @param null|int|array $user_data
     *
     * @return bool
     */
    public function can_admin_login_subaccounts($user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_LOGIN_SUBACCOUNT, null, $user_data);
    }

    /**
     * @param null|int|array $user_data
     *
     * @return bool
     */
    public function can_admin_export_accounts($user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_EXPORT_ACCOUNTS, null, $user_data);
    }

    /**
     * @param null|int|array $user_data
     *
     * @return bool
     */
    public function can_admin_import_accounts($user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_IMPORT_ACCOUNTS, null, $user_data);
    }

    /**
     * @param null|int|array $user_data
     *
     * @return bool
     */
    public function can_admin_manage_agent_jobs($user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_MANAGE_AGENT_JOBS, null, $user_data);
    }

    /**
     * @param null|int|array $user_data
     *
     * @return bool
     */
    public function can_admin_list_agent_jobs($user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_LIST_AGENT_JOBS, null, $user_data);
    }

    /**
     * @param null|int|array $user_data
     *
     * @return bool
     */
    public function can_admin_manage_api_keys($user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_MANAGE_API_KEYS, null, $user_data);
    }

    /**
     * @param null|int|array $user_data
     *
     * @return bool
     */
    public function can_admin_list_api_keys($user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_LIST_API_KEYS, null, $user_data);
    }

    /**
     * @param null|int|array $user_data
     *
     * @return bool
     */
    public function can_admin_view_api_monitoring_report($user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_API_MONITORING_REPORT, null, $user_data);
    }

    /**
     * @param null|int|array $user_data
     *
     * @return bool
     */
    public function can_admin_view_logs($user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_VIEW_LOGS, null, $user_data);
    }

    /**
     * @param null|int|array $user_data
     *
     * @return bool
     */
    public function can_admin_list_tenants($user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_TENANTS_LIST, null, $user_data);
    }

    /**
     * @param null|int|array $user_data
     *
     * @return bool
     */
    public function can_admin_manage_tenants($user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_TENANTS_MANAGE, null, $user_data);
    }
    // endregion Can_* section

    /**
     * @param bool $include_core
     *
     * @return null|array<string, array{"plugin_info":array, "instance":\phs\libraries\PHS_Plugin}>
     */
    public function get_plugins_list_as_array(bool $include_core = true) : ?array
    {
        $this->reset_error();

        /** @var \phs\system\core\models\PHS_Model_Plugins $plugins_model */
        if (!($plugins_model = PHS_Model_Plugins::get_instance())) {
            $this->set_error(self::ERR_RESOURCES, $this->_pt('Error loading required resources.'));

            return null;
        }

        if (!($dir_entries = $plugins_model->cache_all_dir_details())
         || !is_array($dir_entries)) {
            $dir_entries = [];
        }

        $return_arr = [];

        if ($include_core) {
            $return_arr[''] = [
                'plugin_info' => self::core_plugin_details_fields(),
                'instance'    => false,
            ];
        }

        foreach ($dir_entries as $plugin_dir => $plugin_instance) {
            if (empty($plugin_instance)
             || !($plugin_info_arr = $plugin_instance->get_plugin_info())
             || empty($plugin_info_arr['plugin_name'])) {
                continue;
            }

            $return_arr[$plugin_info_arr['plugin_name']] = [
                'plugin_info' => $plugin_info_arr,
                'instance'    => $plugin_instance,
            ];
        }

        return $return_arr;
    }

    //
    // region Import plugin settings
    //
    /**
     * @param array $encoded_arr
     * @param string $crypting_key
     *
     * @return null|array
     */
    public function decode_plugin_settings_from_encoded_array(array $encoded_arr, string $crypting_key) : ?array
    {
        if (!($settings_buf = PHS_Crypt::quick_decode_from_export_array($encoded_arr, $crypting_key))) {
            if (PHS_Crypt::st_has_error()) {
                $this->copy_static_error();
            } else {
                $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error decoding settings data.'));
            }

            return null;
        }

        if (!($settings_arr = @json_decode($settings_buf, true))
         || !is_array($settings_arr)) {
            return [];
        }

        return $settings_arr;
    }
    //
    // endregion Import plugin settings
    //

    //
    // region Export plugin settings
    //
    /**
     * @param string $crypting_key
     * @param string[] $plugins_arr
     * @param null|array $export_params
     *
     * @return bool
     */
    public function export_plugin_settings(string $crypting_key, array $plugins_arr = [], ?array $export_params = null) : bool
    {
        $export_params ??= [];

        if (empty($export_params['export_file_dir'])) {
            $export_params['export_file_dir'] = '';
        }

        if (empty($export_params['export_to'])
            || !self::valid_export_to($export_params['export_to'])) {
            $export_params['export_to'] = self::EXPORT_TO_BROWSER;
        }

        if (empty($export_params['export_file_name'])) {
            $export_params['export_file_name'] = 'plugin_settings_export_'.date('YmdHi').'.json';
        }

        if (!($settings_json = $this->get_settings_for_plugins_as_encrypted_json($crypting_key, $plugins_arr))) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Nothing to export.'));

            return false;
        }

        switch ($export_params['export_to']) {
            case self::EXPORT_TO_FILE:
                if (empty($export_params['export_file_dir'])
                 || !($export_file_dir = rtrim($export_params['export_file_dir'], '/\\'))
                 || !@is_dir($export_file_dir)
                 || !@is_writable($export_file_dir)) {
                    $this->set_error(self::ERR_PARAMETERS,
                        $this->_pt('No directory provided to save export data to or no rights to write in that directory.'));

                    return false;
                }

                $full_file_path = $export_file_dir.'/'.$export_params['export_file_name'];
                if (!($fd = @fopen($full_file_path, 'wb'))) {
                    $this->set_error(self::ERR_PARAMETERS, $this->_pt('Couldn\'t create export file.'));

                    return false;
                }

                @fwrite($fd, $settings_json);
                @fflush($fd);
                @fclose($fd);
                break;

            case self::EXPORT_TO_BROWSER:
                if (@headers_sent()) {
                    $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Headers already sent. Cannot send export file to browser.'));

                    return false;
                }

                @header('Content-Transfer-Encoding: binary');
                @header('Content-Disposition: attachment; filename="'.$export_params['export_file_name'].'"');
                @header('Expires: 0');
                @header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                @header('Pragma: public');
                @header('Content-Type: application/json;charset=UTF-8');

                echo $settings_json;
                exit;
                break;

            case self::EXPORT_TO_OUTPUT:
                echo $settings_json;
                exit;
                break;
        }

        return true;
    }

    /**
     * @param string $crypting_key
     * @param string[] $plugins_arr
     *
     * @return null|array
     */
    public function get_settings_for_plugins_as_encrypted_array(string $crypting_key, array $plugins_arr = []) : ?array
    {
        if (empty($crypting_key)) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error exporting plugin settings. No crypting key provided.'));

            return null;
        }

        if (!($settings_json = $this->get_settings_for_plugins_as_json($plugins_arr))) {
            return null;
        }

        if (!($result_arr = PHS_Crypt::quick_encode_buffer_for_export_as_array($settings_json, $crypting_key))) {
            if (PHS_Crypt::st_has_error()) {
                $this->copy_static_error();
            } else {
                $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error encrypting plugin settings.'));
            }

            return null;
        }

        return $result_arr;
    }

    /**
     * @param string $crypting_key
     * @param string[] $plugins_arr
     *
     * @return null|string
     */
    public function get_settings_for_plugins_as_encrypted_json(string $crypting_key, array $plugins_arr = []) : ?string
    {
        if (!($settings_json = $this->get_settings_for_plugins_as_json($plugins_arr))) {
            return null;
        }

        return PHS_Crypt::quick_encode_buffer_for_export_as_json($settings_json, $crypting_key);
    }

    /**
     * @param string[] $plugins_arr
     *
     * @return string
     */
    public function get_settings_for_plugins_as_json(array $plugins_arr = []) : string
    {
        if (!($return_arr = @json_encode($this->get_settings_for_plugins_as_array($plugins_arr)))) {
            $return_arr = '';
        }

        return $return_arr;
    }

    /**
     * @param string[] $plugins_arr
     *
     * @return array
     */
    public function get_settings_for_plugins_as_array(array $plugins_arr = []) : array
    {
        $this->reset_error();

        if (!($plugins_list = $this->get_plugins_list_as_array(true))) {
            $plugins_list = [];
        }

        $settings_arr = [];
        foreach ($plugins_list as $plugin_name => $plugin_details) {
            if ((!empty($plugins_arr)
                 && !in_array($plugin_name, $plugins_arr, true))
             || !($plugin_settings = $this->extract_settings_for_plugin($plugin_name,
                 (!empty($plugin_details['instance']) ? $plugin_details['instance'] : null)))
            ) {
                continue;
            }

            $settings_arr[$plugin_name] = $plugin_settings;
        }

        return $settings_arr;
    }

    /**
     * @param null|string $plugin_name
     * @param null|\phs\libraries\PHS_Plugin $plugin_instance
     *
     * @return null|array
     */
    public function extract_settings_for_plugin(?string $plugin_name, ?PHS_Plugin $plugin_instance = null) : ?array
    {
        $this->reset_error();

        $is_core = ($plugin_name === '' || $plugin_name === null);
        if (
            // Instantiate plugin (if instance is not already provided)
            (!$is_core
                && $plugin_instance === null
                && !($plugin_instance = PHS::load_plugin($plugin_name))
            )
            // Instance checks... (keep separate from above statement)
            || (!$is_core
             && !($plugin_instance instanceof PHS_Plugin)
            )
        ) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Invalid plugin provided.'));

            return null;
        }

        if ($is_core) {
            $plugin_instance = null;
            $plugin_instance_id = '';
            if (!($models_arr = PHS::get_core_models())) {
                $models_arr = [];
            }
        } else {
            if (!($plugin_instance_id = $plugin_instance->instance_id())) {
                $this->set_error(self::ERR_PARAMETERS, $this->_pt('Couldn\'t obtain plugin instance id.'));

                return null;
            }

            if (!($models_arr = $plugin_instance->get_models())) {
                $models_arr = [];
            }
        }

        $plugin_settings_arr = [];

        if ($plugin_instance
         && ($settings_arr = $plugin_instance->get_plugin_settings())) {
            $plugin_settings_arr[$plugin_instance_id] = $settings_arr;
        }

        foreach ($models_arr as $model_name) {
            if (!($settings = $this->_extract_settings_for_model($model_name, $plugin_name))) {
                if (!$this->has_error()) {
                    $this->set_error(self::ERR_FUNCTIONALITY,
                        $this->_pt('Couldn\'t instantiate model %s to extract settings.',
                            (empty($model_name) ? '-' : $model_name)));
                }

                return null;
            }

            // No need to export no settings...
            if (empty($settings['instance_id'])
             || empty($settings['settings'])) {
                continue;
            }

            $plugin_settings_arr[$settings['instance_id']] = $settings['settings'];
        }

        return $plugin_settings_arr;
    }
    //
    // endregion Export plugin settings
    //

    /**
     * @param bool|array $hook_args
     *
     * @return array
     */
    public function trigger_after_left_menu_admin($hook_args = false)
    {
        $hook_args = self::validate_array($hook_args, PHS_Hooks::default_buffer_hook_args());

        $data = [];

        $hook_args['buffer'] = $this->quick_render_template_for_buffer('left_menu_admin', $data);

        return $hook_args;
    }

    /**
     * @param \phs\system\core\events\layout\PHS_Event_Layout $event_obj
     *
     * @return bool
     */
    public function listen_after_left_menu_admin(PHS_Event_Layout $event_obj) : bool
    {
        if (!($buffer = $event_obj->get_output('buffer'))) {
            $buffer = '';
        }

        if (!is_string($template_buffer = $this->quick_render_template_for_buffer('left_menu_admin', []))) {
            $template_buffer = '';
        }

        $event_obj->set_output('buffer', $buffer.$template_buffer);

        return true;
    }

    /**
     * @param PHS_Event_Template $event_obj
     *
     * @return bool
     */
    public function listen_web_template_rendering(PHS_Event_Template $event_obj) : bool
    {
        if ($event_obj->get_input('page_template') === 'template_admin'
         && ($current_theme = PHS::get_theme()) !== 'default'
         && ($settings_arr = $this->get_plugin_settings())) {
            PHS::set_theme('default');

            if (!empty($settings_arr['current_theme_as_default_in_admin'])
             && !empty($current_theme)) {
                PHS::set_defaut_theme($current_theme);
            }
        }

        return true;
    }

    /**
     * @param string $model_name
     * @param null|string $plugin
     *
     * @return null|array
     */
    private function _extract_settings_for_model(string $model_name, ?string $plugin = null) : ?array
    {
        $this->reset_error();

        if (empty($model_name)
         || !($model_instance = PHS::load_model($model_name, $plugin ?: null))
         || !($instance_id = $model_instance->instance_id())) {
            $this->set_error(self::ERR_PARAMETERS,
                $this->_pt('Couldn\'t initiate model %s to extract settings.',
                    (empty($model_name) ? '-' : $model_name)));

            return null;
        }

        if (!($settings_arr = $model_instance->get_db_settings())) {
            $settings_arr = [];
        }

        return [
            'instance_id' => $instance_id,
            'settings'    => $settings_arr,
        ];
    }

    /**
     * @param int $export_to
     *
     * @return bool
     */
    public static function valid_export_to(int $export_to) : bool
    {
        return !empty($export_to)
                && in_array($export_to, [self::EXPORT_TO_FILE, self::EXPORT_TO_OUTPUT, self::EXPORT_TO_BROWSER], true);
    }
}
