<?php

namespace phs\plugins\remote_phs;

use phs\PHS;
use phs\PHS_Api;
use phs\PHS_Crypt;
use phs\PHS_Scope;
use phs\PHS_Session;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Roles;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Plugin;

class PHS_Plugin_Remote_phs extends PHS_Plugin
{
    public const LOG_CHANNEL = 'phs_remote.log';

    public const ROLE_OPERATOR = 'phs_remote_operator', ROLE_MANAGER = 'phs_remote_manager';

    public const ROLEU_ADM_LIST_DOMAINS = 'phs_remote_adm_list_domains', ROLEU_ADM_MANAGE_DOMAINS = 'phs_remote_adm_manage_domains',
        ROLEU_ADM_PING_DOMAIN = 'phs_remote_adm_ping_domain',
        ROLEU_ADM_LIST_LOGS = 'phs_remote_adm_list_logs', ROLEU_ADM_MANAGE_LOGS = 'phs_remote_adm_manage_logs';

    /** @var bool|\phs\plugins\accounts\models\PHS_Model_Accounts */
    private $_accounts_model = false;

    //
    // region is_* and can_* functions
    //
    public function is_operator($user_data)
    {
        if (empty($user_data)
         || !$this->_load_dependencies()
         || !($accounts_model = $this->_accounts_model)
         || !($user_arr = $accounts_model->data_to_array($user_data))
         || !PHS_Roles::user_has_role($user_arr, self::ROLE_OPERATOR)) {
            return false;
        }

        return $user_arr;
    }

    public function is_manager($user_data)
    {
        if (empty($user_data)
         || !$this->_load_dependencies()
         || !($accounts_model = $this->_accounts_model)
         || !($user_arr = $accounts_model->data_to_array($user_data))
         || !PHS_Roles::user_has_role($user_arr, self::ROLE_MANAGER)) {
            return false;
        }

        return $user_arr;
    }

    public function can_admin_list_domains($user_data = null) : bool
    {
        return can(self::ROLEU_ADM_LIST_DOMAINS, null, $user_data);
    }

    public function can_admin_manage_domains($user_data = null) : bool
    {
        return can(self::ROLEU_ADM_MANAGE_DOMAINS, null, $user_data);
    }

    public function can_admin_ping_domains($user_data = null) : bool
    {
        return can(self::ROLEU_ADM_PING_DOMAIN, null, $user_data);
    }

    public function can_admin_list_logs($user_data = null) : bool
    {
        return can(self::ROLEU_ADM_LIST_LOGS, null, $user_data);
    }

    public function can_admin_manage_logs($user_data = null) : bool
    {
        return can(self::ROLEU_ADM_MANAGE_LOGS, null, $user_data);
    }
    //
    // endregion is_* and can_* functions
    //

    //
    // region Manage platform rights and roles
    //
    public function default_user_platform_rights() : array
    {
        return [
            'has_any_rights'        => false,
            'has_any_admin_rights'  => false,
            'has_any_member_rights' => false,
            'member'                => [
            ],
            'admin' => [
                'manage_domains' => false,
                'list_domains'   => false,
                'ping_domains'   => false,
                'manage_logs'    => false,
                'list_logs'      => false,
            ],
        ];
    }

    public function get_user_platform_rights($user_data)
    {
        static $cuser_rights = false;

        if (empty($user_data)
         || !$this->_load_dependencies()
         || !($accounts_model = $this->_accounts_model)
         || !($user_arr = $accounts_model->data_to_array($user_data))) {
            return false;
        }

        if (($cuser_arr = PHS::user_logged_in())) {
            if ((int)$cuser_arr['id'] === (int)$user_arr['id']
             && $cuser_rights !== false) {
                return $cuser_rights;
            }
        }

        $view_rights = $this->default_user_platform_rights();

        if ($this->can_admin_manage_domains($user_arr)) {
            $view_rights['admin']['manage_domains'] = $view_rights['has_any_admin_rights'] = $view_rights['has_any_rights'] = true;
        }
        if ($this->can_admin_list_domains($user_arr)) {
            $view_rights['admin']['list_domains'] = $view_rights['has_any_admin_rights'] = $view_rights['has_any_rights'] = true;
        }
        if ($this->can_admin_ping_domains($user_arr)) {
            $view_rights['admin']['ping_domains'] = $view_rights['has_any_admin_rights'] = $view_rights['has_any_rights'] = true;
        }
        if ($this->can_admin_manage_logs($user_arr)) {
            $view_rights['admin']['manage_logs'] = $view_rights['has_any_admin_rights'] = $view_rights['has_any_rights'] = true;
        }
        if ($this->can_admin_list_logs($user_arr)) {
            $view_rights['admin']['list_logs'] = $view_rights['has_any_admin_rights'] = $view_rights['has_any_rights'] = true;
        }

        if ((int)$cuser_arr['id'] === (int)$user_arr['id']) {
            $cuser_rights = $view_rights;
        }

        return $view_rights;
    }

