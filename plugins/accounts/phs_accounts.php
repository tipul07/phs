<?php

namespace phs\plugins\accounts;

use phs\libraries\PHS_Hooks;
use \phs\PHS;
use \phs\PHS_session;
use \phs\libraries\PHS_Plugin;

class PHS_Plugin_Accounts extends PHS_Plugin
{
    const ERR_LOGOUT = 40000;

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
            'generate_pass_if_not_present' => true,
            'email_unique' => true,
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

    public function do_logout_subaccount()
    {
        $this->reset_error();

        if( !($db_details = $this->get_current_user_db_details())
         or empty( $db_details['session_db_data'] ) or !is_array( $db_details['session_db_data'] )
         or empty( $db_details['session_db_data']['id'] ) or empty( $db_details['session_db_data']['auid'] ) )
            return true;

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($accounts_model = PHS::load_model( 'accounts', $this->instance_plugin_name() )) )
        {
            if( self::st_has_error() )
                $this->copy_static_error();
            return false;
        }

        if( !($accounts_model->logout_subaccount( $db_details['session_db_data'] )) )
        {
            if( $accounts_model->has_error() )
                $this->copy_error( $accounts_model );
            else
                $this->set_error( self::ERR_LOGOUT, self::_t( 'Couldn\'t logout from subaccount.' ) );

            return false;
        }

        return true;
    }

    public function do_logout()
    {
        $this->reset_error();

        if( !($db_details = $this->get_current_user_db_details())
         or empty( $db_details['session_db_data'] ) or !is_array( $db_details['session_db_data'] )
         or empty( $db_details['session_db_data']['id'] ) or empty( $db_details['session_db_data']['uid'] ) )
            return true;

        if( !empty( $db_details['session_db_data']['auid'] ) )
            return $this->do_logout_subaccount();

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($accounts_model = PHS::load_model( 'accounts', $this->instance_plugin_name() )) )
        {
            if( self::st_has_error() )
                $this->copy_static_error();
            return false;
        }

        if( !($accounts_model->logout( $db_details['session_db_data'] )) )
        {
            if( $accounts_model->has_error() )
                $this->copy_error( $accounts_model );
            else
                $this->set_error( self::ERR_LOGOUT, self::_t( 'Couldn\'t logout from subaccount.' ) );

            return false;
        }

        return PHS_session::_d( self::session_key() );
    }

    public function get_current_user_db_details( $hook_args = false )
    {
        static $check_result = false;

        $hook_args = self::validate_array( $hook_args, PHS_Hooks::default_user_db_details_hook_args() );

        if( empty( $hook_args['force_check'] )
        and !empty( $check_result ) )
            return $check_result;

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($accounts_model = PHS::load_model( 'accounts', $this->instance_plugin_name() )) )
            return $hook_args;

        if( !($skey_value = PHS_session::_g( self::session_key() ))
         or !($online_db_details = $accounts_model->get_details_fields(
                array(
                    'wid' => $skey_value,
                ),
                array(
                    'table_name' => 'online',
                )
             ))
        )
        {
            $hook_args['session_db_data'] = $accounts_model->get_empty_data( array( 'table_name' => 'online' ) );
            $hook_args['user_db_data'] = $accounts_model->get_empty_data();

            return $hook_args;
        }

        if( empty( $online_db_details['uid'] )
         or !($user_db_details = $accounts_model->get_details_fields(
            array(
                'id' => $online_db_details['uid'],
            )
            )) )
        {
            $accounts_model->hard_delete( $online_db_details, array( 'table_name' => 'online' ) );

            // session expired?
            $hook_args['session_expired_secs'] = seconds_passed( $online_db_details['idle'] );

            $hook_args['session_db_data'] = $accounts_model->get_empty_data( array( 'table_name' => 'online' ) );
            $hook_args['user_db_data'] = $accounts_model->get_empty_data();

            return $hook_args;
        }


        $hook_args['session_db_data'] = $online_db_details;
        $hook_args['user_db_data'] = $user_db_details;

        $check_result = $hook_args;

        return $hook_args;
    }

}
