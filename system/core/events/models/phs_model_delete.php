<?php
namespace phs\system\core\events\models;

use Closure;
use phs\libraries\PHS_Event;

class PHS_Event_Model_delete extends PHS_Event
{
    protected function _input_parameters() : array
    {
        return [
            'flow_params' => [],
            'record_data' => null,
            'model_obj'   => null,
        ];
    }

    protected function _output_parameters() : array
    {
        return [
            'record_data' => null,
        ];
    }

    public static function trigger_for_model(
        string $model_class,
        array $input_arr,
        array $params = [],
    ) : ?self {
        return self::trigger(
            $input_arr,
            $model_class,
            $params
        );
    }

    public static function listen_for_model(
        string $model_class,
        callable | array | string | Closure $callback,
        array $options = [],
    ) : ?self {
        return self::listen(
            $callback,
            $model_class,
            $options
        );
    }
}
