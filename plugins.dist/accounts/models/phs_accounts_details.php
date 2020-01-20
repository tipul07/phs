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
        return '1.0.0';
    }

    /**
     * @return array of string Returns an array of strings containing tables that model will handle
     */
    public function get_table_names()
    {
        return array( 'users_details' );
    }

    /**
     * @return string Returns main table name used when calling insert with no table name
     */
    function get_main_table_name()
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

    /**
     * @inheritdoc
     */
    protected function get_insert_prepare_params( $params )
    {
        if( empty( $params ) or !is_array( $params ) )
            return false;

        if( empty( $params['fields']['uid'] ) )
        {
            $this->set_error( self::ERR_INSERT, $this->_pt( 'Please provide an user account id.' ) );
            return false;
        }

        return $params;
    }

    /**
     * @inheritdoc
     */
    final public function fields_definition( $params = false )
    {
        // $params should be flow parameters...
        if( empty( $params ) or !is_array( $params )
         or empty( $params['table_name'] ) )
            return false;

        $return_arr = array();
        switch( $params['table_name'] )
        {
            case 'users_details':
                $return_arr = array(
                    'id' => array(
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ),
                    'uid' => array(
                        'type' => self::FTYPE_INT,
                        'index' => true,
                        'editable' => false,
                    ),
                    'title' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '20',
                        'nullable' => true,
                    ),
                    'fname' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '250',
                        'nullable' => true,
                    ),
                    'lname' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '250',
                        'nullable' => true,
                    ),
                    'phone' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '50',
                        'nullable' => true,
                    ),
                    'company' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '250',
                        'nullable' => true,
                    ),
                );
            break;
       }

        return $return_arr;
    }
}
