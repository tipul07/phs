<?php
namespace phs;

use phs\libraries\PHS_Utils;
use phs\libraries\PHS_Registry;

final class PHS_Session extends PHS_Registry
{
    public const SESS_FILE_WRITE_RETRIES = 5, SESS_FILE_READ_RETRIES = 5;

    public const ERR_DOMAIN = 1, ERR_COOKIE = 2;

    public const SESS_DIR_LENGTH = 2, SESS_DIR_MAX_SEGMENTS = 4;

    public const SESS_DATA = 'sess_data',
        SESS_DIR = 'sess_dir', SESS_NAME = 'sess_name', SESS_COOKIE_LIFETIME = 'sess_cookie_lifetime', SESS_COOKIE_PATH = 'sess_cookie_path',
        SESS_SAMESITE = 'sess_samesite', SESS_AUTOSTART = 'sess_autostart', SESS_STARTED = 'sess_started';

    // Make sure session is not considered garbage by adding a parameter in session with a "random" number
    public const SESS_TIME_PARAM_NAME = '__phs_t';

    public function __construct()
    {
        parent::__construct();
        self::init();
    }

    public static function init() : bool
    {
        self::reset_registry();

        if (defined('PHS_SESSION_DIR')) {
            self::set_data(self::SESS_DIR, PHS_SESSION_DIR);
        }
        if (defined('PHS_SESSION_NAME')) {
            self::set_data(self::SESS_NAME, PHS_SESSION_NAME);
        }
        if (defined('PHS_SESSION_COOKIE_LIFETIME')) {
            self::set_data(self::SESS_COOKIE_LIFETIME, PHS_SESSION_COOKIE_LIFETIME);
        }
        if (defined('PHS_SESSION_COOKIE_PATH')) {
            self::set_data(self::SESS_COOKIE_PATH, PHS_SESSION_COOKIE_PATH);
        }
        if (defined('PHS_SESSION_SAMESITE')) {
            self::set_data(self::SESS_SAMESITE, PHS_SESSION_SAMESITE);
        }
        if (defined('PHS_SESSION_AUTOSTART')) {
            self::set_data(self::SESS_AUTOSTART, PHS_SESSION_AUTOSTART);
        }

        return true;
    }

    /**
     * Delete a session value
     * @param null|string $key
     *
     * @return null|bool
     */
    public static function _d($key = null)
    {
        if (PHS::prevent_session()
         || (!self::is_started() && !self::start())) {
            return null;
        }

        if (!($sess_arr = self::get_data(self::SESS_DATA))
         || !is_array($sess_arr)) {
            $sess_arr = [];
        }

        if ($key === null) {
            $sess_arr = [];
        } elseif (isset($sess_arr[$key])) {
            unset($sess_arr[$key]);
        }

        self::set_data(self::SESS_DATA, $sess_arr);

        return true;
    }

    /**
     * Get a value from session for provided key or if key is null return all session data
     * @param null|string $key
     *
     * @return null|array|mixed
     */
    public static function _g($key = null)
    {
        if (PHS::prevent_session()
         || (!self::is_started() && !self::start())) {
            return null;
        }

        if (!($sess_arr = self::get_data(self::SESS_DATA))
         || !is_array($sess_arr)) {
            $sess_arr = [];
        }

        if ($key === null) {
            return $sess_arr;
        }

        return $sess_arr[$key] ?? null;
    }

    /**
     * Set a key-value pair which will be saved in session
     *
     * @param string $key
     * @param string $val
     *
     * @return bool
     */
    public static function _s(string $key, string $val) : bool
    {
        if (PHS::prevent_session()
         || (!self::is_started() && !self::start())) {
            return false;
        }

        if (!($sess_arr = self::get_data(self::SESS_DATA))
         || !is_array($sess_arr)) {
            $sess_arr = [];
        }

        $sess_arr[$key] = $val;

        self::set_data(self::SESS_DATA, $sess_arr);

        return true;
    }

