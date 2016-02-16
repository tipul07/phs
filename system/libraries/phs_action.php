<?php
namespace phs\libraries;

use \phs\PHS;
use \phs\libraries\PHS_Controller;
use phs\PHS_Scope;

abstract class PHS_Action extends PHS_Signal_and_slot
{
    const SIGNAL_ACTION_BEFORE_RUN = 'action_before_run', SIGNAL_ACTION_AFTER_RUN = 'action_after_run';

    const ERR_CONTROLLER_INSTANCE = 30000;

    /** @var PHS_Controller */
    private $_controller_obj = null;

    /** @var array|null */
    private $_action_result = null;

    /**
     * @return bool|string Returns buffer which should be displayed as result of request or false on an error
     */
    abstract public function execute();

    public function __construct( $instance_details = false )
    {
        parent::__construct( $instance_details );

        $this->define_signal( self::SIGNAL_ACTION_BEFORE_RUN, array(
            'action_obj' => $this,
            'controller_obj' => $this->_controller_obj,
        ) );
        $this->define_signal( self::SIGNAL_ACTION_AFTER_RUN, array(
            'action_obj' => $this,
            'controller_obj' => $this->_controller_obj,
        ) );
    }

    protected function instance_type()
    {
        return self::INSTANCE_TYPE_ACTION;
    }

    public function init_view( $template, $theme = false, $view_class = false, $plugin = null )
    {
        if( $plugin === null )
            $plugin = $this->instance_plugin_name();

        if( !($view_obj = PHS::load_view( $view_class, $plugin )) )
        {
            $this->copy_static_error();
            return false;
        }

        if( !$view_obj->set_action( $this )
         or !$view_obj->set_controller( $this->get_controller() )
         or !$view_obj->set_theme( $theme )
         or !$view_obj->set_template( $template )
        )
        {
            $this->copy_error( $view_obj );
            return false;
        }

        return $view_obj;
    }

    static function default_action_result()
    {
        return array(
            'buffer' => '',
            'main_template' => '', // if empty, scope template will be used...
            'scope' => PHS_Scope::default_scope(),
        );
    }

    public function set_action_defaults()
    {
        $this->_action_result = self::default_action_result();
    }

    public function get_action_result()
    {
        return $this->_action_result;
    }

    public function set_action_result( $result )
    {
        $this->_action_result = self::validate_array( $result, self::default_action_result() );
        return $this->_action_result;
    }

    final public function run_action()
    {
        PHS::running_action( $this );

        $this->set_action_defaults();

        $default_result = self::default_action_result();

        if( ($signal_result = $this->signal_trigger( self::SIGNAL_ACTION_BEFORE_RUN, array(
                    'controller_obj' => $this->_controller_obj,
                ) )) )
        {
            if( !empty( $signal_result['stop_process'] ) )
            {
                if( $signal_result['replace_result'] !== null )
                {
                    $this->set_action_result( self::validate_array( $signal_result['replace_result'], $default_result ) );
                    return $this->get_action_result();
                }
            }
        }

        PHS::trigger_hooks( PHS_Hooks::H_BEFORE_ACTION_EXECUTE, array(
            'action' => $this,
        ) );

        if( !($action_result = $this->execute()) )
            return false;

        $this->set_action_result( $action_result );

        if( ($signal_result = $this->signal_trigger( self::SIGNAL_ACTION_AFTER_RUN, array(
            'controller_obj' => $this->_controller_obj,
        ) )) )
        {
            if( !empty( $signal_result['stop_process'] ) )
            {
                if( $signal_result['replace_result'] !== null )
                {
                    $this->set_action_result( self::validate_array( $signal_result['replace_result'], $default_result ) );
                    return $this->get_action_result();
                }
            }
        }

        PHS::trigger_hooks( PHS_Hooks::H_AFTER_ACTION_EXECUTE, array(
            'action' => $this,
        ) );

        if( ($plugin_instance = $this->get_plugin_instance()) )
        {
            echo 'Sets';
            var_dump( $plugin_instance->get_db_details() );
            var_dump( $plugin_instance->get_error() );
        }

        return $this->get_action_result();
    }

    public function set_controller( PHS_Controller $controller_obj )
    {
        if( !($controller_obj instanceof PHS_Controller) )
        {
            self::st_set_error( self::ERR_CONTROLLER_INSTANCE, self::_t( 'Controller doesn\'t appear to be a PHS instance.' ) );
            return false;
        }

        $this->_controller_obj = $controller_obj;

        return true;
    }

    public function get_controller()
    {
        return $this->_controller_obj;
    }

}
