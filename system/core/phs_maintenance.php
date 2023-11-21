<?php
namespace phs;

use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Registry;
use phs\libraries\PHS_Instantiable;

// We don't translate messages in this class as they are pure maintenance texts...
final class PHS_Maintenance extends PHS_Registry
{
    // How much will an update token be available in seconds
    public const UPDATE_TOKEN_LIFETIME = 86400;

    // Update token parameter names
    public const PARAM_UPDATE_TOKEN_HASH = '_phs_uth', PARAM_UPDATE_TOKEN_PUBKEY = '_phs_upk';

    private static int $last_output = 0;

    private static int $low_level_db_structure_cache = 0;

    public static function lock_db_structure_read() : int
    {
        return ++self::$low_level_db_structure_cache;
    }

    public static function unlock_db_structure_read() : int
    {
        if (self::$low_level_db_structure_cache === 0) {
            return 0;
        }

        return --self::$low_level_db_structure_cache;
    }

    public static function release_db_structure_lock() : void
    {
        self::$low_level_db_structure_cache = 0;
    }

    public static function db_structure_is_locked() : bool
    {
        return self::$low_level_db_structure_cache > 0;
    }

    public static function output($msg) : void
    {
        // We don't need to output anything else than SQL statements
        if (PHS_Db::dry_update()) {
            return;
        }

        // In logs, we have timestamps
        PHS_Logger::notice($msg, PHS_Logger::TYPE_MAINTENANCE);

        if (empty(self::$last_output)) {
            self::$last_output = time();
            $msg = date('(Y-m-d H:i:s)', self::$last_output).' '.$msg;
        } else {
            $msg = '('.str_pad('+'.(time() - self::$last_output).'s', 19, ' ', STR_PAD_LEFT).') '.$msg;
        }

        if (($callback = self::output_callback())) {
            $callback($msg);
        }
    }

    /**
     * If we need to capture maintenance output we will pass a callble which will handle output
     * If $callback is false, maintenance class will not call anything for the output
     *
     * @param null|bool|callable $callback
     *
     * @return bool|callable
     */
    public static function output_callback($callback = null)
    {
        /** @var false|callable $output_callback */
        static $output_callback = false;

        if ($callback === null) {
            return $output_callback;
        }

        self::st_reset_error();

        if ($callback === false) {
            $output_callback = false;

            return true;
        }

        if (empty($callback)
         || !is_callable($callback)) {
            self::st_set_error(self::ERR_PARAMETERS, 'Maintenance output callback is not a callable.');

            return false;
        }

        $output_callback = $callback;

        return true;
    }

    public static function generate_framework_update_token() : array
    {
        $pub_key = time() + self::UPDATE_TOKEN_LIFETIME;
        $clean_str = $pub_key.':'.PHS_Crypt::crypting_key();
        if (@function_exists('hash')
         && ($hash_algos = @hash_algos())
         && in_array('sha256', $hash_algos, true)) {
            $hashed_str = hash('sha256', $clean_str);
        } else {
            $hashed_str = md5($clean_str);
        }

        return [
            'pub_key' => $pub_key,
            'hash'    => $hashed_str,
        ];
    }

    public static function get_framework_update_url_with_token() : string
    {
        $token = self::generate_framework_update_token();

        $args = [
            self::PARAM_UPDATE_TOKEN_HASH   => $token['hash'],
            self::PARAM_UPDATE_TOKEN_PUBKEY => $token['pub_key'],
        ];

        if (!($query_string = @http_build_query($args))) {
            $query_string = '';
        }

        return PHS::get_update_script_url(true).'?'.$query_string;
    }

    /**
     * @param int $pub_key
     * @param string $hash
     *
     * @return bool
     */
    public static function validate_framework_update_params(int $pub_key, string $hash) : bool
    {
        if (empty($pub_key) || empty($hash)
         || $pub_key < time()) {
            return false;
        }

        $clean_str = $pub_key.':'.PHS_Crypt::crypting_key();
        if (@function_exists('hash')
         && ($hash_algos = @hash_algos())
         && @in_array('sha256', $hash_algos, true)) {
            // hash_equals available in PHP >= 5.6.0
            $generated_hash = hash('sha256', $clean_str);
            if (@function_exists('hash_equals')) {
                return @hash_equals($generated_hash, $hash);
            }

            return $generated_hash === $hash;
        }

        return md5($clean_str) === $hash;
    }

    /**
     * @return bool
     */
    public static function validate_framework_update_action() : bool
    {
        return ($pub_key = PHS_Params::_gp(self::PARAM_UPDATE_TOKEN_PUBKEY, PHS_Params::T_INT))
             && ($hash = PHS_Params::_gp(self::PARAM_UPDATE_TOKEN_HASH, PHS_Params::T_NOHTML))
             && self::validate_framework_update_params($pub_key, $hash);
    }

