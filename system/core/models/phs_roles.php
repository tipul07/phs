<?php

namespace phs\system\core\models;

use \phs\PHS;
use \phs\libraries\PHS_Model;
use \phs\libraries\PHS_Params;

class PHS_Model_Roles extends PHS_Model
{
    const ERR_READ = 10000, ERR_WRITE = 10001;

    const STATUS_INACTIVE = 1, STATUS_ACTIVE = 2, STATUS_DELETED = 3, STATUS_SUSPENDED = 4;
    protected static $STATUSES_ARR = array(
        self::STATUS_INACTIVE => array( 'title' => 'Inactive' ),
        self::STATUS_ACTIVE => array( 'title' => 'Active' ),
        self::STATUS_DELETED => array( 'title' => 'Deleted' ),
        self::STATUS_SUSPENDED => array( 'title' => 'Suspended' ),
    );

    /** @var bool|\phs\plugins\accounts\models\PHS_Model_Accounts $_accounts_model */
    private static $_accounts_model = false;

    /**
     * @return string Returns version of model
     */
    public function get_model_version()
    {
        return '1.0.2';
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
                'type' => PHS_Params::T_INT,
                'default' => 1000,
            ),
            'units_cache_size' => array(
                'display_name' => 'Role units cache size',
                'display_hint' => 'How many records to read from role units table. Increase this value if you use more role units.',
                'type' => PHS_Params::T_INT,
                'default' => 1000,
            ),
        );
    }

    final public function get_statuses( $lang = false )
    {
        static $statuses_arr = array();

        if( $lang === false
        and !empty( $statuses_arr ) )
            return $statuses_arr;

        // Let these here so language parser would catch the texts...
        $this->_pt( 'Inactive' );
        $this->_pt( 'Active' );
        $this->_pt( 'Deleted' );
        $this->_pt( 'Suspended' );

        $result_arr = $this->translate_array_keys( self::$STATUSES_ARR, array( 'title' ), $lang );

        if( $lang === false )
            $statuses_arr = $result_arr;

        return $result_arr;
    }

    final public function get_statuses_as_key_val( $lang = false )
    {
        static $statuses_key_val_arr = false;

        if( $lang === false
        and $statuses_key_val_arr !== false )
            return $statuses_key_val_arr;

        $key_val_arr = array();
        if( ($statuses = $this->get_statuses( $lang )) )
        {
            foreach( $statuses as $key => $val )
            {
                if( !is_array( $val ) )
                    continue;

                $key_val_arr[$key] = $val['title'];
            }
        }

        if( $lang === false )
            $statuses_key_val_arr = $key_val_arr;

        return $key_val_arr;
    }

    public function valid_status( $status, $lang = false )
    {
        $all_statuses = $this->get_statuses( $lang );
        if( empty( $status )
         or !isset( $all_statuses[$status] ) )
            return false;

        return $all_statuses[$status];
    }

    public function is_active( $role_data )
    {
        if( !($role_arr = $this->data_to_array( $role_data ))
         or $role_arr['status'] != self::STATUS_ACTIVE )
            return false;

        return $role_arr;
    }

    public function is_inactive( $role_data )
    {
        if( !($role_arr = $this->data_to_array( $role_data ))
         or $role_arr['status'] != self::STATUS_INACTIVE )
            return false;

        return $role_arr;
    }

    public function is_deleted( $role_data )
    {
        if( !($role_arr = $this->data_to_array( $role_data ))
         or $role_arr['status'] != self::STATUS_DELETED )
            return false;

        return $role_arr;
    }

    public function is_suspended( $role_data )
    {
        if( !($role_arr = $this->data_to_array( $role_data ))
         or $role_arr['status'] != self::STATUS_SUSPENDED )
            return false;

        return $role_arr;
    }

    public function is_predefined( $role_data )
    {
        if( !($role_arr = $this->data_to_array( $role_data ))
         or empty( $role_arr['predefined'] ) )
            return false;

        return $role_arr;
    }

    public function activate_role( $role_data, $params = false )
    {
        if( empty( $role_data )
         or !($role_arr = $this->data_to_array( $role_data )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Unknown account.' ) );
            return false;
        }

        if( $this->is_active( $role_arr ) )
            return $role_arr;

        $edit_arr = array();
        $edit_arr['status'] = self::STATUS_ACTIVE;

        $edit_params = $this->fetch_default_flow_params( array( 'table_name' => 'roles' ) );
        $edit_params['fields'] = $edit_arr;

        return $this->edit( $role_arr, $edit_params );
    }

    public function inactivate_role( $role_data, $params = false )
    {
        if( empty( $role_data )
         or !($role_arr = $this->data_to_array( $role_data )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Unknown account.' ) );
            return false;
        }

        if( $this->is_inactive( $role_arr ) )
            return $role_arr;

        $edit_arr = array();
        $edit_arr['status'] = self::STATUS_INACTIVE;

        $edit_params = $this->fetch_default_flow_params( array( 'table_name' => 'roles' ) );
        $edit_params['fields'] = $edit_arr;

        return $this->edit( $role_arr, $edit_params );
    }

    public function delete_role( $role_data, $params = false )
    {
        if( empty( $role_data )
         or !($role_arr = $this->data_to_array( $role_data )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Unknown account.' ) );
            return false;
        }

        if( $this->is_deleted( $role_arr ) )
            return $role_arr;

        $edit_arr = array();
        $edit_arr['name'] = $role_arr['name'].'-DELETED-'.time();
        $edit_arr['slug'] = $role_arr['slug'].'-DELETED-'.time();
        $edit_arr['status'] = self::STATUS_DELETED;

        $edit_params = array();
        $edit_params['fields'] = $edit_arr;

        if( !($edit_result = $this->edit( $role_arr, $edit_params )) )
            return false;

        $this->unlink_all_role_units_from_role( $role_arr );
        $this->unlink_role_from_all_users( $role_arr );
        // Reset any error set by unlink_all_role_units_from_role or unlink_role_from_all_users (role was already marked as deleted)
        $this->reset_error();

        return $edit_result;
    }

    private function load_dependencies()
    {
        if( empty( self::$_accounts_model ) )
        {
            if( !(self::$_accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
            {
                $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error loading accounts model.' ) );
                return false;
            }
        }

        return true;
    }

    /**
     * Try transforming a string to role or role unit slug.
     *
     * @param string $str String to be transformed in characters accepted as role slug
     *
     * @return string Returns string containing resulting slug
     */
    public function transform_string_to_slug( $str )
    {
        $str = trim( (string)$str );
        if( empty( $str ) )
            return '';

        return str_replace( '__', '_', @preg_replace( '/[^a-zA-Z0-9_]+/', '_', $str ) );
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
            'plugin' => '',
        );
    }

    public function get_all_role_units( $force = false )
    {
        static $all_role_units = false;

        if( empty( $force )
        and $all_role_units !== false )
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
        $list_arr['order_by'] = 'roles_units.plugin ASC, roles_units.name ASC';

        if( !($all_role_units = $this->get_list( $list_arr )) )
            $all_role_units = array();

        return $all_role_units;
    }

    public function get_all_role_units_by_slug( $force = false )
    {
        static $all_role_units = false;

        if( empty( $force )
        and $all_role_units !== false )
            return $all_role_units;

        $all_role_units = array();

        if( !($role_units_by_id = $this->get_all_role_units( $force ))
         or !is_array( $role_units_by_id ) )
            return $all_role_units;

        foreach( $role_units_by_id as $role_unit_id => $role_unit_arr )
        {
            $all_role_units[$role_unit_arr['slug']] = $role_unit_arr;
        }

        return $all_role_units;
    }

    public function get_role_unit_by_slug( $slug, $force = false )
    {
        if( empty( $slug ) or !is_string( $slug )
         or !($role_units_arr = $this->get_all_role_units_by_slug( $force ))
         or empty( $role_units_arr[$slug] ) )
            return false;

        return $role_units_arr[$slug];
    }

    public function get_all_role_units_by_slug_list( $slug_arr, $force = false  )
    {
        if( empty( $slug_arr ) or !is_array( $slug_arr )
         or !($role_units_arr = $this->get_all_role_units_by_slug( $force )) )
            return array();

        $return_arr = array();
        foreach( $slug_arr as $slug )
        {
            if( empty( $role_units_arr[$slug] ) )
                continue;

            $return_arr[$role_units_arr[$slug]['id']] = $role_units_arr[$slug];
        }

        return $return_arr;
    }

    public function get_all_roles( $force = false )
    {
        static $all_roles = false;

        if( empty( $force )
        and $all_roles !== false )
            return $all_roles;

        if( !($model_settings = $this->get_db_settings()) )
            $model_settings = array();

        if( empty( $model_settings['roles_cache_size'] ) )
            $model_settings['roles_cache_size'] = 1000;

        $all_roles = array();

        $list_arr = $this->fetch_default_flow_params( array( 'table_name' => 'roles' ) );
        // Raise this limit if you have more units...
        $list_arr['enregs_no'] = $model_settings['roles_cache_size'];
        $list_arr['order_by'] = 'roles.plugin ASC, roles.name ASC';

        if( !($all_roles = $this->get_list( $list_arr )) )
            $all_roles = array();

        return $all_roles;
    }

    public function get_all_roles_by_slug( $force = false )
    {
        static $all_roles = false;

        if( empty( $force )
        and $all_roles !== false )
            return $all_roles;

        $all_roles = array();

        if( !($roles_by_id = $this->get_all_roles( $force ))
         or !is_array( $roles_by_id ) )
            return $all_roles;

        foreach( $roles_by_id as $role_id => $role_arr )
        {
            $all_roles[$role_arr['slug']] = $role_arr;
        }

        return $all_roles;
    }

    public function get_role_by_slug( $slug, $force = false )
    {
        if( empty( $slug ) or !is_string( $slug )
         or !($roles_arr = $this->get_all_roles_by_slug( $force ))
         or empty( $roles_arr[$slug] ) )
            return false;

        return $roles_arr[$slug];
    }

    public function get_all_roles_by_slug_list( $slug_arr, $force = false  )
    {
        if( empty( $slug_arr ) or !is_array( $slug_arr )
         or !($roles_arr = $this->get_all_roles_by_slug( $force )) )
            return array();

        $return_arr = array();
        foreach( $slug_arr as $slug )
        {
            if( empty( $roles_arr[$slug] ) )
                continue;

            $return_arr[$roles_arr[$slug]['id']] = $roles_arr[$slug];
        }

        return $return_arr;
    }

    /**
     * Called first in insert flow.
     * Parses flow parameters if anything special should be done.
     * This should do checks on raw parameters received by insert method.
     *
     * @param array|false $params Parameters in the flow
     *
     * @return array|bool Flow parameters array
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
            $params['{role_units}'] = array();

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
            if( empty( $params['{role_units_params}'] ) )
                $params['{role_units_params}'] = false;

            if( !$this->link_role_units_to_role( $insert_arr, $params['{role_units}'], $params['{role_units_params}'] ) )
                return false;
        }

        return $insert_arr;
    }

    public function get_roles_ids_for_roles_units_list( $role_units_arr )
    {
        if( empty( $role_units_arr ) or !is_array( $role_units_arr )
         or !($flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'roles_units_links' ) ))
         or !($role_units_ids = $this->role_units_list_to_ids( $role_units_arr ))
         or !is_array( $role_units_ids )
         or !($qid = db_query( 'SELECT role_id FROM `'.$this->get_flow_table_name( $flow_params ).'` '.
                               ' WHERE role_unit_id IN ('.@implode( ',', $role_units_ids ).')', $flow_params['db_connection'] ))
         or !@mysqli_num_rows( $qid ) )
            return array();

        $return_arr = array();
        while( ($link_arr = @mysqli_fetch_assoc( $qid )) )
        {
            $return_arr[] = $link_arr['role_id'];
        }

        return $return_arr;
    }

    public function get_roles_ids_for_roles_units_list_grouped( $role_units_arr )
    {
        if( empty( $role_units_arr ) or !is_array( $role_units_arr )
         or !($flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'roles_units_links' ) ))
         or !($role_units_ids = $this->role_units_list_to_ids( $role_units_arr ))
         or !is_array( $role_units_ids )
         or !($qid = db_query( 'SELECT role_id, role_unit_id FROM `'.$this->get_flow_table_name( $flow_params ).'` '.
                               ' WHERE role_unit_id IN ('.@implode( ',', $role_units_ids ).')', $flow_params['db_connection'] ))
         or !@mysqli_num_rows( $qid ) )
            return array();

        $return_arr = array();
        while( ($link_arr = @mysqli_fetch_assoc( $qid )) )
        {
            if( empty( $return_arr[$link_arr['role_unit_id']] ) )
                $return_arr[$link_arr['role_unit_id']] = array();

            $return_arr[$link_arr['role_unit_id']][] = $link_arr['role_id'];
        }

        return $return_arr;
    }

    public function get_role_ids_for_user( $user_id )
    {
        $this->reset_error();

        $user_id = intval( $user_id );
        if( empty( $user_id )
         or !($flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'roles_users' ) ))
         or !($qid = db_query( 'SELECT * FROM `'.$this->get_flow_table_name( $flow_params ).'` WHERE user_id = \''.$user_id.'\'', $this->get_db_connection( $flow_params ) ))
         or !@mysqli_num_rows( $qid ) )
            return array();

        $return_arr = array();
        while( ($link_arr = @mysqli_fetch_assoc( $qid )) )
        {
            $return_arr[] = $link_arr['role_id'];
        }

        return $return_arr;
    }

    public function get_role_unit_ids_for_role( $role_id )
    {
        $this->reset_error();

        $role_id = intval( $role_id );
        if( empty( $role_id )
         or !($flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'roles_units_links' ) ))
         or !($qid = db_query( 'SELECT * FROM `'.$this->get_flow_table_name( $flow_params ).'` WHERE role_id = \''.$role_id.'\'', $this->get_db_connection( $flow_params ) ))
         or !@mysqli_num_rows( $qid ) )
            return array();

        $return_arr = array();
        while( ($link_arr = @mysqli_fetch_assoc( $qid )) )
        {
            $return_arr[] = $link_arr['role_unit_id'];
        }

        return $return_arr;
    }

    /**
     * Convert a list of ids, slugs or role arrays into an array of role ids (which are currently defined)
     *
     * @param array $roles_arr List of roles passed as ids, slugs or role array
     * @param bool $fresh_roles Tells $this->get_all_roles_by_slug_list() method to force querying database
     *
     * @return array
     */
    public function roles_list_to_ids( $roles_arr, $fresh_roles = false )
    {
        if( empty( $roles_arr ) or !is_array( $roles_arr ) )
            return array();

        $role_ids_arr = array();
        $role_slugs_arr = array();
        foreach( $roles_arr as $role_data )
        {
            // check if number is passed as string
            if( is_scalar( $role_data )
            and (string)intval( $role_data ) === (string)$role_data )
                $role_data = intval( $role_data );

            if( is_string( $role_data ) )
                // slug
                $role_slugs_arr[] = $role_data;
            elseif( is_int( $role_data ) )
                $role_ids_arr[$role_data] = true;
            elseif( is_array( $role_data )
                and !empty( $role_data['id'] ) )
                $role_ids_arr[$role_data['id']] = true;
        }

        if( !empty( $role_slugs_arr ) )
        {
            if( ($found_roles = $this->get_all_roles_by_slug_list( $role_slugs_arr, $fresh_roles ))
            and is_array( $found_roles ) )
            {
                foreach( $found_roles as $role_id => $role_arr )
                {
                    $role_ids_arr[$role_id] = true;
                }
            }
        }

        // Values are in keys to be sure they are unique
        return array_keys( $role_ids_arr );
    }

    /**
     * Convert a list of ids, slugs or role unit arrays into an array of role units ids (which are currently defined)
     *
     * @param array $role_units_arr List of role units passed as ids, slugs or role unit array
     * @param bool $fresh_role_units Tells $this->get_all_role_units_by_slug_list() method to force querying database
     *
     * @return array
     */
    public function role_units_list_to_ids( $role_units_arr, $fresh_role_units = false )
    {
        if( empty( $role_units_arr ) or !is_array( $role_units_arr ) )
            return array();

        $unit_ids_arr = array();
        $unit_slugs_arr = array();
        foreach( $role_units_arr as $unit_data )
        {
            // check if number is passed as string
            if( is_scalar( $unit_data )
                and (string)intval( $unit_data ) === (string)$unit_data )
                $unit_data = intval( $unit_data );

            if( is_string( $unit_data ) )
                // slug
                $unit_slugs_arr[] = $unit_data;
            elseif( is_int( $unit_data ) )
                $unit_ids_arr[$unit_data] = true;
            elseif( is_array( $unit_data )
                and !empty( $unit_data['id'] ) )
                $unit_ids_arr[$unit_data['id']] = true;
        }

        if( !empty( $unit_slugs_arr ) )
        {
            if( ($found_role_units = $this->get_all_role_units_by_slug_list( $unit_slugs_arr, $fresh_role_units ))
            and is_array( $found_role_units ) )
            {
                foreach( $found_role_units as $role_unit_id => $role_unit_arr )
                {
                    $unit_ids_arr[$role_unit_id] = true;
                }
            }
        }

        // Values are in keys to be sure they are unique
        return array_keys( $unit_ids_arr );
    }

    /**
     * Un-links all role units from roles.
     *
     * @param array|int $role_data Role id or role array
     *
     * @return bool True on success, false on fail
     */
    public function unlink_all_role_units_from_role( $role_data )
    {
        $this->reset_error();

        if( !($role_arr = $this->data_to_array( $role_data )) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Role not found in database.' ) );
            return false;
        }

        if( !($flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'roles_units_links' ) ))
         or !db_query( 'DELETE FROM `'.$this->get_flow_table_name( $flow_params ).'` WHERE role_id = \''.$role_arr['id'].'\'', $this->get_db_connection( $flow_params ) ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Couldn\'t unlink role units from role.' ) );
            return false;
        }

        return true;
    }

    /**
     * Links role units to roles. We assume role units were already created.
     *
     * @param array|int $role_data Role id or role array
     * @param array $role_units_arr Role units passed as slugs, id or role unit array
     * @param bool|array $params Functionality parameters
     *
     * @return bool
     */
    public function unlink_role_units_from_role( $role_data, $role_units_arr, $params = false )
    {
        $this->reset_error();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !is_array( $role_units_arr ) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'No role units provided to unlink from role.' ) );
            return false;
        }

        if( !($role_arr = $this->data_to_array( $role_data )) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Role not found in database.' ) );
            return false;
        }

        if( !($role_unit_ids = $this->role_units_list_to_ids( $role_units_arr )) )
            return true;

        if( !empty( $role_unit_ids )
        and (!($flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'roles_units_links' ) ))
             or !db_query( 'DELETE FROM `'.$this->get_flow_table_name( $flow_params ).'` WHERE role_id = \''.$role_arr['id'].'\' AND role_unit_id IN ('.implode( ',', $role_unit_ids ).')', $this->get_db_connection( $flow_params ) )
            ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Couldn\'t unlink role units from role.' ) );
            return false;
        }

        return true;
    }

    /**
     * Links role units to roles. We assume role units were already created.
     *
     * @param array|int $role_data Role id or role array
     * @param array $role_units_arr Role units passed as slugs, id or role unit array
     * @param bool|array $params Functionality parameters
     *
     * @return bool
     */
    public function link_role_units_to_role( $role_data, $role_units_arr, $params = false )
    {
        $this->reset_error();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !is_array( $role_units_arr ) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'No role units provided to link to role.' ) );
            return false;
        }

        if( !($role_arr = $this->data_to_array( $role_data )) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Role not found in database.' ) );
            return false;
        }

        if( !($flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'roles_units_links' ) )) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Invalid flow parameters.' ) );
            return false;
        }

        if( !isset( $params['append_role_units'] ) )
            $params['append_role_units'] = true;

        $db_connection = $this->get_db_connection( $flow_params );

        if( empty( $role_units_arr ) )
        {
            if( !empty( $params['append_role_units'] ) )
                return true;

            // Unlink all roles...
            if( !db_query( 'DELETE FROM `'.$this->get_flow_table_name( $flow_params ).'` WHERE role_id = \''.$role_arr['id'].'\'', $db_connection ) )
            {
                $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error un-linking old role units from role.' ) );
                return false;
            }
        } else
        {
            if( !($existing_unit_ids = $this->get_role_unit_ids_for_role( $role_arr['id'] )) )
                $existing_unit_ids = array();

            // Role unit ids we have to set
            if( !($unit_ids_arr = $this->role_units_list_to_ids( $role_units_arr, true )) )
                $unit_ids_arr = array();

            $insert_ids = array();
            $delete_ids = array();
            foreach( $unit_ids_arr as $role_unit_id )
            {
                if( !in_array( $role_unit_id, $existing_unit_ids ) )
                    $insert_ids[] = $role_unit_id;
            }

            foreach( $insert_ids as $role_unit_id )
            {
                if( !db_query( 'INSERT INTO `'.$this->get_flow_table_name( $flow_params ).'` SET role_id = \''.$role_arr['id'].'\', role_unit_id = \''.$role_unit_id.'\'', $db_connection ) )
                {
                    $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error linking all role units to role.' ) );
                    return false;
                }
            }

            if( empty( $params['append_role_units'] ) )
            {
                foreach( $existing_unit_ids as $role_unit_id )
                {
                    if( !in_array( $role_unit_id, $unit_ids_arr ) )
                        $delete_ids[] = $role_unit_id;
                }

                if( !empty( $delete_ids)
                and !db_query( 'DELETE FROM `'.$this->get_flow_table_name( $flow_params ).'` WHERE role_id = \''.$role_arr['id'].'\' AND role_unit_id IN ('.implode( ',', $delete_ids ).')', $db_connection ) )
                {
                    $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error un-linking old role units from role.' ) );
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Un-links roles from one account.
     *
     * @param array|int $account_data Account id or account array
     * @param array $roles_arr Roles passed as slugs, id or role array
     * @param bool|array $params Functionality parameters
     *
     * @return bool
     */
    public function unlink_roles_from_user( $account_data, $roles_arr, $params = false )
    {
        $this->reset_error();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( self::$_accounts_model )
        and !$this->load_dependencies() )
            return false;

        if( !is_array( $roles_arr ) )
            $roles_arr = array( $roles_arr );

        if( empty( $roles_arr ) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'No roles provided to unlink from account.' ) );
            return false;
        }

        if( !($account_arr = self::$_accounts_model->data_to_array( $account_data )) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Account not found in database.' ) );
            return false;
        }

        if( !($role_ids = $this->roles_list_to_ids( $roles_arr )) )
            return true;

        if( !empty( $role_ids )
        and (!($flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'roles_users' ) ))
             or !db_query( 'DELETE FROM `'.$this->get_flow_table_name( $flow_params ).'` WHERE user_id = \''.$account_arr['id'].'\' AND role_id IN ('.implode( ',', $role_ids ).')', $this->get_db_connection( $flow_params ) )
            ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Couldn\'t unlink roles from account.' ) );
            return false;
        }

        return true;
    }

    /**
     * Un-links all roles from one account.
     *
     * @param array|int $account_data Account id or account array
     *
     * @return bool
     */
    public function unlink_all_roles_from_user( $account_data )
    {
        $this->reset_error();

        if( empty( self::$_accounts_model )
        and !$this->load_dependencies() )
            return false;

        if( !($account_arr = self::$_accounts_model->data_to_array( $account_data )) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Account not found in database.' ) );
            return false;
        }

        if( !($flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'roles_users' ) ))
         or !db_query( 'DELETE FROM `'.$this->get_flow_table_name( $flow_params ).'` WHERE user_id = \''.$account_arr['id'].'\'', $this->get_db_connection( $flow_params ) ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Couldn\'t unlink roles from account.' ) );
            return false;
        }

        return true;
    }

    /**
     * Un-links roles from accounts.
     *
     * @param array|int $role_data Role id or role array
     *
     * @return bool
     */
    public function unlink_role_from_all_users( $role_data )
    {
        $this->reset_error();

        if( !($role_arr = $this->data_to_array( $role_data )) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Role not found in database.' ) );
            return false;
        }

        if( !($flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'roles_users' ) ))
         or !db_query( 'DELETE FROM `'.$this->get_flow_table_name( $flow_params ).'` WHERE role_id = \''.$role_arr['id'].'\'', $this->get_db_connection( $flow_params ) ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Couldn\'t unlink role from all accounts.' ) );
            return false;
        }

        return true;
    }

    /**
     * Links role units to roles. We assume role units were already created.
     *
     * @param array|int $account_data Account id or account array
     * @param array $roles_arr Roles passed as slugs, id or role array
     * @param bool|array $params Functionality parameters
     *
     * @return bool
     */
    public function link_roles_to_user( $account_data, $roles_arr, $params = false )
    {
        $this->reset_error();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( self::$_accounts_model )
        and !$this->load_dependencies() )
            return false;

        if( !($account_arr = self::$_accounts_model->data_to_array( $account_data )) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Account not found in database.' ) );
            return false;
        }

        if( !($flow_params = $this->fetch_default_flow_params( array( 'table_name' => 'roles_users' ) )) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Invalid flow parameters.' ) );
            return false;
        }

        if( !is_array( $roles_arr ) )
            $roles_arr = array( $roles_arr );

        if( !isset( $params['append_roles'] ) )
            $params['append_roles'] = true;

        $db_connection = $this->get_db_connection( $flow_params );

        if( empty( $roles_arr ) )
        {
            if( !empty( $params['append_roles'] ) )
                return true;

            // Unlink all roles...
            if( !db_query( 'DELETE FROM `'.$this->get_flow_table_name( $flow_params ).'` WHERE user_id = \''.$account_arr['id'].'\'', $db_connection ) )
            {
                $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error un-linking old roles from account.' ) );
                return false;
            }
        } else
        {
            if( !($existing_ids = $this->get_role_ids_for_user( $account_arr['id'] )) )
                $existing_ids = array();

            // Role ids we have to set
            if( !($role_ids_arr = $this->roles_list_to_ids( $roles_arr, true )) )
                $role_ids_arr = array();

            $insert_ids = array();
            $delete_ids = array();
            foreach( $role_ids_arr as $role_id )
            {
                if( !in_array( $role_id, $existing_ids ) )
                    $insert_ids[] = $role_id;
            }

            foreach( $insert_ids as $role_id )
            {
                if( !db_query( 'INSERT INTO `'.$this->get_flow_table_name( $flow_params ).'` SET user_id = \''.$account_arr['id'].'\', role_id = \''.$role_id.'\'', $db_connection ) )
                {
                    $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error linking all roles to account.' ) );
                    return false;
                }
            }

            if( empty( $params['append_roles'] ) )
            {
                foreach( $existing_ids as $role_id )
                {
                    if( !in_array( $role_id, $role_ids_arr ) )
                        $delete_ids[] = $role_id;
                }

                if( !empty( $delete_ids )
                and !db_query( 'DELETE FROM `'.$this->get_flow_table_name( $flow_params ).'` WHERE user_id = \''.$account_arr['id'].'\' AND role_id IN ('.implode( ',', $delete_ids ).')', $db_connection ) )
                {
                    $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error un-linking old roles from account.' ) );
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Gets role units slugs for provided role. Role can be passed as Id, slug or role array
     *
     * @param int|string|array $role_data Id, slug or role array
     *
     * @return array|bool False on error or an array of slugs for provided role
     */
    public function get_role_role_units_slugs( $role_data )
    {
        if( !($flow_params_ru = $this->fetch_default_flow_params( array( 'table_name' => 'roles_units' ) ))
         or !($flow_params_rul = $this->fetch_default_flow_params( array( 'table_name' => 'roles_units_links' ) ))
         or !($roles_units_table = $this->get_flow_table_name( $flow_params_ru ))
         or !($roles_units_links_table = $this->get_flow_table_name( $flow_params_rul ))
         or !($ids_arr = $this->roles_list_to_ids( array( $role_data ) ))
         or !is_array( $ids_arr )
         or !($role_id = @array_shift( $ids_arr ))
         or !($qid = db_query( 'SELECT `'.$roles_units_table.'`.slug '.
                               ' FROM `'.$roles_units_table.'` '.
                               ' LEFT JOIN `'.$roles_units_links_table.'` ON `'.$roles_units_links_table.'`.role_unit_id = `'.$roles_units_table.'`.id '.
                               ' WHERE `'.$roles_units_links_table.'`.role_id = \''.intval( $role_id ).'\'', $this->get_db_connection( $flow_params_ru ) ))
         or !@mysqli_num_rows( $qid ) )
            return array();

        $return_arr = array();
        while( ($slug_arr = @mysqli_fetch_assoc( $qid )) )
        {
            $return_arr[] = $slug_arr['slug'];
        }

        return $return_arr;
    }

    /**
     * Gets role units slugs for provided roles. Roles can be passed as single slug, array of slugs or array of ids
     *
     * @param string|array $roles_slugs signle slug or array of role slugs / ids
     *
     * @return array|bool False on error or an array of slugs for provided roles
     */
    public function get_role_units_slugs_from_roles_slugs( $roles_slugs )
    {
        if( !is_array( $roles_slugs ) )
            $roles_slugs = array( $roles_slugs );

        if( !($flow_params_ru = $this->fetch_default_flow_params( array( 'table_name' => 'roles_units' ) ))
         or !($flow_params_rul = $this->fetch_default_flow_params( array( 'table_name' => 'roles_units_links' ) ))
         or !($roles_units_table = $this->get_flow_table_name( $flow_params_ru ))
         or !($roles_units_links_table = $this->get_flow_table_name( $flow_params_rul ))
         or !($ids_arr = $this->roles_list_to_ids( $roles_slugs ))
         or !($qid = db_query( 'SELECT `'.$roles_units_table.'`.slug '.
                               ' FROM `'.$roles_units_table.'` '.
                               ' LEFT JOIN `'.$roles_units_links_table.'` ON `'.$roles_units_links_table.'`.role_unit_id = `'.$roles_units_table.'`.id '.
                               ' WHERE `'.$roles_units_links_table.'`.role_id IN (\''.implode( '\', \'', $ids_arr ).'\')', $this->get_db_connection( $flow_params_ru ) ))
         or !@mysqli_num_rows( $qid ) )
            return array();

        $return_arr = array();
        while( ($slug_arr = @mysqli_fetch_assoc( $qid )) )
        {
            $return_arr[] = $slug_arr['slug'];
        }

        return $return_arr;
    }

    /**
     * Tells if a set of roles are assigned to provided account_data. account_data can be a valid user account (id or array) or an empty account array (not logged in user)
     *
     * @param array|int $account_data Account id, account array, or an empty account array. If array provided and $accounts_model::ROLES_USER_KEY key is defined it will be used directly
     * @param array|string $roles_list Single slug or array of ids, slugs or role arrays (can be mixed with ids, slugs or arrays)
     * @param array|bool $params Functional parameters
     *
     * @return array|bool False if logical operation doesn't match list of roles with roles assigned to provided account or an array with account details and matched roles slugs
     */
    public function user_has_roles( $account_data, $roles_list, $params = false )
    {
        $this->reset_error();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        $logical_operations = array( 'and', 'or' );

        if( empty( $params['logical_operation'] ) )
            $params['logical_operation'] = 'and';
        else
            $params['logical_operation'] = strtolower( trim( $params['logical_operation'] ) );

        if( !is_array( $roles_list ) )
            $roles_list = array( $roles_list );

        if( !in_array( $params['logical_operation'], $logical_operations )
         or (empty( self::$_accounts_model ) and !$this->load_dependencies())
         or !($all_role_ids = $this->get_all_roles())
         or !($role_ids = $this->roles_list_to_ids( $roles_list ))
         or !is_array( $role_ids ) )
            return false;

        $accounts_model = self::$_accounts_model;

        $account_slugs = array();
        $account_arr = false;
        if( is_array( $account_data ) )
        {
            $account_arr = $account_data;
            if( isset( $account_arr[$accounts_model::ROLES_USER_KEY] ) and is_array( $account_arr[$accounts_model::ROLES_USER_KEY] ) )
                $account_slugs = $account_arr[$accounts_model::ROLES_USER_KEY];

            else
            {
                if( !($account_slugs = $this->get_user_roles_slugs( $account_arr )) )
                    $account_slugs = array();

                $account_arr[$accounts_model::ROLES_USER_KEY] = $account_slugs;
            }
        } elseif( is_scalar( $account_data ) )
        {
            $account_id = intval( $account_data );
            if( (string)$account_id !== (string)$account_data
             or !($account_arr = $accounts_model->get_details( $account_id )) )
                $account_arr = false;

            else
            {
                if( !($account_slugs = $this->get_user_roles_slugs( $account_arr )) )
                    $account_slugs = array();

                $account_arr[$accounts_model::ROLES_USER_KEY] = $account_slugs;
            }
        }

        if( empty( $account_slugs ) or !is_array( $account_slugs )
         or !($account_role_ids = $this->roles_list_to_ids( $account_slugs ))
         or !is_array( $account_role_ids ) )
            return false;

        $matching_slugs_arr = array();
        foreach( $role_ids as $role_id )
        {
            if( empty( $all_role_ids[$role_id] ) // sanity check
             or empty( $all_role_ids[$role_id]['slug'] )
             or !in_array( $role_id, $account_role_ids ) )
            {
                // If all should match return false when we find one that is not assigned to account
                if( $params['logical_operation'] == 'and' )
                    return false;

                continue;
            }

            $matching_slugs_arr[] = $all_role_ids[$role_id]['slug'];
        }

        // Nothing matched
        if( empty( $matching_slugs_arr ) )
            return false;

        $return_arr = array();
        $return_arr['account_data'] = $account_arr;
        $return_arr['matching_slugs'] = $matching_slugs_arr;

        return $return_arr;
    }

    /**
     * Tells if a set of role units are assigned to provided account_data. account_data can be a valid user account (id or array) or an empty account array (not logged in user)
     *
     * @param array|int $account_data Account id, account array, or an empty account array. If array provided and $accounts_model::ROLE_UNITS_USER_KEY key is defined it will be used directly
     * @param array|string $role_units_list Single slug or array of ids, slugs or role unit arrays (can be mixed with ids, slugs or arrays)
     * @param array|bool $params Functional parameters
     *
     * @return array|bool False if logical operation doesn't match list of role units with role units assigned to provided account or an array with account details and matched role units slugs
     */
    public function user_has_role_units( $account_data, $role_units_list, $params = false )
    {
        $this->reset_error();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        $logical_operations = array( 'and', 'or' );

        if( empty( $params['logical_operation'] ) )
            $params['logical_operation'] = 'and';
        else
            $params['logical_operation'] = strtolower( trim( $params['logical_operation'] ) );

        if( !is_array( $role_units_list ) )
            $role_units_list = array( $role_units_list );

        if( !in_array( $params['logical_operation'], $logical_operations )
         or (empty( self::$_accounts_model ) and !$this->load_dependencies())
         or !($all_role_unit_ids = $this->get_all_role_units())
         or !($role_unit_ids = $this->role_units_list_to_ids( $role_units_list ))
         or !is_array( $role_unit_ids ) )
            return false;

        $accounts_model = self::$_accounts_model;

        $account_slugs = array();
        $account_arr = false;
        if( is_array( $account_data ) )
        {
            $account_arr = $account_data;
            if( isset( $account_arr[$accounts_model::ROLE_UNITS_USER_KEY] ) and is_array( $account_arr[$accounts_model::ROLE_UNITS_USER_KEY] ) )
                $account_slugs = $account_arr[$accounts_model::ROLE_UNITS_USER_KEY];

            else
            {
                if( !($account_slugs = $this->get_user_role_units_slugs( $account_arr )) )
                    $account_slugs = array();

                $account_arr[$accounts_model::ROLE_UNITS_USER_KEY] = $account_slugs;
            }
        } elseif( is_scalar( $account_data ) )
        {
            $account_id = intval( $account_data );
            if( (string)$account_id !== (string)$account_data
             or !($account_arr = $accounts_model->get_details( $account_id )) )
                $account_arr = false;

            else
            {
                if( !($account_slugs = $this->get_user_role_units_slugs( $account_arr )) )
                    $account_slugs = array();

                $account_arr[$accounts_model::ROLE_UNITS_USER_KEY] = $account_slugs;
            }
        }

        if( empty( $account_slugs ) or !is_array( $account_slugs )
         or !($account_role_unit_ids = $this->role_units_list_to_ids( $account_slugs ))
         or !is_array( $account_role_unit_ids ) )
            return false;

        $matching_slugs_arr = array();
        foreach( $role_unit_ids as $role_unit_id )
        {
            if( empty( $all_role_unit_ids[$role_unit_id] ) // sanity check
             or empty( $all_role_unit_ids[$role_unit_id]['slug'] )
             or !in_array( $role_unit_id, $account_role_unit_ids ) )
            {
                // If all should match return false when we find one that is not assigned to account
                if( $params['logical_operation'] == 'and' )
                    return false;

                continue;
            }

            $matching_slugs_arr[] = $all_role_unit_ids[$role_unit_id]['slug'];
        }

        // Nothing matched
        if( empty( $matching_slugs_arr ) )
            return false;

        $return_arr = array();
        $return_arr['account_data'] = $account_arr;
        $return_arr['matching_slugs'] = $matching_slugs_arr;

        return $return_arr;
    }

    public function get_user_roles_slugs( $account_data )
    {
        $this->reset_error();

        if( empty( self::$_accounts_model )
        and !$this->load_dependencies() )
            return false;

        if( !($account_arr = self::$_accounts_model->data_to_array( $account_data )) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Account not found in database.' ) );
            return false;
        }

        if( !($role_ids = $this->get_role_ids_for_user( $account_arr['id'] ))
         or !is_array( $role_ids )
         or !($all_roles = $this->get_all_roles()) )
            return array();

        $return_arr = array();
        foreach( $role_ids as $role_id )
        {
            if( empty( $all_roles[$role_id] ) )
                continue;

            $return_arr[] = $all_roles[$role_id]['slug'];
        }

        return $return_arr;
    }

    public function get_user_role_units_slugs( $account_data )
    {
        $this->reset_error();

        if( empty( self::$_accounts_model )
         && !$this->load_dependencies() )
            return false;

        if( !($account_arr = self::$_accounts_model->data_to_array( $account_data )) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Account not found in database.' ) );
            return false;
        }

        if( !($role_ids = $this->get_role_ids_for_user( $account_arr['id'] ))
         || !is_array( $role_ids )
         || !($flow_params_ru = $this->fetch_default_flow_params( [ 'table_name' => 'roles_units' ] ))
         || !($flow_params_rul = $this->fetch_default_flow_params( [ 'table_name' => 'roles_units_links' ] ))
         || !($roles_units_table = $this->get_flow_table_name( $flow_params_ru ))
         || !($roles_units_links_table = $this->get_flow_table_name( $flow_params_rul ))
         || !($qid = db_query( 'SELECT `'.$roles_units_table.'`.slug '.
                               ' FROM `'.$roles_units_table.'` '.
                               ' LEFT JOIN `'.$roles_units_links_table.'` ON `'.$roles_units_links_table.'`.role_unit_id = `'.$roles_units_table.'`.id '.
                               ' WHERE `'.$roles_units_links_table.'`.role_id IN ('.implode( ',', $role_ids ).')', $this->get_db_connection( $flow_params_ru ) ))
         || !@mysqli_num_rows( $qid ) )
            return [];

        $return_arr = [];
        while( ($slug_arr = @mysqli_fetch_assoc( $qid )) )
        {
            $return_arr[$slug_arr['slug']] = true;
        }

        // Make sure we have unique role unit slugs
        return (!empty( $return_arr )?@array_keys( $return_arr ):[]);
    }

    /**
     * Called first in edit flow.
     * Parses flow parameters if anything special should be done.
     * This should do checks on raw parameters received by edit method.
     *
     * @param array|int $existing_data Data which already exists in database (id or full array with all database fields)
     * @param array|false $params Parameters in the flow
     *
     * @return array|bool Flow parameters array
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

        if( !isset( $params['{role_units}'] ) or !is_array( $params['{role_units}'] ) )
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
     * @return array|bool Returns data array added in database (with changes, if required) or false if record should be deleted from database.
     * Deleted record will be hard-deleted
     */
    protected function edit_after_roles( $existing_data, $edit_arr, $params )
    {
        if( is_array( $params['{role_units}'] ) )
        {
            if( empty( $params['{role_units_params}'] ) )
                $params['{role_units_params}'] = false;

            if( !$this->link_role_units_to_role( $existing_data, $params['{role_units}'], $params['{role_units_params}'] ) )
                return false;
        }

        return $existing_data;
    }

    /**
     * Called first in insert flow.
     * Parses flow parameters if anything special should be done.
     * This should do checks on raw parameters received by insert method.
     *
     * @param array|bool $params Parameters in the flow
     *
     * @return array|bool Flow parameters array
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
     * @return array|bool Flow parameters array
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
     * @param array|bool $params Parameters in the flow
     *
     * @return array|bool Flow parameters array
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
     * @return array|bool Flow parameters array
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
     * @param array|bool $params Parameters in the flow
     *
     * @return array|bool Flow parameters array
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
     * @return array|bool Flow parameters array
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
                    'plugin' => array(
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
                    'plugin' => array(
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
