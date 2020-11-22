<?php

namespace phs\setup\libraries;

use \phs\libraries\PHS_Params;
use \phs\PHS_Db;

class PHS_Step_2 extends PHS_Step
{
    const ERR_CREATE_CONNECTION = 1, ERR_DB_CONNECTION = 2;

    public function step_details()
    {
        return array(
            'title' => 'Database Setup',
            'description' => 'Provide required settings to access database...',
        );
    }

    public function get_config_file()
    {
        return 'database_setup.php';
    }

    public function step_config_passed()
    {
        if( !$this->load_current_configuration() )
            return false;

        // Define special connection with provided settings...
        $mysql_settings = array();
        $mysql_settings['driver'] = PHS_Db::DB_DRIVER_MYSQLI;
        $mysql_settings['host'] = PHS_DB_HOSTNAME;
        $mysql_settings['user'] = PHS_DB_USERNAME;
        $mysql_settings['password'] = PHS_DB_PASSWORD;
        $mysql_settings['database'] = PHS_DB_DATABASE;
        $mysql_settings['prefix'] = PHS_DB_PREFIX;
        $mysql_settings['port'] = PHS_DB_PORT;
        $mysql_settings['timezone'] = date( 'P' );
        $mysql_settings['charset'] = PHS_DB_CHARSET;
        $mysql_settings['use_pconnect'] = PHS_DB_USE_PCONNECT;
        $mysql_settings['driver_settings'] = (!empty( PHS_DB_DRIVER_SETTINGS )?@json_decode( PHS_DB_DRIVER_SETTINGS, true ):array());

        if( empty( $mysql_settings['driver_settings'] ) or !is_array( $mysql_settings['driver_settings'] ) )
            $mysql_settings['driver_settings'] = array();

        if( !defined( 'PHS_SETUP_DB_CONNECTION' ) )
            define( 'PHS_SETUP_DB_CONNECTION', 'db_setup_default_connection' );

        if( !$this->test_db_connection( $mysql_settings, PHS_SETUP_DB_CONNECTION ) )
        {
            if( $this->has_error() )
                $this->add_error_msg( $this->get_error_message() );
            else
                $this->add_error_msg( 'Error while testing DB connection.' );

            return false;
        }

        PHS_Db::default_db_connection( PHS_Db::DB_DRIVER_MYSQLI, PHS_SETUP_DB_CONNECTION );

        return true;
    }

    public function test_db_connection( $db_settings, $connection_name = false )
    {
        if( empty( $connection_name ) )
            $connection_name = 'phs_tmp_db_connection_'.microtime( true );

        if( !($settings_arr = PHS_Db::add_db_connection( $connection_name, $db_settings )) )
        {
            if( self::st_has_error() )
                $this->copy_static_error( self::ERR_CREATE_CONNECTION );
            else
                $this->set_error( self::ERR_CREATE_CONNECTION, 'Error adding DB connection.' );

            return false;
        }

        if( !defined( 'PHS_DB_SILENT_ERRORS' ) )
            define( 'PHS_DB_SILENT_ERRORS', true );
        if( !defined( 'PHS_DB_DIE_ON_ERROR' ) )
            define( 'PHS_DB_DIE_ON_ERROR', false );
        if( !defined( 'PHS_DB_CLOSE_AFTER_QUERY' ) )
            define( 'PHS_DB_CLOSE_AFTER_QUERY', true );

        if( !db_test_connection( $connection_name ) )
        {
            if( ($error_arr = db_last_error( $connection_name ))
            and self::arr_has_error( $error_arr ) )
                $this->copy_error_from_array( $error_arr, self::ERR_CREATE_CONNECTION );
            else
                $this->set_error( self::ERR_CREATE_CONNECTION, 'Database connection failed with current settings.' );

            return false;
        }

        return $connection_name;
    }

    public function load_current_configuration()
    {
        if( $this->config_file_loaded() )
            return true;

        $config_file = PHS_SETUP_CONFIG_DIR.$this->get_config_file();
        if( !@file_exists( $config_file ) )
            return false;

        ob_start();
        include( $config_file );
        ob_end_clean();

        $this->config_file_loaded( true );

        return true;
    }

