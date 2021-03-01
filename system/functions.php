<?php

if( !defined( 'DATETIME_T_EMPTY' ) )
    define( 'DATETIME_T_EMPTY', '0000-00-00T00:00:00' );
if( !defined( 'DATETIME_T_FORMAT' ) )
    define( 'DATETIME_T_FORMAT', 'Y-m-d\TH:i:s' );

use \phs\PHS_Db;
use \phs\libraries\PHS_Model;

function phs_version()
{
    return '1.1.4.4';
}

function phs_init_before_bootstrap()
{
    static $did_definitions = null;

    if( !defined( 'PHS_PATH' )
     || !defined( 'PHS_DEFAULT_DOMAIN' )
     || !defined( 'PHS_DEFAULT_PORT' )
     || !defined( 'PHS_DEFAULT_SSL_DOMAIN' )
     || !defined( 'PHS_DEFAULT_SSL_PORT' )
     || !defined( 'PHS_DEFAULT_DOMAIN_PATH' ) )
        return false;

    if( $did_definitions !== null )
        return true;

    $did_definitions = true;

    if( !defined( 'PHS_DEFAULT_FULL_PATH_WWW' ) )
        define( 'PHS_DEFAULT_FULL_PATH_WWW', PHS_DEFAULT_DOMAIN.(PHS_DEFAULT_PORT!==''?':':'').PHS_DEFAULT_PORT.'/'.PHS_DEFAULT_DOMAIN_PATH );
    if( !defined( 'PHS_DEFAULT_FULL_SSL_PATH_WWW' ) )
        define( 'PHS_DEFAULT_FULL_SSL_PATH_WWW', PHS_DEFAULT_SSL_DOMAIN.(PHS_DEFAULT_SSL_PORT!==''?':':'').PHS_DEFAULT_SSL_PORT.'/'.PHS_DEFAULT_DOMAIN_PATH );

    if( !defined( 'PHS_DEFAULT_HTTP' ) )
        define( 'PHS_DEFAULT_HTTP', 'http://'.PHS_DEFAULT_FULL_PATH_WWW );
    if( !defined( 'PHS_DEFAULT_HTTPS' ) )
        define( 'PHS_DEFAULT_HTTPS', 'https://'.PHS_DEFAULT_FULL_SSL_PATH_WWW );

    // Root folders
    if( !defined( 'PHS_SETUP_DIR' ) )
        define( 'PHS_SETUP_DIR', PHS_PATH.'_setup/' );
    if( !defined( 'PHS_CONFIG_DIR' ) )
        define( 'PHS_CONFIG_DIR', PHS_PATH.'config/' );
    if( !defined( 'PHS_SYSTEM_DIR' ) )
        define( 'PHS_SYSTEM_DIR', PHS_PATH.'system/' );
    if( !defined( 'PHS_PLUGINS_DIR' ) )
        define( 'PHS_PLUGINS_DIR', PHS_PATH.'plugins/' );

    // If logging dir is not setup in main.php or config/*, default location is in system/logs/
    if( !defined( 'PHS_DEFAULT_LOGS_DIR' ) )
        define( 'PHS_DEFAULT_LOGS_DIR', PHS_SYSTEM_DIR.'logs/' );

    // If uploads dir is not setup in main.php or config/*, default location is in _uploads/
    if( !defined( 'PHS_DEFAULT_UPLOADS_DIR' ) )
        define( 'PHS_DEFAULT_UPLOADS_DIR', PHS_PATH.'_uploads/' );

    // If assets dir is not setup in main.php or config/*, default location is in assets/
    if( !defined( 'PHS_DEFAULT_ASSETS_DIR' ) )
        define( 'PHS_DEFAULT_ASSETS_DIR', PHS_PATH.'assets/' );

    // Second level folders
    if( !defined( 'PHS_CORE_DIR' ) )
        define( 'PHS_CORE_DIR', PHS_SYSTEM_DIR.'core/' );
    if( !defined( 'PHS_LIBRARIES_DIR' ) )
        define( 'PHS_LIBRARIES_DIR', PHS_SYSTEM_DIR.'libraries/' );

    if( !defined( 'PHS_CORE_MODEL_DIR' ) )
        define( 'PHS_CORE_MODEL_DIR', PHS_CORE_DIR.'models/' );
    if( !defined( 'PHS_CORE_CONTROLLER_DIR' ) )
        define( 'PHS_CORE_CONTROLLER_DIR', PHS_CORE_DIR.'controllers/' );
    if( !defined( 'PHS_CORE_VIEW_DIR' ) )
        define( 'PHS_CORE_VIEW_DIR', PHS_CORE_DIR.'views/' );
    if( !defined( 'PHS_CORE_ACTION_DIR' ) )
        define( 'PHS_CORE_ACTION_DIR', PHS_CORE_DIR.'actions/' );
    if( !defined( 'PHS_CORE_CONTRACT_DIR' ) )
        define( 'PHS_CORE_CONTRACT_DIR', PHS_CORE_DIR.'contracts/' );
    if( !defined( 'PHS_CORE_PLUGIN_DIR' ) )
        define( 'PHS_CORE_PLUGIN_DIR', PHS_CORE_DIR.'plugins/' );
    if( !defined( 'PHS_CORE_SCOPE_DIR' ) )
        define( 'PHS_CORE_SCOPE_DIR', PHS_CORE_DIR.'scopes/' );
    if( !defined( 'PHS_CORE_LIBRARIES_DIR' ) )
        define( 'PHS_CORE_LIBRARIES_DIR', PHS_CORE_DIR.'libraries/' );

    // These paths will need a www pair, but after bootstrap
    if( !defined( 'PHS_THEMES_DIR' ) )
        define( 'PHS_THEMES_DIR', PHS_PATH.'themes/' );
    if( !defined( 'PHS_LANGUAGES_DIR' ) )
        define( 'PHS_LANGUAGES_DIR', PHS_PATH.'languages/' );

    // name of directory where email templates are stored (either theme relative or plugin relative)
    // eg. (themes/default/emails or plugins/accounts/templates/emails)
    if( !defined( 'PHS_EMAILS_DIRS' ) )
        define( 'PHS_EMAILS_DIRS', 'emails' );

    return true;
}

