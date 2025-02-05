<?php

@set_time_limit(0);

header('Cache-Control: no-store, no-cache, must-revalidate');

header('Cache-Control: post-check=0, pre-check=0', false);

// HTTP/1.0

header('Pragma: no-cache');

const PHS_PREVENT_SESSION = true;
const PHS_SCRIPT_SCOPE = 'background';

include_once 'main.php';

use phs\PHS;
use phs\PHS_Bg_jobs;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Logger;

PHS_Logger::notice(' --- Started bg job...', PHS_Logger::TYPE_BACKGROUND);

if (!($parsed_input = PHS_Bg_jobs::bg_validate_input($_SERVER['argv'][1] ?? ''))
 || empty($parsed_input['job_data'])) {
    PHS_Logger::error('INVALID job input.', PHS_Logger::TYPE_BACKGROUND);
    exit;
}

$job_arr = $parsed_input['job_data'];

if (!($action_result = PHS_Bg_jobs::bg_run_job($job_arr))) {
    PHS_Logger::error('Error running job [#'.$job_arr['id'].'] ('.$job_arr['route'].')', PHS_Logger::TYPE_BACKGROUND);
    PHS_Logger::error('Job error: '.PHS_Bg_jobs::st_get_error_message(PHS_Bg_jobs::_t('Unknown error.')), PHS_Logger::TYPE_BACKGROUND);
} elseif (($debug_data = PHS::platform_debug_data())) {
    PHS_Logger::notice('Job #'.$job_arr['id'].' ('.$job_arr['route'].') run with success: '.$debug_data['db_queries_count'].' queries, '
                      .' bootstrap: '.number_format($debug_data['bootstrap_time'], 6, '.', '').'s, '
                      .' running: '.number_format($debug_data['running_time'], 6, '.', '').'s', PHS_Logger::TYPE_BACKGROUND);
}

PHS_Logger::notice(' --- Background script finish', PHS_Logger::TYPE_BACKGROUND);

if (!empty($action_result)) {
    $action_result = PHS_Action::validate_action_result($action_result);
    if (!empty($job_arr['return_buffer'])) {
        echo @json_encode($action_result);
    }
}
