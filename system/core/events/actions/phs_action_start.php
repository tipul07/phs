<?php
namespace phs\system\core\events\actions;

use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Action;

class PHS_Event_Action_start extends PHS_Event_Action
{
    public const LOGIN = 'phs_accounts_login', LOGOUT = 'phs_accounts_logout', EDIT_PROFILE = 'phs_accounts_edit_profile',
        CHANGE_PASSWORD = 'phs_accounts_change_password', FORGOT_PASSWORD = 'phs_accounts_forgot_password',
        REGISTER = 'phs_accounts_register', SETUP_PASSWORD = 'phs_accounts_setup_password',
        SESSION_DETAILS = 'phs_accounts_session_details',
        TFA_SETUP = 'phs_accounts_tfa_setup', TFA_VERIFY = 'phs_accounts_tfa_verify', TFA_SETTINGS = 'phs_accounts_tfa_settings';

    protected const OLD_HOOKS = [
        self::LOGIN           => [PHS_Hooks::H_USERS_LOGIN_ACTION_START],
        self::LOGOUT          => [PHS_Hooks::H_USERS_LOGOUT_ACTION_START],
        self::EDIT_PROFILE    => [PHS_Hooks::H_USERS_EDIT_PROFILE_ACTION_START],
        self::CHANGE_PASSWORD => [PHS_Hooks::H_USERS_CHANGE_PASSWORD_ACTION_START],
        self::FORGOT_PASSWORD => [PHS_Hooks::H_USERS_FORGOT_PASSWORD_ACTION_START],
        self::REGISTER        => [PHS_Hooks::H_USERS_REGISTER_ACTION_START],
    ];

    /**
     * @param array $action_arr
     * @param string $action
     * @param null|PHS_Action $action_obj
     *
     * @return null|array{"stop_execution":bool, "action_result": ?array}
     */
    public static function action(string $action = '', ?PHS_Action $action_obj = null) : ?array
    {
        $event_params = [];
        if (!empty(self::OLD_HOOKS[$action])) {
            $event_params['old_hooks'] = self::OLD_HOOKS[$action];
        }

        if (!($event_obj = self::trigger(['action_obj' => $action_obj], $action, $event_params))
            || !$event_obj->get_output('action_result')) {
            return null;
        }

        return $event_obj->get_output();
    }
}
