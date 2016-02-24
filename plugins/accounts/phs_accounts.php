<?php

namespace phs\plugins\accounts;

use \phs\PHS;
use \phs\PHS_session;
use \phs\libraries\PHS_Plugin;

class PHS_Plugin_Accounts extends PHS_Plugin
{
    private static $_session_key = 'PHS_sess';

    /**
     * @return string Returns version of model
     */
    public function get_plugin_version()
    {
        return '1.0.0';
    }

    public function get_models()
    {
        return array( 'accounts', 'accounts_details' );
    }

    /**
     * Override this function and return an array with default settings to be saved for current plugin
     *
     * @return array
     */
    public function get_default_settings()
    {
        return array(
            'email_mandatory' => true,
            'replace_nick_with_email' => true,
            'account_requires_activation' => true,
            'min_password_length' => 6,
            'pass_salt_length' => 8,
        );
    }

    public static function session_key( $key = null )
    {
        if( $key === null )
            return self::$_session_key;

        if( !is_string( $key ) )
            return false;

        self::$_session_key = $key;
        return self::$_session_key;
    }

    public function get_current_user_db_details( $hook_args )
    {
        $hook_args = self::validate_array( $hook_args, PHS::default_user_db_details_hook_args() );

        if( !($skey_value = PHS_session::_g( self::session_key() ))
         or !($accounts_model = PHS::load_model( 'accounts', $this->instance_plugin_name() ))

         or !($online_db_details = $accounts_model->get_details_fields(
                array(
                    'wid' => $skey_value,
                )
             ))

        )
            return $hook_args;

        $hook_args['session_db_data'] = $online_db_details;

        return $hook_args;
    }

    public function do_login( $user_data )
    {
        global $APP_CFG;

        PHS_module::reset_static_error();

        if( !is_array( $params )
            or (empty( $params['uid'] ) and (empty( $params['user_arr'] ) or !is_array( $params['user_arr'] ) or empty( $params['user_arr']['id'] ))) )
            return false;

        include_once( $APP_CFG['libdir'].'accounts.inc.php' );

        if( empty( $params['user_arr'] ) )
            $params['user_arr'] = array();
        if( empty( $params['uid'] ) )
            $params['uid'] = 0;
        if( !empty( $params['uid'] ) )
            $params['uid'] = intval( $params['uid'] );

        if( empty( $params['user_arr'] )
            and !($params['user_arr'] = account_class::get_details( $params['uid'] )) )
        {
            PHS_module::set_static_error( self::ERR_USER_NOT_FOUND, PHS_lang::_t( 'CLSESSION_USER_NOT_FOUND' ) );
            return false;
        }

        session_class::reset_data();

        $db_params = array();
        $db_params['auid'] = (empty( $params['auid'] )?0:intval( $params['auid'] ));
        $db_params['host'] = (empty( $params['host'] )?'':$params['host']);
        $db_params['location'] = (empty( $params['location'] )?'':$params['location']);
        $db_params['return_page'] = (empty( $params['return_page'] )?'':$params['return_page']);
        $db_params['expire_secs'] = (empty( $params['expire_secs'] )?0:$params['expire_secs']);

        $ret_val = false;
        if( ($data_arr = session_class::db_create( $params['user_arr'], $db_params )) )
            $ret_val = session_class::set_data( $data_arr );

        return $ret_val;
    }

}
