<?php

namespace phs\libraries;

use \phs\PHS;
use \phs\system\core\models\PHS_Model_Plugins;

abstract class PHS_Model_Core_Base extends PHS_Signal_and_slot
{
    // DON'T OVERWRITE THIS CONSTANT. IT REPRESENTS BASE MODEL CLASS VERSION
    const MODEL_BASE_VERSION = '1.0.0';

    const ERR_MODEL_FIELDS = 1000, ERR_TABLE_GENERATE = 1001, ERR_INSTALL = 1002,
          ERR_INSERT = 1003, ERR_EDIT = 1004, ERR_DELETE_BY_INDEX = 1005;

    const HOOK_RAW_PARAMETERS = 'phs_model_raw_parameters', HOOK_INSERT_BEFORE_DB = 'phs_model_insert_before_db',
          HOOK_TABLES = 'phs_model_tables', HOOK_TABLE_FIELDS = 'phs_model_table_fields', HOOK_HARD_DELETE = 'phs_model_hard_delete';

    const SIGNAL_INSERT = 'phs_model_insert', SIGNAL_EDIT = 'phs_model_edit', SIGNAL_HARD_DELETE = 'phs_model_hard_delete',
          SIGNAL_INSTALL = 'phs_model_install', SIGNAL_UPDATE = 'phs_model_update', SIGNAL_FORCE_INSTALL = 'phs_model_force_install';

    const FTYPE_UNKNOWN = 0,
          FTYPE_TINYINT = 1, FTYPE_SMALLINT = 2, FTYPE_MEDIUMINT = 3, FTYPE_INT = 4, FTYPE_BIGINT = 5, FTYPE_DECIMAL = 6, FTYPE_FLOAT = 7, FTYPE_DOUBLE = 8, FTYPE_REAL = 9,
          FTYPE_DATE = 10, FTYPE_DATETIME = 11, FTYPE_TIMESTAMP = 12,
          FTYPE_VARCHAR = 13, FTYPE_TEXT = 14, FTYPE_MEDIUMTEXT = 15, FTYPE_LONGTEXT = 16,
          FTYPE_BINARY = 17, FTYPE_VARBINARY = 18,
          FTYPE_TINYBLOB = 19, FTYPE_MEDIUMBLOB = 20, FTYPE_BLOB = 21, FTYPE_LONGBLOB = 22,
          FTYPE_ENUM = 23;

    private static $FTYPE_ARR = array(
        self::FTYPE_TINYINT => array( 'title' => 'tinyint', 'default_length' => 4, 'default_value' => 0 ),
        self::FTYPE_SMALLINT => array( 'title' => 'smallint', 'default_length' => 6, 'default_value' => 0, ),
        self::FTYPE_MEDIUMINT => array( 'title' => 'mediumint', 'default_length' => 9, 'default_value' => 0, ),
        self::FTYPE_INT => array( 'title' => 'int', 'default_length' => 11, 'default_value' => 0, ),
        self::FTYPE_BIGINT => array( 'title' => 'bigint', 'default_length' => 20, 'default_value' => 0, ),
        self::FTYPE_DECIMAL => array( 'title' => 'decimal', 'default_length' => '5,2', 'default_value' => 0, ),
        self::FTYPE_FLOAT => array( 'title' => 'float', 'default_length' => '5,2', 'default_value' => 0, ),
        self::FTYPE_DOUBLE => array( 'title' => 'double', 'default_length' => '5,2', 'default_value' => 0, ),

        self::FTYPE_DATE => array( 'title' => 'date', 'default_length' => null, 'default_value' => self::DATE_EMPTY, ),
        self::FTYPE_DATETIME => array( 'title' => 'datetime', 'default_length' => 0, 'default_value' => self::DATETIME_EMPTY, ),
        self::FTYPE_TIMESTAMP => array( 'title' => 'timestamp', 'default_length' => 0, 'default_value' => 0, ),

        self::FTYPE_VARCHAR => array( 'title' => 'varchar', 'default_length' => 255, 'default_value' => '', ),
        self::FTYPE_TEXT => array( 'title' => 'text', 'default_length' => null, 'default_value' => null, ),
        self::FTYPE_MEDIUMTEXT => array( 'title' => 'mediumtext', 'default_length' => null, 'default_value' => null, ),
        self::FTYPE_LONGTEXT => array( 'title' => 'longtext', 'default_length' => null, 'default_value' => null, ),

        self::FTYPE_BINARY => array( 'title' => 'binary', 'default_length' => 255, 'default_value' => null, ),
        self::FTYPE_VARBINARY => array( 'title' => 'varbinary', 'default_length' => 255, 'default_value' => null, ),

        self::FTYPE_TINYBLOB => array( 'title' => 'tinyblob', 'default_length' => null, 'default_value' => null, ),
        self::FTYPE_MEDIUMBLOB => array( 'title' => 'mediumblob', 'default_length' => null, 'default_value' => null, ),
        self::FTYPE_BLOB => array( 'title' => 'blob', 'default_length' => null, 'default_value' => null, ),
        self::FTYPE_LONGBLOB => array( 'title' => 'longblob', 'default_length' => null, 'default_value' => null, ),

        self::FTYPE_ENUM => array( 'title' => 'enum', 'default_length' => '', 'default_value' => null, ),
    );

    const DATE_EMPTY = '0000-00-00', DATETIME_EMPTY = '0000-00-00 00:00:00',
          DATE_DB = 'Y-m-d', DATETIME_DB = 'Y-m-d H:i:s';

    const T_DETAILS_KEY = '{details}';

    protected static $_definition = array();

    private $model_tables_arr = array();

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
     * @param array|false $params Parameters in the flow
     *
     * @return array Returns an array with table fields
     */
    abstract public function fields_definition( $params = false );

    /**
     * Performs any necessary actions when updating model from $old_version to $new_version
     *
     * @param string $old_version Old version of model
     * @param string $new_version New version of model
     *
     * @return bool true on success, false on failure
     */
    abstract protected function update( $old_version, $new_version );

    /**
     * @return int Should return INSTANCE_TYPE_* constant
     */
    protected function instance_type()
    {
        return self::INSTANCE_TYPE_MODEL;
    }

    /**
     * @param array|false $params Parameters in the flow
     *
     * @return false|string Returns false if model uses default database connection or connection name as string
     */
    function get_db_connection( $params = false )
    {
        return false;
    }

