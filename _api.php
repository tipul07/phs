<?php

header('Cache-Control: no-store, no-cache, must-revalidate');

header('Cache-Control: post-check=0, pre-check=0', false);

// HTTP/1.0

header('Pragma: no-cache');

define('PHS_PREVENT_SESSION', true);

define('PHS_SCRIPT_SCOPE', 'api');

include_once 'main.php';

use phs\PHS;
use phs\PHS_Api;
use phs\PHS_Api_base;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Params;

if (!PHS_Api::framework_allows_api_calls()) {
    PHS_Api::http_header_response(PHS_Api_base::H_CODE_SERVICE_UNAVAILABLE);
    exit;
}

if (!PHS::is_secured_request()
 && !PHS_Api::framework_allows_api_calls_over_http()) {
    PHS_Api::http_header_response(PHS_Api_base::H_CODE_SERVICE_UNAVAILABLE, 'Only connections over HTTPS are accepted.');
    exit;
}

$api_params = [];

$vars_from_get = [PHS_Api_base::PARAM_VERSION, PHS_Api_base::PARAM_API_ROUTE,
    PHS_Api_base::PARAM_USING_REWRITE, PHS_Api_base::PARAM_WEB_SIMULATION, ];

foreach ($vars_from_get as $key) {
    if (($val = PHS_Params::_g($key, PHS_Params::T_ASIS)) !== null) {
        $api_params[$key] = $val;
    }
}

if (!($api_obj = PHS_Api::api_factory($api_params))) {
    if (!PHS_Api::st_has_error()) {
        $error_msg = PHS_Api::st_get_error_message();
    } else {
        $error_msg = PHS_Api::_t('Unknown error.');
    }

    PHS_Logger::error('Error obtaining API instance: ['.$error_msg.']', PHS_Logger::TYPE_API);

    PHS_Api::generic_error($error_msg);
    exit;
}

if (!$api_obj->extract_api_request_details()) {
    if ($api_obj->has_error()) {
        $error_msg = $api_obj->get_simple_error_message();
    } else {
        $error_msg = $api_obj::_t('Unknow error.');
    }

    PHS_Api::generic_error($error_msg);
    exit;
}

$api_obj->set_api_credentials();

if (!($action_result = $api_obj->run_route())) {
    if ($api_obj->has_error()) {
        $error_msg = $api_obj->get_error_message();
    } else {
        $error_msg = PHS_Api::_t('Error running API request.');
    }

    PHS_Logger::error('Error running API route: ['.$error_msg.']', PHS_Logger::TYPE_API);

    PHS_Api::generic_error($error_msg);

    exit;
}

if (($debug_data = PHS::platform_debug_data())) {
    PHS_Logger::notice('API route ['.PHS::get_route_as_string().'] run with success: '.$debug_data['db_queries_count'].' queries, '
                      .' bootstrap: '.number_format($debug_data['bootstrap_time'], 6, '.', '').'s, '
                      .' running: '.number_format($debug_data['running_time'], 6, '.', '').'s', PHS_Logger::TYPE_API);
}
