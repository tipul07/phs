<?php
namespace phs\libraries;

class PHS_Undefined_instantiable extends PHS_Instantiable
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
}