    /**
     * Override this function and return an array with default settings to be saved for current plugin
     *
     * @return array
     */
    public function get_default_settings()
    {
        return array();
    }

    /**
     * @return array Array with settings of plugin of current model
     */
    public function get_model_settings()
    {
        $this->reset_error();

        /** @var \phs\system\core\models\PHS_Model_Plugins $plugin_obj */
        if( !($plugin_obj = PHS::load_model( 'plugins' )) )
        {
            $this->set_error( self::ERR_INSTALL, self::_t( 'Error instantiating plugins model.' ) );
            return false;
        }

        if( !($settings_arr = $plugin_obj->get_db_settings( $this->instance_id(), $this->get_default_settings() ))
         or !is_array( $settings_arr ) )
            $this->get_default_settings();

        return $settings_arr;
    }

    /**
     * @param array|false $params Parameters in the flow
     *
     * @return string What's primary key of the table (override the method if not `id`)
     */
    function get_primary_key( $params = false )
    {
        return 'id';
    }

    /**
     * @param array|false $params Parameters in the flow
     *
     * @return string Returns table set in parameters flow or main table if no table is specified in flow
     * (table name can be passed to $params array of each method in 'table_name' index)
     */
    function get_table_name( $params = false )
    {
        if( !empty( $params ) and is_array( $params )
        and !empty( $params['table_name'] ) )
            return $params['table_name'];

        // return default table...
        return $this->get_main_table_name();
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
    protected function get_insert_prepare_params( $params )
    {
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
    protected function get_edit_prepare_params( $existing_data, $params )
    {
        return $params;
    }

    /**
     * @param array $insert_arr Data array which should be added to database
     * @param array $params Flow parameters
     */
    protected function insert_failed( $insert_arr, $params )
    {
    }

    /**
     * Called right after a database update fails.
     *
     * @param array|int $existing_data Data which already exists in database (id or full array with all database fields)
     * @param array $edit_arr Data array which should be saved in database (only fields that change)
     * @param array $params Flow parameters
     */
    protected function edit_failed( $existing_data, $edit_arr, $params )
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
     * @return array|false Returns data array added in database (with changes, if required) or false if record should be deleted from database.
     * Deleted record will be hard-deleted
     */
    protected function insert_after( $insert_arr, $params )
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
     * @return array|false Returns data array added in database (with changes, if required) or false if record should be deleted from database.
     * Deleted record will be hard-deleted
     */
    protected function edit_after( $existing_data, $edit_arr, $params )
    {
        return $existing_data;
    }

    /**
     * Called right after finding a record in database in PHS_Model_Core_Base::insert_or_edit() with provided conditions. This helps unsetting some fields which should not
     * be passed to edit function in case we execute an edit.
     *
     * @param array $existing_arr Data which already exists in database (array with all database fields)
     * @param array $constrain_arr Conditional db fields
     * @param array $params Flow parameters
     *
     * @return array Returns modified parameters (if required)
     */
    protected function insert_or_edit_editing( $existing_arr, $constrain_arr, $params )
    {
        return $params;
    }

    /**
     * Parses flow parameters if anything special should be done for listing records query and returns modified parameters array
     *
     * @param array|false $params Parameters in the flow
     *
     * @return array Flow parameters array
     */
    public function get_list_prepare_params( $params = false )
    {
        return $params;
    }

    /**
     * Parses flow parameters if anything special should be done for count query and returns modified parameters array
     *
     * @param array|false $params Parameters in the flow
     *
     * @return array Flow parameters array
     */
    public function get_count_prepare_params( $params = false )
    {
        return $params;
    }

    static public function get_field_types()
    {
        return self::$FTYPE_ARR;
    }

    static public function valid_field_type( $type )
    {
        if( empty( $type )
         or !($fields_arr = self::get_field_types())
         or empty( $fields_arr[$type] ) or !is_array( $fields_arr[$type] ) )
            return false;

        return $fields_arr[$type];
    }

    public function check_table_exists( $params = false )
    {
        $this->reset_error();

        if( !($params = $this->fetch_default_flow_params( $params )) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Failed validating flow parameters.' ) );
            return false;
        }

        if( ($qid = db_query( 'SHOW TABLES', $params['db_connection'] )) )
        {
            while( ($table_name = db_fetch_assoc( $qid )) )
            {
                if( !is_array( $table_name ) )
                    continue;

                $table_arr = array_values( $table_name );
                if( $table_arr[0] == $params['table_name'] )
                    return true;
            }
        }

        return false;
    }

    public function check_installation()
    {
        $this->reset_error();

        /** @var \phs\system\core\models\PHS_Model_Plugins $plugin_obj */
        if( !($plugin_obj = PHS::load_model( 'plugins' )) )
        {
            $this->set_error( self::ERR_INSTALL, self::_t( 'Error instantiating plugins model.' ) );
            return false;
        }

        if( !($db_details = $plugin_obj->get_db_details( $this->instance_id() )) )
        {
            $this->reset_error();

            return $this->install();
        }

        if( version_compare( $db_details['version'], $this->get_model_version(), '!=' ) )
            return $this->update( $db_details['version'], $this->get_model_version() );

        return true;
    }

    /**
     * This method hard-deletes a record from database. If additional work is required before hard-deleting record, self::HOOK_HARD_DELETE is called before deleting.
     *
     * @param array|int $existing_data Array with full database fields or index id
     * @param array|false $params Parameters in the flow
     *
     * @return bool Returns true or false depending on hard delete success
     */
    public function hard_delete( $existing_data, $params = false )
    {
        self::st_reset_error();
        $this->reset_error();

        if( !($params = $this->fetch_default_flow_params( $params ))
         or !($existing_arr = $this->data_to_array( $existing_data, $params )) )
            return false;

        $hook_params = array();
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

        $signal_params = array();
        $signal_params['existing_data'] = $existing_arr;
        $signal_params['params'] = $params;

        if( ($signal_result = $this->signal_trigger( self::SIGNAL_HARD_DELETE, $signal_params )) )
        {
            if( !empty( $signal_result['stop_process'] ) )
            {
                if( !$this->has_error() )
                    $this->set_error( self::HOOK_HARD_DELETE, self::_t( 'Delete cancelled by delete signal trigger.' ) );

                return false;
            }
        }

        // TODO: Move all queries to a higher level so we can have database connections with different drivers...
        $result = false;
        if( db_query( 'DELETE FROM `'.$params['table_name'].'` WHERE `'.$params['table_index'].'` = \''.db_escape( $existing_arr[$params['table_index']], $params['db_connection'] ).'\'', $params['db_connection'] ) )
            $result = true;

        return $result;
    }

    static public function safe_escape( $str, $char = '\'' )
    {
        return str_replace( $char, '\\'.$char, str_replace( '\\'.$char, $char, $str ) );
    }

    static public function default_field_arr()
    {
        return array(
            'type' => self::FTYPE_UNKNOWN,
            'editable' => true,
            'length' => null,
            'primary' => false,
            'auto_increment' => false,
            'index' => false,
            'default' => null,
            'nullable' => false,
            'comment' => '',
        );
    }

    static public function default_table_details_arr()
    {
        return array(
            'engine' => 'InnoDB',
            'charset' => 'utf8',
            'comment' => '',
        );
    }

    public static function validate_field( $field_arr )
    {
        if( empty( $field_arr ) or !is_array( $field_arr ) )
            $field_arr = array();

        $def_values = self::default_field_arr();
        foreach( $def_values as $key => $val )
        {
            if( !array_key_exists( $key, $field_arr ) )
                $field_arr[$key] = $val;
        }

        return $field_arr;
    }

    public static function validate_table_details( $details_arr )
    {
        if( empty( $details_arr ) or !is_array( $details_arr ) )
            $details_arr = array();

        $def_values = self::default_table_details_arr();
        foreach( $def_values as $key => $val )
        {
            if( !array_key_exists( $key, $details_arr ) )
                $details_arr[$key] = $val;
        }

        return $details_arr;
    }

    public function get_definition( $params = false )
    {
        if( !($params = $this->fetch_default_flow_params( $params )) )
        {
            $this->set_error( self::ERR_MODEL_FIELDS, self::_t( 'Failed validating flow parameters.' ) );
            return false;
        }

        if( empty( self::$_definition[$params['table_name']] ) )
            return false;

        return self::$_definition[$params['table_name']];
    }

    final protected function get_all_table_names()
    {
        if( !empty( $this->model_tables_arr ) )
            return $this->model_tables_arr;

        $tables_arr = $this->get_table_names();
        $instance_id = $this->instance_id();

        $hook_params = array();
        $hook_params['instance_id'] = $instance_id;
        $hook_params['tables_arr'] = $tables_arr;

        if( (($extra_tables_arr = PHS::trigger_hooks( self::HOOK_TABLES.'_'.$instance_id, $hook_params ))
                or ($extra_tables_arr = PHS::trigger_hooks( self::HOOK_TABLES, $hook_params )))
        and is_array( $extra_tables_arr ) and !empty( $extra_tables_arr['tables_arr'] ) )
            $tables_arr = self::array_merge_unique_values( $extra_tables_arr['tables_arr'], $tables_arr );

        $this->model_tables_arr = $tables_arr;

        return $tables_arr;
    }

    final private function all_fields_definition( $params )
    {
        $this->reset_error();

        if( !($params = $this->fetch_default_flow_params( $params )) )
        {
            $this->set_error( self::ERR_MODEL_FIELDS, self::_t( 'Failed validating flow parameters.' ) );
            return false;
        }

        $fields_arr = $this->fields_definition( $params );
        $instance_id = $this->instance_id();

        $hook_params = array();
        $hook_params['model_id'] = $instance_id;
        $hook_params['params'] = $params;
        $hook_params['fields_arr'] = $fields_arr;

        if( (($extra_fields_arr = PHS::trigger_hooks( self::HOOK_TABLE_FIELDS.'_'.$instance_id, $hook_params ))
                or ($extra_fields_arr = PHS::trigger_hooks( self::HOOK_TABLE_FIELDS, $hook_params )))
        and is_array( $extra_fields_arr ) and !empty( $extra_fields_arr['fields_arr'] ) )
            $fields_arr = array_merge( $extra_fields_arr['fields_arr'], $fields_arr );

        return $fields_arr;
    }

    private function validate_tables_definition( $params = false )
    {
        if( !($params = $this->fetch_default_flow_params( $params )) )
        {
            $this->set_error( self::ERR_MODEL_FIELDS, self::_t( 'Failed validating flow parameters.' ) );
            return false;
        }

        if( !($all_tables_arr = $this->get_all_table_names())
         or !is_array( $all_tables_arr ) )
            return false;

        foreach( $all_tables_arr as $table_name )
        {
            $params['table_name'] = $table_name;

            if( !$this->validate_definition( $params ) )
                return false;
        }

        return true;
    }

    private function validate_definition( $params = false )
    {
        if( !($params = $this->fetch_default_flow_params( $params )) )
        {
            $this->set_error( self::ERR_MODEL_FIELDS, self::_t( 'Failed validating flow parameters.' ) );
            return false;
        }

        if( !empty( self::$_definition[$params['table_name']] ) )
            return true;

        if( !($model_fields = $this->all_fields_definition( $params ))
         or !is_array( $model_fields ) )
        {
            $this->set_error( self::ERR_MODEL_FIELDS, self::_t( 'Invalid fields definition for table %s.', $params['table_name'] ) );
            return false;
        }

        self::$_definition[$params['table_name']] = array();
        foreach( $model_fields as $field_name => $field_arr )
        {
            if( $field_name == self::T_DETAILS_KEY )
            {
                self::$_definition[$params['table_name']][$field_name] = self::validate_table_details( $field_arr );
                continue;
            }

            if( empty( $field_arr['type'] )
             or !($field_details = self::valid_field_type( $field_arr['type'] )) )
            {
                $this->set_error( self::ERR_MODEL_FIELDS, self::_t( 'Field %s has an unknown type.', $field_name ) );
                return false;
            }

            $new_field_arr = self::validate_field( $field_arr );

            if( $field_details['default_length'] === null
            and isset( $new_field_arr['length'] ) )
                $new_field_arr['length'] = null;

            if( !isset( $new_field_arr['length'] )
            and isset( $field_details['default_length'] ) )
                $new_field_arr['length'] = $field_details['default_length'];

            if( $new_field_arr['default'] === null
            and isset( $field_details['default_value'] ) )
                $new_field_arr['default'] = $field_details['default_value'];

            if( !empty( $new_field_arr['primary'] ) )
            {
                $new_field_arr['editable'] = false;
                $new_field_arr['default'] = null;
            }

            self::$_definition[$params['table_name']][$field_name] = $new_field_arr;
        }

        if( empty( self::$_definition[$params['table_name']][self::T_DETAILS_KEY] ) )
            self::$_definition[$params['table_name']][self::T_DETAILS_KEY] = self::default_table_details_arr();

        return true;
    }

    function __construct( $instance_details = false )
    {
        parent::__construct( $instance_details );

        if( !$this->signal_defined( self::SIGNAL_INSTALL ) )
        {
            $signal_defaults            = array();
            $signal_defaults['version'] = '';

            $this->define_signal( self::SIGNAL_INSTALL, $signal_defaults );
        }

        if( !$this->signal_defined( self::SIGNAL_UPDATE ) )
        {
            $signal_defaults                = array();
            $signal_defaults['old_version'] = '';
            $signal_defaults['new_version'] = '';

            $this->define_signal( self::SIGNAL_UPDATE, $signal_defaults );
        }

        if( !$this->signal_defined( self::SIGNAL_FORCE_INSTALL ) )
        {
            $signal_defaults = array();

            $this->define_signal( self::SIGNAL_FORCE_INSTALL, $signal_defaults );
        }

        if( !$this->signal_defined( self::SIGNAL_HARD_DELETE ) )
        {
            $signal_defaults                  = array();
            $signal_defaults['existing_data'] = false;
            $signal_defaults['params']        = false;

            $this->define_signal( self::SIGNAL_HARD_DELETE, $signal_defaults );
        }

        $this->validate_tables_definition();
    }

    function fetch_default_flow_params( $params = false )
    {
        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['table_name'] ) )
            $params['table_name'] = $this->get_table_name( $params );

        if( empty( $params['table_index'] ) )
            $params['table_index'] = $this->get_primary_key( $params );

        if( !isset( $params['db_connection'] ) )
            $params['db_connection'] = $this->get_db_connection( $params );

        if( empty( $params['table_index'] ) or empty( $params['table_name'] ) or !isset( $params['db_connection'] )
         or !($all_tables = $this->get_all_table_names())
         or !in_array( $params['table_name'], $all_tables ) )
            return false;

        return $params;
    }

