<?php

namespace phs\libraries;

use phs\PHS;
use phs\PHS_Db;
use phs\PHS_Maintenance;
use phs\traits\PHS_Trait_Has_relations;
use phs\system\core\models\PHS_Model_Plugins;
use phs\system\core\events\models\PHS_Event_Model_fields;
use phs\system\core\events\models\PHS_Event_Model_empty_data;
use phs\system\core\events\models\PHS_Event_Model_hard_delete;
use phs\system\core\events\models\PHS_Event_Model_table_names;
use phs\system\core\events\migrations\PHS_Event_Migration_models;
use phs\system\core\events\models\PHS_Event_Model_validate_data_fields;

abstract class PHS_Model_Core_base extends PHS_Has_db_settings
{
    use PHS_Trait_Has_relations;

    public const ERR_MODEL_FIELDS = 40000, ERR_TABLE_GENERATE = 40001, ERR_INSTALL = 40002, ERR_UPDATE = 40003, ERR_UNINSTALL = 40004,
        ERR_INSERT = 40005, ERR_EDIT = 40006, ERR_DELETE_BY_INDEX = 40007, ERR_ALTER = 40008, ERR_DELETE = 40009, ERR_UPDATE_TABLE = 40010,
        ERR_UNINSTALL_TABLE = 40011, ERR_READ_DB_STRUCTURE = 40012;

    public const HOOK_TABLE_FIELDS = 'phs_model_table_fields';

    public const DATE_EMPTY = '0000-00-00', DATETIME_EMPTY = '0000-00-00 00:00:00',
        DATE_DB = 'Y-m-d', DATETIME_DB = 'Y-m-d H:i:s';

    public const T_DETAILS_KEY = '{details}', EXTRA_INDEXES_KEY = '{indexes}';

    // Tables definition
    protected array $_definition = [];

    protected array $model_tables_arr = [];

    private ?array $_old_db_settings = null;

    protected static array $tables_arr = [];

    //
    // region Model class methods
    //
    /**
     * @return array of string Returns an array of strings containing tables that model will handle
     */
    abstract public function get_table_names() : array;

    /**
     * @return string Returns main table name used when calling insert with no table name
     */
    abstract public function get_main_table_name() : string;

    /**
     * @return string Returns version of model
     */
    abstract public function get_model_version() : string;

    /**
     * @param array|bool $params Parameters in the flow
     *
     * @return null|array Returns an array with table fields
     */
    abstract public function fields_definition($params = false);
    //
    // endregion END Model class methods
    //

    //
    // region Abstract model specific methods
    //
    /**
     * @return string Returns model driver
     */
    abstract public function get_model_driver() : string;

    /**
     * A dynamic table structure means that table fields can be altered by plugins, so system will call update method each time an install check
     * is done. In this case model version is not checked and update will be called anyway to alter fields depending on plugins asking fields changes.
     *
     * @return bool Returns true if table structure is dynamically created, false if static
     */
    abstract public function dynamic_table_structure();

    /**
     * Returns primary table key
     *
     * @param null|bool|array $params Parameters in the flow
     *
     * @return string What's primary key of the table
     */
    abstract public function get_primary_key(null | bool | array $params = []) : string;

    /**
     * Prepares primary key for a query (intval for int or trim for strings)
     *
     * @param int|string $id Primary key value
     * @param null|bool|array $params Parameters in the flow
     *
     * @return int|string Prepared primary key
     */
    abstract public function prepare_primary_key(int | string $id, null | bool | array $params = []) : int | string;

    /**
     * Returns an array of data types supported by model
     *
     * @return array Data types array
     */
    abstract public function get_field_types() : array;

    /**
     * Retrieve one record from database by its primary key (model specific functionality)
     *
     * @param string|int $id Id of record we want to get from database
     * @param null|bool|array $params Flow parameters
     *
     * @return null|array Record from database in an array structure or false on error
     */
    abstract protected function _get_details_for_model(int | string $id, null | bool | array $params = []) : ?array;

    /**
     * Retrieve one (or more) record(s) from database based on provided conditions (model specific functionality)
     *
     * @param array $constrain_arr Conditional db fields
     * @param null|bool|array $params Flow parameters
     *
     * @return null|array Returns one (or more) record(s) as array (with matching conditions)
     */
    abstract protected function _get_details_fields_for_model(array $constrain_arr, null | bool | array $params = []) : ?array;

    /**
     * Tells if table from provided flow exists in flow database
     *
     * @param null|bool|array $flow_params Flow parameters
     * @param bool $force Tells if we should skip cache (true) or, if we got table structure already, use cached tables
     *
     * @return bool True if table exists in flow database and false if it doesn't exist
     */
    abstract protected function _check_table_exists_for_model(null | bool | array $flow_params = [], bool $force = false) : bool;

    /**
     * Install a specific model table provided in flow parameters
     *
     * @param array $flow_params Flow parameters
     *
     * @return bool True on success or false on failure
     */
    abstract protected function _install_table_for_model(array $flow_params) : bool;

    /**
     * Update a specific model table provided in flow parameters
     *
     * @param array $flow_params Flow parameters
     *
     * @return bool True on success or false on failure
     */
    abstract protected function _update_table_for_model(array $flow_params) : bool;

    /**
     * Install a missing table provided in flow parameters when updating model
     *
     * @param array $flow_params Flow parameters
     *
     * @return bool True on success or false on failure
     */
    abstract protected function _install_missing_table_for_model(array $flow_params) : bool;

    /**
     * This method will hard-delete a table from database defined by this model.
     * If you don't want to drop a specific table when model gets uninstalled overwrite this method with an empty method which returns true.
     *
     * @param null|bool|array $flow_params Flow parameters
     *
     * @return bool Returns true if all tables were dropped or false on error
     */
    abstract protected function _uninstall_table_for_model(null | bool | array $flow_params) : bool;

    /**
     * Get table definition from database as an array which can be compared with model table structure
     *
     * @param null|bool|array $flow_params Flow parameters
     * @param bool $force Tells if we should skip cache (true) or, if we got table structure already, use cached tables
     *
     * @return null|array Returns table structure as array or false if we couldn't obtain table structure from database
     */
    abstract protected function _get_table_definition_for_model_from_database(null | bool | array $flow_params = [], bool $force = false) : ?array;

    /**
     * This method hard-deletes a record from database.
     *
     * @param array|PHS_Record_data $existing_data Array with full database fields or primary key
     * @param null|bool|array $params Parameters in the flow
     *
     * @return bool Returns true or false depending on hard delete success
     */
    abstract protected function _hard_delete_for_model(array | PHS_Record_data $existing_data, null | bool | array $params = []) : bool;

    // Default table structures...

    /**
     * Details related to table
     * @return array
     */
    abstract protected function _default_table_details_arr() : array;

    /**
     * Details related table extra indexes
     * @return array
     */
    abstract protected function _default_table_extra_index_arr() : array;

    /**
     * Validate a field definition
     * @param array $field_arr
     *
     * @return null|array
     */
    abstract protected function _validate_field(array $field_arr) : ?array;

    /**
     * Validate a value for a field according to field definition
     * @param mixed $value
     * @param string $field_name
     * @param array $field_details
     *
     * @return mixed
     */
    abstract protected function _validate_field_value(mixed $value, string $field_name, array $field_details) : mixed;

    /**
     * @return string Should return INSTANCE_TYPE_* constant
     */
    final public function instance_type() : string
    {
        return self::INSTANCE_TYPE_MODEL;
    }

    /**
     * Return an array with array keys which are allowed to be set in a PHS_Record_data object
     *
     * @param null|bool|array $flow_arr
     *
     * @return array
     */
    public function allow_record_data_keys(null | bool | array $flow_arr = []) : array
    {
        return [];
    }

    /**
     * @param null|bool|array $params Parameters in the flow
     *
     * @return false|string Returns false if model uses default database connection or connection name as string
     */
    public function get_db_connection(null | bool | array $params = []) : bool | string
    {
        $db_driver = false;
        if (!empty($params) && is_array($params)) {
            if (!empty($params['db_connection'])) {
                return $params['db_connection'];
            }

            if (!empty($params['db_driver'])) {
                $db_driver = $params['db_driver'];
            }
        }

        if (empty($db_driver)) {
            $db_driver = $this->get_model_driver();
        }

        return PHS_Db::default_db_connection($db_driver);
    }

    /**
     * Returns prefix of tables for provided database connection
     *
     * @param bool|array $params Flow parameters
     *
     * @return string Connection tables prefix
     */
    public function get_db_prefix(null | bool | array $params = []) : string
    {
        if (!($params = $this->fetch_default_flow_params($params))) {
            return '';
        }

        $db_connection = $this->get_db_connection($params);

        return db_prefix($db_connection);
    }

    /**
     * Returns database name for provided database connection
     *
     * @param null|bool|array $params Flow parameters
     *
     * @return string Connection tables prefix
     */
    public function get_db_database(null | bool | array $params = []) : string
    {
        if (!($params = $this->fetch_default_flow_params($params))) {
            return '';
        }

        $db_connection = $this->get_db_connection($params);

        return db_database($db_connection);
    }