    /**
     * @inheritdoc
     */
    public function get_roles_definition() : array
    {
        $return_arr = [];

        //
        //  Operator
        //
        $return_arr[self::ROLE_OPERATOR] = [];

        $return_arr[self::ROLE_OPERATOR]['name'] = 'Remote Domains Operator';
        $return_arr[self::ROLE_OPERATOR]['description'] = 'Defines what platform operators can do related to PHS remote domains';

        $return_arr[self::ROLE_OPERATOR]['role_units'][self::ROLEU_ADM_LIST_DOMAINS] = [
            'name'        => 'Remote Domains List (as admin)',
            'description' => 'Gives user rights to view list of PHS remote domains',
        ];

        $return_arr[self::ROLE_OPERATOR]['role_units'][self::ROLEU_ADM_LIST_LOGS] = [
            'name'        => 'Remote Domains Logs List (as admin)',
            'description' => 'Gives user rights to view list logs of PHS remote domains',
        ];
        //
        //  END Operator
        //

        //
        //  Manager
        //
        $return_arr[self::ROLE_MANAGER] = $return_arr[self::ROLE_OPERATOR];

        $return_arr[self::ROLE_MANAGER]['name'] = 'Remote Domains Manager';
        $return_arr[self::ROLE_MANAGER]['description'] = 'Defines what platform manager can do related to PHS remote domains';

        $return_arr[self::ROLE_MANAGER]['role_units'][self::ROLEU_ADM_MANAGE_DOMAINS] = [
            'name'        => 'Remote Domains Management (as admin)',
            'description' => 'Gives user rights to manage PHS remote domains in admin interface',
        ];

        $return_arr[self::ROLE_MANAGER]['role_units'][self::ROLEU_ADM_PING_DOMAIN] = [
            'name'        => 'Remote Domains Ping (as admin)',
            'description' => 'Gives user rights to ping PHS remote domains in admin interface',
        ];

        $return_arr[self::ROLE_MANAGER]['role_units'][self::ROLEU_ADM_MANAGE_LOGS] = [
            'name'        => 'Remote Domains Logs Management (as admin)',
            'description' => 'Gives user rights to manage logs of PHS remote domains in admin interface',
        ];
        //
        //  END Manager
        //

        return $return_arr;
    }
    //
    // endregion Manage platform rights and roles
    //

    /**
     * @inheritdoc
     */
    public function get_settings_structure() : array
    {
        return [
            'enable_remotes' => [
                'display_name' => $this->_pt('Enable Remote Calls'),
                'display_hint' => $this->_pt('Allow remote calls to remote PHS platforms or from remote PHS platforms.'),
                'type'         => PHS_Params::T_BOOL,
                'default'      => false,
            ],
            'allow_remote_calls' => [
                'display_name' => $this->_pt('Allow Incoming Calls'),
                'display_hint' => $this->_pt('Allow remote domains to send actions to this PHS platform.'),
                'type'         => PHS_Params::T_BOOL,
                'default'      => false,
            ],
            'log_outgoing_calls' => [
                'display_name' => $this->_pt('Log All Outgoing Calls'),
                'display_hint' => $this->_pt('For debugging purposes, log each request going out to any remote domains.'),
                'type'         => PHS_Params::T_BOOL,
                'default'      => false,
            ],
        ];
    }

    public function is_remote_enabled() : bool
    {
        return ($settings_arr = $this->get_plugin_settings()) && !empty($settings_arr['enable_remotes']);
    }

    public function is_remote_calls_enabled() : bool
    {
        return ($settings_arr = $this->get_plugin_settings()) && !empty($settings_arr['allow_remote_calls']);
    }

    public function is_accepting_remote_calls() : bool
    {
        return $this->is_remote_enabled() && $this->is_remote_calls_enabled();
    }

    public function log_all_outgoing_calls() : bool
    {
        return ($settings_arr = $this->get_plugin_settings()) && !empty($settings_arr['log_outgoing_calls']);
    }

    //
    // region Triggers
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

        $hook_args['buffer'] = $this->quick_render_template_for_buffer('layout/left_menu_admin', $data);

        return $hook_args;
    }

    /**
     * @param bool|array $hook_args
     *
     * @return array|bool
     */
    public function trigger_assign_registration_roles($hook_args = false)
    {
        $hook_args = self::validate_array($hook_args, PHS_Hooks::default_user_registration_roles_hook_args());

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if (!($accounts_model = PHS::load_model('accounts', 'accounts'))
         || empty($hook_args['account_data'])
         || !($account_arr = $accounts_model->data_to_array($hook_args['account_data']))) {
            return $hook_args;
        }

        if (empty($hook_args['roles_arr'])) {
            $hook_args['roles_arr'] = [];
        }

        if ($accounts_model->acc_is_developer($account_arr)) {
            $hook_args['roles_arr'][] = self::ROLE_MANAGER;
        } elseif ($accounts_model->acc_is_admin($account_arr)) {
            $hook_args['roles_arr'][] = self::ROLE_OPERATOR;
        }

        return $hook_args;
    }

    private function _load_dependencies()
    {
        $this->reset_error();

        if (empty($this->_accounts_model)
         && !($this->_accounts_model = PHS::load_model('accounts', 'accounts'))) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error loading accounts model.'));

            return false;
        }

        return true;
    }
    //
    // endregion Triggers
    //
}