    /**
     * @param null|array $options_arr
     *
     * @return array
     */
    public static function validate_cookie_params(?array $options_arr = null) : array
    {
        $options_arr ??= [];

        if (empty($options_arr['expires'])) {
            $options_arr['expires'] = 0;
        }
        if (empty($options_arr['path']) || !is_string($options_arr['path'])) {
            $options_arr['path'] = self::get_data(self::SESS_COOKIE_PATH);
        }
        if (empty($options_arr['domain']) || !is_string($options_arr['domain'])) {
            $options_arr['domain'] = PHS_DOMAIN;
        }

        $options_arr['secure'] = (!empty($options_arr['secure']));
        $options_arr['httponly'] = (!empty($options_arr['httponly']));

        if (empty($options_arr['samesite']) || !is_string($options_arr['samesite'])
         || !in_array(strtolower($options_arr['samesite']), ['none', 'lax', 'strict'], true)) {
            $options_arr['samesite'] = 'Lax';
        } else {
            $options_arr['samesite'] = ucfirst(strtolower($options_arr['samesite']));
        }

        return $options_arr;
    }

    /**
     * @param string $name
     * @param string|int $value
     * @param null|array $options_arr
     * @return bool
     */
    public static function raw_setcookie(string $name, $value, ?array $options_arr = null) : bool
    {
        $options_arr = self::validate_cookie_params($options_arr);

        if (@headers_sent()) {
            return false;
        }

        $header = 'Set-Cookie: ';
        $header .= rawurlencode($name).'='.rawurlencode($value).';';
        $header .= 'expires='.\gmdate('D, d-M-Y H:i:s T', $options_arr['expires']).';';
        $header .= 'Max-Age='.max(0, ($options_arr['expires'] - time())).';';
        $header .= 'path='.rawurlencode($options_arr['path']).';';
        $header .= 'domain='.rawurlencode($options_arr['domain']).';';

        if (!empty($options_arr['secure'])) {
            $header .= 'secure;';
        }
        if (!empty($options_arr['httponly'])) {
            $header .= 'httponly;';
        }

        $header .= 'SameSite='.rawurlencode($options_arr['samesite']);

        @header($header, false);
        $_COOKIE[$name] = $value;

        return true;
    }

    /**
     * @param string $name
     * @param string $val
     * @param null|array $params
     *
     * @return bool
     */
    public static function set_cookie(string $name, string $val, ?array $params = null) : bool
    {
        self::st_reset_error();

        if (empty($name)) {
            self::st_set_error(self::ERR_COOKIE, self::_t('Please provide valid cookie name.'));

            return false;
        }

        if (!defined('PHS_DOMAIN')) {
            self::st_set_error(self::ERR_COOKIE, self::_t('Framework domain is not defined.'));

            return false;
        }

        if (@headers_sent()) {
            self::st_set_error(self::ERR_COOKIE, self::_t('Headers already sent to request.'));

            return false;
        }

        $params = self::validate_cookie_params($params);
        $params['alter_globals'] = (!isset($params['alter_globals']) || !empty($params['alter_globals']));
        $params['expire_secs'] = (int)($params['expire_secs'] ?? 0);

        if ($params['expire_secs'] < 0) {
            return self::delete_cookie($name, $params);
        }

        $time_expire = time() + $params['expire_secs'];

        $cookie_params = [
            'expires'  => $time_expire,
            'path'     => $params['path'],
            'domain'   => PHS_DOMAIN,
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'],
        ];

        if (defined('PHP_VERSION') && version_compare(constant('PHP_VERSION'), '7.3.0', '>=')) {
            if (!@setcookie($name, $val, $cookie_params)) {
                return false;
            }
        } elseif (!self::raw_setcookie($name, $val, $cookie_params)) {
            return false;
        }

        if (!empty($params['alter_globals'])) {
            $_COOKIE[$name] = $val;
            $_REQUEST[$name] = $val;
        }

        return true;
    }

