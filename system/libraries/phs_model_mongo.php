<?php

namespace phs\libraries;

use Exception;
use phs\PHS_Db;
use MongoDB\BSON\ObjectId;
use phs\system\core\events\models\PHS_Event_Model_delete;

abstract class PHS_Model_Mongo extends PHS_Model_Core_base
{
    public const FTYPE_DOUBLE = 1, FTYPE_STRING = 2, FTYPE_OBJECT = 3, FTYPE_ARRAY = 4, FTYPE_BINARY_DATA = 5, FTYPE_UNDEFINED = 6,
        FTYPE_OBJECT_ID = 7, FTYPE_BOOLEAN = 8, FTYPE_DATE = 9, FTYPE_NULL = 10, FTYPE_REGULAR_EXPRESSION = 11, FTYPE_JAVASCRIPT = 12,
        FTYPE_SYMBOL = 13, FTYPE_SCOPE_JAVASCRIPT = 14, FTYPE_INTEGER = 15, FTYPE_TIMESTAMP = 16, FTYPE_MIN_KEY = 17, FTYPE_MAX_KEY = 18;

    private static array $FTYPE_ARR = [
        self::FTYPE_DOUBLE             => ['title' => 'Double', 'type_ids' => [1], 'default_value' => 0, ],
        self::FTYPE_STRING             => ['title' => 'String', 'type_ids' => [2], 'default_value' => '', ],
        self::FTYPE_OBJECT             => ['title' => 'Object', 'type_ids' => [3], 'default_value' => null, ],
        self::FTYPE_ARRAY              => ['title' => 'Array', 'type_ids' => [4], 'default_value' => [], ],
        self::FTYPE_BINARY_DATA        => ['title' => 'Binary data', 'type_ids' => [5], 'default_value' => '', ],
        self::FTYPE_UNDEFINED          => ['title' => 'Undefined', 'type_ids' => [6], 'default_value' => 'undefined', ],
        self::FTYPE_OBJECT_ID          => ['title' => 'Object Id', 'type_ids' => [7], 'default_value' => '', ],
        self::FTYPE_BOOLEAN            => ['title' => 'Boolean', 'type_ids' => [9], 'default_value' => false, ],
        self::FTYPE_DATE               => ['title' => 'Date', 'type_ids' => [10], 'default_value' => null, ],
        self::FTYPE_NULL               => ['title' => 'Null', 'type_ids' => [11], 'default_value' => null, ],
        self::FTYPE_REGULAR_EXPRESSION => ['Regular Expression' => 'Integer', 'type_ids' => [12], 'default_value' => null, ],
        self::FTYPE_JAVASCRIPT         => ['title' => 'JavaScript', 'type_ids' => [13], 'default_value' => '', ],
        self::FTYPE_SYMBOL             => ['title' => 'Symbol', 'type_ids' => [14], 'default_value' => '', ],
        self::FTYPE_SCOPE_JAVASCRIPT   => ['title' => 'JavaScript with scope', 'type_ids' => [15], 'default_value' => '', ],
        self::FTYPE_INTEGER            => ['title' => 'Integer', 'type_ids' => [16, 18], 'default_value' => 0, ],
        self::FTYPE_TIMESTAMP          => ['title' => 'Timestamp', 'type_ids' => [10], 'default_value' => 0, ],
        self::FTYPE_MIN_KEY            => ['title' => 'Min key', 'type_ids' => [255], 'default_value' => '', ],
        self::FTYPE_MAX_KEY            => ['title' => 'Max key', 'type_ids' => [127], 'default_value' => '', ],
    ];

    //
    //  region Abstract model specific methods
    //
    /**
     * @inheritdoc
     */
    public function get_model_driver() : string
    {
        return PHS_Db::DB_DRIVER_MONGO;
    }

    /**
     * @inheritdoc
     */
    public function dynamic_table_structure()
    {
        return false;
    }

    /**
     * @inheritdoc
     *
     * (override the method if not `_id`)
     */
    public function get_primary_key(null | bool | array $params = []) : string
    {
        return '_id';
    }

    /**
     * @inheritdoc
     *
     * Default primary key a hash, override this method if otherwise
     */
    public function prepare_primary_key(int | string $id, null | bool | array $params = []) : int | string
    {
        return trim($id);
    }

    /**
     * @inheritdoc
     */
    public function get_field_types() : array
    {
        return self::$FTYPE_ARR;
    }

    /**
     * Parses flow parameters if anything special should be done for listing records query and returns modified parameters array
     *
     * @param array|bool $params Parameters in the flow
     *
     * @return array Flow parameters array
     */
    public function get_list_prepare_params($params = false)
    {
        return $params;
    }

    /**
     * Parses flow parameters if anything special should be done for count query and returns modified parameters array
     *
     * @param array|bool $params Parameters in the flow
     *
     * @return array Flow parameters array
     */
    public function get_count_prepare_params($params = false)
    {
        return $params;
    }

    final public function alter_table_add_column($field_name, $field_details, $flow_params = false, $params = false)
    {
        $this->reset_error();

        $field_details = self::validate_array($field_details, self::_default_field_arr());

        if (empty($field_name)
         || $field_name == self::T_DETAILS_KEY
         || $field_name == self::EXTRA_INDEXES_KEY
         || !($flow_params = $this->fetch_default_flow_params($flow_params))
         || empty($field_details) || !is_array($field_details)
         || !($field_details = $this->_validate_field($field_details))
         || !($mysql_field_arr = $this->_get_mysql_field_definition($field_name, $field_details))
         || !($flow_table_name = $this->get_flow_table_name($flow_params))
         || empty($mysql_field_arr['field_str'])) {
            PHS_Logger::error('Invalid column definition ['.(!empty($field_name) ? $field_name : '???').'].', PHS_Logger::TYPE_MAINTENANCE);

            $this->set_error(self::ERR_ALTER, self::_t('Invalid column definition [%s].', (!empty($field_name) ? $field_name : '???')));

            return false;
        }

        if ($this->check_column_exists($field_name, $flow_params)) {
            PHS_Logger::error('Column ['.$field_name.'] already exists.', PHS_Logger::TYPE_MAINTENANCE);

            $this->set_error(self::ERR_ALTER, self::_t('Column [%s] already exists.', $field_name));

            return false;
        }

        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        if (empty($params['after_column']) || strtolower(trim($params['after_column'])) == '`first`') {
            $params['after_column'] = ' FIRST';
        } else {
            if (!$this->check_column_exists($params['after_column'], $flow_params)) {
                PHS_Logger::error('Column ['.$params['after_column'].'] in alter table statement doesn\'t exist.', PHS_Logger::TYPE_MAINTENANCE);

                $this->set_error(self::ERR_ALTER, self::_t('Column [%s] in alter table statement doesn\'t exist.', $params['after_column']));

                return false;
            }

            $params['after_column'] = ' AFTER `'.$params['after_column'].'`';
        }

        $db_connection = $this->get_db_connection($flow_params);

        if (!db_query('ALTER TABLE `'.$flow_table_name.'` ADD COLUMN '.$mysql_field_arr['field_str'].$params['after_column'], $db_connection)) {
            PHS_Logger::error('Error altering table to add column ['.$field_name.'].', PHS_Logger::TYPE_MAINTENANCE);

            $this->set_error(self::ERR_ALTER, self::_t('Error altering table to add column [%s].', $field_name));

            return false;
        }

        if (!empty($mysql_field_arr['keys_str'])) {
            if (!db_query('ALTER TABLE `'.$flow_table_name.'` ADD '.$mysql_field_arr['keys_str'], $db_connection)) {
                PHS_Logger::error('Error altering table to add indexes for ['.$field_name.'].', PHS_Logger::TYPE_MAINTENANCE);

                $this->set_error(self::ERR_ALTER, self::_t('Error altering table to add indexes for [%s].', $field_name));

                return false;
            }
        }

        // Force reloading table columns to be sure changes are not cached
        $this->get_table_columns_as_definition($flow_params, true);

        return true;
    }

