<?php

namespace phs\libraries;

use \phs\PHS_Db;

/**
 *  MySQL class parser for PHS suite...
 */
abstract class PHS_Db_class extends PHS_Registry implements PHS_Db_interface
{
    public const ERR_CONNECT = 1, ERR_QUERY = 2, ERR_DATABASE = 3;

    //! Database settings - array with connection settings (can hold one or more database connection settings).
    //! Eg. $my_settings['default']['host'] = 'localhost'; $my_settings['default']['port'] = 'XXXX'; ... etc
    // @see PHS_Db_mysql::settings()
    protected $my_settings;

    //! Default connection index from $my_settings array
    /** @var false|string $my_def_connection */
    protected $my_def_connection;

    //! Last connection index used from $my_settings array (the one which is opened now)
    protected $last_connection_name;

    //! Behaviour: Display errors
    protected bool $display_errors;

    //! Behaviour: Die on error
    protected bool $die_on_errors;

    //! Behaviour: Display more info on errors
    protected bool $debug_errors;

    // Used to surpress database errors on specific queries
    protected ?array $error_state = null;

    public function __construct( $mysql_settings = false )
    {
        parent::__construct();

        $this->my_settings = false;
        $this->my_def_connection = false;
        $this->last_connection_name = false;

        // Default behaviours
        $this->display_errors = true;
        $this->die_on_errors = true;
        $this->debug_errors = true;
        $this->error_state = null;

        $this->settings( $mysql_settings );
    }

    abstract public function get_last_db_error( $connection_name );

    abstract protected function default_custom_settings_structure(): array;
    abstract protected function custom_settings_validation( array $conn_settings ): ?array;

    /**
     * @param array $conn_settings
     *
     * @return array|false
     */
    abstract protected function custom_settings_are_valid( $conn_settings );

    /**
     * @return string
     */
    abstract protected function default_connection_name(): string;

    protected function default_common_settings_structure(): array
    {
        return [
            // defaults to MySQLi driver...
            'driver' => PHS_Db::DB_DRIVER_MYSQLI,
            'driver_settings' => [],
            'default' => false,
        ];
    }

    public function default_settings_structure(): array
    {
        if( !($custom_structure = $this->default_custom_settings_structure()) ) {
            $custom_structure = [];
        }
        if( !($common_structure = $this->default_common_settings_structure()) ) {
            $common_structure = [];
        }

        return self::validate_array( $custom_structure, $common_structure );
    }

    /**
     * @param string|false $connection_name
     * @param bool|array $conn_settings
     *
     * @return bool
     */
    public function connection_settings( $connection_name, $conn_settings = false )
    {
        if( $connection_name === false ) {
            $connection_name = $this->default_connection();
        }

        if( $conn_settings === false )
        {
            if( $this->my_settings !== false
             && isset( $this->my_settings[$connection_name] )
             && is_array( $this->my_settings[$connection_name] ) ) {
                return $this->my_settings[$connection_name];
            }

            return false;
        }

        $this->reset_error();

        if( !($settings_struct = $this->default_settings_structure()) ) {
            $settings_struct = [];
        }

        if( !($validated_conn_settings = self::validate_array( $conn_settings, $settings_struct ))
         || !($validated_conn_settings = $this->custom_settings_validation( $validated_conn_settings )) )
        {
            if( !$this->has_error() ) {
                $this->set_error(self::ERR_PARAMETERS, self::_t('Database connection settings validation failed.'));
            }

            return false;
        }

        if( $this->my_settings === false ) {
            $this->my_settings = [];
        }

        if( empty( $validated_conn_settings['driver_settings'] )  || !is_array( $validated_conn_settings['driver_settings'] ) ) {
            $validated_conn_settings['driver_settings'] = [];
        }

        $this->my_settings[$connection_name] = $validated_conn_settings;

        if( !empty( $validated_conn_settings['default'] ) ) {
            $this->my_def_connection = $connection_name;
        }

        // If no default is passed, get first server as default...
        if( empty( $this->my_def_connection ) ) {
            $this->my_def_connection = $connection_name;
        }

        return true;
    }

    /**
     * @param  null|array  $conn_settings
     *
     * @return array|bool
     */
    public function settings( array $conn_settings = null )
    {
        if( $conn_settings === null ) {
            return $this->my_settings;
        }

        $this->reset_error();

        if( !is_array( $conn_settings ) )
        {
            $this->set_error( self::ERR_PARAMETERS, 'Bad settings array.' );
            return false;
        }

        if( $this->custom_settings_are_valid( $conn_settings ) )
        {
            // Only one connection settings was passed...
            return $this->connection_settings( $this->default_connection_name(), $conn_settings );
        }

        // In case we don't have a single array with connection settings and have an array with more db settings reset error set by connection validation
        $this->reset_error();

        $got_an_error = false;
        foreach( $conn_settings as $connection_name => $connection_settings )
        {
            if( $this->connection_settings( $connection_name, $connection_settings ) === false ) {
                $got_an_error = true;
            }
        }

        if( $got_an_error ) {
            return false;
        }

        return true;
    }

