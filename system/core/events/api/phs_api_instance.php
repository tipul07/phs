<?php

namespace phs\system\core\events\api;

use phs\libraries\PHS_Event;

class PHS_Event_Api_instance extends PHS_Event
{
    /** @inheritdoc */
    public function supports_background_listeners() : bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    protected function _input_parameters(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     *
     * 'api_instance' should extend \phs\PHS_Api_base
     * @see \phs\PHS_Api_base
     */
    protected function _output_parameters(): array
    {
        return [
            'api_instance' => null,
        ];
    }
}
