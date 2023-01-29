<?php

namespace phs\libraries;

use \phs\PHS_Db;
use \SQLite3;
use \SQLite3Result;

/**
 *  SQLite class parser for PHS suite...
 */
class PHS_Db_sqlite extends PHS_Db_class
{
    public const DEFAULT_CONNECTION_NAME = '@def_connection@';

    //! Tells if class should close connection to mongo server after done with query
    private bool $close_after_query = true;

    //! In case connection is not closed after each query this should keep connection id
    /** @var null|array[string]  */
    private ?array $connection_id;

    /** @var ?SQLite3Result $query_id  */
    private ?SQLite3Result $query_id;

    /** @var int */
    private int $last_inserted_id;
    private int $affected_rows;

    public function __construct( $mysql_settings = null )
    {
        $this->query_id = null;
        $this->connection_id = null;

        $this->last_inserted_id = 0;
        $this->affected_rows = 0;

        parent::__construct( $mysql_settings );
    }

    public function query_id(): ?SQLite3Result
    {
        return $this->query_id;
    }

    public function last_inserted_id()
    {
        return $this->last_inserted_id;
    }

    public function affected_rows(): int
    {
        return $this->affected_rows;
    }

    public function close_after_query( $var = null ): bool
    {
        if( $var === null ) {
            return $this->close_after_query;
        }

        $this->close_after_query = (bool)$var;
        return $this->close_after_query;
    }

    //
    //  Abstract methods...
    //
    /**
     * @param false|string $connection_name
     *
     * @return string
     */
    public function get_last_db_error( $connection_name = false ): string
    {
        if( $connection_name === false ) {
            $connection_name = $this->default_connection();
        }

        /** @var SQLite3|null $obj */
        if( ($obj = $this->is_connected( $connection_name )) ) {
            return $obj->lastErrorMsg();
        }

        return '';
    }

    protected function default_custom_settings_structure(): array
    {
        $db_flags = (defined('SQLITE3_OPEN_READWRITE') && defined('SQLITE3_OPEN_CREATE')) ?
            SQLITE3_OPEN_READWRITE|SQLITE3_OPEN_CREATE : 0;

        return [
            // filename or :memory:
            'database' => '',
            'flags' => $db_flags,
            // If you want to encrypt database...
            'encryption_key' => '',
            'prefix' => '',
            'charset' => '',
            'timezone' => '',
        ];
    }

    protected function custom_settings_validation( array $conn_settings ): ?array
    {
        if( !$this->custom_settings_are_valid( $conn_settings ) ) {
            return null;
        }

        return $conn_settings;
    }

    protected function custom_settings_are_valid( $conn_settings )
    {
        $this->reset_error();

        if( empty( $conn_settings )
         || (empty( $conn_settings['driver'] ) && $conn_settings['driver'] !== PHS_Db::DB_DRIVER_SQLITE)
         || empty( $conn_settings['database'] )
         || !isset( $conn_settings['flags'] ) || !isset( $conn_settings['encryption_key'] ) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Database details not present in settings array.' ) );
            return false;
        }

        return $conn_settings;
    }

    /**
     * @return string
     */
    protected function default_connection_name(): string
    {
        return self::DEFAULT_CONNECTION_NAME;
    }
    //
    //  END Abstract methods...
    //

    /**
     * @param false|string $connection_name
     *
     * @return null|SQLite3
     */
    public function is_connected( $connection_name = false ): ?SQLite3
    {
        if( $connection_name === false ) {
            $connection_name = $this->default_connection();
        }

        if( empty( $this->connection_id ) || !is_array( $this->connection_id )
         || empty( $this->connection_id[$connection_name] )
         || $this->my_settings === false
         || !@is_object( $this->connection_id[$connection_name] ) || !($this->connection_id[$connection_name] instanceof SQLite3) ) {
            return null;
        }

        return $this->connection_id[$connection_name];
    }

