<?php
namespace phs\libraries;

abstract class PHS_Plugin extends PHS_Signal_and_slot
{
    protected function instance_type()
    {
        return self::INSTANCE_TYPE_PLUGIN;
    }
}
