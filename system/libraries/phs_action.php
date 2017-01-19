<?php
namespace phs\libraries;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Controller;
use \phs\system\core\views\PHS_View;

abstract class PHS_Action extends PHS_Signal_and_slot
{
    const ERR_CONTROLLER_INSTANCE = 40000, ERR_RUN_ACTION = 40001, ERR_RENDER = 40002, ERR_SCOPE = 40003;

    const SIGNAL_ACTION_BEFORE_RUN = 'action_before_run', SIGNAL_ACTION_AFTER_RUN = 'action_after_run';

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

        if( !$this->signal_defined( self::SIGNAL_ACTION_BEFORE_RUN ) )
        {
            $this->define_signal( self::SIGNAL_ACTION_BEFORE_RUN, array(
                'action_obj' => $this,
                'controller_obj' => $this->_controller_obj,
            ) );
        }

        if( !$this->signal_defined( self::SIGNAL_ACTION_AFTER_RUN ) )
        {
            $this->define_signal( self::SIGNAL_ACTION_AFTER_RUN, array(
                'action_obj' => $this,
                'controller_obj' => $this->_controller_obj,
            ) );
        }
    }

    public function instance_type()
    {
        return self::INSTANCE_TYPE_ACTION;
    }

    /**
     * Returns an array of scopes in which action is allowed to run
     *
     * @return array If empty array, action is allowed in all scopes...
     */
    public function allowed_scopes()
    {
        return array();
    }

    /**
     * @param int $scope Scope to be checked
     *
     * @return bool Returns true if controller is allowed to run in provided scope
     */
    public function scope_is_allowed( $scope )
    {
        $this->reset_error();

        if( !PHS_Scope::valid_scope( $scope ) )
        {
            $this->set_error( self::ERR_SCOPE, self::_t( 'Invalid scope.' ) );
            return false;
        }

        if( ($allowed_scopes = $this->allowed_scopes())
        and is_array( $allowed_scopes )
        and !in_array( $scope, $allowed_scopes ) )
            return false;

        return true;
    }

    static function default_action_result()
    {
        return array(
            // Action "content"
            'buffer' => '',
            'ajax_result' => false,
            'ajax_only_result' => false,
            'custom_headers' => array(), // key - value headers...

            'redirect_to_url' => '', // any URLs that we should redirect to (we might have to do javascript redirect or header redirect)
            'page_template' => 'template_main', // if empty, scope template will be used...

            // page related variables
            'page_settings' => PHS::get_default_page_settings(),
            // anything that is required as attributes to body tag
            'page_body_extra_tags' => '',

            // false means use current scope
            'scope' => false,
        );
    }

    public function set_action_defaults()
    {
        $this->_action_result = self::default_action_result();
    }

    /**
     * @return array|null
     */
    public function get_action_result()
    {
        return $this->_action_result;
    }

    public function set_action_result( $result )
    {
        $this->_action_result = self::validate_array( $result, self::default_action_result() );
        return $this->_action_result;
    }

    final public function quick_render_template( $template, $template_data = false )
    {
        $this->reset_error();

        $view_params = array();
        $view_params['action_obj'] = $this;
        $view_params['controller_obj'] = $this->get_controller();
        $view_params['parent_plugin_obj'] = $this->get_plugin_instance();
        $view_params['plugin'] = $this->instance_plugin_name();
        $view_params['template_data'] = $template_data;

        if( !($view_obj = PHS_View::init_view( $template, $view_params )) )
        {
            if( self::st_has_error() )
                $this->copy_static_error();

            return false;
        }

        $action_result = self::default_action_result();

        if( ($action_result['buffer'] = $view_obj->render()) === false )
        {
            if( $view_obj->has_error() )
                $this->copy_error( $view_obj );
            else
                $this->set_error( self::ERR_RENDER, self::_t( 'Error rendering template [%s].', $view_obj->get_template() ) );

            return false;
        }

        if( empty( $action_result['buffer'] ) )
            $action_result['buffer'] = '';

        return $action_result;
    }

    /**
     * @return array|bool|null
     */
    final public function run_action()
    {
        PHS::running_action( $this );

        $action_body_classes = '';
        if( ($route_as_string = PHS::get_route_as_string()) )
            $action_body_classes .= str_replace( array( '/', '-' ), '_', $route_as_string );
        if( ($route_as_array = PHS::get_route_details()) )
        {
            $route_parts = array();
            foreach( $route_as_array as $part_type => $part_value )
                $route_parts[$part_value] = true;

            $action_body_classes .= ' '.implode( ' ', array_keys( $route_parts ) );
        }

        if( !empty( $action_body_classes ) )
            PHS::page_body_class( $action_body_classes );

        if( !$this->instance_is_core()
        and (!($plugin_instance = $this->get_plugin_instance())
                or !$plugin_instance->plugin_active()) )
        {
            $this->set_error( self::ERR_RUN_ACTION, self::_t( 'Unknown or not active action.' ) );
            return false;
        }

        $this->set_action_defaults();

        $default_result = self::default_action_result();

        if( ($allowed_scopes = $this->allowed_scopes())
        and is_array( $allowed_scopes )
        and ($current_scope = PHS_Scope::current_scope())
        and !in_array( $current_scope, $allowed_scopes ) )
        {
            $this->set_error( self::ERR_RUN_ACTION, self::_t( 'Action not allowed to run in current scope.' ) );
            return false;
        }

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

        $hook_args = PHS_Hooks::default_action_execute_hook_args();
        $hook_args['action_obj'] = $this;
        $hook_args['action_result'] = self::default_action_result();

        PHS::trigger_hooks( PHS_Hooks::H_BEFORE_ACTION_EXECUTE, $hook_args );

        self::st_reset_error();

        if( !($action_result = $this->execute()) )
            return false;

        $action_result = self::validate_array( $action_result, self::default_action_result() );

        if( ($page_settings = self::validate_array( PHS::page_settings(), PHS::get_default_page_settings() )) )
        {
            if( empty( $action_result['page_settings'] ) or !is_array( $action_result['page_settings'] ) )
                $action_result['page_settings'] = $page_settings;

            else
            {
                foreach( $page_settings as $key => $val )
                {
                    if( !empty( $val )
                    and (!array_key_exists( $key, $action_result['page_settings'] )
                            or empty( $action_result['page_settings'][$key] )) )
                        $action_result['page_settings'][$key] = $val;
                }
            }
        }

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

        $hook_args = PHS_Hooks::default_action_execute_hook_args();
        $hook_args['action_obj'] = $this;
        $hook_args['action_result'] = $action_result;

        if( ($hook_args = PHS::trigger_hooks( PHS_Hooks::H_AFTER_ACTION_EXECUTE, $hook_args ))
        and is_array( $hook_args ) )
        {
            if( !empty( $hook_args['action_result'] ) )
                $action_result = $hook_args['action_result'];
        }

        $this->set_action_result( $action_result );

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
