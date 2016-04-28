<?php
namespace phs\libraries;

use \phs\PHS;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Hooks;

abstract class PHS_Controller extends PHS_Signal_and_slot
{
    const ERR_RUN_ACTION = 40000;

    private $_action = false;

    public function instance_type()
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

        if( !$this->instance_is_core()
        and (!($plugin_instance = $this->get_plugin_instance())
                or !$plugin_instance->plugin_active()) )
        {
            $this->set_error( self::ERR_RUN_ACTION, self::_t( 'Unknown or not active controller.' ) );
            return false;
        }

        if( $plugin === null )
            $plugin = $this->instance_plugin_name();

        self::st_reset_error();

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

        if( !($action_result = $action_obj->run_action()) )
        {
            if( $action_obj->has_error() )
                $this->copy_error( $action_obj );
            else
                $this->set_error( self::ERR_RUN_ACTION, self::_t( 'Error executing action [%s].', $action ) );

            return false;
        }

        return $action_result;
    }

    /**
     * @param array|bool|false $action_result stop execution from controller level using a standard action, just to have nice display...
     *
     * @return bool|array Returns an action result array which was generated from controller...
     */
    public function execute_foobar_action( $action_result = false )
    {
        PHS::running_controller( $this );

        if( !$this->instance_is_core()
        and (!($plugin_instance = $this->get_plugin_instance())
                or !$plugin_instance->plugin_active()) )
        {
            $this->set_error( self::ERR_RUN_ACTION, self::_t( 'Unknown or not active controller.' ) );
            return false;
        }

        self::st_reset_error();

        /** @var \phs\system\core\actions\PHS_Action_Foobar $foobar_action_obj */
        if( !($foobar_action_obj = PHS::load_action( 'foobar' )) )
        {
            if( self::st_has_error() )
                $this->copy_static_error();
            else
                $this->set_error( self::ERR_RUN_ACTION, self::_t( 'Couldn\'t load foobar action.' ) );
            return false;
        }

        if( $action_result === false )
            $action_result = PHS_Action::default_action_result();

        $action_result = self::validate_array( $action_result, PHS_Action::default_action_result() );

        $foobar_action_obj->set_controller( $this );
        $foobar_action_obj->run_action();
        $foobar_action_obj->set_action_result( $action_result );

        return $action_result;
    }

}
