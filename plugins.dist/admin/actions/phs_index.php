<?php
namespace phs\plugins\admin\actions;

use phs\PHS;
use phs\PHS_Scope;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Notifications;
use phs\plugins\accounts\models\PHS_Model_Accounts;

class PHS_Action_Index extends PHS_Action
{
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_WEB];
    }

    public function execute()
    {
        PHS::page_settings('page_title', $this->_pt('Admin Area'));

        if (!($current_user = PHS::user_logged_in())) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        $level_title = $this->_pt('N/A');

        /** @var PHS_Model_Accounts $accounts_model */
        if (!($accounts_model = PHS_Model_Accounts::get_instance())) {
            PHS_Notifications::add_error_notice($this->_pt('Couldn\'t load accounts model.'));
        } elseif (($user_level = $accounts_model->valid_level($current_user['level']))) {
            $level_title = $user_level['title'];
        }

        $data = [
            'current_user' => $current_user,
            'user_level'   => $level_title,
        ];

        return $this->quick_render_template('index', $data);
    }
}