    static function validate_field_value( $value, $field_name, $field_details )
    {
        self::st_reset_error();

        if( empty( $field_name ) )
            $field_name = self::_t( 'N/A' );

        if( !($field_details = self::validate_field( $field_details ))
         or empty( $field_details['type'] )
         or !($field_type_arr = self::valid_field_type( $field_details['type'] )) )
        {
            self::st_set_error( self::ERR_MODEL_FIELDS, self::_t( 'Couldn\'t validate field %s.', $field_name ) );
            return false;
        }

        $phs_params_arr = array();
        $phs_params_arr['trim_before'] = true;

        switch( $field_details['type'] )
        {
            case self::FTYPE_TINYINT:
            case self::FTYPE_SMALLINT:
            case self::FTYPE_MEDIUMINT:
            case self::FTYPE_INT:
            case self::FTYPE_BIGINT:
                $value = PHS_params::set_type( $value, PHS_params::T_INT, $phs_params_arr );
            break;

            case self::FTYPE_DATE:
                if( empty_db_date( $value ) )
                    $value = self::DATE_EMPTY;
                else
                    $value = @date( self::DATE_DB, parse_db_date( $value ) );
            break;

            case self::FTYPE_DATETIME:
                if( empty_db_date( $value ) )
                    $value = self::DATETIME_EMPTY;
                else
                    $value = @date( self::DATETIME_DB, parse_db_date( $value ) );
            break;

            case self::FTYPE_DECIMAL:
            case self::FTYPE_FLOAT:
            case self::FTYPE_DOUBLE:

                $digits = 0;
                if( !empty( $field_details['length'] )
                and is_string( $field_details['length'] ) )
                {
                    $length_arr = explode( ',', $field_details['length'] );
                    $digits = (!empty( $length_arr[1] )?intval(trim( $length_arr[1] )):0);
                }

                $phs_params_arr['digits'] = $digits;

                $value = PHS_params::set_type( $value, PHS_params::T_FLOAT, $phs_params_arr );
            break;

            case self::FTYPE_ENUM:

                $values_arr = array();
                if( !empty( $field_details['length'] )
                and is_string( $field_details['length'] ) )
                {
                    $values_arr = explode( ',', $field_details['length'] );
                    $trim_value = trim( $value );
                    $lower_value = strtolower( $trim_value );
                    $value_valid = false;
                    foreach( $values_arr as $possible_value )
                    {
                        $trim_possible_value = trim( $value );
                        $lower_possible_value = strtolower( $trim_value );

                        if( $value == $possible_value
                         or $trim_value == $trim_possible_value
                         or $lower_value == $lower_possible_value )
                        {
                            $value_valid = true;
                            break;
                        }
                    }

                    if( empty( $value_valid ) )
                    {
                        self::st_set_error( self::ERR_MODEL_FIELDS, self::_t( 'Field %s is not in enum scope.', $field_name ) );
                        return false;
                    }
                }
            break;
        }

        return $value;
    }