    final public function alter_table_change_column($field_name, $field_details, $old_field = false, $flow_params = false, $params = false)
    {
        $this->reset_error();

        $field_details = self::validate_array($field_details, self::_default_field_arr());

        if (empty($field_name)
         || $field_name == self::T_DETAILS_KEY
         || $field_name == self::EXTRA_INDEXES_KEY
         || !($flow_params = $this->fetch_default_flow_params($flow_params))
         || !($flow_table_name = $this->get_flow_table_name($flow_params))
         || empty($field_details) || !is_array($field_details)
         || !($field_details = $this->_validate_field($field_details))
         || !($mysql_field_arr = $this->_get_mysql_field_definition($field_name, $field_details))
         || empty($mysql_field_arr['field_str'])) {
            PHS_Logger::error('Invalid column definition ['.(!empty($field_name) ? $field_name : '???').'].', PHS_Logger::TYPE_MAINTENANCE);

            $this->set_error(self::ERR_ALTER, self::_t('Invalid column definition [%s].', (!empty($field_name) ? $field_name : '???')));

            return false;
        }

        $db_connection = $this->get_db_connection($flow_params);

        $old_field_name = false;
        $old_field_details = false;
        if (!empty($old_field) && is_array($old_field)
        && !empty($old_field['name'])
        && !empty($old_field['definition']) && is_array($old_field['definition'])
        && ($old_field_details = $this->_validate_field($old_field['definition']))) {
            $old_field_name = $old_field['name'];
        }

        if (empty($old_field_name)) {
            $db_old_field_name = $field_name;
        } else {
            $db_old_field_name = $old_field_name;
        }

        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        if (!isset($params['alter_indexes'])) {
            $params['alter_indexes'] = true;
        } else {
            $params['alter_indexes'] = (!empty($params['alter_indexes']) ? true : false);
        }

        if (empty($params['after_column'])) {
            $params['after_column'] = '';
        } elseif (strtolower(trim($params['after_column'])) == '`first`') {
            $params['after_column'] = ' FIRST';
        } else {
            if (!$this->check_column_exists($params['after_column'], $flow_params)) {
                PHS_Logger::error('Column ['.$params['after_column'].'] in alter table (change) statement doesn\'t exist in table structure.', PHS_Logger::TYPE_MAINTENANCE);

                $this->set_error(self::ERR_ALTER, self::_t('Column [%s] in alter table (change) statement doesn\'t exist in table structure.', $params['after_column']));

                return false;
            }

            $params['after_column'] = ' AFTER `'.$params['after_column'].'`';
        }

        $sql = 'ALTER TABLE `'.$flow_table_name.'` CHANGE `'.$db_old_field_name.'` '.$mysql_field_arr['field_str'].$params['after_column'];
        if (!db_query($sql, $db_connection)) {
            PHS_Logger::error('Error altering table to change column ['.$field_name.']: ('.$sql.')', PHS_Logger::TYPE_MAINTENANCE);

            $this->set_error(self::ERR_ALTER, self::_t('Error altering table to change column [%s].', $field_name));

            return false;
        }

        if (!empty($params['alter_indexes'])
         && !empty($old_field_name)
         && !empty($old_field_details) && is_array($old_field_details)
         && empty($old_field_details['primary']) && !empty($old_field_details['index'])) {
            if (!db_query('ALTER TABLE `'.$flow_table_name.'` DROP KEY `'.$old_field_name.'`', $db_connection)) {
                PHS_Logger::error('Error altering table (change) to drop OLD index for ['.$old_field_name.'].', PHS_Logger::TYPE_MAINTENANCE);

                $this->set_error(self::ERR_ALTER, self::_t('Error altering table (change) to drop OLD index for [%s].', $old_field_name));

                return false;
            }
        }

        if (!empty($params['alter_indexes'])
         && !empty($mysql_field_arr['keys_str'])) {
            if (!db_query('ALTER TABLE `'.$flow_table_name.'` ADD '.$mysql_field_arr['keys_str'], $db_connection)) {
                PHS_Logger::error('Error altering table (change) to add indexes for ['.$field_name.'].', PHS_Logger::TYPE_MAINTENANCE);

                $this->set_error(self::ERR_ALTER, self::_t('Error altering table (change) to add indexes for [%s].', $field_name));

                return false;
            }
        }

        // Force reloading table columns to be sure changes are not cached
        $this->get_table_columns_as_definition($flow_params, true);

        return true;
    }

    final public function alter_table_drop_column($field_name, $flow_params = false)
    {
        $this->reset_error();

        if (empty($field_name)
         || $field_name == self::T_DETAILS_KEY
         || $field_name == self::EXTRA_INDEXES_KEY
         || !($flow_params = $this->fetch_default_flow_params($flow_params))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Invalid parameters sent to drop column method.'));

            return false;
        }

        if (!$this->check_column_exists($field_name, $flow_params)) {
            return true;
        }

        $db_connection = $this->get_db_connection($flow_params);

        if (!db_query('ALTER TABLE `'.$this->get_flow_table_name($flow_params).'` DROP COLUMN `'.$field_name.'`', $db_connection)) {
            $this->set_error(self::ERR_ALTER, self::_t('Error altering table to drop column [%s].', $field_name));

            return false;
        }

        // Force reloading table columns to be sure changes are not cached
        $this->get_table_columns_as_definition($flow_params, true);

        return true;
    }

    public function create_table_extra_indexes_from_array(array $indexes_array, $flow_params = false) : bool
    {
        $this->reset_error();

        if (empty($indexes_array) || !is_array($indexes_array)) {
            return true;
        }

        foreach ($indexes_array as $index_name => $index_arr) {
            if (empty($index_arr) || !is_array($index_arr)) {
                continue;
            }

            if (!$this->_create_table_extra_index($index_name, $index_arr, $flow_params)) {
                return false;
            }
        }

        return true;
    }

    public function drop_table_index($index_name, $flow_params = false)
    {
        $this->reset_error();

        if (!($model_id = $this->instance_id())
         || empty($index_name)
         || !($flow_params = $this->fetch_default_flow_params($flow_params))
         || empty($flow_params['table_name'])
         || !($full_table_name = $this->get_flow_table_name($flow_params))
         || !($database_name = $this->get_db_database($flow_params))) {
            PHS_Logger::error('Error deleting extra index bad parameters sent to method for model ['.(!empty($model_id) ? $model_id : 'N/A').'].', PHS_Logger::TYPE_MAINTENANCE);

            $this->set_error(self::ERR_TABLE_GENERATE, self::_t('Error deleting extra index for model %s.', (!empty($model_id) ? $model_id : 'N/A')));

            return false;
        }

        $db_connection = $this->get_db_connection($flow_params);

        if (!db_query('ALTER TABLE `'.$full_table_name.'` DROP INDEX `'.$index_name.'`', $db_connection)) {
            PHS_Logger::error('Error deleting extra index ['.$index_name.'] for table ['.$full_table_name.'] for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE);

            $this->set_error(self::ERR_TABLE_GENERATE, self::_t('Error creating extra index %s for table %s for model %s.', $index_name, $full_table_name, $this->instance_id()));

            return false;
        }

        return true;
    }
    //
    // endregion Database structure methods
    //

    //
    //  region CRUD functionality
    //
    public function insert($params)
    {
        $this->reset_error();

        if (!($params = $this->fetch_default_flow_params($params))
         || !isset($params['fields']) || !is_array($params['fields'])) {
            $this->set_error(self::ERR_INSERT, self::_t('Failed validating flow parameters.'));

            return false;
        }

        $params['action'] = 'insert';

        if ((
            @method_exists($this, 'get_insert_prepare_params_'.$params['table_name'])
            && !($params = @call_user_func([$this, 'get_insert_prepare_params_'.$params['table_name']], $params))
        )

        || (
            !@method_exists($this, 'get_insert_prepare_params_'.$params['table_name'])
            && !($params = $this->get_insert_prepare_params($params))
        )
        ) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_INSERT, self::_t('Couldn\'t parse parameters for database insert.'));
            }

