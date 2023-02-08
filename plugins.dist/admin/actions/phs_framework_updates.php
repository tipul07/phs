<?php
namespace phs\plugins\admin\actions;

use phs\PHS;
use phs\PHS_Scope;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Notifications;

class PHS_Action_Framework_updates extends PHS_Action
{
    public function allowed_scopes()
    {
        return [PHS_Scope::SCOPE_WEB];
    }

    public function execute()
    {
        PHS::page_settings('page_title', $this->_pt('Framework Updates'));

        if (!($current_user = PHS::user_logged_in())) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            $action_result = self::default_action_result();

            $action_result['request_login'] = true;

            return $action_result;
        }

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if (!($accounts_model = PHS::load_model('accounts', 'accounts'))) {
            PHS_Notifications::add_error_notice($this->_pt('Couldn\'t load accounts model.'));
            self::default_action_result();
        }

        if (!$accounts_model->acc_is_developer($current_user)) {
            PHS_Notifications::add_error_notice($this->_pt('You don\'t have enough rights to access this section.'));
            self::default_action_result();
        }

        $data = [
            'current_user' => $current_user,
        ];

        return $this->quick_render_template('framework_updates', $data);
    }
}
