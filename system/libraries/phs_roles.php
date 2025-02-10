<?php
namespace phs\libraries;

use phs\PHS;
use phs\system\core\models\PHS_Model_Roles;

// ! Main wrapper for roles (roles are defined by plugins/functionalities and are pushed to this class)
class PHS_Roles extends PHS_Registry
{
    public const ROLE_GUEST = 'phs_guest', ROLE_MEMBER = 'phs_member', ROLE_OPERATOR = 'phs_operator', ROLE_ADMIN = 'phs_admin',
        ROLE_TENANT_ADMIN = 'phs_tenant_admin', ROLE_PLATFORM_OPERATIONS = 'phs_platform_operations';

    public const ROLEU_CONTACT_US = 'phs_contact_us', ROLEU_REGISTER = 'phs_register',
        ROLEU_MANAGE_ROLES = 'phs_manage_roles', ROLEU_LIST_ROLES = 'phs_list_roles',
        ROLEU_MANAGE_PLUGINS = 'phs_manage_plugins', ROLEU_LIST_PLUGINS = 'phs_list_plugins',
        ROLEU_EXPORT_PLUGINS_SETTINGS = 'phs_export_plugins_settings',
        ROLEU_IMPORT_PLUGINS_SETTINGS = 'phs_import_plugins_settings',
        ROLEU_MANAGE_ACCOUNTS = 'phs_manage_accounts', ROLEU_LIST_ACCOUNTS = 'phs_list_accounts',
        ROLEU_LOGIN_SUBACCOUNT = 'phs_login_subaccount',
        ROLEU_EXPORT_ACCOUNTS = 'phs_accounts_export', ROLEU_IMPORT_ACCOUNTS = 'phs_accounts_import',
        ROLEU_MANAGE_AGENT_JOBS = 'phs_manage_agent_jobs', ROLEU_LIST_AGENT_JOBS = 'phs_list_agent_jobs',
        ROLEU_MANAGE_API_KEYS = 'phs_manage_api_keys', ROLEU_LIST_API_KEYS = 'phs_list_api_keys',
        ROLEU_API_MONITORING_REPORT = 'phs_api_monitor_report',
        ROLEU_VIEW_LOGS = 'phs_view_logs',
        ROLEU_MANAGE_MIGRATIONS = 'phs_manage_migrations', ROLEU_LIST_MIGRATIONS = 'phs_list_migrations',
        ROLEU_MANAGE_DATA_RETENTION = 'phs_manage_data_retention', ROLEU_LIST_DATA_RETENTION = 'phs_list_data_retention',
        ROLEU_LIST_HTTP_CALLS = 'phs_list_http_calls', ROLEU_MANAGE_HTTP_CALLS = 'phs_manage_http_calls',

        // Tenant role units
        ROLEU_TENANTS_LIST = 'phs_list_tenants', ROLEU_TENANTS_MANAGE = 'phs_manage_tenants';

    /** @var null|PHS_Model_Roles */
    private static ?PHS_Model_Roles $_role_model = null;

    public static function transform_string_to_slug($str) : ?string
    {
        self::st_reset_error();

        if (!self::load_dependencies()) {
            return null;
        }

        return self::$_role_model->transform_string_to_slug($str);
    }

    public static function user_has_role(int | array $account_data, string | array $role_list, array $params = []) : ?array
    {
        self::st_reset_error();

        if (!self::load_dependencies()) {
            return null;
        }

        if (empty($account_data)
            || null === ($return_arr = self::$_role_model->user_has_roles($account_data, $role_list, $params))) {
            self::st_copy_or_set_error(self::$_role_model,
                self::ERR_FUNCTIONALITY, self::_t('Error checking user roles.'));

            return null;
        }

        return $return_arr;
    }

    public static function user_has_role_units(
        null | bool | int | array | PHS_Record_data $account_data,
        string | array $role_units_list,
        ?array $params = null
    ) : ?array {
        self::st_reset_error();

        if (!self::load_dependencies()) {
            return null;
        }

        $params ??= [];

        if (empty($account_data)
            || null === ($return_arr = self::$_role_model->user_has_role_units($account_data, $role_units_list, $params))) {
            self::st_copy_or_set_error(self::$_role_model,
                self::ERR_FUNCTIONALITY, self::_t('Error checking user role units.'));

            return null;
        }

        return $return_arr;
    }

    public static function get_user_roles_slugs(null | bool | int | array | PHS_Record_data $account_data) : ?array
    {
        if (!self::load_dependencies()) {
            return null;
        }

        if (empty($account_data)
            || null === ($slugs_arr = self::$_role_model->get_user_roles_slugs($account_data))) {
            self::st_copy_or_set_error(self::$_role_model,
                self::ERR_FUNCTIONALITY, self::_t('Error obtaining user roles slugs.'));

            return null;
        }

        return $slugs_arr;
    }

