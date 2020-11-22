<?php

namespace phs\plugins\cookie_notice\actions;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\PHS_Session;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Params;

class PHS_Action_Cookie_notice_ajax extends PHS_Action
{
    public function allowed_scopes()
    {
        return array( PHS_Scope::SCOPE_AJAX );
    }

    public function execute()
    {
        $action_result = self::default_action_result();

        $action_result['ajax_result'] = array(
            'with_success' => false,
        );

        /** @var \phs\plugins\cookie_notice\PHS_Plugin_Cookie_notice $plugin_obj */
        if( !($plugin_obj = $this->get_plugin_instance()) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load cookies notification plugin.' ) );
            return $action_result;
        }

        $agree_cookies = PHS_Params::_pg( 'agree_cookies', PHS_Params::T_INT );

        if( !empty( $agree_cookies )
        and $plugin_obj->accept_cookie_agreement() )
            $action_result['ajax_result']['with_success'] = true;

        return $action_result;
    }
}
