<?php

namespace phs\system\core\models;

use phs\PHS;
use phs\libraries\PHS_Model;
use phs\libraries\PHS_Roles;
use phs\traits\PHS_Model_Trait_statuses;
use phs\plugins\accounts\models\PHS_Model_Accounts;

class PHS_Model_Tenants extends PHS_Model
{
    use PHS_Model_Trait_statuses;

    public const STATUS_ACTIVE = 1, STATUS_INACTIVE = 2, STATUS_DELETED = 3;

    protected static array $STATUSES_ARR = [
        self::STATUS_ACTIVE   => ['title' => 'Active'],
        self::STATUS_INACTIVE => ['title' => 'Inactive'],
        self::STATUS_DELETED  => ['title' => 'Deleted'],
    ];

    private static ?array $_default_tenant = null;

    public function get_model_version() : string
    {
        return '1.0.6';
    }

    public function get_table_names() : array
    {
        return ['phs_tenants'];
    }

    public function get_main_table_name() : string
    {
        return 'phs_tenants';
    }

    public function is_active($record_data) : bool
    {
        return !empty($record_data)
               && ($record_arr = $this->data_to_array($record_data))
               && (int)$record_arr['status'] === self::STATUS_ACTIVE;
    }

    public function is_inactive($record_data) : bool
    {
        return !empty($record_data)
               && ($record_arr = $this->data_to_array($record_data))
               && (int)$record_arr['status'] === self::STATUS_INACTIVE;
    }

    public function is_deleted($record_data) : bool
    {
        return !empty($record_data)
               && ($record_arr = $this->data_to_array($record_data))
               && (int)$record_arr['status'] === self::STATUS_DELETED;
    }

    public function is_default_tenant($record_data) : bool
    {
        return !empty($record_data)
               && ($record_arr = $this->data_to_array($record_data))
               && !empty($record_arr['is_default']);
    }

    public function act_activate($record_data) : ?array
    {
        $this->reset_error();

        if (empty($record_data) || !($record_arr = $this->data_to_array($record_data))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Tenant details not found in database.'));

            return null;
        }

        if ($this->is_active($record_arr)) {
            return $record_arr;
        }

        $edit_arr = [];
        $edit_arr['status'] = self::STATUS_ACTIVE;

        $edit_params = [];
        $edit_params['fields'] = $edit_arr;

        if (!($new_record = $this->edit($record_arr, $edit_params))) {
            return null;
        }

        return $new_record;
    }

    public function act_inactivate($record_data) : ?array
    {
        $this->reset_error();

        if (empty($record_data) || !($record_arr = $this->data_to_array($record_data))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Tenant details not found in database.'));

            return null;
        }

        if ($this->is_active($record_arr)) {
            return $record_arr;
        }

        $edit_arr = [];
        $edit_arr['status'] = self::STATUS_INACTIVE;

        $edit_params = [];
        $edit_params['fields'] = $edit_arr;

        if (!($new_record = $this->edit($record_arr, $edit_params))) {
            return null;
        }

        return $new_record;
    }

    public function act_delete($record_data) : ?array
    {
        $this->reset_error();

        if (empty($record_data) || !($record_arr = $this->data_to_array($record_data))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Tenant details not found in database.'));

            return null;
        }

        if ($this->is_deleted($record_arr)) {
            return $record_arr;
        }

        if ($this->is_default_tenant($record_arr)) {
            $this->set_error(self::ERR_DELETE, $this->_pt('Cannot delete default tenant.'));

            return null;
        }

        $edit_arr = [];
        $edit_arr['status'] = self::STATUS_DELETED;

        $edit_params = [];
        $edit_params['fields'] = $edit_arr;

        if (!($new_record = $this->edit($record_arr, $edit_params))) {
            return null;
        }

        return $new_record;
    }

    public function act_set_default($record_data) : ?array
    {
        $this->reset_error();

        if (empty($record_data) || !($record_arr = $this->data_to_array($record_data))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Tenant details not found in database.'));

            return null;
        }

        if ($this->is_default_tenant($record_arr)) {
            return $record_arr;
        }

        $edit_arr = [];
        $edit_arr['is_default'] = 1;
        if (!$this->is_active($record_arr)) {
            $edit_arr['status'] = self::STATUS_ACTIVE;
        }

        $edit_params = [];
        $edit_params['fields'] = $edit_arr;

        if (!($new_record = $this->edit($record_arr, $edit_params))) {
            return null;
        }

        return $new_record;
    }

    public function get_default_tenant() : ?array
    {
        if (!PHS::is_multi_tenant() || (empty(self::$_default_tenant) && !$this->_get_cached_tenants())) {
            return null;
        }

        return self::$_default_tenant;
    }