    public function get_empty_data( $params = false )
    {
        $this->reset_error();

        if( !($table_fields = $this->get_definition( $params ))
         or !is_array( $table_fields ) )
        {
            $this->set_error( self::ERR_MODEL_FIELDS, self::_t( 'Invalid table definition.' ) );
            return false;
        }

        $data_arr = array();
        foreach( $table_fields as $field_name => $field_details )
        {
            if( isset( $field_details['default'] ) )
                $data_arr[$field_name] = $field_details['default'];
            else
                $data_arr[$field_name] = self::validate_field_value( 0, $field_name, $field_details );
        }

        return $data_arr;
    }

    protected function validate_data_for_fields( $params )
    {
        $this->reset_error();

        if( !($table_fields = $this->get_definition( $params ))
         or !is_array( $table_fields ) )
        {
            $this->set_error( self::ERR_MODEL_FIELDS, self::_t( 'Invalid table definition.' ) );
            return false;
        }

        if( empty( $params['action'] )
         or !in_array( $params['action'], array( 'insert', 'edit' ) ) )
            $params['action'] = 'insert';

        $hook_params = array();
        $hook_params['params'] = $params;
        $hook_params['table_fields'] = $table_fields;

        if( ($trigger_result = PHS::trigger_hooks( self::HOOK_RAW_PARAMETERS, $hook_params ))
        and is_array( $trigger_result ) )
        {
            if( !empty( $trigger_result['params'] ) )
                $params = $trigger_result['params'];
        }

        $validated_fields = array();
        $data_arr = array();
        foreach( $table_fields as $field_name => $field_details )
        {
            if( empty( $field_details['editable'] )
            and $params['action'] == 'edit' )
                continue;

            if( array_key_exists( $field_name, $params['fields'] ) )
            {
                $data_arr[$field_name] = self::validate_field_value( $params['fields'][ $field_name ], $field_name, $field_details );
                $validated_fields[] = $field_name;
            } elseif( isset( $field_details['default'] )
                  and $params['action'] == 'insert' )
                // When editting records only passed fields will be saved in database...
                $data_arr[$field_name] = $field_details['default'];
        }

        $return_arr = array();
        $return_arr['data_arr'] = $data_arr;
        $return_arr['validated_fields'] = $validated_fields;

        return $return_arr;
    }