    /**
     * Returns full table name used in current flow
     *
     * @param null|bool|array $params Flow parameters
     *
     * @return string Full table name used in current flow
     */
    public function get_flow_table_name(null | bool | array $params = []) : string
    {
        if (!($params = $this->fetch_default_flow_params($params))) {
            return '';
        }

        if (!($db_prefix = $this->get_db_prefix($params))) {
            $db_prefix = '';
        }

        return $db_prefix.$params['table_name'];
    }

    /**
     * Returns table name used in flow without prefix
     *
     * @param null|bool|array $params Parameters in the flow
     *
     * @return string Returns table set in parameters flow or main table if no table is specified in flow
     *                (table name can be passed to $params array of each method in 'table_name' index)
     */
    public function get_table_name(null | bool | array $params = []) : string
    {
        return $params['table_name'] ?? $this->get_main_table_name();
    }

    /**
     * Test DB connection for this model
     * @param null|bool|array $flow_params
     *
     * @return bool
     */
    public function test_db_connection(null | bool | array $flow_params = []) : bool
    {
        if (!($flow_params = $this->fetch_default_flow_params($flow_params))) {
            return false;
        }

        db_supress_errors($flow_params['db_connection']);
        if (!db_test_connection($flow_params['db_connection'])) {
            db_restore_errors_state($flow_params['db_connection']);

            return false;
        }

        db_restore_errors_state($flow_params['db_connection']);

        return true;
    }

    /**
     * Get table definition array
     *
     * @param null|bool|array $flow_params Flow parameters
     * @param bool $force
     *
     * @return null|array
     */
    public function get_table_details(null | bool | array $flow_params = [], bool $force = false) : ?array
    {
        $this->reset_error();

        if (!($flow_params = $this->fetch_default_flow_params($flow_params))
         || !($flow_table_name = $this->get_flow_table_name($flow_params))
         || !($my_driver = $this->get_model_driver())) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Failed validating flow parameters.'));

            return null;
        }

        $this->_check_table_exists_for_model($flow_params, $force);

        if (!($table_details = self::get_cached_db_table_structure($flow_table_name, $my_driver))) {
            return null;
        }