/**
 * @return string
 */
function generate_guid()
{
    return sprintf( '%04X%04X-%04X-%04X-%04X-%04X%04X%04X',
                    mt_rand(0, 65535), mt_rand(0, 65535),
                    mt_rand(0, 65535),
                    mt_rand(16384, 20479),
                    mt_rand(32768, 49151),
                    mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535)
    );
}

/**
 * @param string $ip
 *
 * @return bool|string
 */
function validate_ip( $ip )
{
    if( @function_exists( 'filter_var' ) && defined( 'FILTER_VALIDATE_IP' ) )
    {
        $ret_val = filter_var( $ip, FILTER_VALIDATE_IP );
        return ($ret_val?trim( $ret_val ):false);
    }

    if( !($ip_numbers = explode( '.', $ip ))
     || !is_array( $ip_numbers ) || count( $ip_numbers ) !== 4 )
        return false;

    $parsed_ip = '';
    foreach( $ip_numbers as $ip_part )
    {
        $ip_part = (int)$ip_part;
        if( $ip_part < 0 || $ip_part > 255 )
            return false;

        $parsed_ip = ($parsed_ip!==''?'.':'').$ip_part;
    }

    return $parsed_ip;
}

/**
 * @return string
 */
function request_ip()
{
    $guessed_ip = '';
    if( !empty( $_SERVER['HTTP_CLIENT_IP'] ) )
        $guessed_ip = validate_ip( $_SERVER['HTTP_CLIENT_IP'] );

    if( empty( $guessed_ip )
     && !empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) )
        $guessed_ip = validate_ip( $_SERVER['HTTP_X_FORWARDED_FOR'] );

    if( empty( $guessed_ip ) )
        $guessed_ip = (!empty( $_SERVER['REMOTE_ADDR'] )?trim( $_SERVER['REMOTE_ADDR'] ):'');

    return $guessed_ip;
}