    /**
     * @param false|string $connection_name
     *
     * @return bool
     */
    public function close( $connection_name = false ): bool
    {
        if( $connection_name === false ) {
            $connection_name = $this->default_connection();
        }

        if( !($con = $this->is_connected( $connection_name )) ) {
            return false;
        }

        $con->close();
        $this->connection_id[$connection_name] = null;

        return true;
    }

    /**
     * @param false|string $connection_name
     *
     * @return bool
     */
    public function connect( $connection_name = false ): bool
    {
        if( $connection_name === false ) {
            $connection_name = $this->default_connection();
        }

        if( ($conn_settings = $this->connection_settings( $connection_name )) === false ) {
            return false;
        }

        if( $this->is_connected( $connection_name ) ) {
            return true;
        }

        if( empty( $this->connection_id ) || !is_array( $this->connection_id ) ) {
            $this->connection_id = [];
        }

        try {
            $this->connection_id[$connection_name]
                = new SQLite3( $conn_settings['database'], $conn_settings['flags'], $conn_settings['encryption_key'] );
        } catch( \Exception $e ) {
            $this->connection_id[$connection_name] = null;
        }

        if( empty( $this->connection_id[$connection_name] )
         || !is_object( $this->connection_id[$connection_name] ) || !($this->connection_id[$connection_name] instanceof SQLite3) )
        {
            $this->set_my_error( self::ERR_CONNECT,
                                 'Cannot connect to database '.$conn_settings['database'].'.',
                                 'Cannot connect to database.',
                                 $connection_name );
            return false;
        }

        $this->last_connection_name = $connection_name;

        return true;
    }

    /**
     * Returns an INSERT query string for table $table_name for $insert_arr data
     *
     * @param string $table_name
     * @param array $insert_arr
     * @param bool|string $connection_name
     * @param bool|array $params
     *
     * @return string
     */
    public function quick_insert( $table_name, $insert_arr, $connection_name = false, $params = false ): string
    {
        if( !is_array( $insert_arr ) || !count( $insert_arr ) ) {
            return '';
        }

        if( empty( $params ) || !is_array( $params ) ) {
            $params = [];
        }

        if( !isset( $params['escape'] ) ) {
            $params['escape'] = true;
        }

        $return = '';
        foreach( $insert_arr as $key => $val )
        {
            if( $val === null ) {
                $field_value = 'NULL';
            }

            elseif( is_array( $val ) )
            {
                if( !array_key_exists( 'value', $val ) ) {
                    continue;
                }

                if( empty( $val['raw_field'] ) ) {
                    $val['raw_field'] = false;
                }

                $field_value = $val['value'];

                if( $field_value === null ) {
                    $field_value = 'NULL';
                }

                elseif( empty( $val['raw_field'] ) )
                {
                    if( !empty( $params['escape'] ) ) {
                        $field_value = $this->escape($field_value, $connection_name);
                    }

                    $field_value = '\''.$field_value.'\'';
                }
            } else {
                $field_value = '\''.(!empty($params['escape']) ? $this->escape($val, $connection_name) : $val).'\'';
            }

            $return .= '`'.$key.'`='.$field_value.', ';
        }

        if( $return === '' ) {
            return '';
        }

        return 'INSERT INTO `'.$table_name.'` SET '.substr( $return, 0, -2 );
    }

    /**
     * Returns an EDIT query string for table $table_name for $edit_arr data conditions added outside this method
     * in future where conditions should be added here to support more drivers...
     *
     * @param string $table_name
     * @param array $edit_arr
     * @param bool|string $connection_name
     * @param bool|array $params
     *
     * @return string
     */
    public function quick_edit( $table_name, $edit_arr, $connection_name = false, $params = false ): string
    {
        if( !is_array( $edit_arr ) || !count( $edit_arr ) ) {
            return '';
        }

        if( empty( $params ) || !is_array( $params ) ) {
            $params = [];
        }

        if( !isset( $params['escape'] ) ) {
            $params['escape'] = true;
        }

        $return = '';
        foreach( $edit_arr as $key => $val )
        {
            if( $val === null ) {
                $field_value = 'NULL';
            }

            elseif( is_array( $val ) )
            {
                if( !array_key_exists( 'value', $val ) ) {
                    continue;
                }

                if( empty( $val['raw_field'] ) ) {
                    $val['raw_field'] = false;
                }

                $field_value = $val['value'];

                if( $field_value === null ) {
                    $field_value = 'NULL';
                }

                elseif( empty( $val['raw_field'] ) )
                {
                    if( !empty( $params['escape'] ) ) {
                        $field_value = $this->escape($field_value, $connection_name);
                    }

                    $field_value = '\''.$field_value.'\'';
                }
            } else {
                $field_value = '\''.(!empty($params['escape']) ? $this->escape($val, $connection_name) : $val).'\'';
            }

            $return .= '`'.$key.'`='.$field_value.', ';
        }

        if( $return === '' ) {
            return '';
        }

        return 'UPDATE `'.$table_name.'` SET '.substr( $return, 0, -2 );
    }

