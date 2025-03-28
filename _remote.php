<?php

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', false);
// HTTP/1.0
header('Pragma: no-cache');

const PHS_PREVENT_SESSION = true;

const PHS_SCRIPT_SCOPE = 'remote';

include_once 'main.php';

use phs\PHS;
use phs\PHS_Api;
use phs\PHS_Api_base;
use phs\PHS_Api_remote;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Params;
use phs\plugins\remote_phs\PHS_Plugin_Remote_phs;

if (!PHS_Api_remote::framework_allows_api_calls()) {
    PHS_Api_remote::http_header_response(PHS_Api_base::H_CODE_SERVICE_UNAVAILABLE);
    exit;
}

if (!PHS::is_secured_request()) {
    PHS_Api_remote::http_header_response(PHS_Api_base::H_CODE_SERVICE_UNAVAILABLE, 'Only connections over HTTPS are accepted.');
    exit;
}

/** @var PHS_Plugin_Remote_phs $remote_plugin */
if (!($remote_plugin = PHS_Plugin_Remote_phs::get_instance())
 || !$remote_plugin->is_accepting_remote_calls()) {
    PHS_Api_remote::http_header_response(PHS_Api_base::H_CODE_SERVICE_UNAVAILABLE, 'Service unavailable.');
    exit;
}

$api_params = [];

$vars_from_get = [PHS_Api_base::PARAM_VERSION, PHS_Api_base::PARAM_USING_REWRITE, PHS_Api_base::PARAM_WEB_SIMULATION, ];

foreach ($vars_from_get as $key) {
    if (($val = PHS_Params::_g($key, PHS_Params::T_ASIS)) !== null) {
        $api_params[$key] = $val;
    }
}

if (!($api_obj = PHS_Api_remote::api_factory($api_params))) {
    PHS_Logger::error('Error instantiating remote API.', PHS_Logger::TYPE_REMOTE);

    PHS_Api_remote::http_header_response(PHS_Api_base::H_CODE_INTERNAL_SERVER_ERROR, 'Error instantiating remote API.');
    exit;
}

if (!$api_obj->extract_api_request_details()) {
    PHS_Api::generic_error($api_obj->get_simple_error_message($api_obj::_t('Unknow error.')));
    exit;
}

$api_obj->set_api_credentials();

if (!($action_result = $api_obj->run_route())) {
    $error_msg = $api_obj->get_simple_error_message(PHS_Api::_t('Error running REMOTE request.'));
    $http_code = $api_obj::H_CODE_INTERNAL_SERVER_ERROR;

    switch (($error_no = $api_obj->get_error_code())) {
        case $api_obj::ERR_PARAMETERS:
            $http_code = $api_obj::H_CODE_BAD_REQUEST;
            break;

        case $api_obj::ERR_AUTHENTICATION:
            $http_code = $api_obj::H_CODE_UNAUTHORIZED;
            break;
    }

    PHS_Logger::error('Error running REMOTE route: ['.$error_no.': '.$error_msg.']', PHS_Logger::TYPE_REMOTE);

    PHS_Api_remote::http_header_response($http_code, $error_msg);
    exit;
}

if (($debug_data = PHS::platform_debug_data())) {
    PHS_Logger::notice('REMOTE route ['.PHS::get_route_as_string().'] run with success: '.$debug_data['db_queries_count'].' queries, '
                      .' bootstrap: '.number_format($debug_data['bootstrap_time'], 6, '.', '').'s, '
                      .' running: '.number_format($debug_data['running_time'], 6, '.', '').'s', PHS_Logger::TYPE_REMOTE);
}
