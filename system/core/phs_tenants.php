<?php
namespace phs;

use phs\libraries\PHS_Utils;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Registry;
use phs\system\core\models\PHS_Model_Tenants;
use phs\system\core\events\tenants\PHS_Event_Tenant_changed;

final class PHS_Tenants extends PHS_Registry
{
    // Header variable passed to framework to select a tenant or _GET/_POST variable name (if applicable)
    public const HEADER_TENANT_IDENTIFIER = 'PHS_TENANT_IDENTIFIER', REQUEST_TENANT_IDENTIFIER = '__phs_tid';

    public const COOKIE_LIFETIME = 432000; // 5 days

    private static ?PHS_Model_Tenants $_tenants_model = null;

    private static ?array $_current_tenant = null;

    public function __construct()
    {
        parent::__construct();
        self::init();
    }

    public static function init() : bool
    {
        if (!PHS::is_multi_tenant()
            || self::get_current_tenant_record()) {
            return true;
        }

        if (!self::_load_dependencies()) {
            return false;
        }

        if (($tenant_id = self::_get_tenant_identifier())) {
            if (!($tenant_arr = self::$_tenants_model->get_tenant_by_identifier($tenant_id))) {
                self::st_set_error(self::ERR_RESOURCES, self::_t('Provided tenant not found.'));

                return false;
            }

            if (!self::set_current_tenant($tenant_arr)) {
                if (!self::st_has_error()) {
                    self::st_set_error(self::ERR_RESOURCES, self::_t('Invalid tenant provided.'));
                }

                return false;
            }

            return true;
        }

        if (
            (($tenant_arr = self::_guess_tenant_from_domain_and_path())
             || ($tenant_arr = self::$_tenants_model->get_default_tenant()))
         && self::set_current_tenant($tenant_arr)) {
            return true;
        }

        PHS_Logger::notice('No default tenant defined.', PHS_Logger::TYPE_DEBUG);

        return self::st_has_error();
    }

    public static function get_tenant_details_for_display($tenant_data) : ?string
    {
        if (!PHS::is_multi_tenant()) {
            return '';
        }

        if (!self::_load_dependencies()
           || !($tenant_arr = self::$_tenants_model->data_to_array($tenant_data, ['table_name' => 'phs_tenants']))) {
            return null;
        }

        return $tenant_arr['name'].' ('.$tenant_arr['domain'].(!empty($tenant_arr['directory']) ? '/'.$tenant_arr['directory'] : '').')';
    }

    public static function set_current_tenant($tenant_data) : bool
    {
        if (!PHS::is_multi_tenant()) {
            return true;
        }

        if (!self::_load_dependencies()) {
            return false;
        }

        if (!($tenant_arr = self::$_tenants_model->data_to_array($tenant_data, ['table_name' => 'phs_tenants']))
         || !self::$_tenants_model->is_active($tenant_arr)) {
            PHS_Logger::debug('Tenant ['.($tenant_arr['identifier'] ?? 'N/A').'] cannot be set for ['.self::get_requested_script().'].', PHS_Logger::TYPE_DEBUG);
            self::st_set_error(self::ERR_RESOURCES, self::_t('Provided tenant not found.'));

            return false;
        }

        // Make sure further requests use cookies...
        if (in_array(PHS_Scope::current_scope(), [PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX], true)) {
            PHS_Session::set_cookie(self::REQUEST_TENANT_IDENTIFIER, $tenant_arr['identifier'],
                ['expire_secs' => self::COOKIE_LIFETIME]);
        }

        if (!empty(self::$_current_tenant['id'])
           && (int)self::$_current_tenant['id'] === (int)$tenant_arr['id']) {
            return true;
        }

        if (PHS::st_debugging_mode()) {
            PHS_Logger::debug('Current tenant ['.($tenant_arr['identifier'] ?? 'N/A').'] set for ['.self::get_requested_script().'].',
                PHS_Logger::TYPE_DEBUG);
        }

        $old_tenant = self::$_current_tenant;
        self::$_current_tenant = $tenant_arr;

        if (($settings_arr = self::$_tenants_model->get_tenant_settings($tenant_arr))) {
            if (!empty($settings_arr['default_theme'])
                && !PHS::set_defaut_theme($settings_arr['default_theme'])) {
                PHS_Logger::debug('Cannot set default theme ['.$settings_arr['default_theme'].'] '
                                  .'for tenant #'.$tenant_arr['id'].' ('.($tenant_arr['identifier'] ?? 'N/A').')', PHS_Logger::TYPE_DEBUG);
            }
            if (!empty($settings_arr['current_theme'])
                && !PHS::set_theme($settings_arr['current_theme'])) {
                PHS_Logger::debug('Cannot set current theme ['.$settings_arr['current_theme'].'] '
                                  .'for tenant #'.$tenant_arr['id'].' ('.($tenant_arr['identifier'] ?? 'N/A').')', PHS_Logger::TYPE_DEBUG);
            }
            if (!empty($settings_arr['cascading_themes']) && is_array($settings_arr['cascading_themes'])
            && !PHS::set_cascading_themes($settings_arr['cascading_themes'])) {
                PHS_Logger::debug('Cannot set cascading themes ['.print_r($settings_arr['cascading_themes'], true).'] '
                                  .'for tenant #'.$tenant_arr['id'].' ('.($tenant_arr['identifier'] ?? 'N/A').')', PHS_Logger::TYPE_DEBUG);
            }
        }

        PHS_Event_Tenant_changed::trigger(['old_tenant' => $old_tenant, 'new_tenant' => self::$_current_tenant]);

        return true;
    }

