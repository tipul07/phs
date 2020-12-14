<?php

namespace phs\libraries;

use \phs\PHS;

//! Main wrapper for roles (roles are defined by plugins/functionalities and are pushed to this class)
class PHS_Roles extends PHS_Registry
{
    const ERR_DEPENDENCIES = 1;

    const ROLE_GUEST = 'phs_guest', ROLE_MEMBER = 'phs_member', ROLE_OPERATOR = 'phs_operator', ROLE_ADMIN = 'phs_admin';

    const ROLEU_CONTACT_US = 'phs_contact_us', ROLEU_REGISTER = 'phs_register',
          ROLEU_MANAGE_ROLES = 'phs_manage_roles', ROLEU_LIST_ROLES = 'phs_list_roles',
          ROLEU_MANAGE_PLUGINS = 'phs_manage_plugins', ROLEU_LIST_PLUGINS = 'phs_list_plugins',
          ROLEU_MANAGE_ACCOUNTS = 'phs_manage_accounts', ROLEU_LIST_ACCOUNTS = 'phs_list_accounts',
          ROLEU_LOGIN_SUBACCOUNT = 'phs_login_subaccount',
          ROLEU_MANAGE_AGENT_JOBS = 'phs_manage_agent_jobs', ROLEU_LIST_AGENT_JOBS = 'phs_list_agent_jobs',
          ROLEU_MANAGE_API_KEYS = 'phs_manage_api_keys', ROLEU_LIST_API_KEYS = 'phs_list_api_keys',
          ROLEU_VIEW_LOGS = 'phs_view_logs';

    /** @var bool|\phs\system\core\models\PHS_Model_Roles $_role_model  */
    private static $_role_model = false;

    private static function load_dependencies()
    {
        self::st_reset_error();

        if( empty( self::$_role_model )
        and !(self::$_role_model = PHS::load_model( 'roles' )) )
        {
            self::st_set_error( self::ERR_DEPENDENCIES, self::_t( 'Couldn\'t load roles model.' ) );
            return false;
        }

        return true;
    }

    public static function transform_string_to_slug( $str )
    {
        self::st_reset_error();

        if( !self::load_dependencies() )
            return false;

        $role_model = self::$_role_model;

        return $role_model->transform_string_to_slug( $str );
   }

    public static function user_has_role( $account_data, $role_list, $params = false )
    {
        self::st_reset_error();

        if( !self::load_dependencies() )
            return false;

        $role_model = self::$_role_model;

        if( ($return_arr = $role_model->user_has_roles( $account_data, $role_list, $params )) === false )
        {
            self::st_copy_error( $role_model );
            return false;
        }

        return $return_arr;
   }

    public static function user_has_role_units( $account_data, $role_units_list, $params = false )
    {
        self::st_reset_error();

        if( !self::load_dependencies() )
            return false;

        $role_model = self::$_role_model;

        if( ($return_arr = $role_model->user_has_role_units( $account_data, $role_units_list, $params )) === false )
        {
            self::st_copy_error( $role_model );
            return false;
        }

        return $return_arr;
   }

    public static function get_user_roles_slugs( $account_data )
    {
        self::st_reset_error();

        if( !self::load_dependencies() )
            return false;

        $role_model = self::$_role_model;

        if( ($slugs_arr = $role_model->get_user_roles_slugs( $account_data )) === false )
        {
            self::st_copy_error( $role_model );
            return false;
        }

        return $slugs_arr;
    }

    public static function get_user_role_units_slugs( $account_data )
    {
        self::st_reset_error();

        if( !self::load_dependencies() )
            return false;

        $role_model = self::$_role_model;

        if( ($slugs_arr = $role_model->get_user_role_units_slugs( $account_data )) === false )
        {
            self::st_copy_error( $role_model );
            return false;
        }

        return $slugs_arr;
    }

    public static function get_role_role_units_slugs( $role_data )
    {
        self::st_reset_error();

        if( !self::load_dependencies() )
            return false;

        $role_model = self::$_role_model;

        if( ($slugs_arr = $role_model->get_role_role_units_slugs( $role_data )) === false )
        {
            self::st_copy_error( $role_model );
            return false;
        }

        return $slugs_arr;
    }

