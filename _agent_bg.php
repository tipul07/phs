<?php

@set_time_limit(0);

header('Cache-Control: no-store, no-cache, must-revalidate');

header('Cache-Control: post-check=0, pre-check=0', false);

// HTTP/1.0

header('Pragma: no-cache');

define('PHS_PREVENT_SESSION', true);

define('PHS_SCRIPT_SCOPE', 'agent');

include_once 'main.php';

use phs\PHS;
use phs\PHS_Agent;
use phs\libraries\PHS_Logger;

PHS_Logger::notice(' --- Started agent bg job...', PHS_Logger::TYPE_AGENT);

$input = '';

if (!empty($_SERVER['argv']) && is_array($_SERVER['argv']) && !empty($_SERVER['argv'][1])) {
    $input = $_SERVER['argv'][1];
}

if (!($parsed_input = PHS_Agent::bg_validate_input($input))
 || empty($parsed_input['job_data'])) {
    PHS_Logger::error('INVALID job input', PHS_Logger::TYPE_AGENT);
    exit;
}

$job_arr = $parsed_input['job_data'];

$run_job_extra = [];
$run_job_extra['agent_jobs_model'] = (!empty($parsed_input['agent_jobs_model']) ? $parsed_input['agent_jobs_model'] : false);
$run_job_extra['force_run'] = (!empty($parsed_input['force_run']));

if (!($run_result = PHS_Agent::bg_run_job($job_arr, $run_job_extra))) {
    PHS_Logger::error('Error running agent job [#'.$job_arr['id'].'] ('.$job_arr['route'].')', PHS_Logger::TYPE_AGENT);

    if (PHS_Agent::st_has_error()) {
        PHS_Logger::error('Job error: ['.PHS_Agent::st_get_error_message().']', PHS_Logger::TYPE_AGENT);
    }
} elseif (($debug_data = PHS::platform_debug_data())) {
    PHS_Logger::notice('Agent Job #'.$job_arr['id'].' ('.$job_arr['route'].') run with success: '.$debug_data['db_queries_count'].' queries, '
                      .' bootstrap: '.number_format($debug_data['bootstrap_time'], 6, '.', '').'s, '
                      .' running: '.number_format($debug_data['running_time'], 6, '.', '').'s', PHS_Logger::TYPE_AGENT);
}

PHS_Logger::notice(' --- Agent background script finish', PHS_Logger::TYPE_AGENT);