    /**
     * @param string $name
     * @param null|array $params
     *
     * @return bool
     */
    public static function delete_cookie(string $name, ?array $params = null) : bool
    {
        self::st_reset_error();

        if (empty($name)) {
            self::st_set_error(self::ERR_COOKIE, self::_t('Please provide valid cookie name and value.'));

            return false;
        }

        if (!defined('PHS_DOMAIN')) {
            self::st_set_error(self::ERR_COOKIE, self::_t('Framework domain is not defined.'));

            return false;
        }

        if (@headers_sent()) {
            self::st_set_error(self::ERR_COOKIE, self::_t('Headers already sent to request.'));

            return false;
        }

        $params = self::validate_cookie_params($params);
        $params['alter_globals'] = (!isset($params['alter_globals']) || !empty($params['alter_globals']));

        $time_expire = time() - 90000;

        $cookie_params = [
            'expires'  => $time_expire,
            'path'     => $params['path'],
            'domain'   => PHS_DOMAIN,
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'],
        ];

        if (defined('PHP_VERSION') && version_compare(constant('PHP_VERSION'), '7.3.0', '>=')) {
            if (!@setcookie($name, '', $cookie_params)) {
                return false;
            }
        } elseif (!self::raw_setcookie($name, '', $cookie_params)) {
            return false;
        }

        if (!empty($params['alter_globals'])) {
            if (isset($_COOKIE[$name])) {
                unset($_COOKIE[$name]);
            }
            if (isset($_REQUEST[$name])) {
                unset($_REQUEST[$name]);
            }
        }

        return true;
    }

    public static function get_cookie(string $name) : ?string
    {
        return $_COOKIE[$name] ?? null;
    }

    public static function start() : bool
    {
        if (PHS::prevent_session()) {
            return false;
        }

        if (self::is_started()) {
            return true;
        }

        if (!defined('PHS_DOMAIN') || !constant('PHS_DOMAIN')) {
            self::st_set_error(self::ERR_DOMAIN, self::_t('Domain not set.'));

            return false;
        }

        @session_set_save_handler(
            [__CLASS__, 'sf_open'],
            [__CLASS__, 'sf_close'],
            [__CLASS__, 'sf_read'],
            [__CLASS__, 'sf_write'],
            [__CLASS__, 'sf_destroy'],
            [__CLASS__, 'sf_gc']
        );

        @session_save_path(self::get_data(self::SESS_DIR));
        @session_cache_limiter('nocache');
        @session_name(self::get_data(self::SESS_NAME));

        // SameSite session cookie...
        if (defined('PHP_VERSION') && version_compare(constant('PHP_VERSION'), '7.3.0', '>=')) {
            @session_set_cookie_params([
                'lifetime' => self::get_data(self::SESS_COOKIE_LIFETIME),
                'path'     => self::get_data(self::SESS_COOKIE_PATH),
                'domain'   => PHS_DOMAIN,
                'secure'   => PHS::is_secured_request(),
                'httponly' => true,
                'samesite' => self::get_data(self::SESS_SAMESITE), ]);
        } else {
            @session_set_cookie_params(self::get_data(self::SESS_COOKIE_LIFETIME),
                self::get_data(self::SESS_COOKIE_PATH),
                PHS_DOMAIN,
                PHS::is_secured_request(),
                true);
        }

        @register_shutdown_function([__CLASS__, 'session_close']);

        // If provided session ID is not safe, generate a new one
        if (!self::safe_session_id(@session_id())) {
            @session_regenerate_id(true);
        }

        self::start_session_with_data();

        return true;
    }

    public static function get_id() : ?string
    {
        return PHS::prevent_session()
            ? null
            : (@session_id() ?: null);
    }

    public static function resume_session(string $id) : ?bool
    {
        if (PHS::prevent_session()
           || !self::safe_session_id($id)) {
            return null;
        }

        @session_reset();
        @session_id($id);

        self::start_session_with_data();

        return true;
    }