    protected function render_step_interface( $data = false )
    {
        if( empty( $data ) or !is_array( $data ) )
            $data = array();

        $foobar = PHS_Params::_p( 'foobar', PHS_Params::T_INT );
        $phs_db_hostname = PHS_Params::_p( 'phs_db_hostname', PHS_Params::T_NOHTML );
        $phs_db_username = PHS_Params::_p( 'phs_db_username', PHS_Params::T_NOHTML );
        $phs_db_password = PHS_Params::_p( 'phs_db_password', PHS_Params::T_NOHTML );
        $phs_db_database = PHS_Params::_p( 'phs_db_database', PHS_Params::T_NOHTML );
        $phs_db_prefix = PHS_Params::_p( 'phs_db_prefix', PHS_Params::T_NOHTML );
        $phs_db_port = PHS_Params::_p( 'phs_db_port', PHS_Params::T_NOHTML );
        $phs_db_charset = PHS_Params::_p( 'phs_db_charset', PHS_Params::T_NOHTML );
        if( !($phs_db_use_pconnect = PHS_Params::_p( 'phs_db_use_pconnect', PHS_Params::T_BOOL )) )
            $phs_db_use_pconnect = false;
        $phs_db_driver_settings = PHS_Params::_p( 'phs_db_driver_settings', PHS_Params::T_NOHTML );

        $do_submit = PHS_Params::_p( 'do_submit', PHS_Params::T_NOHTML );
        $do_test_connection = PHS_Params::_p( 'do_test_connection', PHS_Params::T_NOHTML );

        if( !empty( $do_test_connection ) or !empty( $do_submit ) )
        {
            if( empty( $phs_db_hostname ) )
                $this->add_error_msg( 'Please provide a valid DB hostname.' );

            if( empty( $phs_db_username ) )
                $phs_db_username = '';

            if( empty( $phs_db_password ) )
                $phs_db_password = '';

            if( empty( $phs_db_database ) )
                $this->add_error_msg( 'Please provide a database to be used.' );

            if( empty( $phs_db_prefix ) )
                $phs_db_prefix = '';

            if( empty( $phs_db_port ) )
                $phs_db_port = '';

            if( empty( $phs_db_charset ) )
                $phs_db_charset = '';

            if( empty( $phs_db_driver_settings ) )
                $phs_db_driver_settings = '';
            elseif( !@json_decode( $phs_db_driver_settings, true ) )
                $this->add_error_msg( 'Please provide a valid JSON for driver settings.' );
        }

        if( !empty( $do_test_connection )
        and !$this->has_error_msgs() )
        {
            // Define special connection with provided settings...
            $mysql_settings = array();
            $mysql_settings['driver'] = PHS_Db::DB_DRIVER_MYSQLI;
            $mysql_settings['host'] = $phs_db_hostname;
            $mysql_settings['user'] = $phs_db_username;
            $mysql_settings['password'] = $phs_db_password;
            $mysql_settings['database'] = $phs_db_database;
            $mysql_settings['prefix'] = $phs_db_prefix;
            $mysql_settings['port'] = $phs_db_port;
            $mysql_settings['timezone'] = date( 'P' );
            $mysql_settings['charset'] = $phs_db_charset;
            $mysql_settings['use_pconnect'] = (!empty( $phs_db_use_pconnect )?true:false);
            $mysql_settings['driver_settings'] = (!empty( $phs_db_driver_settings )?@json_decode( $phs_db_driver_settings, true ):array());

            if( empty( $mysql_settings['driver_settings'] ) or !is_array( $mysql_settings['driver_settings'] ) )
                $mysql_settings['driver_settings'] = array();

            if( $this->test_db_connection( $mysql_settings ) )
                $this->add_success_msg( 'Connected to database with success!' );

            else
            {
                if( $this->has_error() )
                    $this->add_error_msg( $this->get_error_message() );
                else
                    $this->add_error_msg( 'Error while testing DB connection.' );
            }
        }

        if( !empty( $do_submit )
        and !$this->has_error_msgs() )
        {
            $defines_arr = array(
                'PHS_DB_HOSTNAME' => $phs_db_hostname,
                'PHS_DB_USERNAME' => $phs_db_username,
                'PHS_DB_PASSWORD' => $phs_db_password,
                'PHS_DB_DATABASE' => $phs_db_database,
                'PHS_DB_PREFIX' => $phs_db_prefix,
                'PHS_DB_PORT' => $phs_db_port,
                'PHS_DB_TIMEZONE' => array(
                    'raw' => 'date( \'P\' )',
                ),
                'PHS_DB_CHARSET' => $phs_db_charset,
                'PHS_DB_USE_PCONNECT' => ($phs_db_use_pconnect?'true':'false'),
                'PHS_DB_DRIVER_SETTINGS' => (empty( $phs_db_driver_settings )?'':@json_encode( @json_decode( $phs_db_driver_settings, true ) )),
            );

            $config_params = array(
                array(
                    'defines' => $defines_arr,
                ),
            );

            if( $this->save_step_config_file( $config_params ) )
            {
                $this->add_success_msg( 'Config file saved with success. Redirecting to next step...' );

                if( ($setup_instance = $this->setup_instance()) )
                    $setup_instance->goto_next_step();
            }

            else
            {
                if( $this->has_error() )
                    $this->add_error_msg( $this->get_error_message() );
                else
                    $this->add_error_msg( 'Error saving config file for current step.' );
            }
        }

        if( empty( $foobar ) )
        {
            if( $this->config_file_loaded() )
            {
                $this->add_notice_msg( 'Existing config file loaded...' );

                $phs_db_hostname = PHS_DB_HOSTNAME;
                $phs_db_username = PHS_DB_USERNAME;
                $phs_db_password = PHS_DB_PASSWORD;
                $phs_db_database = PHS_DB_DATABASE;
                $phs_db_prefix = PHS_DB_PREFIX;
                $phs_db_port = PHS_DB_PORT;
                $phs_db_charset = PHS_DB_CHARSET;
                $phs_db_use_pconnect = PHS_DB_USE_PCONNECT;
                $phs_db_driver_settings = PHS_DB_DRIVER_SETTINGS;
            } else
            {
                $phs_db_hostname = 'localhost';
                $phs_db_username = '';
                $phs_db_password = '';
                $phs_db_database = '';
                $phs_db_prefix = '';
                $phs_db_port = '3306';
                $phs_db_charset = 'UTF8';
                $phs_db_use_pconnect = true;
                $phs_db_driver_settings = @json_encode( array( 'sql_mode' => '-ONLY_FULL_GROUP_BY' ) );
            }
        }

        $data['phs_db_hostname'] = $phs_db_hostname;
        $data['phs_db_username'] = $phs_db_username;
        $data['phs_db_password'] = $phs_db_password;
        $data['phs_db_database'] = $phs_db_database;
        $data['phs_db_prefix'] = $phs_db_prefix;
        $data['phs_db_port'] = $phs_db_port;
        $data['phs_db_charset'] = $phs_db_charset;
        $data['phs_db_use_pconnect'] = $phs_db_use_pconnect;
        $data['phs_db_driver_settings'] = $phs_db_driver_settings;

        return PHS_Setup_layout::get_instance()->render( 'step2', $data );
    }
}