    /**
     * Check if provided dir contains a valid plugin named $plugin
     *
     * @param string $plugin
     * @param string $repo_dir An absolute path or relative path from framework plugins directory
     *
     * @return false|array
     */
    public static function check_plugin_in_repo(string $plugin, string $repo_dir)
    {
        self::st_reset_error();

        // Check if plugin directory is present in repository
        if (!($real_path = self::convert_plugin_repo_to_real_path($repo_dir))
         || !@file_exists($real_path.$plugin)
         || !@is_dir($real_path.$plugin)) {
            self::st_set_error(self::ERR_PLUGIN_SETUP, self::_t('Plugin repository directory is not valid.'));

            return false;
        }

        // Check if JSON file is present in plugin directory
        if (!($json_file = PHS_Instantiable::get_plugin_details_json_file($plugin))
         || !($json_full_path = $real_path.$plugin.'/'.$json_file)
         || !($json_arr = PHS_Instantiable::read_plugin_json_details($json_full_path))) {
            self::st_set_error(self::ERR_PLUGIN_SETUP, self::_t('Plugin repository directory is not valid.'));

            return false;
        }

        return $json_arr;
    }

    /**
     * Check if provided dir
     *
     * @param string $repo_dir
     * @param bool $slash_ended
     *
     * @return string
     */
    public static function convert_plugin_repo_to_real_path(string $repo_dir, bool $slash_ended = true) : string
    {
        if (substr($repo_dir, 0, 1) === '/') {
            return $slash_ended ? rtrim($repo_dir, '/').'/' : $repo_dir;
        }

        if (!($real_path = @realpath(PHS_PLUGINS_DIR.$repo_dir))) {
            return '';
        }

        return $slash_ended ? rtrim($real_path, '/').'/' : $real_path;
    }

    public static function symlink_plugin_from_repo(string $plugin, string $repo_dir) : bool
    {
        self::st_reset_error();

        if (empty($plugin) || empty($repo_dir)
         || !($real_path = self::convert_plugin_repo_to_real_path($repo_dir))
         || !self::check_plugin_in_repo($plugin, $repo_dir)
         || !($instance_details = PHS_Instantiable::get_instance_details('PHS_Plugin_'.ucfirst(strtolower($plugin)),
             $plugin, PHS_Instantiable::INSTANCE_TYPE_PLUGIN))) {
            if (!self::st_has_error()) {
                self::st_set_error(self::ERR_PLUGIN_SETUP,
                    self::_t('Error creating symlink for plugin %s from %s (%s) plugin repository directory.',
                        ($plugin ?: 'N/A'), ($repo_dir ?: 'N/A'), (!empty($real_path) ? $real_path : 'N/A')));
            }

            return false;
        }

        if (!empty($instance_details['plugin_is_setup'])) {
            return true;
        }

        return @symlink(rtrim($repo_dir, '/').'/'.$plugin, PHS_PLUGINS_DIR.$plugin);
    }

    public static function plugin_is_symlinked_with_repo(string $plugin, string $repo_dir) : bool
    {
        if (empty($plugin) || empty($repo_dir)
         || !self::check_plugin_in_repo($plugin, $repo_dir)
         || !($instance_details = PHS_Instantiable::get_instance_details('PHS_Plugin_'.ucfirst(strtolower($plugin)),
             $plugin, PHS_Instantiable::INSTANCE_TYPE_PLUGIN))) {
            return false;
        }

        return !empty($instance_details['plugin_is_setup']);
    }

    public static function plugin_is_symlinked(string $plugin) : bool
    {
        return !empty($plugin)
                && ($instance_details = PHS_Instantiable::get_instance_details('PHS_Plugin_'.ucfirst(strtolower($plugin)),
                    $plugin, PHS_Instantiable::INSTANCE_TYPE_PLUGIN))
                && !empty($instance_details['plugin_is_setup']);
    }

    public static function unlink_plugin(string $plugin) : bool
    {
        self::st_reset_error();

        if (empty($plugin)
         || !($instance_details
                = PHS_Instantiable::get_instance_details(
                    'PHS_Plugin_'.ucfirst(strtolower($plugin)),
                    $plugin,
                    PHS_Instantiable::INSTANCE_TYPE_PLUGIN))) {
            return false;
        }

        if (empty($instance_details['plugin_is_setup'])) {
            return true;
        }

        if (empty($instance_details['plugin_is_link'])) {
            self::st_set_error(self::ERR_PLUGIN_SETUP, self::_t('Plugin is not setup using symlinks.'));

            return true;
        }

        return @unlink(PHS_PLUGINS_DIR.$plugin);
    }
}
