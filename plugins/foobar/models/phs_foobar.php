<?php

namespace phs\plugins\foobar\models;

use \phs\libraries\PHS_Model;

class PHS_Model_Foobar extends PHS_Model
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
        return array( 'foobar' );
    }

    /**
     * @return string Returns main table name used when calling insert with no table name
     */
    function get_main_table_name()
    {
        return 'foobar';
    }

    /**
     * Performs any necessary actions when updating model from $old_version to $new_version
     *
     * @param string $old_version Old version of model
     * @param string $new_version New version of model
     *
     * @return bool true on success, false on failure
     */
    protected function update( $old_version, $new_version )
    {
        return true;
    }

    public function get_default_settings()
    {
        return array(
            'minutes_to_stall' => 15,
        );
    }

    /**
     * @param array|bool $params Parameters in the flow
     *
     * @return array Returns an array with table fields
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
            case 'foobar':
                $return_arr = array(
                    'id' => array(
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ),
                    'uid' => array(
                        'type' => self::FTYPE_INT,
                        'index' => true,
                    ),
                    'pid' => array(
                        'type' => self::FTYPE_INT,
                    ),
                    'route' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '255',
                        'nullable' => true,
                        'default' => null,
                    ),
                    'params' => array(
                        'type' => self::FTYPE_LONGTEXT,
                        'nullable' => true,
                    ),
                    'last_error' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '255',
                        'nullable' => true,
                        'default' => null,
                    ),
                    'last_action' => array(
                        'type' => self::FTYPE_DATETIME,
                        'index' => true,
                    ),
                    'timed_action' => array(
                        'type' => self::FTYPE_DATETIME,
                        'index' => true,
                    ),
                    'cdate' => array(
                        'type' => self::FTYPE_DATETIME,
                    ),
                );
            break;
       }

        return $return_arr;
    }
}
