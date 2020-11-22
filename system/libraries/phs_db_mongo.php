<?php

namespace phs\libraries;

use \phs\PHS_Db;

//! If only one server/db connection is used or parameter sent to settings method is one array containing only one mysql connection settings, these settings will be kept in settings array with this index
/**
 * @deprecated
 */
define( 'PHS_MONGO_DEF_CONNECTION_NAME', '@def_mongo_connection@' );

/**
 *  Mongo class for PHS suite...
 */
class PHS_Db_mongo extends PHS_Db_class
{
    const DEFAULT_CONNECTION_NAME = '@def_mongo_connection@';

    //! Last created manager object
    /** @var bool|\MongoDB\Driver\Manager[] $managers_obj */
    private $managers_obj = null;

    private $last_errors_arr = array();

    //! Hold last query id
    /** @var bool|\MongoDB\Driver\Cursor $query_id */
    private $query_id;

    //! Query result details...
    private $last_inserted_id, $inserted_rows, $updated_rows;

    public function __construct( $mysql_settings = false )
    {
        $this->query_id = false;
        $this->managers_obj = null;
        $this->last_errors_arr = array();

        $this->last_inserted_id = false;
        $this->inserted_rows = 0;
        $this->updated_rows = 0;

        parent::__construct( $mysql_settings );
    }

    public function reset_last_db_error( $connection_name = false )
    {
        if( $connection_name === false )
            $connection_name = $this->default_connection();

        if( isset( $this->last_errors_arr[$connection_name] ) )
            unset( $this->last_errors_arr[$connection_name] );
    }

    //
    //  Abstract methods...
    //
    public function get_last_db_error( $connection_name = false )
    {
        if( $connection_name === false )
            $connection_name = $this->default_connection();

        if( !empty( $this->last_errors_arr[$connection_name] ) )
            return $this->last_errors_arr[$connection_name];

        return '';
    }

    protected function default_custom_settings_structure()
    {
        return array(
            // FULL connection URI(s). This is prefferable as it supports multiple Mongo servers
            // If you use this and you use collection prefixes, don't forget to also provide 'prefix' index
            'connection_string' => '',

            'prefix' => '',

            'user' => '',
            'password' => '',
            'host' => 'localhost',
            'port' => 27017,
            'database' => '',

            'uri_options' => array(),
        );
    }

    protected function custom_settings_validation( $conn_settings )
    {
        if( !$this->custom_settings_are_valid( $conn_settings ) )
            return false;

        if( empty( $conn_settings['uri_options'] ) or !is_array( $conn_settings['uri_options'] ) )
            $conn_settings['uri_options'] = array();

        if( !empty( $conn_settings['port'] ) )
            $conn_settings['port'] = (int)$conn_settings['port'];

        return $conn_settings;
    }

    protected function custom_settings_are_valid( $conn_settings )
    {
        $this->reset_error();

        if( empty( $conn_settings )
         or (empty( $conn_settings['driver'] ) and $conn_settings['driver'] !== PHS_Db::DB_DRIVER_MONGO)
         or (
                (!isset( $conn_settings['database'] ) or !isset( $conn_settings['user'] ) or !isset( $conn_settings['password'] ))
                and
                empty( $conn_settings['connection_string'] )
            ) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Connection settings not passed correctly.' ) );
            return false;
        }

        return $conn_settings;
    }

    protected function default_connection_name()
    {
        return self::DEFAULT_CONNECTION_NAME;
    }
    //
    //  END Abstract methods...
    //

    public function query_id()
    {
        return $this->query_id;
    }

    public function last_inserted_id()
    {
        return $this->last_inserted_id;
    }

    public function affected_rows()
    {
        return $this->inserted_rows + $this->updated_rows;
    }

    public function inserted_rows()
    {
        return $this->inserted_rows;
    }

    public function updated_rows()
    {
        return $this->updated_rows;
    }

