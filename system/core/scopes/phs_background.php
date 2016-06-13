<?php

namespace phs\system\core\scopes;

use phs\libraries\PHS_Logger;
use \phs\PHS;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Action;
use \phs\system\core\views\PHS_View;

class PHS_Scope_Background extends PHS_Scope
{
    public function get_scope_type()
    {
        return self::SCOPE_BACKGROUND;
    }

    public function process_action_result( $action_result )
    {
        $action_result = self::validate_array( $action_result, PHS_Action::default_action_result() );

        if( !empty( $action_result['redirect_to_url'] ) )
        {
            PHS_Logger::logf( 'We are told to redirect to an URL ('.$action_result['redirect_to_url'].'), but we are in a background script...', PHS_Logger::TYPE_DEBUG );
            exit;
        }

        if( empty( $action_result['page_settings']['page_title'] ) )
            $action_result['page_settings']['page_title'] = '';
        if( empty( $action_result['buffer'] ) )
            $action_result['buffer'] = '';

        if( !empty( $action_result['page_settings']['page_title'] ) or !empty( $action_result['buffer'] ) )
            PHS_Logger::logf( 'Title ['.$action_result['page_settings']['page_title'].'], Body ['.$action_result['buffer'].']', PHS_Logger::TYPE_DEBUG );
        else
            PHS_Logger::logf( 'Action run with success.', PHS_Logger::TYPE_DEBUG );

        return true;
    }
}
