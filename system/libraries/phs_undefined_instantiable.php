<?php
namespace phs\libraries;

use Closure;

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

    /**
     * @inheritdoc
     */
    // region Event methods
    public static function listen(callable | array | string | Closure $callback, string $event_prefix = '', array $options = []) : ?self
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public static function listen_in_background(callable | array | string $callback, string $event_prefix = '', array $options = []) : ?self
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public static function trigger(array $input = [], string $event_prefix = '', array $params = []) : ?self
    {
        return null;
    }
    // endregion Event methods
}
