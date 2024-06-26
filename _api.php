<?php

@header('Cache-Control: no-store, no-cache, must-revalidate');
@header('Cache-Control: post-check=0, pre-check=0', false);
// HTTP/1.0
@header('Pragma: no-cache');

const PHS_PREVENT_SESSION = true;

const PHS_SCRIPT_SCOPE = 'api';

include_once 'main.php';

use phs\PHS;
use phs\PHS_Api;
use phs\PHS_Api_base;
use phs\libraries\PHS_Utils;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Params;
use phs\system\core\models\PHS_Model_Api_monitor;

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
    $error_msg = PHS_Api::st_get_simple_error_message(PHS_Api::_t('Unknown error.'));

    PHS_Logger::error('Error obtaining API instance: ['.$error_msg.']', PHS_Logger::TYPE_API);

    PHS_Model_Api_monitor::api_incoming_request_direct_error(
        PHS_Api_base::GENERIC_ERROR_CODE, 'Error obtaining API instance: '.$error_msg
    );

    PHS_Api::generic_error($error_msg);
    exit;
}

if (!$api_obj->extract_api_request_details()) {
    $error_msg = $api_obj->get_simple_error_message($api_obj::_t('Unknow error.'));

    PHS_Model_Api_monitor::api_incoming_request_direct_error(
        PHS_Api_base::GENERIC_ERROR_CODE, 'Error initializing API: '.$error_msg
    );

    PHS_Api::generic_error($error_msg);
    exit;
}

if (PHS_Api::framework_allow_cors_api_calls()) {
    if (('' === ($origin_response = PHS_Api::framework_cors_origins()))
        && ($request_origin = $_SERVER['HTTP_ORIGIN'] ?? null)
        && ($origin_details = PHS_Utils::myparse_url($request_origin))
        && !empty($origin_details['host'])) {
        $origin_response = $origin_details['host'];
    }

    if ($origin_response !== '') {
        @header('Access-Control-Allow-Origin: '.$origin_response);
    }
    if ('' !== ($cors_methods = PHS_Api::framework_cors_methods())) {
        @header('Access-Control-Allow-Methods: '.$cors_methods);
    }
    if ('' !== ($cors_headers = PHS_Api::framework_cors_headers())) {
        @header('Access-Control-Allow-Headers: '.$cors_headers);
    }
    if (-1 !== ($cors_max_age = PHS_Api::framework_cors_max_age())) {
        @header('Access-Control-Max-Age: '.$cors_max_age);
    }

    if ($api_obj->http_method() === 'options') {
        PHS_Api_base::http_header_response(PHS_Api_base::H_CODE_OK_NO_CONTENT);

        if (PHS_Api::framework_monitor_cors_options_calls()) {
            PHS_Api::incoming_monitoring_record(PHS_Model_Api_monitor::api_incoming_request_started());
            PHS_Model_Api_monitor::api_incoming_request_success(PHS_Api_base::H_CODE_OK_NO_CONTENT);
        }

        exit;
    }
}

$api_obj->set_api_credentials();

PHS_Api::incoming_monitoring_record(PHS_Model_Api_monitor::api_incoming_request_started());

if (!($action_result = $api_obj->run_route())) {
    $error_msg = $api_obj->get_simple_error_message(PHS_Api::_t('Error running API request.'));

    PHS_Logger::error('Error running API route: ['.$error_msg.']', PHS_Logger::TYPE_API);

    PHS_Model_Api_monitor::api_incoming_request_error(
        PHS_Api_base::GENERIC_ERROR_CODE, 'Error running API route: '.$error_msg
    );

    PHS_Api::generic_error($error_msg);

    exit;
}

if (($debug_data = PHS::platform_debug_data())) {
    PHS_Logger::notice('API route ['.$api_obj->http_method().'] ['.PHS::get_route_as_string().'] run with success: '.$debug_data['db_queries_count'].' queries, '
                      .' bootstrap: '.number_format($debug_data['bootstrap_time'], 6, '.', '').'s, '
                      .' running: '.number_format($debug_data['running_time'], 6, '.', '').'s', PHS_Logger::TYPE_API);
}
