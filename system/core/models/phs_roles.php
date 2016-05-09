<?php

namespace phs\system\core\models;

use \phs\libraries\PHS_Model;
use \phs\libraries\PHS_params;

class PHS_Model_Roles extends PHS_Model
{
    const ERR_READ = 10000, ERR_WRITE = 10001;

    const STATUS_INACTIVE = 1, STATUS_ACTIVE = 2, STATUS_DELETED = 3;
    protected static $STATUSES_ARR = array(
        self::STATUS_INACTIVE => array( 'title' => 'Inactive' ),
        self::STATUS_ACTIVE => array( 'title' => 'Active' ),
        self::STATUS_DELETED => array( 'title' => 'Deleted' ),
    );

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
        return array( 'roles', 'roles_units', 'roles_units_links', 'roles_users' );
    }

    /**
     * @return string Returns main table name used when calling insert with no table name
     */
    function get_main_table_name()
    {
        return 'roles';
    }

    public function get_settings_structure()
    {
        return array(
        );
    }

    final public function get_statuses()
    {
        static $statuses_arr = array();

        if( !empty( $statuses_arr ) )
            return $statuses_arr;

        $statuses_arr = array();
        // Translate and validate statuses...
        if( !empty( $new_statuses_arr ) and is_array( $new_statuses_arr ) )
        {
            foreach( $new_statuses_arr as $status_id => $status_arr )
            {
                $status_id = intval( $status_id );
                if( empty( $status_id ) )
                    continue;

                if( empty( $status_arr['title'] ) )
                    $status_arr['title'] = self::_t( 'Status %s', $status_id );
                else
                    $status_arr['title'] = self::_t( $status_arr['title'] );

                $statuses_arr[$status_id] = array(
                    'title' => $status_arr['title']
                );
            }
        }

        return $statuses_arr;
    }

    final public function get_statuses_as_key_val()
    {
        static $user_statuses_key_val_arr = false;

        if( $user_statuses_key_val_arr !== false )
            return $user_statuses_key_val_arr;

        $user_statuses_key_val_arr = array();
        if( ($user_statuses = $this->get_statuses()) )
        {
            foreach( $user_statuses as $key => $val )
            {
                if( !is_array( $val ) )
                    continue;

                $user_statuses_key_val_arr[$key] = $val['title'];
            }
        }

        return $user_statuses_key_val_arr;
    }

    public function valid_status( $status )
    {
        $all_statuses = $this->get_statuses();
        if( empty( $status )
         or empty( $all_statuses[$status] ) )
            return false;

        return $all_statuses[$status];
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
    protected function get_insert_prepare_params_roles( $params )
    {
        if( empty( $params ) or !is_array( $params ) )
            return false;

        if( empty( $params['fields']['name'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a name for this role.' ) );
            return false;
        }

        $params['fields']['cdate'] = date( self::DATETIME_DB );

        if( empty( $params['fields']['status'] ) )
            $params['fields']['status'] = self::STATUS_ACTIVE;

        if( !$this->valid_status( $params['fields']['status'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a valid status for this role.' ) );
            return false;
        }

        if( empty( $params['fields']['status_date'] )
         or empty_db_date( $params['fields']['status_date'] ) )
            $params['fields']['status_date'] = $params['fields']['cdate'];

        return $params;
    }

    /**
     * Called first in edit flow.
     * Parses flow parameters if anything special should be done.
     * This should do checks on raw parameters received by edit method.
     *
     * @param array|int $existing_data Data which already exists in database (id or full array with all database fields)
     * @param array|false $params Parameters in the flow
     *
     * @return array Flow parameters array
     */
    protected function get_edit_prepare_params_roles( $existing_data, $params )
    {
        if( empty( $params ) or !is_array( $params ) )
            return false;

        if( isset( $params['fields']['status'] ) )
        {
            if( !$this->valid_status( $params['fields']['status'] ) )
            {
                $this->set_error( self::ERR_EDIT, self::_t( 'Please provide a valid status.' ) );
                return false;
            }

            $cdate = date( self::DATETIME_DB );
            $params['fields']['status_date'] = $cdate;
        }

        return $params;
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
    protected function get_insert_prepare_params_roles_units( $params )
    {
        if( empty( $params ) or !is_array( $params ) )
            return false;

        if( empty( $params['fields']['slug'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a slug for this role unit.' ) );
            return false;
        }

        if( empty( $params['fields']['name'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a name for this role unit.' ) );
            return false;
        }

        $params['fields']['cdate'] = date( self::DATETIME_DB );

        if( empty( $params['fields']['status'] ) )
            $params['fields']['status'] = self::STATUS_ACTIVE;

        if( !$this->valid_status( $params['fields']['status'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a valid status for this role unit.' ) );
            return false;
        }

        if( empty( $params['fields']['status_date'] )
         or empty_db_date( $params['fields']['status_date'] ) )
            $params['fields']['status_date'] = $params['fields']['cdate'];

        return $params;
    }

    /**
     * Called first in edit flow.
     * Parses flow parameters if anything special should be done.
     * This should do checks on raw parameters received by edit method.
     *
     * @param array|int $existing_data Data which already exists in database (id or full array with all database fields)
     * @param array|false $params Parameters in the flow
     *
     * @return array Flow parameters array
     */
    protected function get_edit_prepare_params_roles_units( $existing_data, $params )
    {
        if( empty( $params ) or !is_array( $params ) )
            return false;

        if( isset( $params['fields']['status'] ) )
        {
            if( !$this->valid_status( $params['fields']['status'] ) )
            {
                $this->set_error( self::ERR_EDIT, self::_t( 'Please provide a valid status.' ) );
                return false;
            }

            $cdate = date( self::DATETIME_DB );
            $params['fields']['status_date'] = $cdate;
        }

        return $params;
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
    protected function get_insert_prepare_params_roles_units_links( $params )
    {
        if( empty( $params ) or !is_array( $params ) )
            return false;

        if( !empty( $params['fields']['role_id'] ) )
            $params['fields']['role_id'] = intval( $params['fields']['role_id'] );
        if( !empty( $params['fields']['role_unit_id'] ) )
            $params['fields']['role_unit_id'] = intval( $params['fields']['role_unit_id'] );

        if( empty( $params['fields']['role_id'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a role id.' ) );
            return false;
        }

        if( empty( $params['fields']['role_unit_id'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a role unit id.' ) );
            return false;
        }

        return $params;
    }

    /**
     * Called first in edit flow.
     * Parses flow parameters if anything special should be done.
     * This should do checks on raw parameters received by edit method.
     *
     * @param array|int $existing_data Data which already exists in database (id or full array with all database fields)
     * @param array|false $params Parameters in the flow
     *
     * @return array Flow parameters array
     */
    protected function get_edit_prepare_params_roles_units_links( $existing_data, $params )
    {
        if( empty( $params ) or !is_array( $params ) )
            return false;

        if( isset( $params['fields']['role_id'] ) )
            $params['fields']['role_id'] = intval( $params['fields']['role_id'] );
        if( isset( $params['fields']['role_unit_id'] ) )
            $params['fields']['role_unit_id'] = intval( $params['fields']['role_unit_id'] );

        if( isset( $params['fields']['role_id'] ) and empty( $params['fields']['role_id'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a role id.' ) );
            return false;
        }

        if( isset( $params['fields']['role_unit_id'] ) and empty( $params['fields']['role_unit_id'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a role unit id.' ) );
            return false;
        }

        return $params;
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
    protected function get_insert_prepare_params_roles_users( $params )
    {
        if( empty( $params ) or !is_array( $params ) )
            return false;

        if( !empty( $params['fields']['role_id'] ) )
            $params['fields']['role_id'] = intval( $params['fields']['role_id'] );
        if( !empty( $params['fields']['user_id'] ) )
            $params['fields']['user_id'] = intval( $params['fields']['user_id'] );

        if( empty( $params['fields']['role_id'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a role id.' ) );
            return false;
        }

        if( empty( $params['fields']['user_id'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide user id.' ) );
            return false;
        }

        return $params;
    }

    /**
     * Called first in edit flow.
     * Parses flow parameters if anything special should be done.
     * This should do checks on raw parameters received by edit method.
     *
     * @param array|int $existing_data Data which already exists in database (id or full array with all database fields)
     * @param array|false $params Parameters in the flow
     *
     * @return array Flow parameters array
     */
    protected function get_edit_prepare_params_roles_users( $existing_data, $params )
    {
        if( empty( $params ) or !is_array( $params ) )
            return false;

        if( isset( $params['fields']['role_id'] ) )
            $params['fields']['role_id'] = intval( $params['fields']['role_id'] );
        if( isset( $params['fields']['user_id'] ) )
            $params['fields']['user_id'] = intval( $params['fields']['user_id'] );

        if( isset( $params['fields']['role_id'] ) and empty( $params['fields']['role_id'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a role id.' ) );
            return false;
        }

        if( isset( $params['fields']['user_id'] ) and empty( $params['fields']['user_id'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide user id.' ) );
            return false;
        }

        return $params;
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
            case 'roles':
                $return_arr = array(
                    'id' => array(
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ),
                    'uid' => array(
                        'type' => self::FTYPE_INT,
                        'index' => true,
                        'comment' => 'Which user defined this role',
                    ),
                    'name' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '255',
                        'nullable' => true,
                        'default' => null,
                    ),
                    'description' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '255',
                        'nullable' => true,
                        'default' => null,
                    ),
                    'status' => array(
                        'type' => self::FTYPE_TINYINT,
                        'length' => '2',
                        'index' => true,
                    ),
                    'status_date' => array(
                        'type' => self::FTYPE_DATETIME,
                        'index' => false,
                    ),
                    'predefined' => array(
                        'type' => self::FTYPE_TINYINT,
                        'length' => 2,
                        'index' => true,
                    ),
                    'cdate' => array(
                        'type' => self::FTYPE_DATETIME,
                    ),
                );
            break;
            case 'roles_units':
                $return_arr = array(
                    'id' => array(
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ),
                    'slug' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '255',
                        'nullable' => true,
                        'default' => null,
                        'index' => true,
                    ),
                    'name' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '255',
                        'nullable' => true,
                        'default' => null,
                    ),
                    'description' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '255',
                        'nullable' => true,
                        'default' => null,
                    ),
                    'status' => array(
                        'type' => self::FTYPE_TINYINT,
                        'length' => '2',
                        'index' => true,
                    ),
                    'status_date' => array(
                        'type' => self::FTYPE_DATETIME,
                        'index' => false,
                    ),
                    'cdate' => array(
                        'type' => self::FTYPE_DATETIME,
                    ),
                );
            break;
            case 'roles_units_links':
                $return_arr = array(
                    'id' => array(
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ),
                    'role_id' => array(
                        'type' => self::FTYPE_INT,
                        'index' => true,
                    ),
                    'role_unit_id' => array(
                        'type' => self::FTYPE_INT,
                        'index' => true,
                    ),
                );
            break;
            case 'roles_users':
                $return_arr = array(
                    'id' => array(
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ),
                    'role_id' => array(
                        'type' => self::FTYPE_INT,
                        'index' => true,
                    ),
                    'user_id' => array(
                        'type' => self::FTYPE_INT,
                        'index' => true,
                    ),
                );
            break;
       }

        return $return_arr;
    }
}
