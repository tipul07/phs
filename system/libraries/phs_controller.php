<?php
namespace phs\libraries;

abstract class PHS_Controller extends PHS_Signal_and_slot
{
    /**
     * @return array An array of strings which are the models used by this controller
     */
    abstract public function get_models();

    protected function instance_type()
    {
        return self::INSTANCE_TYPE_CONTROLLER;
    }

}
