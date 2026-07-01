#!/usr/bin/php
<?php

@set_time_limit(0);

const PHS_PREVENT_SESSION = true;
const PHS_SCRIPT_SCOPE = 'inmail';

include_once 'main.php';

use phs\PHS;
use phs\libraries\PHS_Logger;
use phs\plugins\phs_inmail\PHS_Plugin_Phs_inmail;
use phs\plugins\phs_inmail\events\PHS_Event_Inmail_new;
use phs\plugins\phs_inmail\libraries\PHS_Inmail_parser;

if (!($inmail_plugin = PHS_Plugin_Phs_inmail::get_instance())
   || !$inmail_plugin->plugin_active()
   || !$inmail_plugin->is_inmail_enabled()) {
    exit(254);
}

if (!($inmail_lib = PHS_Inmail_parser::get_instance())) {
    PHS_Logger::error('Error loading required resources.', $inmail_plugin::LOG_CHANNEL);

    exit(254);
}

if ((!$email_buf = PHS::get_php_stdin() ?? PHS::get_php_input())) {
    $input_file = $_SERVER['argv'][1] ?? $argv[1] ?? null;

    if ($input_file) {
        PHS_Logger::notice('Reading input file: '.$input_file, $inmail_plugin::LOG_CHANNEL);
        if (!@file_exists($input_file) || @is_readable($input_file)
           || !($email_buf = @file_get_contents($input_file))) {
            PHS_Logger::error('Error reading file content.', $inmail_plugin::LOG_CHANNEL);
        }
    }

    if (!$email_buf) {
        PHS_Logger::error('Could not obtain email buffer.', $inmail_plugin::LOG_CHANNEL);
        exit(254);
    }
}

PHS_Logger::notice(' --- Started InMail check', $inmail_plugin::LOG_CHANNEL);

if (!$inmail_lib->check_incoming_email_from_buffer($email_buf)) {
    PHS_Logger::error('Error parsing incoming email: '
                      .$inmail_lib->get_simple_error_message(),
        $inmail_plugin::LOG_CHANNEL
    );
}

if (($debug_data = PHS::platform_debug_data())) {
    PHS_Logger::notice(
        'DEBUG data: '.$debug_data['db_queries_count'].' queries,'
        .' bootstrap: '.number_format($debug_data['bootstrap_time'], 6, '.', '').'s,'
        .' running: '.number_format($debug_data['running_time'], 6, '.', '').'s',
        $inmail_plugin::LOG_CHANNEL
    );
}

PHS_Logger::notice(' --- InMail script finish', $inmail_plugin::LOG_CHANNEL);