//
// Database related functions
//

function db_supress_errors( $connection = false )
{
    if( !($db_instance = PHS_Db::db( $connection )) )
    {
        if( PHS_Db::st_debugging_mode() )
            PHS_Db::st_throw_error();

        elseif( PHS_DB_SILENT_ERRORS )
            return false;

        if( PHS_DB_DIE_ON_ERROR )
            exit;

        return false;
    }

    $db_instance->suppress_errors();

    return true;
}

function db_restore_errors_state( $connection = false )
{
    if( !($db_instance = PHS_Db::db( $connection )) )
    {
        if( PHS_Db::st_debugging_mode() )
            PHS_Db::st_throw_error();

        elseif( PHS_DB_SILENT_ERRORS )
            return false;

        if( PHS_DB_DIE_ON_ERROR )
            exit;

        return false;
    }

    $db_instance->restore_errors_state();

    return true;
}

function db_query_insert( $query, $connection = false )
{
    if( !($db_instance = PHS_Db::db( $connection )) )
    {
        if( PHS_Db::st_debugging_mode() )
            PHS_Db::st_throw_error();

        elseif( PHS_DB_SILENT_ERRORS )
            return false;

        if( PHS_DB_DIE_ON_ERROR )
            exit;

        return false;
    }

    if( !($qid = $db_instance->query( $query, $connection )) )
    {
        if( $db_instance->display_errors() )
        {
            $error = $db_instance->get_error();
            echo $error['display_error'];
        }

        return 0;
    }

    $last_id = $db_instance->last_inserted_id();

    return $last_id;
}

function db_query( $query, $connection = false )
{
    if( !($db_instance = PHS_Db::db( $connection )) )
    {
        if( PHS_Db::st_debugging_mode() )
            PHS_Db::st_throw_error();

        elseif( PHS_DB_SILENT_ERRORS )
            return false;

        if( PHS_DB_DIE_ON_ERROR )
            exit;

        return false;
    }

    if( !($qid = $db_instance->query( $query, $connection )) )
    {
        if( $db_instance->display_errors() )
        {
            $error = $db_instance->get_error();
            echo $error['display_error'];
        }

        if( $db_instance->has_error()
         && ($db_instance->get_error_code() === $db_instance::ERR_CONNECT
                || $db_instance->get_error_code() === $db_instance::ERR_DATABASE
            ) )
        {
            return false;
        }

        return 0;
    }

    return $qid;
}

function db_test_connection( $connection = false )
{
    if( !($db_instance = PHS_Db::db( $connection )) )
    {
        if( PHS_Db::st_debugging_mode() )
            PHS_Db::st_throw_error();

        elseif( PHS_DB_SILENT_ERRORS )
            return false;

        if( PHS_DB_DIE_ON_ERROR )
            exit;

        return false;
    }

    if( !$db_instance->test_connection( $connection ) )
    {
        if( $db_instance->display_errors() )
        {
            $error = $db_instance->get_error();
            echo $error['display_error'];
        }

        return false;
    }

    return true;
}

function db_last_error( $connection = false )
{
    if( !($db_instance = PHS_Db::db( $connection )) )
        return false;

    return $db_instance->get_error();
}

function db_fetch_assoc( $qid, $connection = false )
{
    if( !($db_instance = PHS_Db::db( $connection )) )
    {
        if( PHS_Db::st_debugging_mode() )
            PHS_Db::st_throw_error();

        elseif( PHS_DB_SILENT_ERRORS )
            return false;

        if( PHS_DB_DIE_ON_ERROR )
            exit;

        return false;
    }

    return $db_instance->fetch_assoc( $qid );
}

