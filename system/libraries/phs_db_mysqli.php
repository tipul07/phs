<?php

namespace phs\libraries;

//! \author Andy (andy [at] sqnp [dot] net)
//! \version 2.01

//! If only one server/db connection is used or parameter sent to settings method is one array containing only one mysql connection settings, these settings will be kept in settings array with this index
define( 'PHS_MYSQL_DEF_CONNECTION_NAME', '@def_connection@' );

//! Create rules to extract information from strings
/**
 *  MySQL class parser for PHS suite...
 */
class PHS_db_mysqli extends PHS_Language implements PHS_db_interface
{
    //! Cannot connect to server.
    const ERR_CONNECT = 1;
    //! Cannot query server.
    const ERR_QUERY = 2;

    //! Database settings - array with connection settings (can hold one or more database connection settings). Array indexes: host, port (default 3306), database, user, password, default;
    //! Eg. $my_settings['default']['host'] = 'localhost'; $my_settings['default']['port'] = '3306'; ... etc
    // /see PHS_db_mysql::settings()
    var $my_settings;

    //! Default connection index from $my_settings array
    var $my_def_connection;

    //! Tells if class should close connection to mysql server after done with query
    var $close_after_query;

    //! Last connection index used from $my_settings array (the one which is opened now)
    var $last_connection_name;

    //! In case connection is not closed after each query this should keep connection id
    var $connection_id;

    //! Hold last query id
    var $query_id;

    //! Behaviour: Display errors
    var $display_errors;

    //! Behaviour: Die on error
    var $die_on_errors;

    //! Behaviour: Display more info on errors
    var $debug_errors;

    //! Behaviour: Use mysql_pconnect or mysql_connect when connecting to mysql server
    var $use_pconnect;

    //! Query result details...
    var $last_inserted_id, $affected_rows;

    var $error_state = false;

    function __construct( $mysql_settings = false )
    {
        parent::__construct();

        $this->my_settings = false;
        $this->my_def_connection = false;
        $this->last_connection_name = false;
        $this->settings( $mysql_settings );

        // Default behaviours
        $this->display_errors = true;
        $this->die_on_errors = true;
        $this->debug_errors = true;
        $this->use_pconnect = true;
        $this->close_after_query = true;
        $this->error_state = false;

        $this->query_id = false;
        $this->connection_id = null;

        $this->last_inserted_id = false;
        $this->affected_rows = 0;
    }

    function query_id()
    {
        return $this->query_id;
    }

    public function last_inserted_id()
    {
        return $this->last_inserted_id;
    }

    function affected_rows()
    {
        return $this->affected_rows;
    }

    public function display_errors( $var = null )
    {
        if( is_null( $var ) )
            return $this->display_errors;

        $this->display_errors = $var;
        return $this->display_errors;
    }

    function die_on_errors( $var = null )
    {
        if( is_null( $var ) )
            return $this->die_on_errors;

        $this->die_on_errors = $var;
        return $this->die_on_errors;
    }

    function debug_errors( $var = null )
    {
        if( is_null( $var ) )
            return $this->debug_errors;

        $this->debug_errors = $var;
        return $this->debug_errors;
    }

    function close_after_query( $var = null )
    {
        if( is_null( $var ) )
            return $this->close_after_query;

        $this->close_after_query = $var;
        return $this->close_after_query;
    }

    function use_pconnect( $var = null )
    {
        if( is_null( $var ) )
            return $this->use_pconnect;

        $this->use_pconnect = $var;
        return $this->use_pconnect;
    }

    function default_connection( $connection_name = null )
    {
        if( is_null( $connection_name ) )
            return $this->my_def_connection;

        if( empty( $this->my_settings ) or empty( $this->my_settings[$connection_name] ) )
            return false;

        $this->my_def_connection = $connection_name;

        return true;
    }

