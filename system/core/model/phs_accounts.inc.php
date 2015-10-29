<?php

class PHS_Model_Accounts extends PHS_Model
{
    const MODEL_VERSION = '1.0.0';

    /**
     * @param array|false $params Parameters in the flow
     *
     * @return string Returns main table name used (table name can be passed to $params array of each method in 'table_name' index)
     */
    function get_table_name( $params = false )
    {
        // return default table...
        if( empty( $params ) or !is_array( $params ) )
            return 'users';

        if( !empty( $params['table_name'] ) )
            return $params['table_name'];

        return false;
    }

    function get_all_table_names()
    {
        return array( 'users', 'users_details', 'online' );
    }

    /**
     * @param array|false $params Parameters in the flow
     *
     * @return array Returns an array with table fields
     */
    protected function fields_definition( $params = false )
    {
        // $params should be flow parameters...
        if( empty( $params ) or !is_array( $params )
         or empty( $params['table_name'] ) )
            return false;

        $return_arr = array();
        switch( $params['table_name'] )
        {
            default:
            case 'users':
                $return_arr = array(
                    'id' => array(
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ),
                    'nick' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '50',
                        'index' => true,
                    ),
                    'pass' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '50',
                    ),
                    'pass_clear' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '150',
                    ),
                    'email' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '150',
                        'index' => true,
                    ),
                    'email_verified' => array(
                        'type' => self::FTYPE_TINYINT,
                        'length' => '2',
                    ),
                    'added_by' => array(
                        'type' => self::FTYPE_INT,
                    ),
                    'level' => array(
                        'type' => self::FTYPE_TINYINT,
                        'length' => '2',
                    ),
                    'active' => array(
                        'type' => self::FTYPE_DATETIME,
                        'index' => true,
                    ),
                    'deleted' => array(
                        'type' => self::FTYPE_DATETIME,
                        'index' => true,
                    ),
                    'cdate' => array(
                        'type' => self::FTYPE_DATETIME,
                    ),
                );
            break;

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
                    ),
                    'title' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '20',
                    ),
                    'fname' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '250',
                    ),
                    'lname' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '250',
                    ),
                    'phone' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '50',
                    ),
                    'company' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '250',
                    ),
                );
            break;

            case 'online':
                $return_arr = array(
                    'id' => array(
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ),
                    'wid' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '50',
                        'index' => true,
                    ),
                    'uid' => array(
                        'type' => self::FTYPE_INT,
                        'index' => true,
                    ),
                    'auid' => array(
                        'type' => self::FTYPE_INT,
                    ),
                    'host' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '50',
                    ),
                    'idle' => array(
                        'type' => self::FTYPE_DATETIME,
                    ),
                    'connected' => array(
                        'type' => self::FTYPE_DATETIME,
                    ),
                    'expire_secs' => array(
                        'type' => self::FTYPE_INT,
                    ),
                    'location' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '255',
                    ),
                );
                break;
        }

        return $return_arr;
    }

}
