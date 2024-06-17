<?php

namespace phs\system\core\models;

use phs\PHS;
use phs\libraries\PHS_Model;
use phs\libraries\PHS_Roles;
use phs\traits\PHS_Model_Trait_statuses;
use phs\plugins\accounts\models\PHS_Model_Accounts;

class PHS_Model_Data_retention extends PHS_Model
{
    use PHS_Model_Trait_statuses;

    public const TYPE_ARCHIVE = 1, TYPE_DELETE = 2;

    public const INT_DAYS = 'D', INT_MONTHS = 'M', INT_YEARS = 'Y';

    public const STATUS_ACTIVE = 1, STATUS_INACTIVE = 2, STATUS_DELETED = 3;

    protected static array $STATUSES_ARR = [
        self::STATUS_ACTIVE   => ['title' => 'Active'],
        self::STATUS_INACTIVE => ['title' => 'Inactive'],
        self::STATUS_DELETED  => ['title' => 'Deleted'],
    ];

    protected static array $TYPES_ARR = [
        self::TYPE_ARCHIVE => ['title' => 'Archive'],
        self::TYPE_DELETE  => ['title' => 'Delete'],
    ];

    protected static array $INTERVALS_ARR = [
        self::INT_DAYS   => ['title' => 'Days'],
        self::INT_MONTHS => ['title' => 'Months'],
        self::INT_YEARS  => ['title' => 'Years'],
    ];

    public function get_model_version() : string
    {
        return '1.0.1';
    }

    public function get_table_names() : array
    {
        return ['phs_data_retention', 'phs_data_retention_runs'];
    }

    public function get_main_table_name() : string
    {
        return 'phs_data_retention';
    }

    public function get_types(null | bool | string $lang = null) : array
    {
        static $types_arr = [];

        if (empty(self::$TYPES_ARR)) {
            return [];
        }

        if (empty($lang)
            && !empty($types_arr)) {
            return $types_arr;
        }

        $result_arr = $this->translate_array_keys(self::$TYPES_ARR, ['title'], $lang);

        if (empty($lang)) {
            $types_arr = $result_arr;
        }

        return $result_arr;
    }

    public function get_types_as_key_val(null | bool | string $lang = null) : array
    {
        static $types_key_val_arr = null;

        if (empty($lang)
            && $types_key_val_arr !== null) {
            return $types_key_val_arr;
        }

        $key_val_arr = [];
        if (($raw_arr = $this->get_types($lang))) {
            foreach ($raw_arr as $key => $val) {
                if (!is_array($val)) {
                    continue;
                }

                $key_val_arr[$key] = $val['title'];
            }
        }

        if (empty($lang)) {
            $types_key_val_arr = $key_val_arr;
        }

        return $key_val_arr;
    }

    public function valid_type(int $type, null | bool | string $lang = null) : ?array
    {
        $all_types = $this->get_types($lang);
        if (empty($type)
            || !isset($all_types[$type])) {
            return null;
        }

        return $all_types[$type];
    }

    public function get_type_title(int $type, null | bool | string $lang = null) : string
    {
        return ($type_arr = $this->valid_type($type, $lang))
            ? $type_arr['title'] ?? ''
            : '';
    }

    public function get_intervals(null | bool | string $lang = null) : array
    {
        static $intervals_arr = [];

        if (empty(self::$INTERVALS_ARR)) {
            return [];
        }

        if (empty($lang)
            && !empty($intervals_arr)) {
            return $intervals_arr;
        }

        $result_arr = $this->translate_array_keys(self::$INTERVALS_ARR, ['title'], $lang);

        if (empty($lang)) {
            $intervals_arr = $result_arr;
        }

        return $result_arr;
    }

    public function get_intervals_as_key_val(null | bool | string $lang = null) : array
    {
        static $intervals_key_val_arr = null;

        if (empty($lang)
            && $intervals_key_val_arr !== null) {
            return $intervals_key_val_arr;
        }

        $key_val_arr = [];
        if (($raw_arr = $this->get_intervals($lang))) {
            foreach ($raw_arr as $key => $val) {
                if (!is_array($val)) {
                    continue;
                }

                $key_val_arr[$key] = $val['title'];
            }
        }

        if (empty($lang)) {
            $intervals_key_val_arr = $key_val_arr;
        }

        return $key_val_arr;
    }

