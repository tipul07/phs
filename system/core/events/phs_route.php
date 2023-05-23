<?php

namespace phs\system\core\events;

use phs\libraries\PHS_Event;

class PHS_Event_Route extends PHS_Event
{
    /** @inheritdoc */
    public function supports_background_listeners() : bool
    {
        return false;
    }

    protected function _input_parameters(): array
    {
        return [
            'route' => [],
        ];
    }

    /**
     * @inheritdoc
     * @see \phs\PHS::validate_route_from_parts()
     * @return array[]
     */
    protected function _output_parameters(): array
    {
        return [
            'route' => [],
        ];
    }
}