    public function insert( $params )
    {
        $this->reset_error();

        if( !($params = $this->fetch_default_flow_params( $params ))
         or !isset( $params['fields'] ) or !is_array( $params['fields'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Failed validating flow parameters.' ) );
            return false;
        }

        $params['action'] = 'insert';

        if( @method_exists( $this, 'get_insert_prepare_params_'.$params['table_name'] ) )
        {
            if( !($params = call_user_func( array( $this, 'get_insert_prepare_params_' . $params['table_name'] ), $params )) )
            {
                if( ! $this->has_error() )
                    $this->set_error( self::ERR_INSERT, self::_t( 'Couldn\'t parse parameters for database insert.' ) );

                return false;
            }
        } elseif( !($params = $this->get_insert_prepare_params( $params )) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_INSERT, self::_t( 'Couldn\'t parse parameters for database insert.' ) );
            return false;
        }

        if( !($validation_arr = $this->validate_data_for_fields( $params ))
         or empty( $validation_arr['data_arr'] ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_INSERT, self::_t( 'Error validating parameters.' ) );
            return false;
        }

        $insert_arr = $validation_arr['data_arr'];
        if( !($sql = db_quick_insert( $params['table_name'], $insert_arr ))
         or !($item_id = db_query_insert( $sql, $params['db_connection'] )) )
        {
            $this->insert_failed( $insert_arr, $params );

            if( !$this->has_error() )
                $this->set_error( self::ERR_INSERT, self::_t( 'Failed saving information to database.' ) );
            return false;
        }

        $insert_arr[$params['table_index']] = $item_id;

        // Set to tell future calls record was just added to database...
        $insert_arr['{new_in_db}'] = true;

        if( @method_exists( $this, 'insert_after_'.$params['table_name'] ) )
        {
            if( !($new_insert_arr = call_user_func( array( $this, 'insert_after_' . $params['table_name'] ), $insert_arr, $params )) )
            {
                $error_arr = $this->get_error();

                $this->hard_delete( $insert_arr );

                if( self::arr_has_error( $error_arr ) )
                    $this->copy_error_from_array( $error_arr );
                elseif( !$this->has_error() )
                    $this->set_error( self::ERR_INSERT, self::_t( 'Failed actions after database insert.' ) );
                return false;
            }
        } elseif( !($new_insert_arr = $this->insert_after( $insert_arr, $params )) )
        {
            $error_arr = $this->get_error();
            
            $this->hard_delete( $insert_arr );

            if( self::arr_has_error( $error_arr ) )
                $this->copy_error_from_array( $error_arr );
            elseif( !$this->has_error() )
                $this->set_error( self::ERR_INSERT, self::_t( 'Failed actions after database insert.' ) );
            return false;
        }

        $insert_arr = $new_insert_arr;

        return $insert_arr;
    }

    public function edit( $existing_data, $params )
    {
        $this->reset_error();

        if( !($params = $this->fetch_default_flow_params( $params ))
         or !isset( $params['fields'] ) or !is_array( $params['fields'] ) )
        {
            $this->set_error( self::ERR_EDIT, self::_t( 'Failed validating flow parameters.' ) );
            return false;
        }

        if( !($existing_arr = $this->data_to_array( $existing_data, $params ))
         or !array_key_exists( $params['table_index'], $existing_arr ) )
        {
            $this->set_error( self::ERR_EDIT, self::_t( 'Exiting record not found in database.' ) );
            return false;
        }

        $params['action'] = 'edit';

        if( !($params = $this->get_edit_prepare_params( $existing_arr, $params )) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_EDIT, self::_t( 'Couldn\'t parse parameters for database edit.' ) );
            return false;
        }

        if( !($validation_arr = $this->validate_data_for_fields( $params ))
         or empty( $validation_arr['data_arr'] ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_EDIT, self::_t( 'Error validating parameters.' ) );
            return false;
        }

        $edit_arr = $validation_arr['data_arr'];
        if( !empty( $edit_arr )
        and (!($sql = db_quick_edit( $params['table_name'], $edit_arr ))
                or !db_query( $sql.' WHERE `'.$params['table_name'].'`.`'.$params['table_index'].'` = \''.$existing_arr[$params['table_index']].'\'', $params['db_connection'] )
            ) )
        {
            $this->edit_failed( $existing_arr, $edit_arr, $params );

            if( !$this->has_error() )
                $this->set_error( self::ERR_EDIT, self::_t( 'Failed saving information to database.' ) );
            return false;
        }

        if( !($new_edit_arr = $this->edit_after( $existing_arr, $edit_arr, $params )) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_INSERT, self::_t( 'Failed actions after database edit.' ) );
            return false;
        }