    public static function get_user_role_units_slugs(null | bool | int | array | PHS_Record_data $account_data) : ?array
    {
        if (!self::load_dependencies()) {
            return null;
        }

        if (empty($account_data)
            || null === ($slugs_arr = self::$_role_model->get_user_role_units_slugs($account_data))) {
            self::st_copy_or_set_error(self::$_role_model,
                self::ERR_FUNCTIONALITY, self::_t('Error obtaining user role units slugs.'));

            return null;
        }

        return $slugs_arr;
    }

    public static function get_role_role_units_slugs(int | string | array | PHS_Record_data $role_data) : ?array
    {
        self::st_reset_error();

        if (!self::load_dependencies()) {
            return null;
        }

        if (!($slugs_arr = self::$_role_model->get_role_role_units_slugs($role_data))) {
            if (self::$_role_model->has_error()) {
                self::st_copy_error(self::$_role_model);

                return null;
            }

            return [];
        }

        return $slugs_arr;
    }

    public static function get_role_units_slugs_from_roles_slugs(int | string | array | PHS_Record_data $roles_slugs) : ?array
    {
        if (!self::load_dependencies()) {
            return null;
        }

        if (!($slugs_arr = self::$_role_model->get_role_units_slugs_from_roles_slugs($roles_slugs))) {
            if (self::$_role_model->has_error()) {
                self::st_copy_error(self::$_role_model);

                return null;
            }

            return [];
        }

        return $slugs_arr;
    }

    public static function link_roles_to_user(int | array | PHS_Record_data $account_data, $role_data, array $params = []) : bool
    {
        if (!self::load_dependencies()) {
            return false;
        }

        if (!self::$_role_model->link_roles_to_user($account_data, $role_data, $params)) {
            self::st_copy_error(self::$_role_model);

            return false;
        }

        return true;
    }

    public static function unlink_roles_from_user(int | array | PHS_Record_data $account_data, string | array $role_data) : bool
    {
        if (!self::load_dependencies()) {
            return false;
        }

        if (!self::$_role_model->unlink_roles_from_user($account_data, $role_data)) {
            self::st_copy_error(self::$_role_model);

            return false;
        }

        return true;
    }

    public static function unlink_all_roles_from_user(int | array | PHS_Record_data $account_data) : bool
    {
        if (!self::load_dependencies()) {
            return false;
        }

        if (!self::$_role_model->unlink_all_roles_from_user($account_data)) {
            self::st_copy_error(self::$_role_model);

            return false;
        }

        return true;
    }

    public static function register_role(array $params) : ?array
    {
        if (!self::load_dependencies()) {
            return null;
        }

        if (empty($params)) {
            self::st_set_error(self::ERR_PARAMETERS, self::_t('Please provide valid parameters for this role.'));

            return null;
        }

        if (empty($params['slug'])) {
            self::st_set_error(self::ERR_PARAMETERS, self::_t('Please provide a slug for this role.'));

            return null;
        }

        $role_units_arr = false;
        if (!empty($params['{role_units}']) && is_array($params['{role_units}'])) {
            $role_units_arr = $params['{role_units}'];
        }

        if (isset($params['{role_units}'])) {
            unset($params['{role_units}']);
        }

        $constrain_arr = [];
        $constrain_arr['slug'] = $params['slug'];

        $check_params = self::$_role_model->fetch_default_flow_params(['table_name' => 'roles']);

        if (($role_arr = self::$_role_model->get_details_fields($constrain_arr, $check_params))) {
            if (!empty($role_arr['plugin']) && !empty($params['plugin'])
                && (string)$role_arr['plugin'] !== (string)$params['plugin']) {
                self::st_set_error(self::ERR_PARAMETERS,
                    self::_t('Error adding role [%s] there is already a role with same slug from other plugin.', $params['slug']));

                return null;
            }

            $check_fields = self::$_role_model::get_register_edit_role_unit_fields();

            $edit_fields_arr = [];
            foreach ($check_fields as $key => $def_val) {
                if (array_key_exists($key, $role_arr)
                    && array_key_exists($key, $params)
                    && (string)$role_arr[$key] !== (string)$params[$key]) {
                    $edit_fields_arr[$key] = $params[$key];
                }
            }

            if (empty($role_arr['plugin'])
                && !empty($params['plugin'])) {
                $edit_fields_arr['plugin'] = $params['plugin'];
            }

            if (self::$_role_model->is_deleted($role_arr)) {
                $edit_fields_arr['status'] = self::$_role_model::STATUS_ACTIVE;
            }

            if (!empty($edit_fields_arr)) {
                $edit_arr = self::$_role_model->fetch_default_flow_params(['table_name' => 'roles']);
                $edit_arr['fields'] = $edit_fields_arr;

                // if we have an error because edit didn't work, don't throw error as this is not something major...
                if (($new_existing_arr = self::$_role_model->edit($role_arr, $edit_arr))) {
                    $role_arr = $new_existing_arr;
                }
            }

            // Don't append role units as we might remove some role units from role...
            if (!empty($role_units_arr)) {
                self::$_role_model->link_role_units_to_role($role_arr, $role_units_arr, ['append_role_units' => false]);
            }
        } else {
            if (empty($params['name'])) {
                $params['name'] = $params['slug'];
            }

            $params['status'] = self::$_role_model::STATUS_ACTIVE;

            $insert_arr = self::$_role_model->fetch_default_flow_params(['table_name' => 'roles']);
            $insert_arr['fields'] = $params;
            $insert_arr['{role_units}'] = $role_units_arr;

            if (!($role_arr = self::$_role_model->insert($insert_arr))) {
                self::st_copy_or_set_error(self::$_role_model,
                    self::ERR_FUNCTIONALITY, self::_t('Error adding role [%s] to database.', $params['slug']));

                return null;
            }
        }

        return $role_arr;
    }

