<?php
namespace phs\system\core\events\emails;

use phs\libraries\PHS_Event;

class PHS_Event_Emails_settings extends PHS_Event
{
    /**
     * @inheritdoc
     */
    public function supports_background_listeners() : bool
    {
        return false;
    }

    protected function _input_parameters() : array
    {
        return [];
    }

    protected function _output_parameters() : array
    {
        return [
            'email_vars'          => [],
            'max_attachment_size' => 0,
        ];
    }

    public static function get_settings(?string $key = null) : ?array
    {
        if (($event_obj = self::trigger())) {
            return $event_obj->get_output($key);
        }

        return $key !== null ? null : [];
    }
}
