<?php

namespace phs\libraries;

use \phs\PHS;
use \phs\PHS_Db;
use phs\PHS_Maintenance;
use \phs\system\core\models\PHS_Model_Plugins;

abstract class PHS_Model_Core_Base extends PHS_Has_db_settings
{
    const ERR_MODEL_FIELDS = 40000, ERR_TABLE_GENERATE = 40001, ERR_INSTALL = 40002, ERR_UPDATE = 40003, ERR_UNINSTALL = 40004,
          ERR_INSERT = 40005, ERR_EDIT = 40006, ERR_DELETE_BY_INDEX = 40007, ERR_ALTER = 40008, ERR_DELETE = 40009, ERR_UPDATE_TABLE = 40010,
          ERR_UNINSTALL_TABLE = 40011;

    const HOOK_RAW_PARAMETERS = 'phs_model_raw_parameters', HOOK_INSERT_BEFORE_DB = 'phs_model_insert_before_db',
          HOOK_TABLES = 'phs_model_tables', HOOK_TABLE_FIELDS = 'phs_model_table_fields', HOOK_HARD_DELETE = 'phs_model_hard_delete';

    const SIGNAL_INSERT = 'phs_model_insert', SIGNAL_EDIT = 'phs_model_edit', SIGNAL_HARD_DELETE = 'phs_model_hard_delete',
          SIGNAL_INSTALL = 'phs_model_install', SIGNAL_UNINSTALL = 'phs_model_uninstall',
          SIGNAL_UPDATE = 'phs_model_update', SIGNAL_FORCE_INSTALL = 'phs_model_force_install';

    const DATE_EMPTY = '0000-00-00', DATETIME_EMPTY = '0000-00-00 00:00:00',
          DATE_DB = 'Y-m-d', DATETIME_DB = 'Y-m-d H:i:s';

    const T_DETAILS_KEY = '{details}', EXTRA_INDEXES_KEY = '{indexes}';

    // Tables definition
    protected $_definition = [];

    protected $model_tables_arr = [];

    protected static $tables_arr = [];

    //
    //region Model class methods
    //
    /**
     * @return array of string Returns an array of strings containing tables that model will handle
     */
    abstract public function get_table_names();

    /**
     * @return string Returns main table name used when calling insert with no table name
     */
    abstract public function get_main_table_name();

    /**
     * @return string Returns version of model
     */
    abstract public function get_model_version();

    /**
     * @param array|bool $params Parameters in the flow
     *
     * @return array|bool Returns an array with table fields
     */
    abstract public function fields_definition( $params = false );
    //
    //endregion END Model class methods
    //

    //
    //region Abstract model specific methods
    //
    /**
     * @return string Returns model driver
     */
    abstract public function get_model_driver();

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
     * @param array|bool $params Parameters in the flow
     *
     * @return string What's primary key of the table
     */
    abstract public function get_primary_key( $params = false );

    /**
     * Prepares primary key for a query (intval for int or trim for strings)
     *
     * @param int|string $id Primary key value
     * @param array|bool $params Parameters in the flow
     *
     * @return int|string Prepared primary key
     */
    abstract public function prepare_primary_key( $id, $params = false );

    /**
     * Returns an array of data types supported by model
     *
     * @return array Data types array
     */
    abstract public function get_field_types();

    /**
     * Checks if provided type is a valid data type and returns an array with details about data type
     *
     * @param int $type Field type
     *
     * @return array Data type details array
     */
    abstract public function valid_field_type( $type );

    /**
     * Retrieve one record from database by it's primary key (model specific functionality)
     *
     * @param string|int $id Id of record we want to get from database
     * @param bool|array $params Flow parameters
     *
     * @return array|bool Record from database in an array structure or false on error
     */
    abstract protected function _get_details_for_model( $id, $params = false );

    /**
     * Retrieve one (or more) record(s) from database based on provided conditions (model specific functionality)
     *
     * @param array $constrain_arr Conditional db fields
     * @param array|bool $params Flow parameters
     *
     * @return array|false|null Returns one (or more) record(s) as array (with matching conditions)
     */
    abstract protected function _get_details_fields_for_model( $constrain_arr, $params = false );

    /**
     * Tells if table from provided flow exists in flow database
     *
     * @param bool|array $flow_params Flow parameters
     * @param bool $force Tells if we should skip cache (true) or, if we got table structure already, use cached tables
     *
     * @return bool True if table exists in flow database and false if it doesn't exist
     */
    abstract protected function _check_table_exists_for_model( $flow_params = false, $force = false );

    /**
     * Install a specific model table provided in flow parameters
     *
     * @param array $flow_params Flow parameters
     *
     * @return bool True on success or false on failure
     */
    abstract protected function _install_table_for_model( $flow_params );

    /**
     * Update a specific model table provided in flow parameters
     *
     * @param array $flow_params Flow parameters
     *
     * @return bool True on success or false on failure
     */
    abstract protected function _update_table_for_model( $flow_params );

    /**
     * Install a missing table provided in flow parameters when updating model
     *
     * @param array $flow_params Flow parameters
     *
     * @return bool True on success or false on failure
     */
    abstract protected function _update_missing_table_for_model( $flow_params );

    /**
     * This method will hard-delete a table from database defined by this model.
     * If you don't want to drop a specific table when model gets uninstalled overwrite this method with an empty method which returns true.
     *
     * @param array $flow_params Flow parameters
     *
     * @return bool Returns true if all tables were dropped or false on error
     */
    abstract protected function _uninstall_table_for_model( $flow_params );

    /**
     * Get table definition from database as an array which can be compared with model table structure
     *
     * @param bool|array $flow_params Flow parameters
     * @param bool $force Tells if we should skip cache (true) or, if we got table structure already, use cached tables
     *
     * @return bool|array Returns table structure as array or false if we couldn't obtain table structure from database
     */
    abstract protected function _get_table_columns_as_definition_for_model( $flow_params = false, $force = false );

    /**
     * This method hard-deletes a record from database.
     *
     * @param array|string|int $existing_data Array with full database fields or primary key
     * @param array|bool $params Parameters in the flow
     *
     * @return bool Returns true or false depending on hard delete success
     */
    abstract protected function _hard_delete_for_model( $existing_data, $params = false );

    // Default table structures...

    /**
     * Details related to table
     * @return array
     */
    abstract protected function _default_table_details_arr();
    /**
     * Details related table extra indexes
     * @return array
     */
    abstract protected function _default_table_extra_index_arr();

