<?php

namespace phs\libraries;

use \phs\PHS_Scope;
use phs\PHS_Api_remote;
use \phs\libraries\PHS_Params;

abstract class PHS_Remote_action extends PHS_Api_action
{
    /** @var bool|\phs\PHS_Api_base $api_obj */
    protected $api_obj = false;

    /**
     * Returns an array of scopes in which action is allowed to run
     *
     * @return array If empty array, action is allowed in all scopes...
     */
    public function allowed_scopes()
    {
        return [ PHS_Scope::SCOPE_REMOTE ];
    }

    /**
     * @return bool|\phs\PHS_Api_base
     */
    public function get_action_api_instance()
    {
        if( PHS_Scope::current_scope() === PHS_Scope::SCOPE_REMOTE
         && !$this->api_obj )
            $this->api_obj = PHS_Api_remote::api_factory();

        return $this->api_obj;
    }

    /**
     * @param string $var_name What variable are we looking for
     * @param int $type Type used for value validation
     * @param mixed $default Default value if variable not found in request
     * @param false|array $type_extra Extra params used in variable validation
     * @param string $order Order in which we will do the checks (b - request body as JSON, g - get, p - post)
     *
     * @return mixed
     */
    public function request_var( $var_name, $type = PHS_Params::T_ASIS, $default = null, $type_extra = false, $order = 'bpg' )
    {
        static $json_request = null;

        if( $json_request === null )
        {
            if( !($api_obj = $this->get_action_api_instance())
             || !($json_request = $api_obj->api_flow_value( 'remote_domain_message' ))
             || !is_array( $json_request )
             || empty( $json_request['request_arr'] ) || !is_array( $json_request['request_arr'] ) )
                $json_request = [];
        }

        if( !is_string( $order )
         || $order === '' ) {
            return $default;
        }

        $val = null;
        while( ($ch = substr( $order, 0, 1 )) )
        {
            switch( strtolower( $ch ) )
            {
                case 'b':
                    if( !empty( $json_request ) && is_array( $json_request )
                     && isset( $json_request[$var_name] ) ) {
                        $val = $json_request[$var_name];
                        break 2;
                    }
                break;

                case 'p':
                    if( null !== ($val = PHS_Params::_p( $var_name, $type, $type_extra )) ) {
                        return $val;
                    }
                break;

                case 'g':
                    if( null !== ($val = PHS_Params::_g( $var_name, $type, $type_extra )) ) {
                        return $val;
                    }
                break;
            }

            if( !($order = substr( $order, 1 )) ) {
                break;
            }
        }

        if( $val === null
         || null === ($type_val = PHS_Params::set_type( $val, $type, $type_extra )) ) {
            return $default;
        }

        return $type_val;
    }
}
