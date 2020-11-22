<?php

namespace phs;

use \phs\libraries\PHS_Registry;
use \phs\libraries\PHS_Db_mysqli;
use \phs\libraries\PHS_Db_mongo;

final class PHS_Db extends PHS_Registry
{
    const ERR_DATABASE = 2000;

    const DB_DEFAULT_CONNECTION = 'db_default_connection', DB_DEFAULT_DRIVER = 'db_default_driver', DB_SETTINGS = 'db_settings',
          DB_MYSQLI_INSTANCE = 'db_mysqli_instance', DB_MONGO_INSTANCE = 'db_mongo_instance';

    const DB_DRIVER_MYSQLI = 'mysqli', DB_DRIVER_MONGO = 'mongo';
    private static $KNOWN_DB_DRIVERS = array(
        self::DB_DRIVER_MYSQLI => 'MySQLi',
        self::DB_DRIVER_MONGO => 'Mongo',
    );

    private static $inited = false;
    private static $instance = false;
    private static $check_db_fields_boundaries = true;

    public function __construct()
    {
        parent::__construct();

        self::init();
    }

    /**
     * @return string[]
     */
    public static function known_db_drivers()
    {
        return self::$KNOWN_DB_DRIVERS;
    }

    /**
     * @param string $driver
     *
     * @return bool|string
     */
    public static function valid_db_driver( $driver )
    {
        if( empty( $driver ) || !is_string( $driver )
         || empty( self::$KNOWN_DB_DRIVERS[$driver] ) )
            return false;

        return self::$KNOWN_DB_DRIVERS[$driver];
    }

    /**
     * @return array
     */
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