        if( !empty( $edit_arr ) )
        {
            foreach( $edit_arr as $key => $val )
                $existing_arr[$key] = $val;
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
    public function insert_or_edit( $constrain_arr, $params )
    {
        $this->reset_error();

        if( !($params = $this->fetch_default_flow_params( $params ))
         or ! isset($params['fields']) or ! is_array( $params['fields'] ) )
        {
            $this->set_error( self::ERR_EDIT, self::_t( 'Failed validating flow parameters.' ) );
            return false;
        }

        $check_params = $params;
        $check_params['result_type'] = 'single';
        $check_params['details'] = '*';
        if( !($existing_arr = $this->get_details_fields( $constrain_arr, $params )) )
        {
            if( !array_key_exists( $params['table_index'], $existing_arr ) )
            {
                $this->set_error( self::ERR_EDIT, self::_t( 'Exiting record not found in database.' ) );
                return false;
            }

            return $this->insert( $params );
        }

        if( !($new_edit_arr = $this->insert_or_edit_editing( $existing_arr, $constrain_arr, $params )) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_INSERT, self::_t( 'Failed actions after database edit.' ) );
            return false;
        }

        return $this->edit( $existing_arr, $params );
    }

    protected function get_details_common( $constrain_arr, $params = false )
    {
        if( !($params = $this->fetch_default_flow_params( $params )) )
            return false;

        if( empty( $params['details'] ) )
            $params['details'] = '*';
        if( !isset( $params['result_type'] ) )
            $params['result_type'] = 'single';
        if( !isset( $params['result_key'] ) )
            $params['result_key'] = $params['table_index'];
        if( empty( $params['extra_sql'] ) )
            $params['extra_sql'] = '';
        if( empty( $params['order_by'] ) )
            $params['order_by'] = '';

        if( !isset( $params['limit'] )
         or $params['result_type'] == 'single' )
            $params['limit'] = 1;
        else
        {
            $params['limit'] = intval( $params['limit'] );
            $params['result_type'] = 'list';
        }

        if( empty( $constrain_arr ) or !is_array( $constrain_arr ) )
            return false;

        $params['fields'] = $constrain_arr;

        if( !($params = $this->get_query_fields( $params ))
         or !($qid = db_query( 'SELECT '.$params['details'].
                               ' FROM '.$params['table_name'].
                               ' WHERE '.$params['extra_sql'].
                               (!empty( $params['order_by'] )?' ORDER BY '.$params['order_by']:'').
                               (isset( $params['limit'] )?' LIMIT 0, '.$params['limit']:''), $params['db_connection'] ))
         or !($item_count = db_num_rows( $qid, $params['db_connection'] )) )
            return false;

        $return_arr = array();
        $return_arr['params'] = $params;
        $return_arr['qid'] = $qid;
        $return_arr['item_count'] = $item_count;

        return $return_arr;
    }

    /**
     * @param array $constrain_arr Conditional db fields
     * @param array|false $params Parameters in the flow
     *
     * @return array|false|\Generator|null Returns single record as array (first matching conditions), array of records matching conditions or acts as generator
     */
    function get_details_fields( $constrain_arr, $params = false )
    {
        if( !($params = $this->fetch_default_flow_params( $params ))
         or !($common_arr = $this->get_details_common( $constrain_arr, $params ))
         or !is_array( $common_arr ) or empty( $common_arr['qid'] ) )
            return false;

        if( !empty( $common_arr['params'] ) )
            $params = $common_arr['params'];

        if( $params['result_type'] == 'single' )
            return db_fetch_assoc( $common_arr['qid'], $params['db_connection'] );

        $item_arr = array();
        while( ($row_arr = db_fetch_assoc( $common_arr['qid'], $params['db_connection'] )) )
        {
            $item_arr[$row_arr[$params['result_key']]] = $row_arr;
        }

        return $item_arr;
    }

    public function get_details( $id, $params = false )
    {
        if( !($params = $this->fetch_default_flow_params( $params )) )
            return false;

        if( empty( $params['details'] ) )
            $params['details'] = '*';

        $id = intval( $id );
        if( empty( $id )
         or !($qid = db_query( 'SELECT '.$params['details'].' FROM `'.$params['table_name'].'` WHERE `'.$params['table_index'].'` = \''.db_escape( $id, $params['db_connection'] ).'\'', $params['db_connection'] ))
         or !($item_arr = db_fetch_assoc( $qid, $params['db_connection'] )) )
            return false;

        return $item_arr;
    }

    public function data_to_array( $item_data, $params = false )
    {
        if( !($params = $this->fetch_default_flow_params( $params )) )
            return false;

        $id = 0;
        $item_arr = false;
        if( is_array( $item_data ) )
        {
            if( !empty( $item_data[$params['table_index']] ) )
                $id = intval( $item_data[$params['table_index']] );
            $item_arr = $item_data;
        } else
            $id = intval( $item_data );

        if( empty( $id ) and (!is_array( $item_arr ) or empty( $item_arr[$params['table_index']] )) )
            return false;

        if( empty( $item_arr ) )
            $item_arr = $this->get_details( $id, $params );

        if( empty( $item_arr ) or !is_array( $item_arr ) )
            return false;

        return $item_arr;
    }

    static function linkage_db_functions()
    {
        return array( 'and', 'or' );
    }

