<?php
namespace phs;

use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Registry;

class PHS_Ajax extends PHS_Registry
{
    public const ERR_RUN_JOB = 30000;

    public const PARAM_PUB_KEY = '_apk', PARAM_FB_KEY = '_phs_ajax_fb', PARAM_CHECK_SUM = '_achks';

    public const TIME_OFFSET = 1460000000;

    private static int $_ajax_checksum_timeout = 86400; // checksum will fail after one day...

    public static function checksum_timeout(?int $timeout = null) : int
    {
        if ($timeout === null) {
            return self::$_ajax_checksum_timeout;
        }

        self::$_ajax_checksum_timeout = $timeout;

        return self::$_ajax_checksum_timeout;
    }

    public static function url(null | bool | array $route_arr = null, null | bool | array $args = null, null | bool | array $extra = null) : string
    {
        $extra = $extra ?: [];
        $extra['for_scope'] = PHS_Scope::SCOPE_AJAX;

        return PHS::url($route_arr ?: [], self::get_ajax_validation_params($args ?: []), $extra);
    }

    public static function get_ajax_validation_params(array $args = []) : array
    {
        $args[self::PARAM_PUB_KEY] = time() - self::TIME_OFFSET;
        $args[self::PARAM_CHECK_SUM] = md5($args[self::PARAM_PUB_KEY].':'.PHS_Crypt::crypting_key());

        return $args;
    }

    public static function validate_input() : bool
    {
        if (!($pub_key = PHS_Params::_g(self::PARAM_PUB_KEY, PHS_Params::T_INT))
            || !($check_sum = PHS_Params::_g(self::PARAM_CHECK_SUM, PHS_Params::T_NOHTML))) {
            PHS_Logger::error('Required parameters not found.', PHS_Logger::TYPE_AJAX);

            return false;
        }

        $computed_checksum = md5($pub_key.':'.PHS_Crypt::crypting_key());

        $pub_key += self::TIME_OFFSET;

        if ($computed_checksum !== $check_sum
            || $pub_key + self::checksum_timeout() < time()) {
            if (self::st_debugging_mode()) {
                PHS_Logger::error('Checksum failed. ['.$computed_checksum.' != '.$check_sum.']', PHS_Logger::TYPE_AJAX);
            }

            return false;
        }

        return true;
    }

    public static function run_route() : bool | array
    {
        self::st_reset_error();

        if (!PHS_Scope::current_scope(PHS_Scope::SCOPE_AJAX)) {
            self::st_set_error_if_not_set(self::ERR_RUN_JOB, self::_t('Error preparing environment.'));

            return false;
        }

        if (!($action_result = PHS::execute_route(['die_on_error' => false]))) {
            self::st_set_error_if_not_set(self::ERR_RUN_JOB,
                self::_t('Error executing route [%s].', PHS::get_route_as_string()));

            return false;
        }

        return $action_result;
    }
}
