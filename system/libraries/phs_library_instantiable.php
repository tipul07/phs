<?php
namespace phs\libraries;

abstract class PHS_Library_instantiable extends PHS_Instantiable
{
    public function instance_type() : string
    {
        return self::INSTANCE_TYPE_LIBRARY;
    }

    public static function is_core_library(?string $library_class = null) : bool
    {
        $library_class ??= static::class;

        return str_starts_with(strtolower(ltrim($library_class, '\\')), 'phs\\system\\core\\libraries\\');
    }
}
