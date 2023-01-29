<?php

namespace phs\plugins\accounts\models;

use \phs\libraries\PHS_Model;

class PHS_Model_Accounts_details extends PHS_Model
{
    /**
     * @return string Returns version of model
     */
    public function get_model_version()
    {
        return '1.0.3';
    }

    /**
     * @return array of string Returns an array of strings containing tables that model will handle
     */
    public function get_table_names()
    {
        return [ 'users_details' ];
    }

    /**
     * @return string Returns main table name used when calling insert with no table name
     */
    public function get_main_table_name()
    {
        return 'users_details';
    }

    /**
     * @inheritdoc
     */
    public function dynamic_table_structure()
    {
        return true;
    }

    protected function get_insert_prepare_params_users_details( $params )
    {
        if( empty( $params ) || !is_array( $params ) ) {
            return false;
        }

        if( empty( $params['fields']['uid'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide an user account id.' ) );
            return false;
        }

        if( !empty( $params['fields']['title'] ) ) {
            $params['fields']['title'] = substr($params['fields']['title'], 0, 20);
        }
        if( !empty( $params['fields']['fname'] ) ) {
            $params['fields']['fname'] = substr($params['fields']['fname'], 0, 255);
        }
        if( !empty( $params['fields']['lname'] ) ) {
            $params['fields']['lname'] = substr($params['fields']['lname'], 0, 255);
        }
        if( !empty( $params['fields']['phone'] ) ) {
            $params['fields']['phone'] = substr($params['fields']['phone'], 0, 50);
        }
        if( !empty( $params['fields']['company'] ) ) {
            $params['fields']['company'] = substr($params['fields']['company'], 0, 255);
        }

        if( !isset( $params['fields']['limit_emails'] ) ) {
            $params['fields']['limit_emails'] = 0;
        } else {
            $params['fields']['limit_emails'] = (!empty($params['fields']['limit_emails']) ? 1 : 0);
        }

        return $params;
    }

    protected function get_edit_prepare_params_users_details( $existing_data, $params )
    {
        if( empty( $params ) || !is_array( $params ) )
            return false;

        if( isset( $params['fields']['uid'] ) && empty( $params['fields']['uid'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide an user account id.' ) );
            return false;
        }

        if( !empty( $params['fields']['title'] ) )
            $params['fields']['title'] = substr( $params['fields']['title'], 0, 20 );
        if( !empty( $params['fields']['fname'] ) )
            $params['fields']['fname'] = substr( $params['fields']['fname'], 0, 255 );
        if( !empty( $params['fields']['lname'] ) )
            $params['fields']['lname'] = substr( $params['fields']['lname'], 0, 255 );
        if( !empty( $params['fields']['phone'] ) )
            $params['fields']['phone'] = substr( $params['fields']['phone'], 0, 50 );
        if( !empty( $params['fields']['company'] ) )
            $params['fields']['company'] = substr( $params['fields']['company'], 0, 255 );

        if( isset( $params['fields']['limit_emails'] ) )
            $params['fields']['limit_emails'] = (!empty( $params['fields']['limit_emails'] )?1:0);

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
            case 'users_details':
                $return_arr = [
                    'id' => [
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ],
                    'uid' => [
                        'type' => self::FTYPE_INT,
                        'index' => true,
                        'editable' => false,
                    ],
                    'title' => [
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 20,
                        'nullable' => true,
                    ],
                    'fname' => [
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'nullable' => true,
                    ],
                    'lname' => [
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'nullable' => true,
                    ],
                    'phone' => [
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 50,
                        'nullable' => true,
                    ],
                    'company' => [
                        'type' => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'nullable' => true,
                    ],
                    'limit_emails' => [
                        'type' => self::FTYPE_TINYINT,
                        'length' => 2,
                        'default' => 0,
                        'comment' => 'Try to minimize emails sent to user',
                    ],
                ];
            break;
       }

        return $return_arr;
    }
}
