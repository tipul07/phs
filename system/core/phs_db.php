<?php

namespace phs;

use \phs\libraries\PHS_Registry;
use \phs\libraries\PHS_db_mysqli;

final class PHS_db extends PHS_Registry
{
    const ERR_DATABASE = 2000;

    const DB_DEFAULT_CONNECTION = 'db_default_connection', DB_SETTINGS = 'db_settings', DB_MYSQLI_INSTANCE = 'db_mysqli_instance';

    const DB_DRIVER_MYSQLI = 'mysqli';//, DB_DRIVER_MONGO = 'mongo';
    private static $KNOWN_DB_DRIVERS = array(
        self::DB_DRIVER_MYSQLI => 'MySQLi',
        //self::DB_DRIVER_MONGO => 'Mongo',
    );

    private static $inited = false;
    private static $instance = false;

    function __construct()
    {
        parent::__construct();

        self::init();
    }

    public static function known_db_drivers()
    {
        return self::$KNOWN_DB_DRIVERS;
    }

    public static function valid_db_driver( $driver )
    {
        if( empty( $driver ) or !is_string( $driver )
         or empty( self::$KNOWN_DB_DRIVERS[$driver] ) )
            return false;

        return self::$KNOWN_DB_DRIVERS[$driver];
    }

    public static function get_default_db_connection_settings_arr()
    {
        return array(
            'driver' => self::DB_DRIVER_MYSQLI,
            'host' => 'localhost',
            'user' => '',
            'password' => '',
            'database' => '',
            'prefix' => '',
            'port' => '3306',
            'timezone' => date( 'P' ),
            'charset' => 'UTF8',
            'use_pconnect' => true,
            // tells if connection was passed to database driver
            'connection_passed' => false,
        );
    }

    public static function validate_db_connection_settings( $settings_arr )
    {
        self::st_reset_error();
        if( empty( $settings_arr ) or !is_array( $settings_arr )
         or empty( $settings_arr['host'] ) or empty( $settings_arr['database'] ) )
        {
            self::st_set_error( self::ERR_DATABASE, self::_t( 'Invalid database settings' ) );
            return false;
        }

        $default_settings = self::get_default_db_connection_settings_arr();
        foreach( $default_settings as $key => $val )
        {
            if( !array_key_exists( $key, $settings_arr ) )
                $settings_arr[$key] = $val;
        }

        if( !self::valid_db_driver( $settings_arr['driver'] ) )
        {
            self::st_set_error( self::ERR_DATABASE,
                                self::_t( 'Invalid database driver' ),
                                'Invalid database driver ('.$settings_arr['driver'].'), valid drivers ('.implode( ', ', self::$KNOWN_DB_DRIVERS ).').'
                            );
            return false;
        }

        $settings_arr['connection_passed'] = false;

        return $settings_arr;
    }

    public static function add_db_connection( $connection_name, $settings_arr )
    {
        if( empty( $connection_name )
         or empty( $settings_arr ) or !is_array( $settings_arr ) )
        {
            self::st_set_error( self::ERR_DATABASE, self::_t( 'Invalid connection or database settings' ) );
            return false;
        }

        if( !($settings_arr = self::validate_db_connection_settings( $settings_arr )) )
            return false;

        if( !($existing_db_settings = self::get_data( self::DB_SETTINGS )) )
            $existing_db_settings = array();

        $existing_db_settings[$connection_name] = $settings_arr;

        self::set_data( self::DB_SETTINGS, $existing_db_settings );

        if( !self::default_db_connection() )
            self::default_db_connection( $connection_name );

        return true;
    }

    /**
     * Returns database settings for a specific connection. These settings are not settings saved inside specific driver, but generic database settings.
     * Inside driver settings connection name passed as false means default connection. Here we don't have connection name as false!
     *
     * @param string|bool $connection_name Connection name
     *
     * @return false|array All or required connection settings
     */
    public static function get_db_connection( $connection_name = false )
    {
        if( !($all_connections = self::get_data( self::DB_SETTINGS ))
         or ($connection_name !== false and !is_string( $connection_name )) )
            return false;

        if( $connection_name === false )
            return $all_connections;

        if( empty( $all_connections[$connection_name] ) )
            return false;

        return $all_connections[$connection_name];
    }

    public static function get_connection_identifier( $connection_name )
    {
        if( empty( $connection_name )
         or !($connection_settings = self::get_db_connection( $connection_name ))
         or !is_array( $connection_settings )
         or !($connection_settings = self::validate_array( $connection_settings, self::get_default_db_connection_settings_arr() )) )
            return false;

        $return_arr = array();
        // As unique as possible identifier for database connection (as string)
        $return_arr['identifier'] = false;
        // Extension given to dump file/dir
        $return_arr['type'] = 'dump';

        switch( $connection_settings['driver'] )
        {
            case self::DB_DRIVER_MYSQLI:
                $return_arr['identifier'] = $connection_settings['driver'];
                $return_arr['identifier'] .= (!empty( $return_arr['identifier'] )?'__':'').str_replace( '.', '_', $connection_settings['host'] );
                $return_arr['identifier'] .= (!empty( $return_arr['identifier'] )?'__':'').$connection_settings['database'];

                $return_arr['type'] = 'sql';
            break;
        }

        return $return_arr;
    }

    public static function default_db_connection( $connection_name = false )
    {
        if( $connection_name === false )
            return self::get_data( self::DB_DEFAULT_CONNECTION );

        if( !self::db_connection_exists( $connection_name ) )
            return false;

        self::set_data( self::DB_DEFAULT_CONNECTION, $connection_name );

        return $connection_name;
    }

