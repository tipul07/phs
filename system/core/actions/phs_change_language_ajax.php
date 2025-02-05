<?php
namespace phs\system\core\actions;

use phs\PHS;
use phs\PHS_Scope;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;
use phs\plugins\accounts\models\PHS_Model_Accounts;

class PHS_Action_Change_language_ajax extends PHS_Action
{
    public function action_roles() : array
    {
        return [self::ACT_ROLE_CHANGE_LANGUAGE, ];
    }

    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_AJAX];
    }

    public function execute()
    {
        if (!($to_lang = PHS_Params::_gp(self::LANG_URL_PARAMETER, PHS_Params::T_NOHTML))) {
            PHS_Notifications::add_error_notice($this->_pt('Please provide language you want to switch to.'));

            return self::default_action_result();
        }

        if (!($clean_lang = self::valid_language($to_lang))) {
            PHS_Notifications::add_error_notice($this->_pt('Invalid language provided.'));

            return self::default_action_result();
        }

        if ($clean_lang !== self::get_current_language()) {
            PHS_Notifications::add_error_notice($this->_pt('Couldn\'t change current language. Please try again.'));

            return self::default_action_result();
        }

        if (($accounts_model = PHS_Model_Accounts::get_instance())
         && ($current_user = PHS::user_logged_in())
         && (!($account_language = $accounts_model->get_account_language($current_user))
                || $account_language !== $clean_lang
         )) {
            // If we have an error when saving language in profile, don't throw an error as we have a cookie set with the language
            $accounts_model->set_account_language($current_user, $clean_lang);
        }

        return $this->send_ajax_response(['language_changed' => true]);
    }
}
