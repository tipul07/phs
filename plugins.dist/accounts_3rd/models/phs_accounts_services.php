<?php

namespace phs\plugins\accounts_3rd\models;

use \phs\libraries\PHS_Model;
use \phs\traits\PHS_Model_Trait_statuses;

class PHS_Model_Accounts_services extends PHS_Model
{
    public const SERVICE_GOOGLE = 1, SERVICE_APPLE = 2, SERVICE_FACEBOOK = 3;
    protected static array $SERVICES_ARR = [
        self::SERVICE_GOOGLE => [ 'title' => 'Google' ],
        self::SERVICE_APPLE => [ 'title' => 'Apple' ],
        self::SERVICE_FACEBOOK => [ 'title' => 'Facebook' ],
    ];

    /**
     * @inheritDoc
     */
    public function get_model_version()
    {
        return '1.0.3';
    }

    /**
     * @inheritDoc
     */
    public function get_table_names()
    {
        return [ 'users_services' ];
    }

    /**
     * @inheritDoc
     */
    public function get_main_table_name()
    {
        return 'users_services';
    }

    /**
     * @param bool|string $lang
     *
     * @return array
     */
    public function get_services( $lang = false )
    {
        static $services_arr = [];

        if( empty( self::$SERVICES_ARR ) )
            return [];

        if( $lang === false
         && !empty( $services_arr ) )
            return $services_arr;

        $result_arr = $this->translate_array_keys( self::$SERVICES_ARR, [ 'title' ], $lang );

        if( $lang === false )
            $services_arr = $result_arr;

        return $result_arr;
    }

    /**
     * @param bool|string $lang
     *
     * @return array
     */
    public function get_services_as_key_val( $lang = false )
    {
        static $services_key_val_arr = false;

        if( $lang === false
         && $services_key_val_arr !== false )
            return $services_key_val_arr;

        $key_val_arr = [];
        if( ($services = $this->get_services( $lang )) )
        {
            foreach( $services as $key => $val )
            {
                if( !is_array( $val ) )
                    continue;

                $key_val_arr[$key] = $val['title'];
            }
        }

        if( $lang === false )
            $services_key_val_arr = $key_val_arr;

        return $key_val_arr;
    }

    /**
     * @param int $service
     * @param bool|string $lang
     *
     * @return bool|array
     */
    public function valid_service( $service, $lang = false )
    {
        $all_services = $this->get_services( $lang );
        if( empty( $service )
         || !isset( $all_services[$service] ) )
            return false;

        return $all_services[$service];
    }

    /**
     * @param int $user_id User ID
     * @param int $service_id 3rd party service ID
     *
     * @return array|bool
     */
    public function user_is_linked_with_service( $user_id, $service_id )
    {
        $this->reset_error();

        $user_id = (int)$user_id;
        if( empty( $user_id ) )
            return false;

        if( $service_id !== false )
            $service_id = (int)$service_id;

        if( !empty( $service_id )
         && !$this->valid_service( $service_id ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Invalid service ID provided.' ) );
            return false;
        }

        $as_flow_params = $this->fetch_default_flow_params( [ 'table_name' => 'users_services' ] );

        $check_arr = [];
        $check_arr['user_id'] = $user_id;
        $check_arr['service_id'] = $service_id;

        return $this->get_details_fields( $check_arr, $as_flow_params );
    }

    public function link_user_with_service( $user_id, $service_id, $account_details = null )
    {
        $this->reset_error();

        $user_id = (int)$user_id;
        $service_id = (int)$service_id;

        if( empty( $service_id )
         || !$this->valid_service( $service_id ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Invalid service ID provided.' ) );
            return false;
        }

        $flow_params = $this->fetch_default_flow_params( [ 'table_name' => 'users_services' ] );

        $action_fields_arr = [];
        $action_fields_arr['user_id'] = $user_id;
        $action_fields_arr['service_id'] = $service_id;
        $action_fields_arr['account_details'] = $account_details;

        $action_arr = $flow_params;
        $action_arr['fields'] = $action_fields_arr;

        if( ($existing_arr = $this->get_details_fields( [ 'user_id' => $user_id, 'service_id' => $service_id ], $flow_params )) )
            return $this->edit( $existing_arr, $action_arr );

        return $this->insert( $action_arr );
    }

    protected function get_insert_prepare_params_users_services( $params )
    {
        if( empty( $params ) || !is_array( $params ) )
            return false;

        if( !empty( $params['fields']['account_details'] ) )
            $params['fields']['account_details'] = trim( $params['fields']['account_details'] );

        if( empty( $params['fields']['user_id'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide an account id for this service.' ) );
            return false;
        }

        if( empty( $params['fields']['service_id'] )
         || !$this->valid_service( $params['fields']['service_id'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide a valid service for this account.' ) );
            return false;
        }

        if( !empty( $params['fields']['account_details'] )
         && (!is_string( $params['fields']['account_details'] )
            || !($account_details_arr = @json_decode( $params['fields']['account_details'], true ))
            ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide service account details. This should be a JSON string.' ) );
            return false;
        }

        $params['fields']['last_update'] = $params['fields']['cdate'] = date( self::DATETIME_DB );

        return $params;
    }

    protected function get_edit_prepare_params_users_services( $existing_data, $params )
    {
        if( empty( $params ) || !is_array( $params ) )
            return false;

        if( isset( $params['fields']['user_id'] ) && empty( $params['fields']['user_id'] ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Please provide an account id for this service.' ) );
            return false;
        }

        if( isset( $params['fields']['service_id'] )
         && !$this->valid_service( $params['fields']['service_id'] ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Please provide a valid service for this account.' ) );
            return false;
        }

        if( !empty( $params['fields']['account_details'] ) && is_string( $params['fields']['account_details'] ) )
            $params['fields']['account_details'] = trim( $params['fields']['account_details'] );

        if( !empty( $params['fields']['account_details'] )
         && (!is_string( $params['fields']['account_details'] )
             || !($account_details_arr = @json_decode( $params['fields']['account_details'], true ))
            ) )
        {
            $this->set_error( self::ERR_EDIT, $this->_pt( 'Please provide valid service account details. This should be a JSON string.' ) );
            return false;
        }

        $params['fields']['last_update'] = date( self::DATETIME_DB );

        return $params;
    }

    /**
     * @inheritdoc
     */
    final public function fields_definition( $params = false )
    {
        // $params should be flow parameters...
        if( empty( $params ) || !is_array( $params )
         || empty( $params['table_name'] ) )
            return false;

        $return_arr = [];
        switch( $params['table_name'] )
        {
            case 'users_services':
                $return_arr = [
                    'id' => [
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ],
                    'user_id' => [
                        'type' => self::FTYPE_INT,
                        'index' => true,
                    ],
                    'service_id' => [
                        'type' => self::FTYPE_TINYINT,
                        'length' => 4,
                        'index' => true,
                    ],
                    'account_details' => [
                        'type' => self::FTYPE_LONGTEXT,
                        'nullable' => true,
                        'comment' => 'JSON containing details passed by 3rd party',
                    ],
                    'last_update' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                    'cdate' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                ];
            break;
       }

        return $return_arr;
    }
}
