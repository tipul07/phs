<?php

if (!defined('DATETIME_T_EMPTY')) {
    define('DATETIME_T_EMPTY', '0000-00-00T00:00:00');
}
if (!defined('DATETIME_T_FORMAT')) {
    define('DATETIME_T_FORMAT', 'Y-m-d\TH:i:s');
}

use phs\PHS;
use phs\PHS_Db;
use phs\libraries\PHS_Error;
use phs\libraries\PHS_Roles;
use phs\libraries\PHS_Utils;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Model_Core_base;
use phs\system\core\libraries\PHS_Migrations_manager;
use phs\system\core\libraries\PHS_Requests_queue_manager;

function phs_version() : string
{
    return '1.2.4.7';
}

// region Helper functions
/**
 * @return array
 */
function action_request_login() : array
{
    $action_result = PHS_Action::default_action_result();
    $action_result['request_login'] = true;

    return $action_result;
}

/**
 * @param string|array $path
 * @param null|array $args
 * @param null|array $extra
 *
 * @return array
 */
function action_redirect(array | string $path = '', ?array $args = null, ?array $extra = null) : array
{
    $action_result = PHS_Action::default_action_result();
    if (is_string($path)) {
        if ($path === '') {
            $path = PHS::url();
        }
        $action_result['redirect_to_url'] = $path;
    } elseif (is_array($path)) {
        $action_result['redirect_to_url'] = PHS::url($path, $args, $extra);
    }

    return $action_result;
}

/**
 * @param string|array $role_units
 * @param null|array $roles_params
 * @param null|array|int $account_structure
 *
 * @return bool
 */
function can($role_units, ?array $roles_params = null, $account_structure = null) : bool
{
    static $current_structure = null;

    if ($account_structure === null) {
        if ($current_structure === null) {
            $current_structure = PHS::account_structure(PHS::user_logged_in());
        }

        $account_structure = $current_structure;
    } else {
        $account_structure = PHS::account_structure($account_structure);
    }

    return (bool)PHS_Roles::user_has_role_units($account_structure, $role_units, $roles_params);
}

function migrations_manager() : ?PHS_Migrations_manager
{
    PHS::st_reset_error();

    /** @var PHS_Migrations_manager $manager */
    if (!($manager = PHS::get_core_library_instance('migrations_manager', ['as_singleton' => true]))) {
        PHS::st_set_error(PHS_Error::ERR_RESOURCES, PHS::_t('Error loading required resources.'));

        return null;
    }

    return $manager;
}

function requests_queue_manager() : ?PHS_Requests_queue_manager
{
    PHS::st_reset_error();

    /** @var PHS_Requests_queue_manager $manager */
    if (!($manager = PHS::get_core_library_instance('requests_queue_manager', ['as_singleton' => true]))) {
        PHS::st_set_error(PHS_Error::ERR_RESOURCES, PHS::_t('Error loading required resources.'));

        return null;
    }

    return $manager;
}

function http_call(
    string $url,
    ?string $method = null,
    null | array | string $payload = null,
    ?array $settings = null,
    array $params = [],
) : ?array {
    if (!($rq_manager = requests_queue_manager())) {
        return null;
    }

    $params['max_retries'] = (int)($params['max_retries'] ?? 1);
    $params['handle'] ??= null;
    $params['sync_run'] = !isset($params['sync_run']) || !empty($params['sync_run']);
    $params['same_thread_if_bg'] = !isset($params['same_thread_if_bg']) || !empty($params['same_thread_if_bg']);
    $params['run_after'] ??= null;

    if (!empty($params['run_after'])
        && ($run_after = parse_db_date($params['run_after']))) {
        $params['run_after'] = date(PHS_Model_Core_base::DATETIME_DB, $run_after);
    } else {
        $params['run_after'] = null;
    }

    if (!($result = $rq_manager->http_call($url, $method, $payload, $settings, $params))) {
        PHS::st_copy_or_set_error($rq_manager, PHS_Error::ERR_RESOURCES, PHS::_t('Error sending HTTP call to background.'));

        return null;
    }

    return $result;
}
// endregion Helper functions