    /**
     * @param bool|string $connection_name
     *
     * @return bool|\MongoDB\Driver\Manager Connection manager
     */
    public function is_connected( $connection_name = false )
    {
        if( $connection_name === false )
            $connection_name = $this->default_connection();

        if( empty( $this->managers_obj ) or !is_array( $this->managers_obj )
         or empty( $this->managers_obj[$connection_name] )
         or $this->my_settings === false
         or !@is_object( $this->managers_obj[$connection_name] ) or !($this->managers_obj[$connection_name] instanceof \MongoDB\Driver\Manager) )
            return false;

        return $this->managers_obj[$connection_name];
    }

    public function close( $connection_name = false )
    {
        // There's no close method in MongoDb driver
        return true;
    }

    public function connect( $connection_name = false )
    {
        if( !@class_exists( '\\MongoDB\\Driver\\Manager' ) )
        {
            $this->set_error( self::ERR_CONNECT, self::_t( 'Seems like MongoDB driver is not properly installed.' ) );
            return false;
        }

        if( $connection_name === false )
            $connection_name = $this->default_connection();

        if( ($conn_settings = $this->connection_settings( $connection_name )) === false )
            return false;

        if( ($manager_obj = $this->is_connected( $connection_name )) )
            return $manager_obj;

        if( empty( $this->managers_obj ) or !is_array( $this->managers_obj ) )
            $this->managers_obj = array();

        if( !empty( $conn_settings['connection_string'] ) )
            $host = $conn_settings['connection_string'];

        else
        {
            $host = rawurlencode( $conn_settings['host'] );
            if( !empty( $conn_settings['port'] ) )
                $host .= ':'.intval( $conn_settings['port'] );

            $user_pass = '';
            if( !empty( $conn_settings['user'] ) or !empty( $conn_settings['password'] ) )
            {
                if( !empty( $conn_settings['user'] ) )
                    $user_pass = rawurlencode( $conn_settings['user'] ).':';
                if( !empty( $conn_settings['password'] ) )
                    $user_pass = ($user_pass==''?':':'').rawurlencode( $conn_settings['password'] );

                if( $user_pass != '' )
                    $user_pass .= '@';
            }

            $host = 'mongodb://'.$user_pass.$host;

            if( !empty( $conn_settings['database'] ) )
                $host .= '/'.rawurlencode( $conn_settings['database'] );
        }

        try
        {
            $this->managers_obj[$connection_name] = new \MongoDB\Driver\Manager( $host, $conn_settings['uri_options'], $conn_settings['driver_settings'] );
        } catch( \Exception $e )
        {
            if( isset( $this->managers_obj[$connection_name] ) )
                unset( $this->managers_obj[$connection_name] );

            if( empty( $this->managers_obj ) )
                $this->managers_obj = null;

            $this->set_my_error( self::ERR_CONNECT,
                                 'Cannot connect to Mongo host '.$host.', user '.($conn_settings['user']!=''?$conn_settings['user']:'N/A').($conn_settings['password']!=''?' (with password)':'').'.',
                                 'Cannot connect to Mongo server.',
                                 $connection_name );
            return false;
        }

        if( empty( $this->managers_obj[$connection_name] )
         or !is_object( $this->managers_obj[$connection_name] )
         or !($this->managers_obj[$connection_name] instanceof \MongoDB\Driver\Manager) )
        {
            $this->set_my_error( self::ERR_CONNECT,
                                 'Cannot connect to Mongo host '.$host.', user '.($conn_settings['user']!=''?$conn_settings['user']:'N/A').($conn_settings['password']!=''?' (with password)':'').'.',
                                 'Cannot connect to Mongo server.',
                                 $connection_name );
            return false;
        }

        $this->last_connection_name = $connection_name;

        return true;
    }

    // Returns an INSERT query string for table $table_name for $insert_arr data
    public function quick_insert( $table_name, $insert_arr, $connection_name = false, $params = false )
    {
        return $insert_arr;
    }