    /**
     * @param string $format
     * @param bool|array $fields
     * @param false|string $connection_name
     *
     * @return bool|\mysqli_result
     */
    public function formated_query( $format, $fields = false, $connection_name = false )
    {
        if( $connection_name === false ) {
            $connection_name = $this->default_connection();
        }

        if( ($conn_settings = $this->connection_settings( $connection_name )) === false ) {
            return false;
        }

        // We connect now to database because we don't need escape and query methods to open 2 connections...
        // if connect wasn't called separately, call it now
        if( !$this->is_connected( $connection_name )
         && $this->connect( $connection_name ) === false ) {
            return false;
        }

        // make sure we have a connection
        if( !$this->is_connected( $connection_name ) ) {
            return false;
        }

        if( !empty( $fields ) ) {
            $fields = $this->escape($fields, $connection_name);
        }

        $mysql_str = @vsprintf( $format, $fields );
        if( $mysql_str === '' )
        {
            $this->set_my_error( self::ERR_QUERY,
                                 'Bad format for query: '.(is_string( $format )?htmlspecialchars( $format ):print_r( $format, true )).(is_array( $fields )?' - ['.count( $fields ).' parameters passed]':''),
                                 'Bad query format.',
                                 $connection_name );
            if( $this->close_after_query ) {
                $this->close();
            }
            return false;
        }

        $qid = $this->query( $mysql_str, $connection_name );

        // just to be sure...
        if( $this->close_after_query ) {
            $this->close($connection_name);
        }

        return $qid;
    }

    /**
     * @param bool|string $connection_name
     *
     * @return bool
     */
    public function test_connection( $connection_name = false ): bool
    {
        $this->reset_error();

        if( !$this->query( 'SELECT * FROM sqlite_schema;', $connection_name ) )
        {
            if( !$this->has_error() ) {
                $this->set_my_error(
                    self::ERR_CONNECT, 'Connection test failed.',
                    'Connection test failed',
                    $connection_name);
            }

            return false;
        }

        return true;
    }

    /**
     * Do the query and return query ID
     *
     * @param string|array $query
     * @param false|string $connection_name
     *
     * @return null|SQLite3Result
     */
    public function query( $query, $connection_name = false )
    {
        if( $connection_name === false ) {
            $connection_name = $this->default_connection();
        }

        // if connect wasn't called separately call it now
        if( !$this->is_connected( $connection_name ) && $this->connect( $connection_name ) === false )
        {
            $this->set_my_error( self::ERR_CONNECT,
                                 'Cannot connect to database with connection '.$connection_name.'.',
                                 'Cannot connect to database.',
                                 $connection_name );
            return null;
        }

        // make sure we have a connection
        if( !($sqlite = $this->is_connected( $connection_name )) )
        {
            $this->set_my_error( self::ERR_CONNECT,
                                 'Cannot connect to database with connection '.$connection_name.'.',
                                 'Cannot connect to database.',
                                 $connection_name );
            return null;
        }

        $last_error_msg = '';
        if( !($this->query_id = $sqlite->query( $query )) ) {
            $this->query_id = null;
            $last_error_msg = $sqlite->lastErrorMsg();
        }

        $this->queries_number( true );

        if( !$this->query_id )
        {
            $this->set_my_error( self::ERR_QUERY,
                                 'Error on query: '.(is_string( $query )?htmlspecialchars( $query ):print_r( $query, true ))."\n".
                                 'SQLite error: ['.$last_error_msg.']',
                                 'Error running query.',
                                 $connection_name );
            if( $this->close_after_query ) {
                $this->close($connection_name);
            }
            return null;
        }

        $this->last_inserted_id = $sqlite->lastInsertRowID();
        $this->affected_rows = $sqlite->changes();

        if( $this->close_after_query ) {
            $this->close($connection_name);
        }

        return $this->query_id;
    }

