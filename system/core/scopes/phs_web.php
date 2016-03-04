<?php

namespace phs\system\core\scopes;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Action;
use \phs\system\core\views\PHS_View;

class PHS_Scope_Web extends PHS_Scope
{
    public function get_scope_type()
    {
        return self::SCOPE_WEB;
    }

    public function process_action_result( $action_result )
    {
        /** @var \phs\libraries\PHS_Action $action_obj */
        if( !($action_obj = PHS::running_action()) )
            $action_obj = false;
        /** @var \phs\libraries\PHS_Controller $controller_obj */
        if( !($controller_obj = PHS::running_controller()) )
            $controller_obj = false;

        $action_result = self::validate_array( $action_result, PHS_Action::default_action_result() );

        if( !empty( $action_result['redirect_to_url'] )
        and !@headers_sent() )
        {
            header( 'Location: '.$action_result['redirect_to_url'] );
            exit;
        }

        if( empty( $action_obj )
        and !empty( $action_result['page_template'] ) )
        {
            echo 'No running action to render page template.';
            exit;
        }

        $view_params = array();
        $view_params['action_obj'] = $action_obj;
        $view_params['controller_obj'] = $controller_obj;
        $view_params['plugin'] = (!empty( $action_obj )?$action_obj->instance_plugin_name():false);
        $view_params['as_singleton'] = false;

        if( empty( $action_obj )
         or empty( $action_result['page_template'] ) )
            echo $action_result['buffer'];

        else
        {
            if( !($view_obj = PHS_View::init_view( $action_result['page_template'], $view_params )) )
            {
                if( self::st_has_error() )
                    echo self::st_get_error_message();
                else
                    echo self::_t( 'Error instantiating view object.' );

                exit;
            }

            if( empty( $action_result['page_title'] ) )
                $action_result['page_title'] = '';

            $action_result['page_title'] .= ($action_result['page_title']!=''?' - ':'').PHS_SITE_NAME;

            if( !($view_data = $view_obj->get_context( $view_obj::VIEW_CONTEXT_DATA_KEY )) )
                $view_data = array();

            $view_obj->set_context( $view_obj::VIEW_CONTEXT_DATA_KEY, self::validate_array( $view_data, array( 'action_result' => $action_result ) ) );

            echo $view_obj->render();
        }

        return true;
    }
}
