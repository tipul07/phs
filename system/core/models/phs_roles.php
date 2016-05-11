<?php

namespace phs\system\core\models;

use \phs\PHS;
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

    /** @var bool|\phs\plugins\accounts\models\PHS_Model_Accounts $_accounts_model */
    private static $_accounts_model = false;

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

    public function get_all_role_units_by_slug( $force = false )
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
        $list_arr['order_by'] = 'roles_units.name ASC';
        $list_arr['arr_index_field'] = 'slug';

        if( !($all_role_units = $this->get_list( $list_arr )) )
            $all_role_units = array();

        return $all_role_units;
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

        $list_arr = array();
        // Raise this limit if you have more units...
        $list_arr['enregs_no'] = $model_settings['roles_cache_size'];
        $list_arr['order_by'] = 'roles.name ASC';

        if( !($all_roles = $this->get_list( $list_arr )) )
            $all_roles = array();

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

    public function get_all_roles_by_slug( $force = false )
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

        $list_arr = array();
        $list_arr['table_name'] = 'roles';
        // Raise this limit if you have more roles...
        $list_arr['enregs_no'] = $model_settings['roles_cache_size'];
        $list_arr['order_by'] = 'roles.name ASC';
        $list_arr['arr_index_field'] = 'slug';

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
            if( !$this->link_role_units_to_role( $insert_arr, $params['{role_units}'] ) )
                return false;
        }

        return $insert_arr;
    }

    public function get_role_ids_for_user( $user_id )
    {
        $this->reset_error();

        $user_id = intval( $user_id );
        if( empty( $user_id )
         or !($qid = db_query( 'SELECT * FROM roles_users WHERE user_id = \''.$user_id.'\'', $this->get_db_connection() ))
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
         or !($qid = db_query( 'SELECT * FROM roles_units_links WHERE role_id = \''.$role_id.'\'', $this->get_db_connection() ))
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
        and !db_query( 'DELETE FROM roles_units_links WHERE role_id = \''.$role_arr['id'].'\' AND role_unit_id IN ('.implode( ',', $role_unit_ids ).')', $this->get_db_connection() ) )
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

        if( !isset( $params['append_role_units'] ) )
            $params['append_role_units'] = true;

        $db_connection = $this->get_db_connection();

        if( empty( $role_units_arr ) )
        {
            if( !empty( $params['append_role_units'] ) )
                return true;

            // Unlink all roles...
            if( !db_query( 'DELETE FROM roles_units_links WHERE role_id = \''.$role_arr['id'].'\'', $db_connection ) )
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
                if( !db_query( 'INSERT INTO roles_units_links SET role_id = \''.$role_arr['id'].'\', role_unit_id = \''.$role_unit_id.'\'', $db_connection ) )
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
                and !db_query( 'DELETE FROM roles_units_links WHERE role_id = \''.$role_arr['id'].'\' AND role_unit_id IN ('.implode( ',', $delete_ids ).')', $db_connection ) )
                {
                    $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error un-linking old role units from role.' ) );
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Links roles to accounts. We assume roles were already created.
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
        and !db_query( 'DELETE FROM roles_users WHERE user_id = \''.$account_arr['id'].'\' AND role_unit_id IN ('.implode( ',', $role_ids ).')', $this->get_db_connection() ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Couldn\'t unlink roles from account.' ) );
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

        if( !is_array( $roles_arr ) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'No roles provided to link to account.' ) );
            return false;
        }

        if( !($account_arr = self::$_accounts_model->data_to_array( $account_data )) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Account not found in database.' ) );
            return false;
        }

        if( !isset( $params['append_roles'] ) )
            $params['append_roles'] = true;

        $db_connection = $this->get_db_connection();

        if( empty( $roles_arr ) )
        {
            if( !empty( $params['append_roles'] ) )
                return true;

            // Unlink all roles...
            if( !db_query( 'DELETE FROM roles_users WHERE user_id = \''.$account_arr['id'].'\'', $db_connection ) )
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
                if( !db_query( 'INSERT INTO roles_users SET user_id = \''.$account_arr['id'].'\', role_id = \''.$role_id.'\'', $db_connection ) )
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
                and !db_query( 'DELETE FROM roles_users WHERE user_id = \''.$account_arr['id'].'\' AND role_id IN ('.implode( ',', $delete_ids ).')', $db_connection ) )
                {
                    $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error un-linking old roles from account.' ) );
                    return false;
                }
            }
        }

        return true;
    }

    public function get_user_role_units_slugs( $account_data )
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
     * @return array|false Returns data array added in database (with changes, if required) or false if record should be deleted from database.
     * Deleted record will be hard-deleted
     */
    protected function edit_after_roles( $existing_data, $edit_arr, $params )
    {
        if( is_array( $params['{role_units}'] ) )
        {
            if( !$this->link_role_units_to_role( $existing_data, $params['{role_units}'] ) )
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
