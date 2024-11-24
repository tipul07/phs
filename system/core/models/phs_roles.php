<?php

namespace phs\system\core\models;

use phs\libraries\PHS_Model;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Record_data;
use phs\traits\PHS_Model_Trait_statuses;
use phs\plugins\accounts\models\PHS_Model_Accounts;

class PHS_Model_Roles extends PHS_Model
{
    use PHS_Model_Trait_statuses;

    public const ERR_READ = 10000, ERR_WRITE = 10001;

    public const STATUS_INACTIVE = 1, STATUS_ACTIVE = 2, STATUS_DELETED = 3, STATUS_SUSPENDED = 4;

    protected static array $STATUSES_ARR = [
        self::STATUS_INACTIVE  => ['title' => 'Inactive'],
        self::STATUS_ACTIVE    => ['title' => 'Active'],
        self::STATUS_DELETED   => ['title' => 'Deleted'],
        self::STATUS_SUSPENDED => ['title' => 'Suspended'],
    ];

    private static ?PHS_Model_Accounts $_accounts_model = null;

    public function get_model_version() : string
    {
        return '1.0.2';
    }

    public function get_table_names() : array
    {
        return ['roles', 'roles_units', 'roles_units_links', 'roles_users'];
    }

    public function get_main_table_name() : string
    {
        return 'roles';
    }

    public function get_settings_structure() : array
    {
        return [
            'roles_cache_size' => [
                'display_name' => 'Role cache size',
                'display_hint' => 'How many records to read from roles table. Increase this value if you use more roles.',
                'type'         => PHS_Params::T_INT,
                'default'      => 1000,
            ],
            'units_cache_size' => [
                'display_name' => 'Role units cache size',
                'display_hint' => 'How many records to read from role units table. Increase this value if you use more role units.',
                'type'         => PHS_Params::T_INT,
                'default'      => 1000,
            ],
        ];
    }

    public function is_active(int | array | PHS_Record_data $role_data) : bool
    {
        return ($role_arr = $this->data_to_array($role_data))
               && (int)$role_arr['status'] === self::STATUS_ACTIVE;
    }

    public function is_inactive(int | array | PHS_Record_data $role_data) : bool
    {
        return ($role_arr = $this->data_to_array($role_data))
               && (int)$role_arr['status'] === self::STATUS_INACTIVE;
    }

    public function is_deleted(int | array | PHS_Record_data $role_data) : bool
    {
        return ($role_arr = $this->data_to_array($role_data))
               && (int)$role_arr['status'] === self::STATUS_DELETED;
    }

    public function is_suspended(int | array | PHS_Record_data $role_data) : bool
    {
        return ($role_arr = $this->data_to_array($role_data))
               && (int)$role_arr['status'] === self::STATUS_SUSPENDED;
    }

    public function is_predefined(int | array | PHS_Record_data $role_data) : bool
    {
        return ($role_arr = $this->data_to_array($role_data))
               && !empty($role_arr['predefined']);
    }

    public function activate_role(int | array | PHS_Record_data $role_data) : ?array
    {
        $this->reset_error();

        if (empty($role_data)
         || !($role_arr = $this->data_to_array($role_data))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Role not found in database.'));

            return null;
        }

        if ($this->is_active($role_arr)) {
            return $role_arr;
        }

        $edit_params = $this->fetch_default_flow_params(['table_name' => 'roles']);
        $edit_params['fields'] = [
            'status' => self::STATUS_ACTIVE,
        ];

        if (!($new_role = $this->edit($role_arr, $edit_params))) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error saving role details in database.'));

            return null;
        }