function phs_init_before_bootstrap() : bool
{
    static $did_definitions = null;

    if (!defined('PHS_PATH')
     || !defined('PHS_DEFAULT_DOMAIN')
     || !defined('PHS_DEFAULT_PORT')
     || !defined('PHS_DEFAULT_SSL_DOMAIN')
     || !defined('PHS_DEFAULT_SSL_PORT')
     || !defined('PHS_DEFAULT_DOMAIN_PATH')) {
        return false;
    }

    if ($did_definitions !== null) {
        return true;
    }

    $did_definitions = true;

    if (!defined('PHS_DEFAULT_FULL_PATH_WWW')) {
        define('PHS_DEFAULT_FULL_PATH_WWW',
            PHS_DEFAULT_DOMAIN.(PHS_DEFAULT_PORT !== '' ? ':' : '').PHS_DEFAULT_PORT.'/'.PHS_DEFAULT_DOMAIN_PATH);
    }
    if (!defined('PHS_DEFAULT_FULL_SSL_PATH_WWW')) {
        define('PHS_DEFAULT_FULL_SSL_PATH_WWW',
            PHS_DEFAULT_SSL_DOMAIN.(PHS_DEFAULT_SSL_PORT !== '' ? ':' : '').PHS_DEFAULT_SSL_PORT.'/'.PHS_DEFAULT_DOMAIN_PATH);
    }

    if (!defined('PHS_DEFAULT_HTTP')) {
        define('PHS_DEFAULT_HTTP', 'http://'.PHS_DEFAULT_FULL_PATH_WWW);
    }
    if (!defined('PHS_DEFAULT_HTTPS')) {
        define('PHS_DEFAULT_HTTPS', 'https://'.PHS_DEFAULT_FULL_SSL_PATH_WWW);
    }

    // Root folders
    if (!defined('PHS_SETUP_DIR')) {
        define('PHS_SETUP_DIR', PHS_PATH.'_setup/');
    }
    if (!defined('PHS_CONFIG_DIR')) {
        define('PHS_CONFIG_DIR', PHS_PATH.'config/');
    }
    if (!defined('PHS_SYSTEM_DIR')) {
        define('PHS_SYSTEM_DIR', PHS_PATH.'system/');
    }
    if (!defined('PHS_PLUGINS_DIR')) {
        define('PHS_PLUGINS_DIR', PHS_PATH.'plugins/');
    }

    // If logging dir is not setup in main.php or config/*, default location is in system/logs/
    if (!defined('PHS_DEFAULT_LOGS_DIR')) {
        define('PHS_DEFAULT_LOGS_DIR', PHS_SYSTEM_DIR.'logs/');
    }

    // If uploads dir is not setup in main.php or config/*, default location is in _uploads/
    if (!defined('PHS_DEFAULT_UPLOADS_DIR')) {
        define('PHS_DEFAULT_UPLOADS_DIR', PHS_PATH.'_uploads/');
    }

    // If assets dir is not setup in main.php or config/*, default location is in assets/
    if (!defined('PHS_DEFAULT_ASSETS_DIR')) {
        define('PHS_DEFAULT_ASSETS_DIR', PHS_PATH.'assets/');
    }

    // Second level folders
    if (!defined('PHS_CORE_DIR')) {
        define('PHS_CORE_DIR', PHS_SYSTEM_DIR.'core/');
    }
    if (!defined('PHS_LIBRARIES_DIR')) {
        define('PHS_LIBRARIES_DIR', PHS_SYSTEM_DIR.'libraries/');
    }

    if (!defined('PHS_CORE_MODEL_DIR')) {
        define('PHS_CORE_MODEL_DIR', PHS_CORE_DIR.'models/');
    }
    if (!defined('PHS_CORE_CONTROLLER_DIR')) {
        define('PHS_CORE_CONTROLLER_DIR', PHS_CORE_DIR.'controllers/');
    }
    if (!defined('PHS_CORE_VIEW_DIR')) {
        define('PHS_CORE_VIEW_DIR', PHS_CORE_DIR.'views/');
    }
    if (!defined('PHS_CORE_ACTION_DIR')) {
        define('PHS_CORE_ACTION_DIR', PHS_CORE_DIR.'actions/');
    }
    if (!defined('PHS_CORE_CONTRACT_DIR')) {
        define('PHS_CORE_CONTRACT_DIR', PHS_CORE_DIR.'contracts/');
    }
    if (!defined('PHS_CORE_EVENT_DIR')) {
        define('PHS_CORE_EVENT_DIR', PHS_CORE_DIR.'events/');
    }
    if (!defined('PHS_CORE_GRAPHQL_DIR')) {
        define('PHS_CORE_GRAPHQL_DIR', PHS_CORE_DIR.'graphql/');
    }
    if (!defined('PHS_CORE_PLUGIN_DIR')) {
        define('PHS_CORE_PLUGIN_DIR', PHS_CORE_DIR.'plugins/');
    }
    if (!defined('PHS_CORE_SCOPE_DIR')) {
        define('PHS_CORE_SCOPE_DIR', PHS_CORE_DIR.'scopes/');
    }
    if (!defined('PHS_CORE_LIBRARIES_DIR')) {
        define('PHS_CORE_LIBRARIES_DIR', PHS_CORE_DIR.'libraries/');
    }
    if (!defined('PHS_CORE_TRAITS_DIR')) {
        define('PHS_CORE_TRAITS_DIR', PHS_CORE_DIR.'traits/');
    }

    // These paths will need a www pair, but after bootstrap
    if (!defined('PHS_THEMES_DIR')) {
        define('PHS_THEMES_DIR', PHS_PATH.'themes/');
    }
    if (!defined('PHS_LANGUAGES_DIR')) {
        define('PHS_LANGUAGES_DIR', PHS_PATH.'languages/');
    }
    if (!defined('PHS_GRAPHQL_DIR')) {
        define('PHS_GRAPHQL_DIR', PHS_PATH.'graphql/');
    }

    // name of directory where email templates are stored (either theme relative or plugin relative)
    // e.g. (themes/default/emails or plugins/accounts/templates/emails)
    if (!defined('PHS_EMAILS_DIRS')) {
        define('PHS_EMAILS_DIRS', 'emails');
    }

    return true;
}