    /**
     * @param  null|string  $connection_name
     *
     * @return string|bool
     */
    public function default_connection( string $connection_name = null )
    {
        if( $connection_name === null ) {
            return $this->my_def_connection;
        }

        if( empty( $this->my_settings ) || empty( $this->my_settings[$connection_name] ) ) {
            return false;
        }

        $this->my_def_connection = $connection_name;

        return true;
    }

    public function display_errors( $var = null ): bool
    {
        if( $var === null ) {
            return $this->display_errors;
        }

        $this->display_errors = (bool)$var;
        return $this->display_errors;
    }

    public function die_on_errors( $var = null ): bool
    {
        if( $var === null ) {
            return $this->die_on_errors;
        }

        $this->die_on_errors = (bool)$var;
        return $this->die_on_errors;
    }

    public function debug_errors( $var = null ): bool
    {
        if( $var === null ) {
            return $this->debug_errors;
        }

        $this->debug_errors = (bool)$var;
        return $this->debug_errors;
    }

    public static function default_dump_parameters(): array
    {
        return [
            // input parameters
            'connection_name' => false,
            'output_dir' => '',
            'log_file' => '',
            'zip_dump' => true,

            // Binary files used in dump (for all known drivers)
            'binaries' => [
                'zip_bin' => 'zip',
                'mysqldump_bin' => 'mysqldump',
                'mongodump_bin' => 'mongodump',
            ],

            // output parameters
            'connection_identifier' => [],
            'dump_commands_for_shell' => [],
            'delete_files_after_export' => [],
            // Files to be deleted in case we get an error in dump process
            'generated_files' => [],
            'resulting_files' => [
                'dump_files' => [],
                'log_files' => [],
            ],
        ];
    }

    //! This method does only common part on checking and validating $dump_params
    /**
     * @inheritdoc
     */
    public function dump_database( $dump_params = false )
    {
        if( !($dump_params = self::validate_array_recursive( $dump_params, self::default_dump_parameters() )) ) {
            $dump_params = [];
        }

        if( empty( $dump_params['output_dir'] ) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Please provide output directory for database dump.' ) );
            return false;
        }

        $dump_params['output_dir'] = rtrim( $dump_params['output_dir'], '/\\' );

        if( !@is_dir( $dump_params['output_dir'] )
         || !@is_writable( $dump_params['output_dir'] ) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Output directory does not exist or is not writable.' ) );
            return false;
        }

        if( !($connection_identifier = PHS_Db::get_connection_identifier( $dump_params['connection_name'] ))
         || empty( $connection_identifier['identifier'] ) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Couldn\'t get connection identifier for database connection.' ) );
            return false;
        }

        if( empty( $connection_identifier['type'] ) ) {
            $connection_identifier['type'] = 'dump';
        }

        $dump_params['connection_identifier'] = $connection_identifier;

        if( empty( $dump_params['log_file'] ) ) {
            $dump_params['log_file'] =
                $dump_params['output_dir'].'/'.$connection_identifier['identifier'].'_'.$connection_identifier['type'].'.log';
        }

        if( ($dirname = @dirname( $dump_params['log_file'] )) )
        {
            if( !@is_dir( $dirname )
             || !@is_writable( $dirname ) )
            {
                $this->set_error( self::ERR_PARAMETERS, self::_t( 'Directory of log file does not exist or is not writable.' ) );
                return false;
            }
        }

        return $dump_params;
    }

    // Suppress any errors database driver might throw
    public function suppress_errors(): void
    {
        if( !empty( $this->error_state ) ) {
            return;
        }

        $this->error_state = [
            'display_errors' => $this->display_errors,
            'debug_errors' => $this->debug_errors,
            'die_on_errors' => $this->die_on_errors,
        ];

        $this->display_errors = false;
        $this->debug_errors = false;
        $this->die_on_errors = false;
    }

    // Restore error handling functions as before suppress_errors() method was called
    public function restore_errors_state(): void
    {
        if( empty( $this->error_state ) ) {
            return;
        }

        $this->display_errors = $this->error_state['display_errors'];
        $this->debug_errors = $this->error_state['debug_errors'];
        $this->die_on_errors = $this->error_state['die_on_errors'];

        $this->error_state = null;
    }

    /**
     * @param  int  $error_code
     * @param  string  $debug_err
     * @param  string  $short_err
     * @param string|false $connection_name
     *
     * @return void
     */
    protected function set_my_error( int $error_code, string $debug_err, string $short_err, $connection_name = false ): void
    {
        if( $connection_name === false ) {
            $connection_name = $this->default_connection();
        }

        $error_params = [];
        $error_params['prevent_throwing_errors'] = !empty( $this->error_state );

        $this->set_error( $error_code, $debug_err, '', $error_params );

        if( $this->display_errors )
        {
            if( $this->debug_errors )
            {
                if( ($db_error = $this->get_last_db_error( $connection_name )) ) {
                    echo '<p><b>DB error</b>: '.$db_error.'</p>';
                }

                echo '<p><pre>'.$this->get_simple_error_message().'</pre></p>';
            } else
            {
                echo '<h2>Database error. ('.$short_err.')</h2>';
            }
        }

        if( $this->die_on_errors )
        {
            if( $this->display_errors ) {
                echo '<p>This script cannot continue and will be stoped.</p>';
            }
            die();
        }
    }
}