    public static function get_role_units_slugs_from_roles_slugs( $roles_slugs )
    {
        self::st_reset_error();

        if( !self::load_dependencies() )
            return false;

        $role_model = self::$_role_model;

        if( ($slugs_arr = $role_model->get_role_units_slugs_from_roles_slugs( $roles_slugs )) === false )
        {
            self::st_copy_error( $role_model );
            return false;
        }

        return $slugs_arr;
    }

    public static function link_roles_to_user( $account_data, $role_data, $params = false )
    {
        self::st_reset_error();

        if( !self::load_dependencies() )
            return false;

        $role_model = self::$_role_model;

        if( $role_model->link_roles_to_user( $account_data, $role_data, $params ) === false )
        {
            self::st_copy_error( $role_model );
            return false;
        }

        return true;
    }

    public static function unlink_roles_from_user( $account_data, $role_data, $params = false )
    {
        self::st_reset_error();

        if( !self::load_dependencies() )
            return false;

        $role_model = self::$_role_model;

        if( $role_model->unlink_roles_from_user( $account_data, $role_data, $params ) === false )
        {
            self::st_copy_error( $role_model );
            return false;
        }

        return true;
    }

    public static function unlink_all_roles_from_user( $account_data )
    {
        self::st_reset_error();

        if( !self::load_dependencies() )
            return false;

        $role_model = self::$_role_model;

        if( $role_model->unlink_all_roles_from_user( $account_data ) === false )
        {
            self::st_copy_error( $role_model );
            return false;
        }

        return true;
    }

    public static function register_role( $params )
    {
        self::st_reset_error();

        if( !self::load_dependencies() )
            return false;

        if( empty( $params ) || !is_array( $params ) )
        {
            self::st_set_error( self::ERR_PARAMETERS, self::_t( 'Please provide valid parameters for this role.' ) );
            return false;
        }

        if( empty( $params['slug'] ) )
        {
            self::st_set_error( self::ERR_PARAMETERS, self::_t( 'Please provide a slug for this role.' ) );
            return false;
        }

        $role_units_arr = false;
        if( !empty( $params['{role_units}'] ) && is_array( $params['{role_units}'] ) )
            $role_units_arr = $params['{role_units}'];

        if( isset( $params['{role_units}'] ) )
            unset( $params['{role_units}'] );

        $role_model = self::$_role_model;

        $constrain_arr = [];
        $constrain_arr['slug'] = $params['slug'];

        $check_params = $role_model->fetch_default_flow_params( [ 'table_name' => 'roles' ] );

        if( ($role_arr = $role_model->get_details_fields( $constrain_arr, $check_params )) )
        {
            if( !empty( $role_arr['plugin'] ) && !empty( $params['plugin'] )
             && (string)$role_arr['plugin'] !== (string)$params['plugin'] )
            {
                self::st_set_error( self::ERR_PARAMETERS,
                                    self::_t( 'Error adding role [%s] there is already a role with same slug from other plugin.', $params['slug'] ) );
                return false;
            }

            $edit_fields_arr = [];
            $check_fields = $role_model::get_register_edit_role_unit_fields();

            foreach( $check_fields as $key => $def_val )
            {
                if( array_key_exists( $key, $role_arr )
                 && array_key_exists( $key, $params )
                 && (string)$role_arr[$key] !== (string)$params[$key] )
                    $edit_fields_arr[$key] = $params[$key];
            }

            if( empty( $role_arr['plugin'] )
             && !empty( $params['plugin'] ) )
                $edit_fields_arr['plugin'] = $params['plugin'];

            if( $role_model->is_deleted( $role_arr ) )
                $edit_fields_arr['status'] = $role_model::STATUS_ACTIVE;

            if( !empty( $edit_fields_arr ) )
            {
                $edit_arr = $role_model->fetch_default_flow_params( [ 'table_name' => 'roles' ] );
                $edit_arr['fields'] = $edit_fields_arr;

                // if we have an error because edit didn't work, don't throw error as this is not something major...
                if( ($new_existing_arr = $role_model->edit( $role_arr, $edit_arr )) )
                    $role_arr = $new_existing_arr;
            }

            // Don't append role units as we might remove some role units from role...
            if( !empty( $role_units_arr ) )
                $role_model->link_role_units_to_role( $role_arr, $role_units_arr, [ 'append_role_units' => false ] );

        } else
        {
            if( empty( $params['name'] ) )
                $params['name'] = $params['slug'];

            $params['status'] = $role_model::STATUS_ACTIVE;

            $insert_arr = $role_model->fetch_default_flow_params( [ 'table_name' => 'roles' ] );
            $insert_arr['fields'] = $params;
            $insert_arr['{role_units}'] = $role_units_arr;

            if( !($role_arr = $role_model->insert( $insert_arr )) )
            {
                if( $role_model->has_error() )
                    self::st_copy_error( $role_model );
                else
                    self::st_set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error adding role [%s] to database.', $params['slug'] ) );

                return false;
            }
        }

        return $role_arr;
    }

