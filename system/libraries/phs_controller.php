<?php
namespace phs\libraries;

use \phs\PHS;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Hooks;

abstract class PHS_Controller extends PHS_Signal_and_slot
{
    const ERR_RUN_ACTION = 30000;

    private $_action = false;

    /**
     * @return array An array of strings which are the models used by this controller
     */
    abstract public function get_models();

    protected function instance_type()
    {
        return self::INSTANCE_TYPE_CONTROLLER;
    }

    public function get_action()
    {
        return $this->_action;
    }

    /**
     * @param string $action Action to be loaded and executed
     * @param null|bool|string $plugin NULL means same plugin as controller (default), false means core plugin, string is name of plugin
     *
     * @return bool|
     */
    public function execute_action( $action, $plugin = null )
    {
        PHS::running_controller( $this );

        if( $plugin === null )
            $plugin = $this->instance_plugin_name();

        /** @var \phs\libraries\PHS_Action $action_obj */
        if( !($action_obj = PHS::load_action( $action, $plugin )) )
        {
            if( self::st_has_error() )
                $this->copy_static_error();
            else
                $this->set_error( self::ERR_RUN_ACTION, self::_t( 'Couldn\'t load action [%s].', $action ) );
            return false;
        }

        $action_obj->set_controller( $this );

        $action_result = $action_obj->run_action();

        if( ($plugin_instance = $this->get_plugin_instance()) )
        {
            var_dump( $plugin_instance->get_db_details() );
        }

        return $action_result['buffer'];
    }

}
