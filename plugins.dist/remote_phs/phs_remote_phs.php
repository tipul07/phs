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
use phs\plugins\accounts\models\PHS_Model_Accounts;
use phs\system\core\events\layout\PHS_Event_Layout;

class PHS_Plugin_Remote_phs extends PHS_Plugin
{
    public const LOG_CHANNEL = 'phs_remote.log';

    public const ROLE_OPERATOR = 'phs_remote_operator', ROLE_MANAGER = 'phs_remote_manager';

    public const ROLEU_ADM_LIST_DOMAINS = 'phs_remote_adm_list_domains', ROLEU_ADM_MANAGE_DOMAINS = 'phs_remote_adm_manage_domains',
        ROLEU_ADM_PING_DOMAIN = 'phs_remote_adm_ping_domain',
        ROLEU_ADM_LIST_LOGS = 'phs_remote_adm_list_logs', ROLEU_ADM_MANAGE_LOGS = 'phs_remote_adm_manage_logs';

    private ?PHS_Model_Accounts $_accounts_model = null;

    //
    // region is_* and can_* functions
    //
    public function is_operator(int | array $user_data) : bool
    {
        return !empty($user_data)
               && $this->_load_dependencies()
               && ($user_arr = $this->_accounts_model->data_to_array($user_data))
               && PHS_Roles::user_has_role($user_arr, self::ROLE_OPERATOR);
    }

    public function is_manager(int | array $user_data) : bool
    {
        return !empty($user_data)
               && $this->_load_dependencies()
               && ($user_arr = $this->_accounts_model->data_to_array($user_data))
               && PHS_Roles::user_has_role($user_arr, self::ROLE_MANAGER);
    }

    public function can_admin_list_domains(bool | null | int | array $user_data = null) : bool
    {
        return can(self::ROLEU_ADM_LIST_DOMAINS, null, $user_data);
    }

    public function can_admin_manage_domains(bool | null | int | array $user_data = null) : bool
    {
        return can(self::ROLEU_ADM_MANAGE_DOMAINS, null, $user_data);
    }

    public function can_admin_ping_domains(bool | null | int | array $user_data = null) : bool
    {
        return can(self::ROLEU_ADM_PING_DOMAIN, null, $user_data);
    }

    public function can_admin_list_logs(bool | null | int | array $user_data = null) : bool
    {
        return can(self::ROLEU_ADM_LIST_LOGS, null, $user_data);
    }

    public function can_admin_manage_logs(bool | null | int | array $user_data = null) : bool
    {
        return can(self::ROLEU_ADM_MANAGE_LOGS, null, $user_data);
    }
    //
    // endregion is_* and can_* functions
    //

    //
    // region Manage platform rights and roles
    //
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
    public function listen_after_left_menu_admin(PHS_Event_Layout $event_obj) : bool
    {
        $event_obj->append_to_buffer($this->quick_render_template_for_buffer('layout/left_menu_admin') ?? '');

        return true;
    }

    /**
     * @param bool|array $hook_args
     *
     * @return array|bool
     */
    public function trigger_assign_registration_roles($hook_args = false)
    {
        $hook_args = self::validate_array($hook_args, PHS_Hooks::default_user_registration_roles_hook_args());

        if (empty($hook_args['account_data'])
            || !$this->_load_dependencies()
            || !($account_arr = $this->_accounts_model->data_to_array($hook_args['account_data']))) {
            return $hook_args;
        }

        if (empty($hook_args['roles_arr'])) {
            $hook_args['roles_arr'] = [];
        }

        if ($this->_accounts_model->acc_is_developer($account_arr)) {
            $hook_args['roles_arr'][] = self::ROLE_MANAGER;
        } elseif ($this->_accounts_model->acc_is_admin($account_arr)) {
            $hook_args['roles_arr'][] = self::ROLE_OPERATOR;
        }

        return $hook_args;
    }

    private function _load_dependencies() : bool
    {
        $this->reset_error();

        if (empty($this->_accounts_model)
         && !($this->_accounts_model = PHS_Model_Accounts::get_instance())) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error loading accounts model.'));

            return false;
        }

        return true;
    }
    //
    // endregion Triggers
    //
}