    public static function register_role_unit( $params )
    {
        self::st_reset_error();

        if( !self::load_dependencies() )
            return false;

        if( empty( $params ) or !is_array( $params ) )
        {
            self::st_set_error( self::ERR_PARAMETERS, self::_t( 'Please provide valid parameters for this role unit.' ) );
            return false;
        }

        if( empty( $params['slug'] ) )
        {
            self::st_set_error( self::ERR_PARAMETERS, self::_t( 'Please provide a slug for this role unit.' ) );
            return false;
        }

        $role_model = self::$_role_model;

        $constrain_arr = array();
        $constrain_arr['slug'] = $params['slug'];

        $check_params = array();
        $check_params['table_name'] = 'roles_units';
        $check_params['result_type'] = 'single';
        $check_params['details'] = '*';

        if( ($role_unit_arr = $role_model->get_details_fields( $constrain_arr, $check_params )) )
        {
            // TODO: check $role_arr['plugin'] if it is same as $params['plugin'] (don't allow other plugins to overwrite role units)
            $edit_fields_arr = array();
            $check_fields = $role_model::get_register_edit_role_unit_fields();

            foreach( $check_fields as $key => $def_val )
            {
                if( array_key_exists( $key, $role_unit_arr )
                and array_key_exists( $key, $params )
                and (string)$role_unit_arr[$key] !== (string)$params[$key] )
                    $edit_fields_arr[$key] = $params[$key];
            }

            if( empty( $role_unit_arr['plugin'] )
            and !empty( $params['plugin'] ) )
                $edit_fields_arr['plugin'] = $params['plugin'];

            if( $role_model->is_deleted( $role_unit_arr ) )
                $edit_fields_arr['status'] = $role_model::STATUS_ACTIVE;

            if( !empty( $edit_fields_arr ) )
            {
                $edit_arr = $role_model->fetch_default_flow_params( array( 'table_name' => 'roles_units' ) );
                $edit_arr['fields'] = $edit_fields_arr;

                // if we have an error because edit didn't work, don't throw error as this is not something major...
                if( ($new_existing_arr = $role_model->edit( $role_unit_arr, $edit_arr )) )
                    $role_unit_arr = $new_existing_arr;
            }
        } else
        {
            if( empty( $params['name'] ) )
                $params['name'] = $params['slug'];

            $params['status'] = $role_model::STATUS_ACTIVE;

            $insert_arr = $role_model->fetch_default_flow_params( array( 'table_name' => 'roles_units' ) );
            $insert_arr['fields'] = $params;

            if( !($role_unit_arr = $role_model->insert( $insert_arr )) )
            {
                if( $role_model->has_error() )
                    self::st_copy_error( $role_model );
                else
                    self::st_set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error adding role unit [%s] to database.', $params['slug'] ) );

                return false;
            }
        }

        return $role_unit_arr;
    }
}