/**
 * @return string
 */
function generate_guid() : string
{
    try {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', random_int(0, 65535), random_int(0, 65535),
            random_int(0, 65535), random_int(16384, 20479), random_int(32768, 49151), random_int(0, 65535), random_int(0, 65535),
            random_int(0, 65535));
    } catch (Exception $e) {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 65535), mt_rand(0, 65535),
            mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535),
            mt_rand(0, 65535));
    }
}

/**
 * @param string $ip
 *
 * @return string
 */
function validate_ip(string $ip) : string
{
    if (!($ip = trim($ip))) {
        return '';
    }

    if (@function_exists('filter_var') && defined('FILTER_VALIDATE_IP')) {
        $ret_val = filter_var($ip, FILTER_VALIDATE_IP);

        return $ret_val ? trim($ret_val) : '';
    }

    if (!($ip_numbers = explode('.', $ip))
     || !is_array($ip_numbers) || count($ip_numbers) !== 4) {
        return '';
    }

    $parsed_ip = '';
    foreach ($ip_numbers as $ip_part) {
        $ip_part = (int)$ip_part;
        if ($ip_part < 0 || $ip_part > 255) {
            return '';
        }

        $parsed_ip = ($parsed_ip !== '' ? '.' : '').$ip_part;
    }

    return $parsed_ip;
}

/**
 * @return string
 */
function request_ip() : string
{
    $guessed_ip = '';
    // CloudFlare proxy
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $guessed_ip = validate_ip($_SERVER['HTTP_CF_CONNECTING_IP']);
    }

    if (empty($guessed_ip)
     && !empty($_SERVER['HTTP_CLIENT_IP'])) {
        $guessed_ip = validate_ip($_SERVER['HTTP_CLIENT_IP']);
    }

    if (empty($guessed_ip)
     && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $guessed_ip = validate_ip($_SERVER['HTTP_X_FORWARDED_FOR']);
    }

    if (empty($guessed_ip)) {
        $guessed_ip = (!empty($_SERVER['REMOTE_ADDR']) ? trim($_SERVER['REMOTE_ADDR']) : '');
    }

    return $guessed_ip;
}