function db_num_rows( $qid, $connection = false )
{
    if( !($db_instance = PHS_Db::db( $connection )) )
    {
        if( PHS_Db::st_debugging_mode() )
            PHS_Db::st_throw_error();

        elseif( PHS_DB_SILENT_ERRORS )
            return false;

        if( PHS_DB_DIE_ON_ERROR )
            exit;

        return false;
    }

    return $db_instance->num_rows( $qid );
}

function db_query_count( $connection = false )
{
    if( !($db_instance = PHS_Db::db( $connection )) )
        return 0;

    return $db_instance->queries_number();
}

function db_affected_rows( $connection = false )
{
    if( !($db_instance = PHS_Db::db( $connection )) )
        return 0;

    return $db_instance->affected_rows();
}

function db_quick_insert( $table_name, $insert_arr, $connection = false, $params = false )
{
    if( !($db_instance = PHS_Db::db( $connection )) )
        return '';

    return $db_instance->quick_insert( $table_name, $insert_arr, $connection, $params );
}

function db_quick_edit( $table_name, $edit_arr, $connection = false, $params = false )
{
    if( !($db_instance = PHS_Db::db( $connection )) )
        return '';

    return $db_instance->quick_edit( $table_name, $edit_arr, $connection, $params );
}

function db_escape( $fields, $connection = false )
{
    if( !($db_instance = PHS_Db::db( $connection )) )
        return false;

    return $db_instance->escape( $fields, $connection );
}

function db_last_id( $connection = false )
{
    if( !($db_instance = PHS_Db::db( $connection )) )
        return -1;

    return $db_instance->last_inserted_id();
}

function db_settings( $connection = false )
{
    if( !($db_instance = PHS_Db::db( $connection )) )
        return -1;

    return $db_instance->connection_settings( $connection );
}

function db_connection_identifier( $connection )
{
    if( empty( $connection )
     || !($connection_identifier = PHS_Db::get_connection_identifier( $connection ))
     || !is_array( $connection_identifier ) )
        return false;

    return $connection_identifier;
}

function db_prefix( $connection = false )
{
    if( !($db_settings = db_settings( $connection ))
     || !is_array( $db_settings )
     || empty( $db_settings['prefix'] ) )
        return '';

    return $db_settings['prefix'];
}

function db_database( $connection = false )
{
    if( !($db_settings = db_settings( $connection ))
     || !is_array( $db_settings )
     || empty( $db_settings['database'] ) )
        return '';

    return $db_settings['database'];
}

function db_dump( $dump_params, $connection = false )
{
    PHS_Db::st_reset_error();

    if( !($db_instance = PHS_Db::db( $connection )) )
    {
        PHS_Db::st_set_error( PHS_Db::ERR_DATABASE, PHS_Db::_t( 'Error obtaining database driver instance.' ) );
        return false;
    }

    if( !($dump_result = $db_instance->dump_database( $dump_params )) )
    {
        if( $db_instance->has_error() )
            PHS_Db::st_copy_error( $db_instance );
        else
            PHS_Db::st_set_error( PHS_Db::ERR_DATABASE, PHS_Db::_t( 'Error obtaining dump commands from driver instance.' ) );

        return false;
    }

    return $dump_result;
}
//
// END Database related functions
//

function form_str( $str )
{
    return str_replace( '"', '&quot;', $str );
}

function textarea_str( $str )
{
    return str_replace( [ '<', '>' ], [ '&lt;', '&gt;' ], $str );
}

function make_sure_is_filename( $str )
{
    if( !is_string( $str ) )
        return false;

    return str_replace(
        [ '..', '/', '\\', '~', '<', '>', '|', '`', '*', '&', ],
        [ '.', '', '', '', '', '', '', '', '', '', ],
        $str );
}

/**
 * @param string $str
 * @param bool|array $params
 *
 * @return int
 */
function seconds_passed( $str, $params = false )
{
    return time() - (int)parse_db_date( $str, $params );
}

