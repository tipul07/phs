<?php

namespace phs\system\core\scopes;

use phs\libraries\PHS_Logger;
use \phs\PHS;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Action;
use \phs\system\core\views\PHS_View;

class PHS_Scope_Ajax extends PHS_Scope
{
    public function get_scope_type()
    {
        return self::SCOPE_AJAX;
    }

    public function process_action_result( $action_result )
    {
        $action_result = self::validate_array( $action_result, PHS_Action::default_action_result() );

        if( !empty( $action_result['redirect_to_url'] )
        and !@headers_sent() )
        {
            @header( 'Location: '.$action_result['redirect_to_url'] );
            exit;
        }

        if( !isset( $action_result['ajax_result'] ) )
            $action_result['ajax_result'] = false;

        echo @json_encode( $action_result['ajax_result'] );

        return true;
    }
}