//
// region Database related functions
//
function db_supress_errors($connection = false)
{
    if (!($db_instance = PHS_Db::db($connection))) {
        if (PHS_Db::st_debugging_mode()) {
            PHS_Db::st_throw_error();
        } elseif (PHS_DB_SILENT_ERRORS) {
            return false;
        }

        if (PHS_DB_DIE_ON_ERROR) {
            exit;
        }

        return false;
    }

    $db_instance->suppress_errors();

    return true;
}

function db_restore_errors_state($connection = false)
{
    if (!($db_instance = PHS_Db::db($connection))) {
        if (PHS_Db::st_debugging_mode()) {
            PHS_Db::st_throw_error();
        } elseif (PHS_DB_SILENT_ERRORS) {
            return false;
        }

        if (PHS_DB_DIE_ON_ERROR) {
            exit;
        }

        return false;
    }

    $db_instance->restore_errors_state();

    return true;
}

function db_query_insert($query, $connection = false)
{
    if (!($db_instance = PHS_Db::db($connection))) {
        if (PHS_Db::st_debugging_mode()) {
            PHS_Db::st_throw_error();
        } elseif (PHS_DB_SILENT_ERRORS) {
            return false;
        }

        if (PHS_DB_DIE_ON_ERROR) {
            exit;
        }

        return false;
    }

    if (!$db_instance->query($query, $connection)) {
        if ($db_instance->display_errors()) {
            $error = $db_instance->get_error();
            echo $error['display_error'];
        }

        return 0;
    }

    return $db_instance->last_inserted_id();
}

function db_query($query, $connection = false)
{
    if (!($db_instance = PHS_Db::db($connection))) {
        if (PHS_Db::st_debugging_mode()) {
            PHS_Db::st_throw_error();
        } elseif (PHS_DB_SILENT_ERRORS) {
            return false;
        }

        if (PHS_DB_DIE_ON_ERROR) {
            exit;
        }

        return false;
    }

    if (!($qid = $db_instance->query($query, $connection))) {
        if ($db_instance->display_errors()) {
            $error = $db_instance->get_error();
            echo $error['display_error'];
        }

        if ($db_instance->has_error()
         && ($db_instance->get_error_code() === $db_instance::ERR_CONNECT
                || $db_instance->get_error_code() === $db_instance::ERR_DATABASE
         )) {
            return false;
        }

        return 0;
    }

    return $qid;
}

function db_close($connection = false) : bool
{
    if (!($db_instance = PHS_Db::db($connection))) {
        if (PHS_Db::st_debugging_mode()) {
            PHS_Db::st_throw_error();
        } elseif (PHS_DB_SILENT_ERRORS) {
            return false;
        }

        if (PHS_DB_DIE_ON_ERROR) {
            exit;
        }

        return false;
    }

    $db_instance->close($connection);

    return true;
}

function db_test_connection($connection = false)
{
    if (!($db_instance = PHS_Db::db($connection))) {
        if (PHS_Db::st_debugging_mode()) {
            PHS_Db::st_throw_error();
        } elseif (PHS_DB_SILENT_ERRORS) {
            return false;
        }

        if (PHS_DB_DIE_ON_ERROR) {
            exit;
        }

        return false;
    }

    if (!$db_instance->test_connection($connection)) {
        if ($db_instance->display_errors()) {
            $error = $db_instance->get_error();
            echo $error['display_error'];
        }

        return false;
    }

    return true;
}

function db_last_error($connection = false)
{
    if (!($db_instance = PHS_Db::db($connection))) {
        return false;
    }

    return $db_instance->get_error();
}

function db_fetch_assoc($qid, $connection = false) : ?array
{
    if (!($db_instance = PHS_Db::db($connection))) {
        if (PHS_Db::st_debugging_mode()) {
            PHS_Db::st_throw_error();
        } elseif (PHS_DB_SILENT_ERRORS) {
            return null;
        }

        if (PHS_DB_DIE_ON_ERROR) {
            exit;
        }

        return null;
    }

    return $db_instance->fetch_assoc($qid);
}