            return false;
        }

        if (!($validation_arr = $this->validate_data_for_fields($params))
         || empty($validation_arr['data_arr'])) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_INSERT, self::_t('Error validating parameters.'));
            }

            return false;
        }

        $insert_arr = $validation_arr['data_arr'];
        $db_connection = $this->get_db_connection($params);

        if (!($sql = db_quick_insert($this->get_flow_table_name($params), $insert_arr, $db_connection))
         || !($item_id = db_query_insert($sql, $db_connection))) {
            if (@method_exists($this, 'insert_failed_'.$params['table_name'])) {
                @call_user_func([$this, 'insert_failed_'.$params['table_name']], $insert_arr, $params);
            } else {
                $this->insert_failed($insert_arr, $params);
            }

            if (!$this->has_error()) {
                $this->set_error(self::ERR_INSERT, self::_t('Failed saving information to database.'));
            }

            return false;
        }

        if (!empty($validation_arr['has_raw_fields'])) {
            // there are raw fields, so we query for existing data in table...
            if (!($db_insert_arr = $this->get_details($item_id, $params))) {
                if (!$this->has_error()) {
                    $this->set_error(self::ERR_INSERT, self::_t('Failed saving information to database.'));
                }

                return false;
            }
        } else {
            $db_insert_arr = $this->get_empty_data($params);
            foreach ($insert_arr as $key => $val) {
                $db_insert_arr[$key] = $val;
            }
        }

        $insert_arr = $db_insert_arr;

        $insert_arr[$params['table_index']] = $item_id;

        // Set to tell future calls record was just added to database...
        $insert_arr[self::RECORD_NEW_INSERT_KEY] = true;

        $insert_after_exists = (@method_exists($this, 'insert_after_'.$params['table_name']) ? true : false);

        if ((
            $insert_after_exists
            && !($new_insert_arr = @call_user_func([$this, 'insert_after_'.$params['table_name']], $insert_arr, $params))
        )

        || (
            !$insert_after_exists
            && !($new_insert_arr = $this->insert_after($insert_arr, $params))
        )
        ) {
            $error_arr = $this->get_error();

            $this->hard_delete($insert_arr);

            if (self::arr_has_error($error_arr)) {
                $this->copy_error_from_array($error_arr);
            } elseif (!$this->has_error()) {
                $this->set_error(self::ERR_INSERT, self::_t('Failed actions after database insert.'));
            }

            return false;
        }

        $insert_arr = $new_insert_arr;

        return $insert_arr;
    }

    public function record_is_new($record_arr)
    {
        return !(empty($record_arr) || !is_array($record_arr)
            || empty($record_arr[self::RECORD_NEW_INSERT_KEY]));
    }

    public function edit($existing_data, $params)
    {
        $this->reset_error();

        if (!($params = $this->fetch_default_flow_params($params))
         || !isset($params['fields']) || !is_array($params['fields'])) {
            $this->set_error(self::ERR_EDIT, self::_t('Failed validating flow parameters.'));

            return false;
        }

        if (!($existing_arr = $this->data_to_array($existing_data, $params))
         || !array_key_exists($params['table_index'], $existing_arr)) {
            $this->set_error(self::ERR_EDIT, self::_t('Existing record not found in database.'));

            return false;
        }

        $params['action'] = 'edit';

        $edit_prepare_params_exists = (@method_exists($this, 'get_edit_prepare_params_'.$params['table_name']) ? true : false);

        if ((
            $edit_prepare_params_exists
            && !($params = call_user_func([$this, 'get_edit_prepare_params_'.$params['table_name']], $existing_arr, $params))
        )

        || (
            !$edit_prepare_params_exists
            && !($params = $this->get_edit_prepare_params($existing_arr, $params))
        )
        ) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_EDIT, self::_t('Couldn\'t parse parameters for database edit.'));
            }

            return false;
        }

        if (!($validation_arr = $this->validate_data_for_fields($params))
         || !isset($validation_arr['data_arr']) || !is_array($validation_arr['data_arr'])) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_EDIT, self::_t('Error validating parameters.'));
            }

            return false;
        }

        $full_table_name = $this->get_flow_table_name($params);
        $db_connection = $this->get_db_connection($params);

        $new_existing_arr = $existing_arr;

        $edit_arr = $validation_arr['data_arr'];
        if (!empty($edit_arr)
        && (!($sql = db_quick_edit($full_table_name, $edit_arr, $db_connection))
                || !db_query($sql.' WHERE `'.$full_table_name.'`.`'.$params['table_index'].'` = \''.$existing_arr[$params['table_index']].'\'', $db_connection)
        )) {
            if (@method_exists($this, 'edit_failed_'.$params['table_name'])) {
                @call_user_func([$this, 'edit_failed_'.$params['table_name']], $existing_arr, $edit_arr, $params);
            } else {
                $this->edit_failed($existing_arr, $edit_arr, $params);
            }

            if (!$this->has_error()) {
                $this->set_error(self::ERR_EDIT, self::_t('Failed saving information to database.'));
            }

            return false;
        }

        $edit_after_exists = (@method_exists($this, 'edit_after_'.$params['table_name']) ? true : false);

        if ((
            $edit_after_exists
            && !($new_existing_arr = @call_user_func([$this, 'edit_after_'.$params['table_name']], $existing_arr, $edit_arr, $params))
        )

        || (
            !$edit_after_exists
            && !($new_existing_arr = $this->edit_after($existing_arr, $edit_arr, $params))
        )
        ) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_EDIT, self::_t('Failed actions after database edit.'));
            }

            return false;
        }

        $existing_arr = $new_existing_arr;

        if (!empty($edit_arr)) {
            if (!empty($validation_arr['has_raw_fields'])) {
                // there are raw fields, so we query for existing data in table...
                if (!($existing_arr = $this->get_details($existing_arr['id'], $params))) {
                    if (!$this->has_error()) {
                        $this->set_error(self::ERR_INSERT, self::_t('Failed saving information to database.'));
                    }

                    return false;
                }
            } else {
                foreach ($edit_arr as $key => $val) {
                    $existing_arr[$key] = $val;
                }
            }
        }

        return $existing_arr;
    }

    /**
     * Checks if $constrain_arr conditional fields find a record in database. If they return a record, method will edit that record and if none found, method will add new record
     * with provided fields in $params
     *
     * @param array $constrain_arr Conditional db fields
     * @param array $params Parameters in the flow
     *
     * @return bool
     */
    public function insert_or_edit($constrain_arr, $params)
    {
        $this->reset_error();

        if (!($params = $this->fetch_default_flow_params($params))
         || !isset($params['fields']) || !is_array($params['fields'])) {
            $this->set_error(self::ERR_EDIT, self::_t('Failed validating flow parameters.'));

            return false;
        }

        $check_params = $params;
        $check_params['result_type'] = 'single';
        $check_params['details'] = '*';
        if (!($existing_arr = $this->get_details_fields($constrain_arr, $params))) {
            return $this->insert($params);
        }

        if (!array_key_exists($params['table_index'], $existing_arr)) {
            $this->set_error(self::ERR_EDIT, self::_t('Record doesn\'t have table index as key in result.'));

            return false;
        }

        if (!($new_edit_arr = $this->insert_or_edit_editing($existing_arr, $constrain_arr, $params))) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_INSERT, self::_t('Failed actions before database edit.'));
            }

            return false;
        }

        return $this->edit($existing_arr, $params);
    }

    public function get_count($params = false)
    {
        $this->reset_error();

        if (!($params = $this->fetch_default_flow_params($params))
         || !($params = self::validate_array($params, self::get_count_default_params()))
         || ($params = $this->get_count_list_common_params($params)) === false
         || ($params = $this->get_count_prepare_params($params)) === false
         || ($params = $this->get_query_fields($params)) === false) {
            return 0;
        }

        if (empty($params['extra_sql'])) {
            $params['extra_sql'] = '';
        }

        if (!isset($params['return_query'])) {
            $params['return_query'] = false;
        } else {
            $params['return_query'] = (!empty($params['return_query']) ? true : false);
        }

        $db_connection = $this->get_db_connection($params);

        $distinct_str = '';
        if ($params['count_field'] != '*') {
            $distinct_str = 'DISTINCT ';
        }

        $sql = 'SELECT COUNT('.$distinct_str.$params['count_field'].') AS total_enregs '
               .' FROM `'.$this->get_flow_table_name($params).'` '
               .$params['join_sql']
               .(!empty($params['extra_sql']) ? ' WHERE '.$params['extra_sql'] : '')
               .(!empty($params['group_by']) ? ' GROUP BY '.$params['group_by'] : '')
               .(!empty($params['having_sql']) ? ' HAVING '.$params['having_sql'] : '');

        if (!empty($params['return_query'])) {
            $return_arr = [];
            $return_arr['query'] = $sql;
            $return_arr['params'] = $params;

            return $return_arr;
        }

        $ret = 0;
        if (($qid = db_query($sql, $db_connection))
        && ($result = db_fetch_assoc($qid, $db_connection))) {
            $ret = $result['total_enregs'];
        }

        return $ret;
    }

    public function get_list($params = false)
    {
        $this->reset_error();

        if (!($common_arr = $this->get_list_common($params))
         || !is_array($common_arr)
         || (empty($params['return_query']) && empty($common_arr['qid']))) {
            return false;
        }

        if (!empty($params['return_query'])) {
            return $common_arr;
        }

        if (!empty($params['get_query_id'])) {
            return $common_arr['qid'];
        }

        if (isset($common_arr['params'])) {
            $params = $common_arr['params'];
        }

        $db_connection = $this->get_db_connection($params);

        $ret_arr = [];
        while (($item_arr = db_fetch_assoc($common_arr['qid'], $db_connection))) {
            $key = $params['table_index'];
            if (isset($item_arr[$params['arr_index_field']])) {
                $key = $params['arr_index_field'];
            }

            $ret_arr[$item_arr[$key]] = $item_arr;
        }

        return $ret_arr;
    }

    /**
     * @inheritdoc
     */
    protected function _get_details_for_model(int | string $id, null | bool | array $params = []) : ?array
    {
        $this->reset_error();

        if (!($params = $this->fetch_default_flow_params($params))) {
            return null;
        }

        $db_connection = $this->get_db_connection($params);

        /** @var PHS_Db_mongo $mongo_driver */
        if (empty($id)
         || !($mongo_driver = PHS_Db::db($db_connection))) {
            return null;
        }

        $id_obj = false;
        try {
            if (@class_exists(ObjectId::class, false)) {
                $id_obj = new ObjectId($id);
            }
        } catch (Exception $e) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Cannot obtain Object Id instance.'));

            return null;
        }

        if (empty($id_obj)) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Cannot obtain Object Id instance.'));

            return null;
        }

        $query_arr = $mongo_driver::default_query_arr();
        $query_arr['table_name'] = $this->get_flow_table_name($params);
        $query_arr['filter'] = [
            $params['table_index'] => $id_obj,
        ];
        $query_arr['query_options']['limit'] = 1;

        if (!($qid = $mongo_driver->query($query_arr, $db_connection))
            || !($item_arr = $mongo_driver->fetch_assoc($qid))) {
            return null;
        }

        return $item_arr;
    }

    /**
     * @inheritdoc
     */
    protected function _get_details_fields_for_model(array $constrain_arr, null | bool | array $params = []) : ?array
    {
        if (!($params = $this->fetch_default_flow_params($params))
            || !($common_arr = $this->get_details_common($constrain_arr, $params))
            || !is_array($common_arr)
            || (empty($params['return_query']) && empty($common_arr['qid']))) {
            return null;
        }

        if (!empty($params['return_query'])) {
            return $common_arr;
        }

        if (!empty($common_arr['params'])) {
            $params = $common_arr['params'];
        }

        /** @var \MongoDB\Driver\Cursor $qid */
        $qid = $common_arr['qid'];

        if ($params['result_type'] === 'single') {
            try {
                if (!($result_arr = $qid->toArray())
                 || empty($result_arr[0])) {
                    return null;
                }

                return $result_arr[0];
            } catch (Exception $e) {
                return null;
            }
        }

        // $item_arr = array();
        // while( ($row_arr = @mysqli_fetch_assoc( $common_arr['qid'] )) )
        // {
        //     $item_arr[$row_arr[$params['result_key']]] = $row_arr;
        // }

        return $qid->toArray();
    }

    /**
     * @inheritdoc
     */
    protected function _check_table_exists_for_model(null | bool | array $flow_params = [], bool $force = false) : bool
    {
        $this->reset_error();

        if (!($flow_params = $this->fetch_default_flow_params($flow_params))
         || !($flow_table_name = $this->get_flow_table_name($flow_params))
         || !($my_driver = $this->get_model_driver())) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Failed validating flow parameters.'));

            return false;
        }

        db_supress_errors($this->get_db_connection($flow_params));
        if ((empty(self::$tables_arr[$my_driver]) || !empty($force))
        && ($qid = db_query('SHOW TABLES', $this->get_db_connection($flow_params)))) {
            self::$tables_arr[$my_driver] = [];
            while (($table_name = @mysqli_fetch_assoc($qid))) {
                if (!is_array($table_name)) {
                    continue;
                }

                $table_arr = array_values($table_name);
                self::$tables_arr[$my_driver][$table_arr[0]] = [];

                self::$tables_arr[$my_driver][$table_arr[0]][self::T_DETAILS_KEY] = $this->_parse_mysql_table_details($table_arr[0]);
            }
        }

        db_restore_errors_state($this->get_db_connection($flow_params));

        return (bool)(is_array(self::$tables_arr[$my_driver])
        && array_key_exists($flow_table_name, self::$tables_arr[$my_driver]));
    }

    protected function _install_table_for_model(array $flow_params) : bool
    {
        $this->reset_error();

        if (!($model_id = $this->instance_id())) {
            return false;
        }

        if (empty($this->_definition)
         || !($flow_params = $this->fetch_default_flow_params($flow_params))
         || empty($flow_params['table_name'])
         || !($full_table_name = $this->get_flow_table_name($flow_params))) {
            PHS_Logger::error('Setup for model ['.$model_id.'] is invalid.', PHS_Logger::TYPE_MAINTENANCE);

            $this->set_error(self::ERR_TABLE_GENERATE, self::_t('Setup for model [%s] is invalid.', $model_id));

            return false;
        }

        $table_name = $flow_params['table_name'];

        PHS_Logger::notice('Installing table ['.$full_table_name.'] for model ['.$model_id.']['.$this->get_model_driver().']', PHS_Logger::TYPE_MAINTENANCE);

        if (empty($this->_definition[$table_name])) {
            PHS_Logger::error('Model table ['.$table_name.'] not defined in model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE);

            $this->set_error(self::ERR_TABLE_GENERATE, self::_t('Model table [%s] not defined in model [%s].', $table_name, $model_id));

            return false;
        }

        $table_definition = $this->_definition[$table_name];

        $db_connection = $this->get_db_connection($flow_params);

        if (empty($table_definition[self::T_DETAILS_KEY])) {
            $table_details = $this->_default_table_details_arr();
        } else {
            $table_details = $table_definition[self::T_DETAILS_KEY];
        }

        $sql = 'CREATE TABLE IF NOT EXISTS `'.$full_table_name.'` ( '."\n";
        $all_fields_str = '';
        $keys_str = '';
        foreach ($table_definition as $field_name => $field_details) {
            if (!($field_definition = $this->_get_mysql_field_definition($field_name, $field_details))
             || !is_array($field_definition) || empty($field_definition['field_str'])) {
                continue;
            }

            $all_fields_str .= ($all_fields_str != '' ? ', '."\n" : '').$field_definition['field_str'];

            if (!empty($field_definition['keys_str'])) {
                $keys_str .= ($keys_str != '' ? ',' : '').$field_definition['keys_str'];
            }
        }

        $sql .= $all_fields_str.(!empty($keys_str) ? ', '."\n" : '').$keys_str.(!empty($keys_str) ? "\n" : '')
                .') ENGINE='.$table_details['engine']
                .' DEFAULT CHARSET='.$table_details['charset']
                .(!empty($table_details['collate']) ? ' COLLATE '.$table_details['collate'] : '')
                .(!empty($table_details['comment']) ? ' COMMENT=\''.self::safe_escape($table_details['comment']).'\'' : '').';';

        if (!db_query($sql, $db_connection)) {
            PHS_Logger::error('Error generating table ['.$full_table_name.'] for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE);

            $this->set_error(self::ERR_TABLE_GENERATE, self::_t('Error generating table %s for model %s.', $full_table_name, $this->instance_id()));

            return false;
        }

        if (!$this->_create_table_extra_indexes($flow_params)) {
            return false;
        }

        // Re-cache table structure...
        $this->get_table_columns_as_definition($flow_params, true);

        PHS_Logger::notice('DONE Installing table ['.$full_table_name.'] for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE);

        return true;
    }

    protected function _update_table_for_model(array $flow_params) : bool
    {
        $this->reset_error();

        if (!($model_id = $this->instance_id())) {
            return false;
        }

        if (empty($this->_definition) || !is_array($this->_definition)
         || !($flow_params = $this->fetch_default_flow_params($flow_params))
         || empty($flow_params['table_name'])
         || !($full_table_name = $this->get_flow_table_name($flow_params))) {
            PHS_Logger::error('Setup for model ['.$model_id.'] is invalid.', PHS_Logger::TYPE_MAINTENANCE);

            $this->set_error(self::ERR_UPDATE_TABLE, self::_t('Setup for model [%s] is invalid.', $model_id));

            return false;
        }

        if (!$this->check_table_exists($flow_params)) {
            return $this->install_table($flow_params);
        }

        $table_name = $flow_params['table_name'];

        PHS_Logger::notice('Updating table ['.$full_table_name.'] for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE);

        if (empty($this->_definition[$table_name])) {
            PHS_Logger::error('Model table ['.$table_name.'] not defined in model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE);

            $this->set_error(self::ERR_UPDATE_TABLE, self::_t('Model table [%s] not defined in model [%s].', $table_name, $model_id));

            return false;
        }

        $table_definition = $this->_definition[$table_name];
        $db_table_definition = $this->get_table_columns_as_definition($flow_params);

        // extracting old names so we get quick field definition from old names...
        $old_field_names_arr = [];
        $found_old_field_names_arr = [];
        foreach ($table_definition as $field_name => $field_definition) {
            if ($field_name == self::T_DETAILS_KEY
             || $field_name == self::EXTRA_INDEXES_KEY
             || !is_array($field_definition)
             || empty($field_definition['old_names']) || !is_array($field_definition['old_names'])) {
                continue;
            }

            foreach ($field_definition['old_names'] as $old_field_name) {
                if (!empty($found_old_field_names_arr[$old_field_name])) {
                    PHS_Logger::error('Old field name '.$old_field_name.' found twice in same table model table ['.$table_name.'], model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE);

                    $this->set_error(self::ERR_UPDATE_TABLE,
                        self::_t('Old field name %s found twice in same table model table %s, model %s.', $old_field_name, $table_name, $model_id));

                    return false;
                }

                // Check if in current table structure we have this old name...
                if (empty($db_table_definition[$old_field_name])) {
                    continue;
                }

                $found_old_field_names_arr[$old_field_name] = true;
                $old_field_names_arr[$field_name] = $old_field_name;
            }
        }

        $db_connection = $this->get_db_connection($flow_params);

        if (empty($table_definition[self::T_DETAILS_KEY])) {
            $table_details = $this->_default_table_details_arr();
        } else {
            $table_details = $table_definition[self::T_DETAILS_KEY];
        }

        if (empty($db_table_details[self::T_DETAILS_KEY])) {
            $db_table_details = $this->_default_table_details_arr();
        } else {
            $db_table_details = $table_definition[self::T_DETAILS_KEY];
        }

        if (($changed_values = $this->_table_details_changed($db_table_details, $table_details))) {
            $sql = 'ALTER TABLE `'.$full_table_name.'`';
            if (!empty($changed_values['engine'])) {
                $sql .= ' ENGINE='.$changed_values['engine'];
            }

            if (!empty($changed_values['charset']) || !empty($changed_values['collate'])) {
                $sql .= ' DEFAULT CHARSET=';
                if (!empty($changed_values['charset'])) {
                    $sql .= $changed_values['charset'];
                } else {
                    $sql .= $table_details['charset'];
                }

                $sql .= ' COLLATE ';
                if (!empty($changed_values['collate'])) {
                    $sql .= $changed_values['collate'];
                } else {
                    $sql .= $table_details['collate'];
                }
            }

            if (!empty($changed_values['comment'])) {
                $sql .= ' COMMENT=\''.self::safe_escape($table_details['comment']).'\'';
            }

            // ALTER TABLE `table_name` ENGINE=INNODB DEFAULT CHARSET=utf8 COLLATE utf8_general_ci COMMENT "New comment"
            if (!db_query($sql, $db_connection)) {
                PHS_Logger::error('Error updating table properties ['.$full_table_name.'] for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE);

                $this->set_error(self::ERR_TABLE_GENERATE, self::_t('Error updating table properties %s for model %s.', $table_name, $this->instance_id()));

                return false;
            }
        }

        $after_field = '`first`';
        $fields_found_in_old_structure = [];
        // First we add or remove missing fields
        foreach ($table_definition as $field_name => $field_definition) {
            if ($field_name == self::T_DETAILS_KEY
             || $field_name == self::EXTRA_INDEXES_KEY) {
                continue;
            }

            $field_extra_params = [];
            $field_extra_params['after_column'] = $after_field;

            $after_field = $field_name;

            if (empty($db_table_definition[$field_name])) {
                // Field doesn't exist in in db structure...
                // Check if we must rename it...
                if (!empty($old_field_names_arr[$field_name])) {
                    $fields_found_in_old_structure[$old_field_names_arr[$field_name]] = true;

                    // Yep we rename it...
                    $old_field = [];
                    $old_field['name'] = $old_field_names_arr[$field_name];
                    $old_field['definition'] = $db_table_definition[$old_field_names_arr[$field_name]];

                    if (!$this->alter_table_change_column($field_name, $field_definition, $old_field, $flow_params, $field_extra_params)) {
                        if (!$this->has_error()) {
                            PHS_Logger::error('Error changing column '.$old_field_names_arr[$field_name].', table '.$full_table_name.', model '.$model_id.'.', PHS_Logger::TYPE_MAINTENANCE);

                            $this->set_error(self::ERR_UPDATE_TABLE, self::_t('Error changing column %s, table %s, model %s.', $old_field_names_arr[$field_name], $full_table_name, $model_id));
                        }

                        return false;
                    }

                    continue;
                }

                // Didn't find old fields to rename... Just add it...
                if (!$this->alter_table_add_column($field_name, $field_definition, $flow_params, $field_extra_params)) {
                    if (!$this->has_error()) {
                        PHS_Logger::error('Error adding column '.$field_name.', table '.$full_table_name.', model '.$model_id.'.', PHS_Logger::TYPE_MAINTENANCE);

                        $this->set_error(self::ERR_UPDATE_TABLE, self::_t('Error adding column %s, table %s, model %s.', $field_name, $full_table_name, $model_id));
                    }

                    return false;
                }

                continue;
            }

            $fields_found_in_old_structure[$field_name] = true;

            $alter_params = $field_extra_params;
            $alter_params['alter_indexes'] = false;

            // Call alter table anyway as position might change...
            if (!$this->alter_table_change_column($field_name, $field_definition, false, $flow_params, $alter_params)) {
                if (!$this->has_error()) {
                    PHS_Logger::error('Error updating column '.$field_name.', table '.$full_table_name.', model '.$model_id.'.', PHS_Logger::TYPE_MAINTENANCE);

                    $this->set_error(self::ERR_UPDATE_TABLE, self::_t('Error updating column %s, table %s, model %s.', $field_name, $full_table_name, $model_id));
                }

                return false;
            }
        }

        // Delete fields which we didn't find in new structure
        foreach ($db_table_definition as $field_name => $junk) {
            if ($field_name == self::T_DETAILS_KEY
             || $field_name == self::EXTRA_INDEXES_KEY
             || !empty($fields_found_in_old_structure[$field_name])) {
                continue;
            }

            if (!$this->alter_table_drop_column($field_name, $flow_params)) {
                if (!$this->has_error()) {
                    PHS_Logger::error('Error dropping column '.$field_name.', table '.$full_table_name.', model '.$model_id.'.', PHS_Logger::TYPE_MAINTENANCE);

                    $this->set_error(self::ERR_UPDATE_TABLE, self::_t('Error dropping column %s, table %s, model %s.', $field_name, $full_table_name, $model_id));
                }

                return false;
            }
        }

        // Check extra indexes...
        if (!empty($table_definition[self::EXTRA_INDEXES_KEY])
         || !empty($db_table_definition[self::EXTRA_INDEXES_KEY])) {
            if (!empty($table_definition[self::EXTRA_INDEXES_KEY])
            && empty($db_table_definition[self::EXTRA_INDEXES_KEY])) {
                // new extra indexes
                if (!$this->_create_table_extra_indexes($flow_params)) {
                    return false;
                }
            } elseif (empty($table_definition[self::EXTRA_INDEXES_KEY])
                  && !empty($db_table_definition[self::EXTRA_INDEXES_KEY])) {
                // delete existing extra indexes
                if (!$this->drop_table_indexes_from_array($db_table_definition[self::EXTRA_INDEXES_KEY], $flow_params)) {
                    return false;
                }
            } else {
                // do the diff on extra indexes...
                $current_indexes = [];
                foreach ($db_table_definition[self::EXTRA_INDEXES_KEY] as $index_name => $index_arr) {
                    if (empty($index_arr['fields']) || !is_array($index_arr['fields'])) {
                        $index_arr['fields'] = [];
                    }

                    if (array_key_exists($index_name, $table_definition[self::EXTRA_INDEXES_KEY])
                    && !empty($table_definition[self::EXTRA_INDEXES_KEY][$index_name]['fields'])
                    && is_array($table_definition[self::EXTRA_INDEXES_KEY][$index_name]['fields'])
                    && !($index_arr['unique'] xor $table_definition[self::EXTRA_INDEXES_KEY][$index_name]['unique'])
                    && self::arrays_have_same_values($index_arr['fields'], $table_definition[self::EXTRA_INDEXES_KEY][$index_name]['fields'])) {
                        $current_indexes[$index_name] = true;
                        continue;
                    }

                    $this->drop_table_index($index_name, $flow_params);
                }

                // add new extra indexes after we did the diff with existing ones...
                foreach ($table_definition[self::EXTRA_INDEXES_KEY] as $index_name => $index_arr) {
                    if (!empty($current_indexes[$index_name])) {
                        continue;
                    }

                    if (!$this->_create_table_extra_index($index_name, $index_arr, $flow_params)) {
                        return false;
                    }
                }
            }
        }

        // Force reloading table columns to be sure changes are not cached
        $this->get_table_columns_as_definition($flow_params, true);

        PHS_Logger::notice('DONE Updating table ['.$full_table_name.'] for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE);

        return true;
    }

    protected function _install_missing_table_for_model(array $flow_params) : bool
    {
        return $this->_install_table_for_model($flow_params);
    }

    /**
     * @inheritdoc
     */
    protected function _uninstall_table_for_model(null | bool | array $flow_params) : bool
    {
        $this->reset_error();

        if (empty($this->_definition)
            || !($flow_params = $this->fetch_default_flow_params($flow_params))
            || !($db_connection = $this->get_db_connection($flow_params))
            || !($full_table_name = $this->get_flow_table_name($flow_params))) {
            return true;
        }

        if (!db_query('DROP TABLE IF EXISTS `'.$full_table_name.'`;', $db_connection)) {
            $this->set_error(self::ERR_UNINSTALL_TABLE, self::_t('Error dropping table %s.', $full_table_name));

            return false;
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    protected function _get_table_definition_for_model_from_database(null | bool | array $flow_params = [], bool $force = false) : ?array
    {
        $this->reset_error();

        if (!($flow_params = $this->fetch_default_flow_params($flow_params))
         || !($flow_table_name = $this->get_flow_table_name($flow_params))
         || !($flow_database_name = $this->get_db_database($flow_params))
         || !($my_driver = $this->get_model_driver())) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Failed validating flow parameters.'));

            return null;
        }

        if (($qid = db_query('SHOW FULL COLUMNS FROM `'.$flow_table_name.'`', $flow_params['db_connection']))) {
            while (($field_arr = db_fetch_assoc($qid, $flow_params['db_connection']))) {
                if (!is_array($field_arr)
                 || empty($field_arr['Field'])) {
                    continue;
                }

                self::$tables_arr[$my_driver][$flow_table_name][$field_arr['Field']] = $this->_parse_mysql_field_result($field_arr);
            }
        }

        // Get extra indexes...
        if (($qid = db_query('SELECT * FROM information_schema.statistics '
                              .' WHERE '
                              .' table_schema = \''.$flow_database_name.'\' AND table_name = \''.$flow_table_name.'\''
                              .' AND SEQ_IN_INDEX > 1', $flow_params['db_connection']))) {
            while (($index_arr = db_fetch_assoc($qid, $flow_params['db_connection']))) {
                if (!is_array($index_arr)
                 || empty($index_arr['INDEX_NAME'])
                 || empty($index_arr['COLUMN_NAME'])) {
                    continue;
                }

                if (empty(self::$tables_arr[$my_driver][$flow_table_name][self::EXTRA_INDEXES_KEY])
                 || !is_array(self::$tables_arr[$my_driver][$flow_table_name][self::EXTRA_INDEXES_KEY])) {
                    self::$tables_arr[$my_driver][$flow_table_name][self::EXTRA_INDEXES_KEY] = [];
                }

                if (empty(self::$tables_arr[$my_driver][$flow_table_name][self::EXTRA_INDEXES_KEY][$index_arr['INDEX_NAME']])
                 || !is_array(self::$tables_arr[$my_driver][$flow_table_name][self::EXTRA_INDEXES_KEY][$index_arr['INDEX_NAME']])) {
                    self::$tables_arr[$my_driver][$flow_table_name][self::EXTRA_INDEXES_KEY][$index_arr['INDEX_NAME']] = [];
                }

                if (empty(self::$tables_arr[$my_driver][$flow_table_name][self::EXTRA_INDEXES_KEY][$index_arr['INDEX_NAME']]['fields'])
                 || !is_array(self::$tables_arr[$my_driver][$flow_table_name][self::EXTRA_INDEXES_KEY][$index_arr['INDEX_NAME']]['fields'])) {
                    self::$tables_arr[$my_driver][$flow_table_name][self::EXTRA_INDEXES_KEY][$index_arr['INDEX_NAME']]['fields'] = [];
                }

                self::$tables_arr[$my_driver][$flow_table_name][self::EXTRA_INDEXES_KEY][$index_arr['INDEX_NAME']]['fields'][] = $index_arr['COLUMN_NAME'];

                if (empty($index_arr['NON_UNIQUE'])) {
                    self::$tables_arr[$my_driver][$flow_table_name][self::EXTRA_INDEXES_KEY][$index_arr['INDEX_NAME']]['unique'] = true;
                } else {
                    self::$tables_arr[$my_driver][$flow_table_name][self::EXTRA_INDEXES_KEY][$index_arr['INDEX_NAME']]['unique'] = false;
                }
            }
        }

        return self::$tables_arr[$my_driver][$flow_table_name];
    }

    /**
     * @inheritdoc
     */
    protected function _hard_delete_for_model(array | PHS_Record_data $existing_data, null | bool | array $params = []) : bool
    {
        self::st_reset_error();
        $this->reset_error();

        if (empty($existing_data)
         || !($params = $this->fetch_default_flow_params($params))
         || empty($params['table_index'])
         || !isset($existing_data[$params['table_index']])) {
            return false;
        }

        $db_connection = $this->get_db_connection($params['db_connection']);

        if (!db_query('DELETE FROM `'.$this->get_flow_table_name($params).'` '
                      .'WHERE `'.$params['table_index'].'` = \''.db_escape($existing_data[$params['table_index']], $db_connection).'\'',
            $db_connection)) {
            return false;
        }

        PHS_Event_Model_delete::trigger_for_model($this::class, [
            'flow_params' => $params,
            'record_data' => $existing_data,
            'model_obj'   => $this,
        ]);

        return true;
    }

    /**
     * @inheritdoc
     */
    protected function _default_table_details_arr() : array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    protected function _default_table_extra_index_arr() : array
    {
        return [
            'unique' => false,
            'fields' => [],
        ];
    }

    /**
     * @inheritdoc
     */
    protected function _validate_field(array $field_arr) : ?array
    {
        $field_arr = self::validate_array_to_new_array($field_arr, self::_default_field_arr());

        if (empty($field_arr['type'])
            || !($field_details = $this->valid_field_type($field_arr['type']))) {
            return null;
        }

        if (isset($field_details['nullable'])) {
            $field_arr['nullable'] = !empty($field_details['nullable']);
        }

        if ($field_arr['default'] === null
            && isset($field_details['default_value'])) {
            $field_arr['default'] = $field_details['default_value'];
        }

        if (empty($field_arr['raw_default'])
            && !empty($field_details['raw_default'])) {
            $field_arr['raw_default'] = $field_details['raw_default'];
        }

        return $field_arr;
    }

    /**
     * @inheritdoc
     */
    protected function _validate_field_value(mixed $value, string $field_name, array $field_details) : mixed
    {
        $this->reset_error();

        if (empty($field_name)) {
            $field_name = self::_t('N/A');
        }

        if (!($field_details = $this->_validate_field($field_details))
         || empty($field_details['type'])
         || !($field_type_arr = $this->valid_field_type($field_details['type']))) {
            self::st_set_error(self::ERR_MODEL_FIELDS, self::_t('Couldn\'t validate field %s.', $field_name));

            return false;
        }

        $phs_params_arr = [];
        $phs_params_arr['trim_before'] = true;

        switch ($field_details['type']) {
            case self::FTYPE_INTEGER:
                if (($value = PHS_Params::set_type($value, PHS_Params::T_INT, $phs_params_arr)) === null) {
                    $value = 0;
                }
                break;

            case self::FTYPE_DATE:
                if (empty_db_date($value)) {
                    $value = null;
                } else {
                    $value = @date(self::DATE_DB, parse_db_date($value));
                }
                break;

            case self::FTYPE_DOUBLE:

                $digits = 0;
                if (!empty($field_details['length'])
                && is_string($field_details['length'])) {
                    $length_arr = explode(',', $field_details['length']);
                    $digits = (!empty($length_arr[1]) ? intval(trim($length_arr[1])) : 0);
                }

                $phs_params_arr['digits'] = $digits;

                if (($value = PHS_Params::set_type($value, PHS_Params::T_FLOAT, $phs_params_arr)) === null) {
                    $value = 0;
                }
                break;
        }

        return $value;
    }
    //
    //  endregion Abstract model specific methods
    //

    //
    //  region Methods that will be overridden in child classes
    //
    /**
     * Called first in insert flow.
     * Parses flow parameters if anything special should be done.
     * This should do checks on raw parameters received by insert method.
     *
     * @param array|bool $params Parameters in the flow
     *
     * @return array|bool Flow parameters array
     */
    protected function get_insert_prepare_params($params)
    {
        return $params;
    }

    /**
     * Called first in edit flow.
     * Parses flow parameters if anything special should be done.
     * This should do checks on raw parameters received by edit method.
     *
     * @param array|int $existing_data Data which already exists in database (id or full array with all database fields)
     * @param array|bool $params Parameters in the flow
     *
     * @return array|bool Flow parameters array
     */
    protected function get_edit_prepare_params($existing_data, $params)
    {
        return $params;
    }

    /**
     * @param array $insert_arr Data array which should be added to database
     * @param array $params Flow parameters
     */
    protected function insert_failed($insert_arr, $params)
    {
    }

    /**
     * Called right after a database update fails.
     *
     * @param array|int $existing_data Data which already exists in database (id or full array with all database fields)
     * @param array $edit_arr Data array which should be saved in database (only fields that change)
     * @param array $params Flow parameters
     */
    protected function edit_failed($existing_data, $edit_arr, $params)
    {
    }

    /**
     * Called right after a successfull insert in database. Some model need more database work after successfully adding records in database or eventually chaining
     * database inserts. If one chain fails function should return false so all records added before to be hard-deleted. In case of success, function will return an array with all
     * key-values added in database.
     *
     * @param array $insert_arr Data array added with success in database
     * @param array $params Flow parameters
     *
     * @return array|bool Returns data array added in database (with changes, if required) or false if record should be deleted from database.
     *                    Deleted record will be hard-deleted
     */
    protected function insert_after($insert_arr, $params)
    {
        return $insert_arr;
    }

    /**
     * Called right after a successfull edit action. Some model need more database work after editing records. This action is called even if model didn't save anything
     * in database.
     *
     * @param array|int $existing_data Data which already exists in database (id or full array with all database fields)
     * @param array $edit_arr Data array saved with success in database. This can also be an empty array (nothing to save in database)
     * @param array $params Flow parameters
     *
     * @return array|bool Returns data array added in database (with changes, if required) or false if functionality failed.
     *                    Saved information will not be rolled back.
     */
    protected function edit_after($existing_data, $edit_arr, $params)
    {
        return $existing_data;
    }

    /**
     * Called right after finding a record in database in PHS_Model_Core_base::insert_or_edit() with provided conditions. This helps unsetting some fields which should not
     * be passed to edit function in case we execute an edit.
     *
     * @param array $existing_arr Data which already exists in database (array with all database fields)
     * @param array $constrain_arr Conditional db fields
     * @param array $params Flow parameters
     *
     * @return array Returns modified parameters (if required)
     */
    protected function insert_or_edit_editing($existing_arr, $constrain_arr, $params)
    {
        return $params;
    }

    /**
     * Prepares parameters common to _count and _list methods
     *
     * @param array|bool $params Parameters in the flow
     *
     * @return array Flow parameters array
     */
    protected function get_count_list_common_params($params = false)
    {
        return $params;
    }

    protected function _create_table_extra_indexes($flow_params)
    {
        $this->reset_error();

        if (!($model_id = $this->instance_id())
         || empty($this->_definition) || !is_array($this->_definition)
         || !($flow_params = $this->fetch_default_flow_params($flow_params))
         || empty($flow_params['table_name'])
         || empty($this->_definition[$flow_params['table_name']])
         || !($full_table_name = $this->get_flow_table_name($flow_params))) {
            return false;
        }

        $table_definition = $this->_definition[$flow_params['table_name']];

        if (empty($table_definition[self::EXTRA_INDEXES_KEY])
         || !is_array($table_definition[self::EXTRA_INDEXES_KEY])
         || !($database_name = $this->get_db_database($flow_params))) {
            return true;
        }

        foreach ($table_definition[self::EXTRA_INDEXES_KEY] as $index_name => $index_arr) {
            if (!$this->_create_table_extra_index($index_name, $index_arr, $flow_params)) {
                return false;
            }
        }

        return true;
    }

    protected function drop_table_indexes_from_array($indexes_array, $flow_params = false)
    {
        $this->reset_error();

        if (empty($indexes_array) || !is_array($indexes_array)) {
            return true;
        }

        foreach ($indexes_array as $index_name => $index_arr) {
            if (empty($index_arr) || !is_array($index_arr)) {
                continue;
            }

            if (!$this->drop_table_index($index_name, $flow_params)) {
                return false;
            }
        }

        return true;
    }
    //
    //  endregion CRUD functionality
    //

    //
    //  region Querying database functionality
    //
    protected function get_details_common(array $constrain_arr, null | bool | array $params = [])
    {
        if (!($params = $this->fetch_default_flow_params($params))) {
            return false;
        }

        if (empty($params['query_fields']) || !is_array($params['query_fields'])) {
            $params['query_fields'] = [];
        }

        if (!isset($params['result_type'])) {
            $params['result_type'] = 'single';
        }

        $params['result_key'] ??= $params['table_index'];
        $params['return_query'] = !isset($params['return_query']) || !empty($params['return_query']);

        if (!isset($params['limit'])
            || $params['result_type'] === 'single') {
            $params['limit'] = 1;
        } else {
            $params['limit'] = (int)$params['limit'];
            $params['result_type'] = 'list';
        }

        $db_connection = $this->get_db_connection($params);

        /** @var PHS_Db_mongo $mongo_driver */
        if (empty($constrain_arr) || !is_array($constrain_arr)
         || !($mongo_driver = PHS_Db::db($db_connection))) {
            return false;
        }

        if (empty($params['query_fields']['read_preference']) || !is_array($params['query_fields']['read_preference'])) {
            $params['query_fields']['read_preference'] = false;
        }

        if (empty($params['query_fields']['query_options']) || !is_array($params['query_fields']['query_options'])) {
            $params['query_fields']['query_options'] = $mongo_driver::default_query_options_arr();
        }

        $params['query_fields']['query_options']['limit'] = $params['limit'];

        $query_arr = $mongo_driver::default_query_arr();
        $query_arr['table_name'] = $this->get_flow_table_name($params);
        $query_arr['filter'] = $constrain_arr;
        $query_arr['query_options'] = $params['query_fields']['query_options'];

        if (!empty($params['query_fields']['read_preference']) && is_array($params['query_fields']['read_preference'])) {
            $query_arr['read_preference'] = $params['query_fields']['read_preference'];
        }
        if (!empty($params['query_fields']['cursor_type_map']) && is_array($params['query_fields']['cursor_type_map'])) {
            $query_arr['cursor_type_map'] = $params['query_fields']['cursor_type_map'];
        }

        $qid = false;
        $item_count = 0;

        if (empty($params['return_query'])
        && (!($qid = $mongo_driver->query($query_arr, $db_connection))
            // or !($item_count = $mongo_driver->num_rows( $qid ))
        )) {
            return false;
        }

        $return_arr = [];
        $return_arr['query'] = $query_arr;
        $return_arr['params'] = $params;
        $return_arr['qid'] = $qid;
        $return_arr['item_count'] = $item_count;

        return $return_arr;
    }

    protected function get_list_common($params = false)
    {
        $this->reset_error();

        if (!($params = $this->fetch_default_flow_params($params))
         || !($params = self::validate_array($params, self::get_list_default_params()))) {
            return false;
        }

        $db_connection = $this->get_db_connection($params);
        $full_table_name = $this->get_flow_table_name($params);

        if (!isset($params['return_query'])) {
            $params['return_query'] = false;
        } else {
            $params['return_query'] = (!empty($params['return_query']) ? true : false);
        }

        // Field which will be used as key in result array (be sure is unique)
        if (empty($params['arr_index_field'])) {
            $params['arr_index_field'] = $params['table_index'];
        }

        if (($params = $this->get_count_list_common_params($params)) === false
         || ($params = $this->get_list_prepare_params($params)) === false) {
            return false;
        }

        $sql = 'SELECT '.$params['db_fields'].' '
               .' FROM `'.$full_table_name.'` '
               .$params['join_sql']
               .(!empty($params['extra_sql']) ? ' WHERE '.$params['extra_sql'] : '')
               .(!empty($params['group_by']) ? ' GROUP BY '.$params['group_by'] : '')
               .(!empty($params['having_sql']) ? ' HAVING '.$params['having_sql'] : '')
               .(!empty($params['order_by']) ? ' ORDER BY '.$params['order_by'] : '')
               .' LIMIT '.$params['offset'].', '.$params['enregs_no'];

        $qid = false;
        $rows_number = 0;

        if (empty($params['return_query'])
         && (!($qid = db_query($sql, $db_connection))
                || !($rows_number = db_num_rows($qid, $db_connection))
         )) {
            return false;
        }

        $return_arr = [];
        $return_arr['query'] = $sql;
        $return_arr['params'] = $params;
        $return_arr['qid'] = $qid;
        $return_arr['item_count'] = $rows_number;

        return $return_arr;
    }
    //
    //  endregion Methods that will be overridden in child classes
    //

    //
    // region Database structure methods
    //
    private function _parse_mysql_table_details($table_name, $flow_params = false)
    {
        $this->reset_error();

        if (empty($table_name)) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Please provide table name.'));

            return false;
        }

        if (!($flow_params = $this->fetch_default_flow_params($flow_params))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Failed validating flow parameters.'));

            return false;
        }

        $table_details = $this->_default_table_details_arr();
        if (($qid = db_query('SHOW TABLE STATUS WHERE Name = \''.$table_name.'\'', $this->get_db_connection($flow_params)))
        && ($result_arr = @mysqli_fetch_assoc($qid))) {
            if (!empty($result_arr['Engine'])) {
                $table_details['engine'] = $result_arr['Engine'];
            }
            if (!empty($result_arr['Comment'])) {
                $table_details['comment'] = $result_arr['Comment'];
            }
            if (!empty($result_arr['Collation'])) {
                $table_details['collate'] = $result_arr['Collation'];
                if (($collate_parts = explode('_', $table_details['collate']))) {
                    $table_details['charset'] = $collate_parts[0];
                }
            }
        }

        return $table_details;
    }

    private function _get_type_from_mysql_field_type($type)
    {
        $type = trim($type);
        if (empty($type)) {
            return false;
        }

        $return_arr = [];
        $return_arr['type'] = self::FTYPE_UNKNOWN;
        $return_arr['length'] = null;

        $mysql_type = '';
        $mysql_length = '';
        if (!preg_match('@([a-z]+)([\(\s*[0-9,\s]+\s*\)]*)@i', $type, $matches)) {
            $mysql_type = $type;
        } else {
            if (!empty($matches[1])) {
                $mysql_type = strtolower(trim($matches[1]));
            }

            if (!empty($matches[2])) {
                $mysql_length = trim($matches[2], ' ()');
            }
        }

        if (!empty($mysql_type)
        && ($field_types = $this->get_field_types())
        && is_array($field_types)) {
            $mysql_type = strtolower(trim($mysql_type));
            foreach ($field_types as $field_type => $field_arr) {
                if (empty($field_arr['title'])) {
                    continue;
                }

                if ($field_arr['title'] == $mysql_type) {
                    $return_arr['type'] = $field_type;
                    break;
                }
            }
        }

        if (!($field_arr = $this->valid_field_type($return_arr['type']))) {
            return $return_arr;
        }

        if (!empty($mysql_length)) {
            $length_arr = [];
            if (($parts_arr = explode(',', $mysql_length))
            && is_array($parts_arr)) {
                foreach ($parts_arr as $part) {
                    $part = trim($part);
                    if ($part === '') {
                        continue;
                    }

                    $length_arr[] = $part;
                }
            }

            $return_arr['length'] = implode(',', $length_arr);
        }

        return $return_arr;
    }

    private function _parse_mysql_field_result($field_arr)
    {
        $field_arr = self::validate_array($field_arr, self::_default_mysql_table_field_fields());
        $model_field_arr = self::_default_field_arr();

        if (!($model_field_type = $this->_get_type_from_mysql_field_type($field_arr['Type']))) {
            $model_field_arr['type'] = self::FTYPE_UNKNOWN;
        } else {
            $model_field_arr['type'] = $model_field_type['type'];
            $model_field_arr['length'] = $model_field_type['length'];
        }

        $model_field_arr['nullable'] = ((!empty($field_arr['Null']) && strtolower($field_arr['Null']) == 'yes') ? true : false);
        $model_field_arr['primary'] = ((!empty($field_arr['Key']) && strtolower($field_arr['Key']) == 'pri') ? true : false);
        $model_field_arr['auto_increment'] = ((!empty($field_arr['Extra']) && strtolower($field_arr['Extra']) == 'auto_increment') ? true : false);
        $model_field_arr['index'] = ((!empty($field_arr['Key']) && strtolower($field_arr['Key']) == 'mul') ? true : false);
        $model_field_arr['default'] = $field_arr['Default'];
        $model_field_arr['comment'] = (!empty($field_arr['Comment']) ? $field_arr['Comment'] : '');

        return $model_field_arr;
    }

    private function _field_definition_changed($field1_arr, $field2_arr)
    {
        if (!($field1_arr = $this->_validate_field($field1_arr))
         || !($field2_arr = $this->_validate_field($field2_arr))) {
            return true;
        }

        return (bool)((int)$field1_arr['type'] !== (int)$field2_arr['type']
         || (bool)$field1_arr['primary'] !== (bool)$field2_arr['primary']
         || (bool)$field1_arr['auto_increment'] !== (bool)$field2_arr['auto_increment']
         || (bool)$field1_arr['index'] !== (bool)$field2_arr['index']
         || (bool)$field1_arr['unsigned'] !== (bool)$field2_arr['unsigned']
         || (bool)$field1_arr['nullable'] !== (bool)$field2_arr['nullable']
         || $field1_arr['default'] !== $field2_arr['default']
         || trim($field1_arr['comment']) !== trim($field2_arr['comment'])
         // for lengths with comma
         || str_replace(' ', '', trim($field1_arr['length']))
            !== str_replace(' ', '', trim($field2_arr['length']))
        );
    }

    private function _table_details_changed(array $details1_arr, array $details2_arr) : ?array
    {
        $default_table_details = $this->_default_table_details_arr();

        if (!($details1_arr = self::validate_array($details1_arr, $default_table_details))
            || !($details2_arr = self::validate_array($details2_arr, $default_table_details))) {
            return array_keys($default_table_details);
        }

        $keys_changed = [];
        if (strtolower(trim($details1_arr['engine'])) !== strtolower(trim($details2_arr['engine']))) {
            $keys_changed['engine'] = $details2_arr['engine'];
        }
        if (strtolower(trim($details1_arr['charset'])) !== strtolower(trim($details2_arr['charset']))) {
            $keys_changed['charset'] = $details2_arr['charset'];
        }
        if (strtolower(trim($details1_arr['collate'])) !== strtolower(trim($details2_arr['collate']))) {
            $keys_changed['collate'] = $details2_arr['collate'];
        }
        if (trim($details1_arr['comment']) !== trim($details2_arr['comment'])) {
            $keys_changed['comment'] = $details2_arr['comment'];
        }

        return !empty($keys_changed) ? $keys_changed : null;
    }

    /**
     * Returns an array containing mysql and keys string statement for a table field named $field_name and a structure provided in $field_arr
     *
     * @param string $field_name Name of mysql field
     * @param array $field_details Field details array
     *
     * @return bool|array Returns an array containing mysql statement for provided field and key string (if required) or false on failure
     */
    private function _get_mysql_field_definition($field_name, $field_details)
    {
        $field_details = self::validate_array($field_details, self::_default_field_arr());

        if ($field_name === self::T_DETAILS_KEY
         || $field_name === self::EXTRA_INDEXES_KEY
         || empty($field_details) || !is_array($field_details)
         || !($type_details = $this->valid_field_type($field_details['type']))
         || !($field_details = $this->_validate_field($field_details))) {
            return false;
        }

        $field_str = '';
        $keys_str = '';

        if (!empty($field_details['primary'])) {
            $keys_str = ' PRIMARY KEY (`'.$field_name.'`)';
        } elseif (!empty($field_details['index'])) {
            $keys_str = ' KEY `'.$field_name.'` (`'.$field_name.'`)';
        }

        $field_str .= '`'.$field_name.'` '.$type_details['title'];
        if ($field_details['length'] !== null
        && $field_details['length'] !== false
        && (!in_array($field_details['type'], [self::FTYPE_DATE, self::FTYPE_DATETIME])
                || $field_details['length'] !== 0
        )) {
            $field_str .= '('.$field_details['length'].')';
        }

        if (!empty($field_details['nullable'])) {
            $field_str .= ' NULL';
        } else {
            $field_str .= ' NOT NULL';
        }

        if (!empty($field_details['auto_increment'])) {
            $field_str .= ' AUTO_INCREMENT';
        }

        if (empty($field_details['primary'])
        && $field_details['type'] != self::FTYPE_DATE) {
            if (!empty($field_details['raw_default'])) {
                $default_value = $field_details['raw_default'];
            } elseif ($field_details['default'] === null) {
                $default_value = 'NULL';
            } elseif ($field_details['default'] === '') {
                $default_value = '\'\'';
            } else {
                $default_value = '\''.self::safe_escape($field_details['default']).'\'';
            }

            $field_str .= ' DEFAULT '.$default_value;
        }

        if (!empty($field_details['comment'])) {
            $field_str .= ' COMMENT \''.self::safe_escape($field_details['comment']).'\'';
        }

        return [
            'field_str' => $field_str,
            'keys_str'  => $keys_str,
        ];
    }

    private function _create_table_extra_index($index_name, $index_arr, $flow_params = false)
    {
        $this->reset_error();

        if (!($model_id = $this->instance_id())
         || empty($index_name)
         || empty($index_arr) || !is_array($index_arr)
         || !($flow_params = $this->fetch_default_flow_params($flow_params))
         || !($index_arr = $this->validate_table_extra_index($index_arr))
         || empty($index_arr['fields']) || !is_array($index_arr['fields'])
         || empty($flow_params['table_name'])
         || empty($this->_definition[$flow_params['table_name']])
         || !($full_table_name = $this->get_flow_table_name($flow_params))
         || !($database_name = $this->get_db_database($flow_params))) {
            PHS_Logger::error('Error creating extra index bad parameters sent to method for model ['.(!empty($model_id) ? $model_id : 'N/A').'].', PHS_Logger::TYPE_MAINTENANCE);

            $this->set_error(self::ERR_TABLE_GENERATE, self::_t('Error creating extra index for model %s.', (!empty($model_id) ? $model_id : 'N/A')));

            return false;
        }

        $db_connection = $this->get_db_connection($flow_params);

        $fields_str = '';
        foreach ($index_arr['fields'] as $field_name) {
            $fields_str .= ($fields_str !== '' ? ',' : '').'`'.$field_name.'`';
        }

        // $sql =
        //     'SELECT IF ('.
        //         ' EXISTS( '.
        //             'SELECT DISTINCT index_name FROM information_schema.statistics '.
        //             ' WHERE table_schema = \''.$database_name.'\' AND table_name = \''.$full_table_name.'\' '.
        //             ' AND index_name LIKE \''.$index_name.'\''.
        //         ' )'.
        //     ' ,\'SELECT \'\'index exists\'\' junk;\' '.
        //     ' ,\'CREATE '.(!empty( $index_arr['unique'] )?'UNIQUE':'').' INDEX `'.$index_name.'` ON `'.$full_table_name.'` ('.$fields_str.');\''.
        //     ') INTO @a;'."\n".
        //     'USE \''.$database_name.'\';'."\n".
        //     'PREPARE stmt1 FROM @a;'."\n".
        //     'EXECUTE stmt1;'."\n".
        //     'DEALLOCATE PREPARE stmt1;'."\n";
        //
        // if( !db_query( $sql, $db_connection ) )
        // {
        //     PHS_Logger::error( 'Error creating extra index ['.$index_name.'] for table ['.$full_table_name.'] for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE );
        //
        //     $this->set_error( self::ERR_TABLE_GENERATE, self::_t( 'Error creating extra index %s for table %s for model %s.', $index_name, $full_table_name, $this->instance_id() ) );
        //     return false;
        // }

        if (($qid = db_query('SELECT DISTINCT index_name '
                               .' FROM information_schema.statistics '
                               .' WHERE table_schema = \''.$database_name.'\' AND table_name = \''.$full_table_name.'\' '
                               .' AND index_name LIKE \''.$index_name.'\'', $db_connection))
         && @mysqli_num_rows($qid)) {
            PHS_Logger::error('Extra index ['.$index_name.'] for table ['.$full_table_name.'] for model ['.$model_id.'] already exists.', PHS_Logger::TYPE_MAINTENANCE);

            $this->set_error(self::ERR_TABLE_GENERATE, self::_t('Extra index %s for table %s for model %s already exists.', $index_name, $full_table_name, $this->instance_id()));

            return false;
        }

        if (!db_query('CREATE '.(!empty($index_arr['unique']) ? 'UNIQUE' : '').' INDEX `'.$index_name.'` ON `'.$full_table_name.'` ('.$fields_str.')', $db_connection)) {
            PHS_Logger::error('Error creating extra index ['.$index_name.'] for table ['.$full_table_name.'] for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE);

            $this->set_error(self::ERR_TABLE_GENERATE, self::_t('Error creating extra index %s for table %s for model %s.', $index_name, $full_table_name, $this->instance_id()));

            return false;
        }

        return true;
    }

    public static function get_count_default_params()
    {
        return [
            'count_field' => '*',
            'extra_sql'   => '',
            'join_sql'    => '',
            'group_by'    => '',

            'db_fields' => '',

            'fields' => [],

            'flags' => [],
        ];
    }

    public static function get_list_default_params()
    {
        return [
            'get_query_id' => false,
            // will get populated in get_list_common
            'arr_index_field' => '',

            'extra_sql'  => '',
            'join_sql'   => '',
            'having_sql' => '',
            'group_by'   => '',
            'order_by'   => '',

            'db_fields' => '',

            'offset'    => 0,
            'enregs_no' => 1000,

            'fields' => [],

            'flags' => [],
        ];
    }

    public static function safe_escape($str, $char = '\'')
    {
        return str_replace($char, '\\'.$char, str_replace('\\'.$char, $char, $str));
    }

    private static function _default_mysql_table_field_fields()
    {
        return [
            'Field'      => '',
            'Type'       => '',
            'Collation'  => '',
            'Null'       => '',
            'Key'        => '',
            'Default'    => '',
            'Extra'      => '',
            'Privileges' => '',
            'Comment'    => '',
        ];
    }

    private static function _default_field_arr()
    {
        // if 'default_value' is set in field definition that value will be used for 'default' key
        return [
            'type'        => self::FTYPE_UNDEFINED,
            'editable'    => true,
            'default'     => null,
            'raw_default' => null,
            'nullable'    => false,
            'comment'     => '',
            // Let framework know this field is holding sensitive data, so it will no get exported
            'sensitive_data' => false,
        ];
    }
    //
    //  endregion Querying database functionality
    //
}