        return $table_details;
    }

    /**
     * Tells if table from provided flow exists in flow database
     *
     * @param null|bool|array $flow_params Flow parameters
     * @param bool $force Tells if we should skip cache (true) or, if we got table structure already, use cached tables
     *
     * @return bool True if table exists in flow database and false if it doesn't exist
     */
    public function check_table_exists(null | bool | array $flow_params = [], bool $force = false) : bool
    {
        return (bool)$this->get_table_details($flow_params, $force);
    }

    /**
     * Get table definition from database as an array which can be compared with model table structure
     *
     * @param null|bool|array $flow_params Flow parameters
     * @param bool $force Tells if we should skip cache (true) or, if we got table structure already, use cached tables
     *
     * @return null|array Returns table structure as array or false if we couldn't obtain table structure from database
     */
    public function get_table_columns_as_definition(null | bool | array $flow_params = [], bool $force = false) : ?array
    {
        $this->reset_error();

        if (!($flow_params = $this->fetch_default_flow_params($flow_params))
            || !($flow_table_name = $this->get_flow_table_name($flow_params))
            || !($my_driver = $this->get_model_driver())) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Failed validating flow parameters.'));

            return null;
        }

        if (!$this->check_table_exists($flow_params, $force)) {
            $this->set_error_if_not_set(self::ERR_FUNCTIONALITY, self::_t('Table %s doesn\'t exist.', $flow_table_name));

            return null;
        }

        if (($table_structure = self::get_cached_db_table_structure($flow_table_name, $my_driver))
            && self::cached_db_table_structure_has_fields($table_structure)) {
            return $table_structure;
        }

        $this->_get_table_definition_for_model_from_database($flow_params, $force);

        return self::get_cached_db_table_structure($flow_table_name, $my_driver);
    }

    /**
     * Check if a specified column exists in table definition array and if it exists return structure definition as array
     *
     * @param string $field Field to be checked/retrieved
     * @param null|bool|array $flow_params Flow parameters
     * @param bool $force Tells if we should skip cache (true) or, if we got table structure already, use cached tables
     *
     * @return null|array Returns column structure as array or false if we couldn't obtain column structure from flow table
     */
    public function check_column_exists(string $field, null | bool | array $flow_params = [], bool $force = false) : ?array
    {
        $this->reset_error();

        if (!($flow_params = $this->fetch_default_flow_params($flow_params))
            || !($flow_table_name = $this->get_flow_table_name($flow_params))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Failed validating flow parameters.'));

            return null;
        }

        if (!($table_definition = $this->get_table_columns_as_definition($flow_params, $force))) {
            $this->set_error_if_not_set(self::ERR_FUNCTIONALITY, self::_t('Couldn\'t get definition for table %s.', $flow_table_name));

            return null;
        }

        return $table_definition[$field] ?? null;
    }

    /**
     * Check if a specified column exists in table definition array and if it exists return structure definition as array
     *
     * @param string $field Field to be checked/retrieved
     * @param null|bool|array $flow_params Flow parameters
     * @param bool $force Tells if we should skip cache (true) or, if we got table structure already, use cached tables
     *
     * @return null|array Returns column structure as array or false if we couldn't obtain column structure from flow table
     */
    public function check_column_index_exists(string $field, null | bool | array $flow_params = [], bool $force = false) : ?array
    {
        $this->reset_error();

        if (!($flow_params = $this->fetch_default_flow_params($flow_params))
            || !($flow_table_name = $this->get_flow_table_name($flow_params))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Failed validating flow parameters.'));

            return null;
        }

        if (!($table_definition = $this->get_table_columns_as_definition($flow_params, $force))) {
            $this->set_error_if_not_set(self::ERR_FUNCTIONALITY,
                self::_t('Couldn\'t get definition for table %s.', $flow_table_name));

            return null;
        }

        if (empty($table_definition[$field])
            || empty($table_definition[$field]['index'])) {
            return null;
        }

        return $table_definition[$field];
    }

    /**
     * Check if provided index is defined in table structure
     *
     * @param string $index_name Index to be found
     * @param null|bool|array $flow_params Flow parameters
     * @param bool $force
     *
     * @return null|array
     */
    public function check_extra_index_exists(string $index_name, null | bool | array $flow_params = [], bool $force = false) : ?array
    {
        $this->reset_error();

        if (!($flow_params = $this->fetch_default_flow_params($flow_params))
         || !($flow_table_name = $this->get_flow_table_name($flow_params))) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Failed validating flow parameters.'));

            return null;
        }

        if (!($table_definition = $this->get_table_columns_as_definition($flow_params, $force))
         || !is_array($table_definition)) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_FUNCTIONALITY,
                    self::_t('Couldn\'t get definition for table %s.', $flow_table_name));
            }

            return null;
        }

        if (empty($table_definition[self::EXTRA_INDEXES_KEY])
            || !array_key_exists($index_name, $table_definition[self::EXTRA_INDEXES_KEY])) {
            return null;
        }

        return $table_definition[self::EXTRA_INDEXES_KEY][$index_name];
    }

    /**
     * Checks if model tables structure exists in database. Install table structures if model is not installed, update model if required
     *
     * @return bool True if installed, update or structure doesn't require any actions or false if failed installing or updating structures
     */
    public function check_installation() : bool
    {
        $this->reset_error();

        if (!$this->_load_plugins_instance()) {
            return false;
        }

        if (!($db_details = $this->_plugins_instance->get_plugins_db_main_details($this->instance_id()))) {
            $this->reset_error();

            return $this->install();
        }

        if ($this->dynamic_table_structure()
            || version_compare($db_details['version'], $this->get_model_version(), '!=')) {
            return $this->update($db_details['version'], $this->get_model_version());
        }

        return true;
    }

    /**
     * Checks if provided type is a valid data type and returns an array with details about data type
     *
     * @param int $type Field type
     *
     * @return null|array Data type details array
     */
    public function valid_field_type(int $type) : ?array
    {
        if (empty($type)
            || !($fields_arr = $this->get_field_types())
            || empty($fields_arr[$type]) || !is_array($fields_arr[$type])) {
            return null;
        }

        return $fields_arr[$type];
    }

    /**
     * This method hard-deletes a record from database.
     * If additional work is required before hard-deleting record, or you want to cancel the delete,
     * PHS_Event_Model_hard_delete::trigger() is triggered before deleting.
     *
     * @param array|int|string|PHS_Record_data $existing_data Array with full database fields or primary key
     * @param null|bool|array $params Parameters in the flow
     *
     * @return bool Returns true or false depending on hard delete success
     */
    final public function hard_delete(int | string | array | PHS_Record_data $existing_data, null | bool | array $params = []) : bool
    {
        self::st_reset_error();
        $this->reset_error();

        if (!($params = $this->fetch_default_flow_params($params))) {
            $this->set_error(self::ERR_DELETE,
                self::_t('Invalid flow parameters.'));

            return false;
        }

        if (!($existing_arr = $this->data_to_array($existing_data, $params))) {
            return true;
        }

        if ( ($event_obj = PHS_Event_Model_hard_delete::trigger_for_model($this::class, [
            'flow_params' => $params,
            'record_data' => $existing_arr,
            'model_obj'   => $this,
        ]))
            && $event_obj->get_output('stop_hard_delete')) {
            $this->copy_or_set_error($event_obj,
                self::ERR_DELETE, self::_t('Delete cancelled by trigger.'));

            return false;
        }

        return $this->_hard_delete_for_model($existing_arr, $params);
    }

    /**
     * Retrieve table structure as defined in database
     *
     * @param null|bool|array $params Flow parameters
     *
     * @return null|array Return flow table structure definition as array
     */
    public function get_definition(null | bool | array $params = []) : ?array
    {
        if (!($params = $this->fetch_default_flow_params($params))) {
            $this->set_error(self::ERR_MODEL_FIELDS, self::_t('Failed validating flow parameters.'));

            return null;
        }

        if (empty($this->_definition[$params['table_name']])) {
            return null;
        }

        return $this->_definition[$params['table_name']];
    }

    /**
     * Populate missing flow parameters in provided flow
     *
     * @param null|bool|array $params Flow parameters
     *
     * @return null|array Complete flow parameters or false on failure
     */
    public function fetch_default_flow_params(null | bool | array $params = []) : ?array
    {
        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        if (empty($params['table_name'])) {
            $params['table_name'] = $this->get_table_name($params);
        }

        if (empty($params['table_index'])) {
            $params['table_index'] = $this->get_primary_key($params);
        }

        if (!isset($params['db_connection'])) {
            $params['db_connection'] = $this->get_db_connection($params);
        }

        $params['db_driver'] = $this->get_model_driver();

        if (empty($params['table_index']) || empty($params['table_name']) || !isset($params['db_connection'])
            || !($all_tables = $this->get_all_table_names())
            || !in_array($params['table_name'], $all_tables, true)) {
            return null;
        }

        return $params;
    }

    /**
     * Retrieve a data array that should be a structure copy of a record retrieved from table definition with default/[empty|void] values
     *
     * @param null|bool|array $flow_params Flow parameters
     *
     * @return array Empty data array or false on failure
     */
    public function get_empty_data(null | bool | array $flow_params = []) : array
    {
        $this->reset_error();

        if (!($flow_params = $this->fetch_default_flow_params($flow_params))
            || !($table_fields = $this->get_definition($flow_params))) {
            $this->set_error(self::ERR_MODEL_FIELDS, self::_t('Invalid table definition.'));

            return [];
        }

        $data_arr = [];
        foreach ($table_fields as $field_name => $field_details) {
            if ($field_name === self::T_DETAILS_KEY
             || $field_name === self::EXTRA_INDEXES_KEY) {
                continue;
            }

            if (array_key_exists('default', $field_details)) {
                $data_arr[$field_name] = $field_details['default'];
            } else {
                $data_arr[$field_name] = $this->_validate_field_value(0, $field_name, $field_details);
            }
        }

        /** @var PHS_Event_Model_empty_data $event_obj */
        if (($event_obj = PHS_Event_Model_empty_data::trigger_for_model($this::class, [
            'model_instance_id'  => $this->instance_id(),
            'plugin_instance_id' => $this->get_plugin_instance()?->instance_id(),
            'flow_params'        => $flow_params,
            'data_arr'           => $data_arr,
            'model_obj'          => $this,
        ]))
            && ($new_data_arr = $event_obj->get_output('data_arr'))) {
            $data_arr = self::merge_array_assoc($data_arr, $new_data_arr);
        }

        return $data_arr;
    }

    public function validate_field_value(mixed $value, string $field_name, array $flow_arr = []) : mixed
    {
        if ( !($field_details = $this->table_field_details($field_name, $flow_arr)) ) {
            $this->set_error_if_not_set(self::ERR_PARAMETERS, self::_t('Cannot obtain table field details.'));

            return null;
        }

        return $this->_validate_field_value($value, $field_name, $field_details);
    }

    /**
     * Retrieve a column definition from table definition structure
     *
     * @param string $field Column name
     * @param bool|array $params Flow parameters
     *
     * @return null|bool|array Return column definition as array, null if column is not in table structure definition or false on failure
     */
    public function table_field_details(string $field, null | bool | array $params = []) : null | bool | array
    {
        $this->reset_error();

        $table = null;
        if (str_contains($field, '.')) {
            [$table, $field] = explode('.', $field, 2);
        }

        if (empty($params)) {
            $params = [];
        }

        $params['table_name'] = $table;

        if (!($params = $this->fetch_default_flow_params($params))) {
            $this->set_error(self::ERR_MODEL_FIELDS, self::_t('Failed validating flow parameters.'));

            return false;
        }

        if (!($table_fields = $this->get_definition($params))) {
            $this->set_error(self::ERR_MODEL_FIELDS, self::_t('Invalid table definition.'));

            return false;
        }

        if (empty($table_fields[$field]) || !is_array($table_fields[$field])) {
            return null;
        }

        return $table_fields[$field];
    }

    /**
     * @param array $constrain_arr Conditional db fields
     * @param array|bool $flow_params Parameters in the flow
     *
     * @return null|array Returns single record as array (first matching conditions), array of records matching conditions or acts as generator
     */
    public function get_details_fields(array $constrain_arr, null | bool | array $flow_params = []) : ?array
    {
        if (!($flow_params = $this->fetch_default_flow_params($flow_params))) {
            return null;
        }

        return $this->_get_details_fields_for_model($constrain_arr, $flow_params);
    }

    /**
     * Retrieve one record from database by its primary key
     *
     * @param string|int $id Id of record we want to get from database
     * @param null|bool|array $flow_params Flow parameters
     *
     * @return null|array Record from database in an array structure or false on error
     */
    public function get_details(int | string $id, null | bool | array $flow_params = []) : ?array
    {
        if (!($flow_params = $this->fetch_default_flow_params($flow_params))
            || !($id = $this->prepare_primary_key($id, $flow_params))) {
            return null;
        }

        return $this->_get_details_for_model($id, $flow_params);
    }

    /**
     * Retrieve one record from database by its primary key
     *
     * @param string|int $id Id of record we want to get from database
     * @param null|bool|array $flow_params Flow parameters
     *
     * @return null|PHS_Record_data Record from database in a PHS_Record_data object or null on error
     */
    public function get_details_to_record_data(int | string $id, null | bool | array $flow_params = []) : ?PHS_Record_data
    {
        if (!($data_arr = $this->get_details($id, $flow_params))) {
            return null;
        }

        return $this->data_to_record_data($data_arr, $flow_params);
    }

    /**
     * Provided a primary key or a data array structure, return data array structure from database.
     * If provided $item_data is an array, make sure it has as key primary key defined in table structure.
     * If $item_data is a primary key, query database and return data array structure from database.
     *
     * @param null|int|array|string|PHS_Record_data $item_data Data array or primary key in database
     * @param bool|array $flow_params Flow parameters
     *
     * @return null|array|PHS_Record_data Data array structure or false on failure
     */
    public function data_to_array(null | int | array | string | PHS_Record_data $item_data, null | bool | array $flow_params = []) : null | array | PHS_Record_data
    {
        if (empty($item_data)
            || !($flow_params = $this->fetch_default_flow_params($flow_params))) {
            return null;
        }

        if ($item_data instanceof PHS_Record_data) {
            // Different table in record data than in flow parameters
            if ($item_data->get_simple_table_name_from_flow() !== ($flow_params['table_name'] ?? '')) {
                return null;
            }

            return $item_data;
        }

        $id = 0;
        $item_arr = null;
        if (is_array($item_data)) {
            if (!empty($item_data[$flow_params['table_index']])) {
                $id = (int)$item_data[$flow_params['table_index']];
            }
            $item_arr = $item_data;
        } else {
            $id = $this->prepare_primary_key($item_data);
        }

        if (empty($id) && (!is_array($item_arr) || empty($item_arr[$flow_params['table_index']]))) {
            return null;
        }

        if (empty($item_arr)) {
            $item_arr = $this->get_details($id, $flow_params);
        }

        if (empty($item_arr) || !is_array($item_arr)) {
            return null;
        }

        return $item_arr;
    }

    public function data_to_record_data(null | int | array | string | PHS_Record_data $item_data, null | bool | array $flow_params = []) : ?PHS_Record_data
    {
        if (empty($item_data)
            || !($flow_params = $this->fetch_default_flow_params($flow_params))) {
            return null;
        }

        if (!($item_arr = $this->data_to_array($item_data, $flow_params))) {
            return null;
        }

        if ($item_arr instanceof PHS_Record_data) {
            return $item_arr;
        }

        return $this->record_data_from_array($item_arr, $flow_params);
    }

    public function record_data_from_array(array $data_arr, null | bool | array $flow_params = []) : ?PHS_Record_data
    {
        if (empty($data_arr)
            || !($flow_params = $this->fetch_default_flow_params($flow_params))) {
            return null;
        }

        return new PHS_Record_data(
            data: $data_arr,
            model: $this,
            flow_arr: $flow_params,
        );
    }

    public function data_key_exists(string $key, array | PHS_Record_data $item_data) : bool
    {
        return (is_array($item_data) && array_key_exists($key, $item_data))
            || ($item_data instanceof PHS_Record_data && $item_data->data_key_exists($key));
    }

    /**
     * Install model
     *
     * @return bool True on success or false on failure
     */
    final public function install() : bool
    {
        $this->reset_error();

        if (!($plugins_model_id = self::generate_instance_id(self::INSTANCE_TYPE_MODEL, 'plugins'))) {
            $this->set_error(self::ERR_INSTALL, self::_t('Couldn\'t obtain plugins model id.'));

            return false;
        }

        if (!($this_instance_id = $this->instance_id())) {
            $this->set_error(self::ERR_INSTALL, self::_t('Couldn\'t obtain current model id.'));

            return false;
        }

        PHS_Logger::notice('Installing model ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE);

        PHS_Maintenance::lock_db_structure_read();

        /** @var PHS_Model_Plugins $plugins_model */
        if ($this_instance_id === $plugins_model_id) {
            $plugins_model = $this;

            $this->install_tables();
        } else {
            if (!$this->_load_plugins_instance()) {
                PHS_Logger::error('!!! Error instantiating plugins model. ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE);

                $this->set_error_if_not_set(self::ERR_INSTALL, self::_t('Error instantiating plugins model.'));

                PHS_Maintenance::unlock_db_structure_read();

                return false;
            }

            if (!$this->_plugins_instance->check_install_plugins_db()) {
                $this->copy_or_set_error($this->_plugins_instance,
                    self::ERR_INSTALL, self::_t('Error installing plugins model.'));

                PHS_Logger::error('!!! Error ['.$this->get_error_message().'] ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE);

                PHS_Maintenance::unlock_db_structure_read();

                return false;
            }
        }

        // This will only create non-existing tables...
        if ($this_instance_id !== $plugins_model_id
            && !$this->install_tables()) {
            PHS_Maintenance::unlock_db_structure_read();

            return false;
        }

        $plugin_name = ($plugin_obj = $this->get_plugin_instance())
                       && ($plugin_info = $plugin_obj->get_plugin_info())
                       && !empty($plugin_info['name'])
            ? $plugin_info['name']
            : $this->instance_plugin_name();

        if (!($db_details = $this->_plugins_instance->install_record($this_instance_id,
            $this->instance_plugin_name(), $plugin_name, $this->instance_type(), $this->instance_is_core(),
            $this->get_default_settings(), $this->get_model_version()))
            || empty($db_details['new_data'])) {
            $this->copy_or_set_error($this->_plugins_instance,
                self::ERR_INSTALL, self::_t('Error saving plugin details to database.'));

            PHS_Logger::error('!!! Error ['.$this->get_error_message().'] ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE);

            PHS_Maintenance::unlock_db_structure_read();

            return false;
        }

        $plugin_arr = $db_details['new_data'];
        $old_plugin_arr = $db_details['old_data'] ?? null;

        // Performs any necessary actions when updating model from old version to new version
        if (!empty($old_plugin_arr)
            && version_compare($old_plugin_arr['version'], $plugin_arr['version'], '!=')) {
            PHS_Logger::notice('Calling update method from version ['.$old_plugin_arr['version'].'] to version ['.$plugin_arr['version'].'] ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE);

            // Installed version is different from what we already had in database... update...
            if (!$this->update($old_plugin_arr['version'], $plugin_arr['version'])) {
                PHS_Logger::error('!!! Update failed ['.$this->get_error_message().']', PHS_Logger::TYPE_MAINTENANCE);

                PHS_Maintenance::unlock_db_structure_read();

                return false;
            }

            PHS_Logger::notice('Update with success ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE);
        }

        PHS_Logger::notice('DONE installing model ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE);

        PHS_Maintenance::unlock_db_structure_read();

        return true;
    }

    /**
     * Install model tables
     *
     * @return bool True on success or false on failure
     */
    final public function install_tables() : bool
    {
        $this->reset_error();

        if (!($model_id = $this->instance_id())) {
            return false;
        }

        if (empty($this->_definition)) {
            return true;
        }

        PHS_Logger::notice('Installing tables for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE);

        $is_dry_update = PHS_Db::dry_update();

        $model_version = $this->get_model_version();

        /** @var null|PHS_Event_Migration_models $event_obj */
        if ( !($event_obj = PHS_Event_Migration_models::trigger_before_missing(
            model_obj: $this, old_version: '0.0.0', new_version: $model_version, is_dry_update: $is_dry_update
        ))
             || $event_obj->result_has_error()
             || self::st_has_error()) {
            $this->set_error(self::ERR_UPDATE, self::_t('Error in migrations before installing tables for model %s.', $model_id));
            PHS_Logger::error('Error in migrations before installing tables for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE);

            PHS_Maintenance::output('['.$this->instance_plugin_name().']['.$this->instance_name().'] !!! Error in migrations before installing tables: '
                                    .self::st_get_simple_error_message($event_obj?->get_result_errors_as_string() ?: 'Unknown error.'));

            return false;
        }

        foreach ($this->_definition as $table_name => $table_definition) {
            if (!($flow_params = $this->fetch_default_flow_params(['table_name' => $table_name]))
                || !($full_table_name = $this->get_flow_table_name($flow_params))) {
                PHS_Logger::error('Couldn\'t get flow parameters for table ['.$table_name.'], model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE);
                continue;
            }

            /** @var null|PHS_Event_Migration_models $event_obj */
            if ( !($event_obj = PHS_Event_Migration_models::trigger_before_missing(
                model_obj: $this, table_name: $table_name, old_version: '0.0.0', new_version: $model_version, is_dry_update: $is_dry_update
            ))
                 || $event_obj->result_has_error()
                 || self::st_has_error()) {
                $this->set_error(self::ERR_UPDATE, self::_t('Error in migrations before installing table %s, model %s.', $full_table_name, $model_id));
                PHS_Logger::error('Error in migrations before installing table ['.$full_table_name.'], model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE);

                PHS_Maintenance::output('['.$this->instance_plugin_name().']['.$this->instance_name().'] !!! Error in migrations before installing table ['.$table_name.']: '
                                        .self::st_get_simple_error_message($event_obj?->get_result_errors_as_string() ?: 'Unknown error.'));

                return false;
            }

            if (!$this->install_table($flow_params)) {
                if (!$this->has_error()) {
                    $this->set_error(self::ERR_INSTALL, self::_t('Couldn\'t install table %s, model %s.', $full_table_name, $model_id));
                }

                PHS_Logger::error('FAILED Installing table ['.$full_table_name.'], model ['.$model_id.']',
                    PHS_Logger::TYPE_MAINTENANCE);

                return false;
            }

            /** @var null|PHS_Event_Migration_models $event_obj */
            if ( !($event_obj = PHS_Event_Migration_models::trigger_after_missing(
                model_obj: $this, table_name: $table_name, old_version: '0.0.0', new_version: $model_version, is_dry_update: $is_dry_update
            ))
                 || $event_obj->result_has_error()
                 || self::st_has_error()) {
                $this->set_error(self::ERR_INSTALL, self::_t('Error in migrations after installing table %s, model %s.', $full_table_name, $model_id));
                PHS_Logger::error('Error in migrations after installing table ['.$full_table_name.'], model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE);

                PHS_Maintenance::output('['.$this->instance_plugin_name().']['.$this->instance_name().'] !!! Error in migrations after installing table ['.$table_name.']: '
                                        .self::st_get_simple_error_message($event_obj?->get_result_errors_as_string() ?: 'Unknown error.'));

                return false;
            }
        }

        /** @var null|PHS_Event_Migration_models $event_obj */
        if ( !($event_obj = PHS_Event_Migration_models::trigger_after_missing(
            model_obj: $this, old_version: '0.0.0', new_version: $model_version, is_dry_update: $is_dry_update
        ))
             || $event_obj->result_has_error()
             || self::st_has_error()) {
            $this->set_error(self::ERR_UPDATE, self::_t('Error in migrations after installing tables for model %s.', $model_id));
            PHS_Logger::error('Error in migrations after installing tables for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE);

            PHS_Maintenance::output('['.$this->instance_plugin_name().']['.$this->instance_name().'] !!! Error in migrations after installing tables: '
                                    .self::st_get_simple_error_message($event_obj?->get_result_errors_as_string() ?: 'Unknown error.'));

            return false;
        }

        // Reset any errors related to generating tables...
        $this->reset_error();

        PHS_Logger::notice('DONE Installing tables for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE);

        return true;
    }

    /**
     * Update model tables
     *
     * @param string $old_version
     * @param string $new_version
     * @param array $params_arr Functionality parameters
     *
     * @return bool True on success or false on failure
     */
    final public function update_tables(string $old_version, string $new_version, array $params_arr) : bool
    {
        $this->reset_error();

        if (!($model_id = $this->instance_id())) {
            return false;
        }

        if (empty($this->_definition)) {
            return true;
        }

        if (empty($params_arr['created_tables']) || !is_array($params_arr['created_tables'])) {
            $params_arr['created_tables'] = [];
        }

        PHS_Logger::notice('Updating tables for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE);

        PHS_Maintenance::lock_db_structure_read();

        $is_dry_update = PHS_Db::dry_update();

        /** @var null|PHS_Event_Migration_models $event_obj */
        if ( !($event_obj = PHS_Event_Migration_models::trigger_before_update(
            model_obj: $this, old_version: $old_version, new_version: $new_version, is_dry_update: $is_dry_update
        ))
             || $event_obj->result_has_error()
             || self::st_has_error()) {
            $this->set_error(self::ERR_UPDATE, self::_t('Error in migrations before updating tables for model %s.', $model_id));
            PHS_Logger::error('Error in migrations before updating tables for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE);

            PHS_Maintenance::output('['.$this->instance_plugin_name().']['.$this->instance_name().'] !!! Error in migrations before updating tables: '
                                    .self::st_get_simple_error_message($event_obj?->get_result_errors_as_string() ?: 'Unknown error.'));

            PHS_Maintenance::unlock_db_structure_read();

            return false;
        }

        foreach ($this->_definition as $table_name => $table_definition) {
            if (!empty($params_arr['created_tables'])
                && in_array($table_name, $params_arr['created_tables'], true)) {
                continue;
            }

            if (!($flow_params = $this->fetch_default_flow_params(['table_name' => $table_name]))
                || !($full_table_name = $this->get_flow_table_name($flow_params))) {
                PHS_Logger::error('Couldn\'t get flow parameters for model table ['.$table_name.'], model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE);
                continue;
            }

            /** @var null|PHS_Event_Migration_models $event_obj */
            if ( !($event_obj = PHS_Event_Migration_models::trigger_before_update(
                model_obj: $this, table_name: $table_name, old_version: $old_version, new_version: $new_version, is_dry_update: $is_dry_update
            ))
                 || $event_obj->result_has_error()
                 || self::st_has_error()) {
                $this->set_error(self::ERR_UPDATE, self::_t('Error in migrations before updating table %s for model %s.', $full_table_name, $model_id));
                PHS_Logger::error('Error in migrations before updating table ['.$full_table_name.'], model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE);

                PHS_Maintenance::output('['.$this->instance_plugin_name().']['.$this->instance_name().'] !!! Error in migrations before updating table ['.$full_table_name.']: '
                                        .self::st_get_simple_error_message($event_obj?->get_result_errors_as_string() ?: 'Unknown error.'));

                PHS_Maintenance::unlock_db_structure_read();

                return false;
            }

            if (!$this->update_table($flow_params)) {
                if (!$this->has_error()) {
                    $this->set_error(self::ERR_UPDATE, self::_t('Couldn\'t update table %s, model %s.', $full_table_name, $model_id));
                }

                PHS_Logger::error('FAILED Updating table ['.$full_table_name.'] for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE);

                PHS_Maintenance::unlock_db_structure_read();

                return false;
            }

            /** @var null|PHS_Event_Migration_models $event_obj */
            if ( !($event_obj = PHS_Event_Migration_models::trigger_after_update(
                model_obj: $this, table_name: $table_name, old_version: $old_version, new_version: $new_version, is_dry_update: $is_dry_update
            ))
                 || $event_obj->result_has_error()
                 || self::st_has_error()) {
                $this->set_error(self::ERR_UPDATE, self::_t('Error in migrations after updating table %s for model %s.', $full_table_name, $model_id));
                PHS_Logger::error('Error in migrations after updating table ['.$full_table_name.'], model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE);

                PHS_Maintenance::output('['.$this->instance_plugin_name().']['.$this->instance_name().'] !!! Error in migrations after updating table ['.$full_table_name.']: '
                                        .self::st_get_simple_error_message($event_obj?->get_result_errors_as_string() ?: 'Unknown error.'));

                PHS_Maintenance::unlock_db_structure_read();

                return false;
            }
        }

        /** @var null|PHS_Event_Migration_models $event_obj */
        if ( !($event_obj = PHS_Event_Migration_models::trigger_after_update(
            model_obj: $this, old_version: $old_version, new_version: $new_version, is_dry_update: $is_dry_update
        ))
             || $event_obj->result_has_error()
             || self::st_has_error()) {
            $this->set_error(self::ERR_UPDATE, self::_t('Error in migrations after updating tables for model %s.', $model_id));
            PHS_Logger::error('Error in migrations after updating tables for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE);

            PHS_Maintenance::output('['.$this->instance_plugin_name().']['.$this->instance_name().'] !!! Error in migrations after updating tables: '
                                    .self::st_get_simple_error_message($event_obj?->get_result_errors_as_string() ?: 'Unknown error.'));

            PHS_Maintenance::unlock_db_structure_read();

            return false;
        }

        // Reset any errors related to generating tables...
        $this->reset_error();

        PHS_Logger::notice('DONE Updating tables for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE);

        PHS_Maintenance::unlock_db_structure_read();

        return true;
    }

    /**
     * Install missing tables when doing updates on this model
     *
     * @param string $old_version
     * @param string $new_version
     * @return null|array Array of tables created on success or false on failure
     */
    final public function install_missing_tables(string $old_version, string $new_version) : ?array
    {
        $this->reset_error();

        if (!($model_id = $this->instance_id())) {
            return null;
        }

        if (empty($this->_definition)) {
            return [];
        }

        PHS_Logger::notice('Installing missing tables for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE);

        PHS_Maintenance::lock_db_structure_read();

        $is_dry_update = PHS_Db::dry_update();

        /** @var null|PHS_Event_Migration_models $event_obj */
        if ( !($event_obj = PHS_Event_Migration_models::trigger_before_missing(
            model_obj: $this, old_version: $old_version, new_version: $new_version, is_dry_update: $is_dry_update
        ))
             || $event_obj->result_has_error()
             || self::st_has_error()) {
            $this->set_error(self::ERR_UPDATE, self::_t('Error in migrations before installing missing tables for model %s.', $model_id));
            PHS_Logger::error('Error in migrations before installing missing tables for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE);

            PHS_Maintenance::output('['.$this->instance_plugin_name().']['.$this->instance_name().'] !!! Error in migrations before installing missing tables: '
                                    .self::st_get_simple_error_message($event_obj?->get_result_errors_as_string() ?: 'Unknown error.'));

            PHS_Maintenance::unlock_db_structure_read();

            return null;
        }

        $created_tables = [];
        foreach ($this->_definition as $table_name => $table_definition) {
            if (!($flow_params = $this->fetch_default_flow_params(['table_name' => $table_name]))
             || !($full_table_name = $this->get_flow_table_name($flow_params))) {
                PHS_Logger::error('Couldn\'t get flow parameters for model table ['.$table_name.'], model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE);
                continue;
            }

            if ($this->check_table_exists($flow_params)) {
                continue;
            }

            /** @var null|PHS_Event_Migration_models $event_obj */
            if ( !($event_obj = PHS_Event_Migration_models::trigger_before_missing(
                model_obj: $this, table_name: $table_name, old_version: $old_version, new_version: $new_version, is_dry_update: $is_dry_update
            ))
                 || $event_obj->result_has_error()
                 || self::st_has_error()) {
                $this->set_error(self::ERR_UPDATE, self::_t('Error in migrations before installing table %s, model %s.', $full_table_name, $model_id));
                PHS_Logger::error('Error in migrations before installing table ['.$full_table_name.'], model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE);

                PHS_Maintenance::output('['.$this->instance_plugin_name().']['.$this->instance_name().'] !!! Error in migrations before installing table ['.$table_name.']: '
                                        .self::st_get_simple_error_message($event_obj?->get_result_errors_as_string() ?: 'Unknown error.'));

                PHS_Maintenance::unlock_db_structure_read();

                return null;
            }

            $created_tables[] = $table_name;

            if (!$this->install_missing_table($flow_params)) {
                if (!$this->has_error()) {
                    $this->set_error(self::ERR_UPDATE, self::_t('Couldn\'t install table %s, model %s.', $full_table_name, $model_id));
                    PHS_Logger::error('Couldn\'t install missing table ['.$full_table_name.'], model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE);
                }

                PHS_Logger::error('FAILED installing missing table ['.$full_table_name.'] for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE);

                PHS_Maintenance::unlock_db_structure_read();

                return null;
            }

            /** @var null|PHS_Event_Migration_models $event_obj */
            if ( !($event_obj = PHS_Event_Migration_models::trigger_after_missing(
                model_obj: $this, table_name: $table_name, old_version: $old_version, new_version: $new_version, is_dry_update: $is_dry_update
            ))
                 || $event_obj->result_has_error()
                 || self::st_has_error()) {
                $this->set_error(self::ERR_UPDATE, self::_t('Error in migrations after installing table %s, model %s.', $full_table_name, $model_id));
                PHS_Logger::error('Error in migrations after installing table ['.$full_table_name.'], model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE);

                PHS_Maintenance::output('['.$this->instance_plugin_name().']['.$this->instance_name().'] !!! Error in migrations after installing table ['.$full_table_name.']: '
                                        .self::st_get_simple_error_message($event_obj?->get_result_errors_as_string() ?: 'Unknown error.'));

                PHS_Maintenance::unlock_db_structure_read();

                return null;
            }
        }

        /** @var null|PHS_Event_Migration_models $event_obj */
        if ( !($event_obj = PHS_Event_Migration_models::trigger_after_missing(
            model_obj: $this, old_version: $old_version, new_version: $new_version, is_dry_update: $is_dry_update
        ))
             || $event_obj->result_has_error()
             || self::st_has_error()) {
            $this->set_error(self::ERR_UPDATE, self::_t('Error in migrations after installing missing tables for model %s.', $model_id));
            PHS_Logger::error('Error in migrations after installing missing tables for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE);

            PHS_Maintenance::output('['.$this->instance_plugin_name().']['.$this->instance_name().'] !!! Error in migrations before installing missing tables: '
                                    .self::st_get_simple_error_message($event_obj?->get_result_errors_as_string() ?: 'Unknown error.'));

            PHS_Maintenance::unlock_db_structure_read();

            return null;
        }

        // Reset any errors related to generating tables...
        $this->reset_error();

        PHS_Logger::notice('DONE installing missing tables for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE);

        PHS_Maintenance::unlock_db_structure_read();

        return $created_tables;
    }

    /**
     * Uninstall model
     *
     * @return bool True on success or false on failure
     */
    final public function uninstall() : bool
    {
        $this->reset_error();

        if (!($plugins_model_id = self::generate_instance_id(self::INSTANCE_TYPE_MODEL, 'plugins'))) {
            $this->set_error(self::ERR_UNINSTALL, self::_t('Couldn\'t obtain plugins model id.'));

            return false;
        }

        if (!($this_instance_id = $this->instance_id())) {
            $this->set_error(self::ERR_UNINSTALL, self::_t('Couldn\'t obtain current model id.'));

            return false;
        }

        PHS_Logger::notice('Uninstalling model ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE);

        if ($this_instance_id === $plugins_model_id) {
            $this->set_error(self::ERR_UNINSTALL, self::_t('Plugins model cannot be uninstalled.'));

            return false;
        }

        if (!$this->_load_plugins_instance()) {
            PHS_Logger::error('!!! Error instantiating plugins model. ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE);

            $this->set_error(self::ERR_UNINSTALL, self::_t('Error instantiating plugins model.'));

            return false;
        }

        db_supress_errors($this->_plugins_instance->get_db_connection());
        if (!($db_details = $this->_plugins_instance->get_details_fields(['instance_id' => $this_instance_id]))
            || empty($db_details['type'])
            || $db_details['type'] !== self::INSTANCE_TYPE_MODEL) {
            db_restore_errors_state($this->_plugins_instance->get_db_connection());

            PHS_Logger::warning('Model doesn\'t seem to be installed. ['.$this_instance_id.']', PHS_Logger::TYPE_MAINTENANCE);

            return true;
        }

        db_restore_errors_state($this->_plugins_instance->get_db_connection());

        PHS_Logger::notice('Calling uninstall tables ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE);

        // First delete tables so in case it fails we can repeat the process...
        if (!$this->uninstall_tables()) {
            return false;
        }

        PHS_Logger::notice('DONE calling uninstall tables ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE);

        if (!$this->_plugins_instance->hard_delete($db_details)) {
            $this->copy_or_set_error($this->_plugins_instance,
                self::ERR_UNINSTALL, self::_t('Error hard-deleting model from database.'));

            PHS_Logger::error('!!! Error ['.$this->get_error_message().'] ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE);

            return false;
        }

        PHS_Logger::notice('DONE uninstalling model ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE);

        return true;
    }

    /**
     * This method will hard-delete tables in database defined by this model.
     * If you don't want to drop tables when model gets uninstalled overwrite this method with an empty method which returns true.
     *
     * @return bool Returns true if all tables were dropped or false on error
     */
    final public function uninstall_tables() : bool
    {
        $this->reset_error();

        if (empty($this->_definition)
            || !($flow_params = $this->fetch_default_flow_params())) {
            return true;
        }

        PHS_Logger::notice('Uninstalling tables for model ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE);

        foreach ($this->_definition as $table_name => $table_definition) {
            $flow_params['table_name'] = $table_name;

            if (!$this->uninstall_table($flow_params)) {
                PHS_Logger::error('!!! Error uninstalling tables for model ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE);

                $this->set_error(self::ERR_TABLE_GENERATE, self::_t('Error dropping table %s.', $table_name));

                return false;
            }
        }

        PHS_Logger::notice('DONE uninstalling tables for model ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE);

        return true;
    }

    /**
     * This method will hard-delete a table from database defined by this model.
     * If you don't want to drop a specific table when model gets uninstalled overwrite this method with an empty method which returns true.
     *
     * @param null|bool|array $flow_params Flow parameters
     *
     * @return bool Returns true if all tables were dropped or false on error
     */
    final public function uninstall_table(null | bool | array $flow_params) : bool
    {
        $this->reset_error();

        if (empty($this->_definition)
            || !($flow_params = $this->fetch_default_flow_params($flow_params))
            || !($full_table_name = $this->get_flow_table_name($flow_params))) {
            return true;
        }

        PHS_Logger::notice('Uninstalling table ['.$full_table_name.'] for model ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE);

        if (!$this->_uninstall_table_for_model($flow_params)) {
            PHS_Logger::error('FAILED uninstalling table ['.$full_table_name.'] for model ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE);

            $this->set_error_if_not_set(self::ERR_UNINSTALL_TABLE, self::_t('Error dropping table %s.', $full_table_name));

            return false;
        }

        PHS_Logger::notice('DONE uninstalling table ['.$full_table_name.'] for model ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE);

        return true;
    }

    /**
     * Performs any necessary actions when updating model from $old_version to $new_version
     *
     * @param string $old_version Old version of model
     * @param string $new_version New version of model
     *
     * @return bool true on success, false on failure
     */
    public function update(string $old_version, string $new_version) : bool
    {
        $this->reset_error();

        if (!($this_instance_id = $this->instance_id())) {
            PHS_Logger::error('!!! Couldn\'t obtain model instance ID.', PHS_Logger::TYPE_MAINTENANCE);

            $this->set_error(self::ERR_UPDATE, self::_t('Couldn\'t obtain current plugin id.'));

            return false;
        }

        $is_dry_update = PHS_Db::dry_update();

        PHS_Maintenance::output('['.$this->instance_plugin_name().']['.$this->instance_name().'] '
                                 .'Updating model from ['.$old_version.'] to ['.$new_version.']...'
                                 .($this->dynamic_table_structure() ? ' (Dynamic structure)' : ''));

        PHS_Maintenance::lock_db_structure_read();

        // If it is a dry update, don't trigger custom updates
        if (!$is_dry_update
            && !$this->custom_update($old_version, $new_version)) {
            $this->set_error_if_not_set(self::ERR_UPDATE, self::_t('Model custom update functionality failed.'));

            PHS_Maintenance::unlock_db_structure_read();

            PHS_Maintenance::output('['.$this->instance_plugin_name().']['.$this->instance_name().'] !!! Error in model custom update functionality: '.$this->get_error_message());

            return false;
        }

        if (!$this->_load_plugins_instance()) {
            $this->set_error_if_not_set(self::ERR_UPDATE, self::_t('Error instantiating plugins model.'));

            PHS_Maintenance::unlock_db_structure_read();

            PHS_Maintenance::output('['.$this->instance_plugin_name().']['.$this->instance_name().'] !!! Error instantiating plugins model.');

            return false;
        }

        // This will only create non-existing tables...
        if (null === ($created_tables = $this->install_missing_tables($old_version, $new_version))) {
            PHS_Maintenance::unlock_db_structure_read();

            return false;
        }

        $custom_after_missing_tables_updates_params = ['created_tables' => $created_tables, ];

        if (!$is_dry_update
            && !$this->custom_after_missing_tables_update($old_version, $new_version, $custom_after_missing_tables_updates_params)) {
            $this->set_error_if_not_set(self::ERR_UPDATE,
                self::_t('Model custom after missing tables update functionality failed.'));

            PHS_Maintenance::unlock_db_structure_read();

            PHS_Maintenance::output('['.$this->instance_plugin_name().']['.$this->instance_name().'] !!! Error in model custom after missing tables update functionality: '.$this->get_error_message());

            return false;
        }

        $update_tables_params = ['created_tables' => $created_tables, ];

        // Update table structure
        if (!$this->update_tables($old_version, $new_version, $update_tables_params)) {
            PHS_Maintenance::unlock_db_structure_read();

            return false;
        }

        if (!$is_dry_update
            && !$this->custom_after_update($old_version, $new_version)) {
            $this->set_error_if_not_set(self::ERR_UPDATE, self::_t('Model custom after update functionality failed.'));

            PHS_Maintenance::unlock_db_structure_read();

            PHS_Maintenance::output('['.$this->instance_plugin_name().']['.$this->instance_name().'] !!! Error in model custom after update functionality: '.$this->get_error_message());

            return false;
        }

        PHS_Maintenance::unlock_db_structure_read();

        $plugin_name = ($plugin_obj = $this->get_plugin_instance())
                       && ($plugin_info = $plugin_obj->get_plugin_info())
                       && !empty($plugin_info['name'])
            ? $plugin_info['name']
            : $this->instance_plugin_name();

        if (!$is_dry_update
         && (!($db_details = $this->_plugins_instance->update_record(
             $this_instance_id, $plugin_name, $this->instance_is_core(), $this->get_model_version()))
             || empty($db_details['new_data']))
        ) {
            $this->copy_or_set_error($this->_plugins_instance,
                self::ERR_UPDATE, self::_t('Error saving model details to database.'));

            PHS_Maintenance::output('['.$this->instance_plugin_name().']['.$this->instance_name().'] !!! Error updating model details in database: '.$this->get_error_message());

            return false;
        }

        PHS_Maintenance::output('['.$this->instance_plugin_name().']['.$this->instance_name().'] DONE Updating model');

        return true;
    }

    public function set_maintenance_database_credentials(array $flow_arr = []) : bool
    {
        $maintenance_db_pass = constant('PHS_MAINTENANCE_DB_PASSWORD') ?? '';

        if (!defined('PHS_MAINTENANCE_DB_USERNAME')
            || !($maintenance_db_user = constant('PHS_MAINTENANCE_DB_USERNAME'))
            // make sure we don't have the placeholder from main.dist.php
            || $maintenance_db_user === '{{PHS_MAINTENANCE_DB_USERNAME}}'
            || (!empty($this->_old_db_settings['user'])
                && $this->_old_db_settings['user'] === $maintenance_db_user
                && $this->_old_db_settings['password'] === $maintenance_db_pass)
        ) {
            return true;
        }

        if ( !($connection_name = $this->get_db_connection($this->fetch_default_flow_params($flow_arr)))
            || !($settings_arr = PHS_Db::get_db_connection($connection_name)) ) {
            return false;
        }

        $this->_old_db_settings = $settings_arr;

        $settings_arr['user'] = $maintenance_db_user;
        $settings_arr['password'] = $maintenance_db_pass;

        return (bool)PHS_Db::add_db_connection($connection_name, $settings_arr);
    }

    public function reset_maintenance_database_credentials(array $flow_arr = []) : bool
    {
        if ( empty($this->_old_db_settings) ) {
            return true;
        }

        if ( !($connection_name = $this->get_db_connection($this->fetch_default_flow_params($flow_arr)))
            || !PHS_Db::add_db_connection($connection_name, $this->_old_db_settings)) {
            return false;
        }

        $this->_old_db_settings = null;

        return true;
    }

    protected function _relations_definition() : void
    {
    }

    /**
     * Performs any necessary custom actions when updating model from $old_version to $new_version.
     * This action is performed before changing any database structure
     * Overwrite this method to do particular updates.
     * If this function returns false whole update will stop and error set in this method will be used.
     *
     * @param string $old_version Old version of plugin
     * @param string $new_version New version of plugin
     *
     * @return bool true on success, false on failure
     */
    /**
     * @noinspection PhpUnusedParameterInspection
     */
    protected function custom_update($old_version, $new_version)
    {
        return true;
    }

    /**
     * Performs any necessary custom actions after updating model tables from $old_version to $new_version
     * This action is performed after all table structure and data was updated
     * Overwrite this method to do particular updates.
     * If this function returns false updating model to last version will stop, model table structure will remain changed tho.
     *
     * @param string $old_version Old version of plugin
     * @param string $new_version New version of plugin
     *
     * @return bool true on success, false on failure
     */
    /**
     * @noinspection PhpUnusedParameterInspection
     */
    protected function custom_after_update($old_version, $new_version)
    {
        return true;
    }

    /**
     * Performs any necessary custom actions after installing missing tables while updating model from $old_version to $new_version
     * This action is performed after all missing tables are created
     * Overwrite this method to do particular updates.
     * If this function returns false updating model to last version will stop, model table structure will remain changed tho.
     *
     * @param string $old_version Old version of plugin
     * @param string $new_version New version of plugin
     * @param bool|array $params_arr Functionality parameters
     *
     * @return bool true on success, false on failure
     */
    /**
     * @noinspection PhpUnusedParameterInspection
     */
    protected function custom_after_missing_tables_update($old_version, $new_version, $params_arr = false)
    {
        return true;
    }

    /**
     * Validate array containing table definition
     *
     * @param array $details_arr
     *
     * @return array
     */
    protected function _validate_table_details(array $details_arr) : array
    {
        return self::validate_array($details_arr, $this->_default_table_details_arr());
    }

    /**
     * Validate table extra indexes array definition
     *
     * @param array $indexes_arr
     *
     * @return array
     */
    protected function _validate_table_extra_indexes(array $indexes_arr) : array
    {
        if (empty($indexes_arr)) {
            return [];
        }

        $new_indexes = [];
        foreach ($indexes_arr as $index_name => $index_arr) {
            if (empty($index_arr) || !is_array($index_arr)) {
                continue;
            }

            $new_indexes[$index_name] = $this->_validate_table_extra_index($index_arr);
        }

        return $new_indexes;
    }

    /**
     * Validate table extra index array definition
     *
     * @param array $index_arr
     *
     * @return array
     */
    protected function _validate_table_extra_index(array $index_arr) : array
    {
        $def_values = $this->_default_table_extra_index_arr();
        if (empty($index_arr)) {
            return $def_values;
        }

        return self::validate_array($index_arr, $def_values);
    }

    /**
     * Get a list of all tables for this model
     * @return array
     */
    final protected function get_all_table_names() : array
    {
        if (!empty($this->model_tables_arr)) {
            return $this->model_tables_arr;
        }

        $tables_arr = $this->get_table_names();
        $instance_id = $this->instance_id();

        /** @var PHS_Event_Model_table_names $event_obj */
        if (($event_obj = PHS_Event_Model_table_names::tables_for_instance_id($instance_id, $tables_arr))
           && ($new_tables = $event_obj->get_output('tables_arr'))) {
            $tables_arr = self::array_merge_unique_values($new_tables, $tables_arr);
        }

        $this->model_tables_arr = $tables_arr;

        return $tables_arr;
    }

    /**
     * @param false|array $instance_details
     */
    protected function _do_construct($instance_details = false) : void
    {
        parent::_do_construct($instance_details);

        $this->_validate_tables_definition();
        $this->_relations_definition();
    }

    /**
     * When inserting or editing records in database, this method will normalize and validate provided data and give an array of values that should be used
     * in insert or edit call
     *
     * @param array $flow_params Flow parameters
     *
     * @return null|array Validated data structure or false on failure
     */
    protected function validate_data_for_fields(array $flow_params) : ?array
    {
        $this->reset_error();

        if (!($table_fields = $this->get_definition($flow_params))) {
            $this->set_error(self::ERR_MODEL_FIELDS, self::_t('Invalid table definition.'));

            return null;
        }

        if (empty($flow_params['action'])
            || !in_array($flow_params['action'], ['insert', 'edit'], true)) {
            $flow_params['action'] = 'insert';
        }

        $input_arr = [
            'model_instance_id'  => $this->instance_id(),
            'plugin_instance_id' => $this->get_plugin_instance()?->instance_id(),
            'flow_params'        => $flow_params,
            'table_fields'       => $table_fields,
            'model_obj'          => $this,
        ];

        /** @var PHS_Event_Model_validate_data_fields $event_obj */
        if (($event_obj = PHS_Event_Model_validate_data_fields::trigger( $input_arr ))) {
            if (($new_flow_params = $event_obj->get_output('flow_params'))) {
                $flow_params = self::merge_array_assoc($flow_params, $new_flow_params);
            }
            if (($new_table_fields = $event_obj->get_output('table_fields'))) {
                $table_fields = self::merge_array_assoc($table_fields, $new_table_fields);
            }
        }

        $validated_fields = [];
        $data_arr = [];
        $has_raw_fields = false;
        foreach ($table_fields as $field_name => $field_details) {
            if (empty($field_details['editable'])
                && $flow_params['action'] === 'edit') {
                continue;
            }

            if (array_key_exists($field_name, $flow_params['fields'])) {
                // we can pass raw values (see quick_edit or quick_insert)
                if (!is_array($flow_params['fields'][$field_name])) {
                    $field_value
                        = $this->_validate_field_value($flow_params['fields'][$field_name], $field_name, $field_details);
                } else {
                    $has_raw_fields = true;
                    $field_value = $flow_params['fields'][$field_name];

                    if (empty($flow_params['fields'][$field_name]['raw_field'])
                        && array_key_exists('value', $flow_params['fields'][$field_name])) {
                        $field_value['value']
                            = $this->_validate_field_value($flow_params['fields'][$field_name]['value'], $field_name,
                                $field_details);
                    }
                }

                $data_arr[$field_name] = $field_value;
                $validated_fields[] = $field_name;
            } elseif (array_key_exists('default', $field_details)
                      && $flow_params['action'] === 'insert') {
                // When editing records only passed fields will be saved in database...
                $data_arr[$field_name] = $field_details['default'];
            }
        }

        $return_arr = [];
        $return_arr['has_raw_fields'] = $has_raw_fields;
        $return_arr['data_arr'] = $data_arr;
        $return_arr['validated_fields'] = $validated_fields;

        return $return_arr;
    }

    /**
     * Install a specific model table provided in flow parameters
     *
     * @param array $flow_params Flow parameters
     *
     * @return bool True on success or false on failure
     */
    final protected function install_table(array $flow_params) : bool
    {
        $this->reset_error();

        return $this->_install_table_for_model($flow_params);
    }

    /**
     * Update a specific model table provided in flow parameters
     *
     * @param array $flow_params Flow parameters
     *
     * @return bool True on success or false on failure
     */
    final protected function update_table(array $flow_params) : bool
    {
        $this->reset_error();

        return $this->_update_table_for_model($flow_params);
    }

    /**
     * Install a missing table provided in flow parameters when updating model
     *
     * @param array $flow_params Flow parameters
     *
     * @return bool True on success or false on failure
     */
    final protected function install_missing_table(array $flow_params) : bool
    {
        $this->reset_error();

        return $this->_install_missing_table_for_model($flow_params);
    }

    /**
     * Populate model tables structures array with definition from model (not from database)
     *
     * @return bool True on success, false on failure
     */
    protected function _validate_tables_definition() : bool
    {
        if (!($all_tables_arr = $this->get_all_table_names())) {
            return false;
        }

        foreach ($all_tables_arr as $table_name) {
            if (!($flow_params = $this->fetch_default_flow_params(['table_name' => $table_name]))) {
                $this->set_error(self::ERR_MODEL_FIELDS, self::_t('Couldn\'t fetch flow parameters for table %s.', $table_name));

                return false;
            }

            if (!$this->_validate_definition($flow_params)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array $flow_params
     *
     * @return null|array
     */
    private function _all_fields_definition(array $flow_params) : ?array
    {
        $this->reset_error();

        if (!($flow_params = $this->fetch_default_flow_params($flow_params))
            || !($fields_arr = $this->fields_definition($flow_params))) {
            $this->set_error(self::ERR_MODEL_FIELDS, self::_t('Failed validating flow parameters.'));

            return null;
        }

        $instance_id = $this->instance_id();
        $plugin_instance_id = null;
        if (($plugin_obj = $this->get_plugin_instance())) {
            $plugin_instance_id = $plugin_obj->instance_id();
        }

        $hook_params = self::default_table_fields_hook_args();
        $hook_params['model_id'] = $instance_id;
        $hook_params['flow_params'] = $flow_params;
        $hook_params['fields_arr'] = $fields_arr;

        // Plugin level customization
        if (!empty($plugin_instance_id)
         && ($extra_fields_arr = PHS::trigger_hooks(self::HOOK_TABLE_FIELDS.'_'.$plugin_instance_id, $hook_params))) {
            $fields_arr = self::merge_array_assoc($extra_fields_arr['fields_arr'], $fields_arr);
        }

        // Model level customization
        if (($extra_fields_arr = PHS::trigger_hooks(self::HOOK_TABLE_FIELDS.'_'.$instance_id, $hook_params))) {
            $fields_arr = self::merge_array_assoc($extra_fields_arr['fields_arr'], $fields_arr);
        }

        // Table level customization
        if (($extra_fields_arr = PHS::trigger_hooks(self::HOOK_TABLE_FIELDS, $hook_params))) {
            $fields_arr = self::merge_array_assoc($extra_fields_arr['fields_arr'], $fields_arr);
        }

        $input_arr = [
            'model_instance_id'  => $instance_id,
            'plugin_instance_id' => $plugin_instance_id,
            'flow_params'        => $flow_params,
            'fields_arr'         => $fields_arr,
            'model_obj'          => $this,
        ];

        /** @var PHS_Event_Model_fields $event_obj */
        if (($event_obj = PHS_Event_Model_fields::trigger_for_model($this::class, $input_arr))
           && ($new_fields_arr = $event_obj->get_output('fields_arr'))) {
            $fields_arr = $new_fields_arr;
        }

        return $fields_arr;
    }

    /**
     * Validate a single table definition (provided in flow parameters)
     *
     * @param null|array $params Flow parameters
     *
     * @return bool True on success, false on failure
     */
    private function _validate_definition(?array $params = null) : bool
    {
        if (!($params = $this->fetch_default_flow_params($params))) {
            $this->set_error(self::ERR_MODEL_FIELDS, self::_t('Failed validating flow parameters.'));

            return false;
        }

        if (!empty($this->_definition[$params['table_name']])) {
            return true;
        }

        if (!($model_fields = $this->_all_fields_definition($params))) {
            $this->set_error(self::ERR_MODEL_FIELDS, self::_t('Invalid fields definition for table %s.', $params['table_name']));

            return false;
        }

        $this->_definition[$params['table_name']] = [];
        foreach ($model_fields as $field_name => $field_arr) {
            if (!is_array($field_arr)) {
                continue;
            }

            if ($field_name === self::T_DETAILS_KEY) {
                $this->_definition[$params['table_name']][$field_name] = $this->_validate_table_details($field_arr);
                continue;
            }

            if ($field_name === self::EXTRA_INDEXES_KEY) {
                $this->_definition[$params['table_name']][$field_name] = $this->_validate_table_extra_indexes($field_arr);
                continue;
            }

            if (!($new_field_arr = $this->_validate_field($field_arr))) {
                $this->set_error(self::ERR_MODEL_FIELDS, self::_t('Field %s has an invalid definition.', $field_name));

                return false;
            }

            $this->_definition[$params['table_name']][$field_name] = $new_field_arr;
        }

        if (empty($this->_definition[$params['table_name']][self::T_DETAILS_KEY])) {
            $this->_definition[$params['table_name']][self::T_DETAILS_KEY] = $this->_default_table_details_arr();
        }
        if (empty($this->_definition[$params['table_name']][self::EXTRA_INDEXES_KEY])) {
            $this->_definition[$params['table_name']][self::EXTRA_INDEXES_KEY] = [];
        }

        return true;
    }
    //
    // endregion  END Abstract model specific methods
    //

    /**
     * @return string Returns version of base model (abstract class)
     */
    final public static function get_model_base_version() : string
    {
        return '1.2.0';
    }

    /**
     * Default hook parameters
     * @return array
     */
    final public static function default_table_fields_hook_args() : array
    {
        return PHS_Hooks::hook_args_definition([
            'model_id'    => '',
            'flow_params' => [],
            'fields_arr'  => [],
        ]);
    }

    protected static function get_cached_db_tables_structure_for_driver(string $driver) : array
    {
        return self::$tables_arr[$driver] ?? [];
    }

    protected static function get_cached_db_table_structure(string $table_name, string $driver) : array
    {
        return self::$tables_arr[$driver][$table_name] ?? [];
    }

    protected static function add_cached_db_table_structure(array $structure, string $table_name, string $driver) : void
    {
        if (empty(self::$tables_arr[$driver]) || !is_array(self::$tables_arr[$driver])) {
            self::$tables_arr[$driver] = [];
        }
        if (empty(self::$tables_arr[$driver][$table_name]) || !is_array(self::$tables_arr[$driver][$table_name])) {
            self::$tables_arr[$driver][$table_name] = [];
        }

        self::$tables_arr[$driver][$table_name] = $structure;
    }

    protected static function cached_db_table_structure_has_fields(array $structure) : bool
    {
        return !empty($structure) && !empty($structure[self::T_DETAILS_KEY]) && count($structure) > 1;
    }

    protected static function cached_db_add_column_index(string $column, string $table_name, string $driver) : bool
    {
        if (empty(self::$tables_arr[$driver][$table_name][$column])
         || !is_array(self::$tables_arr[$driver][$table_name][$column])) {
            return false;
        }

        self::$tables_arr[$driver][$table_name][$column]['index'] = true;

        return true;
    }

    protected static function cached_db_drop_column_index(string $column, string $table_name, string $driver) : bool
    {
        if (empty(self::$tables_arr[$driver][$table_name][$column])
         || !is_array(self::$tables_arr[$driver][$table_name][$column])) {
            return false;
        }

        self::$tables_arr[$driver][$table_name][$column]['index'] = false;

        return true;
    }

    protected static function cached_db_set_column_definition(string $column, array $definition, string $table_name, string $driver) : bool
    {
        if (empty(self::$tables_arr[$driver][$table_name])
         || !is_array(self::$tables_arr[$driver][$table_name])) {
            return false;
        }

        self::$tables_arr[$driver][$table_name][$column] = $definition;

        return true;
    }

    protected static function cached_db_remove_column(string $column, string $table_name, string $driver) : bool
    {
        if (empty(self::$tables_arr[$driver][$table_name][$column])
         || !is_array(self::$tables_arr[$driver][$table_name][$column])) {
            return true;
        }

        unset(self::$tables_arr[$driver][$table_name][$column]);

        return true;
    }
}