/**
 * @param $qid
 * @param false|string $connection
 *
 * @return int
 */
function db_num_rows($qid, $connection = false) : int
{
    if (!($db_instance = PHS_Db::db($connection))) {
        if (PHS_Db::st_debugging_mode()) {
            PHS_Db::st_throw_error();
        } elseif (PHS_DB_SILENT_ERRORS) {
            return 0;
        }

        if (PHS_DB_DIE_ON_ERROR) {
            exit;
        }

        return 0;
    }

    return $db_instance->num_rows($qid);
}

function db_query_count($connection = false) : int
{
    if (!($db_instance = PHS_Db::db($connection))) {
        return 0;
    }

    return $db_instance->queries_number();
}

function db_affected_rows($connection = false)
{
    if (!($db_instance = PHS_Db::db($connection))) {
        return 0;
    }

    return $db_instance->affected_rows();
}

function db_quick_insert($table_name, $insert_arr, $connection = false, $params = false) : string
{
    if (!($db_instance = PHS_Db::db($connection))) {
        return '';
    }

    return $db_instance->quick_insert($table_name, $insert_arr, $connection, $params);
}

function db_quick_edit($table_name, $edit_arr, $connection = false, $params = false) : string
{
    if (!($db_instance = PHS_Db::db($connection))) {
        return '';
    }

    return $db_instance->quick_edit($table_name, $edit_arr, $connection, $params);
}

/**
 * @param $fields
 * @param $connection
 *
 * @return string|string[]
 */
function db_escape($fields, $connection = false)
{
    if (!($db_instance = PHS_Db::db($connection))) {
        return '';
    }

    return $db_instance->escape($fields, $connection);
}

function db_last_id($connection = false)
{
    if (!($db_instance = PHS_Db::db($connection))) {
        return -1;
    }

    return $db_instance->last_inserted_id();
}

function db_settings($connection = false)
{
    if (!($db_instance = PHS_Db::db($connection))) {
        return -1;
    }

    return $db_instance->connection_settings($connection);
}

function db_connection_identifier($connection)
{
    if (empty($connection)
     || !($connection_identifier = PHS_Db::get_connection_identifier($connection))
     || !is_array($connection_identifier)) {
        return false;
    }

    return $connection_identifier;
}

function db_prefix($connection = false) : string
{
    if (!($db_settings = db_settings($connection))
     || !is_array($db_settings)
     || empty($db_settings['prefix'])) {
        return '';
    }

    return $db_settings['prefix'];
}

function db_database($connection = false) : string
{
    if (!($db_settings = db_settings($connection))
     || !is_array($db_settings)
     || empty($db_settings['database'])) {
        return '';
    }

    return $db_settings['database'];
}

function db_dump($dump_params, $connection = false)
{
    PHS_Db::st_reset_error();

    if (!($db_instance = PHS_Db::db($connection))) {
        PHS_Db::st_set_error(PHS_Db::ERR_DATABASE, PHS_Db::_t('Error obtaining database driver instance.'));

        return false;
    }

    if (!($dump_result = $db_instance->dump_database($dump_params))) {
        if ($db_instance->has_error()) {
            PHS_Db::st_copy_error($db_instance);
        } else {
            PHS_Db::st_set_error(PHS_Db::ERR_DATABASE,
                PHS_Db::_t('Error obtaining dump commands from driver instance.'));
        }

        return false;
    }

    return $dump_result;
}
//
// endregion Database related functions
//

function form_str($str) : string
{
    if (!is_scalar($str) || (string)$str === '') {
        return '';
    }

    return str_replace('"', '&quot;', $str);
}

function textarea_str($str) : string
{
    if (!is_scalar($str) || (string)$str === '') {
        return '';
    }

    return str_replace(['<', '>'], ['&lt;', '&gt;'], $str);
}

function make_sure_is_filename(string $str) : string
{
    return str_replace(
        ['..', '/', '\\', '~', '<', '>', '|', '`', '*', '&', ],
        ['.', '', '', '', '', '', '', '', '', '', ],
        $str);
}

/**
 * @param string|mixed $str
 * @param bool|array $params
 *
 * @return int
 */