    public function get_query_fields( $params )
    {
        if( empty( $params['fields'] ) or !is_array( $params['fields'] )
         or !($new_params = $this->fetch_default_flow_params( $params )) )
            return $params;

        $params = $new_params;

        if( empty( $params['extra_sql'] ) )
            $params['extra_sql'] = '';

        // Params used for <linkage> parameter (recurring)...
        if( empty( $params['recurring_level'] ) )
            $params['recurring_level'] = 0;

        $linkage_func = 'AND';
        if( !empty( $params['fields']['{linkage_func}'] )
        and in_array( strtolower( $params['fields']['{linkage_func}'] ), self::linkage_db_functions() ) )
            $linkage_func = strtoupper( $params['fields']['{linkage_func}'] );

        if( isset( $params['fields']['{linkage_func}'] ) )
            unset( $params['fields']['{linkage_func}'] );

        foreach( $params['fields'] as $field_name => $field_val )
        {
            $field_name = trim( $field_name );
            if( empty( $field_name ) )
                continue;

            if( $field_name == '{linkage}' )
            {
                if( empty( $field_val ) or !is_array( $field_val )
                 or empty( $field_val['fields'] ) or !is_array( $field_val['fields'] ) )
                    continue;

                $recurring_params = $params;
                $recurring_params['fields'] = $field_val['fields'];
                $recurring_params['extra_sql'] = '';
                $recurring_params['recurring_level']++;

                if( ($recurring_result = $this->get_query_fields( $recurring_params ))
                and is_array( $recurring_result ) and !empty( $recurring_result['extra_sql'] ) )
                {
                    $params['extra_sql'] .= (!empty( $params['extra_sql'] )?' '.$linkage_func.' ':'').' ('.$recurring_result['extra_sql'].') ';
                }

                continue;
            }

            if( strstr( $field_name, '.' ) === false )
                $field_name = '`'.$params['table_name'].'`.`'.$field_name.'`';

            if( !is_array( $field_val ) )
            {
                if( $field_val !== false )
                    $params['extra_sql'] .= (!empty( $params['extra_sql'] )?' '.$linkage_func.' ':'').' '.$field_name.' = \''.db_escape( $field_val, $params['db_connection'] ).'\' ';
            } else
            {
                if( empty( $field_val['field'] ) )
                    $field_val['field'] = $field_name;
                if( empty( $field_val['check'] ) )
                    $field_val['check'] = '=';
                if( !isset( $field_val['value'] ) )
                    $field_val['value'] = false;

                if( $field_val['value'] !== false )
                {
                    $field_val['check'] = trim( $field_val['check'] );
                    if( in_array( strtolower( $field_val['check'] ), array( 'in', 'is', 'between' ) ) )
                        $check_value = $field_val['value'];
                    else
                        $check_value = '\''.db_escape( $field_val['value'], $params['db_connection'] ).'\'';

                    $params['extra_sql'] .= (!empty( $params['extra_sql'] )?' '.$linkage_func.' ':'').' '.$field_val['field'].' '.$field_val['check'].' '.$check_value.' ';
                }
            }
        }

        return $params;
    }

    public function get_count( $params = false )
    {
        $this->reset_error();

        if( !($params = $this->fetch_default_flow_params( $params )) )
            return 0;

        if( empty( $params['count_field'] ) )
            $params['count_field'] = '*';

        if( empty( $params['extra_sql'] ) )
            $params['extra_sql'] = '';
        if( empty( $params['join_sql'] ) )
            $params['join_sql'] = '';
        if( empty( $params['group_by'] ) )
            $params['group_by'] = '';

        if( empty( $params['fields'] ) )
            $params['fields'] = array();

        if( ($params = $this->get_count_prepare_params( $params )) === false
         or ($params = $this->get_query_fields( $params )) === false )
            return 0;

        if( empty( $params['extra_sql'] ) )
            $params['extra_sql'] = '';

        $ret = 0;
        if( ($qid = db_query( 'SELECT COUNT('.$params['count_field'].') AS total_enregs '.
                              ' FROM `'.$params['table_name'].'` '.
                              $params['join_sql'].
                              (!empty( $params['extra_sql'] )?' WHERE '.$params['extra_sql']:'').
                              (!empty( $params['group_by'] )?' GROUP BY '.$params['group_by']:''), $params['db_connection']
            ))
            and ($result = db_fetch_assoc( $qid, $params['db_connection'] )) )
        {
            $ret = $result['total_enregs'];
        }

        return $ret;
    }

    protected function get_list_common( $params = false )
    {
        $this->reset_error();

        if( !($params = $this->fetch_default_flow_params( $params )) )
            return false;

        if( empty( $params['get_query_id'] ) )
            $params['get_query_id'] = false;
        // Field which will be used as key in result array (be sure is unique)
        if( empty( $params['arr_index_field'] ) )
            $params['arr_index_field'] = $params['table_index'];

        if( empty( $params['extra_sql'] ) )
            $params['extra_sql'] = '';
        if( empty( $params['join_sql'] ) )
            $params['join_sql'] = '';
        if( empty( $params['db_fields'] ) )
            $params['db_fields'] = '`'.$params['table_name'].'`.*';
        if( empty( $params['offset'] ) )
            $params['offset'] = 0;
        if( empty( $params['enregs_no'] ) )
            $params['enregs_no'] = 1000;
        if( empty( $params['order_by'] ) )
            $params['order_by'] = '`'.$params['table_name'].'`.`'.$params['table_index'].'` DESC';
        if( empty( $params['group_by'] ) )
            $params['group_by'] = '`'.$params['table_name'].'`.`'.$params['table_index'].'`';

        if( empty( $params['fields'] ) )
            $params['fields'] = array();

        if( ($params = $this->get_list_prepare_params( $params )) === false
         or ($params = $this->get_query_fields( $params )) === false
         or !($qid = db_query( 'SELECT '.$params['db_fields'].' '.
                              ' FROM `'.$params['table_name'].'` '.
                              $params['join_sql'].
                              (!empty( $params['extra_sql'] )?' WHERE '.$params['extra_sql']:'').
                              (!empty( $params['group_by'] )?' GROUP BY '.$params['group_by']:'').
                              (!empty( $params['order_by'] )?' ORDER BY '.$params['order_by']:'').
                              ' LIMIT '.$params['offset'].', '.$params['enregs_no'], $params['db_connection']
                ))
        or !($rows_number = db_num_rows( $qid, $params['db_connection'] )) )
            return false;

        $return_arr = array();
        $return_arr['params'] = $params;
        $return_arr['qid'] = $qid;
        $return_arr['item_count'] = $rows_number;

        return $return_arr;
    }