    public static function safe_session_id($id) : ?string
    {
        if (empty($id) || !is_string($id)
         || !preg_match('/^[-,a-zA-Z0-9]{1,128}$/', $id)) {
            return null;
        }

        return $id;
    }

    /**
     * @param string $id
     *
     * @return array
     */
    public static function get_session_id_dir_as_array($id) : array
    {
        if (empty($id) || !is_string($id)
         || !self::safe_session_id($id)
         || !($return_arr = @str_split($id, self::SESS_DIR_LENGTH))
         || !is_array($return_arr)) {
            return [];
        }

        if (count($return_arr) > self::SESS_DIR_MAX_SEGMENTS) {
            $return_arr = @array_slice($return_arr, 0, self::SESS_DIR_MAX_SEGMENTS);
        }

        return $return_arr;
    }

    /**
     * @param string $id
     *
     * @return string
     */
    public static function get_session_id_dir($id) : string
    {
        if (empty($id) || !is_string($id)
         || !self::safe_session_id($id)) {
            return '';
        }

        if (($sess_dir = self::get_data(self::SESS_DIR))) {
            $sess_dir = rtrim($sess_dir, '/\\');
        }

        if (empty($sess_dir)) {
            $sess_dir = '';
        }

        if (($id_arr = self::get_session_id_dir_as_array($id))) {
            $sess_dir .= '/'.implode('/', $id_arr);
        }

        return $sess_dir;
    }

    /**
     * @param string $id
     *
     * @return string
     */
    public static function get_session_file_name_for_id($id)
    {
        if (!self::safe_session_id($id)) {
            return false;
        }

        return 'sess_'.$id;
    }

    /**
     * @param string $id
     *
     * @return string|bool
     */
    public static function get_session_id_file_name($id)
    {
        if (!self::safe_session_id($id)) {
            return false;
        }

        if (!($sess_dir = self::get_session_id_dir($id))) {
            return self::get_session_file_name_for_id($id);
        }

        return $sess_dir.'/'.self::get_session_file_name_for_id($id);
    }

    /**
     * @param bool $params
     *
     * @return bool
     */
    public static function session_close($params = false)
    {
        if (PHS::prevent_session()
         || !self::is_started()
         || !self::safe_session_id(@session_id())) {
            return true;
        }

        if (!($sess_arr = self::get_data(self::SESS_DATA))
         || !is_array($sess_arr)) {
            $sess_arr = [];
        }

        $_SESSION = $sess_arr;

        $_SESSION[self::SESS_TIME_PARAM_NAME] = microtime(true);

        @session_write_close();

        self::set_data(self::SESS_STARTED, false);

        return true;
    }

    public static function sf_open($path, $session_name)
    {
        if (PHS::prevent_session()) {
            return true;
        }

        if (!@is_dir($path)) {
            PHS_Utils::mkdir_tree($path, ['dir_mode' => 0775]);
        }

        return true;
    }

    public static function sf_close()
    {
        return true;
    }

    public static function sf_read($id)
    {
        if (PHS::prevent_session()
         || !($sess_file = self::get_session_id_file_name($id))
         || !@file_exists($sess_file)) {
            return '';
        }

        $retries = self::SESS_FILE_READ_RETRIES;
        while ((false === ($ret_val = @file_get_contents($sess_file))) && $retries) {
            $retries--;
        }

        if (!$ret_val) {
            return '';
        }

        return $ret_val;
    }

    public static function sf_write($id, $data)
    {
        if (PHS::prevent_session()
         || !self::safe_session_id($id)) {
            return true;
        }

        if (!($sess_file = self::get_session_id_file_name($id))) {
            return false;
        }

        if (!@file_exists($sess_file)
        && ($sess_dir = self::get_session_id_dir($id))
        && !@is_dir($sess_dir)) {
            // maybe we should create directory...
            if (!PHS_Utils::mkdir_tree($sess_dir, ['dir_mode' => 0775])) {
                return false;
            }
        }

        $retries = self::SESS_FILE_WRITE_RETRIES;
        while (!($fil = @fopen($sess_file, 'wb')) && $retries) {
            $retries--;
        }

        if (!$fil) {
            return false;
        }

        @fwrite($fil, $data);
        @fflush($fil);
        @fclose($fil);

        return true;
    }