    public static function get_current_tenant_record() : ?array
    {
        if (!PHS::is_multi_tenant()) {
            return null;
        }

        return self::$_current_tenant ?? null;
    }

    public static function get_current_tenant_id() : int
    {
        if (!PHS::is_multi_tenant()) {
            return 0;
        }

        return (int)(self::$_current_tenant['id'] ?? 0);
    }

    private static function get_requested_script() : string
    {
        static $script_name = null;

        if ($script_name !== null) {
            return $script_name;
        }

        if (!empty($_SERVER['SCRIPT_NAME'])
         && ($script_name = basename($_SERVER['SCRIPT_NAME']))) {
            return $script_name;
        }

        if (!empty($_SERVER['SCRIPT_FILENAME'])
         && ($script_name = basename($_SERVER['SCRIPT_FILENAME']))) {
            return $script_name;
        }

        $script_name = '';

        return $script_name;
    }

    private static function _guess_tenant_from_domain_and_path() : ?array
    {
        $directory = self::_get_request_directory();
        if (!empty($_SERVER['SERVER_NAME'])
         && ($tenant_arr = self::_check_tenant_with_domain_and_directory($_SERVER['SERVER_NAME'], $directory))) {
            return $tenant_arr;
        }

        if (!empty($_SERVER['SERVER_ADDR'])
         && ($tenant_arr = self::_check_tenant_with_domain_and_directory($_SERVER['SERVER_ADDR'], $directory))) {
            return $tenant_arr;
        }

        return null;
    }

    private static function _check_tenant_with_domain_and_directory(string $domain, string $directory) : ?array
    {
        if (!empty($domain)
         && ($tenants_arr = self::$_tenants_model->get_tenants_by_domain_and_directory($domain, $directory))
         && count($tenants_arr) === 1
         && ($tenants_arr = array_values($tenants_arr))
         && !empty($tenants_arr[0]['id'])) {
            return $tenants_arr[0];
        }

        return null;
    }

    private static function _get_request_directory() : string
    {
        if (empty($_SERVER['REQUEST_URI'])
            || !($path_url = PHS_Utils::myparse_url($_SERVER['REQUEST_URI']))
            || empty($path_url['path'])
            || ($path = trim($path_url['path'], '/'))
            || $path === 'index.php') {
            return '';
        }

        if (substr($path, -9) === 'index.php') {
            $path = substr($path, 0, -9);
        }

        if (($path = trim($path, '/')) === '') {
            return '';
        }

        return $path;
    }

    private static function _get_tenant_identifier_from_headers() : ?string
    {
        $tenant_identifier = null;
        if (!empty($_SERVER['HTTP_'.self::HEADER_TENANT_IDENTIFIER])) {
            $tenant_identifier = trim($_SERVER['HTTP_'.self::HEADER_TENANT_IDENTIFIER]);
            PHS_Logger::debug('Tenant identifier ['.$tenant_identifier.'] from header HTTP_'.self::HEADER_TENANT_IDENTIFIER.' for ['.self::get_requested_script().']', PHS_Logger::TYPE_DEBUG);
        } elseif (!empty($_SERVER[self::HEADER_TENANT_IDENTIFIER])) {
            $tenant_identifier = trim($_SERVER[self::HEADER_TENANT_IDENTIFIER]);
            PHS_Logger::debug('Tenant identifier ['.$tenant_identifier.'] from header '.self::HEADER_TENANT_IDENTIFIER.' for ['.self::get_requested_script().']', PHS_Logger::TYPE_DEBUG);
        }

        return $tenant_identifier;
    }

    private static function _get_tenant_identifier_from_request() : ?string
    {
        $tenant_identifier = null;
        if (!empty($_POST[self::REQUEST_TENANT_IDENTIFIER])) {
            $tenant_identifier = trim($_POST[self::REQUEST_TENANT_IDENTIFIER]);
            PHS_Logger::debug('Tenant identifier ['.$tenant_identifier.'] from POST '.self::REQUEST_TENANT_IDENTIFIER.' for ['.self::get_requested_script().']', PHS_Logger::TYPE_DEBUG);
        } elseif (!empty($_GET[self::REQUEST_TENANT_IDENTIFIER])) {
            $tenant_identifier = trim($_GET[self::REQUEST_TENANT_IDENTIFIER]);
            PHS_Logger::debug('Tenant identifier ['.$tenant_identifier.'] from GET '.self::REQUEST_TENANT_IDENTIFIER.' for ['.self::get_requested_script().']', PHS_Logger::TYPE_DEBUG);
        } elseif (!empty($_COOKIE[self::REQUEST_TENANT_IDENTIFIER])) {
            $tenant_identifier = trim($_COOKIE[self::REQUEST_TENANT_IDENTIFIER]);
            PHS_Logger::debug('Tenant identifier ['.$tenant_identifier.'] from COOKIE '.self::REQUEST_TENANT_IDENTIFIER.' for ['.self::get_requested_script().']', PHS_Logger::TYPE_DEBUG);
        }

        return $tenant_identifier;
    }

    private static function _get_tenant_identifier() : ?string
    {
        if (($tenant_id = self::_get_tenant_identifier_from_headers())
            || ($tenant_id = self::_get_tenant_identifier_from_request())) {
            return $tenant_id;
        }

        return null;
    }

    private static function _load_dependencies() : bool
    {
        self::st_reset_error();

        if (empty(self::$_tenants_model)
            && !(self::$_tenants_model = PHS_Model_Tenants::get_instance())) {
            self::st_set_error(self::ERR_DEPENDENCIES, self::_t('Error loading required resources.'));

            return false;
        }

        return true;
    }
}
