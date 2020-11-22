<?php

namespace phs\libraries;

use \phs\PHS_Db;

//! \author Andy (andy [at] sqnp [dot] net)
//! \version 2.01

//! If only one server/db connection is used or parameter sent to settings method is one array containing only one mysql connection settings, these settings will be kept in settings array with this index
/**
 * @deprecated
 */
define( 'PHS_MYSQL_DEF_CONNECTION_NAME', '@def_connection@' );

/**
 *  MySQL class parser for PHS suite...
 */
class PHS_Db_mysqli extends PHS_Db_class
{
    const DEFAULT_CONNECTION_NAME = '@def_connection@';

    //! Tells if class should close connection to mongo server after done with query
    private $close_after_query;

    //! In case connection is not closed after each query this should keep connection id
    /** @var \mysqli[string]  */
    private $connection_id;

    //! Hold last query id
    private $query_id;

    //! Query result details...
    private $last_inserted_id, $affected_rows;

    public function __construct( $mysql_settings = false )
    {
        $this->close_after_query = true;

        $this->query_id = false;
        $this->connection_id = null;

        $this->last_inserted_id = false;
        $this->affected_rows = 0;

        parent::__construct( $mysql_settings );
    }

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
        return $this->affected_rows;
    }

    public function close_after_query( $var = null )
    {
        if( $var === null )
            return $this->close_after_query;

        $this->close_after_query = $var;
        return $this->close_after_query;
    }

    //
    //  Abstract methods...
    //
    public function get_last_db_error( $connection_name = false )
    {
        if( $connection_name === false )
            $connection_name = $this->default_connection();

        if( $this->is_connected( $connection_name ) )
            return @mysqli_error( $this->connection_id[$connection_name] );

        return '';
    }

    protected function default_custom_settings_structure()
    {
        return array(
            'user' => '',
            'password' => '',
            'host' => 'localhost',
            'port' => 3306,
            'database' => '',
            'prefix' => '',
            'charset' => '',
            'timezone' => '',
            'use_pconnect' => true,
        );
    }

    protected function custom_settings_validation( $conn_settings )
    {
        if( !$this->custom_settings_are_valid( $conn_settings ) )
            return false;

        if( !empty( $conn_settings['port'] ) )
            $conn_settings['port'] = (int)$conn_settings['port'];
        if( isset( $conn_settings['use_pconnect'] ) )
            $conn_settings['use_pconnect'] = (!empty( $conn_settings['use_pconnect'] )?true:false);
        else
            $conn_settings['use_pconnect'] = true;

        return $conn_settings;
    }

    protected function custom_settings_are_valid( $conn_settings )
    {
        $this->reset_error();

        if( empty( $conn_settings )
         or (empty( $conn_settings['driver'] ) and $conn_settings['driver'] !== PHS_Db::DB_DRIVER_MYSQLI)
         or !isset( $conn_settings['database'] ) or !isset( $conn_settings['user'] ) or !isset( $conn_settings['password'] ) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Database, user or password not pressent in settings array.' ) );
            return false;
        }

        return $conn_settings;
    }

    /**
     * @return string
     */
    protected function default_connection_name()
    {
        return self::DEFAULT_CONNECTION_NAME;
    }
    //
    //  END Abstract methods...
    //

    public function is_connected( $connection_name = false )
    {
        if( $connection_name === false )
            $connection_name = $this->default_connection();

        if( empty( $this->connection_id ) or !is_array( $this->connection_id )
         or empty( $this->connection_id[$connection_name] )
         or $this->my_settings === false
         or !@is_object( $this->connection_id[$connection_name] ) or !($this->connection_id[$connection_name] instanceof \mysqli) )
            return false;

        return $this->connection_id[$connection_name];
    }

    public function close( $connection_name = false )
    {
        if( $connection_name === false )
            $connection_name = $this->default_connection();

        if( !$this->is_connected( $connection_name ) )
            return false;

        @mysqli_close( $this->connection_id[$connection_name] );
        $this->connection_id[$connection_name] = null;

        return true;
    }

    public function connect( $connection_name = false )
    {
        if( $connection_name === false )
            $connection_name = $this->default_connection();

        if( ($conn_settings = $this->connection_settings( $connection_name )) === false )
            return false;

        if( ($resource_id = $this->is_connected( $connection_name )) )
            return $resource_id;

        if( empty( $this->connection_id ) or !is_array( $this->connection_id ) )
            $this->connection_id = array();

        $host = $conn_settings['host'];
        if( !empty( $conn_settings['use_pconnect'] ) )
            $host = 'p:'.$conn_settings['host'];

        $this->connection_id[$connection_name] = @mysqli_connect( $host, $conn_settings['user'], $conn_settings['password'], $conn_settings['database'], $conn_settings['port'] );

        if( empty( $this->connection_id[$connection_name] )
         or !is_object( $this->connection_id[$connection_name] ) or !($this->connection_id[$connection_name] instanceof \mysqli) )
        {
            $this->set_my_error( self::ERR_CONNECT,
                                 'Cannot connect to '.$host.', user '.$conn_settings['user'].($conn_settings['password']!==''?' (with password)':'').(!empty( $conn_settings['use_pconnect'] )?' (using permanent)':'').'.',
                                 'Cannot connect to database server.',
                                 $connection_name );
            return false;
        }

        $this->last_connection_name = $connection_name;

        if( !empty( $conn_settings['charset'] ) )
        {
            @mysqli_query( $this->connection_id[$connection_name], 'SET NAMES \''.@mysqli_real_escape_string( $this->connection_id[$connection_name], $conn_settings['charset'] ).'\'' );
            @mysqli_query( $this->connection_id[$connection_name], 'SET CHARACTER SET \''.@mysqli_real_escape_string( $this->connection_id[$connection_name], $conn_settings['charset'] ).'\'' );
            @mysqli_set_charset( $this->connection_id[$connection_name], $conn_settings['charset'] );
        }

        if( !empty( $conn_settings['timezone'] ) )
        {
            @mysqli_query( $this->connection_id[$connection_name], 'SET time_zone = \''.@mysqli_real_escape_string( $this->connection_id[$connection_name], $conn_settings['timezone'] ).'\'' );
        }

        if( !empty( $conn_settings['driver_settings'] ) and is_array( $conn_settings['driver_settings'] ) )
        {
            $conn_settings['driver_settings'] = self::validate_array_recursive( $conn_settings['driver_settings'], self::get_default_driver_settings() );

            $sql_mode_add_arr = array();
            $sql_mode_remove_arr = array();
            if( !empty( $conn_settings['driver_settings']['sql_mode'] ) and is_string( $conn_settings['driver_settings']['sql_mode'] )
            and ($sql_mode_arr = explode( ',', $conn_settings['driver_settings']['sql_mode'] )) )
            {
                foreach( $sql_mode_arr as $mysql_mode_flag )
                {
                    $mysql_mode_flag = trim( $mysql_mode_flag );
                    if( substr( $mysql_mode_flag, 0, 1 ) === '-' )
                        $sql_mode_remove_arr[substr( $mysql_mode_flag, 1 )] = true;

                    else
                    {
                        if( substr( $mysql_mode_flag, 0, 1 ) === '+' )
                            $mysql_mode_flag = substr( $mysql_mode_flag, 1 );

                        $sql_mode_add_arr[$mysql_mode_flag] = true;
                    }
                }

                $new_sql_mode_arr = array();
                if( (!empty( $sql_mode_remove_arr ) or !empty( $sql_mode_add_arr ))
                and ($qid = @mysqli_query( $this->connection_id[$connection_name], 'SELECT @@session.sql_mode AS session_sql_mode' ))
                and ($session_data = @mysqli_fetch_assoc( $qid ))
                and isset( $session_data['session_sql_mode'] ) )
                {
                    if( !empty( $session_data['session_sql_mode'] )
                    and ($session_sql_mode_arr = explode( ',', $session_data['session_sql_mode'] )) )
                    {
                        foreach( $session_sql_mode_arr as $mysql_mode_flag )
                        {
                            $mysql_mode_flag = trim( $mysql_mode_flag );
                            if( empty( $mysql_mode_flag )
                             or !empty( $sql_mode_remove_arr[$mysql_mode_flag] ) )
                                continue;

                            $new_sql_mode_arr[] = $mysql_mode_flag;
                        }
                    }

                    if( !empty( $sql_mode_add_arr ) )
                    {
                        foreach( $sql_mode_add_arr as $mysql_mode_flag => $junk )
                        {
                            $new_sql_mode_arr[] = $mysql_mode_flag;
                        }
                    }
                }

                if( !empty( $new_sql_mode_arr ) )
                {
                    @mysqli_query( $this->connection_id[$connection_name], 'SET @@session.sql_mode = \''.@mysqli_real_escape_string( $this->connection_id[$connection_name], implode( ',', $new_sql_mode_arr ) ).'\'' );
                }
            }
        }

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
    public function quick_insert( $table_name, $insert_arr, $connection_name = false, $params = false )
    {
        if( !is_array( $insert_arr ) or !count( $insert_arr ) )
            return '';

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !isset( $params['escape'] ) )
            $params['escape'] = true;

        $return = '';
        foreach( $insert_arr as $key => $val )
        {
            if( $val === null )
                $field_value = 'NULL';

            elseif( is_array( $val ) )
            {
                if( !array_key_exists( 'value', $val ) )
                    continue;

                if( empty( $val['raw_field'] ) )
                    $val['raw_field'] = false;

                $field_value = $val['value'];

                if( $field_value === null )
                    $field_value = 'NULL';

                elseif( empty( $val['raw_field'] ) )
                {
                    if( !empty( $params['escape'] ) )
                        $field_value = $this->escape( $field_value, $connection_name );

                    $field_value = '\''.$field_value.'\'';
                }
            } else
                $field_value = '\''.(!empty( $params['escape'] )?$this->escape( $val, $connection_name ):$val).'\'';

            $return .= '`'.$key.'`='.$field_value.', ';
        }

        if( $return === '' )
            return '';

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
    public function quick_edit( $table_name, $edit_arr, $connection_name = false, $params = false )
    {
        if( !is_array( $edit_arr ) or !count( $edit_arr ) )
            return '';

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !isset( $params['escape'] ) )
            $params['escape'] = true;

        $return = '';
        foreach( $edit_arr as $key => $val )
        {
            if( $val === null )
                $field_value = 'NULL';

            elseif( is_array( $val ) )
            {
                if( !array_key_exists( 'value', $val ) )
                    continue;

                if( empty( $val['raw_field'] ) )
                    $val['raw_field'] = false;

                $field_value = $val['value'];

                if( $field_value === null )
                    $field_value = 'NULL';

                elseif( empty( $val['raw_field'] ) )
                {
                    if( !empty( $params['escape'] ) )
                        $field_value = $this->escape( $field_value, $connection_name );

                    $field_value = '\''.$field_value.'\'';
                }
            } else
                $field_value = '\''.(!empty( $params['escape'] )?$this->escape( $val, $connection_name ):$val).'\'';

            $return .= '`'.$key.'`='.$field_value.', ';
        }

        if( $return === '' )
            return '';

        return 'UPDATE `'.$table_name.'` SET '.substr( $return, 0, -2 );
    }

    /**
     * @param string $format
     * @param bool|array $fields
     * @param bool|string $connection_name
     *
     * @return bool|\mysqli_result
     */
    public function formated_query( $format, $fields = false, $connection_name = false )
    {
        if( $connection_name === false )
            $connection_name = $this->default_connection();

        if( ($conn_settings = $this->connection_settings( $connection_name )) === false )
            return false;

        // We connect now to database because we don't need escape and query methods to open 2 connections...
        // if connect wasn't called separately call it now
        if( !$this->is_connected( $connection_name ) and $this->connect( $connection_name ) === false )
            return false;

        // make sure we have a connection
        if( !$this->is_connected( $connection_name ) )
            return false;

        if( $fields !== false and !empty( $fields ) )
            $fields = $this->escape( $fields, $connection_name );

        $mysql_str = @vsprintf( $format, $fields );
        if( $mysql_str === '' )
        {
            $this->set_my_error( self::ERR_QUERY,
                                 'Bad format for query: '.(is_string( $format )?htmlspecialchars( $format ):print_r( $format, true )).(is_array( $fields )?' - ['.count( $fields ).' parameters passed]':''),
                                 'Bad query format.',
                                 $connection_name );
            if( $this->close_after_query )
                $this->close();
            return false;
        }

        $qid = $this->query( $mysql_str, $connection_name );

        // just to be sure...
        if( $this->close_after_query )
            $this->close( $connection_name );

        return $qid;
    }

    /**
     * @param bool|string $connection_name
     *
     * @return bool
     */
    public function test_connection( $connection_name = false )
    {
        $this->reset_error();

        if( !$this->query( 'SHOW TABLES;', $connection_name ) )
        {
            if( !$this->has_error() )
                $this->set_my_error( self::ERR_CONNECT,
                                     'Connection test failed.',
                                     'Connection test failed',
                                     $connection_name );

            return false;
        }

        return true;
    }

    /**
     * Do the query and return query ID
     *
     * @param string|array $query
     * @param bool $connection_name
     *
     * @return bool|\mysqli_result
     */
    public function query( $query, $connection_name = false )
    {
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
        if( !$this->is_connected( $connection_name ) )
        {
            $this->set_my_error( self::ERR_CONNECT,
                                 'Cannot connect to database with connection '.$connection_name.'. (server error?)',
                                 'Cannot connect to database.',
                                 $connection_name );
            return false;
        }

        if( !@mysqli_select_db( $this->connection_id[$connection_name], $conn_settings['database'] ) )
        {
            $this->set_my_error( self::ERR_DATABASE,
                'Cannot acces <b>'.$conn_settings['database'].'</b> database with user <b>'.$conn_settings['user'].'</b>.',
                'Cannot select database.',
                $connection_name );
            $this->close();
            return false;
        }

        $this->query_id = @mysqli_query( $this->connection_id[$connection_name], $query );
        $this->queries_number( true );

        if( !$this->query_id )
        {
            $this->set_my_error( self::ERR_QUERY,
                                 'Error on query: '.(is_string( $query )?htmlspecialchars( $query ):print_r( $query, true ))."\n".
                                 'MySQL error: ['.@mysqli_error( $this->connection_id[$connection_name] ).']',
                                 'Error running query.',
                                 $connection_name );
            if( $this->close_after_query )
                $this->close( $connection_name );
            return false;
        }

        $this->last_inserted_id = @mysqli_insert_id( $this->connection_id[$connection_name] );
        /**
        If the last query was a DELETE query with no WHERE clause, all of the records will have been deleted from the table but this function will return zero
        with MySQL versions prior to 4.1.2.

        When using UPDATE, MySQL will not update columns where the new value is the same as the old value. This creates the possibility that mysql_affected_rows()
        may not actually equal the number of rows matched, only the number of rows that were literally affected by the query.

        The REPLACE statement first deletes the record with the same primary key and then inserts the new record. This function returns the number of deleted records
        plus the number of inserted records.
        **/
        $this->affected_rows = @mysqli_affected_rows( $this->connection_id[$connection_name] );

        if( $this->close_after_query )
            $this->close( $connection_name );

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

        if( !is_array( $arg_list ) or empty( $numargs ) )
            return false;

        if( count( $arg_list ) === 1 and is_array( $arg_list[0] ) )
            $arg_list = $arg_list[0];

        $connection_name = false;

        $last_param = $arg_list[$numargs-1];
        if( is_string( $last_param ) and $this->connection_settings( $last_param ) !== false )
        {
            $connection_name = $arg_list[$numargs-1];
            $numargs--;
            unset( $arg_list[$numargs] );

            // Check if only connection name was passed as parameter
            if( empty( $numargs ) )
            {
                $this->set_my_error( self::ERR_QUERY,
                                     'Unknown stored query (with connection: <b>'.(is_string( $connection_name )?htmlspecialchars( $connection_name ):print_r( $connection_name, true )).'</b>)',
                                     'Query error.',
                                     $connection_name );
                return false;
            }
        }

        if( $connection_name === false )
            $connection_name = $this->default_connection();

        $qname = @array_shift( $arg_list );
        if( ($query_info = self::stored_query( $qname )) === false )
        {
            $this->set_my_error( self::ERR_QUERY,
                                 'Stored query not found: <b>'.(is_string( $qname )?htmlspecialchars( $qname ):print_r( $qname, true )).'</b>',
                                 'Query error.',
                                 $connection_name );
            return false;
        }

        if( is_array( $arg_list ) and ($al_count = count( $arg_list )) )
        {
            $qparams = $arg_list;
            if( $al_count <= $query_info['pcount'] )
                $qparams[] = '';
        } else
            $qparams = array( '' );

        $mysql_str = @vsprintf( $query_info['query'], $qparams );
        if( $mysql_str === '' )
        {
            $this->set_my_error( self::ERR_QUERY,
                                 'Bad format for query: '.@htmlspecialchars( $query_info['query'] ).(is_array( $qparams )?' - ['.count( $qparams ).' parameters passed]':''),
                                 'Bad format for query.',
                                 $connection_name );

            if( $this->close_after_query )
                $this->close( $connection_name );

            return false;
        }

        $qid = $this->query( $mysql_str, $connection_name );

        // just to be sure...
        if( $this->close_after_query )
            $this->close( $connection_name );

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

        if( !is_array( $arg_list ) or empty( $numargs ) )
            return false;

        if( count( $arg_list ) === 1 and is_array( $arg_list[0] ) )
            $arg_list = $arg_list[0];

        $connection_name = false;

        $last_param = $arg_list[$numargs-1];
        if( is_string( $last_param ) and $this->connection_settings( $last_param ) !== false )
        {
            $connection_name = $arg_list[$numargs-1];
            $numargs--;
            unset( $arg_list[$numargs] );

            // Check if only connection name was passed as parameter
            if( empty( $numargs ) )
            {
                $this->set_my_error( self::ERR_QUERY,
                                     'Unknown stored query (with connection: <b>'.(is_string( $connection_name )?htmlspecialchars( $connection_name ):print_r( $connection_name, true )).'</b>)',
                                     'Query error.',
                                     $connection_name );
                return false;
            }
        }

        if( $connection_name === false )
            $connection_name = $this->default_connection();

        $qname = @array_shift( $arg_list );
        if( ($query_info = self::stored_query( $qname )) === false )
        {
            $this->set_my_error( self::ERR_QUERY,
                                 'Stored query not found: <b>'.(is_string( $qname )?htmlspecialchars( $qname ):print_r( $qname, true )).'</b>',
                                 'Query error.',
                                 $connection_name );
            return false;
        }

        if( is_array( $arg_list ) and ($al_count = count( $arg_list )) )
        {
            $qparams = $arg_list;
            if( $al_count <= $query_info['pcount'] )
                $qparams[] = '';
        } else
            $qparams = array( '' );

        $mysql_str = @vsprintf( $query_info['query'], $qparams );
        if( $mysql_str === '' )
        {
            $this->set_my_error( self::ERR_QUERY,
                                 'Bad format for query: '.@htmlspecialchars( $query_info['query'] ).(is_array( $qparams )?' - ['.count( $qparams ).' parameters passed]':''),
                                 'Bad format for query.',
                                 $connection_name );
            if( $this->close_after_query )
                $this->close();
            return false;
        }

        return $mysql_str;
    }

    /**
     * @return array
     */
    public static function get_default_driver_settings()
    {
        return array(
            'sql_mode' => '',
        );
    }

    /**
     * Define a predefined/stored MySQL query as a string which will be used against vsprintf (placeholders: %s, %d, etc)
     *
     * @param string $qname Stored query name
     * @param null|string $qformat If null, method will return stored query with $qname as name
     *
     * @return bool|string
     */
    public static function stored_query( $qname, $qformat = null )
    {
        static $stored_queries;

        if( $qformat === null )
        {
            if( empty( $stored_queries ) or !is_array( $stored_queries ) or !isset( $stored_queries[$qname] ) )
                return false;

            return $stored_queries[$qname];
        }

        if( !isset( $stored_queries ) or !is_array( $stored_queries ) )
            $stored_queries = array();

        if( !isset( $stored_queries[$qname] ) or !is_array( $stored_queries[$qname] ) )
            $stored_queries[$qname] = array();

        $stored_queries[$qname]['query'] = $qformat.' %s';
        $stored_queries[$qname]['pcount'] = @substr_count( $qformat, '%s' );
        if( empty( $stored_queries[$qname]['pcount'] ) )
            $stored_queries[$qname]['pcount'] = 0;

        return true;
    }

    /**
     * Escape a single value or an array of values that should be sent to MySQL in a query
     *
     * @param array|int|string $fields
     * @param bool|string $connection_name
     *
     * @return array|bool|mixed
     */
    public function escape( $fields, $connection_name = false )
    {
        if( $connection_name === false )
            $connection_name = $this->default_connection();

        if( ($conn_settings = $this->connection_settings( $connection_name )) === false )
            return false;

        if( empty( $fields ) )
            return $fields;

        if( !is_array( $fields ) )
            $escape_fields = array( 0 => $fields );
        else
            $escape_fields = $fields;

        // if connect wasn't called separately call it now
        $connection_opened_now = false;
        if( !$this->is_connected( $connection_name ) )
        {
            if( $this->connect( $connection_name ) === false )
                return false;

            $connection_opened_now = true;
        }

        // make sure we have a connection
        if( !$this->is_connected( $connection_name ) )
            return false;

        foreach( $escape_fields as $key => $val )
        {
            if( ($escaped_str = @mysqli_real_escape_string( $this->connection_id[$connection_name], $escape_fields[$key] )) === false )
                 continue;

            $escape_fields[$key] = $escaped_str; // prepare_data( $escaped_str );
        }

        if( $connection_opened_now and $this->close_after_query )
            $this->close( $connection_name );

        return (!is_array( $fields )?$escape_fields[0]:$escape_fields);
    }

    /**
     * @param int $error_code
     * @param string $debug_err
     * @param string $short_err
     * @param bool|string $connection_name
     */
    protected function set_my_error( $error_code, $debug_err, $short_err, $connection_name = false )
    {
        if( $connection_name === false )
            $connection_name = $this->default_connection();

        $error_params = array();
        $error_params['prevent_throwing_errors'] = (!empty( $this->error_state )?true:false);

        $this->set_error( $error_code, $debug_err, '', $error_params );

        if( $this->display_errors )
        {
            if( $this->debug_errors )
            {
                $error = $this->get_error();

                if( $this->is_connected( $connection_name ) )
                    echo '<p><b>MySql error</b>: '.@mysqli_error( $this->connection_id[$connection_name] ).'</p>';
                echo '<p><pre>'.$error['error_msg'].'</pre></p>';
            } else
            {
                echo '<h2>Database error. ('.$short_err.')</h2>';
            }
        }

        if( $this->die_on_errors )
        {
            if( $this->display_errors )
                echo '<p>This script cannot continue and will be stoped.</p>';
            die();
        }
    }

    /**
     * @param bool $incr
     *
     * @return int
     */
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

    /**
     * @param \mysqli_result $qid
     *
     * @return bool|string[]|null
     */
    public function fetch_assoc( $qid )
    {
        if( empty( $qid )
         or !($qid instanceof \mysqli_result) )
            return false;

        return @mysqli_fetch_assoc( $qid );
    }

    /**
     * @param \mysqli_result $qid
     *
     * @return bool|int
     */
    public function num_rows( $qid )
    {
        if( empty( $qid )
         or !($qid instanceof \mysqli_result) )
            return false;

        return @mysqli_num_rows( $qid );
    }

    /**
     * @param bool|array $dump_params Array containing dump parameters
     *
     * @return array|bool Returns populated $dump_params array
     */
    public function dump_database( $dump_params = false )
    {
        if( !($dump_params = self::validate_array_recursive( $dump_params, self::default_dump_parameters() ))
         or !($dump_params = parent::dump_database( $dump_params ))
         or empty( $dump_params['binaries'] ) or !is_array( $dump_params['binaries'] )
         or empty( $dump_params['binaries']['mysqldump_bin'] )
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
        if( !($fil = @fopen( $credentials_file, 'wb' )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error creating dump credentials file.' ) );
            return false;
        }

        if( !@fwrite( $fil, '[mysqldump]'."\n".
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