function seconds_passed($str, $params = false) : int
{
    return time() - parse_db_date($str, $params);
}

function validate_db_date_array(array $date_arr) : bool
{
    for ($i = 0; $i < 6; $i++) {
        if (!isset($date_arr[$i])) {
            return false;
        }

        $date_arr[$i] = (int)$date_arr[$i];
    }

    return !(
        $date_arr[1] < 1 || $date_arr[1] > 12
     || $date_arr[2] < 1 || $date_arr[2] > 31
     || $date_arr[3] < 0 || $date_arr[3] > 23
     || $date_arr[4] < 0 || $date_arr[4] > 59
     || $date_arr[5] < 0 || $date_arr[5] > 59
    );
}

/**
 * @param string $date
 *
 * @return bool
 */
function empty_t_date(string $date) : bool
{
    return empty($date) || $date === DATETIME_T_EMPTY || $date === PHS_Model_Core_base::DATE_EMPTY;
}

/**
 * @param string $date
 * @param bool|array $params
 *
 * @return array|bool
 */
function is_t_date($date, $params = false)
{
    if (is_string($date)) {
        $date = trim($date);
    }

    if (empty($date)
     || !is_string($date)
     || strpos($date, 'T') === false) {
        return false;
    }

    if (empty_t_date($date)) {
        return [0, 0, 0, 0, 0, 0];
    }

    if (empty($params) || !is_array($params)) {
        $params = [];
    }

    $params['validate_intervals'] = (!isset($params['validate_intervals']) || !empty($params['validate_intervals']));

    if (strpos($date, 'T') !== false) {
        $d = explode('T', $date);
        $date_ = explode('-', $d[0]);
        $time_ = explode(':', $d[1], 3);
    } else {
        $date_ = explode('-', $date);
        $time_ = [0, 0, 0];
    }

    for ($i = 0; $i < 3; $i++) {
        if (!isset($date_[$i])
         || !isset($time_[$i])) {
            return false;
        }

        if ($i === 2
         && !empty($time_[$i])) {
            // try removing any timezone at the end of format...
            $time_[$i] = substr($time_[$i], 0, 2);
        }

        $date_[$i] = (int)$date_[$i];
        $time_[$i] = (int)$time_[$i];
    }

    $result_arr = array_merge($date_, $time_);
    if (!empty($params['validate_intervals'])
     && !validate_db_date_array($result_arr)) {
        return false;
    }

    return $result_arr;
}

/**
 * @param string|array $date
 * @param bool|array $params
 *
 * @return false|int
 */
function parse_t_date($date, $params = false)
{
    if (empty($params) || !is_array($params)) {
        $params = [];
    }

    $params['validate_intervals'] = (!isset($params['validate_intervals']) || !empty($params['validate_intervals']));

    if (!isset($params['offset_seconds']) && !isset($params['offset_hours'])) {
        $params['offset_seconds'] = 0;
    } else {
        // offset in seconds...
        if (isset($params['offset_seconds'])) {
            $params['offset_seconds'] = (int)$params['offset_seconds'];
        } else {
            // offset in hours...
            if (empty($params['offset_hours'])) {
                $params['offset_seconds'] = 0;
            } else {
                $params['offset_seconds'] = (int)$params['offset_hours'] * 3600;
            }
        }

        $params['offset_seconds'] = @date('Z') - $params['offset_seconds'];
    }

    if (is_array($date)) {
        for ($i = 0; $i < 6; $i++) {
            if (!isset($date[$i])) {
                return 0;
            }

            $date[$i] = (int)$date[$i];
        }

        $date_arr = $date;

        if (!empty($params['validate_intervals'])
         && !validate_db_date_array($date_arr)) {
            return 0;
        }
    } elseif (is_string($date)) {
        if (!($date_arr = is_t_date($date, $params))) {
            return 0;
        }
    } else {
        return 0;
    }

    return @mktime($date_arr[3], $date_arr[4], $date_arr[5], $date_arr[1], $date_arr[2], $date_arr[0]) + $params['offset_seconds'];
}