    // Returns an EDIT query string for table $table_name for $edit_arr data conditions added outside this method
    // in future where conditions should be added here to support more drivers...
    public function quick_edit( $table_name, $edit_arr, $connection_name = false, $params = false )
    {
        return $edit_arr;
    }

    public function test_connection( $connection_name = false )
    {
        return $this->connect( $connection_name );
    }

    public static function default_query_options_arr()
    {
        return array(
            // For queries against a sharded collection, returns partial results from the mongos if some shards are unavailable instead of throwing an error.
            'allowPartialResults' => false,
            // Determines whether to return the record identifier for each document. If TRUE, adds a top-level "$recordId" field to the returned documents.
            'showRecordId' => true,
            // Number of documents to skip. Defaults to 0.
            'skip' => 0,
            // The maximum number of documents to return.
            'limit' => 0,
        );
    }

    public static function default_read_preference_arr()
    {
        // https://secure.php.net/manual/en/mongodb-driver-readpreference.construct.php
        // final public MongoDB\Driver\ReadPreference::__construct ( string|integer $mode [, array $tagSets = NULL [, array $options = array() ]] )
        return array(
            // int (MongoDB\Driver\ReadPreference constant) or string: where to send the query (primary, primaryPreferred, secondary, secondaryPreferred, nearest)
            // string was added in driver version 1.3.0
            'mode' => \MongoDB\Driver\ReadPreference::RP_PRIMARY_PREFERRED,
            // Tag sets allow you to target read operations to specific members of a replica set
            'tag_sets' => null,
            // options array argument was added in driver version 1.2.0
            'options' => array(),
        );
    }

    public static function default_cursor_type_map()
    {
        return array(
            'root' => 'array',
            'document' => 'array',
            'array' => 'array',
        );
    }

    public static function default_query_arr()
    {
        return array(
            'table_name' => '',
            'filter' => array(),
            'query_options' => self::default_query_options_arr(),
            'read_preference' => self::default_read_preference_arr(),
            'cursor_type_map' => self::default_cursor_type_map(),
        );
    }

