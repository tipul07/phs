<?php
namespace phs\system\core\events\emails;

use phs\libraries\PHS_Event;

class PHS_Event_Emails_send extends PHS_Event
{
    public function is_success() : bool
    {
        return (bool)($this->get_output('send_result') ?? false);
    }

    public function result_error() : ?array
    {
        $result_error = $this->get_output('result_error') ?: null;

        return is_array($result_error) ? self::validate_array($result_error, self::default_error_array()) : null;
    }

    /**
     * @inheritdoc
     */
    public function supports_background_listeners() : bool
    {
        return false;
    }

    protected function _input_parameters() : array
    {
        return [
            'force_language' => null,
            'to'             => '',
            'to_name'        => '',
            'from_name'      => '',
            'from_email'     => '',
            'reply_name'     => '',
            'reply_email'    => '',
            'subject'        => '',

            'with_priority'   => false,
            'custom_headers'  => [],
            'email_html_body' => '',
            'email_text_body' => '',

            'attachments' => [],
        ];
    }

    protected function _output_parameters() : array
    {
        return [
            'send_result'  => false,
            'result_error' => null,
        ];
    }
}