function validate_db_date_array( $date_arr )
{
    if( !is_array( $date_arr ) )
        return false;

    for( $i = 0; $i < 6; $i++ )
    {
        if( !isset( $date_arr[$i] ) )
            return false;

        $date_arr[$i] = (int)$date_arr[$i];
    }

    if(
        $date_arr[1] < 1 || $date_arr[1] > 12
     || $date_arr[2] < 1 || $date_arr[2] > 31
     || $date_arr[3] < 0 || $date_arr[3] > 23
     || $date_arr[4] < 0 || $date_arr[4] > 59
     || $date_arr[5] < 0 || $date_arr[5] > 59
        )
        return false;

    return true;
}

/**
 * @param string $date
 *
 * @return bool
 */
function empty_t_date( $date )
{
    return (empty( $date ) || (string)$date === DATETIME_T_EMPTY || (string)$date === PHS_Model::DATE_EMPTY);
}

/**
 * @param string $date
 * @param bool|array $params
 *
 * @return array|bool
 */
function is_t_date( $date, $params = false )
{
    if( is_string( $date ) )
        $date = trim( $date );

    if( empty( $date )
     || !is_string( $date )
     || strpos( $date, 'T' ) === false )
        return false;

    if( empty_t_date( $date ) )
        return [ 0, 0, 0, 0, 0, 0 ];

    if( empty( $params ) || !is_array( $params ) )
        $params = [];

    if( !isset( $params['validate_intervals'] ) )
        $params['validate_intervals'] = true;
    else
        $params['validate_intervals'] = (!empty( $params['validate_intervals'] )?true:false);

    if( strpos( $date, 'T' ) !== false )
    {
        $d = explode( 'T', $date );
        $date_ = explode( '-', $d[0] );
        $time_ = explode( ':', $d[1], 3 );
    } else
    {
        $date_ = explode( '-', $date );
        $time_ = [ 0, 0, 0 ];
    }

    for( $i = 0; $i < 3; $i++ )
    {
        if( !isset( $date_[$i] )
         || !isset( $time_[$i] ) )
            return false;

        if( $i === 2
         && !empty( $time_[$i] ) )
        {
            // try removing any timezone at the end of format...
            $time_[$i] = substr( $time_[$i], 0, 2 );
        }

        $date_[$i] = (int)$date_[$i];
        $time_[$i] = (int)$time_[$i];
    }

    $result_arr = array_merge( $date_, $time_ );
    if( !empty( $params['validate_intervals'] )
     && !validate_db_date_array( $result_arr ) )
        return false;

    return $result_arr;
}

/**
 * @param string|array $date
 * @param bool|array $params
 *
 * @return false|int
 */
function parse_t_date( $date, $params = false )
{
    if( empty( $params ) || !is_array( $params ) )
        $params = [];

    if( !isset( $params['offset_seconds'] ) && !isset( $params['offset_hours'] ) )
        $params['offset_seconds'] = 0;

    else
    {
        // offset in seconds...
        if( isset( $params['offset_seconds'] ) )
            $params['offset_seconds'] = (int)$params['offset_seconds'];

        else
        {
            // offset in hours...
            if( empty( $params['offset_hours'] ) )
                $params['offset_seconds'] = 0;

            else
                $params['offset_seconds'] = (int)$params['offset_hours'] * 3600;
        }

        $params['offset_seconds'] = @date( 'Z' ) - $params['offset_seconds'];
    }

    if( !isset( $params['validate_intervals'] ) )
        $params['validate_intervals'] = true;
    else
        $params['validate_intervals'] = (!empty( $params['validate_intervals'] ));

    if( is_array( $date ) )
    {
        for( $i = 0; $i < 6; $i++ )
        {
            if( !isset( $date[$i] ) )
                return 0;

            $date[$i] = (int)$date[$i];
        }

        $date_arr = $date;

        if( !empty( $params['validate_intervals'] )
         && !validate_db_date_array( $date_arr ) )
            return 0;
    } elseif( is_string( $date ) )
    {
        if( !($date_arr = is_t_date( $date, $params )) )
            return 0;
    } else
        return 0;

    return @mktime( $date_arr[3], $date_arr[4], $date_arr[5], $date_arr[1], $date_arr[2], $date_arr[0] ) + $params['offset_seconds'];
}

