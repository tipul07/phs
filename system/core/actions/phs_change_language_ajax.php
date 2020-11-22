<?php

namespace phs\system\core\actions;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Params;
use \phs\libraries\PHS_Notifications;

class PHS_Action_Change_language_ajax extends PHS_Action
{
    /** @inheritdoc */
    public function action_roles()
    {
        return array( self::ACT_ROLE_CHANGE_LANGUAGE, );
    }

    /**
     * @inheritdoc
     */
    public function allowed_scopes()
    {
        return array( PHS_Scope::SCOPE_AJAX );
    }

    public function execute()
    {
        $action_result = self::default_action_result();
        if( !($to_lang = PHS_Params::_gp( self::LANG_URL_PARAMETER, PHS_Params::T_NOHTML )) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Please provide language you want to switch to.' ) );
            return $action_result;
        }

        if( !($clean_lang = self::valid_language( $to_lang )) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Invalid language provided.' ) );
            return $action_result;
        }

        if( $clean_lang !== self::get_current_language() )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t change current language. Please try again.' ) );
            return $action_result;
        }

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( ($accounts_model = PHS::load_model( 'accounts', 'accounts' ))
        and ($current_user = PHS::user_logged_in())
        and (!($account_language = $accounts_model->get_account_language( $current_user ))
                or $account_language !== $clean_lang
            ) )
        {
            // If we have an error when saving language in profile, don't throw an error as we have a cookie set with the language
            $accounts_model->set_account_language( $current_user, $clean_lang );
        }

        $action_result['ajax_result'] = array(
            'language_changed' => true,
        );

        return $action_result;
    }
}