function is_db_date(?string $date, array $params = []) : ?array
{
    $date = trim($date ?? '');
    if (empty($date)
        || !str_contains($date, '-')) {
        return null;
    }

    if (empty_db_date($date)) {
        return [0, 0, 0, 0, 0, 0];
    }

    if (empty($params) || !is_array($params)) {
        $params = [];
    }

    $params['validate_intervals'] = (!isset($params['validate_intervals']) || !empty($params['validate_intervals']));

    if (str_contains($date, ' ')) {
        $d = explode(' ', $date);
        $date_ = explode('-', $d[0]);
        $time_ = explode(':', $d[1]);
    } else {
        $date_ = explode('-', $date);
        $time_ = [0, 0, 0];
    }

    for ($i = 0; $i < 3; $i++) {
        if (!isset($date_[$i], $time_[$i])) {
            return null;
        }

        $date_[$i] = (int)$date_[$i];
        $time_[$i] = (int)$time_[$i];
    }

    $result_arr = array_merge($date_, $time_);
    if (!empty($params['validate_intervals'])
     && !validate_db_date_array($result_arr)) {
        return null;
    }

    return $result_arr;
}

/**
 * @param string|array $date
 * @param false|array $params
 *
 * @return int
 */
function parse_db_date($date, $params = false) : int
{
    if (empty($params) || !is_array($params)) {
        $params = [];
    }

    $params['validate_intervals'] = (!isset($params['validate_intervals']) || !empty($params['validate_intervals']));

    if (is_array($date)) {
        for ($i = 0; $i < 6; $i++) {
            if (!isset($date[$i])) {
                return 0;
            }

            $date[$i] = (int)$date[$i];
        }

        $date_arr = $date;

        if (!empty($params['validate_intervals'])
         && !validate_db_date_array($date_arr)) {
            return 0;
        }
    } elseif (is_string($date)) {
        if (!($date_arr = is_db_date($date, $params))) {
            return 0;
        }
    } else {
        return 0;
    }

    if (false === ($ret_val = @mktime($date_arr[3], $date_arr[4], $date_arr[5], $date_arr[1], $date_arr[2], $date_arr[0]))) {
        $ret_val = 0;
    }

    return $ret_val;
}

function empty_db_date(?string $date) : bool
{
    return empty($date) || $date === PHS_Model_Core_base::DATETIME_EMPTY || $date === PHS_Model_Core_base::DATE_EMPTY;
}

function validate_db_date(?string $date, ?string $format = null) : ?string
{
    if (empty_db_date($date)) {
        return null;
    }

    if ($format === null) {
        $format = PHS_Model_Core_base::DATETIME_DB;
    }

    return @date($format, parse_db_date($date));
}

/**
 * @param mixed $str
 *
 * @return string
 */
function prepare_data($str) : string
{
    if (!is_scalar($str) || (string)$str === '') {
        return '';
    }

    return str_replace('\'', '\\\'', str_replace('\\\'', '\'', $str));
}

function http_pretty_date(?string $date, array $params = []) : string
{
    $params['date_format'] ??= null;

    if (empty($date)
        || !($date_time = is_db_date($date))
        || empty_db_date($date)) {
        return '';
    }

    $date_str = !empty($params['date_format'])
        ? @date($params['date_format'], parse_db_date($date_time))
        : $date;

    if (($seconds_ago = seconds_passed($date_time)) < 0) {
        // date in future
        $lang_index = 'in %s';
    } else {
        // date in past
        $lang_index = '%s ago';
    }

    return '<span title="'.PHS::_t($lang_index, PHS_Utils::parse_period($seconds_ago, ['only_big_part' => true])).'">'.$date_str.'</span>';
}

/**
 * @param mixed $url
 *
 * @return string
 */
function safe_url($url) : string
{
    if (!is_scalar($url) || (string)$url === '') {
        return '';
    }

    return str_replace(['?', '&', '#'], ['%3F', '%26', '%23'], $url);
}

/**
 * @param mixed $url
 *
 * @return string
 */
function from_safe_url($url) : string
{
    if (!is_scalar($url) || (string)$url === '') {
        return '';
    }

    return str_replace(['%3F', '%26', '%23'], ['?', '&', '#'], $url);
}

