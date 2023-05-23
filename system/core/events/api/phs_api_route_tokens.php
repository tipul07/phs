<?php

namespace phs\system\core\events\api;

use phs\libraries\PHS_Event;

class PHS_Event_Api_route_tokens extends PHS_Event
{
    /** @inheritdoc */
    public function supports_background_listeners() : bool
    {
        return false;
    }

    /**
     * @inheritdoc
     *
     * 'api_instance' extends \phs\PHS_Api_base
     * @see \phs\PHS_Api_base
     */
    protected function _input_parameters(): array
    {
        return [
            'api_instance' => null,
            'route_tokens' => [],
        ];
    }

    /**
     * @inheritdoc
     */
    protected function _output_parameters(): array
    {
        return [
            'route_tokens' => [],
        ];
    }
}