    public function valid_interval(string $interval, null | bool | string $lang = null) : ?array
    {
        $all_intervals = $this->get_intervals($lang);
        if (empty($interval)
            || !isset($all_intervals[$interval])) {
            return null;
        }

        return $all_intervals[$interval];
    }

    public function get_interval_title(string $interval, ?string $lang = null) : string
    {
        return ($interval_arr = $this->valid_interval($interval, $lang))
            ? $interval_arr['title'] ?? ''
            : '';
    }

    public function start_retention_run(
        int | array $record_data,
        string $last_date,
        int $total_records,
        bool $also_finish = false,
        ?string $destination_table = null,
        ?string $error = null,
    ) : ?array {
        $this->reset_error();

        if (empty($record_data)
            || !($record_arr = $this->data_to_array($record_data))
            || !$this->is_active($record_arr)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Data retention not found in database.'));

            return null;
        }

        if (empty($record_arr['table'])
            || empty($record_arr['date_field'])) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Data retention details are invalid.'));

            return null;
        }

        $now_date = date(self::DATETIME_DB);

        $edit_params = $this->fetch_default_flow_params(['table_name' => 'phs_data_retention_runs']);
        $edit_params['fields'] = [
            'retention_policy_id' => $record_arr['id'],
            'from_table'          => $record_arr['table'],
            'to_table'            => $destination_table ?: null,
            'type'                => $record_arr['type'],
            'date_field'          => $record_arr['date_field'],
            'last_date'           => $last_date,
            'total_records'       => $total_records,
            'current_records'     => 0,
            'start_date'          => $now_date,
            'update_date'         => $now_date,
            'end_date'            => $also_finish ? $now_date : null,
            'error'               => $error,
        ];