    public function get_tenant_by_identifier(string $identifier) : ?array
    {
        if (!PHS::is_multi_tenant()
            || !($all_tenants_arr = $this->_get_cached_tenants_by_identifier())
            || empty($all_tenants_arr[$identifier])) {
            return null;
        }

        return $all_tenants_arr[$identifier];
    }

    public function get_tenants_by_domain_and_directory(string $domain, ?string $directory = null) : ?array
    {
        if (empty($domain)
            || !PHS::is_multi_tenant()
            || !($dd_identifier = self::prepare_tenant_domain_and_directory($domain, $directory))
            || !($all_tenants_arr = $this->_get_cached_tenants_by_domain_and_directory())
            || empty($all_tenants_arr[$dd_identifier])) {
            return null;
        }

        return $all_tenants_arr[$dd_identifier];
    }

    public function get_tenants_as_key_val() : array
    {
        if (!PHS::is_multi_tenant()
            || !($all_tenants_arr = $this->_get_cached_tenants())) {
            return [];
        }

        $return_arr = [];
        foreach ($all_tenants_arr as $t_id => $t_arr) {
            $return_arr[$t_id] = $this->get_tenant_details_for_display($t_arr);
        }

        return $return_arr;
    }

    public function get_all_tenants() : array
    {
        if (!PHS::is_multi_tenant()
            || !($all_tenants_arr = $this->_get_cached_tenants())) {
            return [];
        }

        return $all_tenants_arr;
    }

    public function can_user_edit($record_data, $account_data) : ?array
    {
        /** @var PHS_Model_Accounts $accounts_model */
        if (empty($record_data) || empty($account_data)
         || !PHS::is_multi_tenant()
         || !($tenant_arr = $this->data_to_array($record_data))
         || $this->is_deleted($tenant_arr)
         || !($accounts_model = PHS_Model_Accounts::get_instance())
         || !($account_arr = $accounts_model->data_to_array($account_data))
         || !can(PHS_Roles::ROLEU_TENANTS_MANAGE, null, $account_arr)) {
            return null;
        }

        $return_arr = [];
        $return_arr['tenant_data'] = $tenant_arr;
        $return_arr['account_data'] = $account_arr;

        return $return_arr;
    }

    public function get_tenant_details_for_display($tenant_data) : ?string
    {
        if (!PHS::is_multi_tenant()) {
            return '';
        }

        if (!($tenant_arr = $this->data_to_array($tenant_data, ['table_name' => 'phs_tenants']))) {
            return null;
        }

        return $tenant_arr['name'].' ('.self::prepare_tenant_domain_and_directory($tenant_arr['domain'], $tenant_arr['directory']).')';
    }

    public function get_tenant_settings($record_data) : ?array
    {
        $this->reset_error();

        if (empty($record_data) || !($record_arr = $this->data_to_array($record_data))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Tenant details not found in database.'));

            return null;
        }

        if (empty($record_arr['settings'])) {
            return [];
        }

        return self::validate_array($this->_decode_settings_field($record_arr['settings']), $this->_get_settings_fields());
    }

