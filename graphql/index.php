<?php

@header('Cache-Control: no-store, no-cache, must-revalidate');
@header('Cache-Control: post-check=0, pre-check=0', false);
// HTTP/1.0
@header('Pragma: no-cache');

const PHS_PREVENT_SESSION = true;

const PHS_SCRIPT_SCOPE = 'graphql';

include_once '../main.php';

const PHS_GRAPHQL_LIBRARIES_DIR = PHS_GRAPHQL_DIR.'libraries/';

include_once PHS_CORE_DIR.'phs_api_graphql.php';
include_once PHS_GRAPHQL_LIBRARIES_DIR.'phs_graphql.php';

use phs\PHS;
use phs\PHS_Api;
use phs\PHS_Api_base;
use phs\PHS_Api_graphql;
use phs\libraries\PHS_Logger;
use phs\system\core\models\PHS_Model_Api_monitor;

if (!PHS_Api_graphql::framework_allows_graphql_calls()) {
    PHS_Api_graphql::http_header_response(PHS_Api_base::H_CODE_SERVICE_UNAVAILABLE);
    exit;
}

if (!PHS::is_secured_request()
    && !PHS_Api_graphql::framework_allows_api_calls_over_http()) {
    PHS_Api_graphql::http_header_response(PHS_Api_base::H_CODE_SERVICE_UNAVAILABLE, 'Only connections over HTTPS are accepted.');
    exit;
}

if (!($api_obj = PHS_Api_graphql::api_factory())) {
    $error_msg = PHS_Api_graphql::st_get_simple_error_message(PHS_Api_graphql::_t('Unknown error.'));

    PHS_Logger::error('Error obtaining API instance: ['.$error_msg.']', PHS_Logger::TYPE_GRAPHQL);

    PHS_Model_Api_monitor::graphql_request_error('Error obtaining GraphQL API instance: '.$error_msg);

    PHS_Api_graphql::generic_error($error_msg);
    exit;
}

if (!($input = PHS_Api::get_request_body_as_json_array())
    || empty($input['query'])) {
    $error_msg = $api_obj->get_simple_error_message($api_obj::_t('Invalid GraphQL input.'));

    PHS_Model_Api_monitor::graphql_request_error('Invalid GraphQL input. query is not provided.');

    PHS_Api_graphql::generic_error($error_msg);
    exit;
}

if (!$api_obj->extract_api_request_details()) {
    $error_msg = $api_obj->get_simple_error_message($api_obj::_t('Unknow error.'));

    PHS_Model_Api_monitor::graphql_request_error('Error initializing API: '.$error_msg);

    PHS_Api_graphql::generic_error($error_msg);
    exit;
}

$api_obj->set_api_credentials();

PHS_Api::incoming_monitoring_record(PHS_Model_Api_monitor::graphql_request_started());

if (!($result = $api_obj->run_route($input))) {
    $error_msg = $api_obj->get_simple_error_message(PHS_Api_graphql::_t('Error running GraphQL request.'));

    PHS_Logger::error('Error running GraphQL: ['.$error_msg.']', PHS_Logger::TYPE_GRAPHQL);

    PHS_Model_Api_monitor::graphql_request_error('Error resolving GraphQL request: '.$error_msg);

    PHS_Api_graphql::generic_error($error_msg);
} else {
    $response_body = null;
    if (!empty($result['output_arr'])) {
        $response_body = @json_encode($result['output_arr']) ?: null;
    }

    if (!$api_obj->process_result($result)) {
        $error_msg = $api_obj->get_simple_error_message(PHS_Api_graphql::_t('Error processing GraphQL result.'));

        PHS_Logger::error('Error processing GraphQL result: ['.$error_msg.']', PHS_Logger::TYPE_GRAPHQL);

        PHS_Model_Api_monitor::graphql_request_error(
            'Error processing GraphQL result: '.$error_msg,
            response_body: $response_body
        );

        PHS_Api_graphql::generic_error($error_msg);
    } else {
        PHS_Model_Api_monitor::graphql_request_success($response_body);

        if (($debug_data = PHS::platform_debug_data())) {
            PHS_Logger::notice('GraphQL ['.$api_obj->http_method().'] run with success: '.$debug_data['db_queries_count'].' queries, '
                               .' bootstrap: '.number_format($debug_data['bootstrap_time'], 6, '.', '').'s, '
                               .' running: '.number_format($debug_data['running_time'], 6, '.', '').'s',
                PHS_Logger::TYPE_GRAPHQL);
        }
    }
}