    public function get_list( $params = false )
    {
        $this->reset_error();

        if( !($common_arr = $this->get_list_common( $params ))
         or !is_array( $common_arr ) or empty( $common_arr['qid'] ) )
            return false;

        if( !empty( $params['get_query_id'] ) )
            return $common_arr['qid'];

        $ret_arr = array();
        while( ($item_arr = db_fetch_assoc( $common_arr['qid'], $params['db_connection'] )) )
        {
            $key = $params['table_index'];
            if( isset( $item_arr[$params['arr_index_field']] ) )
                $key = $params['arr_index_field'];

            $ret_arr[$item_arr[$key]] = $item_arr;
        }

        return $ret_arr;
    }

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
        if( $this_instance_id == $plugins_model_id )
        {
            $plugins_model = $this;

            $this->install_tables();
        } else
        {
            if( !($plugins_model = PHS::load_model( 'plugins' )) )
            {
                PHS_Logger::logf( '!!! Error instantiating plugins model. ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

                $this->set_error( self::ERR_INSTALL, self::_t( 'Error instantiating plugins model.' ) );
                return false;
            }

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
        if( $this_instance_id != $plugins_model_id
        and !$this->install_tables() )
            return false;

        $plugin_details = array();
        $plugin_details['instance_id'] = $this_instance_id;
        $plugin_details['plugin'] = $this->instance_plugin_name();
        $plugin_details['type'] = $this->instance_type();
        $plugin_details['is_core'] = ($this->instance_is_core() ? 1 : 0);
        $plugin_details['settings'] = PHS_line_params::to_string( $this->get_default_settings() );
        $plugin_details['status'] = PHS_Model_Plugins::STATUS_INSTALLED;
        $plugin_details['version'] = $this->get_model_version();

        if( !($db_details = $plugins_model->update_db_details( $plugin_details ))
         or empty( $db_details['new_data'] ) )
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

        if( empty( $old_plugin_arr ) )
        {
            PHS_Logger::logf( 'Triggering install signal ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

            // No details in database before... it should be an install
            $signal_params = array();
            $signal_params['version'] = $plugin_arr['version'];

            $this->signal_trigger( self::SIGNAL_INSTALL, $signal_params );

            PHS_Logger::logf( 'DONE triggering install signal ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );
        } else
        {
            $trigger_update_signal = false;
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

                $trigger_update_signal = true;
            }

            if( $trigger_update_signal )
            {
                PHS_Logger::logf( 'Triggering update signal ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

                $signal_params = array();
                $signal_params['old_version'] = $old_plugin_arr['version'];
                $signal_params['new_version'] = $plugin_arr['version'];

                $this->signal_trigger( self::SIGNAL_UPDATE, $signal_params );
            } else
            {
                PHS_Logger::logf( 'Triggering install signal ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

                $signal_params = array();
                $signal_params['version'] = $plugin_arr['version'];

                $this->signal_trigger( self::SIGNAL_INSTALL, $signal_params );
            }

            PHS_Logger::logf( 'DONE triggering signal ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );
        }

        PHS_Logger::logf( 'DONE installing model ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

        return $plugin_arr;
    }

    final public function install_tables()
    {
        $this->reset_error();

        if( empty( self::$_definition ) or !is_array( self::$_definition )
         or !($flow_params = $this->fetch_default_flow_params()) )
            return true;

        PHS_Logger::logf( 'Installing tables for model ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

        foreach( self::$_definition as $table_name => $table_definition )
        {
            $flow_params['table_name'] = $table_name;

            $db_connection = $this->get_db_connection( $flow_params );
            if( !($db_settings = db_settings( $db_connection ))
             or !is_array( $db_settings ) )
                continue;

            if( empty( $table_definition[self::T_DETAILS_KEY] ) )
                $table_details = self::default_table_details_arr();
            else
                $table_details = $table_definition[self::T_DETAILS_KEY];

            if( empty( $db_settings['prefix'] ) )
                $db_settings['prefix'] = '';

            $sql = 'CREATE TABLE IF NOT EXISTS `'.$db_settings['prefix'].$table_name.'` ( '."\n";
            $all_fields_str = '';
            $keys_str = '';
            foreach( $table_definition as $field_name => $field_details )
            {
                if( $field_name == self::T_DETAILS_KEY
                 or empty( $field_details ) or !is_array( $field_details )
                 or !($type_details = self::valid_field_type( $field_details['type'] )) )
                    continue;

                $field_str = '';

                if( !empty( $field_details['primary'] ) )
                    $keys_str = ' PRIMARY KEY (`'.$field_name.'`)'.($keys_str!=''?', ':'');
                elseif( !empty( $field_details['index'] ) )
                    $keys_str .= ($keys_str!=''?', ':'').' KEY `'.$field_name.'` (`'.$field_name.'`)';

                $field_str .= '`'.$field_name.'` '.$type_details['title'];
                if( $field_details['length'] !== null
                and $field_details['length'] !== false )
                    $field_str .= '('.$field_details['length'].')';

                if( !empty( $field_details['nullable'] ) )
                    $field_str .= ' NULL';
                else
                    $field_str .= ' NOT NULL';

                if( !empty( $field_details['auto_increment'] ) )
                    $field_str .= ' AUTO_INCREMENT';

                if( empty( $field_details['primary'] ) )
                {
                    if( $field_details['default'] === null )
                        $default_value = 'NULL';
                    elseif( $field_details['default'] === '' )
                        $default_value = '\'\'';
                    else
                        $default_value = '\''.self::safe_escape( $field_details['default'] ).'\'';

                    $field_str .= ' DEFAULT '.$default_value;
                }

                if( !empty( $field_details['comment'] ) )
                    $field_str .= ' COMMENT \''.self::safe_escape( $field_details['comment'] ).'\'';

                $all_fields_str .= ($all_fields_str!=''?', '."\n":'').$field_str;
            }

            $sql .= $all_fields_str.(!empty( $keys_str )?', '."\n":'').$keys_str.(!empty( $keys_str )?"\n":'').
                    ') ENGINE='.$table_details['engine'].
                    ' DEFAULT CHARSET='.$table_details['charset'].
                    (!empty( $table_details['comment'] )?' COMMENT=\''.self::safe_escape( $table_details['comment'] ).'\'':'').';';

            if( !db_query( $sql, $db_connection ) )
            {
                PHS_Logger::logf( '!!! Error installing tables for model ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

                $this->set_error( self::ERR_TABLE_GENERATE, self::_t( 'Error generating table %s.', $table_name ) );
                return false;
            }
        }

        PHS_Logger::logf( 'DONE Installing tables for model ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

        return true;
    }

    protected function signal_receive( $sender, $signal, $signal_params = false )
    {
        $return_arr = self::default_signal_response();

        switch( $signal )
        {
            default:
                $return_arr = parent::signal_receive( $sender, $signal, $signal_params );
            break;

            case self::SIGNAL_FORCE_INSTALL:
                if( !$this->install() )
                {
                    if( $this->has_error() )
                    {
                        $return_arr['error_arr'] = $this->get_error();
                        $return_arr['stop_process'] = true;
                    }
                }
            break;
        }

        return $return_arr;
    }
}