/**
 * This function behaves as http_build_query() except that it doesn't rawurlencode the values (only values if required)
 *
 * @param array|mixed $arr
 * @param bool|array $params
 *
 * @return string
 */
function array_to_query_string($arr, $params = false) : string
{
    if (empty($params) || !is_array($params)) {
        $params = [];
    }

    if (!isset($params['arg_separator'])) {
        $params['arg_separator'] = '&';
    }
    if (!isset($params['raw_encode_values'])) {
        $params['raw_encode_values'] = true;
    }
    if (empty($params['array_name'])) {
        $params['array_name'] = '';
    }

    if (empty($arr) || !is_array($arr)) {
        return '';
    }

    $return_str = '';
    foreach ($arr as $key => $val) {
        $return_str .= ($return_str !== '' ? '&' : '');

        if (is_array($val)) {
            $call_params = $params;
            $call_params['array_name'] = (!empty($params['array_name']) ? $params['array_name'].'['.$key.']' : $key);

            $return_str .= array_to_query_string($val, $call_params);
        } else {
            if (!empty($params['raw_encode_values'])) {
                $val = urlencode($val);
            }

            if (empty($params['array_name'])) {
                $return_str .= $key.'='.$val;
            } else {
                $return_str .= $params['array_name'].'['.$key.']='.$val;
            }
        }
    }

    return $return_str;
}

/**
 * @param string|mixed $str
 * @param array|mixed $params
 *
 * @return string
 */
function add_url_params($str, $params) : string
{
    if (!is_scalar($str) || (string)$str === '') {
        $str = '';
    }

    if (empty($params) || !is_array($params)) {
        return $str;
    }

    $anchor = '';

    $anch_arr = explode('#', $str, 2);
    if (isset($anch_arr[1])) {
        $str = $anch_arr[0];
        $anchor = '#'.$anch_arr[1];
    }

    if (strpos($str, '?') === false) {
        $str .= '?';
    }

    if (($params_res = array_to_query_string($params))) {
        $str .= '&'.$params_res;
    }

    return $str.$anchor;
}

/**
 * @param mixed|string $str
 * @param mixed|array $params
 *
 * @return string
 */
function exclude_params($str, $params) : string
{
    if (!is_scalar($str) || (string)$str === '') {
        return '';
    }

    if (empty($params) || !is_array($params)) {
        return $str;
    }

    $add_quest = false;
    $anchor = '';
    $script = '';
    $param_str = '';

    $anch_arr = explode('#', $str, 2);
    if (isset($anch_arr[1])) {
        $str = $anch_arr[0];
        $anchor = '#'.$anch_arr[1];
    }

    $qmark_pos = strstr($str, '?');
    if ($qmark_pos !== false) {
        $quest_arr = explode('?', $str, 2);
        $script = $quest_arr[0];
        $param_str = $quest_arr[1];
        $add_quest = true;
    } else {
        $script = $str;
    }

    if ($param_str === '') {
        // check if script is a string of parameters
        $eg_pos = strpos($script, '=');
        $slash_pos = strpos($script, '/');
        if ($slash_pos === false
         && (substr($script, 0, 1) === '&' || $eg_pos !== false)) {
            // only params provided to class...
            $param_str = $script;
            $script = '';
        }
    }

    $params_res = '';
    if ($param_str !== '') {
        parse_str($param_str, $res);

        $new_query_args = [];
        foreach ($res as $key => $val) {
            if (in_array($key, $params, true)) {
                continue;
            }

            $new_query_args[$key] = $val;
        }

        if (!empty($new_query_args)) {
            $params_res = array_to_query_string($new_query_args);
        }
    }

    if ($add_quest) {
        $params_res = '?'.$params_res;
    }

    return $script.$params_res.$anchor;
}

/**
 * @param int $files
 *
 * @return string
 */
function format_filesize(int $files) : string
{
    if ($files >= 1073741824) {
        return (round($files / 1073741824 * 100) / 100).'GB';
    }

    if ($files >= 1048576) {
        return (round($files / 1048576 * 100) / 100).'MB';
    }

    if ($files >= 1024) {
        return (round($files / 1024 * 100) / 100).'KB';
    }

    return $files.'Bytes';
}