    /**
     * Run a stored query and return query result
     *
     * @return bool|\mysqli_result
     */
    public function s_query()
    {
        $numargs = @func_num_args();
        $arg_list = @func_get_args();

        if( !is_array( $arg_list ) || empty( $numargs ) ) {
            return false;
        }

        if( count( $arg_list ) === 1 && is_array( $arg_list[0] ) ) {
            $arg_list = $arg_list[0];
        }

        $connection_name = false;

        $last_param = $arg_list[$numargs-1];
        if( is_string( $last_param ) && $this->connection_settings( $last_param ) !== false )
        {
            $connection_name = $arg_list[$numargs-1];
            $numargs--;
            unset( $arg_list[$numargs] );

            // Check if only connection name was passed as parameter
            if( empty( $numargs ) )
            {
                $this->set_my_error( self::ERR_QUERY,
                                     'Unknown stored query (with connection: <b>'.
                                     (is_string( $connection_name )?htmlspecialchars( $connection_name ):print_r( $connection_name, true ))
                                     .'</b>)',
                                     'Query error.',
                                     $connection_name );
                return false;
            }
        }

        if( $connection_name === false ) {
            $connection_name = $this->default_connection();
        }

        $qname = @array_shift( $arg_list );
        if( ($query_info = self::stored_query( $qname )) === false )
        {
            $this->set_my_error( self::ERR_QUERY,
                                 'Stored query not found: <b>'.
                                 (is_string( $qname )?htmlspecialchars( $qname ):print_r( $qname, true ))
                                 .'</b>',
                                 'Query error.',
                                 $connection_name );
            return false;
        }

        if( is_array( $arg_list ) && ($al_count = count( $arg_list )) )
        {
            $qparams = $arg_list;
            if( $al_count <= $query_info['pcount'] ) {
                $qparams[] = '';
            }
        } else {
            $qparams = [''];
        }

        $mysql_str = @vsprintf( $query_info['query'], $qparams );
        if( $mysql_str === '' )
        {
            $this->set_my_error( self::ERR_QUERY,
                                 'Bad format for query: '.@htmlspecialchars( $query_info['query'] ).
                                 (is_array( $qparams )?' - ['.count( $qparams ).' parameters passed]':''),
                                 'Bad format for query.',
                                 $connection_name );

            if( $this->close_after_query ) {
                $this->close($connection_name);
            }

            return false;
        }

        $qid = $this->query( $mysql_str, $connection_name );

        // just to be sure...
        if( $this->close_after_query ) {
            $this->close($connection_name);
        }

        return $qid;
    }

