<?php
namespace phs\libraries;

use phpDocumentor\Reflection\Types\Null_;

class PHS_Undefined_instantiable extends PHS_Instantiable implements PHS_Event_interface
{
    public function __construct()
    {
        $this->_do_construct();
    }

    public function instance_type() : string
    {
        return self::INSTANCE_TYPE_UNDEFINED;
    }

    public static function get_instance(bool $as_singleton = true, ?string $full_class_name = null)
    {
        return null;
    }

    // region Event methods
    public static function listen($callback, array $options = []) : ?self
    {
        return null;
    }

    public static function listen_in_background($callback, array $options = []) : ?self
    {
        return null;
    }

    public static function trigger(array $input = [], array $params = [], string $event_prefix = '') : ?self
    {
        return null;
    }
    // endregion Event methods
}
