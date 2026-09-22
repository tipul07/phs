<?php
namespace phs\plugins\remote_phs;

use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Plugin;
use phs\libraries\PHS_Record_data;
use phs\system\core\attributes\PHS_Dependency;
use phs\plugins\accounts\models\PHS_Model_Accounts;
use phs\system\core\events\layout\PHS_Event_Layout;
use phs\system\core\events\accounts\PHS_Event_Accounts_registration_roles;

class PHS_Plugin_Remote_phs extends PHS_Plugin
{
    public const LOG_CHANNEL = 'phs_remote.log';

    public const ROLE_OPERATOR = 'phs_remote_operator', ROLE_MANAGER = 'phs_remote_manager';

    public const ROLEU_ADM_LIST_DOMAINS = 'phs_remote_adm_list_domains', ROLEU_ADM_MANAGE_DOMAINS = 'phs_remote_adm_manage_domains',
        ROLEU_ADM_PING_DOMAIN = 'phs_remote_adm_ping_domain',
        ROLEU_ADM_LIST_LOGS = 'phs_remote_adm_list_logs', ROLEU_ADM_MANAGE_LOGS = 'phs_remote_adm_manage_logs';

    #[PHS_Dependency]
    private ?PHS_Model_Accounts $_accounts_model = null;

    //
    // region is_* and can_* functions
    //
    public function is_operator(bool | null | int | array | PHS_Record_data $user_data = null) : bool
    {
        return has_role(self::ROLE_OPERATOR, $user_data);
    }

    public function is_manager(bool | null | int | array | PHS_Record_data $user_data = null) : bool
    {
        return has_role(self::ROLE_MANAGER, $user_data);
    }

    public function can_admin_list_domains(bool | null | int | array | PHS_Record_data $user_data = null) : bool
    {
        return can(self::ROLEU_ADM_LIST_DOMAINS, account_structure: $user_data);
    }

    public function can_admin_manage_domains(bool | null | int | array | PHS_Record_data $user_data = null) : bool
    {
        return can(self::ROLEU_ADM_MANAGE_DOMAINS, account_structure: $user_data);
    }

    public function can_admin_ping_domains(bool | null | int | array | PHS_Record_data $user_data = null) : bool
    {
        return can(self::ROLEU_ADM_PING_DOMAIN, account_structure: $user_data);
    }

    public function can_admin_list_logs(bool | null | int | array | PHS_Record_data $user_data = null) : bool
    {
        return can(self::ROLEU_ADM_LIST_LOGS, account_structure: $user_data);
    }

    public function can_admin_manage_logs(bool | null | int | array | PHS_Record_data $user_data = null) : bool
    {
        return can(self::ROLEU_ADM_MANAGE_LOGS, account_structure: $user_data);
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
        return (bool)($this->get_plugin_settings()['enable_remotes'] ?? false);
    }

    public function is_remote_calls_enabled() : bool
    {
        return (bool)($this->get_plugin_settings()['allow_remote_calls'] ?? false);
    }

    public function is_accepting_remote_calls() : bool
    {
        return $this->is_remote_enabled() && $this->is_remote_calls_enabled();
    }

    public function log_all_outgoing_calls() : bool
    {
        return (bool)($this->get_plugin_settings()['log_outgoing_calls'] ?? false);
    }

    public function listen_after_left_menu_admin(PHS_Event_Layout $event_obj) : bool
    {
        $event_obj->append_to_buffer($this->quick_render_template_for_buffer('layout/left_menu_admin') ?? '');

        return true;
    }

    public function listen_accounts_registration_roles(PHS_Event_Accounts_registration_roles $event_obj) : bool
    {
        if (!($account_arr = $event_obj->get_input('account_data'))) {
            return false;
        }

        $roles_arr = [];
        if ($this->_accounts_model->acc_is_admin($account_arr)) {
            $roles_arr[] = self::ROLE_MANAGER;
        } elseif ($this->_accounts_model->acc_is_operator($account_arr)) {
            $roles_arr[] = self::ROLE_OPERATOR;
        }

        $event_obj->add_roles($roles_arr);

        return true;
    }
}
