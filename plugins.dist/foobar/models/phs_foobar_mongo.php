<?php

namespace phs\plugins\foobar\models;

use \phs\libraries\PHS_Model;
use phs\libraries\PHS_Model_Mongo;
use \phs\libraries\PHS_Params;

class PHS_Model_Foobar_mongo extends PHS_Model_Mongo
{
    const ERR_DB_JOB = 10000;

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
        return array( 'testcol' );
    }

    /**
     * @return string Returns main table name used when calling insert with no table name
     */
    function get_main_table_name()
    {
        return 'testcol';
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
            case 'testcol':
                $return_arr = array(
                    'anint' => array(
                        'type' => self::FTYPE_INTEGER,
                        'default' => 0,
                    ),
                    'fname' => array(
                        'type' => self::FTYPE_STRING,
                        'default' => '',
                    ),
                    'lname' => array(
                        'type' => self::FTYPE_STRING,
                        'default' => '',
                    ),
                    'tstamp' => array(
                        'type' => self::FTYPE_TIMESTAMP,
                        'default' => 0,
                    ),
                    'cdate' => array(
                        'type' => self::FTYPE_DATE,
                    ),
                );
            break;
       }

        return $return_arr;
    }
}