        return $new_role;
    }

    public function inactivate_role(int | array | PHS_Record_data $role_data) : ?array
    {
        $this->reset_error();

        if (empty($role_data)
         || !($role_arr = $this->data_to_array($role_data))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Role not found in database.'));

            return null;
        }

        if ($this->is_inactive($role_arr)) {
            return $role_arr;
        }

        $edit_params = $this->fetch_default_flow_params(['table_name' => 'roles']);
        $edit_params['fields'] = [
            'status' => self::STATUS_INACTIVE,
        ];

        if (!($new_role = $this->edit($role_arr, $edit_params))) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error saving role details in database.'));

            return null;
        }

        return $new_role;
    }

    public function delete_role(int | array | PHS_Record_data $role_data) : ?array
    {
        $this->reset_error();

        if (empty($role_data)
         || !($role_arr = $this->data_to_array($role_data))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Role not found in database.'));

            return null;
        }

        if ($this->is_deleted($role_arr)) {
            return $role_arr;
        }

        $edit_params = $this->fetch_default_flow_params(['table_name' => 'roles']);
        $edit_params['fields'] = [
            'name'   => $role_arr['name'].'-DELETED-'.time(),
            'slug'   => $role_arr['slug'].'-DELETED-'.time(),
            'status' => self::STATUS_DELETED,
        ];

        if (!($edit_result = $this->edit($role_arr, $edit_params))) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error saving role details in database.'));

            return null;
        }

        $this->unlink_all_role_units_from_role($role_arr);
        $this->unlink_role_from_all_users($role_arr);
        // Reset any error set by unlink_all_role_units_from_role or unlink_role_from_all_users (role was already marked as deleted)
        $this->reset_error();

        return $edit_result;
    }

    /**
     * Try transforming a string to role or role unit slug.
     *
     * @param string $str String to be transformed in characters accepted as role slug
     *
     * @return string Returns string containing resulting slug
     */
    public function transform_string_to_slug(string $str) : string
    {
        $str = trim($str);
        if (empty($str)) {
            return '';
        }

        return str_replace('__', '_', @preg_replace('/[^a-zA-Z0-9_]+/', '_', $str));
    }

    public function get_all_role_units(bool $force = false) : array
    {
        static $all_role_units = null;

        if (empty($force)
            && $all_role_units !== null) {
            return $all_role_units;
        }

        $model_settings = $this->get_db_settings();
        $model_settings['units_cache_size'] = (int)($model_settings['units_cache_size'] ?? 1000);

        $all_role_units = [];

        $list_arr = [];
        $list_arr['table_name'] = 'roles_units';
        // Raise this limit if you have more units...
        $list_arr['enregs_no'] = $model_settings['units_cache_size'];
        $list_arr['order_by'] = 'roles_units.plugin ASC, roles_units.name ASC';

        if (!($all_role_units = $this->get_list($list_arr))) {
            $all_role_units = [];
        }

        return $all_role_units;
    }

    public function get_all_role_units_by_slug(bool $force = false) : array
    {
        static $all_role_units = null;

        if (empty($force)
            && $all_role_units !== null) {
            return $all_role_units;
        }

        $all_role_units = [];

        if (!($role_units_by_id = $this->get_all_role_units($force))) {
            return $all_role_units;
        }

        foreach ($role_units_by_id as $role_unit_id => $role_unit_arr) {
            $all_role_units[$role_unit_arr['slug']] = $role_unit_arr;
        }

        return $all_role_units;
    }

    public function get_role_unit_by_slug($slug, $force = false)
    {
        if (empty($slug) || !is_string($slug)
         || !($role_units_arr = $this->get_all_role_units_by_slug($force))
         || empty($role_units_arr[$slug])) {
            return false;
        }

        return $role_units_arr[$slug];
    }

    public function get_all_role_units_by_slug_list(array $slug_arr, bool $force = false)
    {
        if (empty($slug_arr)
            || !($role_units_arr = $this->get_all_role_units_by_slug($force))) {
            return [];
        }

        $return_arr = [];
        foreach ($slug_arr as $slug) {
            if (empty($role_units_arr[$slug])) {
                continue;
            }

            $return_arr[$role_units_arr[$slug]['id']] = $role_units_arr[$slug];
        }

        return $return_arr;
    }

    public function get_all_roles(bool $force = false) : array
    {
        static $all_roles = null;

        if (empty($force)
            && $all_roles !== null) {
            return $all_roles;
        }

        $model_settings = $this->get_db_settings();
        $model_settings['roles_cache_size'] = (int)($model_settings['roles_cache_size'] ?? 1000);

        $list_arr = $this->fetch_default_flow_params(['table_name' => 'roles']);
        // Raise this limit if you have more units...
        $list_arr['enregs_no'] = $model_settings['roles_cache_size'];
        $list_arr['order_by'] = 'roles.plugin ASC, roles.name ASC';

        if (!($all_roles = $this->get_list($list_arr))) {
            $all_roles = [];
        }

        return $all_roles;
    }

    public function get_all_roles_by_slug(bool $force = false)
    {
        static $all_roles = null;

        if (empty($force)
            && $all_roles !== null) {
            return $all_roles;
        }

        $all_roles = [];

        if (!($roles_by_id = $this->get_all_roles($force))) {
            return $all_roles;
        }

        foreach ($roles_by_id as $role_id => $role_arr) {
            $all_roles[$role_arr['slug']] = $role_arr;
        }

        return $all_roles;
    }

    public function get_role_by_slug($slug, $force = false)
    {
        if (empty($slug) || !is_string($slug)
         || !($roles_arr = $this->get_all_roles_by_slug($force))
         || empty($roles_arr[$slug])) {
            return false;
        }

        return $roles_arr[$slug];
    }

    public function get_all_roles_by_slug_list(array $slug_arr, bool $force = false) : array
    {
        if (empty($slug_arr)
            || !($roles_arr = $this->get_all_roles_by_slug($force))) {
            return [];
        }

        $return_arr = [];
        foreach ($slug_arr as $slug) {
            if (empty($roles_arr[$slug])) {
                continue;
            }

            $return_arr[(int)$roles_arr[$slug]['id']] = $roles_arr[$slug];
        }

        return $return_arr;
    }

    public function get_roles_ids_for_roles_units_list($role_units_arr) : array
    {
        if (empty($role_units_arr) || !is_array($role_units_arr)
         || !($flow_params = $this->fetch_default_flow_params(['table_name' => 'roles_units_links']))
         || !($role_units_ids = $this->role_units_list_to_ids($role_units_arr))
         || !is_array($role_units_ids)
         || !($qid = db_query('SELECT role_id FROM `'.$this->get_flow_table_name($flow_params).'` '
                               .' WHERE role_unit_id IN ('.@implode(',', $role_units_ids).')', $flow_params['db_connection']))
         || !db_num_rows($qid, $flow_params['db_connection'])) {
            return [];
        }

        $return_arr = [];
        while (($link_arr = db_fetch_assoc($qid, $flow_params['db_connection']))) {
            $return_arr[] = $link_arr['role_id'];
        }

        return $return_arr;
    }

    public function get_roles_ids_for_roles_units_list_grouped($role_units_arr) : array
    {
        if (empty($role_units_arr) || !is_array($role_units_arr)
         || !($flow_params = $this->fetch_default_flow_params(['table_name' => 'roles_units_links']))
         || !($role_units_ids = $this->role_units_list_to_ids($role_units_arr))
         || !is_array($role_units_ids)
         || !($qid = db_query('SELECT role_id, role_unit_id FROM `'.$this->get_flow_table_name($flow_params).'` '
                               .' WHERE role_unit_id IN ('.@implode(',', $role_units_ids).')', $flow_params['db_connection']))
         || !db_num_rows($qid, $flow_params['db_connection'])) {
            return [];
        }

        $return_arr = [];
        while (($link_arr = db_fetch_assoc($qid, $flow_params['db_connection']))) {
            $role_unit_id = (int)$link_arr['role_unit_id'];
            if (empty($return_arr[$role_unit_id])) {
                $return_arr[$role_unit_id] = [];
            }

            $return_arr[$role_unit_id][] = (int)$link_arr['role_id'];
        }

        return $return_arr;
    }

    public function get_role_ids_for_user(int $user_id) : array
    {
        $this->reset_error();

        if (empty($user_id)
         || !($flow_params = $this->fetch_default_flow_params(['table_name' => 'roles_users']))
         || !($qid = db_query('SELECT * FROM `'.$this->get_flow_table_name($flow_params).'` '
                               .' WHERE user_id = \''.$user_id.'\'', $flow_params['db_connection']))
         || !db_num_rows($qid, $flow_params['db_connection'])) {
            return [];
        }

        $return_arr = [];
        while (($link_arr = db_fetch_assoc($qid, $flow_params['db_connection']))) {
            $return_arr[] = (int)$link_arr['role_id'];
        }

        return $return_arr;
    }

    public function get_role_unit_ids_for_role($role_id) : array
    {
        $this->reset_error();

        $role_id = (int)$role_id;
        if (empty($role_id)
         || !($flow_params = $this->fetch_default_flow_params(['table_name' => 'roles_units_links']))
         || !($qid = db_query('SELECT * FROM `'.$this->get_flow_table_name($flow_params).'` '
                               .' WHERE role_id = \''.$role_id.'\'', $flow_params['db_connection']))
         || !db_num_rows($qid, $flow_params['db_connection'])) {
            return [];
        }

        $return_arr = [];
        while (($link_arr = db_fetch_assoc($qid, $flow_params['db_connection']))) {
            $return_arr[] = (int)$link_arr['role_unit_id'];
        }

        return $return_arr;
    }

    /**
     * Convert a list of ids, slugs or role arrays into an array of role ids (which are currently defined)
     *
     * @param array $roles_arr List of roles passed as ids, slugs or role (array or PHS_Record_data)
     * @param bool $fresh_roles Tells $this->get_all_roles_by_slug_list() method to force querying database
     *
     * @return array
     */
    public function roles_list_to_ids(array $roles_arr, bool $fresh_roles = false) : array
    {
        if (empty($roles_arr)) {
            return [];
        }

        $role_ids_arr = [];
        $role_slugs_arr = [];
        foreach ($roles_arr as $role_data) {
            // check if number is passed as string
            if (is_scalar($role_data)
                && (string)((int)$role_data) === (string)$role_data) {
                $role_data = (int)$role_data;
            }

            if (is_string($role_data)) {
                // slug
                $role_slugs_arr[] = $role_data;
            } elseif (is_int($role_data)) {
                $role_ids_arr[$role_data] = true;
            } elseif (!empty($role_data['id'])) {
                $role_ids_arr[(int)$role_data['id']] = true;
            }
        }

        if (!empty($role_slugs_arr)
            && ($found_roles = $this->get_all_roles_by_slug_list($role_slugs_arr, $fresh_roles))) {
            foreach ($found_roles as $role_id => $role_arr) {
                $role_ids_arr[(int)$role_id] = true;
            }
        }

        // Values are in keys to be sure they are unique
        return array_keys($role_ids_arr);
    }

    /**
     * Convert a list of ids, slugs or role unit arrays into an array of role units ids (which are currently defined)
     *
     * @param array $role_units_arr List of role units passed as ids, slugs or role unit array
     * @param bool $fresh_role_units Tells $this->get_all_role_units_by_slug_list() method to force querying database
     *
     * @return array
     */
    public function role_units_list_to_ids(array $role_units_arr, bool $fresh_role_units = false) : array
    {
        if (empty($role_units_arr)) {
            return [];
        }

        $unit_ids_arr = [];
        $unit_slugs_arr = [];
        foreach ($role_units_arr as $unit_data) {
            // check if number is passed as string
            if (is_scalar($unit_data)
                && (string)((int)$unit_data) === (string)$unit_data) {
                $unit_data = (int)$unit_data;
            }

            if (is_string($unit_data)) {
                // slug
                $unit_slugs_arr[] = $unit_data;
            } elseif (is_int($unit_data)) {
                $unit_ids_arr[$unit_data] = true;
            } elseif (!empty($unit_data['id'])) {
                $unit_ids_arr[(int)$unit_data['id']] = true;
            }
        }

        if (!empty($unit_slugs_arr)
            && ($found_role_units = $this->get_all_role_units_by_slug_list($unit_slugs_arr, $fresh_role_units))) {
            foreach ($found_role_units as $role_unit_id => $role_unit_arr) {
                $unit_ids_arr[(int)$role_unit_id] = true;
            }
        }

        // Values are in keys to be sure they are unique
        return array_keys($unit_ids_arr);
    }

    public function unlink_all_role_units_from_role(int | array | PHS_Record_data $role_data) : bool
    {
        $this->reset_error();

        if (empty($role_data)
            || !($role_arr = $this->data_to_array($role_data))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Role not found in database.'));

            return false;
        }

        if (!($flow_params = $this->fetch_default_flow_params(['table_name' => 'roles_units_links']))
            || !db_query('DELETE FROM `'.$this->get_flow_table_name($flow_params).'` WHERE role_id = \''.$role_arr['id'].'\'', $this->get_db_connection($flow_params))) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Couldn\'t unlink role units from role.'));

            return false;
        }

        return true;
    }

    /**
     * Links role units to roles. We assume role units were already created.
     *
     * @param array|int|PHS_Record_data $role_data Role id or role array
     * @param array $role_units_arr Role units passed as slugs, id or role unit array
     *
     * @return bool
     */
    public function unlink_role_units_from_role(int | array | PHS_Record_data $role_data, array $role_units_arr) : bool
    {
        $this->reset_error();

        if (!($role_arr = $this->data_to_array($role_data))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Role not found in database.'));

            return false;
        }

        if (!($role_unit_ids = $this->role_units_list_to_ids($role_units_arr))) {
            return true;
        }

        if (!empty($role_unit_ids)
        && (!($flow_params = $this->fetch_default_flow_params(['table_name' => 'roles_units_links']))
             || !db_query('DELETE FROM `'.$this->get_flow_table_name($flow_params).'` WHERE role_id = \''.$role_arr['id'].'\' AND role_unit_id IN ('.implode(',', $role_unit_ids).')', $this->get_db_connection($flow_params))
        )) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Couldn\'t unlink role units from role.'));

            return false;
        }

        return true;
    }

    /**
     * Links role units to roles. We assume role units were already created.
     *
     * @param int|array|PHS_Record_data $role_data Role id or role array
     * @param array $role_units_arr Role units passed as slugs, id or role unit array
     * @param null|array $params Functionality parameters
     *
     * @return bool
     */
    public function link_role_units_to_role(int | array | PHS_Record_data $role_data, $role_units_arr, ?array $params = null) : bool
    {
        $this->reset_error();

        if (empty($params)) {
            $params = [];
        }

        if (!is_array($role_units_arr)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('No role units provided to link to role.'));

            return false;
        }

        if (!($role_arr = $this->data_to_array($role_data))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Role not found in database.'));

            return false;
        }

        if (!($flow_params = $this->fetch_default_flow_params(['table_name' => 'roles_units_links']))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Invalid flow parameters.'));

            return false;
        }

        if (!isset($params['append_role_units'])) {
            $params['append_role_units'] = true;
        }

        $db_connection = $this->get_db_connection($flow_params);

        if (empty($role_units_arr)) {
            if (!empty($params['append_role_units'])) {
                return true;
            }

            // Unlink all roles...
            if (!db_query('DELETE FROM `'.$this->get_flow_table_name($flow_params).'` WHERE role_id = \''.$role_arr['id'].'\'', $db_connection)) {
                $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error un-linking old role units from role.'));

                return false;
            }
        } else {
            if (!($existing_unit_ids = $this->get_role_unit_ids_for_role($role_arr['id']))) {
                $existing_unit_ids = [];
            }

            // Role unit ids we have to set
            if (!($unit_ids_arr = $this->role_units_list_to_ids($role_units_arr, true))) {
                $unit_ids_arr = [];
            }

            $insert_ids = [];
            $delete_ids = [];
            foreach ($unit_ids_arr as $role_unit_id) {
                if (!in_array($role_unit_id, $existing_unit_ids)) {
                    $insert_ids[] = $role_unit_id;
                }
            }

            foreach ($insert_ids as $role_unit_id) {
                if (!db_query('INSERT INTO `'.$this->get_flow_table_name($flow_params).'` SET role_id = \''.$role_arr['id'].'\', role_unit_id = \''.$role_unit_id.'\'', $db_connection)) {
                    $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error linking all role units to role.'));

                    return false;
                }
            }

            if (empty($params['append_role_units'])) {
                foreach ($existing_unit_ids as $role_unit_id) {
                    if (!in_array($role_unit_id, $unit_ids_arr)) {
                        $delete_ids[] = $role_unit_id;
                    }
                }

                if (!empty($delete_ids)
                && !db_query('DELETE FROM `'.$this->get_flow_table_name($flow_params).'` WHERE role_id = \''.$role_arr['id'].'\' AND role_unit_id IN ('.implode(',', $delete_ids).')', $db_connection)) {
                    $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error un-linking old role units from role.'));

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
     * @param string|array $roles_arr Roles passed as slugs, id or role array
     * @param bool|array $params Functionality parameters
     *
     * @return bool
     */
    public function unlink_roles_from_user(int | array | PHS_Record_data $account_data, string | array $roles_arr) : bool
    {
        if (!$this->_load_dependencies()) {
            return false;
        }

        if (!is_array($roles_arr)) {
            $roles_arr = [$roles_arr];
        }

        if (empty($roles_arr)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('No roles provided to unlink from account.'));

            return false;
        }

        if (!($account_arr = self::$_accounts_model->data_to_array($account_data))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Account not found in database.'));

            return false;
        }

        if (!($role_ids = $this->roles_list_to_ids($roles_arr))) {
            return true;
        }

        if (!empty($role_ids)
            && (!($flow_params = $this->fetch_default_flow_params(['table_name' => 'roles_users']))
                || !db_query('DELETE FROM `'.$this->get_flow_table_name($flow_params).'` '
                             .'WHERE user_id = \''.$account_arr['id'].'\' AND role_id IN ('.implode(',', $role_ids).')',
                    $this->get_db_connection($flow_params))
            )) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Couldn\'t unlink roles from account.'));

            return false;
        }

        return true;
    }

    /**
     * Un-links all roles from one account.
     *
     * @param int|array|PHS_Record_data $account_data Account id or account array
     *
     * @return bool
     */
    public function unlink_all_roles_from_user(int | array | PHS_Record_data $account_data) : bool
    {
        if (!$this->_load_dependencies()) {
            return false;
        }

        if (!($account_arr = self::$_accounts_model->data_to_array($account_data))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Account not found in database.'));

            return false;
        }

        if (!($flow_params = $this->fetch_default_flow_params(['table_name' => 'roles_users']))
            || !db_query('DELETE FROM `'.$this->get_flow_table_name($flow_params).'` '
                         .'WHERE user_id = \''.$account_arr['id'].'\'', $this->get_db_connection($flow_params))) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Couldn\'t unlink roles from account.'));

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
    public function unlink_role_from_all_users(int | array | PHS_Record_data $role_data) : bool
    {
        $this->reset_error();

        if (empty($role_data)
            || !($role_arr = $this->data_to_array($role_data))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Role not found in database.'));

            return false;
        }

        if (!($flow_params = $this->fetch_default_flow_params(['table_name' => 'roles_users']))
            || !db_query('DELETE FROM `'.$this->get_flow_table_name($flow_params).'` WHERE role_id = \''.$role_arr['id'].'\'',
                $this->get_db_connection($flow_params))) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Couldn\'t unlink role from all accounts.'));

            return false;
        }

        return true;
    }

    /**
     * Check if provided roles are exactly the ones assigned to provided account
     *
     * @param array|int $account_data Account id or account array
     * @param array $roles_arr Roles passed as slugs, id or role array
     *
     * @return bool
     */
    public function account_roles_changed(int | array | PHS_Record_data $account_data, array $roles_arr) : bool
    {
        if (!$this->_load_dependencies()) {
            return false;
        }

        if (empty($account_data)
            || !($account_arr = self::$_accounts_model->data_to_array($account_data))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Account not found in database.'));

            return false;
        }

        return !self::arrays_have_same_values(
            $this->get_role_ids_for_user($account_arr['id']) ?: [],
            $this->roles_list_to_ids($roles_arr, true) ?: []
        );
    }

    /**
     * Links role units to roles. We assume role units were already created.
     *
     * @param int|array|PHS_Record_data $account_data Account id or account array
     * @param string|array $roles_arr Roles passed as slugs, id or role array
     * @param array $params Functionality parameters
     *
     * @return bool
     */
    public function link_roles_to_user(int | array | PHS_Record_data $account_data, string | array $roles_arr, array $params = []) : bool
    {
        $params['append_roles'] = !isset($params['append_roles']) || !empty($params['append_roles']);

        if (!$this->_load_dependencies()) {
            return false;
        }

        if (empty($account_data)
            || !($account_arr = self::$_accounts_model->data_to_array($account_data))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Account not found in database.'));

            return false;
        }

        if (!($flow_params = $this->fetch_default_flow_params(['table_name' => 'roles_users']))
         || !($ru_table = $this->get_flow_table_name($flow_params))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Invalid flow parameters.'));

            return false;
        }

        if (!is_array($roles_arr)) {
            $roles_arr = [$roles_arr];
        }

        $db_connection = $this->get_db_connection($flow_params);

        if (empty($roles_arr)) {
            if (!empty($params['append_roles'])) {
                return true;
            }

            // Unlink all roles...
            if (!db_query('DELETE FROM `'.$ru_table.'` WHERE user_id = \''.$account_arr['id'].'\'', $db_connection)) {
                $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error un-linking old roles from account.'));

                return false;
            }

            return true;
        }

        $existing_ids = $this->get_role_ids_for_user($account_arr['id']) ?: [];

        // Role ids we have to set
        $role_ids_arr = $this->roles_list_to_ids($roles_arr, true) ?: [];

        $insert_ids = [];
        $delete_ids = [];
        foreach ($role_ids_arr as $role_id) {
            if (!in_array($role_id, $existing_ids, true)) {
                $insert_ids[] = $role_id;
            }
        }

        foreach ($insert_ids as $role_id) {
            if (!db_query('INSERT INTO `'.$ru_table.'` SET user_id = \''.$account_arr['id'].'\', role_id = \''.$role_id.'\'', $db_connection)) {
                $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error linking all roles to account.'));

                return false;
            }
        }

        if (empty($params['append_roles'])) {
            foreach ($existing_ids as $role_id) {
                if (!in_array($role_id, $role_ids_arr, true)) {
                    $delete_ids[] = $role_id;
                }
            }

            if (!empty($delete_ids)
             && !db_query('DELETE FROM `'.$ru_table.'` WHERE user_id = \''.$account_arr['id'].'\' '
                           .' AND role_id IN ('.implode(',', $delete_ids).')', $db_connection)) {
                $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error un-linking old roles from account.'));

                return false;
            }
        }

        return true;
    }

    /**
     * Gets role units slugs for provided role. Role can be passed as Id, slug or role array
     *
     * @param int|string|array|PHS_Record_data $role_data Id, slug or role array
     *
     * @return array False on error or an array of slugs for provided role
     */
    public function get_role_role_units_slugs(int | string | array | PHS_Record_data $role_data) : array
    {
        if (!($flow_params_ru = $this->fetch_default_flow_params(['table_name' => 'roles_units']))
         || !($flow_params_rul = $this->fetch_default_flow_params(['table_name' => 'roles_units_links']))
         || !($roles_units_table = $this->get_flow_table_name($flow_params_ru))
         || !($roles_units_links_table = $this->get_flow_table_name($flow_params_rul))
         || !($ids_arr = $this->roles_list_to_ids([$role_data]))
         || !($role_id = @array_shift($ids_arr))
         || !($qid = db_query('SELECT `'.$roles_units_table.'`.slug '
                               .' FROM `'.$roles_units_table.'` '
                               .' LEFT JOIN `'.$roles_units_links_table.'` ON `'.$roles_units_links_table.'`.role_unit_id = `'.$roles_units_table.'`.id '
                               .' WHERE `'.$roles_units_links_table.'`.role_id = \''.(int)$role_id.'\'', $flow_params_ru['db_connection']))
         || !db_num_rows($qid, $flow_params_ru['db_connection'])) {
            return [];
        }

        $return_arr = [];
        while (($slug_arr = db_fetch_assoc($qid, $flow_params_ru['db_connection']))) {
            $return_arr[] = $slug_arr['slug'];
        }

        return $return_arr;
    }

    /**
     * Gets role units slugs for provided roles. Roles can be passed as single slug, array of slugs or array of ids
     *
     * @param int|array|string|PHS_Record_data $roles_slugs single slug or array of role slugs, ids, roles (arrays or PHS_Record_data)
     *
     * @return array array of slugs for provided roles
     */
    public function get_role_units_slugs_from_roles_slugs(int | array | string | PHS_Record_data $roles_slugs) : array
    {
        if (!is_array($roles_slugs)) {
            $roles_slugs = [$roles_slugs];
        }

        if (!($flow_params_ru = $this->fetch_default_flow_params(['table_name' => 'roles_units']))
         || !($flow_params_rul = $this->fetch_default_flow_params(['table_name' => 'roles_units_links']))
         || !($roles_units_table = $this->get_flow_table_name($flow_params_ru))
         || !($roles_units_links_table = $this->get_flow_table_name($flow_params_rul))
         || !($ids_arr = $this->roles_list_to_ids($roles_slugs))
         || !($qid = db_query('SELECT `'.$roles_units_table.'`.slug '
                               .' FROM `'.$roles_units_table.'` '
                               .' LEFT JOIN `'.$roles_units_links_table.'` ON `'.$roles_units_links_table.'`.role_unit_id = `'.$roles_units_table.'`.id '
                               .' WHERE `'.$roles_units_links_table.'`.role_id IN (\''.implode('\', \'', $ids_arr).'\')', $flow_params_ru['db_connection']))
         || !db_num_rows($qid, $flow_params_ru['db_connection'])) {
            return [];
        }

        $return_arr = [];
        while (($slug_arr = db_fetch_assoc($qid, $flow_params_ru['db_connection']))) {
            $return_arr[] = $slug_arr['slug'];
        }

        return $return_arr;
    }

    /**
     * Tells if a set of roles are assigned to provided account_data. account_data can be a valid user account
     * (id or array) or an empty account array (not logged-in user)
     *
     * @param int|array|PHS_Record_data $account_data Account id, account array, or an empty account array.
     *                                                If array provided and $accounts_model::ROLES_USER_KEY key is defined it will be used directly
     * @param array|string $roles_list Single slug or array of ids, slugs or role arrays
     *                                 (can be mixed with ids, slugs or arrays)
     * @param array $params Functional parameters
     *
     * @return array|bool False if logical operation doesn't match list of roles with roles assigned to
     *                    provided account or an array with account details and matched roles slugs
     */
    public function user_has_roles(int | array | PHS_Record_data $account_data, string | array $roles_list, array $params = []) : ?array
    {
        if (!$this->_load_dependencies()) {
            return null;
        }

        $logical_operations = ['and', 'or'];

        $params['logical_operation'] = empty($params['logical_operation'])
            ? 'and'
            : strtolower(trim($params['logical_operation']));

        if (!is_array($roles_list)) {
            $roles_list = [$roles_list];
        }

        if (!in_array($params['logical_operation'], $logical_operations, true)
         || !($all_role_ids = $this->get_all_roles())
         || !($role_ids = $this->roles_list_to_ids($roles_list))) {
            return null;
        }

        if ($account_data instanceof PHS_Record_data) {
            $account_data = $account_data->cast_to_array();
        }

        $account_slugs = [];
        $account_arr = false;
        if (is_array($account_data)) {
            $account_arr = $account_data;
            if (isset($account_arr[self::$_accounts_model::ROLES_USER_KEY])
                && is_array($account_arr[self::$_accounts_model::ROLES_USER_KEY])) {
                $account_slugs = $account_arr[self::$_accounts_model::ROLES_USER_KEY];
            } else {
                $account_slugs = $this->get_user_roles_slugs($account_arr) ?: [];
                $account_arr[self::$_accounts_model::ROLES_USER_KEY] = $account_slugs;
            }
        } elseif (is_scalar($account_data)) {
            $account_id = (int)$account_data;
            if ((string)$account_id !== (string)$account_data
                || !($account_arr = self::$_accounts_model->get_details($account_id))) {
                $account_arr = false;
            } else {
                $account_slugs = $this->get_user_roles_slugs($account_arr) ?: [];
                $account_arr[self::$_accounts_model::ROLES_USER_KEY] = $account_slugs;
            }
        }

        if (empty($account_slugs)
            || !($account_role_ids = $this->roles_list_to_ids($account_slugs))) {
            return null;
        }

        $matching_slugs_arr = [];
        foreach ($role_ids as $role_id) {
            if (empty($all_role_ids[$role_id]['slug'])
                || !in_array($role_id, $account_role_ids, true)) {
                // If all should match return false when we find one that is not assigned to account
                if ($params['logical_operation'] === 'and') {
                    return null;
                }

                continue;
            }

            $matching_slugs_arr[] = $all_role_ids[$role_id]['slug'];
        }

        // Nothing matched
        if (empty($matching_slugs_arr)) {
            return null;
        }

        $return_arr = [];
        $return_arr['account_data'] = $account_arr;
        $return_arr['matching_slugs'] = $matching_slugs_arr;

        return $return_arr;
    }

    /**
     * Tells if a set of role units are assigned to provided account_data. account_data can be a valid
     * user account (id or array) or an empty account array (not logged-in user)
     *
     * @param array|int $account_data Account id, account array, or an empty account array.
     *                                If array provided and $accounts_model::ROLE_UNITS_USER_KEY key is defined it will be used directly
     * @param array|string $role_units_list Single slug or array of ids, slugs or role unit arrays
     *                                      (can be mixed with ids, slugs or arrays)
     * @param array $params Functional parameters
     *
     * @return array|bool False if logical operation doesn't match list of role units with role units assigned
     *                    to provided account or an array with account details and matched role units slugs
     */
    public function user_has_role_units(int | array $account_data, string | array $role_units_list, array $params = []) : ?array
    {
        $this->reset_error();

        $logical_operations = ['and', 'or'];

        $params['logical_operation'] = empty($params['logical_operation'])
            ? 'and'
            : strtolower(trim($params['logical_operation']));

        if (!is_array($role_units_list)) {
            $role_units_list = [$role_units_list];
        }

        if (!in_array($params['logical_operation'], $logical_operations, true)
         || (empty(self::$_accounts_model) && !$this->_load_dependencies())
         || !($all_role_unit_ids = $this->get_all_role_units())
         || !($role_unit_ids = $this->role_units_list_to_ids($role_units_list))) {
            return null;
        }

        $accounts_model = self::$_accounts_model;

        $account_slugs = [];
        $account_arr = false;
        if (is_array($account_data)) {
            $account_arr = $account_data;
            if (isset($account_arr[$accounts_model::ROLE_UNITS_USER_KEY])
                && is_array($account_arr[$accounts_model::ROLE_UNITS_USER_KEY])) {
                $account_slugs = $account_arr[$accounts_model::ROLE_UNITS_USER_KEY];
            } else {
                $account_slugs = $this->get_user_role_units_slugs($account_arr) ?: [];
                $account_arr[$accounts_model::ROLE_UNITS_USER_KEY] = $account_slugs;
            }
        } elseif (is_scalar($account_data)) {
            $account_id = (int)$account_data;
            if ((string)$account_id !== (string)$account_data
                || !($account_arr = $accounts_model->get_details($account_id))) {
                $account_arr = false;
            } else {
                $account_slugs = $this->get_user_role_units_slugs($account_arr) ?: [];
                $account_arr[$accounts_model::ROLE_UNITS_USER_KEY] = $account_slugs;
            }
        }

        if (empty($account_slugs)
            || !($account_role_unit_ids = $this->role_units_list_to_ids($account_slugs))) {
            return null;
        }

        $matching_slugs_arr = [];
        foreach ($role_unit_ids as $role_unit_id) {
            if (empty($all_role_unit_ids[$role_unit_id]['slug'])
             || !in_array($role_unit_id, $account_role_unit_ids, true)) {
                // If all should match return false when we find one that is not assigned to account
                if ($params['logical_operation'] === 'and') {
                    return null;
                }

                continue;
            }

            $matching_slugs_arr[] = $all_role_unit_ids[$role_unit_id]['slug'];
        }

        // Nothing matched
        if (empty($matching_slugs_arr)) {
            return null;
        }

        $return_arr = [];
        $return_arr['account_data'] = $account_arr;
        $return_arr['matching_slugs'] = $matching_slugs_arr;

        return $return_arr;
    }

    public function get_user_roles_slugs(int | array | PHS_Record_data $account_data) : ?array
    {
        $this->reset_error();

        if (empty(self::$_accounts_model)
            && !$this->_load_dependencies()) {
            return null;
        }

        if (empty($account_data)
            || !($account_arr = self::$_accounts_model->data_to_array($account_data))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Account not found in database.'));

            return null;
        }

        if (!($role_ids = $this->get_role_ids_for_user($account_arr['id']))
            || !($all_roles = $this->get_all_roles())) {
            return [];
        }

        $return_arr = [];
        foreach ($role_ids as $role_id) {
            if (empty($all_roles[$role_id])) {
                continue;
            }

            $return_arr[] = $all_roles[$role_id]['slug'];
        }

        return $return_arr;
    }

    public function get_user_role_units_slugs(int | array | PHS_Record_data $account_data) : ?array
    {
        $this->reset_error();

        if (empty(self::$_accounts_model)
            && !$this->_load_dependencies()) {
            return null;
        }

        if (empty($account_data)
            || !($account_arr = self::$_accounts_model->data_to_array($account_data))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Account not found in database.'));

            return null;
        }

        if (!($role_ids = $this->get_role_ids_for_user($account_arr['id']))
         || !($flow_params_ru = $this->fetch_default_flow_params(['table_name' => 'roles_units']))
         || !($flow_params_rul = $this->fetch_default_flow_params(['table_name' => 'roles_units_links']))
         || !($roles_units_table = $this->get_flow_table_name($flow_params_ru))
         || !($roles_units_links_table = $this->get_flow_table_name($flow_params_rul))
         || !($qid = db_query('SELECT `'.$roles_units_table.'`.slug '
                               .' FROM `'.$roles_units_table.'` '
                               .' LEFT JOIN `'.$roles_units_links_table.'` ON `'.$roles_units_links_table.'`.role_unit_id = `'.$roles_units_table.'`.id '
                               .' WHERE `'.$roles_units_links_table.'`.role_id IN ('.implode(',', $role_ids).')', $flow_params_ru['db_connection']))
         || !db_num_rows($qid, $flow_params_ru['db_connection'])) {
            return [];
        }

        $return_arr = [];
        while (($slug_arr = db_fetch_assoc($qid, $flow_params_ru['db_connection']))) {
            $return_arr[$slug_arr['slug']] = true;
        }

        // Make sure we have unique role unit slugs
        return !empty($return_arr) ? @array_keys($return_arr) : [];
    }

    /**
     * @inheritdoc
     */
    final public function fields_definition($params = false) : ?array
    {
        if (empty($params['table_name'])) {
            return null;
        }

        $return_arr = [];

        switch ($params['table_name']) {
            case 'roles':
                $return_arr = [
                    'id' => [
                        'type'           => self::FTYPE_INT,
                        'primary'        => true,
                        'auto_increment' => true,
                    ],
                    'slug' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 255,
                        'nullable' => true,
                        'default'  => null,
                        'index'    => true,
                    ],
                    'plugin' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 255,
                        'nullable' => true,
                        'default'  => null,
                        'index'    => true,
                    ],
                    'uid' => [
                        'type'    => self::FTYPE_INT,
                        'index'   => true,
                        'comment' => 'Which user defined this role',
                    ],
                    'name' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 255,
                        'nullable' => true,
                        'default'  => null,
                    ],
                    'description' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 255,
                        'nullable' => true,
                        'default'  => null,
                    ],
                    'status' => [
                        'type'   => self::FTYPE_TINYINT,
                        'length' => 2,
                        'index'  => true,
                    ],
                    'status_date' => [
                        'type'  => self::FTYPE_DATETIME,
                        'index' => false,
                    ],
                    'predefined' => [
                        'type'   => self::FTYPE_TINYINT,
                        'length' => 2,
                        'index'  => true,
                    ],
                    'cdate' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                ];
                break;

            case 'roles_units':
                $return_arr = [
                    'id' => [
                        'type'           => self::FTYPE_INT,
                        'primary'        => true,
                        'auto_increment' => true,
                    ],
                    'slug' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 255,
                        'nullable' => true,
                        'default'  => null,
                        'index'    => true,
                    ],
                    'plugin' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 255,
                        'nullable' => true,
                        'default'  => null,
                        'index'    => true,
                    ],
                    'name' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 255,
                        'nullable' => true,
                        'default'  => null,
                    ],
                    'description' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 255,
                        'nullable' => true,
                        'default'  => null,
                    ],
                    'status' => [
                        'type'   => self::FTYPE_TINYINT,
                        'length' => 2,
                        'index'  => true,
                    ],
                    'status_date' => [
                        'type'  => self::FTYPE_DATETIME,
                        'index' => false,
                    ],
                    'cdate' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                ];
                break;

            case 'roles_units_links':
                $return_arr = [
                    'id' => [
                        'type'           => self::FTYPE_INT,
                        'primary'        => true,
                        'auto_increment' => true,
                    ],
                    'role_id' => [
                        'type'  => self::FTYPE_INT,
                        'index' => true,
                    ],
                    'role_unit_id' => [
                        'type'  => self::FTYPE_INT,
                        'index' => true,
                    ],
                ];
                break;

            case 'roles_users':
                $return_arr = [
                    'id' => [
                        'type'           => self::FTYPE_INT,
                        'primary'        => true,
                        'auto_increment' => true,
                    ],
                    'role_id' => [
                        'type'  => self::FTYPE_INT,
                        'index' => true,
                    ],
                    'user_id' => [
                        'type'  => self::FTYPE_INT,
                        'index' => true,
                    ],
                ];
                break;
        }

        return $return_arr;
    }

    protected function _relations_definition() : void
    {
        $this->relation_many_to_many('role_units_slugs',
            self::class, 'id',
            self::class, 'role_unit_id', 'role_id',
            dest_flow: ['table_name' => 'roles_units'],
            link_flow: ['table_name' => 'roles_units_links'],
            source_flow: ['table_name' => 'roles'],
            filter_fn: function(PHS_Record_data $role_data, mixed $read_value) {
                return $role_data['slug'] ?? '';
            }
        );
    }

    protected function get_insert_prepare_params_roles($params)
    {
        if (empty($params) || !is_array($params)) {
            return false;
        }

        if (empty($params['fields']['slug'])) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide a slug for this role.'));

            return false;
        }

        if (empty($params['fields']['name'])) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide a name for this role.'));

            return false;
        }

        // Make sure we have a valid slug...
        $params['fields']['slug'] = $this->transform_string_to_slug($params['fields']['slug']);

        $constrain_arr = [];
        $constrain_arr['slug'] = $params['fields']['slug'];

        $check_params = [];
        $check_params['table_name'] = 'roles';
        $check_params['result_type'] = 'single';
        $check_params['details'] = '*';

        if ($this->get_details_fields($constrain_arr, $check_params)) {
            $this->set_error(self::ERR_INSERT, self::_t('There is already a role registered with this slug.'));

            return false;
        }

        $params['fields']['cdate'] = date(self::DATETIME_DB);

        if (empty($params['fields']['status'])) {
            $params['fields']['status'] = self::STATUS_ACTIVE;
        }

        if (!$this->valid_status($params['fields']['status'])) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide a valid status for this role.'));

            return false;
        }

        if (empty($params['fields']['status_date'])
         || empty_db_date($params['fields']['status_date'])) {
            $params['fields']['status_date'] = $params['fields']['cdate'];
        }

        $params['fields']['predefined'] = (!empty($params['fields']['predefined']) ? 1 : 0);

        if (empty($params['{role_units}']) || !is_array($params['{role_units}'])) {
            $params['{role_units}'] = [];
        }

        return $params;
    }

    protected function insert_after_roles(array $insert_arr, array $params) : ?array
    {
        if (!empty($params['{role_units}']) && is_array($params['{role_units}'])) {
            if (empty($params['{role_units_params}'])) {
                $params['{role_units_params}'] = null;
            }

            if (!$this->link_role_units_to_role($insert_arr, $params['{role_units}'], $params['{role_units_params}'])) {
                return null;
            }
        }

        return $insert_arr;
    }

    protected function get_edit_prepare_params_roles($existing_data, $params)
    {
        if (empty($params) || !is_array($params)) {
            return false;
        }

        if (isset($params['fields']['status'])) {
            if (!$this->valid_status($params['fields']['status'])) {
                $this->set_error(self::ERR_EDIT, self::_t('Please provide a valid status.'));

                return false;
            }

            $cdate = date(self::DATETIME_DB);
            $params['fields']['status_date'] = $cdate;
        }

        if (isset($params['fields']['predefined'])) {
            $params['fields']['predefined'] = (!empty($params['fields']['predefined']) ? 1 : 0);
        }

        if (isset($params['fields']['slug'])) {
            if (empty($params['fields']['slug'])) {
                $this->set_error(self::ERR_EDIT, self::_t('Role slug cannot be empty.'));

                return false;
            }

            // Make sure we have a valid slug...
            $params['fields']['slug'] = $this->transform_string_to_slug($params['fields']['slug']);

            $constrain_arr = [];
            $constrain_arr['slug'] = $params['fields']['slug'];
            $constrain_arr['id'] = ['check' => '!=', 'value' => $existing_data['id']];

            $check_params = [];
            $check_params['table_name'] = 'roles';
            $check_params['result_type'] = 'single';
            $check_params['details'] = 'id';

            if ($this->get_details_fields($constrain_arr, $check_params)) {
                $this->set_error(self::ERR_EDIT, self::_t('There is already a role registered with this slug.'));

                return false;
            }
        }

        if (!isset($params['{role_units}']) || !is_array($params['{role_units}'])) {
            $params['{role_units}'] = false;
        }

        return $params;
    }

    protected function edit_after_roles($existing_data, $edit_arr, $params)
    {
        if (is_array($params['{role_units}'])) {
            if (empty($params['{role_units_params}'])) {
                $params['{role_units_params}'] = null;
            }

            if (!$this->link_role_units_to_role($existing_data, $params['{role_units}'], $params['{role_units_params}'])) {
                return false;
            }
        }

        return $existing_data;
    }

    protected function get_insert_prepare_params_roles_units($params)
    {
        if (empty($params) || !is_array($params)) {
            return false;
        }

        if (empty($params['fields']['slug'])) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide a slug for this role unit.'));

            return false;
        }

        if (empty($params['fields']['name'])) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide a name for this role unit.'));

            return false;
        }

        // Make sure we have a valid slug...
        $params['fields']['slug'] = $this->transform_string_to_slug($params['fields']['slug']);

        $constrain_arr = [];
        $constrain_arr['slug'] = $params['fields']['slug'];

        $check_params = [];
        $check_params['table_name'] = 'roles_units';
        $check_params['result_type'] = 'single';
        $check_params['details'] = '*';

        if ($this->get_details_fields($constrain_arr, $check_params)) {
            $this->set_error(self::ERR_INSERT, self::_t('There is already a role unit registered with this slug.'));

            return false;
        }

        $params['fields']['cdate'] = date(self::DATETIME_DB);

        if (empty($params['fields']['status'])) {
            $params['fields']['status'] = self::STATUS_ACTIVE;
        }

        if (!$this->valid_status($params['fields']['status'])) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide a valid status for this role unit.'));

            return false;
        }

        if (empty($params['fields']['status_date'])
         || empty_db_date($params['fields']['status_date'])) {
            $params['fields']['status_date'] = $params['fields']['cdate'];
        }

        return $params;
    }

    protected function get_edit_prepare_params_roles_units($existing_data, $params)
    {
        if (empty($params) || !is_array($params)) {
            return false;
        }

        if (isset($params['fields']['status'])) {
            if (!$this->valid_status($params['fields']['status'])) {
                $this->set_error(self::ERR_EDIT, self::_t('Please provide a valid status.'));

                return false;
            }

            $cdate = date(self::DATETIME_DB);
            $params['fields']['status_date'] = $cdate;
        }

        if (isset($params['fields']['slug'])) {
            if (empty($params['fields']['slug'])) {
                $this->set_error(self::ERR_EDIT, self::_t('Role unit slug cannot be empty.'));

                return false;
            }

            // Make sure we have a valid slug...
            $params['fields']['slug'] = $this->transform_string_to_slug($params['fields']['slug']);

            $constrain_arr = [];
            $constrain_arr['slug'] = $params['fields']['slug'];
            $constrain_arr['id'] = ['check' => '!=', 'value' => $existing_data['id']];

            $check_params = [];
            $check_params['table_name'] = 'roles_units';
            $check_params['result_type'] = 'single';
            $check_params['details'] = 'id';

            if ($this->get_details_fields($constrain_arr, $check_params)) {
                $this->set_error(self::ERR_EDIT, self::_t('There is already a role unit registered with this slug.'));

                return false;
            }
        }

        return $params;
    }

    protected function get_insert_prepare_params_roles_units_links($params)
    {
        if (empty($params) || !is_array($params)) {
            return false;
        }

        if (!empty($params['fields']['role_id'])) {
            $params['fields']['role_id'] = (int)$params['fields']['role_id'];
        }
        if (!empty($params['fields']['role_unit_id'])) {
            $params['fields']['role_unit_id'] = (int)$params['fields']['role_unit_id'];
        }

        if (empty($params['fields']['role_id'])) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide a role id.'));

            return false;
        }

        if (empty($params['fields']['role_unit_id'])) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide a role unit id.'));

            return false;
        }

        return $params;
    }

    protected function get_edit_prepare_params_roles_units_links($existing_data, $params)
    {
        if (empty($params) || !is_array($params)) {
            return false;
        }

        if (isset($params['fields']['role_id'])) {
            $params['fields']['role_id'] = (int)$params['fields']['role_id'];
        }
        if (isset($params['fields']['role_unit_id'])) {
            $params['fields']['role_unit_id'] = (int)$params['fields']['role_unit_id'];
        }

        if (isset($params['fields']['role_id']) && empty($params['fields']['role_id'])) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide a role id.'));

            return false;
        }

        if (isset($params['fields']['role_unit_id']) && empty($params['fields']['role_unit_id'])) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide a role unit id.'));

            return false;
        }

        return $params;
    }

    protected function get_insert_prepare_params_roles_users($params)
    {
        if (empty($params) || !is_array($params)) {
            return false;
        }

        if (!empty($params['fields']['role_id'])) {
            $params['fields']['role_id'] = (int)$params['fields']['role_id'];
        }
        if (!empty($params['fields']['user_id'])) {
            $params['fields']['user_id'] = (int)$params['fields']['user_id'];
        }

        if (empty($params['fields']['role_id'])) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide a role id.'));

            return false;
        }

        if (empty($params['fields']['user_id'])) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide user id.'));

            return false;
        }

        return $params;
    }

    protected function get_edit_prepare_params_roles_users($existing_data, $params)
    {
        if (empty($params) || !is_array($params)) {
            return false;
        }

        if (isset($params['fields']['role_id'])) {
            $params['fields']['role_id'] = (int)$params['fields']['role_id'];
        }
        if (isset($params['fields']['user_id'])) {
            $params['fields']['user_id'] = (int)$params['fields']['user_id'];
        }

        if (isset($params['fields']['role_id']) && empty($params['fields']['role_id'])) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide a role id.'));

            return false;
        }

        if (isset($params['fields']['user_id']) && empty($params['fields']['user_id'])) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide user id.'));

            return false;
        }

        return $params;
    }

    private function _load_dependencies() : bool
    {
        $this->reset_error();

        if (empty(self::$_accounts_model)
         && !(self::$_accounts_model = PHS_Model_Accounts::get_instance())) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Error loading required resources.'));

            return false;
        }

        return true;
    }

    /**
     * Returns an array of key-values of fields that should edit role unit in case role unit already exists
     * @return array
     */
    public static function get_register_edit_role_unit_fields() : array
    {
        return [
            'name'        => '',
            'description' => '',
            'plugin'      => '',
        ];
    }
}
