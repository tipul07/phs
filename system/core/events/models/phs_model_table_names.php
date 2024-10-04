<?php

namespace phs\system\core\events\models;

use Closure;
use phs\libraries\PHS_Event;
use phs\libraries\PHS_Model_Core_base;

class PHS_Event_Model_table_names extends PHS_Event
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
        return [
            'instance_id' => '',
            'tables_arr'  => [],
        ];
    }

    protected function _output_parameters() : array
    {
        return [
            'tables_arr' => [],
        ];
    }

    public static function tables_for_instance_id(
        string $instance_id,
        array $tables_arr,
        array $params = [],
    ) : ?self {
        return self::trigger(
            [
                'instance_id' => $instance_id,
                'tables_arr'  => $tables_arr,
            ],
            params: $params
        );
    }
}