    /**
     * Validate a field definition
     * @param array $field_arr
     *
     * @return mixed
     */
    abstract protected function _validate_field( $field_arr );

    /**
     * Validate a value for a field according to field definition
     * @param mixed $value
     * @param string $field_name
     * @param array $field_details
     * @param bool|array $params
     *
     * @return mixed
     */
    abstract protected function _validate_field_value( $value, $field_name, $field_details, $params = false );
    //
    //endregion  END Abstract model specific methods
    //

    /**
     * @return string Returns version of base model (abstract class)
     */
    final public static function get_model_base_version()
    {
        return '1.0.4';
    }

    /**
     * @return int Should return INSTANCE_TYPE_* constant
     */
    final public function instance_type()
    {
        return self::INSTANCE_TYPE_MODEL;
    }

    /**
     * @param array|bool $params Parameters in the flow
     *
     * @return false|string Returns false if model uses default database connection or connection name as string
     */
    public function get_db_connection( $params = false )
    {
        $db_driver = false;
        if( !empty( $params ) && is_array( $params ) )
        {
            if( !empty( $params['db_connection'] ) )
                return $params['db_connection'];

            if( !empty( $params['db_driver'] ) )
                $db_driver = $params['db_driver'];
        }

        if( empty( $db_driver ) )
            $db_driver = $this->get_model_driver();

        return PHS_Db::default_db_connection( $db_driver );
    }

    /**
     * Returns prefix of tables for provided database connection
     *
     * @param bool|array $params Flow parameters
     *
     * @return string Connection tables prefix
     */
    public function get_db_prefix( $params = false )
    {
        if( !($params = $this->fetch_default_flow_params( $params )) )
            return '';

        $db_connection = $this->get_db_connection( $params );

        return db_prefix( $db_connection );
    }

    /**
     * Returns database name for provided database connection
     *
     * @param bool|array $params Flow parameters
     *
     * @return string Connection tables prefix
     */
    public function get_db_database( $params = false )
    {
        if( !($params = $this->fetch_default_flow_params( $params )) )
            return '';

        $db_connection = $this->get_db_connection( $params );

        return db_database( $db_connection );
    }

    /**
     * Returns full table name used in current flow
     *
     * @param bool|array $params Flow parameters
     *
     * @return string Full table name used in current flow
     */
    public function get_flow_table_name( $params = false )
    {
        if( !($params = $this->fetch_default_flow_params( $params )) )
            return '';

        if( !($db_prefix = $this->get_db_prefix( $params )) )
            $db_prefix = '';

        return $db_prefix.$params['table_name'];
    }

    /**
     * Returns table name used in flow without prefix
     *
     * @param array|bool $params Parameters in the flow
     *
     * @return string Returns table set in parameters flow or main table if no table is specified in flow
     * (table name can be passed to $params array of each method in 'table_name' index)
     */
    public function get_table_name( $params = false )
    {
        if( !empty( $params ) && is_array( $params )
         && !empty( $params['table_name'] ) )
            return $params['table_name'];

        // return default table...
        return $this->get_main_table_name();
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
    /** @noinspection PhpUnusedParameterInspection */
    protected function custom_update( $old_version, $new_version )
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
    /** @noinspection PhpUnusedParameterInspection */
    protected function custom_after_update( $old_version, $new_version )
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
    /** @noinspection PhpUnusedParameterInspection */
    protected function custom_after_missing_tables_update( $old_version, $new_version, $params_arr = false )
    {
        return true;
    }

    /**
     * Test DB connection for this model
     * @param bool|array $flow_params
     *
     * @return bool
     */
    public function test_db_connection( $flow_params = false )
    {
        if( !($flow_params = $this->fetch_default_flow_params( $flow_params )) )
            return false;

        db_supress_errors( $flow_params['db_connection'] );
        if( !db_test_connection( $flow_params['db_connection'] ) )
        {
            db_restore_errors_state( $flow_params['db_connection'] );
            return false;
        }

        db_restore_errors_state( $flow_params['db_connection'] );

        return true;
    }

    /**
     * Get table definition array
     * @param bool|array $flow_params Flow parameters
     * @param bool $force
     *
     * @return bool|mixed
     */
    public function get_table_details( $flow_params = false, $force = false )
    {
        $this->reset_error();

        if( !($flow_params = $this->fetch_default_flow_params( $flow_params ))
         || !($flow_table_name = $this->get_flow_table_name( $flow_params ))
         || !($my_driver = $this->get_model_driver()) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Failed validating flow parameters.' ) );
            return false;
        }

        if( !empty( $force )
         || empty( self::$tables_arr )
         || empty( self::$tables_arr[$my_driver] ) || !is_array( self::$tables_arr[$my_driver] ) )
        {
            if( !$this->_check_table_exists_for_model( $flow_params, $force ) )
                return false;
        }

        if( empty( self::$tables_arr )
         || empty( self::$tables_arr[$my_driver] ) || !is_array( self::$tables_arr[$my_driver] )
         || empty( self::$tables_arr[$my_driver][$flow_table_name] ) )
            return false;

        return self::$tables_arr[$my_driver][$flow_table_name];
    }

    /**
     * Tells if table from provided flow exists in flow database
     *
     * @param bool|array $flow_params Flow parameters
     * @param bool $force Tells if we should skip cache (true) or, if we got table structure already, use cached tables
     *
     * @return bool True if table exists in flow database and false if it doesn't exist
     */
    public function check_table_exists( $flow_params = false, $force = false )
    {
        $this->reset_error();

        return (!$this->get_table_details( $flow_params, $force )?false:true);
    }

    /**
     * Get table definition from database as an array which can be compared with model table structure
     *
     * @param bool|array $flow_params Flow parameters
     * @param bool $force Tells if we should skip cache (true) or, if we got table structure already, use cached tables
     *
     * @return bool|array Returns table structure as array or false if we couldn't obtain table structure from database
     */
    public function get_table_columns_as_definition( $flow_params = false, $force = false )
    {
        $this->reset_error();

        if( !($flow_params = $this->fetch_default_flow_params( $flow_params ))
         || !($flow_table_name = $this->get_flow_table_name( $flow_params ))
         || !($flow_database_name = $this->get_db_database( $flow_params ))
         || !($my_driver = $this->get_model_driver()) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Failed validating flow parameters.' ) );
            return false;
        }

        if( !$this->check_table_exists( $flow_params, $force ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Table %s doesn\'t exist.', $flow_table_name ) );
            return false;
        }

        // sane check...
        if( empty( self::$tables_arr[$my_driver][$flow_table_name] ) || !is_array( self::$tables_arr[$my_driver][$flow_table_name] ) )
            self::$tables_arr[$my_driver][$flow_table_name] = [];

        if( empty( $force )
         && !empty( self::$tables_arr[$my_driver][$flow_table_name] ) && is_array( self::$tables_arr[$my_driver][$flow_table_name] )
         && count( self::$tables_arr[$my_driver][$flow_table_name] ) > 1 )
            return self::$tables_arr[$my_driver][$flow_table_name];

        $this->_get_table_columns_as_definition_for_model( $flow_params, $force );

        return self::$tables_arr[$my_driver][$flow_table_name];
    }

