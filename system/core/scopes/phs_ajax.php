<?php

namespace phs\system\core\scopes;

use phs\libraries\PHS_Logger;
use \phs\PHS;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Notifications;
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

        if( !isset( $action_result['buffer'] ) )
            $action_result['buffer'] = '';
        if( !isset( $action_result['ajax_result'] ) )
            $action_result['ajax_result'] = false;

        if( $action_result['buffer'] != '' )
        {
            @header( 'Content-Type: text/html' );
            echo $action_result['buffer'];
        } else
        {
            if( !empty( $action_result['ajax_only_result'] ) )
                $ajax_data = $action_result['ajax_result'];

            else
            {
                $ajax_data = array();
                $ajax_data['status'] = array(
                    'success_messages' => PHS_Notifications::notifications_success(),
                    'warning_messages' => PHS_Notifications::notifications_warnings(),
                    'error_messages' => PHS_Notifications::notifications_errors(),
                );

                $ajax_data['response'] = $action_result['ajax_result'];
                $ajax_data['redirect_to_url'] = (!empty($action_result['redirect_to_url']) ? $action_result['redirect_to_url'] : '');
            }

        }

        @header( 'Content-Type: application/json' );
        echo @json_encode( $ajax_data );

        return true;
    }
}
