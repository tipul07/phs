<?php
namespace phs\plugins\admin;

use phs\PHS;
use phs\PHS_Api;
use phs\libraries\PHS_Roles;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Plugin;
use phs\plugins\admin\libraries\Phs_Data_retention;
use phs\system\core\events\layout\PHS_Event_Layout;
use phs\plugins\admin\libraries\Phs_Plugin_settings;
use phs\system\core\events\layout\PHS_Event_Template;

class PHS_Plugin_Admin extends PHS_Plugin
{
    public const H_ADMIN_LEFT_MENU_ADMIN_AFTER_USERS = 'phs_admin_left_menu_admin_after_users';

    public const LOG_API_MONITOR = 'api_monitor.log', LOG_DATA_RETENTION = 'phs_data_retention.log';

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
    public function get_settings_structure() : array
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
            'graphql_settings_group' => [
                'display_name' => $this->_pt('GraphQL settings'),
                'display_hint' => $this->_pt('Settings related to GraphQL calls made to this platform.'),
                'group_fields' => [
                    'allow_graphql_calls' => [
                        'display_name' => $this->_pt('Allow GraphQL calls'),
                        'display_hint' => $this->_pt('Are GraphQL calls allowed to this platform?'),
                        'type'         => PHS_Params::T_BOOL,
                        'default'      => false,
                    ],
                    'monitor_graphql_calls' => [
                        'display_name' => $this->_pt('Monitor GraphQL calls'),
                        'display_hint' => $this->_pt('If GraphQL calls are monitored.'),
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
                        'type'         => PHS_Params::T_BOOL,
                        'default'      => false,
                    ],
                    'logs_rotation_enabled' => [
                        'display_name' => 'Logs rotation enabled',
                        'display_hint' => 'Tells if system should rotate log files',
                        'type'         => PHS_Params::T_BOOL,
                        'default'      => false,
                    ],
                    'log_rotate_policy' => [
                        'display_name' => 'Log rotate policy',
                        'display_hint' => 'How should system handle log files rotation (if enabled)',
                        'type'         => PHS_Params::T_INT,
                        'values_arr'   => self::$LOG_ROTATE_ARR,
                        'default'      => self::LOG_ROTATE_MONTHLY,
                    ],
                ],
            ],
            'data_retention_group' => [
                'display_name' => $this->_pt('Data Retention Settings'),
                'display_hint' => $this->_pt('Settings related to data retention functionality'),
                'group_fields' => [
                    'data_retention_run_hour' => [
                        'display_name' => 'Data retention run hour',
                        'display_hint' => 'At which hour should data retention manage the data?',
                        'type'         => PHS_Params::T_INT,
                        'default'      => 3,
                        'values_arr'   => [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23],
                    ],
                ],
            ],
        ];
    }

    public function use_default_theme_in_admin() : bool
    {
        return (bool)($this->get_plugin_settings()['default_theme_in_admin'] ?? false);
    }

    public function use_current_theme_as_default_in_admin() : bool
    {
        return (bool)($this->get_plugin_settings()['current_theme_as_default_in_admin'] ?? false);
    }

    public function monitor_agent_jobs() : bool
    {
        return (bool)($this->get_plugin_settings()['monitor_agent_jobs'] ?? false);
    }

    public function agent_jobs_allowance_interval() : int
    {
        return (int)($this->get_plugin_settings()['data_retention_run_hour'] ?? 60);
    }

    public function data_retention_agent_run_hour() : int
    {
        return (int)($this->get_plugin_settings()['data_retention_run_hour'] ?? 3);
    }

    public function monitor_api_incoming_calls() : bool
    {
        return (bool)($this->get_plugin_settings()['monitor_api_incoming_calls'] ?? false);
    }

    public function monitor_api_incoming_cors_calls() : bool
    {
        return (bool)($this->get_plugin_settings()['monitor_api_incoming_cors_calls'] ?? false);
    }

    public function monitor_api_outgoing_calls() : bool
    {
        return (bool)($this->get_plugin_settings()['monitor_api_outgoing_calls'] ?? false);
    }

    public function monitor_api_full_request_body() : bool
    {
        return (bool)($this->get_plugin_settings()['monitor_api_full_request_body'] ?? false);
    }

    public function monitor_api_full_response_body() : bool
    {
        return (bool)($this->get_plugin_settings()['monitor_api_full_response_body'] ?? false);
    }

    public function allow_graphql_calls() : bool
    {
        return (bool)($this->get_plugin_settings()['allow_graphql_calls'] ?? false);
    }

    public function monitor_graphql_calls() : bool
    {
        return (bool)($this->get_plugin_settings()['monitor_graphql_calls'] ?? false);
    }

    public function is_log_rotation_enabled() : bool
    {
        return (bool)($this->get_plugin_settings()['logs_rotation_enabled'] ?? false);
    }

    public function log_add_loggedin_user() : bool
    {
        return ($settings_arr = $this->get_plugin_settings()) && !empty($settings_arr['log_add_loggedin_user']);
    }

    public function log_rotation_policy() : int
    {
        return (int)($this->get_plugin_settings()['log_rotate_policy'] ?? 0);
    }

    /**
     * @inheritdoc
     */
    public function get_roles_definition() : array
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

            PHS_Roles::ROLE_PLATFORM_OPERATIONS => [
                'name'        => 'Platform operations',
                'description' => 'Role assigned to accounts that do operation tasks on the platform.',
                'role_units'  => [
                    // Migrations...
                    PHS_Roles::ROLEU_LIST_MIGRATIONS => [
                        'name'        => 'List migrations',
                        'description' => 'Allow user to list migration scripts',
                    ],
                    PHS_Roles::ROLEU_MANAGE_MIGRATIONS => [
                        'name'        => 'Manage migrations',
                        'description' => 'Allow user to manage migration scripts',
                    ],

                    // Data retention...
                    PHS_Roles::ROLEU_LIST_DATA_RETENTION => [
                        'name'        => 'List data retention policies',
                        'description' => 'Allow user to list data retention policies',
                    ],
                    PHS_Roles::ROLEU_MANAGE_DATA_RETENTION => [
                        'name'        => 'Manage data retention policies',
                        'description' => 'Allow user to manage data retention policies',
                    ],

                    // Plugins...
                    PHS_Roles::ROLEU_EXPORT_PLUGINS_SETTINGS => [
                        'name'        => 'Export plugin settings',
                        'description' => 'Allow user to export plugins settings',
                    ],
                    PHS_Roles::ROLEU_IMPORT_PLUGINS_SETTINGS => [
                        'name'        => 'Import plugin settings',
                        'description' => 'Allow user to import plugins settings',
                    ],
                ],
            ],

            PHS_Roles::ROLE_ADMIN => [
                'name'        => 'Admin accounts',
                'description' => 'Role assigned to admin accounts.',
                'role_units'  => [
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

                    // HTTP calls...
                    PHS_Roles::ROLEU_LIST_HTTP_CALLS => [
                        'name'        => 'List HTTP calls',
                        'description' => 'Allow user to list HTTP calls',
                    ],
                    PHS_Roles::ROLEU_MANAGE_HTTP_CALLS => [
                        'name'        => 'Manage HTTP calls',
                        'description' => 'Allow user to manage HTTP calls',
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
    public function can_admin_manage_roles(bool | null | int | array $user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_MANAGE_ROLES, null, $user_data);
    }

    public function can_admin_list_roles(bool | null | int | array $user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_LIST_ROLES, null, $user_data);
    }

    public function can_admin_manage_plugins(bool | null | int | array $user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_MANAGE_PLUGINS, null, $user_data);
    }

    public function can_admin_list_plugins(bool | null | int | array $user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_LIST_PLUGINS, null, $user_data);
    }

    public function can_admin_import_plugins_settings(bool | null | int | array $user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_IMPORT_PLUGINS_SETTINGS, null, $user_data);
    }

    public function can_admin_export_plugins_settings(bool | null | int | array $user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_EXPORT_PLUGINS_SETTINGS, null, $user_data);
    }

    public function can_admin_manage_accounts(bool | null | int | array $user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_MANAGE_ACCOUNTS, null, $user_data);
    }

    public function can_admin_list_accounts(bool | null | int | array $user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_LIST_ACCOUNTS, null, $user_data);
    }

    public function can_admin_login_subaccounts(bool | null | int | array $user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_LOGIN_SUBACCOUNT, null, $user_data);
    }

    public function can_admin_export_accounts(bool | null | int | array $user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_EXPORT_ACCOUNTS, null, $user_data);
    }

    public function can_admin_import_accounts(bool | null | int | array $user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_IMPORT_ACCOUNTS, null, $user_data);
    }

    public function can_admin_manage_agent_jobs(bool | null | int | array $user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_MANAGE_AGENT_JOBS, null, $user_data);
    }

    public function can_admin_list_agent_jobs(bool | null | int | array $user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_LIST_AGENT_JOBS, null, $user_data);
    }

    public function can_admin_manage_api_keys(bool | null | int | array $user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_MANAGE_API_KEYS, null, $user_data);
    }

    public function can_admin_list_api_keys(bool | null | int | array $user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_LIST_API_KEYS, null, $user_data);
    }

    public function can_admin_view_api_monitoring_report(bool | null | int | array $user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_API_MONITORING_REPORT, null, $user_data);
    }

    public function can_admin_view_logs(bool | null | int | array $user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_VIEW_LOGS, null, $user_data);
    }

    public function can_admin_list_tenants(bool | null | int | array $user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_TENANTS_LIST, null, $user_data);
    }

    public function can_admin_manage_tenants(bool | null | int | array $user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_TENANTS_MANAGE, null, $user_data);
    }

    public function can_admin_list_migrations(bool | null | int | array $user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_LIST_MIGRATIONS, null, $user_data);
    }

    public function can_admin_manage_migrations(bool | null | int | array $user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_MANAGE_MIGRATIONS, null, $user_data);
    }

    public function can_admin_list_data_retention(bool | null | int | array $user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_LIST_DATA_RETENTION, null, $user_data);
    }

    public function can_admin_manage_data_retention(bool | null | int | array $user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_MANAGE_DATA_RETENTION, null, $user_data);
    }

    public function can_admin_list_http_calls(bool | null | int | array $user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_LIST_HTTP_CALLS, null, $user_data);
    }

    public function can_admin_manage_http_calls(bool | null | int | array $user_data = null) : bool
    {
        return can(PHS_Roles::ROLEU_MANAGE_HTTP_CALLS, null, $user_data);
    }
    // endregion Can_* section

    public function listen_after_left_menu_admin(PHS_Event_Layout $event_obj) : bool
    {
        $event_obj->set_output('buffer',
            ($event_obj->get_output('buffer') ?? '')
            .($this->quick_render_template_for_buffer('left_menu_admin') ?? '')
        );

        return true;
    }

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

    public function get_data_retention_instance() : ?Phs_Data_retention
    {
        static $data_retention_library = null;

        if ($data_retention_library !== null) {
            return $data_retention_library;
        }

        $library_params = [];
        $library_params['full_class_name'] = Phs_Data_retention::class;
        $library_params['as_singleton'] = true;

        /** @var Phs_Data_retention $loaded_library */
        if (!($loaded_library = $this->load_library('phs_data_retention', $library_params))) {
            $this->set_error_if_not_set(self::ERR_LIBRARY, $this->_pt('Error loading data retention library.'));

            return null;
        }

        $data_retention_library = $loaded_library;

        return $data_retention_library;
    }

    public function get_plugin_settings_instance() : ?Phs_Plugin_settings
    {
        static $plugin_settings_library = null;

        if ($plugin_settings_library !== null) {
            return $plugin_settings_library;
        }

        $library_params = [];
        $library_params['full_class_name'] = Phs_Plugin_settings::class;
        $library_params['as_singleton'] = true;

        /** @var Phs_Plugin_settings $loaded_library */
        if (!($loaded_library = $this->load_library('phs_plugin_settings', $library_params))) {
            $this->set_error_if_not_set(self::ERR_LIBRARY, $this->_pt('Error loading plugin settings library.'));

            return null;
        }

        $plugin_settings_library = $loaded_library;

        return $plugin_settings_library;
    }
}
