<?php
namespace phs\system\core\events\actions;

use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Action;

class PHS_Event_Action_after extends PHS_Event_Action
{
    public const LOGIN = 'phs_accounts_login';

    protected const OLD_HOOKS = [
        self::LOGIN => [PHS_Hooks::H_USERS_AFTER_LOGIN],
    ];

    /**
     * @param string $action
     * @param null|\phs\libraries\PHS_Action $action_obj
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