    public function connection_settings( $connection_name, $mysql_settings = false )
    {
        if( $connection_name === false )
            $connection_name = $this->default_connection();

        if( $mysql_settings === false )
        {
            if( $this->my_settings !== false and isset( $this->my_settings[$connection_name] ) and is_array( $this->my_settings[$connection_name] ) )
                return $this->my_settings[$connection_name];
            else
                return false;
        }

        if( !isset( $mysql_settings['database'] ) or !isset( $mysql_settings['user'] ) or !isset( $mysql_settings['password'] ) )
        {
            $this->set_error( self::ERR_PARAMETERS, 'Database, user or password not pressent in settings array.' );

            return false;
        }

        if( $this->my_settings === false )
            $this->my_settings = array();

        if( !isset( $this->my_settings[$connection_name] ) or !is_array( $this->my_settings[$connection_name] ) )
            $this->my_settings[$connection_name] = array();

        if( !empty( $mysql_settings['host'] ) )
            $this->my_settings[$connection_name]['host'] = $mysql_settings['host'];
        if( empty( $this->my_settings[$connection_name]['host'] ) )
            $this->my_settings[$connection_name]['host'] = 'localhost';

        if( !empty( $mysql_settings['port'] ) )
            $this->my_settings[$connection_name]['port'] = intval( $mysql_settings['port'] );
        if( empty( $this->my_settings[$connection_name]['port'] ) )
            $this->my_settings[$connection_name]['port'] = 3306;

        if( !empty( $mysql_settings['charset'] ) )
            $this->my_settings[$connection_name]['charset'] = $mysql_settings['charset'];
        if( empty( $this->my_settings[$connection_name]['charset'] ) )
            $this->my_settings[$connection_name]['charset'] = '';

        if( !empty( $mysql_settings['timezone'] ) )
            $this->my_settings[$connection_name]['timezone'] = $mysql_settings['timezone'];
        if( empty( $this->my_settings[$connection_name]['timezone'] ) )
            $this->my_settings[$connection_name]['timezone'] = '';

        if( !empty( $mysql_settings['prefix'] ) )
            $this->my_settings[$connection_name]['prefix'] = $mysql_settings['prefix'];
        if( empty( $this->my_settings[$connection_name]['prefix'] ) )
            $this->my_settings[$connection_name]['prefix'] = '';

        $this->my_settings[$connection_name]['database'] = $mysql_settings['database'];
        $this->my_settings[$connection_name]['user'] = $mysql_settings['user'];
        $this->my_settings[$connection_name]['password'] = $mysql_settings['password'];

        if( !empty( $mysql_settings['default'] ) )
            $this->my_def_connection = $connection_name;

        // If no default is passed, get first server as default...
        if( empty( $this->my_def_connection ) )
            $this->my_def_connection = $connection_name;

        return true;
    }

    function settings( $mysql_settings = false )
    {
        if( $mysql_settings === false )
            return $this->my_settings;

        if( !is_array( $mysql_settings ) )
        {
            $this->set_error( self::ERR_PARAMETERS, 'Bad settings array.' );
            return false;
        }

        if( isset( $mysql_settings['database'] ) and isset( $mysql_settings['user'] ) and isset( $mysql_settings['password'] ) )
        {
            // Only one connection settings was passed...
            return $this->connection_settings( PHS_MYSQL_DEF_CONNECTION_NAME, $mysql_settings );
        }

        $got_an_error = false;
        foreach( $mysql_settings as $connection_name => $connection_settings )
        {
            if( $this->connection_settings( $connection_name, $connection_settings ) === false )
                $got_an_error = true;
        }

        if( $got_an_error )
            return false;

        return true;
    }

    function is_connected( $connection_name = false )
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

    function close( $connection_name = false )
    {
        if( $connection_name === false )
            $connection_name = $this->default_connection();

        if( !$this->is_connected( $connection_name ) )
            return false;

        @mysqli_close( $this->connection_id[$connection_name] );
        $this->connection_id[$connection_name] = null;

        return true;
    }

    function connect( $connection_name = false )
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
        if( $this->use_pconnect )
            $host = 'p:'.$conn_settings['host'];

        $this->connection_id[$connection_name] = @mysqli_connect( $host, $conn_settings['user'], $conn_settings['password'], $conn_settings['database'], $conn_settings['port'] );

        if( empty( $this->connection_id[$connection_name] )
         or !is_object( $this->connection_id[$connection_name] ) or !($this->connection_id[$connection_name] instanceof \mysqli) )
        {
            $this->set_my_error( self::ERR_CONNECT,
                                 'Cannot connect to '.$host.', user '.$conn_settings['user'].($conn_settings['password']!=''?' (with password)':'').(!empty( $this->use_pconnect )?' (using permanent)':'').'.',
                                 'cannot connect',
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

        return true;
    }

    // Returns an INSERT query string for table $table_name for $insert_arr data
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
            if( is_array( $val ) )
            {
                if( !isset( $val['value'] ) )
                    continue;

                if( empty( $val['raw_field'] ) )
                    $val['raw_field'] = false;

                $field_value = $val['value'];

                if( empty( $val['raw_field'] ) )
                {
                    if( !empty( $params['secure'] ) )
                        $field_value = $this->escape( $field_value, $connection_name );

                    $field_value = '\''.$field_value.'\'';
                }
            } else
                $field_value = '\''.(!empty( $params['secure'] )?$this->escape( $val, $connection_name ):$val).'\'';

            $return .= '`'.$key.'`='.$field_value.', ';
        }

        if( $return == '' )
            return '';

