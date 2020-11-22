<?php

namespace phs\plugins\admin\actions;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\PHS_Bg_jobs;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Notifications;

class PHS_Action_Plugins_integrity_bg extends PHS_Action
{
    public function allowed_scopes()
    {
        return array( PHS_Scope::SCOPE_BACKGROUND );
    }

    public function execute()
    {
        $action_result = self::default_action_result();

        $action_result['ajax_result'] = array(
            'has_error' => false,
            'plugin_info' => false,
            'instance_details' => false,
        );

        if( !($params = PHS_Bg_jobs::get_current_job_parameters())
         or empty( $params['p'] ) )
        {
            $action_result['buffer'] = $this->_pt( 'Plugin name not provided for validity check' );
            $action_result['ajax_result']['has_error'] = true;

            return $action_result;
        }

        if( empty( $params['c'] ) )
            $params['c'] = '';
        if( empty( $params['m'] ) )
            $params['m'] = '';
        if( empty( $params['a'] ) )
            $params['a'] = '';
        if( empty( $params['co'] ) )
            $params['co'] = '';

        $action_result['ajax_result']['params'] = $params;

        if( empty( $params['c'] ) and empty( $params['m'] ) and empty( $params['a'] ) and empty( $params['co'] ) )
        {
            if( !($plugin_obj = PHS::load_plugin( $params['p'] )) )
            {
                if( self::st_has_error() )
                    $error_msg = self::st_get_error_message();
                else
                    $error_msg = $this->_pt( 'Unknown error while instantiating plugin.' );

                $action_result['buffer'] = $error_msg;
                $action_result['ajax_result']['has_error'] = true;
            } elseif( ($plugin_info = $plugin_obj->get_plugin_info()) )
                $action_result['ajax_result']['plugin_info'] = $plugin_info;
        } elseif( !empty( $params['c'] ) )
        {
            if( !($controller_obj = PHS::load_controller( $params['c'], $params['p'] )) )
            {
                if( self::st_has_error() )
                    $error_msg = self::st_get_error_message();
                else
                    $error_msg = $this->_pt( 'Unknown error while instantiating controller.' );

                $action_result['buffer'] = $error_msg;
                $action_result['ajax_result']['has_error'] = true;
            } elseif( ($instance_details = $controller_obj->instance_details()) )
                $action_result['ajax_result']['instance_details'] = $instance_details;
        } elseif( !empty( $params['a'] ) )
        {
            if( !($action_obj = PHS::load_action( $params['a'], $params['p'] )) )
            {
                if( self::st_has_error() )
                    $error_msg = self::st_get_error_message();
                else
                    $error_msg = $this->_pt( 'Unknown error while instantiating action.' );

                $action_result['buffer'] = $error_msg;
                $action_result['ajax_result']['has_error'] = true;
            } elseif( ($instance_details = $action_obj->instance_details()) )
                $action_result['ajax_result']['instance_details'] = $instance_details;
        } elseif( !empty( $params['co'] ) )
        {
            if( !($contract_obj = PHS::load_contract( $params['co'], $params['p'] )) )
            {
                if( self::st_has_error() )
                    $error_msg = self::st_get_error_message();
                else
                    $error_msg = $this->_pt( 'Unknown error while instantiating contract.' );

                $action_result['buffer'] = $error_msg;
                $action_result['ajax_result']['has_error'] = true;
            } elseif( ($instance_details = $contract_obj->instance_details()) )
                $action_result['ajax_result']['instance_details'] = $instance_details;
        } elseif( !empty( $params['m'] ) )
        {
            if( !($model_obj = PHS::load_model( $params['m'], $params['p'] )) )
            {
                if( self::st_has_error() )
                    $error_msg = self::st_get_error_message();
                else
                    $error_msg = $this->_pt( 'Unknown error while instantiating model.' );

                $action_result['buffer'] = $error_msg;
                $action_result['ajax_result']['has_error'] = true;
            } elseif( ($instance_details = $model_obj->instance_details()) )
                $action_result['ajax_result']['instance_details'] = $instance_details;
        } else
        {
            $action_result['buffer'] = $this->_pt( 'Nothing to instantiate. Please check parameters.' );
            $action_result['ajax_result']['has_error'] = true;
        }

        return $action_result;
    }
}