    /**
     * Populate a stored query with parameters sent to this method and return resulting query as string
     *
     * @return bool|string
     */
    public function get_squery()
    {
        $numargs = @func_num_args();
        $arg_list = @func_get_args();

        if( !is_array( $arg_list ) || empty( $numargs ) ) {
            return false;
        }

        if( count( $arg_list ) === 1 && is_array( $arg_list[0] ) ) {
            $arg_list = $arg_list[0];
        }

        $connection_name = false;

        $last_param = $arg_list[$numargs-1];
        if( is_string( $last_param ) && $this->connection_settings( $last_param ) !== false )
        {
            $connection_name = $arg_list[$numargs-1];
            $numargs--;
            unset( $arg_list[$numargs] );

            // Check if only connection name was passed as parameter
            if( empty( $numargs ) )
            {
                $this->set_my_error( self::ERR_QUERY,
                                     'Unknown stored query (with connection: <b>'.
                                     (is_string( $connection_name )?htmlspecialchars( $connection_name ):print_r( $connection_name, true )).
                                     '</b>)',
                                     'Query error.',
                                     $connection_name );
                return false;
            }
        }

        if( $connection_name === false ) {
            $connection_name = $this->default_connection();
        }

        $qname = @array_shift( $arg_list );
        if( ($query_info = self::stored_query( $qname )) === false )
        {
            $this->set_my_error( self::ERR_QUERY,
                                 'Stored query not found: <b>'.
                                 (is_string( $qname )?htmlspecialchars( $qname ):print_r( $qname, true )).
                                 '</b>',
                                 'Query error.',
                                 $connection_name );
            return false;
        }

        if( is_array( $arg_list ) && ($al_count = count( $arg_list )) )
        {
            $qparams = $arg_list;
            if( $al_count <= $query_info['pcount'] ) {
                $qparams[] = '';
            }
        } else {
            $qparams = [''];
        }

        $mysql_str = @vsprintf( $query_info['query'], $qparams );
        if( $mysql_str === '' )
        {
            $this->set_my_error( self::ERR_QUERY,
                                 'Bad format for query: '.@htmlspecialchars( $query_info['query'] ).
                                 (is_array( $qparams )?' - ['.count( $qparams ).' parameters passed]':''),
                                 'Bad format for query.',
                                 $connection_name );
            if( $this->close_after_query ) {
                $this->close();
            }
            return false;
        }

        return $mysql_str;
    }

    /**
     * @return array
     */
    public static function get_default_driver_settings(): array
    {
        return [];
    }

    /**
     * Define a predefined/stored MySQL query as a string which will be used against vsprintf (placeholders: %s, %d, etc)
     *
     * @param  string  $qname Stored query name
     * @param  null|string  $qformat If null, method will return stored query with $qname as name
     *
     * @return bool|string
     */
    public static function stored_query( string $qname, string $qformat = null )
    {
        static $stored_queries;

        if( $qformat === null )
        {
            if( empty( $stored_queries ) || !is_array( $stored_queries ) || !isset( $stored_queries[$qname] ) ) {
                return false;
            }

            return $stored_queries[$qname];
        }

        if( !isset( $stored_queries ) || !is_array( $stored_queries ) ) {
            $stored_queries = [];
        }

        if( !isset( $stored_queries[$qname] ) || !is_array( $stored_queries[$qname] ) ) {
            $stored_queries[$qname] = [];
        }

        $stored_queries[$qname]['query'] = $qformat.' %s';
        $stored_queries[$qname]['pcount'] = @substr_count( $qformat, '%s' );
        if( empty( $stored_queries[$qname]['pcount'] ) ) {
            $stored_queries[$qname]['pcount'] = 0;
        }

        return true;
    }

    /**
     * Escape a single value or an array of values that should be sent to MySQL in a query
     *
     * @param array|int|string $fields
     * @param false|string $connection_name
     *
     * @return array<string>|false
     */
    public function escape( $fields, $connection_name = false )
    {
        if( $connection_name === false ) {
            $connection_name = $this->default_connection();
        }

        if( ($conn_settings = $this->connection_settings( $connection_name )) === false ) {
            return false;
        }

        if( empty( $fields ) ) {
            return $fields;
        }

        if( !is_array( $fields ) ) {
            $escape_fields = [0 => $fields];
        } else {
            $escape_fields = $fields;
        }

        // if connect wasn't called separately call it now
        $connection_opened_now = false;
        if( !$this->is_connected( $connection_name ) )
        {
            if( $this->connect( $connection_name ) === false ) {
                return false;
            }

            $connection_opened_now = true;
        }

        // make sure we have a connection
        if( !($sqlite = $this->is_connected( $connection_name )) ) {
            return false;
        }

        foreach( $escape_fields as $key => $val )
        {
            if( !is_string( $val ) ) {
                continue;
            }

            $escape_fields[$key] = $sqlite::escapeString( $val );
        }

        if( $connection_opened_now && $this->close_after_query ) {
            $this->close($connection_name);
        }

        return (!is_array( $fields )?$escape_fields[0]:$escape_fields);
    }