/**
 * @param string $date
 * @param bool|array $params
 *
 * @return array|bool
 */
function is_db_date( $date, $params = false )
{
    if( is_string( $date ) )
        $date = trim( $date );

    if( empty( $date )
     || !is_string( $date )
     || strpos( $date, '-' ) === false )
        return false;

    if( empty_db_date( $date ) )
        return [ 0, 0, 0, 0, 0, 0 ];

    if( empty( $params ) || !is_array( $params ) )
        $params = [];

    if( !isset( $params['validate_intervals'] ) )
        $params['validate_intervals'] = true;
    else
        $params['validate_intervals'] = (!empty( $params['validate_intervals'] )?true:false);

    if( strpos( $date, ' ' ) !== false )
    {
        $d = explode( ' ', $date );
        $date_ = explode( '-', $d[0] );
        $time_ = explode( ':', $d[1] );
    } else
    {
        $date_ = explode( '-', $date );
        $time_ = [ 0, 0, 0 ];
    }

    for( $i = 0; $i < 3; $i++ )
    {
        if( !isset( $date_[$i] )
         || !isset( $time_[$i] ) )
            return false;

        $date_[$i] = (int)$date_[$i];
        $time_[$i] = (int)$time_[$i];
    }

    $result_arr = array_merge( $date_, $time_ );
    if( !empty( $params['validate_intervals'] )
     && !validate_db_date_array( $result_arr ) )
        return false;

    return $result_arr;
}

/**
 * @param string|array $date
 * @param bool|array $params
 *
 * @return int
 */
function parse_db_date( $date, $params = false )
{
    if( empty( $params ) || !is_array( $params ) )
        $params = [];

    if( !isset( $params['validate_intervals'] ) )
        $params['validate_intervals'] = true;
    else
        $params['validate_intervals'] = (!empty( $params['validate_intervals'] )?true:false);

    if( is_array( $date ) )
    {
        for( $i = 0; $i < 6; $i++ )
        {
            if( !isset( $date[$i] ) )
                return 0;

            $date[$i] = (int)$date[$i];
        }

        $date_arr = $date;

        if( !empty( $params['validate_intervals'] )
         && !validate_db_date_array( $date_arr ) )
            return 0;
    } elseif( is_string( $date ) )
    {
        if( !($date_arr = is_db_date( $date, $params )) )
            return 0;
    } else
        return 0;

    if( false === ($ret_val = @mktime( $date_arr[3], $date_arr[4], $date_arr[5], $date_arr[1], $date_arr[2], $date_arr[0] )) )
        $ret_val = 0;

    return $ret_val;
}

/**
 * @param string $date
 *
 * @return bool
 */
function empty_db_date( $date )
{
    return (empty( $date ) || (string)$date === PHS_Model::DATETIME_EMPTY || (string)$date === PHS_Model::DATE_EMPTY);
}

/**
 * @param string $date
 * @param bool|string $format
 *
 * @return false|string|null
 */
function validate_db_date( $date, $format = false )
{
    if( empty_db_date( $date ) )
        return null;

    if( $format === false )
        $format = PHS_Model::DATETIME_DB;

    return @date( $format, parse_db_date( $date ) );
}

/**
 * @param string $str
 *
 * @return string
 */
function prepare_data( $str )
{
    return str_replace( '\'', '\\\'', str_replace( '\\\'', '\'', $str ) );
}

/**
 * @param string $url
 *
 * @return string
 */
function safe_url( $url )
{
    return str_replace( [ '?', '&', '#' ], [ '%3F', '%26', '%23' ], $url );
}

/**
 * @param string $url
 *
 * @return string
 */
function from_safe_url( $url )
{
    return str_replace( [ '%3F', '%26', '%23' ], [ '?', '&', '#' ], $url );
}

/**
 * This function behaves as http_build_query() except that it doesn't rawurlencode the values (only values if required)
 *
 * @param array $arr
 * @param bool|array $params
 *
 * @return string
 */
