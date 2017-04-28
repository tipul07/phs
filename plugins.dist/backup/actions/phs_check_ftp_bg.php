<?php

namespace phs\plugins\backup\actions;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\PHS_bg_jobs;
use \phs\PHS_crypt;
use \phs\libraries\PHS_Action;

class PHS_Action_Check_ftp_bg extends PHS_Action
{
    public function allowed_scopes()
    {
        return array( PHS_Scope::SCOPE_BACKGROUND );
    }

    public function execute()
    {
        PHS::st_suppress_backtrace( true );

        $action_result = self::default_action_result();

        $action_result['ajax_result'] = array(
            'has_error' => false,
            'error_message' => '',
        );

        if( !($params = PHS_bg_jobs::get_current_job_parameters())
         or empty( $params['ftp_settings'] ) or !is_array( $params['ftp_settings'] ) )
        {
            $action_result['ajax_result']['error_message'] = $this->_pt( 'Please provide FTP server settings.' );
            $action_result['ajax_result']['has_error'] = true;

            return $action_result;
        }

        /** @var \phs\system\core\libraries\PHS_Ftp $ftp_obj */
        if( !($ftp_obj = PHS::get_core_library_instance( 'ftp', array( 'as_singleton' => false ) )) )
        {
            $action_result['ajax_result']['error_message'] = $this->_pt( 'Couldn\'t load FTP core library.' );
            $action_result['ajax_result']['has_error'] = true;

            return $action_result;
        }

        if( empty( $params['ftp_settings']['pass'] ) )
            $params['ftp_settings']['pass'] = '';

        $params['ftp_settings']['pass'] = PHS_crypt::quick_decode( $params['ftp_settings']['pass'] );

        if( !$ftp_obj->settings( $params['ftp_settings'] ) )
        {
            if( $ftp_obj->has_error() )
                $action_result['ajax_result']['error_message'] = $ftp_obj->get_error_message();
            else
                $action_result['ajax_result']['error_message'] = $this->_pt( 'Cannot pass FTP settings to FTP library instance.' );

            $action_result['ajax_result']['has_error'] = true;

            return $action_result;
        }

        if( $ftp_obj->ls() === false )
        {
            $ftp_obj->close();

            if( $ftp_obj->has_error() )
                $action_result['ajax_result']['error_message'] = $ftp_obj->get_error_message();
            else
                $action_result['ajax_result']['error_message'] = $this->_pt( 'Couldn\'t connect to FTP server.' );

            $action_result['ajax_result']['has_error'] = true;

            return $action_result;
        }

        $ftp_obj->close();

        return $action_result;
    }
}
