<?php

namespace phs;

use phs\libraries\PHS_Logger;
use phs\graphql\libraries\PHS_Graphql;
use phs\plugins\admin\PHS_Plugin_Admin;

class PHS_Api_graphql extends PHS_Api_base
{
    public const ERR_API_INIT = 40000, ERR_REQUEST = 40001;

    private static ?PHS_Api_graphql $_api_obj = null;

    public function __construct(?array $init_query_params = null)
    {
        parent::__construct();

        if ($init_query_params !== null
            && !($this->_init_api_query_params($init_query_params))
            && !$this->has_error()) {
            $this->set_error(self::ERR_API_INIT, self::_t('Couldn\'t initialize GraphQL API object.'));
        }
    }

    final public function run_route(array $extra = []) : ?array
    {
        $this->reset_error();

        if (!PHS_Scope::current_scope(PHS_Scope::SCOPE_GRAPHQL)) {
            $this->set_error(self::ERR_RUN_ROUTE, self::_t('Error preparing API environment.'));

            return null;
        }

        // Check if we have authentication...
        if (!$this->_check_api_authentication()) {
            $this->set_error_if_not_set(self::ERR_AUTHENTICATION, self::_t('Authentication failed.'));

            return null;
        }

        if (!PHS_Graphql::resolve_request()) {
            $this->copy_or_set_static_error(self::ERR_RUN_ROUTE,
                self::_t('Error resolving request.', PHS::get_route_as_string()));

            return null;
        }

        return ['junk_response' => true];
    }

    /**
     * @return bool Returns true if custom authentication is ok or false if authentication failed
     */
    protected function _check_api_authentication() : bool
    {
        $this->reset_error();

        if (($authentication_failed = $this->_basic_api_authentication_failed())) {
            $this->set_error_if_not_set(self::ERR_AUTHENTICATION, self::_t('Authentication failed.'));

            if (!$this->send_header_response(
                $authentication_failed['http_code'] ?? self::H_CODE_UNAUTHORIZED,
                $authentication_failed['error_msg'] ?? self::_t('Authentication failed.')
            )) {
                return false;
            }

            exit;
        }

        return true;
    }

    final public static function framework_allows_graphql_calls() : bool
    {
        static $allow_graphql_calls = null;

        if ($allow_graphql_calls !== null) {
            return $allow_graphql_calls;
        }

        $allow_graphql_calls = PHS_Plugin_Admin::get_instance()?->get_plugin_settings()['allow_graphql_calls'] ?? false;

        return $allow_graphql_calls;
    }

    final public static function api_factory(array $init_query_params = []) : ?self
    {
        self::st_reset_error();

        if (!empty(self::$_api_obj)) {
            return self::$_api_obj;
        }

        if (!($api_obj = new self($init_query_params))) {
            self::st_set_error(self::ERR_API_INIT, self::_t('Error obtaining GraphQL API instance.'));

            return null;
        }

        self::$_api_obj = $api_obj;

        return self::$_api_obj;
    }
}
