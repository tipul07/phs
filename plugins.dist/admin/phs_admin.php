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
    /**
     * @inheritdoc
     */
    public function get_settings_structure()
    {
        return array(
            'themes_settings_group' => array(
                'display_name' => $this->_pt( 'Theme settings' ),
                'display_hint' => $this->_pt( 'How should themes inheritance be used.' ),
                'group_fields' => array(
                    'default_theme_in_admin' => array(
                        'display_name' => $this->_pt( 'Default theme in admin' ),
                        'display_hint' => $this->_pt( 'Should framework use default theme in admin section?' ),
                        'type' => PHS_Params::T_BOOL,
                        'default' => false,
                    ),
                    'current_theme_as_default_in_admin' => array(
                        'display_name' => $this->_pt( 'Current theme as default' ),
                        'display_hint' => $this->_pt( 'If using default theme in admin section, should we set current theme as default (helps with loading resources from current theme if needed in admin interface)' ),
                        'type' => PHS_Params::T_BOOL,
                        'default' => false,
                    ),
                ),
            ),
            'api_settings_group' => array(
                'display_name' => $this->_pt( 'API settings' ),
                'display_hint' => $this->_pt( 'Settings related to REST API calls made to this platform.' ),
                'group_fields' => array(
                    'allow_api_calls' => array(
                        'display_name' => $this->_pt( 'Allow API calls' ),
                        'display_hint' => $this->_pt( 'Are API calls allowed to this platform?' ),
                        'type' => PHS_Params::T_BOOL,
                        'default' => false,
                    ),
                    'allow_api_calls_over_http' => array(
                        'display_name' => $this->_pt( 'Allow HTTP API calls' ),
                        'display_hint' => $this->_pt( 'Allow API calls over HTTP? If this checkbox is not ticked only HTTPS calls will be accepted.' ),
                        'type' => PHS_Params::T_BOOL,
                        'default' => false,
                    ),
                    'api_can_simulate_web' => array(
                        'display_name' => $this->_pt( 'API calls WEB emulation' ),
                        'display_hint' => $this->_pt( 'Allow API calls to simulate a normal web call by interpreting JSON body as POST variables. (should send %s=1 as query parameter)', PHS_Api::PARAM_WEB_SIMULATION ),
                        'type' => PHS_Params::T_BOOL,
                        'default' => false,
                    ),
                ),
            ),
        );
    }

    /**
     * @inheritdoc
     */
    public function get_roles_definition()
    {
        $return_arr = array(
            PHS_Roles::ROLE_GUEST => array(
                'name' => 'Guests',
                'description' => 'Role used by non-logged visitors',
                'role_units' => array(
                    PHS_Roles::ROLEU_CONTACT_US => array(
                        'name' => 'Contact Us',
                        'description' => 'Allow user to use contact us form',
                    ),
                    PHS_Roles::ROLEU_REGISTER => array(
                        'name' => 'Register',
                        'description' => 'Allow user to use registration form',
                    ),
                ),
            ),

            PHS_Roles::ROLE_MEMBER => array(
                'name' => 'Member accounts',
                'description' => 'Default functionality role (what normal members can do)',
                'role_units' => array(
                    PHS_Roles::ROLEU_CONTACT_US => array(
                        'name' => 'Contact Us',
                        'description' => 'Allow user to use contact us form',
                    ),
                ),
            ),

            PHS_Roles::ROLE_OPERATOR => array(
                'name' => 'Operator accounts',
                'description' => 'Role assigned to operator accounts.',
                'role_units' => array(

                    // Roles...
                    PHS_Roles::ROLEU_LIST_ROLES => array(
                        'name' => 'List roles',
                        'description' => 'Allow user to view defined roles',
                    ),

                    // Plugins...
                    PHS_Roles::ROLEU_LIST_PLUGINS => array(
                        'name' => 'List plugins',
                        'description' => 'Allow user to list plugins',
                    ),

                    // Agent...
                    PHS_Roles::ROLEU_LIST_AGENT_JOBS => array(
                        'name' => 'List agent jobs',
                        'description' => 'Allow user to list agent jobs',
                    ),

                    // API keys...
                    PHS_Roles::ROLEU_LIST_API_KEYS => array(
                        'name' => 'List API keys',
                        'description' => 'Allow user to list API keys',
                    ),

                    // Logs...
                    PHS_Roles::ROLEU_VIEW_LOGS => array(
                        'name' => 'View system logs',
                        'description' => 'Allow user to view system logs',
                    ),

                    // Accounts...
                    PHS_Roles::ROLEU_LIST_ACCOUNTS => array(
                        'name' => 'List accounts',
                        'description' => 'Allow user to list accounts',
                    ),
                ),
            ),

            PHS_Roles::ROLE_ADMIN => array(
                'name' => 'Admin accounts',
                'description' => 'Role assigned to admin accounts.',
                'role_units' => array(

                    // Roles...
                    PHS_Roles::ROLEU_MANAGE_ROLES => array(
                        'name' => 'Manage roles',
                        'description' => 'Allow user to define or edit roles',
                    ),
                    PHS_Roles::ROLEU_LIST_ROLES => array(
                        'name' => 'List roles',
                        'description' => 'Allow user to view defined roles',
                    ),

                    // Plugins...
                    PHS_Roles::ROLEU_MANAGE_PLUGINS => array(
                        'name' => 'Manage plugins',
                        'description' => 'Allow user to manage plugins',
                    ),
                    PHS_Roles::ROLEU_LIST_PLUGINS => array(
                        'name' => 'List plugins',
                        'description' => 'Allow user to list plugins',
                    ),

                    // Agent...
                    PHS_Roles::ROLEU_MANAGE_AGENT_JOBS => array(
                        'name' => 'Manage agent jobs',
                        'description' => 'Allow user to manage agent jobs',
                    ),
                    PHS_Roles::ROLEU_LIST_AGENT_JOBS => array(
                        'name' => 'List agent jobs',
                        'description' => 'Allow user to list agent jobs',
                    ),

                    // API keys...
                    PHS_Roles::ROLEU_MANAGE_API_KEYS => array(
                        'name' => 'Manage API keys',
                        'description' => 'Allow user to manage API keys',
                    ),
                    PHS_Roles::ROLEU_LIST_API_KEYS => array(
                        'name' => 'List API keys',
                        'description' => 'Allow user to list API keys',
                    ),

                    // Logs...
                    PHS_Roles::ROLEU_VIEW_LOGS => array(
                        'name' => 'View system logs',
                        'description' => 'Allow user to view system logs',
                    ),

                    // Accounts...
                    PHS_Roles::ROLEU_MANAGE_ACCOUNTS => array(
                        'name' => 'Manage accounts',
                        'description' => 'Allow user to manage accounts',
                    ),
                    PHS_Roles::ROLEU_LIST_ACCOUNTS => array(
                        'name' => 'List accounts',
                        'description' => 'Allow user to list accounts',
                    ),
                    PHS_Roles::ROLEU_LOGIN_SUBACCOUNT => array(
                        'name' => 'Login sub-account',
                        'description' => 'Allow user to login as other user',
                    ),
                ),
            ),
        );

        $return_arr[PHS_Roles::ROLE_OPERATOR]['role_units'] = self::validate_array( $return_arr[PHS_Roles::ROLE_OPERATOR]['role_units'],
            self::validate_array( $return_arr[PHS_Roles::ROLE_MEMBER]['role_units'], $return_arr[PHS_Roles::ROLE_GUEST]['role_units'] ) );

        $return_arr[PHS_Roles::ROLE_ADMIN]['role_units'] = self::validate_array( $return_arr[PHS_Roles::ROLE_ADMIN]['role_units'],
            self::validate_array( $return_arr[PHS_Roles::ROLE_MEMBER]['role_units'], $return_arr[PHS_Roles::ROLE_GUEST]['role_units'] ) );

        return $return_arr;
    }

    /**
     * @param bool|array $hook_args
     *
     * @return array
     */
    public function trigger_after_left_menu_admin( $hook_args = false )
    {
        $hook_args = self::validate_array( $hook_args, PHS_Hooks::default_buffer_hook_args() );

        $data = array();

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

        if( !empty( $hook_args ) and !empty( $hook_args['page_template'] ) )
        {
            if( $hook_args['page_template'] === 'template_admin'
            and ($current_theme = PHS::get_theme()) !== 'default'
            and ($settings_arr = $this->get_plugin_settings()) )
            {
                PHS::set_theme( 'default' );

                if( !empty( $settings_arr['current_theme_as_default_in_admin'] )
                and !empty( $current_theme ) )
                    PHS::set_defaut_theme( $current_theme );
            }
        }

        return $hook_args;
    }
}