    /**
     * Check if a specified column exists in table definition array and if it exists return structure definition as array
     *
     * @param string $field Field to be checked/retrieved
     * @param bool|array $flow_params Flow parameters
     * @param bool $force Tells if we should skip cache (true) or, if we got table structure already, use cached tables
     *
     * @return bool|array Returns column structure as array or false if we couldn't obtain column structure from flow table
     */
    public function check_column_exists( $field, $flow_params = false, $force = false )
    {
        $this->reset_error();

        if( !($flow_params = $this->fetch_default_flow_params( $flow_params ))
         || !($flow_table_name = $this->get_flow_table_name( $flow_params )) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Failed validating flow parameters.' ) );
            return false;
        }

        if( !($table_definition = $this->get_table_columns_as_definition( $flow_params, $force ))
         || !is_array( $table_definition ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Couldn\'t get definition for table %s.', $flow_table_name ) );
            return false;
        }

        if( !array_key_exists( $field, $table_definition ) )
            return false;

        return $table_definition[$field];
    }

    /**
     * Check if provided index is defined in table structure
     * @param string $index_name Index to be found
     * @param bool|array $flow_params Flow parameters
     * @param bool $force
     *
     * @return bool|mixed
     */
    public function check_extra_index_exists( $index_name, $flow_params = false, $force = false )
    {
        $this->reset_error();

        if( !($flow_params = $this->fetch_default_flow_params( $flow_params ))
         || !($flow_table_name = $this->get_flow_table_name( $flow_params )) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Failed validating flow parameters.' ) );
            return false;
        }

        if( !($table_definition = $this->get_table_columns_as_definition( $flow_params, $force ))
         || !is_array( $table_definition ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Couldn\'t get definition for table %s.', $flow_table_name ) );
            return false;
        }

        if( empty( $table_definition[self::EXTRA_INDEXES_KEY] )
         || !array_key_exists( $index_name, $table_definition[self::EXTRA_INDEXES_KEY] ) )
            return false;

        return $table_definition[self::EXTRA_INDEXES_KEY][$index_name];
    }

    /**
     * Checks if model tables structure exists in database. Install table structures if model is not installed, update model if required
     *
     * @return bool True if install, update or structure doesn't require any actions or false if failed installing or updating structures
     */
    public function check_installation()
    {
        $this->reset_error();

        if( !$this->_load_plugins_instance() )
            return false;

        if( !($db_details = $this->_plugins_instance->get_plugins_db_details( $this->instance_id() )) )
        {
            $this->reset_error();

            return $this->install();
        }

        if( $this->dynamic_table_structure()
         || version_compare( $db_details['version'], $this->get_model_version(), '!=' ) )
            return $this->update( $db_details['version'], $this->get_model_version() );

        return true;
    }

    /**
     * This method hard-deletes a record from database. If additional work is required before hard-deleting record,
     * self::HOOK_HARD_DELETE is called before deleting.
     *
     * @param array|string|int $existing_data Array with full database fields or primary key
     * @param array|bool $params Parameters in the flow
     *
     * @return bool Returns true or false depending on hard delete success
     */
    final public function hard_delete( $existing_data, $params = false )
    {
        self::st_reset_error();
        $this->reset_error();

        if( !($params = $this->fetch_default_flow_params( $params ))
         || !($existing_arr = $this->data_to_array( $existing_data, $params )) )
            return false;

        $hook_params = [];
        $hook_params['params'] = $params;
        $hook_params['existing_data'] = $existing_arr;

        if( ($trigger_result = PHS::trigger_hooks( self::HOOK_HARD_DELETE, $hook_params )) !== null )
        {
            if( !$trigger_result )
            {
                if( self::st_has_error() )
                    $this->copy_static_error( self::HOOK_HARD_DELETE );
                else
                    $this->set_error( self::HOOK_HARD_DELETE, self::_t( 'Delete cancelled by trigger.' ) );

                return false;
            }

            if( is_array( $trigger_result ) )
            {
                if( !empty( $trigger_result['params'] ) )
                    $params = $trigger_result['params'];
            }
        }

        return $this->_hard_delete_for_model( $existing_arr, $params );
    }

    /**
     * Validate array containing table definition
     * @param array $details_arr
     *
     * @return array|bool
     */
    protected function _validate_table_details( $details_arr )
    {
        return self::validate_array( $details_arr, $this->_default_table_details_arr() );
    }

    /**
     * Validate table extra indexes array definition
     * @param array $indexes_arr
     *
     * @return array
     */
    protected function _validate_table_extra_indexes( $indexes_arr )
    {
        if( empty( $indexes_arr ) || !is_array( $indexes_arr ) )
            return [];

        $new_indexes = [];
        foreach( $indexes_arr as $index_name => $index_arr )
        {
            if( empty( $index_arr ) || !is_array( $index_arr ) )
                continue;

            $new_indexes[$index_name] = $this->_validate_table_extra_index( $index_arr );
        }

        return $new_indexes;
    }

    /**
     * Validate table extra index array definition
     * @param array $index_arr
     *
     * @return array|bool
     */
    protected function _validate_table_extra_index( $index_arr )
    {
        $def_values = $this->_default_table_extra_index_arr();
        if( empty( $index_arr ) || !is_array( $index_arr ) )
            return $def_values;

        return self::validate_array( $index_arr, $def_values );
    }

    /**
     * Retrieve table structure as defined in database
     *
     * @param bool|array $params Flow parameters
     *
     * @return bool|array Return flow table structure definition as array
     */
    public function get_definition( $params = false )
    {
        if( !($params = $this->fetch_default_flow_params( $params )) )
        {
            $this->set_error( self::ERR_MODEL_FIELDS, self::_t( 'Failed validating flow parameters.' ) );
            return false;
        }

        if( empty( $this->_definition[$params['table_name']] ) )
            return false;

        return $this->_definition[$params['table_name']];
    }

    /**
     * Get a list of all tables for this model
     * @return array
     */
    final protected function get_all_table_names()
    {
        if( !empty( $this->model_tables_arr ) )
            return $this->model_tables_arr;

        $tables_arr = $this->get_table_names();
        $instance_id = $this->instance_id();

        $hook_params = [];
        $hook_params['instance_id'] = $instance_id;
        $hook_params['tables_arr'] = $tables_arr;

        if( (($extra_tables_arr = PHS::trigger_hooks( self::HOOK_TABLES.'_'.$instance_id, $hook_params ))
                || ($extra_tables_arr = PHS::trigger_hooks( self::HOOK_TABLES, $hook_params )))
         && is_array( $extra_tables_arr ) && !empty( $extra_tables_arr['tables_arr'] ) )
            $tables_arr = self::array_merge_unique_values( $extra_tables_arr['tables_arr'], $tables_arr );

        $this->model_tables_arr = $tables_arr;

        return $tables_arr;
    }

    /**
     * Default hook parameters
     * @return array|bool
     */
    final public static function default_table_fields_hook_args()
    {
        return PHS_Hooks::hook_args_definition( [
            'model_id' => '',
            'flow_params' => [],
            'fields_arr' => [],
       ] );
    }

    /**
     * @param array $flow_params
     *
     * @return array|bool
     */
    private function _all_fields_definition( $flow_params )
    {
        $this->reset_error();

        if( !($flow_params = $this->fetch_default_flow_params( $flow_params )) )
        {
            $this->set_error( self::ERR_MODEL_FIELDS, self::_t( 'Failed validating flow parameters.' ) );
            return false;
        }

        $fields_arr = $this->fields_definition( $flow_params );
        $instance_id = $this->instance_id();
        $plugin_instance_id = false;
        if( ($plugin_obj = $this->get_plugin_instance()) )
            $plugin_instance_id = $plugin_obj->instance_id();

        $hook_params = self::default_table_fields_hook_args();
        $hook_params['model_id'] = $instance_id;
        $hook_params['flow_params'] = $flow_params;
        $hook_params['fields_arr'] = $fields_arr;

        // Plugin level customization
        if( !empty( $plugin_instance_id )
         && ($extra_fields_arr = PHS::trigger_hooks( self::HOOK_TABLE_FIELDS.'_'.$plugin_instance_id, $hook_params )) )
            $fields_arr = self::merge_array_assoc( $extra_fields_arr['fields_arr'], $fields_arr );

        // Model level customization
        if( ($extra_fields_arr = PHS::trigger_hooks( self::HOOK_TABLE_FIELDS.'_'.$instance_id, $hook_params )) )
            $fields_arr = self::merge_array_assoc( $extra_fields_arr['fields_arr'], $fields_arr );

        // Table level customization
        if( ($extra_fields_arr = PHS::trigger_hooks( self::HOOK_TABLE_FIELDS, $hook_params )) )
            $fields_arr = self::merge_array_assoc( $extra_fields_arr['fields_arr'], $fields_arr );

        return $fields_arr;
    }

    /**
     * Populate model tables structures array with definition from model (not from database)
     *
     * @return bool True on success, false on failure
     */
    private function _validate_tables_definition()
    {
        if( !($all_tables_arr = $this->get_all_table_names())
         || !is_array( $all_tables_arr ) )
            return false;

        foreach( $all_tables_arr as $table_name )
        {
            if( !($flow_params = $this->fetch_default_flow_params( [ 'table_name' => $table_name ] )) )
            {
                $this->set_error( self::ERR_MODEL_FIELDS, self::_t( 'Couldn\'t fetch flow parameters for table %s.', $table_name ) );
                return false;
            }

            if( !$this->_validate_definition( $flow_params ) )
                return false;
        }

        return true;
    }

    /**
     * Validate a single table definition (provided in flow parameters)
     *
     * @param bool|array $params Flow parameters
     *
     * @return bool True on success, false on failure
     */
    private function _validate_definition( $params = false )
    {
        if( !($params = $this->fetch_default_flow_params( $params )) )
        {
            $this->set_error( self::ERR_MODEL_FIELDS, self::_t( 'Failed validating flow parameters.' ) );
            return false;
        }

        if( !empty( $this->_definition[$params['table_name']] ) )
            return true;

        if( !($model_fields = $this->_all_fields_definition( $params ))
         || !is_array( $model_fields ) )
        {
            $this->set_error( self::ERR_MODEL_FIELDS, self::_t( 'Invalid fields definition for table %s.', $params['table_name'] ) );
            return false;
        }

        $this->_definition[$params['table_name']] = [];
        foreach( $model_fields as $field_name => $field_arr )
        {
            if( $field_name === self::T_DETAILS_KEY )
            {
                $this->_definition[$params['table_name']][$field_name] = $this->_validate_table_details( $field_arr );
                continue;
            }

            if( $field_name === self::EXTRA_INDEXES_KEY )
            {
                $this->_definition[$params['table_name']][$field_name] = $this->_validate_table_extra_indexes( $field_arr );
                continue;
            }

            if( !($new_field_arr = $this->_validate_field( $field_arr )) )
            {
                $this->set_error( self::ERR_MODEL_FIELDS, self::_t( 'Field %s has an invalid definition.', $field_name ) );
                return false;
            }

            $this->_definition[$params['table_name']][$field_name] = $new_field_arr;
        }

        if( empty( $this->_definition[$params['table_name']][self::T_DETAILS_KEY] ) )
            $this->_definition[$params['table_name']][self::T_DETAILS_KEY] = $this->_default_table_details_arr();
        if( empty( $this->_definition[$params['table_name']][self::EXTRA_INDEXES_KEY] ) )
            $this->_definition[$params['table_name']][self::EXTRA_INDEXES_KEY] = [];

        return true;
    }

    public function __construct( $instance_details = false )
    {
        parent::__construct( $instance_details );

        $this->_validate_tables_definition();
    }

    /**
     * Populate missing flow parameters in provided flow
     *
     * @param bool|array $params Flow parameters
     *
     * @return array|bool Complete flow parameters or false on failure
     */
    public function fetch_default_flow_params( $params = false )
    {
        if( empty( $params ) || !is_array( $params ) )
            $params = [];

        if( empty( $params['table_name'] ) )
            $params['table_name'] = $this->get_table_name( $params );

        if( empty( $params['table_index'] ) )
            $params['table_index'] = $this->get_primary_key( $params );

        if( !isset( $params['db_connection'] ) )
            $params['db_connection'] = $this->get_db_connection( $params );

        $params['db_driver'] = $this->get_model_driver();

        if( empty( $params['table_index'] ) || empty( $params['table_name'] ) || !isset( $params['db_connection'] )
         || !($all_tables = $this->get_all_table_names())
         || !in_array( $params['table_name'], $all_tables, true ) )
            return false;

        return $params;
    }

    /**
     * Retrieve a data array that should be a structure copy of a record retrieved from table definition with default/[empty|void] values
     *
     * @param bool|array $params Flow parameters
     *
     * @return array|bool Empty data array or false on failure
     */
    public function get_empty_data( $params = false )
    {
        $this->reset_error();

        if( !($params = $this->fetch_default_flow_params( $params ))
         || !($table_fields = $this->get_definition( $params ))
         || !is_array( $table_fields ) )
        {
            $this->set_error( self::ERR_MODEL_FIELDS, self::_t( 'Invalid table definition.' ) );
            return false;
        }

        $data_arr = [];
        foreach( $table_fields as $field_name => $field_details )
        {
            if( $field_name === self::T_DETAILS_KEY
             || $field_name === self::EXTRA_INDEXES_KEY )
                continue;

            if( isset( $field_details['default'] ) )
                $data_arr[$field_name] = $field_details['default'];
            else
                $data_arr[$field_name] = $this->_validate_field_value( 0, $field_name, $field_details );
        }

        $hook_params = PHS_Hooks::default_model_empty_data_hook_args();
        $hook_params['data_arr'] = $data_arr;
        $hook_params['flow_params'] = $params;

        if( ($hook_result = PHS::trigger_hooks( PHS_Hooks::H_MODEL_EMPTY_DATA, $hook_params ))
         && is_array( $hook_result ) && !empty( $hook_result['data_arr'] ) )
            $data_arr = self::merge_array_assoc( $data_arr, $hook_result['data_arr'] );

        return $data_arr;
    }

    /**
     * Retrieve a column definition from table definition structure
     *
     * @param string $field Column name
     * @param bool|array $params Flow parameters
     *
     * @return bool|array|null Return column definition as array, null if column is not in table structure definition or false on failure
     */
    public function table_field_details( $field, $params = false )
    {
        $this->reset_error();

        $table = false;
        if( strpos( $field, '.' ) !== false )
            list( $table, $field ) = explode( '.', $field, 2 );

        if( empty( $params ) || !is_array( $params ) )
            $params = [];

        $params['table_name'] = $table;

        if( !($params = $this->fetch_default_flow_params( $params )) )
        {
            $this->set_error( self::ERR_MODEL_FIELDS, self::_t( 'Failed validating flow parameters.' ) );
            return false;
        }

        if( !($table_fields = $this->get_definition( $params ))
         || !is_array( $table_fields ) )
        {
            $this->set_error( self::ERR_MODEL_FIELDS, self::_t( 'Invalid table definition.' ) );
            return false;
        }

        if( empty( $table_fields[$field] ) || !is_array( $table_fields[$field] ) )
            return null;

        return $table_fields[$field];
    }

    /**
     * When inserting or editing records in database, this method will normalize and validate provided data and give an array of values that should be used
     * in insert or edit call
     *
     * @param array $params Flow parameters
     *
     * @return array|bool Validated data structure or false on failure
     */
    protected function validate_data_for_fields( $params )
    {
        $this->reset_error();

        if( !($table_fields = $this->get_definition( $params ))
         || !is_array( $table_fields ) )
        {
            $this->set_error( self::ERR_MODEL_FIELDS, self::_t( 'Invalid table definition.' ) );
            return false;
        }

        if( empty( $params['action'] )
         || !in_array( $params['action'], [ 'insert', 'edit' ] ) )
            $params['action'] = 'insert';

        $hook_params = PHS_Hooks::default_model_validate_data_fields_hook_args();
        $hook_params['flow_params'] = $params;
        $hook_params['table_fields'] = $table_fields;

        if( ($trigger_result = PHS::trigger_hooks( PHS_Hooks::H_MODEL_VALIDATE_DATA_FIELDS, $hook_params ))
         && is_array( $trigger_result ) )
        {
            if( !empty( $trigger_result['flow_params'] ) && is_array( $trigger_result['flow_params'] ) )
                $params = self::merge_array_assoc( $params, $trigger_result['flow_params'] );
            if( !empty( $trigger_result['table_fields'] ) && is_array( $trigger_result['table_fields'] ) )
                $table_fields = self::merge_array_assoc( $table_fields, $trigger_result['table_fields'] );
        }

        $validated_fields = [];
        $data_arr = [];
        $has_raw_fields = false;
        foreach( $table_fields as $field_name => $field_details )
        {
            if( empty( $field_details['editable'] )
             && $params['action'] === 'edit' )
                continue;

            if( array_key_exists( $field_name, $params['fields'] ) )
            {
                // we can pass raw values (see quick_edit or quick_insert)
                if( !is_array( $params['fields'][$field_name] ) )
                    $field_value = $this->_validate_field_value( $params['fields'][$field_name], $field_name, $field_details );

                else
                {
                    $has_raw_fields = true;
                    $field_value = $params['fields'][$field_name];

                    if( empty( $params['fields'][$field_name]['raw_field'] )
                     && array_key_exists( 'value', $params['fields'][$field_name] ) )
                        $field_value['value'] = $this->_validate_field_value( $params['fields'][$field_name]['value'], $field_name, $field_details );
                }

                $data_arr[$field_name] = $field_value;
                $validated_fields[] = $field_name;
            } elseif( isset( $field_details['default'] )
                   && $params['action'] === 'insert' )
                // When editing records only passed fields will be saved in database...
                $data_arr[$field_name] = $field_details['default'];
        }

        $return_arr = [];
        $return_arr['has_raw_fields'] = $has_raw_fields;
        $return_arr['data_arr'] = $data_arr;
        $return_arr['validated_fields'] = $validated_fields;

        return $return_arr;
    }

    /**
     * @param array $constrain_arr Conditional db fields
     * @param array|bool $params Parameters in the flow
     *
     * @return array|false|null Returns single record as array (first matching conditions), array of records matching conditions or acts as generator
     */
    public function get_details_fields( $constrain_arr, $params = false )
    {
        if( !($params = $this->fetch_default_flow_params( $params )) )
            return false;

        return $this->_get_details_fields_for_model( $constrain_arr, $params );
    }

    /**
     * Retrieve one record from database by it's primary key
     *
     * @param string|int $id Id of record we want to get from database
     * @param bool|array $params Flow parameters
     *
     * @return array|bool Record from database in an array structure or false on error
     */
    public function get_details( $id, $params = false )
    {
        if( !($params = $this->fetch_default_flow_params( $params ))
         || !($id = $this->prepare_primary_key( $id, $params )) )
            return false;

        return $this->_get_details_for_model( $id, $params );
    }

    /**
     * Provided a primary key or a data array structure, return data array structure from database.
     * If provided $item_data is an array, make sure it has as key primary key defined in table structure.
     * If $item_data is a primary key, query database and return data array structure from database.
     *
     * @param int|string|array $item_data Data array or primary key in database
     * @param bool|array $params Flow parameters
     *
     * @return array|bool Data array structure or false on failure
     */
    public function data_to_array( $item_data, $params = false )
    {
        if( empty( $item_data )
         || !($params = $this->fetch_default_flow_params( $params )) )
            return false;

        $id = 0;
        $item_arr = false;
        if( is_array( $item_data ) )
        {
            if( !empty( $item_data[$params['table_index']] ) )
                $id = (int)$item_data[$params['table_index']];
            $item_arr = $item_data;
        } else
            $id = $this->prepare_primary_key( $item_data );

        if( empty( $id ) && (!is_array( $item_arr ) || empty( $item_arr[$params['table_index']] )) )
            return false;

        if( empty( $item_arr ) )
            $item_arr = $this->get_details( $id, $params );

        if( empty( $item_arr ) || !is_array( $item_arr ) )
            return false;

        return $item_arr;
    }

    /**
     * Install model
     *
     * @return bool True on success or false on failure
     */
    final public function install()
    {
        $this->reset_error();

        if( !($plugins_model_id = self::generate_instance_id( self::INSTANCE_TYPE_MODEL, 'plugins' )) )
        {
            $this->set_error( self::ERR_INSTALL, self::_t( 'Couldn\'t obtain plugins model id.' ) );
            return false;
        }

        if( !($this_instance_id = $this->instance_id()) )
        {
            $this->set_error( self::ERR_INSTALL, self::_t( 'Couldn\'t obtain current model id.' ) );
            return false;
        }

        PHS_Logger::logf( 'Installing model ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

        /** @var \phs\system\core\models\PHS_Model_Plugins $plugins_model */
        if( $this_instance_id === $plugins_model_id )
        {
            $plugins_model = $this;

            $this->install_tables();
        } else
        {
            if( !$this->_load_plugins_instance() )
            {
                PHS_Logger::logf( '!!! Error instantiating plugins model. ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

                if( !$this->has_error() )
                    $this->set_error( self::ERR_INSTALL, self::_t( 'Error instantiating plugins model.' ) );

                return false;
            }

            $plugins_model = $this->_plugins_instance;

            if( !$plugins_model->check_install_plugins_db() )
            {
                if( $plugins_model->has_error() )
                    $this->copy_error( $plugins_model );
                else
                    $this->set_error( self::ERR_INSTALL, self::_t( 'Error installing plugins model.' ) );

                PHS_Logger::logf( '!!! Error ['.$this->get_error_message().'] ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

                return false;
            }
        }

        // This will only create non-existing tables...
        if( $this_instance_id !== $plugins_model_id
         && !$this->install_tables() )
            return false;

        $plugin_details = [];
        $plugin_details['instance_id'] = $this_instance_id;
        $plugin_details['plugin'] = $this->instance_plugin_name();
        $plugin_details['type'] = $this->instance_type();
        $plugin_details['is_core'] = ($this->instance_is_core() ? 1 : 0);
        $plugin_details['settings'] = PHS_Line_params::to_string( $this->get_default_settings() );
        $plugin_details['status'] = PHS_Model_Plugins::STATUS_INSTALLED;
        $plugin_details['version'] = $this->get_model_version();

        if( !($db_details = $plugins_model->update_db_details( $plugin_details, $this->get_all_settings_keys_to_obfuscate() ))
         || empty( $db_details['new_data'] ) )
        {
            if( $plugins_model->has_error() )
                $this->copy_error( $plugins_model );
            else
                $this->set_error( self::ERR_INSTALL, self::_t( 'Error saving plugin details to database.' ) );

            PHS_Logger::logf( '!!! Error ['.$this->get_error_message().'] ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

            return false;
        }

        $plugin_arr = $db_details['new_data'];
        $old_plugin_arr = (!empty( $db_details['old_data'] )?$db_details['old_data']:false);

        if( !empty( $old_plugin_arr ) )
        {
            // Performs any necessary actions when updating model from old version to new version
            if( version_compare( $old_plugin_arr['version'], $plugin_arr['version'], '<' ) )
            {
                PHS_Logger::logf( 'Calling update method from version ['.$old_plugin_arr['version'].'] to version ['.$plugin_arr['version'].'] ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

                // Installed version is bigger than what we already had in database... update...
                if( !$this->update( $old_plugin_arr['version'], $plugin_arr['version'] ) )
                {
                    PHS_Logger::logf( '!!! Update failed ['.$this->get_error_message().']', PHS_Logger::TYPE_MAINTENANCE );

                    return false;
                }

                PHS_Logger::logf( 'Update with success ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );
            }
        }

        PHS_Logger::logf( 'DONE installing model ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

        return $plugin_arr;
    }

    /**
     * Install model tables
     *
     * @return bool True on success or false on failure
     */
    final public function install_tables()
    {
        $this->reset_error();

        if( !($model_id = $this->instance_id()) )
            return false;

        if( empty( $this->_definition ) || !is_array( $this->_definition ) )
            return true;

        PHS_Logger::logf( 'Installing tables for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE );

        foreach( $this->_definition as $table_name => $table_definition )
        {
            if( !($flow_params = $this->fetch_default_flow_params( [ 'table_name' => $table_name ] ))
             || !($full_table_name = $this->get_flow_table_name( $flow_params )) )
            {
                PHS_Logger::logf( 'Couldn\'t get flow parameters for table ['.$table_name.'], model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE );
                continue;
            }

            if( !$this->install_table( $flow_params ) )
            {
                if( !$this->has_error() )
                    PHS_Logger::logf( 'Couldn\'t generate table ['.$full_table_name.'], model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE );
            }
        }

        // Reset any errors related to generating tables...
        $this->reset_error();

        PHS_Logger::logf( 'DONE Installing tables for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE );

        return true;
    }

    /**
     * Install a specific model table provided in flow parameters
     *
     * @param array $flow_params Flow parameters
     *
     * @return bool True on success or false on failure
     */
    final protected function install_table( $flow_params )
    {
        $this->reset_error();

        return $this->_install_table_for_model( $flow_params );
    }

    /**
     * Update model tables
     *
     * @param array $params_arr Functionality parameters
     *
     * @return bool True on success or false on failure
     */
    final public function update_tables( $params_arr )
    {
        $this->reset_error();

        if( !($model_id = $this->instance_id()) )
            return false;

        if( empty( $this->_definition ) || !is_array( $this->_definition ) )
            return true;

        if( empty( $params_arr ) || !is_array( $params_arr ) )
            $params_arr = [];

        if( empty( $params_arr['created_tables'] ) || !is_array( $params_arr['created_tables'] ) )
            $params_arr['created_tables'] = [];

        PHS_Logger::logf( 'Updating tables for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE );

        foreach( $this->_definition as $table_name => $table_definition )
        {
            if( !empty( $params_arr['created_tables'] )
             && in_array( $table_name, $params_arr['created_tables'], true ) )
                continue;

            if( !($flow_params = $this->fetch_default_flow_params( [ 'table_name' => $table_name ] ))
             || !($full_table_name = $this->get_flow_table_name( $flow_params )) )
            {
                PHS_Logger::logf( 'Couldn\'t get flow parameters for model table ['.$table_name.'], model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE );
                continue;
            }

            if( !$this->update_table( $flow_params ) )
            {
                if( !$this->has_error() )
                {
                    $this->set_error( self::ERR_UPDATE, self::_t( 'Couldn\'t update table %s, model %s.', $full_table_name, $model_id ) );
                    PHS_Logger::logf( 'Couldn\'t update table [' . $full_table_name . '], model [' . $model_id . ']', PHS_Logger::TYPE_MAINTENANCE );
                }

                PHS_Logger::logf( 'FAILED Updating tables for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE );

                return false;
            }
        }

        // Reset any errors related to generating tables...
        $this->reset_error();

        PHS_Logger::logf( 'DONE Updating tables for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE );

        return true;
    }

    /**
     * Install missing tables when doing updates on this model
     *
     * @return bool|array Array of tables created on success or false on failure
     */
    final public function update_missing_tables()
    {
        $this->reset_error();

        if( !($model_id = $this->instance_id()) )
            return false;

        if( empty( $this->_definition ) || !is_array( $this->_definition ) )
            return [];

        PHS_Logger::logf( 'Installing missing tables for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE );

        $created_tables = [];
        foreach( $this->_definition as $table_name => $table_definition )
        {
            if( !($flow_params = $this->fetch_default_flow_params( [ 'table_name' => $table_name ] ))
             || !($full_table_name = $this->get_flow_table_name( $flow_params )) )
            {
                PHS_Logger::logf( 'Couldn\'t get flow parameters for model table ['.$table_name.'], model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE );
                continue;
            }

            if( $this->check_table_exists( $flow_params ) )
                continue;

            $created_tables[] = $table_name;

            if( !$this->update_missing_table( $flow_params ) )
            {
                if( !$this->has_error() )
                {
                    $this->set_error( self::ERR_UPDATE, self::_t( 'Couldn\'t update table %s, model %s.', $full_table_name, $model_id ) );
                    PHS_Logger::logf( 'Couldn\'t install missing table [' . $full_table_name . '], model [' . $model_id . ']', PHS_Logger::TYPE_MAINTENANCE );
                }

                PHS_Logger::logf( 'FAILED installing missing tables for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE );

                return false;
            }
        }

        // Reset any errors related to generating tables...
        $this->reset_error();

        PHS_Logger::logf( 'DONE installing missing tables for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE );

        return $created_tables;
    }

    /**
     * Update a specific model table provided in flow parameters
     *
     * @param array $flow_params Flow parameters
     *
     * @return bool True on success or false on failure
     */
    final protected function update_table( $flow_params )
    {
        $this->reset_error();

        return $this->_update_table_for_model( $flow_params );
    }

    /**
     * Install a missing table provided in flow parameters when updating model
     *
     * @param array $flow_params Flow parameters
     *
     * @return bool True on success or false on failure
     */
    final protected function update_missing_table( $flow_params )
    {
        $this->reset_error();

        return $this->_update_missing_table_for_model( $flow_params );
    }

    /**
     * Uninstall model
     *
     * @return bool True on success or false on failure
     */
    final public function uninstall()
    {
        $this->reset_error();

        if( !($plugins_model_id = self::generate_instance_id( self::INSTANCE_TYPE_MODEL, 'plugins' )) )
        {
            $this->set_error( self::ERR_UNINSTALL, self::_t( 'Couldn\'t obtain plugins model id.' ) );
            return false;
        }

        if( !($this_instance_id = $this->instance_id()) )
        {
            $this->set_error( self::ERR_UNINSTALL, self::_t( 'Couldn\'t obtain current model id.' ) );
            return false;
        }

        PHS_Logger::logf( 'Uninstalling model ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

        if( $this_instance_id === $plugins_model_id )
        {
            $this->set_error( self::ERR_UNINSTALL, self::_t( 'Plugins model cannot be uninstalled.' ) );
            return false;
        }

        if( !$this->_load_plugins_instance() )
        {
            PHS_Logger::logf( '!!! Error instantiating plugins model. ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

            $this->set_error( self::ERR_UNINSTALL, self::_t( 'Error instantiating plugins model.' ) );
            return false;
        }

        $check_arr = [];
        $check_arr['instance_id'] = $this_instance_id;

        db_supress_errors( $this->_plugins_instance->get_db_connection() );
        if( !($db_details = $this->_plugins_instance->get_details_fields( $check_arr ))
         || empty( $db_details['type'] )
         || $db_details['type'] !== self::INSTANCE_TYPE_MODEL )
        {
            db_restore_errors_state( $this->_plugins_instance->get_db_connection() );

            PHS_Logger::logf( 'Model doesn\'t seem to be installed. ['.$this_instance_id.']', PHS_Logger::TYPE_MAINTENANCE );

            return true;
        }

        db_restore_errors_state( $this->_plugins_instance->get_db_connection() );

        PHS_Logger::logf( 'Calling uninstall tables ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

        // First delete tables so in case it fails we can repeat the process...
        if( !$this->uninstall_tables() )
            return false;

        PHS_Logger::logf( 'DONE calling uninstall tables ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

        if( !$this->_plugins_instance->hard_delete( $db_details ) )
        {
            if( $this->_plugins_instance->has_error() )
                $this->copy_error( $this->_plugins_instance );
            else
                $this->set_error( self::ERR_UNINSTALL, self::_t( 'Error hard-deleting model from database.' ) );

            PHS_Logger::logf( '!!! Error ['.$this->get_error_message().'] ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

            return false;
        }

        PHS_Logger::logf( 'DONE uninstalling model ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

        return $db_details;
    }

    /**
     * This method will hard-delete tables in database defined by this model.
     * If you don't want to drop tables when model gets uninstalled overwrite this method with an empty method which returns true.
     *
     * @return bool Returns true if all tables were dropped or false on error
     */
    final public function uninstall_tables()
    {
        $this->reset_error();

        if( empty( $this->_definition ) || !is_array( $this->_definition )
         || !($flow_params = $this->fetch_default_flow_params()) )
            return true;

        PHS_Logger::logf( 'Uninstalling tables for model ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

        foreach( $this->_definition as $table_name => $table_definition )
        {
            $flow_params['table_name'] = $table_name;

            if( !$this->uninstall_table( $flow_params ) )
            {
                PHS_Logger::logf( '!!! Error uninstalling tables for model ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

                $this->set_error( self::ERR_TABLE_GENERATE, self::_t( 'Error dropping table %s.', $table_name ) );
                return false;
            }
        }

        PHS_Logger::logf( 'DONE uninstalling tables for model ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

        return true;
    }

    /**
     * This method will hard-delete a table from database defined by this model.
     * If you don't want to drop a specific table when model gets uninstalled overwrite this method with an empty method which returns true.
     *
     * @param array $flow_params Flow parameters
     *
     * @return bool Returns true if all tables were dropped or false on error
     */
    final public function uninstall_table( $flow_params )
    {
        $this->reset_error();

        if( empty( $this->_definition ) || !is_array( $this->_definition )
         || !($flow_params = $this->fetch_default_flow_params( $flow_params ))
         || !($full_table_name = $this->get_flow_table_name( $flow_params )) )
            return true;

        PHS_Logger::logf( 'Uninstalling table ['.$full_table_name.'] for model ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

        if( !$this->_uninstall_table_for_model( $flow_params ) )
        {
            PHS_Logger::logf( 'FAILED uninstalling table ['.$full_table_name.'] for model ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

            if( !$this->has_error() )
                $this->set_error( self::ERR_UNINSTALL_TABLE, self::_t( 'Error dropping table %s.', $full_table_name ) );

            return false;
        }

        PHS_Logger::logf( 'DONE uninstalling table ['.$full_table_name.'] for model ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

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
    public function update( $old_version, $new_version )
    {
        $this->reset_error();

        if( !($this_instance_id = $this->instance_id()) )
        {
            PHS_Logger::logf( '!!! Couldn\'t obtain model instance ID.', PHS_Logger::TYPE_MAINTENANCE );

            $this->set_error( self::ERR_UPDATE, self::_t( 'Couldn\'t obtain current plugin id.' ) );
            return false;
        }

        PHS_Maintenance::output( '['.$this->instance_plugin_name().']['.$this->instance_name().'] '.
                                 'Updating model from ['.$old_version.'] to ['.$new_version.']...'.
                                 ($this->dynamic_table_structure()?' (Dynamic structure)':'') );

        if( !$this->custom_update( $old_version, $new_version ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_UPDATE, self::_t( 'Model custom update functionality failed.' ) );

            PHS_Maintenance::output( '['.$this->instance_plugin_name().']['.$this->instance_name().'] !!! Error in model custom update functionality: '.$this->get_error_message() );

            return false;
        }

        if( !$this->_load_plugins_instance() )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_UPDATE, self::_t( 'Error instantiating plugins model.' ) );

            PHS_Maintenance::output( '['.$this->instance_plugin_name().']['.$this->instance_name().'] !!! Error instantiating plugins model.' );

            return false;
        }

        // This will only create non-existing tables...
        if( ($created_tables = $this->update_missing_tables()) === false )
            return false;

        $custom_after_missing_tables_updates_params = [ 'created_tables' => $created_tables, ];

        if( !$this->custom_after_missing_tables_update( $old_version, $new_version, $custom_after_missing_tables_updates_params ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_UPDATE, self::_t( 'Model custom after missing tables update functionality failed.' ) );

            PHS_Maintenance::output( '['.$this->instance_plugin_name().']['.$this->instance_name().'] !!! Error in model custom after missing tables update functionality: '.$this->get_error_message() );

            return false;
        }

        $update_tables_params = [ 'created_tables' => $created_tables, ];

        // Update table structure
        if( !$this->update_tables( $update_tables_params ) )
            return false;

        if( !$this->custom_after_update( $old_version, $new_version ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_UPDATE, self::_t( 'Model custom after update functionality failed.' ) );

            PHS_Maintenance::output( '['.$this->instance_plugin_name().']['.$this->instance_name().'] !!! Error in model custom after update functionality: '.$this->get_error_message() );

            return false;
        }

        $plugin_details = [];
        $plugin_details['instance_id'] = $this_instance_id;
        $plugin_details['plugin'] = $this->instance_plugin_name();
        $plugin_details['type'] = $this->instance_type();
        $plugin_details['is_core'] = ($this->instance_is_core() ? 1 : 0);
        $plugin_details['version'] = $this->get_model_version();

        if( !($db_details = $this->_plugins_instance->update_db_details( $plugin_details, $this->get_all_settings_keys_to_obfuscate() ))
         || empty( $db_details['new_data'] ) )
        {
            if( $this->_plugins_instance->has_error() )
                $this->copy_error( $this->_plugins_instance );
            else
                $this->set_error( self::ERR_UPDATE, self::_t( 'Error saving model details to database.' ) );

            PHS_Maintenance::output( '['.$this->instance_plugin_name().']['.$this->instance_name().'] !!! Error updating model details in database: '.$this->get_error_message() );

            return false;
        }

        PHS_Maintenance::output( '['.$this->instance_plugin_name().']['.$this->instance_name().'] DONE Updating model' );

        return $db_details['new_data'];
    }
}