    /**
     * @param array $params
     *
     * @return null|array
     */
    public static function register_role_unit(array $params) : ?array
    {
        self::st_reset_error();

        if (!self::load_dependencies()) {
            return null;
        }

        if (empty($params)) {
            self::st_set_error(self::ERR_PARAMETERS, self::_t('Please provide valid parameters for this role unit.'));

            return null;
        }

        if (empty($params['slug'])) {
            self::st_set_error(self::ERR_PARAMETERS, self::_t('Please provide a slug for this role unit.'));

            return null;
        }

        $role_model = self::$_role_model;

        $constrain_arr = [];
        $constrain_arr['slug'] = $params['slug'];

        $check_params = [];
        $check_params['table_name'] = 'roles_units';
        $check_params['result_type'] = 'single';
        $check_params['details'] = '*';

        if (($role_unit_arr = self::$_role_model->get_details_fields($constrain_arr, $check_params))) {
            // TODO: check $role_arr['plugin'] if it is same as $params['plugin'] (don't allow other plugins to overwrite role units)
            $edit_fields_arr = [];
            $check_fields = self::$_role_model::get_register_edit_role_unit_fields();

            foreach ($check_fields as $key => $def_val) {
                if (array_key_exists($key, $role_unit_arr)
                 && array_key_exists($key, $params)
                 && (string)$role_unit_arr[$key] !== (string)$params[$key]) {
                    $edit_fields_arr[$key] = $params[$key];
                }
            }

            if (empty($role_unit_arr['plugin'])
             && !empty($params['plugin'])) {
                $edit_fields_arr['plugin'] = $params['plugin'];
            }

            if (self::$_role_model->is_deleted($role_unit_arr)) {
                $edit_fields_arr['status'] = self::$_role_model::STATUS_ACTIVE;
            }

            if (!empty($edit_fields_arr)) {
                $edit_arr = self::$_role_model->fetch_default_flow_params(['table_name' => 'roles_units']);
                $edit_arr['fields'] = $edit_fields_arr;

                // if we have an error because edit didn't work, don't throw error as this is not something major...
                if (($new_existing_arr = self::$_role_model->edit($role_unit_arr, $edit_arr))) {
                    $role_unit_arr = $new_existing_arr;
                }
            }
        } else {
            if (empty($params['name'])) {
                $params['name'] = $params['slug'];
            }

            $params['status'] = self::$_role_model::STATUS_ACTIVE;

            $insert_arr = self::$_role_model->fetch_default_flow_params(['table_name' => 'roles_units']);
            $insert_arr['fields'] = $params;

            if (!($role_unit_arr = self::$_role_model->insert($insert_arr))) {
                if (self::$_role_model->has_error()) {
                    self::st_copy_error(self::$_role_model);
                } else {
                    self::st_set_error(self::ERR_FUNCTIONALITY, self::_t('Error adding role unit [%s] to database.', $params['slug']));
                }

                return null;
            }
        }

        return $role_unit_arr;
    }

    private static function load_dependencies() : bool
    {
        self::st_reset_error();

        if (empty(self::$_role_model)
         && !(self::$_role_model = PHS_Model_Roles::get_instance())) {
            self::st_set_error(self::ERR_DEPENDENCIES, self::_t('Error loading required resources.'));

            return false;
        }

        return true;
    }
}