    public function generate_identifier() : string
    {
        return md5(uniqid(mt_rand(), true));
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
            case 'phs_tenants':
                $return_arr = [
                    'id' => [
                        'type'           => self::FTYPE_INT,
                        'primary'        => true,
                        'auto_increment' => true,
                    ],
                    'added_by_uid' => [
                        'type' => self::FTYPE_INT,
                    ],
                    'name' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 255,
                        'nullable' => true,
                        'default'  => null,
                    ],
                    'domain' => [
                        'type'   => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'index'  => true,
                    ],
                    'directory' => [
                        'type'   => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'index'  => true,
                    ],
                    'identifier' => [
                        'type'   => self::FTYPE_VARCHAR,
                        'length' => 50,
                        'index'  => true,
                    ],
                    'settings' => [
                        'type' => self::FTYPE_TEXT,
                    ],
                    'is_default' => [
                        'type'    => self::FTYPE_TINYINT,
                        'length'  => 2,
                        'index'   => true,
                        'default' => 0,
                    ],
                    'status' => [
                        'type'   => self::FTYPE_TINYINT,
                        'length' => 2,
                        'index'  => true,
                    ],
                    'status_date' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                    'last_edit' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                    'deleted' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                    'cdate' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                ];
                break;
        }

        return $return_arr;
    }

    protected function get_insert_prepare_params_phs_tenants($params)
    {
        if (empty($params) || !is_array($params)) {
            return false;
        }

        if (empty($params['fields']['name'])) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide a tenant name.'));

            return false;
        }

        if (empty($params['fields']['domain'])) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide a tenant domain.'));

            return false;
        }

        if (empty($params['fields']['status'])) {
            $params['fields']['status'] = self::STATUS_INACTIVE;
        }

        if (!$this->valid_status($params['fields']['status'])) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide a valid status for the tenant.'));

            return false;
        }

        $params['fields']['domain'] = self::prepare_tenant_domain($params['fields']['domain']);
        if (empty($params['fields']['directory'])) {
            $params['fields']['directory'] = null;
        } else {
            $params['fields']['directory'] = self::prepare_tenant_directory($params['fields']['directory']);
        }

        if (empty($params['fields']['identifier'])) {
            $params['fields']['identifier'] = $this->generate_identifier();
            while ($this->get_details_fields(['identifier' => $params['fields']['identifier']])) {
                $params['fields']['identifier'] = $this->generate_identifier();
            }
        }

        if (empty($params['fields']['settings'])) {
            $params['fields']['settings'] = null;
        } else {
            $params['fields']['settings'] = $this->_encode_settings_field($params['fields']['settings']);
        }

        $params['fields']['is_default'] = (!empty($params['fields']['is_default']) ? 1 : 0);

        $params['fields']['last_edit'] = date(self::DATETIME_DB);

        if (empty($params['fields']['cdate'])
         || empty_db_date($params['fields']['cdate'])) {
            $params['fields']['cdate'] = $params['fields']['last_edit'];
        }

        if (empty($params['fields']['status_date'])
            || empty_db_date($params['fields']['status_date'])) {
            $params['fields']['status_date'] = $params['fields']['last_edit'];
        }

        return $params;
    }

    protected function get_edit_prepare_params_phs_tenants($existing_data, $params)
    {
        if (empty($params) || !is_array($params)) {
            return false;
        }

        if (isset($params['fields']['name'])
            && empty($params['fields']['name'])) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide a tenant name.'));

            return false;
        }

        if (isset($params['fields']['domain'])
            && empty($params['fields']['domain'])) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide a tenant domain.'));

            return false;
        }

        if (!empty($params['fields']['identifier'])
            && $existing_data['identifier'] !== $params['fields']['identifier']
            && $this->get_details_fields(
                [
                    'identifier' => $params['fields']['identifier'],
                    'id'         => ['check' => '!=', 'value' => $existing_data['id']],
                ])
        ) {
            $this->set_error(self::ERR_INSERT, $this->_pt('A tenant with same identifier already exists.'));

            return false;
        }

        $now_date = date(self::DATETIME_DB);

        if (isset($params['fields']['status'])) {
            if (!$this->valid_status($params['fields']['status'])) {
                $this->set_error(self::ERR_EDIT, $this->_pt('Please provide a valid status.'));

                return false;
            }

            $params['fields']['status_date'] = $now_date;

            if ($params['fields']['status'] === self::STATUS_DELETED) {
                $params['fields']['deleted'] = $now_date;
            }
        }

        if (isset($params['fields']['domain'])) {
            $params['fields']['domain'] = self::prepare_tenant_domain($params['fields']['domain']);
        }

        if (isset($params['fields']['directory'])) {
            if ($params['fields']['directory'] === '') {
                $params['fields']['directory'] = null;
            } else {
                $params['fields']['directory'] = self::prepare_tenant_directory($params['fields']['directory']) ?: null;
            }
        }

        if (isset($params['fields']['settings'])) {
            if (empty($params['fields']['settings'])) {
                $params['fields']['settings'] = null;
            } else {
                $params['fields']['settings'] = $this->_encode_settings_field($params['fields']['settings']);
            }
        }

        if (isset($params['fields']['is_default'])) {
            $params['fields']['is_default'] = (!empty($params['fields']['is_default']) ? 1 : 0);
        }

        if (empty($params['fields']['last_edit'])
         || empty_db_date($params['fields']['last_edit'])) {
            $params['fields']['last_edit'] = $now_date;
        }

        return $params;
    }

    protected function insert_after_phs_tenants(array $insert_arr, array $params) : ?array
    {
        if (!empty($params['fields']['is_default'])
         && ($flow_arr = $this->fetch_default_flow_params(['table_name' => 'phs_tenants']))
         && ($table_name = $this->get_flow_table_name($flow_arr))
        ) {
            // low level update, so we don't trigger anything in model
            db_query('UPDATE `'.$table_name.'` SET is_default = 0 WHERE id != \''.$insert_arr['id'].'\'',
                $flow_arr['db_connection']);
        }

        return $insert_arr;
    }

    protected function edit_after_phs_tenants($existing_data, $edit_arr, $params)
    {
        if (!empty($params['fields']['is_default'])
         && empty($existing_data['is_default'])
         && ($flow_arr = $this->fetch_default_flow_params(['table_name' => 'phs_tenants']))
         && ($table_name = $this->get_flow_table_name($flow_arr))
        ) {
            // low level update, so we don't trigger anything in model
            db_query('UPDATE `'.$table_name.'` SET is_default = 0 WHERE id != \''.$existing_data['id'].'\'',
                $flow_arr['db_connection']);
        }

        return $existing_data;
    }

    private function _get_settings_fields() : array
    {
        return [
            'default_theme'    => '',
            'current_theme'    => '',
            'cascading_themes' => [],
        ];
    }

    /**
     * @param null|array|string $settings
     *
     * @return null|string
     */
    private function _encode_settings_field($settings) : ?string
    {
        if ($settings === null) {
            return null;
        }

        if (is_array($settings)) {
            if (!($settings = @json_encode($settings))) {
                $settings = null;
            }
        } elseif (is_string($settings)) {
            if (!($settings = @json_decode($settings, true))
                || !($settings = @json_encode($settings))) {
                $settings = null;
            }
        } else {
            return null;
        }

        return $settings;
    }

    /**
     * @param null|array|string $settings
     *
     * @return array
     */
    private function _decode_settings_field($settings) : array
    {
        if (empty($settings)) {
            return [];
        }

        if (is_array($settings)) {
            return $settings;
        }

        if (is_string($settings)) {
            if (!($settings = @json_decode($settings, true))) {
                $settings = [];
            }
        } else {
            return [];
        }

        return $settings;
    }

    private function _get_cached_tenants(bool $only_active = false, bool $force = false) : ?array
    {
        static $all_tenants = null, $active_tenants = null;

        if (empty($force)
            && $all_tenants !== null) {
            return $only_active ? $active_tenants : $all_tenants;
        }

        $list_arr = $this->fetch_default_flow_params(['table_name' => 'phs_tenants']);
        $list_arr['fields'] = [];
        $list_arr['fields']['status'] = ['check' => '!=', 'value' => self::STATUS_DELETED];

        $all_tenants = [];
        $active_tenants = [];
        if (!($result_list = $this->get_list($list_arr))) {
            return [];
        }

        foreach ($result_list as $t_id => $t_arr) {
            $all_tenants[(int)$t_id] = $t_arr;
            if ($this->is_active($t_arr)) {
                $active_tenants[(int)$t_id] = $t_arr;
            }
            if ($this->is_default_tenant($t_arr)) {
                self::$_default_tenant = $t_arr;
            }
        }

        return $only_active ? $active_tenants : $all_tenants;
    }

    private function _get_cached_tenants_by_identifier(bool $only_active = false, bool $force = false) : ?array
    {
        static $all_tenants_id = null, $active_tenants_id = null;

        if (empty($force)
            && $all_tenants_id !== null) {
            return $only_active ? $active_tenants_id : $all_tenants_id;
        }

        $all_tenants_id = [];
        $active_tenants_id = [];
        if (!($result_list = $this->_get_cached_tenants(false, $force))) {
            return [];
        }

        foreach ($result_list as $t_arr) {
            $all_tenants_id[$t_arr['identifier']] = $t_arr;
            if ($this->is_active($t_arr)) {
                $active_tenants_id[$t_arr['identifier']] = $t_arr;
            }
        }

        return $only_active ? $active_tenants_id : $all_tenants_id;
    }

    private function _get_cached_tenants_by_domain_and_directory(bool $only_active = false, bool $force = false) : ?array
    {
        static $all_tenants_dd = null, $active_tenants_dd = null;

        if (empty($force)
            && $all_tenants_dd !== null) {
            return $only_active ? $active_tenants_dd : $all_tenants_dd;
        }

        $all_tenants_dd = [];
        $active_tenants_dd = [];
        if (!($result_list = $this->_get_cached_tenants(false, $force))) {
            return [];
        }

        foreach ($result_list as $t_id => $t_arr) {
            $identifier_dd = self::prepare_tenant_domain_and_directory($t_arr['domain'], $t_arr['directory'] ?? '');
            $all_tenants_dd[$identifier_dd][$t_id] = $t_arr;
            if ($this->is_active($t_arr)) {
                $active_tenants_dd[$identifier_dd][$t_id] = $t_arr;
            }
        }

        return $only_active ? $active_tenants_dd : $all_tenants_dd;
    }

    public static function prepare_tenant_domain(?string $domain, bool $slash_ended = true) : string
    {
        if ($domain === null
            || ($domain = trim(trim($domain), '/')) === '') {
            return '';
        }

        return $domain.($slash_ended ? '/' : '');
    }

    public static function prepare_tenant_directory(?string $directory, bool $slash_ended = true) : string
    {
        if ($directory === null
            || ($directory = trim(trim($directory), '/')) === '') {
            return '';
        }

        return $directory.($slash_ended ? '/' : '');
    }

    public static function prepare_tenant_domain_and_directory(?string $domain, ?string $directory, bool $slash_ended = true) : string
    {
        return self::prepare_tenant_domain($domain).self::prepare_tenant_directory($directory, $slash_ended);
    }
}
