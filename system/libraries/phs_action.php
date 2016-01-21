<?php
namespace phs\libraries;

use \phs\PHS;
use \phs\libraries\PHS_Controller;

abstract class PHS_Action extends PHS_Signal_and_slot
{
    const SIGNAL_ACTION_BEFORE_RUN = 'action_before_run';

    const ERR_CONTROLLER_INSTANCE = 30000;

    /** @var PHS_Controller */
    private $_controller_obj = null;

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

    final public function run_action()
    {
        PHS::running_action( $this );

        if( ($signal_result = $this->signal_trigger( self::SIGNAL_ACTION_BEFORE_RUN, array(
                    'controller_obj' => $this->_controller_obj,
                ) )) )
        {
            if( !empty( $signal_result['stop_process'] ) )
            {
                if( $signal_result['replace_result'] !== null )
                    return $signal_result['replace_result'];
            }
        }

        if( !($action_buffer = $this->execute()) )
            return false;

        return $action_buffer;
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