        if (!($new_record = $this->insert($edit_params))) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this::_t('Error saving data rentention run details in database.'));

            return null;
        }

        return $new_record;
    }

    public function update_retention_run(
        int | array $run_record,
        int $current_records,
        bool $also_finish = false,
        bool | null | string $error = false,
        ?string $destination_table = null,
    ) : ?array {
        $this->reset_error();

        if (empty($run_record)
            || !($record_arr = $this->data_to_array($run_record, ['table_name' => 'phs_data_retention_runs']))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Data retention run record not found in database.'));

            return null;
        }

        $now_date = date(self::DATETIME_DB);

        $edit_params = $this->fetch_default_flow_params(['table_name' => 'phs_data_retention_runs']);
        $edit_params['fields'] = [];
        if ( !empty($destination_table) ) {
            $edit_params['fields']['to_table'] = $destination_table;
        }
        if ( $error !== false) {
            $edit_params['fields']['error'] = $error;
        }
        if ( !empty($also_finish) ) {
            $edit_params['fields']['end_date'] = $now_date;
        }

        $edit_params['fields']['update_date'] = $now_date;
        $edit_params['fields']['current_records'] = $current_records;

        if (!($new_record = $this->edit($record_arr, $edit_params))) {
            $this->set_error(self::ERR_FUNCTIONALITY,
                $this::_t('Error saving data rentention run details in database.'));

            return null;
        }

        return $new_record;
    }

    public function parse_retention_interval_from_retention_data(int | array $record_data) : ?array
    {
        $this->reset_error();

        if (empty($record_data)
           || !($record_arr = $this->data_to_array($record_data))
           || $this->is_deleted($record_arr)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Data retention not found in database.'));

            return null;
        }

        if ( empty($record_arr['retention'])
            || !($interval_arr = $this->parse_retention_interval($record_arr['retention']))) {
            $this->set_error_if_not_set(self::ERR_PARAMETERS, self::_t('Data retention interval is invalid.'));

            return null;
        }

        return $interval_arr;
    }

    public function parse_retention_interval(?string $retention) : ?array
    {
        $this->reset_error();

        if ( !$retention
            || !($interval_count = (int)substr($retention, 0, -1))
            || !($interval = substr($retention, -1))
            || !$this->valid_interval($interval) ) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Data retention interval is invalid.'));

            return null;
        }

        return [
            'count'    => $interval_count,
            'interval' => $interval,
        ];
    }

    public function generate_retention_interval_time(array $retention_data) : string
    {
        if ( empty($retention_data['count'])
             || (int)$retention_data['count'] <= 0
             || empty($retention_data['interval'])
             || !$this->valid_interval($retention_data['interval'])
             || !($strtime = match ($retention_data['interval'] ) {
                 self::INT_DAYS   => '-'.$retention_data['count'].' day',
                 self::INT_MONTHS => '-'.$retention_data['count'].' month',
                 self::INT_YEARS  => '-'.$retention_data['count'].' year',
                 default          => '',
             }) ) {
            return '';
        }

        return strtotime($strtime);
    }

    public function generate_retention_field(array $retention_data) : string
    {
        if ( empty($retention_data['count'])
            || (int)$retention_data['count'] <= 0
            || empty($retention_data['interval'])
            || !$this->valid_interval($retention_data['interval'])) {
            return '';
        }

        return (int)($retention_data['count']).$retention_data['interval'];
    }

    public function is_active(int | array $record_data) : bool
    {
        return !empty($record_data)
               && ($record_arr = $this->data_to_array($record_data))
               && (int)$record_arr['status'] === self::STATUS_ACTIVE;
    }

    public function is_inactive(int | array $record_data) : bool
    {
        return !empty($record_data)
               && ($record_arr = $this->data_to_array($record_data))
               && (int)$record_arr['status'] === self::STATUS_INACTIVE;
    }

    public function is_deleted(int | array $record_data) : bool
    {
        return !empty($record_data)
               && ($record_arr = $this->data_to_array($record_data))
               && (int)$record_arr['status'] === self::STATUS_DELETED;
    }

    public function act_activate(int | array $record_data) : ?array
    {
        $this->reset_error();

        if (empty($record_data) || !($record_arr = $this->data_to_array($record_data))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Data retention details not found in database.'));

            return null;
        }

        if ($this->is_active($record_arr)) {
            return $record_arr;
        }

        $edit_params = $this->fetch_default_flow_params(['table_name' => 'phs_data_retention']);
        $edit_params['fields'] = [
            'status' => self::STATUS_ACTIVE,
        ];

        if (!($new_record = $this->edit($record_arr, $edit_params))) {
            return null;
        }

        return $new_record;
    }

    public function act_inactivate(int | array $record_data) : ?array
    {
        $this->reset_error();

        if (empty($record_data) || !($record_arr = $this->data_to_array($record_data))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Tenant details not found in database.'));

            return null;
        }

        if ($this->is_inactive($record_arr)) {
            return $record_arr;
        }

        $edit_params = $this->fetch_default_flow_params(['table_name' => 'phs_data_retention']);
        $edit_params['fields'] = [
            'status' => self::STATUS_INACTIVE,
        ];

        if (!($new_record = $this->edit($record_arr, $edit_params))) {
            return null;
        }

        return $new_record;
    }

    public function act_delete(int | array $record_data) : ?array
    {
        $this->reset_error();

        if (empty($record_data) || !($record_arr = $this->data_to_array($record_data))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Tenant details not found in database.'));

            return null;
        }

        if ($this->is_deleted($record_arr)) {
            return $record_arr;
        }

        $edit_params = $this->fetch_default_flow_params(['table_name' => 'phs_data_retention']);
        $edit_params['fields'] = [
            'status' => self::STATUS_DELETED,
        ];

        if (!($new_record = $this->edit($record_arr, $edit_params))) {
            return null;
        }

        return $new_record;
    }

    public function get_model_date_fields(PHS_Model $model_obj, string $table_name) : ?array
    {
        $this->reset_error();

        if (empty($table_name)
           || !($fields_arr = $model_obj->fields_definition(['table_name' => $table_name])) ) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Could not obtain date fields for provided table.'));

            return null;
        }

        $return_arr = [];
        foreach ($fields_arr as $field_name => $field_arr) {
            if (!empty($field_arr['type'])
               && in_array((int)$field_arr['type'], [self::FTYPE_DATE, self::FTYPE_DATETIME], true)) {
                $return_arr[] = $field_name;
            }
        }

        return $return_arr;
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
            case 'phs_data_retention':
                $return_arr = [
                    'id' => [
                        'type'           => self::FTYPE_INT,
                        'primary'        => true,
                        'auto_increment' => true,
                    ],
                    'added_by_uid' => [
                        'type' => self::FTYPE_INT,
                    ],
                    'plugin' => [
                        'type'     => self::FTYPE_VARCHAR,
                        'length'   => 255,
                        'nullable' => true,
                        'default'  => null,
                    ],
                    'model' => [
                        'type'   => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'index'  => true,
                    ],
                    'table' => [
                        'type'   => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'index'  => true,
                    ],
                    'date_field' => [
                        'type'   => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'index'  => true,
                    ],
                    'retention' => [
                        'type'    => self::FTYPE_VARCHAR,
                        'length'  => 20,
                        'comment' => '1Y, 6M, 24D, etc',
                    ],
                    'type' => [
                        'type'    => self::FTYPE_TINYINT,
                        'length'  => 2,
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

            case 'phs_data_retention_runs':
                $return_arr = [
                    'id' => [
                        'type'           => self::FTYPE_INT,
                        'primary'        => true,
                        'auto_increment' => true,
                    ],
                    'retention_policy_id' => [
                        'type'  => self::FTYPE_INT,
                        'index' => true,
                    ],
                    'from_table' => [
                        'type'   => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'index'  => true,
                    ],
                    'to_table' => [
                        'type'   => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'index'  => true,
                    ],
                    'type' => [
                        'type'    => self::FTYPE_TINYINT,
                        'length'  => 2,
                        'index'   => true,
                        'default' => 0,
                    ],
                    'date_field' => [
                        'type'   => self::FTYPE_VARCHAR,
                        'length' => 255,
                        'index'  => true,
                    ],
                    'last_date' => [
                        'type'    => self::FTYPE_DATETIME,
                        'comment' => 'Date used when moving records',
                    ],
                    'total_records' => [
                        'type' => self::FTYPE_INT,
                    ],
                    'current_records' => [
                        'type' => self::FTYPE_INT,
                    ],
                    'start_date' => [
                        'type'  => self::FTYPE_DATETIME,
                        'index' => true,
                    ],
                    'update_date' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                    'end_date' => [
                        'type' => self::FTYPE_DATETIME,
                    ],
                    'error' => [
                        'type' => self::FTYPE_TEXT,
                    ],
                ];
                break;
        }

        return $return_arr;
    }

    protected function get_insert_prepare_params_phs_data_retention($params)
    {
        if (empty($params) || !is_array($params)) {
            return false;
        }

        if (isset($params['fields']['status'])
            && !$this->valid_status($params['fields']['status'])) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide a valid status for data retention policy.'));

            return false;
        }

        if (empty($params['fields']['type'])
            || !$this->valid_type($params['fields']['type'])) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide a valid action type for data retention policy.'));

            return false;
        }

        if (empty($params['fields']['retention'])
            || !($retention_arr = $this->parse_retention_interval($params['fields']['retention']))
            || !($retention = $this->generate_retention_field($retention_arr))
        ) {
            $this->set_error_if_not_set(self::ERR_INSERT, self::_t('Please provide a valid action type for data retention policy.'));

            return false;
        }

        $params['fields']['retention'] = $retention;

        $params['fields']['plugin'] = ($params['fields']['plugin'] ?? null) ?: null;

        /** @var \phs\libraries\PHS_Model_Core_base $model_obj */
        if (empty($params['fields']['model'])
            || empty($params['fields']['table'])
            || empty($params['fields']['date_field'])
            || (!empty($params['fields']['plugin'])
                && !PHS::load_plugin($params['fields']['plugin']))
            || !($model_obj = PHS::load_model($params['fields']['model'], $params['fields']['plugin']))
        ) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide data retention source.'));

            return false;
        }

        if ( !($field_definition = $model_obj->check_column_exists($params['fields']['date_field'], ['table_name' => $params['fields']['table']]))
            || empty($field_definition['type'])
            || !in_array($field_definition['type'], [self::FTYPE_DATE, self::FTYPE_DATETIME], true)
        ) {
            $this->set_error(self::ERR_INSERT, self::_t('Please provide a date or datetime field for data retention source.'));

            return false;
        }

        if ($this->get_details_fields([
            'plugin' => $params['fields']['plugin'],
            'model'  => $params['fields']['model'],
            'table'  => $params['fields']['table'],
            'status' => ['check' => '!=', 'value' => self::STATUS_DELETED],
        ]) ) {
            $this->set_error(self::ERR_INSERT, self::_t('There is already a data retention policy defined on provided table.'));

            return false;
        }

        $params['fields']['added_by_uid'] = (int)($params['fields']['added_by_uid'] ?? 0);
        $params['fields']['status'] ??= self::STATUS_INACTIVE;
        $params['fields']['last_edit'] = date(self::DATETIME_DB);
        $params['fields']['cdate'] ??= $params['fields']['last_edit'];
        $params['fields']['status_date'] ??= $params['fields']['last_edit'];

        return $params;
    }

    protected function get_edit_prepare_params_phs_data_retention($existing_data, $params) : ?array
    {
        if (empty($params) || !is_array($params)) {
            return null;
        }

        if (array_key_exists('retention', $params['fields'])) {
            if (empty($params['fields']['retention'])
                || !($retention_arr = $this->parse_retention_interval($params['fields']['retention']))
                || !($retention = $this->generate_retention_field($retention_arr))
            ) {
                $this->set_error_if_not_set(self::ERR_INSERT,
                    self::_t('Please provide a valid retention interval for data retention policy.'));

                return null;
            }

            $params['fields']['retention'] = $retention;
        }

        $plugin = array_key_exists('plugin', $params['fields'])
            ? $params['fields']['plugin']
            : $existing_data['plugin'];
        $model = $params['fields']['model'] ?? $existing_data['model'] ?? null;
        $table = $params['fields']['table'] ?? $existing_data['table'] ?? null;
        $date_field = $params['fields']['date_field'] ?? $existing_data['date_field'] ?? null;

        /** @var \phs\libraries\PHS_Model_Core_base $model_obj */
        if (empty($model)
            || empty($table)
            || empty($date_field)
            || (!empty($plugin)
                && !PHS::load_plugin($plugin))
            || !($model_obj = PHS::load_model($model, $plugin))
        ) {
            $this->set_error(self::ERR_EDIT, self::_t('Please provide data retention source.'));

            return null;
        }

        if ( !($field_definition = $model_obj->check_column_exists($date_field, ['table_name' => $table]))
            || empty($field_definition['type'])
            || !in_array($field_definition['type'], [self::FTYPE_DATE, self::FTYPE_DATETIME], true)
        ) {
            $this->set_error(self::ERR_EDIT, self::_t('Please provide a date or datetime field for data retention source.'));

            return null;
        }

        if ($this->get_details_fields([
            'plugin' => $plugin,
            'model'  => $model,
            'table'  => $table,
            'id'     => ['check' => '!=', 'value' => $existing_data['id']],
            'status' => ['check' => '!=', 'value' => self::STATUS_DELETED],
        ]) ) {
            $this->set_error(self::ERR_INSERT, self::_t('There is already a data retention policy defined on provided table.'));

            return null;
        }

        if (isset($params['fields']['type'])
            && !$this->valid_type($params['fields']['type'])) {
            $this->set_error(self::ERR_EDIT, $this->_pt('Please provide a valid action type for data retention policy.'));

            return null;
        }

        $now_date = date(self::DATETIME_DB);

        if (isset($params['fields']['status'])) {
            if (!$this->valid_status($params['fields']['status'])) {
                $this->set_error(self::ERR_EDIT, $this->_pt('Please provide a valid status for data retention policy.'));

                return null;
            }

            $params['fields']['status_date'] = $now_date;

            if ($params['fields']['status'] === self::STATUS_DELETED) {
                $params['fields']['deleted'] = $now_date;
            }
        }

        $params['fields']['last_edit'] ??= $now_date;

        return $params;
    }
}