    /**
     * @param  int  $error_code
     * @param  string  $debug_err
     * @param  string  $short_err
     * @param false|string $connection_name
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
                $error = $this->get_error();

                if( ($sqlite = $this->is_connected( $connection_name )) ) {
                    echo '<p><b>SQLite error</b>: '.$sqlite->lastErrorMsg().'</p>';
                }
                echo '<p><pre>'.$error['error_msg'].'</pre></p>';
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

    /**
     * @param bool $incr
     *
     * @return int
     */
    public function queries_number( bool $incr = false ): int
    {
        static $queries_no = 0;

        if( $incr === false ) {
            return $queries_no;
        }

        $queries_no++;
        return $queries_no;
    }

    /**
     * @param SQLite3Result $qid
     *
     * @return false|string[]|null
     */
    public function fetch_assoc( $qid ): ?array
    {
        if( empty( $qid )
         || !($qid instanceof SQLite3Result) ) {
            return null;
        }

        return $qid->fetchArray( SQLITE3_ASSOC );
    }

    /**
     * @param SQLite3Result $qid
     *
     * @return int
     */
    public function num_rows( $qid ): int
    {
        if( empty( $qid )
         || !($qid instanceof SQLite3Result ) ) {
            return 0;
        }

        return $qid->numColumns();
    }

    /**
     * @param bool|array $dump_params Array containing dump parameters
     *
     * @return array|bool Returns populated $dump_params array
     */
    public function dump_database( $dump_params = false )
    {
        if( !($dump_params = self::validate_array_recursive( $dump_params, self::default_dump_parameters() ))
         || !($dump_params = parent::dump_database( $dump_params ))
         || empty( $dump_params['connection_identifier'] ) )
        {
            if( !$this->has_error() ) {
                $this->set_error(self::ERR_PARAMETERS, self::_t('Error validating database dump parameters.'));
            }
            return false;
        }

        if( !($connection_settings = $this->connection_settings( $dump_params['connection_name'] ))
         || !is_array( $connection_settings ) )
        {
            if( !$this->has_error() ) {
                $this->set_error(self::ERR_PARAMETERS, self::_t('Error validating database dump parameters.'));
            }
            return false;
        }

        $connection_identifier = $dump_params['connection_identifier']['identifier'];

        $dump_file = $connection_identifier.'.'.$dump_params['connection_identifier']['type'];

        $output_file = $dump_params['output_dir'].'/'.$dump_file;

        if( empty( $dump_params['dump_commands_for_shell'] ) || !is_array( $dump_params['dump_commands_for_shell'] ) ) {
            $dump_params['dump_commands_for_shell'] = [];
        }
        if( empty( $dump_params['delete_files_after_export'] ) || !is_array( $dump_params['delete_files_after_export'] ) ) {
            $dump_params['delete_files_after_export'] = [];
        }
        if( empty( $dump_params['generated_files'] ) || !is_array( $dump_params['generated_files'] ) ) {
            $dump_params['generated_files'] = [];
        }

        // We just copy the sqlite file...
        $dump_params['dump_commands_for_shell'][] = 'cp '.$connection_settings['database'].' '.$output_file;

        if( empty( $dump_params['zip_dump'] ) ) {
            $dump_params['resulting_files']['dump_files'][] = $output_file;
        }

        else
        {
            $zip_file = $dump_params['output_dir'].'/'.$connection_identifier.'_'.$dump_params['connection_identifier']['type'].'.zip';

            $dump_params['dump_commands_for_shell'][] = $dump_params['binaries']['zip_bin'].' -q '.$zip_file.' '.$dump_file;

            $dump_params['delete_files_after_export'][] = $output_file;

            $dump_params['resulting_files']['dump_files'][] = $zip_file;
        }

        return $dump_params;
    }

}
