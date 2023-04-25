<?php
namespace phs\plugins\accounts\actions;

use phs\PHS;
use phs\PHS_Scope;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Notifications;
use phs\plugins\accounts\PHS_Plugin_Accounts;
use phs\system\core\events\actions\PHS_Event_Action_start;

class PHS_Action_Logout extends PHS_Action
{
    /**
     * @inheritdoc
     */
    public function action_roles()
    {
        return [self::ACT_ROLE_LOGOUT];
    }

    /**
     * Returns an array of scopes in which action is allowed to run
     *
     * @return array If empty array, action is allowed in all scopes...
     */
    public function allowed_scopes()
    {
        return [PHS_Scope::SCOPE_WEB];
    }

    /**
     * @return array|bool
     */
    public function execute()
    {
        if (($event_result = PHS_Event_Action_start::action(PHS_Event_Action_start::LOGOUT, $this))
            && !empty($event_result['action_result'])) {
            $this->set_action_result($event_result['action_result']);
            if (!empty($event_result['stop_execution'])) {
                return $event_result['action_result'];
            }
        }

        PHS::page_settings('page_title', $this->_pt('Logout'));

        if (!PHS::user_logged_in()) {
            PHS_Notifications::add_success_notice($this->_pt('You logged out from your account...'));

            return action_redirect();
        }

        /** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
        if (!($accounts_plugin = PHS_Plugin_Accounts::get_instance())) {
            PHS_Notifications::add_error_notice($this->_pt('Couldn\'t load accounts plugin.'));

            return self::default_action_result();
        }

        if ($accounts_plugin->do_logout()) {
            PHS_Notifications::add_success_notice($this->_pt('Successfully logged out...'));

            return action_redirect();
        }

        if ($accounts_plugin->has_error()) {
            PHS_Notifications::add_error_notice($accounts_plugin->get_error_message());
        } else {
            PHS_Notifications::add_error_notice($this->_pt('Error logging out... Please try again.'));
        }

        return $this->quick_render_template('logout');
    }
}
