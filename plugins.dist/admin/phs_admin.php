<?php

namespace phs\plugins\admin;

use \phs\PHS;
use \phs\libraries\PHS_Plugin;
use \phs\libraries\PHS_Hooks;
use \phs\libraries\PHS_Roles;

class PHS_Plugin_Admin extends PHS_Plugin
{
    /**
     * @return string Returns version of model
     */
    public function get_plugin_version()
    {
        return '1.0.4';
    }

    /**
     * @return array Returns an array with plugin details populated array returned by default_plugin_details_fields() method
     */
    public function get_plugin_details()
    {
        return array(
            'name' => 'Administration Plugin',
            'description' => 'Handles all administration actions.',
        );
    }

    public function get_models()
    {
        return array();
    }

    /**
     * @inheritdoc
     */
    public function get_roles_definition()
    {
        $return_arr = array(
            PHS_Roles::ROLE_GUEST => array(
                'name' => $this->_pt( 'Guests' ),
                'description' => $this->_pt( 'Role used by non-logged visitors' ),
                'role_units' => array(
                    PHS_Roles::ROLEU_CONTACT_US => array(
                        'name' => $this->_pt( 'Contact Us' ),
                        'description' => $this->_pt( 'Allow user to use contact us form' ),
                    ),
                    PHS_Roles::ROLEU_REGISTER => array(
                        'name' => $this->_pt( 'Register' ),
                        'description' => $this->_pt( 'Allow user to use registration form' ),
                    ),
                ),
            ),

            PHS_Roles::ROLE_MEMBER => array(
                'name' => $this->_pt( 'Member accounts' ),
                'description' => $this->_pt( 'Default functionality role (what normal members can do)' ),
                'role_units' => array(
                    PHS_Roles::ROLEU_CONTACT_US => array(
                        'name' => $this->_pt( 'Contact Us' ),
                        'description' => $this->_pt( 'Allow user to use contact us form' ),
                    ),
                ),
            ),

            PHS_Roles::ROLE_OPERATOR => array(
                'name' => $this->_pt( 'Admin accounts' ),
                'description' => $this->_pt( 'Role assigned to admin accounts.' ),
                'role_units' => array(

                    // Roles...
                    PHS_Roles::ROLEU_LIST_ROLES => array(
                        'name' => $this->_pt( 'List roles' ),
                        'description' => $this->_pt( 'Allow user to view defined roles' ),
                    ),

                    // Plugins...
                    PHS_Roles::ROLEU_LIST_PLUGINS => array(
                        'name' => $this->_pt( 'List plugins' ),
                        'description' => $this->_pt( 'Allow user to list plugins' ),
                    ),

                    // Agent...
                    PHS_Roles::ROLEU_LIST_AGENT_JOBS => array(
                        'name' => $this->_pt( 'List agent jobs' ),
                        'description' => $this->_pt( 'Allow user to list agent jobs' ),
                    ),

                    // Logs...
                    PHS_Roles::ROLEU_VIEW_LOGS => array(
                        'name' => $this->_pt( 'View system logs' ),
                        'description' => $this->_pt( 'Allow user to view system logs' ),
                    ),

                    // Accounts...
                    PHS_Roles::ROLEU_LIST_ACCOUNTS => array(
                        'name' => $this->_pt( 'List accounts' ),
                        'description' => $this->_pt( 'Allow user to list accounts' ),
                    ),
                ),
            ),

            PHS_Roles::ROLE_ADMIN => array(
                'name' => $this->_pt( 'Admin accounts' ),
                'description' => $this->_pt( 'Role assigned to admin accounts.' ),
                'role_units' => array(

                    // Roles...
                    PHS_Roles::ROLEU_MANAGE_ROLES => array(
                        'name' => $this->_pt( 'Manage roles' ),
                        'description' => $this->_pt( 'Allow user to define or edit roles' ),
                    ),
                    PHS_Roles::ROLEU_LIST_ROLES => array(
                        'name' => $this->_pt( 'List roles' ),
                        'description' => $this->_pt( 'Allow user to view defined roles' ),
                    ),

                    // Plugins...
                    PHS_Roles::ROLEU_MANAGE_PLUGINS => array(
                        'name' => $this->_pt( 'Manage plugins' ),
                        'description' => $this->_pt( 'Allow user to manage plugins' ),
                    ),
                    PHS_Roles::ROLEU_LIST_PLUGINS => array(
                        'name' => $this->_pt( 'List plugins' ),
                        'description' => $this->_pt( 'Allow user to list plugins' ),
                    ),

                    // Agent...
                    PHS_Roles::ROLEU_MANAGE_AGENT_JOBS => array(
                        'name' => $this->_pt( 'Manage agent jobs' ),
                        'description' => $this->_pt( 'Allow user to manage agent jobs' ),
                    ),
                    PHS_Roles::ROLEU_LIST_AGENT_JOBS => array(
                        'name' => $this->_pt( 'List agent jobs' ),
                        'description' => $this->_pt( 'Allow user to list agent jobs' ),
                    ),

                    // Logs...
                    PHS_Roles::ROLEU_VIEW_LOGS => array(
                        'name' => $this->_pt( 'View system logs' ),
                        'description' => $this->_pt( 'Allow user to view system logs' ),
                    ),

                    // Accounts...
                    PHS_Roles::ROLEU_MANAGE_ACCOUNTS => array(
                        'name' => $this->_pt( 'Manage accounts' ),
                        'description' => $this->_pt( 'Allow user to manage accounts' ),
                    ),
                    PHS_Roles::ROLEU_LIST_ACCOUNTS => array(
                        'name' => $this->_pt( 'List accounts' ),
                        'description' => $this->_pt( 'Allow user to list accounts' ),
                    ),
                    PHS_Roles::ROLEU_LOGIN_SUBACCOUNT => array(
                        'name' => $this->_pt( 'Login sub-account' ),
                        'description' => $this->_pt( 'Allow user to login as other user' ),
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

    public function trigger_after_left_menu_admin( $hook_args = false )
    {
        $hook_args = self::validate_array( $hook_args, PHS_Hooks::default_buffer_hook_args() );

        $data = array();

        $hook_args['buffer'] = $this->quick_render_template_for_buffer( 'left_menu_admin', $data );

        return $hook_args;
    }

}