    /**
     * Do the query and return query ID as cursor instance
     *
     * @param array $query_arr
     * @param bool $connection_name
     *
     * @return bool|\MongoDB\Driver\Cursor
     */
    public function query( $query_arr, $connection_name = false )
    {
        $query_arr = self::validate_array( $query_arr, self::default_query_arr() );

        if( empty( $query_arr ) or !is_array( $query_arr )
         or empty( $query_arr['table_name'] ) or !is_string( $query_arr['table_name'] )
         or empty( $query_arr['filter'] ) )
        {
            $this->set_my_error( self::ERR_QUERY,
                                 'Invalid query array.',
                                 'Invalid query array',
                                 $connection_name );
            return false;
        }

        if( $connection_name === false )
            $connection_name = $this->default_connection();

        if( ($conn_settings = $this->connection_settings( $connection_name )) === false )
        {
            $this->set_my_error( self::ERR_CONNECT,
                                 'Cannot get MySQL connection settings. Make sure database settings are set.',
                                 'Unknown database settings',
                                 $connection_name );
            return false;
        }

        // if connect wasn't called separately call it now
        if( !$this->is_connected( $connection_name ) and $this->connect( $connection_name ) === false )
        {
            $this->set_my_error( self::ERR_CONNECT,
                                 'Cannot connect to database with connection '.$connection_name.'.',
                                 'Cannot connect to database.',
                                 $connection_name );
            return false;
        }

        // make sure we have a connection
        if( !($manager_obj = $this->is_connected( $connection_name )) )
        {
            $this->set_my_error( self::ERR_CONNECT,
                                 'Cannot connect to database with connection '.$connection_name.'. (server error?)',
                                 'Cannot connect to database.',
                                 $connection_name );
            return false;
        }

        try
        {
            $query_obj = new \MongoDB\Driver\Query( $query_arr['filter'], $query_arr['query_options'] );
        } catch( \Exception $e )
        {
            $this->set_my_error( self::ERR_CONNECT,
                                 'Error creating query object: '.$e->getMessage(),
                                 'Error creating query object.',
                                 $connection_name );
            return false;
        }

        try
        {
            if( empty( $query_arr['read_preference'] ) or !is_array( $query_arr['read_preference'] ) )
                $query_arr['read_preference'] = self::default_read_preference_arr();

            if( defined( 'MONGODB_VERSION' )
            and version_compare( constant( 'MONGODB_VERSION' ), '1.2.0' ) >= 0 )
            {
                $read_preference_obj = new \MongoDB\Driver\ReadPreference( $query_arr['read_preference']['mode'],
                                                            (!empty( $query_arr['read_preference']['tag_sets'] )?$query_arr['read_preference']['tag_sets']:null),
                                                            (!empty( $query_arr['read_preference']['options'] )?$query_arr['read_preference']['options']:array()) );
            } else
            {
                $read_preference_obj = new \MongoDB\Driver\ReadPreference( $query_arr['read_preference']['mode'],
                                                            (!empty( $query_arr['read_preference']['tag_sets'] )?$query_arr['read_preference']['tag_sets']:null) );
            }
        } catch( \Exception $e )
        {
            $this->set_my_error( self::ERR_CONNECT,
                                 'Failed creating read preference for query: '.$e->getMessage(),
                                 'Failed creating read preference for query.',
                                 $connection_name );
            return false;
        }

        $namespace_str = $conn_settings['database'].'.'.$query_arr['table_name'];

        try
        {
            if( defined( 'MONGODB_VERSION' )
            and version_compare( constant( 'MONGODB_VERSION' ), '1.2.0' ) >= 0 )
            {
                $cursor_obj = $manager_obj->executeQuery( $namespace_str,
                                                          $query_obj,
                                                          array( 'readPreference' => $read_preference_obj ) );
            } else
            {
                $cursor_obj = $manager_obj->executeQuery( $namespace_str,
                                                          $query_obj,
                                                          $read_preference_obj );
            }

            if( !empty( $query_arr['cursor_type_map'] ) )
                $cursor_obj->setTypeMap( $query_arr['cursor_type_map'] );

        } catch( \Exception $e )
        {
            $this->set_my_error( self::ERR_QUERY,
                'Error executing query for namespace <b>'.$namespace_str.'</b>:'."\n".
                         'Query: '.print_r( $query_arr, true )."\n".
                         'Error: '.$e->getMessage(),
                'Error executing query.',
                $connection_name );

            return false;
        }

        var_dump( $cursor_obj );
        // var_dump( $cursor_obj->toArray() );

        $this->query_id = $cursor_obj;
        $this->queries_number( true );

        return $this->query_id;
    }

    public function escape( $fields, $connection_name = false )
    {
        return $fields;
    }

    public function queries_number( $incr = false )
    {
        static $queries_no;

        if( !is_numeric( $queries_no ) )
            $queries_no = 0;

        if( $incr === false )
            return $queries_no;

        $queries_no++;

        return $queries_no;
    }

    public function fetch_assoc( $qid )
    {
        if( empty( $qid )
            // MongoDB\Driver\Cursor
         or gettype( $qid ) != 'object'
         or !($qid instanceof \MongoDB\Driver\Cursor) )
            return false;

        try {
            if( !($result_arr = $qid->toArray())
             or empty( $result_arr[0] ) )
                return false;
        } catch( \Exception $e )
        {
            return false;
        }

        return $result_arr[0];
    }

