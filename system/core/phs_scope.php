<?php

namespace phs;

use \phs\libraries\PHS_Instantiable;
use \phs\libraries\PHS_params;

abstract class PHS_Scope extends PHS_Instantiable
{
    const SCOPE_WEB = 1, SCOPE_AJAX = 2, SCOPE_API = 3;

    private static $SCOPES_ARR = array(
        self::SCOPE_WEB => array(
            'title' => 'Web',
            'plugin' => false,
            'class_name' => 'web',
            'front_template' => 'template_web_main',
            'admin_template' => 'template_web_admin',
        ),

        self::SCOPE_AJAX => array(
            'title' => 'Ajax',
            'plugin' => false,
            'class_name' => 'ajax',
            'front_template' => 'template_ajax_main',
            'admin_template' => 'template_ajax_admin',
        ),

        self::SCOPE_API => array(
            'title' => 'API',
            'plugin' => false,
            'class_name' => 'api',
            'front_template' => 'template_api_main',
        ),
    );

    const DEFAULT_SCOPE_KEY = 'default_scope', SCOPE_FLOW_KEY = 'scope_flow';

    abstract public function get_type();

    //! If there is a specific functionality which should extract parameters from request this is the method which will be called
    //! before asking scope for variables... (eg. API might send JSON structure for data, or some standard parameters in _GET
    //! Any scope can use self::_v() method to extract parameters from _GET, _POST, _FILE, etc
    //! This function will store main parameters in registry
    abstract public function extract_vars();

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

    /**
     * @param string $key Variable name
     * @param null|string $from (string with order of global arrays eg. 'gpev' check _GET, then _POST, then _ENV, then _SERVER)
     * @param int $type validation type for PHS_params (used in PHS_params::set_type()
     * @param bool $extra
     *
     * @return bool|null|string
     */
    public function _v( $key, $from = null, $type = PHS_params::T_ASIS, $extra = false )
    {
        if( ($val = self::get_data( $key )) !== null
         or $from !== null )
            return $val;

        if( is_string( $from ) )
            return PHS_params::_var( $from, $key, $type, $extra );

        return null;
    }

}