    /**
     * Tells if a specific connection is defined
     *
     * @param string $connection_name Connection name
     *
     * @return bool True if connection exists, false otherwise
     */
    public static function db_connection_exists( $connection_name )
    {
        if( !($all_connections = self::get_data( self::DB_SETTINGS ))
         or !is_string( $connection_name ) )
            return false;

        if( empty( $all_connections[$connection_name] ) )
            return false;

        return true;
    }

    /**
     * Get all connections defined till now and pass them to each database driver instance
     * This is an alternative to on-demand database drivers instantiation and settings pass
     *
     * Better use on-demand version as this would instantiate maybe un-used database drivers (still in debate if to remove this)
     *
     * @return bool|PHS_db_mysqli Returns instance of database class management
     */
    public static function db_drivers_init()
    {
        self::st_reset_error();

        // If no database connection was defined assume we don't need a database?
        if( !($all_connections = self::get_data( self::DB_SETTINGS ))
         or empty( $all_connections ) )
            return true;

        $we_did_something = false;
        foreach( $all_connections as $connection_name => $settings_arr )
        {
            if( $settings_arr['connection_passed'] )
                continue;

            if( !($driver_instance = self::get_db_driver_instance( $settings_arr['driver'] )) )
                return false;

            if( !$driver_instance->connection_settings( $connection_name, $settings_arr ) )
            {
                self::st_copy_error( $driver_instance );
                return false;
            }

            $all_connections[$connection_name]['connection_passed'] = true;
            $we_did_something = true;
        }

        if( $we_did_something )
            self::set_data( self::DB_SETTINGS, $all_connections );

        return true;
    }

    /**
     * @param string $driver What driver should be instantiated
     *
     * @return bool|\phs\libraries\PHS_db_interface Returns new or cached database driver instance
     */
    public static function get_db_driver_instance( $driver )
    {
        if( !self::valid_db_driver( $driver ) )
        {
            self::st_set_error( self::ERR_DATABASE,
                self::_t( 'Invalid database driver' ),
                'Invalid database driver (' . $driver . '), valid drivers (' . implode( ', ', self::$KNOWN_DB_DRIVERS ) . ').'
            );

            return false;
        }

        $db_instance = false;
        switch( $driver )
        {
            case self::DB_DRIVER_MYSQLI:
                /** @var PHS_db_mysqli $db_instance */
                if( !($db_instance = self::get_data( self::DB_MYSQLI_INSTANCE )) )
                {
                    include_once( PHS_LIBRARIES_DIR . 'phs_db_mysqli.php' );

                    if( !($db_instance = new PHS_db_mysqli())
                     or $db_instance->has_error() )
                    {
                        if( $db_instance )
                            self::st_copy_error( $db_instance );
                        else
                            self::st_set_error( self::ERR_DATABASE, self::_t( 'Database initialization error.' ) );

                        return false;
                    }

                    $on_debugging_mode = self::st_debugging_mode();

                    $db_instance->display_errors( ($on_debugging_mode and !PHS_DB_SILENT_ERRORS) );
                    $db_instance->die_on_errors( PHS_DB_DIE_ON_ERROR );
                    $db_instance->debug_errors( $on_debugging_mode );
                    $db_instance->close_after_query( PHS_DB_CLOSE_AFTER_QUERY );

                    self::set_data( self::DB_MYSQLI_INSTANCE, $db_instance );
                }
            break;

            default:
                self::st_set_error( self::ERR_DATABASE,
                    self::_t( 'Database driver is not implemented yet.' ),
                    'Database driver (' . $driver . ') is not implemented yet.'
                );
            break;
        }

        return $db_instance;
    }

    /**
     * On-demand database driver instantiation... best way to use database connections...
     *
     * @param bool|string $connection_name Connection used with database
     *
     * @return bool|\phs\libraries\PHS_db_interface|\phs\libraries\PHS_Language Returns database driver instance
     */
    public static function db( $connection_name = false )
    {
        self::st_reset_error();

        if( !($all_connections = self::get_data( self::DB_SETTINGS )) )
        {
            self::st_set_error( self::ERR_DATABASE, self::_t( 'No database connection configured.' ) );
            return false;
        }

        if( $connection_name === false )
            $connection_name = self::default_db_connection();

        if( !self::db_connection_exists( $connection_name ) )
        {
            self::st_set_error( self::ERR_DATABASE,
                                self::_t( 'Unknown database connection.',
                                'Database connection ['.(!empty( $connection_name )?$connection_name:'N/A').'] is not defined' ) );
            return false;
        }

        $settings_arr = $all_connections[$connection_name];

        if( !($driver_instance = self::get_db_driver_instance( $settings_arr['driver'] )) )
            return false;

        if( empty( $settings_arr['connection_passed'] ) )
        {
            if( !$driver_instance->connection_settings( $connection_name, $settings_arr ) )
            {
                self::st_copy_error( $driver_instance );
                return false;
            }

            $all_connections[$connection_name]['connection_passed'] = true;

            self::set_data( self::DB_SETTINGS, $all_connections );
        }

        return $driver_instance;
    }

    /**
     * Check what server receives in request
     */
    public static function init()
    {
        if( self::$inited )
            return;

        self::reset_registry();

        self::$inited = true;
    }

    private static function reset_registry()
    {
        self::set_data( self::DB_SETTINGS, array() );
    }

    public static function get_instance()
    {
        if( !empty( self::$instance ) )
            return self::$instance;

        self::$instance = new PHS_db();
        return self::$instance;
    }
}
