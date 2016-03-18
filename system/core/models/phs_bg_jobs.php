<?php

namespace phs\system\core\models;

use \phs\libraries\PHS_Model;

class PHS_Model_Bg_jobs extends PHS_Model
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
        return array( 'bg_jobs' );
    }

    /**
     * @return string Returns main table name used when calling insert with no table name
     */
    function get_main_table_name()
    {
        return 'bg_jobs';
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

    /**
     * Called first in insert flow.
     * Parses flow parameters if anything special should be done.
     * This should do checks on raw parameters received by insert method.
     *
     * @param array|false $params Parameters in the flow
     *
     * @return array Flow parameters array
     */
    protected function get_insert_prepare_params( $params )
    {
        if( empty( $params ) or !is_array( $params ) )
            return false;

        if( empty( $params['fields']['route'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a route.' ) );
            return false;
        }

        $params['fields']['last_action'] = date( self::DATETIME_DB );

        if( empty( $params['fields']['cdate'] ) or $params['fields']['cdate'] == self::DATETIME_EMPTY )
            $params['fields']['cdate'] = $params['fields']['last_action'];

        return $params;
    }

    /**
     * @param array|bool $params Parameters in the flow
     *
     * @return array Returns an array with table fields
     */
    final protected function fields_definition( $params = false )
    {
        // $params should be flow parameters...
        if( empty( $params ) or !is_array( $params )
         or empty( $params['table_name'] ) )
            return false;

        $return_arr = array();
        switch( $params['table_name'] )
        {
            case 'bg_jobs':
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
                    'last_action' => array(
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
