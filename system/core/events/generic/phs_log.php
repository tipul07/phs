<?php

namespace phs\system\core\events\generic;

use phs\libraries\PHS_Event;

class PHS_Event_Log extends PHS_Event
{
    /**
     * @inheritdoc
     */
    protected function _auto_trigger_hook_name() : ?string
    {
        return 'phs_logger';
    }

    protected function _input_parameters() : array
    {
        return [
            'channel'            => '',
            'log_level'          => 0,
            'log_level_str'      => '',
            'log_file'           => '',
            'log_timestamp'      => '',
            'log_time'           => '',
            'request_identifier' => '',
            'request_ip'         => '',
            'str'                => '',
        ];
    }

    protected function _output_parameters() : array
    {
        return [
            // Populated if request_ip should be changed
            'request_ip' => '',
            // Populated if str should be changed
            'str' => '',
            // Do not log in default log file...
            'stop_logging' => false,
        ];
    }
}
