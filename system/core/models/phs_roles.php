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
            'roles_cache_size' => array(
                'display_name' => 'Role cache size',
                'display_hint' => 'How many records to read from roles table. Increase this value if you use more roles.',
                'type' => PHS_params::T_INT,
                'default' => 1000,
            ),
            'units_cache_size' => array(
                'display_name' => 'Role units cache size',
                'display_hint' => 'How many records to read from role units table. Increase this value if you use more role units.',
                'type' => PHS_params::T_INT,
                'default' => 1000,
            ),
        );
    }

    final public function get_statuses()
    {
        static $statuses_arr = array();

        if( !empty( $statuses_arr ) )
            return $statuses_arr;

        $statuses_arr = array();
        // Translate and validate statuses...
        foreach( self::$STATUSES_ARR as $status_id => $status_arr )
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
     * Returns an array of key-values of fields that should edit role unit in case role unit already exists
     * @return array
     */
    public static function get_register_edit_role_unit_fields()
    {
        return array(
            'name' => '',
            'description' => '',
        );
    }

    public function get_all_role_units()
    {
        static $all_role_units = false;

        if( $all_role_units !== false )
            return $all_role_units;

        if( !($model_settings = $this->get_db_settings()) )
            $model_settings = array();

        if( empty( $model_settings['units_cache_size'] ) )
            $model_settings['units_cache_size'] = 1000;

        $all_role_units = array();

        $list_arr = array();
        $list_arr['table_name'] = 'roles_units';
        // Raise this limit if you have more units...
        $list_arr['enregs_no'] = $model_settings['units_cache_size'];
        $list_arr['order_by'] = 'roles_units.name ASC';

        if( !($all_role_units = $this->get_list( $list_arr )) )
            $all_role_units = array();

        return $all_role_units;
    }

    public function get_all_role_units_by_slug()
    {
        static $all_role_units = false;

        if( $all_role_units !== false )
            return $all_role_units;

        if( !($model_settings = $this->get_db_settings()) )
            $model_settings = array();

        if( empty( $model_settings['units_cache_size'] ) )
            $model_settings['units_cache_size'] = 1000;

        $all_role_units = array();

        $list_arr = array();
        $list_arr['table_name'] = 'roles_units';
        // Raise this limit if you have more units...
        $list_arr['enregs_no'] = $model_settings['units_cache_size'];
        $list_arr['order_by'] = 'roles_units.name ASC';
        $list_arr['arr_index_field'] = 'slug';

        if( !($all_role_units = $this->get_list( $list_arr )) )
            $all_role_units = array();

        return $all_role_units;
    }

    public function get_all_roles()
    {
        static $all_roles = false;

        if( $all_roles !== false )
            return $all_roles;

        if( !($model_settings = $this->get_db_settings()) )
            $model_settings = array();

        if( empty( $model_settings['roles_cache_size'] ) )
            $model_settings['roles_cache_size'] = 1000;

        $all_roles = array();

        $list_arr = array();
        // Raise this limit if you have more units...
        $list_arr['enregs_no'] = $model_settings['roles_cache_size'];
        $list_arr['order_by'] = 'roles.name ASC';

        if( !($all_roles = $this->get_list( $list_arr )) )
            $all_roles = array();

        return $all_roles;
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

        if( empty( $params['fields']['slug'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a slug for this role.' ) );
            return false;
        }

        if( empty( $params['fields']['name'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a name for this role.' ) );
            return false;
        }

        $constrain_arr = array();
        $constrain_arr['slug'] = $params['fields']['slug'];

        $check_params = array();
        $check_params['table_name'] = 'roles';
        $check_params['result_type'] = 'single';
        $check_params['details'] = '*';

        if( $this->get_details_fields( $constrain_arr, $check_params ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'There is already a role registered with this slug.' ) );
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

        $params['fields']['predefined'] = (!empty( $params['fields']['predefined'] )?1:0);

        if( empty( $params['{role_units}'] ) or !is_array( $params['{role_units}'] ) )
            $params['{role_units}'] = false;

        return $params;
    }

    /**
     * Called right after a successfull insert in database. Some model need more database work after successfully adding records in database or eventually chaining
     * database inserts. If one chain fails function should return false so all records added before to be hard-deleted. In case of success, function will return an array with all
     * key-values added in database.
     *
     * @param array $insert_arr Data array added with success in database
     * @param array $params Flow parameters
     *
     * @return array|false Returns data array added in database (with changes, if required) or false if record should be deleted from database.
     * Deleted record will be hard-deleted
     */
    protected function insert_after_roles( $insert_arr, $params )
    {
        if( !empty( $params['{role_units}'] ) and is_array( $params['{role_units}'] ) )
        {
            if( !$this->link_role_units_to_roles( $insert_arr, $params['{role_units}'] ) )
                return false;
        }

        return $insert_arr;
    }
    
    public function link_role_units_to_roles( $role_arr, $role_units_arr )
    {
        return true;
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

        if( isset( $params['fields']['predefined'] ) )
            $params['fields']['predefined'] = (!empty( $params['fields']['predefined'] )?1:0);

        if( isset( $params['fields']['slug'] ) )
        {
            if( empty( $params['fields']['slug'] ) )
            {
                $this->set_error( self::ERR_EDIT, self::_t( 'Role slug cannot be empty.' ) );
                return false;
            }

            $constrain_arr = array();
            $constrain_arr['slug'] = $params['fields']['slug'];
            $constrain_arr['id'] = array( 'check' => '!=', 'value' => $existing_data['id'] );

            $check_params = array();
            $check_params['table_name'] = 'roles';
            $check_params['result_type'] = 'single';
            $check_params['details'] = 'id';

            if( $this->get_details_fields( $constrain_arr, $check_params ) )
            {
                $this->set_error( self::ERR_EDIT, self::_t( 'There is already a role registered with this slug.' ) );

                return false;
            }
        }

        if( empty( $params['{role_units}'] ) or !is_array( $params['{role_units}'] ) )
            $params['{role_units}'] = false;

        return $params;
    }

    /**
     * Called right after a successfull edit action. Some model need more database work after editing records. This action is called even if model didn't save anything
     * in database.
     *
     * @param array|int $existing_data Data which already exists in database (id or full array with all database fields)
     * @param array $edit_arr Data array saved with success in database. This can also be an empty array (nothing to save in database)
     * @param array $params Flow parameters
     *
     * @return array|false Returns data array added in database (with changes, if required) or false if record should be deleted from database.
     * Deleted record will be hard-deleted
     */
    protected function edit_after_roles( $existing_data, $edit_arr, $params )
    {
        if( !empty( $params['{role_units}'] ) and is_array( $params['{role_units}'] ) )
        {
            if( !$this->link_role_units_to_roles( $existing_data, $params['{role_units}'] ) )
                return false;
        }

        return $existing_data;
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

        $constrain_arr = array();
        $constrain_arr['slug'] = $params['fields']['slug'];

        $check_params = array();
        $check_params['table_name'] = 'roles_units';
        $check_params['result_type'] = 'single';
        $check_params['details'] = '*';

        if( $this->get_details_fields( $constrain_arr, $check_params ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'There is already a role unit registered with this slug.' ) );
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

        if( isset( $params['fields']['slug'] ) )
        {
            if( empty( $params['fields']['slug'] ) )
            {
                $this->set_error( self::ERR_EDIT, self::_t( 'Role unit slug cannot be empty.' ) );
                return false;
            }

            $constrain_arr = array();
            $constrain_arr['slug'] = $params['fields']['slug'];
            $constrain_arr['id'] = array( 'check' => '!=', 'value' => $existing_data['id'] );

            $check_params = array();
            $check_params['table_name'] = 'roles_units';
            $check_params['result_type'] = 'single';
            $check_params['details'] = 'id';

            if( $this->get_details_fields( $constrain_arr, $check_params ) )
            {
                $this->set_error( self::ERR_EDIT, self::_t( 'There is already a role unit registered with this slug.' ) );

                return false;
            }
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
                    'slug' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '255',
                        'nullable' => true,
                        'default' => null,
                        'index' => true,
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
