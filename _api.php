<?php

    header( 'Cache-Control: no-store, no-cache, must-revalidate' );
    header( 'Cache-Control: post-check=0, pre-check=0', false );

    // HTTP/1.0
    header( 'Pragma: no-cache' );

    include_once( 'main.php' );

    use \phs\PHS;
    use \phs\PHS_api;
    use \phs\libraries\PHS_Logger;
    use \phs\libraries\PHS_params;

    $allow_web_calls = false;
    /** @var \phs\plugins\admin\PHS_Plugin_Admin $admin_plugin */
    if( ($admin_plugin = PHS::load_plugin( 'admin' ))
    and ($admin_plugin_settings = $admin_plugin->get_plugin_settings())
    and !empty( $admin_plugin_settings['allow_api_calls'] ) )
        $allow_web_calls = true;

    if( empty( $allow_web_calls ) )
    {
        PHS_api::http_header_response( PHS_api::H_CODE_SERVICE_UNAVAILABLE );
        exit;
    }

    $api_params = array();
    $vars_from_get = array( PHS_api::PARAM_VERSION, PHS_api::PARAM_API_ROUTE, PHS_api::PARAM_USING_REWRITE, PHS_api::PARAM_WEB_SIMULATION,  );
    foreach( $vars_from_get as $key )
    {
        if( ($val = PHS_params::_g( $key, PHS_params::T_ASIS )) !== null )
            $api_params[$key] = $val;
    }

    if( !($api_obj = PHS_api::api_factory( $api_params )) )
    {
        if( !PHS_api::st_has_error() )
            $error_msg = PHS_api::st_get_error_message();
        else
            $error_msg = PHS_api::_t( 'Unknown error.' );

        PHS_Logger::logf( 'Error obtaining API instance: ['.$error_msg.']', PHS_Logger::TYPE_API );

        PHS_api::generic_error( $error_msg );

        exit;
    }

    if( !empty( $_SERVER ) and is_array( $_SERVER ) )
    {
        $content_type = false;
        if( !empty( $_SERVER['CONTENT_TYPE'] ) )
            $content_type = $_SERVER['CONTENT_TYPE'];

        elseif( !empty( $_SERVER['HTTP_CONTENT_TYPE'] ) )
            $content_type = $_SERVER['HTTP_CONTENT_TYPE'];

        if( !empty( $content_type )
        and !$api_obj->set_content_type( strtolower( trim( $content_type ) ) ) )
        {
            if( $api_obj->has_error() )
                $error_msg = $api_obj->get_error_message();
            else
                $error_msg = PHS_api::_t( 'Couldn\'t set content type to API object.' );

            PHS_Logger::logf( 'Error setting content type in API instance: ['.$error_msg.']', PHS_Logger::TYPE_API );

            PHS_api::generic_error( $error_msg );

            exit;
        }

        if( !empty( $_SERVER['REQUEST_METHOD'] )
        and !$api_obj->set_http_method( $_SERVER['REQUEST_METHOD'] ) )
        {
            if( $api_obj->has_error() )
                $error_msg = $api_obj->get_error_message();
            else
                $error_msg = PHS_api::_t( 'Couldn\'t set HTTP method to API object.' );

            PHS_Logger::logf( 'Error setting HTTP method in API instance: ['.$error_msg.']', PHS_Logger::TYPE_API );

            PHS_api::generic_error( $error_msg );

            exit;
        }

        if( !empty( $_SERVER['SERVER_PROTOCOL'] )
        and !$api_obj->set_http_protocol( trim( $_SERVER['SERVER_PROTOCOL'] ) ) )
        {
            if( $api_obj->has_error() )
                $error_msg = $api_obj->get_error_message();
            else
                $error_msg = PHS_api::_t( 'Couldn\'t set response protocol to API object.' );

            PHS_Logger::logf( 'Error setting response protocol in API instance: ['.$error_msg.']', PHS_Logger::TYPE_API );

            PHS_api::generic_error( $error_msg );

            exit;
        }
    }

    if( !$api_obj->api_authentication() )
    {
        if( $api_obj->has_error() )
            $error_msg = $api_obj->get_error_message();
        else
            $error_msg = PHS_api::_t( 'Authentication failed.' );

        PHS_Logger::logf( 'Error setting response protocol in API instance: ['.$error_msg.']', PHS_Logger::TYPE_API );

        PHS_api::generic_error( $error_msg );

        exit;
    }

    // $run_result is an action result
    if( !($action_result = $api_obj->run_route()) )
    {
        if( $api_obj->has_error() )
            $error_msg = $api_obj->get_error_message();
        else
            $error_msg = PHS_api::_t( 'Error running API request.' );

        PHS_Logger::logf( 'Error running API route: ['.$error_msg.']', PHS_Logger::TYPE_API );

        PHS_api::generic_error( $error_msg );

        exit;
    } elseif( ($debug_data = PHS::platform_debug_data()) )
    {
        PHS_Logger::logf( 'API route ['.PHS::get_route_as_string().'] run with success: '.$debug_data['db_queries_count'].' queries, '.
                          ' bootstrap: '.number_format( $debug_data['bootstrap_time'], 6, '.', '' ).'s, '.
                          ' running: '.number_format( $debug_data['running_time'], 6, '.', '' ).'s', PHS_Logger::TYPE_API );
    }
