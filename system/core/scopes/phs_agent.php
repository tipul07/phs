<?php
namespace phs\system\core\scopes;

use phs\PHS_Scope;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Notifications;

class PHS_Scope_Agent extends PHS_Scope
{
    public function get_scope_type() : int
    {
        return self::SCOPE_AGENT;
    }

    /**
     * @param array|false $action_result
     * @param array|false $static_error_arr
     *
     * @return array
     */
    public function process_action_result($action_result, $static_error_arr = false)
    {
        $action_result = PHS_Action::validate_action_result($action_result);

        $notifications_list_arr = [
            'success'  => PHS_Notifications::notifications_success(),
            'warnings' => PHS_Notifications::notifications_warnings(),
            'errors'   => PHS_Notifications::notifications_errors(),
        ];

        foreach ($notifications_list_arr as $notification_type => $notifications_arr) {
            if (empty($notifications_arr) || !is_array($notifications_arr)) {
                continue;
            }

            PHS_Logger::notice(ucfirst($notification_type).' notifications:'."\n".implode("\n", $notifications_arr), PHS_Logger::TYPE_AGENT);
        }

        if (!empty($action_result['request_login'])) {
            PHS_Logger::warning('Script required login action, but we are in an agent script...',
                PHS_Logger::TYPE_AGENT);
        }

        if (!empty($action_result['redirect_to_url'])) {
            PHS_Logger::warning('We are told to redirect to an URL ('.$action_result['redirect_to_url'].'), but we are in an agent script...',
                PHS_Logger::TYPE_AGENT);
        }

        return $action_result;
    }
}
