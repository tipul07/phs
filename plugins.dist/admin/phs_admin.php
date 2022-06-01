<?php

namespace phs\plugins\admin;

use \phs\PHS;
use \phs\PHS_Api;
use \phs\libraries\PHS_Plugin;
use \phs\libraries\PHS_Hooks;
use \phs\libraries\PHS_Roles;
use \phs\libraries\PHS_Params;

class PHS_Plugin_Admin extends PHS_Plugin
{
    const H_ADMIN_LEFT_MENU_ADMIN_AFTER_USERS = 'phs_admin_left_menu_admin_after_users';

    /** @var bool|\phs\plugins\accounts\models\PHS_Model_Accounts $_accounts_model  */
    private $_accounts_model = false;

    private function _load_dependencies()
    {
        $this->reset_error();

        if( empty( $this->_accounts_model )
         && !($this->_accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
        {
            $this->set_error( self::ERR_DEPENDENCIES, $this->_pt( 'Error loading required resources.' ) );
            return false;
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function get_settings_structure()
    {
        return [
            'themes_settings_group' => [
                'display_name' => $this->_pt( 'Theme settings' ),
                'display_hint' => $this->_pt( 'How should themes inheritance be used.' ),
                'group_fields' => [
                    'default_theme_in_admin' => [
                        'display_name' => $this->_pt( 'Default theme in admin' ),
                        'display_hint' => $this->_pt( 'Should framework use default theme in admin section?' ),
                        'type' => PHS_Params::T_BOOL,
                        'default' => false,
                    ],
                    'current_theme_as_default_in_admin' => [
                        'display_name' => $this->_pt( 'Current theme as default' ),
                        'display_hint' => $this->_pt( 'If using default theme in admin section, should we set current theme as default (helps with loading resources from current theme if needed in admin interface)' ),
                        'type' => PHS_Params::T_BOOL,
                        'default' => false,
                    ],
                ],
            ],
            'api_settings_group' => [
                'display_name' => $this->_pt( 'API settings' ),
                'display_hint' => $this->_pt( 'Settings related to REST API calls made to this platform.' ),
                'group_fields' => [
                    'allow_api_calls' => [
                        'display_name' => $this->_pt( 'Allow API calls' ),
                        'display_hint' => $this->_pt( 'Are API calls allowed to this platform?' ),
                        'type' => PHS_Params::T_BOOL,
                        'default' => false,
                    ],
                    'allow_api_calls_over_http' => [
                        'display_name' => $this->_pt( 'Allow HTTP API calls' ),
                        'display_hint' => $this->_pt( 'Allow API calls over HTTP? If this checkbox is not ticked only HTTPS calls will be accepted.' ),
                        'type' => PHS_Params::T_BOOL,
                        'default' => false,
                    ],
                    'api_can_simulate_web' => [
                        'display_name' => $this->_pt( 'API calls WEB emulation' ),
                        'display_hint' => $this->_pt( 'Allow API calls to simulate a normal web call by interpreting JSON body as POST variables. (should send %s=1 as query parameter)', PHS_Api::PARAM_WEB_SIMULATION ),
                        'type' => PHS_Params::T_BOOL,
                        'default' => false,
                    ],
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function get_roles_definition()
    {
        $return_arr = [
            PHS_Roles::ROLE_GUEST => [
                'name' => 'Guests',
                'description' => 'Role used by non-logged visitors',
                'role_units' => [
                    PHS_Roles::ROLEU_CONTACT_US => [
                        'name' => 'Contact Us',
                        'description' => 'Allow user to use contact us form',
                    ],
                    PHS_Roles::ROLEU_REGISTER => [
                        'name' => 'Register',
                        'description' => 'Allow user to use registration form',
                    ],
                ],
            ],

            PHS_Roles::ROLE_MEMBER => [
                'name' => 'Member accounts',
                'description' => 'Default functionality role (what normal members can do)',
                'role_units' => [
                    PHS_Roles::ROLEU_CONTACT_US => [
                        'name' => 'Contact Us',
                        'description' => 'Allow user to use contact us form',
                    ],
                ],
            ],

            PHS_Roles::ROLE_OPERATOR => [
                'name' => 'Operator accounts',
                'description' => 'Role assigned to operator accounts.',
                'role_units' => [

                    // Roles...
                    PHS_Roles::ROLEU_LIST_ROLES => [
                        'name' => 'List roles',
                        'description' => 'Allow user to view defined roles',
                    ],

                    // Plugins...
                    PHS_Roles::ROLEU_LIST_PLUGINS => [
                        'name' => 'List plugins',
                        'description' => 'Allow user to list plugins',
                    ],

                    // Agent...
                    PHS_Roles::ROLEU_LIST_AGENT_JOBS => [
                        'name' => 'List agent jobs',
                        'description' => 'Allow user to list agent jobs',
                    ],

                    // API keys...
                    PHS_Roles::ROLEU_LIST_API_KEYS => [
                        'name' => 'List API keys',
                        'description' => 'Allow user to list API keys',
                    ],

                    // Logs...
                    PHS_Roles::ROLEU_VIEW_LOGS => [
                        'name' => 'View system logs',
                        'description' => 'Allow user to view system logs',
                    ],

                    // Accounts...
                    PHS_Roles::ROLEU_LIST_ACCOUNTS => [
                        'name' => 'List accounts',
                        'description' => 'Allow user to list accounts',
                    ],
                ],
            ],

            PHS_Roles::ROLE_ADMIN => [
                'name' => 'Admin accounts',
                'description' => 'Role assigned to admin accounts.',
                'role_units' => [

                    // Roles...
                    PHS_Roles::ROLEU_MANAGE_ROLES => [
                        'name' => 'Manage roles',
                        'description' => 'Allow user to define or edit roles',
                    ],
                    PHS_Roles::ROLEU_LIST_ROLES => [
                        'name' => 'List roles',
                        'description' => 'Allow user to view defined roles',
                    ],

                    // Plugins...
                    PHS_Roles::ROLEU_MANAGE_PLUGINS => [
                        'name' => 'Manage plugins',
                        'description' => 'Allow user to manage plugins',
                    ],
                    PHS_Roles::ROLEU_LIST_PLUGINS => [
                        'name' => 'List plugins',
                        'description' => 'Allow user to list plugins',
                    ],
                    PHS_Roles::ROLEU_EXPORT_PLUGINS_SETTINGS => [
                        'name' => 'Export plugin settings',
                        'description' => 'Allow user to export plugins settings',
                    ],
                    PHS_Roles::ROLEU_IMPORT_PLUGINS_SETTINGS => [
                        'name' => 'Import plugin settings',
                        'description' => 'Allow user to import plugins settings',
                    ],

                    // Agent...
                    PHS_Roles::ROLEU_MANAGE_AGENT_JOBS => [
                        'name' => 'Manage agent jobs',
                        'description' => 'Allow user to manage agent jobs',
                    ],
                    PHS_Roles::ROLEU_LIST_AGENT_JOBS => [
                        'name' => 'List agent jobs',
                        'description' => 'Allow user to list agent jobs',
                    ],

                    // API keys...
                    PHS_Roles::ROLEU_MANAGE_API_KEYS => [
                        'name' => 'Manage API keys',
                        'description' => 'Allow user to manage API keys',
                    ],
                    PHS_Roles::ROLEU_LIST_API_KEYS => [
                        'name' => 'List API keys',
                        'description' => 'Allow user to list API keys',
                    ],

                    // Logs...
                    PHS_Roles::ROLEU_VIEW_LOGS => [
                        'name' => 'View system logs',
                        'description' => 'Allow user to view system logs',
                    ],

                    // Accounts...
                    PHS_Roles::ROLEU_MANAGE_ACCOUNTS => [
                        'name' => 'Manage accounts',
                        'description' => 'Allow user to manage accounts',
                    ],
                    PHS_Roles::ROLEU_LIST_ACCOUNTS => [
                        'name' => 'List accounts',
                        'description' => 'Allow user to list accounts',
                    ],
                    PHS_Roles::ROLEU_LOGIN_SUBACCOUNT => [
                        'name' => 'Login sub-account',
                        'description' => 'Allow user to login as other user',
                    ],
                    PHS_Roles::ROLEU_EXPORT_ACCOUNTS => [
                        'name' => 'Accounts Export',
                        'description' => 'Allow user to export user accounts',
                    ],
                    PHS_Roles::ROLEU_IMPORT_ACCOUNTS => [
                        'name' => 'Accounts Import',
                        'description' => 'Allow user to import user accounts',
                    ],
                ],
            ],
        ];

        $return_arr[PHS_Roles::ROLE_OPERATOR]['role_units'] = self::validate_array( $return_arr[PHS_Roles::ROLE_OPERATOR]['role_units'],
            self::validate_array( $return_arr[PHS_Roles::ROLE_MEMBER]['role_units'], $return_arr[PHS_Roles::ROLE_GUEST]['role_units'] ) );

        $return_arr[PHS_Roles::ROLE_ADMIN]['role_units'] = self::validate_array( $return_arr[PHS_Roles::ROLE_ADMIN]['role_units'],
            self::validate_array( $return_arr[PHS_Roles::ROLE_MEMBER]['role_units'], $return_arr[PHS_Roles::ROLE_GUEST]['role_units'] ) );

        return $return_arr;
    }

    /**
     * @param int|array $user_data
     *
     * @return array|bool
     */
    public function can_admin_manage_roles( $user_data )
    {
        if( empty( $user_data )
         || !$this->_load_dependencies()
         || !($accounts_model = $this->_accounts_model)
         || !($user_arr = $accounts_model->data_to_array( $user_data ))
         || !PHS_Roles::user_has_role_units( $user_arr, PHS_Roles::ROLEU_MANAGE_ROLES ) )
            return false;

        return $user_arr;
    }

    /**
     * @param int|array $user_data
     *
     * @return array|bool
     */
    public function can_admin_list_roles( $user_data )
    {
        if( empty( $user_data )
         || !$this->_load_dependencies()
         || !($accounts_model = $this->_accounts_model)
         || !($user_arr = $accounts_model->data_to_array( $user_data ))
         || !PHS_Roles::user_has_role_units( $user_arr, PHS_Roles::ROLEU_LIST_ROLES ) )
            return false;

        return $user_arr;
    }

    /**
     * @param int|array $user_data
     *
     * @return array|bool
     */
    public function can_admin_manage_plugins( $user_data )
    {
        if( empty( $user_data )
         || !$this->_load_dependencies()
         || !($accounts_model = $this->_accounts_model)
         || !($user_arr = $accounts_model->data_to_array( $user_data ))
         || !PHS_Roles::user_has_role_units( $user_arr, PHS_Roles::ROLEU_MANAGE_PLUGINS ) )
            return false;

        return $user_arr;
    }

    /**
     * @param int|array $user_data
     *
     * @return array|bool
     */
    public function can_admin_list_plugins( $user_data )
    {
        if( empty( $user_data )
         || !$this->_load_dependencies()
         || !($accounts_model = $this->_accounts_model)
         || !($user_arr = $accounts_model->data_to_array( $user_data ))
         || !PHS_Roles::user_has_role_units( $user_arr, PHS_Roles::ROLEU_LIST_PLUGINS ) )
            return false;

        return $user_arr;
    }

    /**
     * @param int|array $user_data
     *
     * @return array|bool
     */
    public function can_admin_import_plugins_settings( $user_data )
    {
        if( empty( $user_data )
         || !$this->_load_dependencies()
         || !($accounts_model = $this->_accounts_model)
         || !($user_arr = $accounts_model->data_to_array( $user_data ))
         || !PHS_Roles::user_has_role_units( $user_arr, PHS_Roles::ROLEU_IMPORT_PLUGINS_SETTINGS ) )
            return false;

        return $user_arr;
    }

    /**
     * @param int|array $user_data
     *
     * @return array|bool
     */
    public function can_admin_export_plugins_settings( $user_data )
    {
        if( empty( $user_data )
         || !$this->_load_dependencies()
         || !($accounts_model = $this->_accounts_model)
         || !($user_arr = $accounts_model->data_to_array( $user_data ))
         || !PHS_Roles::user_has_role_units( $user_arr, PHS_Roles::ROLEU_EXPORT_PLUGINS_SETTINGS ) )
            return false;

        return $user_arr;
    }

    /**
     * @param int|array $user_data
     *
     * @return array|bool
     */
    public function can_admin_manage_accounts( $user_data )
    {
        if( empty( $user_data )
         || !$this->_load_dependencies()
         || !($accounts_model = $this->_accounts_model)
         || !($user_arr = $accounts_model->data_to_array( $user_data ))
         || !PHS_Roles::user_has_role_units( $user_arr, PHS_Roles::ROLEU_MANAGE_ACCOUNTS ) )
            return false;

        return $user_arr;
    }

    /**
     * @param int|array $user_data
     *
     * @return array|bool
     */
    public function can_admin_list_accounts( $user_data )
    {
        if( empty( $user_data )
         || !$this->_load_dependencies()
         || !($accounts_model = $this->_accounts_model)
         || !($user_arr = $accounts_model->data_to_array( $user_data ))
         || !PHS_Roles::user_has_role_units( $user_arr, PHS_Roles::ROLEU_LIST_ACCOUNTS ) )
            return false;

        return $user_arr;
    }

    /**
     * @param int|array $user_data
     *
     * @return array|bool
     */
    public function can_admin_login_subaccounts( $user_data )
    {
        if( empty( $user_data )
         || !$this->_load_dependencies()
         || !($accounts_model = $this->_accounts_model)
         || !($user_arr = $accounts_model->data_to_array( $user_data ))
         || !PHS_Roles::user_has_role_units( $user_arr, PHS_Roles::ROLEU_LOGIN_SUBACCOUNT ) )
            return false;

        return $user_arr;
    }

    /**
     * @param int|array $user_data
     *
     * @return array|bool
     */
    public function can_admin_export_accounts( $user_data )
    {
        if( empty( $user_data )
         || !$this->_load_dependencies()
         || !($accounts_model = $this->_accounts_model)
         || !($user_arr = $accounts_model->data_to_array( $user_data ))
         || !PHS_Roles::user_has_role_units( $user_arr, PHS_Roles::ROLEU_EXPORT_ACCOUNTS ) )
            return false;

        return $user_arr;
    }

    /**
     * @param int|array $user_data
     *
     * @return array|bool
     */
    public function can_admin_import_accounts( $user_data )
    {
        if( empty( $user_data )
         || !$this->_load_dependencies()
         || !($accounts_model = $this->_accounts_model)
         || !($user_arr = $accounts_model->data_to_array( $user_data ))
         || !PHS_Roles::user_has_role_units( $user_arr, PHS_Roles::ROLEU_IMPORT_ACCOUNTS ) )
            return false;

        return $user_arr;
    }

    /**
     * @param int|array $user_data
     *
     * @return array|bool
     */
    public function can_admin_manage_agent_jobs( $user_data )
    {
        if( empty( $user_data )
         || !$this->_load_dependencies()
         || !($accounts_model = $this->_accounts_model)
         || !($user_arr = $accounts_model->data_to_array( $user_data ))
         || !PHS_Roles::user_has_role_units( $user_arr, PHS_Roles::ROLEU_MANAGE_AGENT_JOBS ) )
            return false;

        return $user_arr;
    }

    /**
     * @param int|array $user_data
     *
     * @return array|bool
     */
    public function can_admin_list_agent_jobs( $user_data )
    {
        if( empty( $user_data )
         || !$this->_load_dependencies()
         || !($accounts_model = $this->_accounts_model)
         || !($user_arr = $accounts_model->data_to_array( $user_data ))
         || !PHS_Roles::user_has_role_units( $user_arr, PHS_Roles::ROLEU_LIST_AGENT_JOBS ) )
            return false;

        return $user_arr;
    }

    /**
     * @param int|array $user_data
     *
     * @return array|bool
     */
    public function can_admin_manage_api_keys( $user_data )
    {
        if( empty( $user_data )
         || !$this->_load_dependencies()
         || !($accounts_model = $this->_accounts_model)
         || !($user_arr = $accounts_model->data_to_array( $user_data ))
         || !PHS_Roles::user_has_role_units( $user_arr, PHS_Roles::ROLEU_MANAGE_API_KEYS ) )
            return false;

        return $user_arr;
    }

    /**
     * @param int|array $user_data
     *
     * @return array|bool
     */
    public function can_admin_list_api_keys( $user_data )
    {
        if( empty( $user_data )
         || !$this->_load_dependencies()
         || !($accounts_model = $this->_accounts_model)
         || !($user_arr = $accounts_model->data_to_array( $user_data ))
         || !PHS_Roles::user_has_role_units( $user_arr, PHS_Roles::ROLEU_LIST_API_KEYS ) )
            return false;

        return $user_arr;
    }

    /**
     * @param int|array $user_data
     *
     * @return array|bool
     */
    public function can_admin_view_logs( $user_data )
    {
        if( empty( $user_data )
         || !$this->_load_dependencies()
         || !($accounts_model = $this->_accounts_model)
         || !($user_arr = $accounts_model->data_to_array( $user_data ))
         || !PHS_Roles::user_has_role_units( $user_arr, PHS_Roles::ROLEU_VIEW_LOGS ) )
            return false;

        return $user_arr;
    }

    /**
     * @param bool|array $hook_args
     *
     * @return array
     */
    public function trigger_after_left_menu_admin( $hook_args = false )
    {
        $hook_args = self::validate_array( $hook_args, PHS_Hooks::default_buffer_hook_args() );

        $data = [];

        $hook_args['buffer'] = $this->quick_render_template_for_buffer( 'left_menu_admin', $data );

        return $hook_args;
    }

    /**
     * @param bool|array $hook_args
     *
     * @return array|bool
     */
    public function trigger_web_template_rendering( $hook_args = false )
    {
        $hook_args = self::validate_array( $hook_args, PHS_Hooks::default_page_location_hook_args() );

        if( !empty( $hook_args ) && !empty( $hook_args['page_template'] ) )
        {
            if( $hook_args['page_template'] === 'template_admin'
             && ($current_theme = PHS::get_theme()) !== 'default'
             && ($settings_arr = $this->get_plugin_settings()) )
            {
                PHS::set_theme( 'default' );

                if( !empty( $settings_arr['current_theme_as_default_in_admin'] )
                 && !empty( $current_theme ) )
                    PHS::set_defaut_theme( $current_theme );
            }
        }

        return $hook_args;
    }
}