function array_to_query_string( $arr, $params = false )
{
    if( empty( $params ) || !is_array( $params ) )
        $params = [];

    if( !isset( $params['arg_separator'] ) )
        $params['arg_separator'] = '&';
    if( !isset( $params['raw_encode_values'] ) )
        $params['raw_encode_values'] = true;
    if( empty( $params['array_name'] ) )
        $params['array_name'] = '';

    if( empty( $arr ) || !is_array( $arr ) )
        return '';

    $return_str = '';
    foreach( $arr as $key => $val )
    {
        $return_str .= ($return_str!==''?'&':'');

        if( is_array( $val ) )
        {
            $call_params = $params;
            $call_params['array_name'] = (!empty( $params['array_name'] )?$params['array_name'].'['.$key.']':$key);

            $return_str .= array_to_query_string( $val, $call_params );
        } else
        {
            if( !empty( $params['raw_encode_values'] ) )
                $val = urlencode( $val );

            if( empty( $params['array_name'] ) )
                $return_str .= $key.'='.$val;
            else
                $return_str .= $params['array_name'].'['.$key.']='.$val;
        }
    }

    return $return_str;
}

/**
 * @param string $str
 * @param array $params
 *
 * @return string
 */
function add_url_params( $str, $params )
{
    if( empty( $params ) || !is_array( $params ) )
        return $str;

    $anchor = '';

    $anch_arr = explode( '#', $str, 2 );
    if( isset( $anch_arr[1] ) )
    {
        $str = $anch_arr[0];
        $anchor = '#'.$anch_arr[1];
    }

    if( strpos( $str, '?' ) === false )
        $str .= '?';

    if( ($params_res = array_to_query_string( $params )) )
        $str .= '&'.$params_res;

    return $str.$anchor;
}

/**
 * @param string $str
 * @param array $params
 *
 * @return string
 */
function exclude_params( $str, $params )
{
    if( empty( $params ) || !is_array( $params ) )
        return $str;

    $add_quest = false;
    $anchor = '';
    $script = '';
    $param_str = '';

    $anch_arr = explode( '#', $str, 2 );
    if( isset( $anch_arr[1] ) )
    {
        $str = $anch_arr[0];
        $anchor = '#'.$anch_arr[1];
    }

    $qmark_pos = strstr( $str, '?' );
    if( $qmark_pos !== false )
    {
        $quest_arr = explode( '?', $str, 2 );
        $script = $quest_arr[0];
        $param_str = $quest_arr[1];
        $add_quest = true;
    } else
        $script = $str;

    if( $param_str === '' )
    {
        // check if script is a string of parameters
        $eg_pos = strpos( $script, '=' );
        $slash_pos = strpos( $script, '/' );
        if( $slash_pos === false
         && (substr( $script, 0, 1 ) === '&' || $eg_pos !== false) )
        {
            // only params provided to class...
            $param_str = $script;
            $script = '';
        }
    }

    $params_res = '';
    if( $param_str !== '' )
    {
        parse_str( $param_str, $res );

        $new_query_args = [];
        foreach( $res as $key => $val )
        {
            if( in_array( $key, $params, true ) )
                continue;

            $new_query_args[$key] = $val;
        }

        if( !empty( $new_query_args ) )
            $params_res = array_to_query_string( $new_query_args );
    }

    if( $add_quest )
        $params_res = '?'.$params_res;

    return $script.$params_res.$anchor;
}

/**
 * @param int $files
 *
 * @return string
 */
function format_filesize( $files )
{
    $files = (int)$files;

    if( $files >= 1073741824 )
        $return_str = (round( $files / 1073741824 * 100 ) / 100) . 'GB';
    elseif( $files >= 1048576 )
        $return_str = (round( $files / 1048576 * 100 ) / 100) . 'MB';
    elseif( $files >= 1024 )
        $return_str = (round( $files / 1024 * 100 ) / 100) . 'KB';
    else
        $return_str = $files.'Bytes';

    return $return_str;
}