        return 'INSERT INTO `'.$table_name.'` SET '.substr( $return, 0, -2 );
    }

    // Returns an EDIT query string for table $table_name for $edit_arr data conditions added outside this method
    // in future where conditions should be added here to support more drivers...
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
            if( is_array( $val ) )
            {
                if( !isset( $val['value'] ) )
                    continue;

                if( empty( $val['raw_field'] ) )
                    $val['raw_field'] = false;

                $field_value = $val['value'];

                if( empty( $val['raw_field'] ) )
                {
                    if( !empty( $params['secure'] ) )
                        $field_value = $this->escape( $field_value, $connection_name );

                    $field_value = '\''.$field_value.'\'';
                }
            } else
                $field_value = '\''.(!empty( $params['secure'] )?$this->escape( $val, $connection_name ):$val).'\'';

            $return .= '`'.$key.'`='.$field_value.', ';
        }

        if( $return == '' )
            return '';

        return 'UPDATE `'.$table_name.'` SET '.substr( $return, 0, -2 );
    }

    function formated_query( $format, $fields = false, $connection_name = false )
    {
        if( $connection_name === false )
            $connection_name = $this->default_connection();

        if( ($conn_settings = $this->connection_settings( $connection_name )) === false )
            return false;

        // We connect now to database bcuz we don't need escape and query methods to open 2 connections...
        // if connect wasn't called separately call it now
        if( !$this->is_connected( $connection_name ) and $this->connect( $connection_name ) === false )
            return false;

        // make sure we have a connection
        if( !$this->is_connected( $connection_name ) )
            return false;

        if( $fields !== false and !empty( $fields ) )
            $fields = $this->escape( $fields, $connection_name );

        $mysql_str = @vsprintf( $format, $fields );
        if( $mysql_str == '' )
        {
            $this->set_my_error( self::ERR_QUERY,
                                 'Bad format for query: '.(is_string( $format )?htmlspecialchars( $format ):print_r( $format, true )).(is_array( $fields )?' - ['.count( $fields ).' parameters passed]':''),
                                 'malformed query',
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
     * Do the query and return query ID
     *
     * @param $query
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
            $this->set_my_error( self::ERR_CONNECT,
                'Cannot acces <b>'.$conn_settings['database'].'</b> database with user <b>'.$conn_settings['user'].'</b>.',
                'cannot select db.',
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
                                 'query error',
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

    function s_query()
    {
        $numargs = @func_num_args();
        $arg_list = @func_get_args();

        if( !is_array( $arg_list ) or empty( $numargs ) )
            return false;

        if( count( $arg_list ) == 1 and is_array( $arg_list[0] ) )
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
                                     'query error',
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
                                 'query error',
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
        if( $mysql_str == '' )
        {
            $this->set_my_error( self::ERR_QUERY,
                                 'Bad format for query: '.@htmlspecialchars( $query_info['query'] ).(is_array( $qparams )?' - ['.count( $qparams ).' parameters passed]':''),
                                 'malformed query',
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

    function get_squery()
    {
        $numargs = @func_num_args();
        $arg_list = @func_get_args();

        if( !is_array( $arg_list ) or empty( $numargs ) )
            return false;

        if( count( $arg_list ) == 1 and is_array( $arg_list[0] ) )
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
                                     'query error',
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
                                 'query error',
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
        if( $mysql_str == '' )
        {
            $this->set_my_error( self::ERR_QUERY,
                                 'Bad format for query: '.@htmlspecialchars( $query_info['query'] ).(is_array( $qparams )?' - ['.count( $qparams ).' parameters passed]':''),
                                 'malformed query',
                                 $connection_name );
            if( $this->close_after_query )
                $this->close();
            return false;
        }

        return $mysql_str;
    }

    static function stored_query( $qname, $qformat = null )
    {
        static $stored_queries;

        if( is_null( $qformat ) )
        {
            if( empty( $stored_queries ) or !is_array( $stored_queries ) or !isset( $stored_queries[$qname] ) )
                return false;
            else
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

            $escape_fields[$key] = $escaped_str;
        }

        if( $connection_opened_now and $this->close_after_query )
            $this->close( $connection_name );

        return (!is_array( $fields )?$escape_fields[0]:$escape_fields);
    }

    // Suppress any errors database driver might throw
    public function suppress_errors()
    {
        if( !empty( $this->error_state ) )
            return;

        $this->error_state = array(
            'display_errors' => $this->display_errors,
            'debug_errors' => $this->debug_errors,
            'die_on_errors' => $this->die_on_errors,
        );

        $this->display_errors = false;
        $this->debug_errors = false;
        $this->die_on_errors = false;
    }

    // Restore error handling functions as before suppress_errors() method was called
    public function restore_errors_state()
    {
        if( empty( $this->error_state ) )
            return;

        $this->display_errors = $this->error_state['display_errors'];
        $this->debug_errors = $this->error_state['debug_errors'];
        $this->die_on_errors = $this->error_state['die_on_errors'];

        $this->error_state = false;
    }

    function set_my_error( $error_code, $debug_err, $short_err, $connection_name = false )
    {
        if( $connection_name === false )
            $connection_name = $this->default_connection();

        $this->set_error( $error_code, $debug_err );

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
        and gettype( $qid ) != 'resource' )
            return false;

        return @mysqli_fetch_assoc( $qid );
    }

    public function num_rows( $qid )
    {
        if( empty( $qid )
        and gettype( $qid ) != 'resource' )
            return false;

        return @mysqli_num_rows( $qid );
    }


}
