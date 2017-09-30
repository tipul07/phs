<?php

namespace phs\system\core\scopes;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\PHS_ajax;
use \phs\libraries\PHS_params;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Hooks;
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

        $full_buffer = PHS_params::_gp( PHS_ajax::PARAM_FB_KEY, PHS_params::T_INT );

        if( !isset( $action_result['buffer'] ) )
            $action_result['buffer'] = '';
        if( !isset( $action_result['ajax_result'] ) )
            $action_result['ajax_result'] = false;

        // send custom headers as we will echo page content here...
        if( !@headers_sent() )
        {
            if( !empty( $action_result['custom_headers'] ) and is_array( $action_result['custom_headers'] ) )
            {
                foreach( $action_result['custom_headers'] as $key => $val )
                {
                    if( empty( $key ) )
                        continue;

                    $header_str = $key;
                    if( !is_null( $val ) )
                        $header_str .= ': '.$val;

                    @header( $header_str );
                }
            }

            @header( 'X-Powered-By: PHS-'.PHS_VERSION );
        }

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

                if( !empty( $full_buffer )
                and PHS_Notifications::have_any_notifications() )
                {
                    $hook_args = PHS_Hooks::default_notifications_hook_args();
                    $hook_args['output_ajax_placeholders'] = false;

                    if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_NOTIFICATIONS_DISPLAY, PHS_Hooks::default_notifications_hook_args() ))
                    and is_array( $hook_args )
                    and !empty( $hook_args['notifications_buffer'] ) )
                        $action_result['ajax_result'] = $hook_args['notifications_buffer'].$action_result['ajax_result'];
                }

                $ajax_data['response'] = $action_result['ajax_result'];
                $ajax_data['redirect_to_url'] = (!empty($action_result['redirect_to_url']) ? $action_result['redirect_to_url'] : '');
            }

            if( $full_buffer )
            {
                @header( 'Content-Type: text/html' );
                if( is_string( $ajax_data ) )
                    echo $ajax_data;
                elseif( !empty( $action_result['ajax_result'] ) )
                    echo $action_result['ajax_result'];
            } else
            {
                @header( 'Content-Type: application/json' );
                echo @json_encode( $ajax_data );
            }
        }

        return true;
    }
}
