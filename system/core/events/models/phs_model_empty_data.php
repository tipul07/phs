<?php
namespace phs\system\core\events\models;

use Closure;
use phs\libraries\PHS_Event;

class PHS_Event_Model_empty_data extends PHS_Event
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
            'model_instance_id'  => '',
            'plugin_instance_id' => '',
            'flow_params'        => [],
            'data_arr'           => [],
            'model_obj'          => null,
        ];
    }

    protected function _output_parameters() : array
    {
        return [
            'data_arr' => [],
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
