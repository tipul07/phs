<?php

namespace phs;

use phs\libraries\PHS_Action;
use \phs\libraries\PHS_Instantiable;

abstract class PHS_Scope extends PHS_Instantiable
{
    const ERR_ACTION = 20000;

    const DEFAULT_SCOPE_KEY = 'default_scope', SCOPE_FLOW_KEY = 'scope_flow';

    const SCOPE_VAR_PREFIX = '__scp_pre_';

    const SCOPE_WEB = 1, SCOPE_BACKGROUND = 2, SCOPE_AJAX = 3, SCOPE_API = 4;

    private static $SCOPES_ARR = array(
        self::SCOPE_WEB => array(
            'title' => 'Web',
            'plugin' => false,
            'class_name' => 'web',
        ),

        self::SCOPE_BACKGROUND => array(
            'title' => 'Background',
            'plugin' => false,
            'class_name' => 'background',
        ),

        self::SCOPE_AJAX => array(
            'title' => 'Ajax',
            'plugin' => false,
            'class_name' => 'ajax',
        ),

        self::SCOPE_API => array(
            'title' => 'API',
            'plugin' => false,
            'class_name' => 'api',
        ),
    );

    abstract public function get_scope_type();

    abstract public function process_action_result( $action_result );

    protected function instance_type()
    {
        return self::INSTANCE_TYPE_SCOPE;
    }

    public static function get_scopes()
    {
        return self::$SCOPES_ARR;
    }

    public static function valid_scope( $scope )
    {
        $scope = intval( $scope );
        if( !($scopes_arr = self::get_scopes()) or empty( $scopes_arr[$scope] ) )
            return false;

        return $scopes_arr[$scope];
    }

    public static function default_scope_params()
    {
        return array(
            'title' => '',
            'plugin' => false,
            'class_name' => '',
            'front_template' => '',
            'admin_template' => '',
        );
    }

    public static function register_scope( array $scope_params )
    {
        self::st_reset_error();

        if( empty( $scope_params ) or !is_array( $scope_params )
         or !($scope_params = self::validate_array( $scope_params, self::default_scope_params() ))
         or empty( $scope_params['title'] )
         or empty( $scope_params['class_name'] ) )
        {
            self::_t( 'Invalid scope parameters.' );
            return false;
        }

        if( empty( $scope_params['plugin'] ) or $scope_params['plugin'] == PHS_Instantiable::CORE_PLUGIN )
            $scope_params['plugin'] = false;

        $scope_key = count( self::$SCOPES_ARR );

        self::$SCOPES_ARR[] = $scope_params;

        return array( 'scope_key' => $scope_key, 'scope_params' => $scope_params );
    }

    public static function default_scope( $scope = false )
    {
        if( $scope === false )
        {
            if( !($default_scope = self::get_data( self::DEFAULT_SCOPE_KEY )) )
            {
                self::set_data( self::DEFAULT_SCOPE_KEY, self::SCOPE_WEB );

                return self::SCOPE_WEB;
            }

            return $default_scope;
        }

        $scope = intval( $scope );
        if( !self::valid_scope( $scope ) )
            return false;

        self::set_data( self::DEFAULT_SCOPE_KEY, $scope );

        return $scope;
    }

    public static function current_scope( $scope = false )
    {
        if( $scope === false )
        {
            if( !($current_scope = self::get_data( self::SCOPE_FLOW_KEY )) )
                return self::default_scope();

            return $current_scope;
        }

        $scope = intval( $scope );
        if( !self::valid_scope( $scope ) )
            return false;

        self::set_data( self::SCOPE_FLOW_KEY, $scope );

        return $scope;
    }

    public static function spawn_scope_instance( $scope = null )
    {
        if( $scope === null )
            $scope = self::current_scope();

        if( !($scope_details = self::valid_scope( $scope ))
         or !($scope_instance = PHS::load_scope( $scope_details['class_name'], $scope_details['plugin'] )) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_INSTANCE, self::_t( 'Error spawning scope instance.' ) );

            return false;
        }

        return $scope_instance;
    }

    public static function get_scope_instance()
    {
        static $one_scope = false;

        if( empty( $one_scope ) )
            $one_scope = self::spawn_scope_instance();

        return $one_scope;
    }

    public function generate_response( $action_result = false )
    {
        /** @var \phs\libraries\PHS_Action $action_obj */
        if( !($action_obj = PHS::running_action()) )
            $action_obj = false;

        $default_action_result = PHS_Action::default_action_result();

        if( $action_result === false )
        {
            if( empty( $action_obj ) )
            {
                $action_result = $default_action_result;
                $action_result['buffer'] = self::_t( 'Unknown running action.' );
            } elseif( !($action_result = $action_obj->get_action_result()) )
            {
                $action_result = $default_action_result;
                $action_result['buffer'] = self::_t( 'Couldn\'t obtain action result.' );
            }
        }

        $action_result = self::validate_array( $action_result, $default_action_result );

        PHS::set_data( PHS::PHS_END_TIME, microtime( true ) );

        return $this->process_action_result( $action_result );
    }

}