    public function num_rows( $qid )
    {
        if( empty( $qid )
         or gettype( $qid ) != 'object'
         or !($qid instanceof \MongoDB\Driver\Cursor) )
            return false;

        try {
            if( !($result_arr = $qid->toArray())
             or !($count_val = count( $result_arr )) )
                return 0;
        } catch( \Exception $e )
        {
            return 0;
        }

        return $count_val;
    }

    /**
     * @inheritdoc
     */
    public function dump_database( $dump_params = false )
    {
        if( !($dump_params = self::validate_array_recursive( $dump_params, self::default_dump_parameters() ))
         or !($dump_params = parent::dump_database( $dump_params ))
         or empty( $dump_params['binaries'] ) or !is_array( $dump_params['binaries'] )
         or empty( $dump_params['binaries']['mongodump_bin'] )
         or empty( $dump_params['connection_identifier'] ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_PARAMETERS, self::_t( 'Error validating database dump parameters.' ) );
            return false;
        }

        if( !($connection_settings = $this->connection_settings( $dump_params['connection_name'] ))
         or !is_array( $connection_settings ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_PARAMETERS, self::_t( 'Error validating database dump parameters.' ) );
            return false;
        }

        $connection_identifier = $dump_params['connection_identifier']['identifier'];

        $credentials_file = $dump_params['output_dir'].'/export_'.$connection_identifier.'.cnf';
        if( !($fil = @fopen( $credentials_file, 'w' )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error creating dump credentials file.' ) );
            return false;
        }

        if( !@fputs( $fil, '[mysqldump]'."\n".
                           'user = '.$connection_settings['user']."\n".
                           'password = '.$connection_settings['password']."\n" ) )
        {
            @unlink( $credentials_file );

            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Couldn\'t write to dump credentials file.' ) );
            return false;
        }
        @fflush( $fil );
        @fclose( $fil );

        $dump_file = $connection_identifier.'.'.$dump_params['connection_identifier']['type'];

        $output_file = $dump_params['output_dir'].'/'.$dump_file;

        if( empty( $dump_params['dump_commands_for_shell'] ) or !is_array( $dump_params['dump_commands_for_shell'] ) )
            $dump_params['dump_commands_for_shell'] = array();
        if( empty( $dump_params['delete_files_after_export'] ) or !is_array( $dump_params['delete_files_after_export'] ) )
            $dump_params['delete_files_after_export'] = array();
        if( empty( $dump_params['generated_files'] ) or !is_array( $dump_params['generated_files'] ) )
            $dump_params['generated_files'] = array();

        $dump_params['generated_files'][] = $credentials_file;

        // mysqldump -av --user=root -p --add-drop-table=true --comments=true testdb > testdb.sql 2> output.log
        $dump_params['dump_commands_for_shell'][] = $dump_params['binaries']['mysqldump_bin'].
                                                 ' --defaults-extra-file='.$credentials_file.
                                                 ' --host='.$connection_settings['host'].' --port='.$connection_settings['port'].
                                                 (!empty( $dump_params['log_file'] )?' --log-error='.$dump_params['log_file']:'').
                                                 ' -av --add-drop-table=true --comments=true '.
                                                 $connection_settings['database'].' > '.$output_file;

        if( empty( $dump_params['zip_dump'] ) )
            $dump_params['resulting_files']['dump_files'][] = $output_file;

        else
        {
            $zip_file = $dump_params['output_dir'].'/'.$connection_identifier.'_'.$dump_params['connection_identifier']['type'].'.zip';

            $dump_params['dump_commands_for_shell'][] = $dump_params['binaries']['zip_bin'].' -q '.$zip_file.' '.$dump_file;

            $dump_params['delete_files_after_export'][] = $output_file;

            $dump_params['resulting_files']['dump_files'][] = $zip_file;
        }

        if( !empty( $dump_params['log_file'] ) )
            $dump_params['resulting_files']['log_files'][] = $dump_params['log_file'];

        $dump_params['delete_files_after_export'][] = $credentials_file;

        return $dump_params;
    }

}