    /**
     * @param array $settings_arr
     *
     * @return array|bool
     */
    public static function validate_db_connection_settings( $settings_arr )
    {
        self::st_reset_error();
        if( empty( $settings_arr ) || !is_array( $settings_arr )
         || empty( $settings_arr['host'] ) || empty( $settings_arr['database'] ) )
        {
            self::st_set_error( self::ERR_DATABASE, self::_t( 'Invalid database settings' ) );
            return false;
        }

        $settings_arr = self::validate_array( $settings_arr, self::get_default_db_connection_settings_arr() );

        if( empty( $settings_arr['driver'] )
         || !self::valid_db_driver( $settings_arr['driver'] ) )
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

    /**
     * @param string $connection_name
     * @param array $settings_arr
     *
     * @return array|bool
     */
    public static function add_db_connection( $connection_name, $settings_arr )
    {
        if( empty( $connection_name )
         || empty( $settings_arr ) || !is_array( $settings_arr ) )
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

        if( !self::default_db_connection( $settings_arr['driver'] ) )
            self::default_db_connection( $settings_arr['driver'], $connection_name );

        return $settings_arr;
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
        if( ($connection_name !== false && !is_string( $connection_name ))
         || !($all_connections = self::get_data( self::DB_SETTINGS )) )
            return false;

        if( $connection_name === false )
            return $all_connections;

        if( empty( $all_connections[$connection_name] ) )
            return false;

        return $all_connections[$connection_name];
    }

    /**
     * Tells database drivers to check table fields for their limits (value boundaries) when sending data to the database. (If applicable)
     * For MySQL driver this means check int max and min values
     * @param null|bool $check
     *
     * @return bool
     */
    public static function check_db_fields_boundaries( $check = null )
    {
        if( $check === null )
            return self::$check_db_fields_boundaries;

        self::$check_db_fields_boundaries = (!empty( $check ));

        return self::$check_db_fields_boundaries;
    }

    public static function get_connection_identifier( $connection_name )
    {
        if( empty( $connection_name )
         || !($connection_settings = self::get_db_connection( $connection_name ))
         || !is_array( $connection_settings )
         || !($connection_settings = self::validate_array( $connection_settings, self::get_default_db_connection_settings_arr() )) )
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

    /**
     * @param bool $driver
     * @param bool $connection_name
     *
     * @return bool|mixed
     */
    public static function default_db_connection( $driver = false, $connection_name = false )
    {
        if( $driver === false )
            $driver = self::default_db_driver();

        if( $connection_name === false )
        {
            if( !($default_connection_arr = self::get_data( self::DB_DEFAULT_CONNECTION ))
             || !is_array( $default_connection_arr ) )
                $default_connection_arr = array();

            if( empty( $default_connection_arr[$driver] ) )
                return false;

            return $default_connection_arr[$driver];
        }

        if( !($settings_arr = self::get_db_connection( $connection_name ))
         || !is_array( $settings_arr ) )
            return false;

        if( !($default_connection_arr = self::get_data( self::DB_DEFAULT_CONNECTION ))
         || !is_array( $default_connection_arr ) )
            $default_connection_arr = array();

        $default_connection_arr[$settings_arr['driver']] = $connection_name;

        self::set_data( self::DB_DEFAULT_CONNECTION, $default_connection_arr );

        return $connection_name;
    }

    public static function default_db_driver( $driver = false )
    {
        if( $driver === false )
            return self::get_data( self::DB_DEFAULT_DRIVER );

        if( !self::valid_db_driver( $driver ) )
            return false;

        self::set_data( self::DB_DEFAULT_DRIVER, $driver );

        return $driver;
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
         || !is_string( $connection_name ) )
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
     * @return bool|PHS_Db_mysqli Returns instance of database class management
     */
    public static function db_drivers_init()
    {
        self::st_reset_error();

        // If no database connection was defined assume we don't need a database?
        if( !($all_connections = self::get_data( self::DB_SETTINGS ))
         || empty( $all_connections ) )
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
     * @return bool|\phs\libraries\PHS_Db_interface Returns new or cached database driver instance
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
                /** @var PHS_Db_mysqli $db_instance */
                if( !($db_instance = self::get_data( self::DB_MYSQLI_INSTANCE )) )
                {
                    include_once( PHS_LIBRARIES_DIR . 'phs_db_mysqli.php' );

                    if( !($db_instance = new PHS_Db_mysqli())
                     || $db_instance->has_error() )
                    {
                        if( $db_instance )
                            self::st_copy_error( $db_instance );
                        else
                            self::st_set_error( self::ERR_DATABASE, self::_t( 'Database initialization error (MySQLi driver).' ) );

                        return false;
                    }

                    $on_debugging_mode = self::st_debugging_mode();

                    $db_instance->display_errors( ($on_debugging_mode && !PHS_DB_SILENT_ERRORS) );
                    $db_instance->die_on_errors( PHS_DB_DIE_ON_ERROR );
                    $db_instance->debug_errors( $on_debugging_mode );
                    $db_instance->close_after_query( PHS_DB_CLOSE_AFTER_QUERY );

                    self::set_data( self::DB_MYSQLI_INSTANCE, $db_instance );
                }
            break;

            case self::DB_DRIVER_MONGO:
                /** @var PHS_Db_mongo $db_instance */
                if( !($db_instance = self::get_data( self::DB_MONGO_INSTANCE )) )
                {
                    include_once( PHS_LIBRARIES_DIR . 'phs_db_mongo.php' );

                    if( !($db_instance = new PHS_Db_mongo())
                     || $db_instance->has_error() )
                    {
                        if( $db_instance )
                            self::st_copy_error( $db_instance );
                        else
                            self::st_set_error( self::ERR_DATABASE, self::_t( 'Database initialization error (Mongo driver).' ) );

                        return false;
                    }

                    $on_debugging_mode = self::st_debugging_mode();

                    $db_instance->display_errors( ($on_debugging_mode && !PHS_DB_SILENT_ERRORS) );
                    $db_instance->die_on_errors( PHS_DB_DIE_ON_ERROR );
                    $db_instance->debug_errors( $on_debugging_mode );

                    self::set_data( self::DB_MONGO_INSTANCE, $db_instance );
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
     * @return bool|\phs\libraries\PHS_Db_interface Returns database driver instance
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

        self::$instance = new PHS_Db();
        return self::$instance;
    }
}
