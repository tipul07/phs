<?php
namespace phs\plugins\cookie_notice\actions;

use phs\PHS_Scope;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Params;
use phs\plugins\cookie_notice\PHS_Plugin_Cookie_notice;

class PHS_Action_Cookie_notice_ajax extends PHS_Action
{
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_AJAX];
    }

    public function execute()
    {
        if (PHS_Params::_pg('agree_cookies', PHS_Params::T_INT)
            && ($plugin_obj = PHS_Plugin_Cookie_notice::get_instance())
            && $plugin_obj->accept_cookie_agreement()) {
            return $this->send_ajax_response(['with_success' => true]);
        }

        return $this->send_ajax_response(['with_success' => false]);
    }
}
