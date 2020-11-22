<?php

namespace phs\system\core\scopes;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Hooks;
use \phs\libraries\PHS_Logger;
use \phs\libraries\PHS_Utils;
use \phs\libraries\PHS_Notifications;
use \phs\system\core\views\PHS_View;

class PHS_Scope_Web extends PHS_Scope
{
    public function get_scope_type()
    {
        return self::SCOPE_WEB;
    }

    public function process_action_result( $action_result, $static_error_arr = false )
    {
        /** @var \phs\libraries\PHS_Action $action_obj */
        if( !($action_obj = PHS::running_action()) )
            $action_obj = false;
        /** @var \phs\libraries\PHS_Controller $controller_obj */
        if( !($controller_obj = PHS::running_controller()) )
            $controller_obj = false;

        $action_result = self::validate_array( $action_result, PHS_Action::default_action_result() );

        if( ($expiration_arr = PHS::current_user_password_expiration())
        and !empty( $expiration_arr['is_expired'] ) )
        {
            if( $action_obj->action_role_is( array( $action_obj::ACT_ROLE_CHANGE_PASSWORD, $action_obj::ACT_ROLE_LOGIN,
                                                    $action_obj::ACT_ROLE_LOGOUT, $action_obj::ACT_ROLE_PASSWORD_EXPIRED ) ) )
                $in_special_page = true;
            else
                $in_special_page = false;

            if( !$in_special_page )
                PHS_Notifications::add_warning_notice( $this->_pt( 'Your password expired %s ago. For security reasons, please <a href="%s">change your password</a>.',
                                                                   PHS_Utils::parse_period( $expiration_arr['expired_for_seconds'] ),
                                                                   PHS::url( array( 'p' => 'accounts', 'a' => 'change_password' ), array( 'password_expired' => 1 ) ) ) );

            if( empty( $expiration_arr['show_only_warning'] )
            and !empty( $action_obj )
            and !$in_special_page )
            {
                $args = array();
                $args['password_expired'] = 1;

                $action_result['redirect_to_url'] = PHS::url( array( 'p' => 'accounts', 'a' => 'change_password' ), $args );
            }
        }

        if( !empty( $action_result['request_login'] ) )
        {
            $args = array();
            if( !empty( $action_result['redirect_to_url'] ) )
                $args['back_page'] = $action_result['redirect_to_url'];
            else
                $args['back_page'] = PHS::current_url();

            $action_result['redirect_to_url'] = PHS::url( array( 'p' => 'accounts', 'a' => 'login' ), $args );
        }

        if( !empty( $action_result['redirect_to_url'] )
        and !@headers_sent() )
        {
            @header( 'Location: '.$action_result['redirect_to_url'] );
            exit;
        }

        $hook_args = PHS_Hooks::default_page_location_hook_args();
        $hook_args['page_template'] = $action_result['page_template'];
        $hook_args['page_template_args'] = $action_result['action_data'];

        if( ($new_hook_args = PHS::trigger_hooks( PHS_Hooks::H_WEB_TEMPLATE_RENDERING, $hook_args ))
        and is_array( $new_hook_args ) )
        {
            if( !empty( $new_hook_args['new_page_template'] ) )
                $action_result['page_template'] = $new_hook_args['new_page_template'];
            if( isset( $new_hook_args['new_page_template_args'] ) and $new_hook_args['new_page_template_args'] !== false )
                $action_result['action_data'] = $new_hook_args['new_page_template_args'];
        }

        if( empty( $action_obj )
        and empty( $action_result['page_template'] ) )
        {
            echo 'No running action to render page template.';
            exit;
        }

        // send custom headers as we will echo page content here...
        if( !@headers_sent() )
        {
            $result_headers = array();
            if( !empty( $action_result['custom_headers'] ) and is_array( $action_result['custom_headers'] ) )
            {
                foreach( $action_result['custom_headers'] as $key => $val )
                {
                    if( empty( $key ) )
                        continue;

                    if( !is_null( $val ) )
                        $result_headers[$key] = $val;
                    else
                        $result_headers[$key] = '';
                }
            }

            $result_headers['X-Powered-By'] = 'PHS-'.PHS_VERSION;

            $result_headers = self::unify_array_insensitive( $result_headers, array( 'trim_keys' => true ) );

            foreach( $result_headers as $key => $val )
            {
                if( $val == '' )
                    @header( $key );
                else
                    @header( $key.': '.$val );
            }
        }

        if( self::arr_has_error( $static_error_arr ) )
            echo self::arr_get_error_message( $static_error_arr );

        elseif( empty( $action_obj )
         or empty( $action_result['page_template'] ) )
            echo $action_result['buffer'];

        else
        {
            $view_params = array();
            $view_params['action_obj'] = $action_obj;
            $view_params['controller_obj'] = $controller_obj;
            $view_params['parent_plugin_obj'] = (!empty( $action_obj )?$action_obj->get_plugin_instance():false);
            $view_params['plugin'] = (!empty( $action_obj )?$action_obj->instance_plugin_name():false);
            $view_params['template_data'] = (!empty( $action_result['action_data'] )?$action_result['action_data']:false);
            $view_params['as_singleton'] = false;

            if( !($view_obj = PHS_View::init_view( $action_result['page_template'], $view_params )) )
            {
                if( self::st_has_error() )
                    echo self::st_get_error_message();
                else
                    echo self::_t( 'Error instantiating view object.' );

                exit;
            }

            if( empty( $action_result['page_settings']['page_title'] ) )
                $action_result['page_settings']['page_title'] = '';

            $action_result['page_settings']['page_title'] .= ($action_result['page_settings']['page_title']!=''?' - ':'').PHS_SITE_NAME;

            if( ($result_buffer = $view_obj->render()) === false )
            {
                if( $view_obj->has_error() )
                    $error_msg = $view_obj->get_error_message();
                else
                {
                    if( !is_string( $action_result['page_template'] ) )
                    {
                        ob_start();
                        var_dump( $action_result['page_template'] );
                        $template_str = ob_get_clean();
                    } else
                        $template_str = $action_result['page_template'];

                    $error_msg = 'Error rendering action result in template ['.$template_str.']';
                }

                PHS_Logger::logf( $error_msg, PHS_Logger::TYPE_DEBUG );

                echo self::_t( 'Error rendering page template.' );
                exit;
            }

            echo $result_buffer;
        }

        return true;
    }
}