    public static function sf_destroy($id)
    {
        if (PHS::prevent_session()) {
            return true;
        }

        if (($sess_file = self::get_session_id_file_name($id))
         && @file_exists($sess_file)) {
            @unlink($sess_file);
        }

        return true;
    }

    public static function sf_gc($maxlifetime)
    {
        if (PHS::prevent_session()) {
            return true;
        }

        return (bool)self::sessions_gc($maxlifetime);
    }

    /**
     * @param bool|int $maxlifetime
     *
     * @return array|bool
     */
    public static function sessions_gc($maxlifetime = false)
    {
        if (($sess_dir = self::get_data(self::SESS_DIR))) {
            $sess_dir = rtrim($sess_dir, '/\\');
        }

        if (empty($sess_dir)
         && defined('PHS_SESSION_DIR')) {
            $sess_dir = constant('PHS_SESSION_DIR');
        }

        if (empty($sess_dir)) {
            return false;
        }

        $sess_dir = rtrim($sess_dir, '/\\');

        if ($maxlifetime === false
        && defined('PHS_SESSION_COOKIE_LIFETIME')) {
            $maxlifetime = constant('PHS_SESSION_COOKIE_LIFETIME');
        }

        $maxlifetime = (int)$maxlifetime;

        // If max lifetime is 0 (meaning till browser is closed) we will put a default value of 30 days
        if (empty($maxlifetime)) {
            $maxlifetime = 2592000;
        } // delete all sessions older than 30 days if session max lifetime is 0...

        $dir_pattern = $sess_dir.str_repeat('/*', self::SESS_DIR_MAX_SEGMENTS);

        $return_arr = [];
        $return_arr['sess_dir'] = $sess_dir;
        $return_arr['dir_pattern'] = $dir_pattern;
        $return_arr['maxlifetime'] = $maxlifetime;
        $return_arr['total'] = 0;
        $return_arr['deleted'] = 0;

        if (($file_list = @glob($dir_pattern.'/'.self::get_session_file_name_for_id('*').'*'))) {
            $empty_dir_maybe = [];

            foreach ($file_list as $file) {
                $return_arr['total']++;

                if (@file_exists($file)
                && @filemtime($file) + $maxlifetime < time()) {
                    @unlink($file);

                    $check_dir = $file;
                    for ($i = 0; $i < self::SESS_DIR_MAX_SEGMENTS; $i++) {
                        $check_dir = @dirname($check_dir);
                    }

                    $empty_dir_maybe[$check_dir] = true;

                    $return_arr['deleted']++;
                }
            }

            if (!empty($empty_dir_maybe)) {
                foreach ($empty_dir_maybe as $check_dir => $true) {
                    PHS_Utils::rmdir_tree($check_dir, ['recursive' => true, 'only_if_no_files' => true]);
                }
            }
        }

        return $return_arr;
    }

    public static function is_started() : bool
    {
        return (bool)self::get_data(self::SESS_STARTED);
    }

    private static function start_session_with_data() : void
    {
        @session_start();

        self::set_data(self::SESS_STARTED, true);

        // safe...
        if (empty($_SESSION) || !is_array($_SESSION)) {
            $_SESSION = [];
        }

        self::set_data(self::SESS_DATA, $_SESSION);
    }

    private static function reset_registry() : void
    {
        self::set_data(self::SESS_DIR, '');
        self::set_data(self::SESS_NAME, 'PHS_SESS');
        self::set_data(self::SESS_COOKIE_LIFETIME, 0);
        self::set_data(self::SESS_COOKIE_PATH, '/');
        self::set_data(self::SESS_AUTOSTART, false);

        self::set_data(self::SESS_STARTED, false);
        self::set_data(self::SESS_DATA, []);
    }
}
