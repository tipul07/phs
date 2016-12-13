<?php

namespace phs\libraries;

use \phs\PHS;
use \phs\system\core\models\PHS_Model_Plugins;

abstract class PHS_Model_Core_Base extends PHS_Has_db_settings
{
    // DON'T OVERWRITE THIS CONSTANT. IT REPRESENTS BASE MODEL CLASS VERSION
    const MODEL_BASE_VERSION = '1.0.0';

    const ERR_MODEL_FIELDS = 40000, ERR_TABLE_GENERATE = 40001, ERR_INSTALL = 40002, ERR_UPDATE = 40003, ERR_UNINSTALL = 40004,
          ERR_INSERT = 40005, ERR_EDIT = 40006, ERR_DELETE_BY_INDEX = 40007, ERR_ALTER = 40008, ERR_DELETE = 40009, ERR_UPDATE_TABLE = 40010;

    const HOOK_RAW_PARAMETERS = 'phs_model_raw_parameters', HOOK_INSERT_BEFORE_DB = 'phs_model_insert_before_db',
          HOOK_TABLES = 'phs_model_tables', HOOK_TABLE_FIELDS = 'phs_model_table_fields', HOOK_HARD_DELETE = 'phs_model_hard_delete';

    const SIGNAL_INSERT = 'phs_model_insert', SIGNAL_EDIT = 'phs_model_edit', SIGNAL_HARD_DELETE = 'phs_model_hard_delete',
          SIGNAL_INSTALL = 'phs_model_install', SIGNAL_UNINSTALL = 'phs_model_uninstall',
          SIGNAL_UPDATE = 'phs_model_update', SIGNAL_FORCE_INSTALL = 'phs_model_force_install';

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

        self::FTYPE_DATE => array( 'title' => 'date', 'default_length' => null, 'default_value' => null, 'nullable' => true, ), // 'raw_default' => 'CURRENT_TIMESTAMP' ), // self::DATE_EMPTY, ),
        self::FTYPE_DATETIME => array( 'title' => 'datetime', 'default_length' => null, 'default_value' => null, 'nullable' => true, ), // 'raw_default' => 'CURRENT_TIMESTAMP' ), // self::DATETIME_EMPTY, ),
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

    // Tables definition
    protected $_definition = array();

    private $model_tables_arr = array();

    static $tables_arr = false;

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

    /**
     * A dynamic table structure means that table fields can be altered by plugins, so system will call update method each time an install check
     * is done. In this case model version is not checked and update will be called anyway to alter fields depending on plugins asking fields changes.
     *
     * @return bool Returns true if table structure is dynamically created, false if static
     */
    public function dynamic_table_structure()
    {
        return false;
    }

    /**
     * @return int Should return INSTANCE_TYPE_* constant
     */
    public function instance_type()
    {
        return self::INSTANCE_TYPE_MODEL;
    }

    /**
     * @param array|false $params Parameters in the flow
     *
     * @return false|string Returns false if model uses default database connection or connection name as string
     */
    public function get_db_connection( $params = false )
    {
        return false;
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
     * Returns primary table key
     *
     * @param array|false $params Parameters in the flow
     *
     * @return string What's primary key of the table (override the method if not `id`)
     */
    public function get_primary_key( $params = false )
    {
        return 'id';
    }

    /**
     * Returns table name used in flow without prefix
     *
     * @param array|false $params Parameters in the flow
     *
     * @return string Returns table set in parameters flow or main table if no table is specified in flow
     * (table name can be passed to $params array of each method in 'table_name' index)
     */
    public function get_table_name( $params = false )
    {
        if( !empty( $params ) and is_array( $params )
        and !empty( $params['table_name'] ) )
            return $params['table_name'];

        // return default table...
        return $this->get_main_table_name();
    }

    /**
     * Performs any necessary custom actions when updating model from $old_version to $new_version
     * Overwrite this method to do particular updates.
     * If this function returns false whole update will stop and error set in this method will be used.
     *
     * @param string $old_version Old version of plugin
     * @param string $new_version New version of plugin
     *
     * @return bool true on success, false on failure
     */
    protected function custom_update( $old_version, $new_version )
    {
        return true;
    }

    /**
     * Called first in insert flow.
     * Parses flow parameters if anything special should be done.
     * This should do checks on raw parameters received by insert method.
     *
     * @param array|false $params Parameters in the flow
     *
     * @return array|bool Flow parameters array
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
     * @return array|bool Flow parameters array
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
     * @return array|bool Returns data array added in database (with changes, if required) or false if functionality failed.
     * Saved information will not be rolled back.
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

    /**
     * Prepares parameters common to _count and _list methods
     *
     * @param array|false $params Parameters in the flow
     *
     * @return array Flow parameters array
     */
    protected function get_count_list_common_params( $params = false )
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

    private function parse_mysql_table_details( $table_name, $flow_params = false )
    {
        $this->reset_error();

        if( empty( $table_name ) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Please provide table name.' ) );
            return false;
        }

        if( !($flow_params = $this->fetch_default_flow_params( $flow_params )) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Failed validating flow parameters.' ) );
            return false;
        }

        $table_details = self::default_table_details_arr();
        if( ($qid = db_query( 'SHOW TABLE STATUS WHERE Name = \''.$table_name.'\'', $this->get_db_connection( $flow_params ) ))
        and ($result_arr = @mysqli_fetch_assoc( $qid )) )
        {
            if( !empty( $result_arr['Engine'] ) )
                $table_details['engine'] = $result_arr['Engine'];
            if( !empty( $result_arr['Comment'] ) )
                $table_details['comment'] = $result_arr['Comment'];
            if( !empty( $result_arr['Collation'] ) )
            {
                $table_details['collate'] = $result_arr['Collation'];
                if( ($collate_parts = explode( '_', $table_details['collate'] )) )
                    $table_details['charset'] = $collate_parts[0];
            }
        }

        return $table_details;
    }

    public function check_table_exists( $flow_params = false, $force = false )
    {
        $this->reset_error();

        if( !($flow_params = $this->fetch_default_flow_params( $flow_params ))
         or !($flow_table_name = $this->get_flow_table_name( $flow_params )) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Failed validating flow parameters.' ) );
            return false;
        }

        if( (self::$tables_arr === false or !empty( $force ))
        and ($qid = db_query( 'SHOW TABLES', $this->get_db_connection( $flow_params ) )) )
        {
            self::$tables_arr = array();
            while( ($table_name = db_fetch_assoc( $qid )) )
            {
                if( !is_array( $table_name ) )
                    continue;

                $table_arr = array_values( $table_name );
                self::$tables_arr[$table_arr[0]] = array();

                self::$tables_arr[$table_arr[0]][self::T_DETAILS_KEY] = $this->parse_mysql_table_details( $table_arr[0] );
            }
        }

        if( is_array( self::$tables_arr )
        and array_key_exists( $flow_table_name, self::$tables_arr ) )
            return true;

        return false;
    }

    public static function default_mysql_table_field_fields()
    {
        return array(
            'Field' => '',
            'Type' => '',
            'Collation' => '',
            'Null' => '',
            'Key' => '',
            'Default' => '',
            'Extra' => '',
            'Privileges' => '',
            'Comment' => '',
        );
    }

    public static function get_type_from_mysql_field_type( $type )
    {
        $type = trim( $type );
        if( empty( $type ) )
            return false;

        $return_arr = array();
        $return_arr['type'] = self::FTYPE_UNKNOWN;
        $return_arr['length'] = null;

        $mysql_type = '';
        $mysql_length = '';
        if( !preg_match( '@([a-z]+)([\(\s*[0-9,\s]+\s*\)]*)@i', $type, $matches ) )
            $mysql_type = $type;

        else
        {
            if( !empty( $matches[1] ) )
                $mysql_type = strtolower( trim( $matches[1] ) );

            if( !empty( $matches[2] ) )
                $mysql_length = trim( $matches[2], ' ()' );
        }

        if( !empty( $mysql_type )
        and ($field_types = self::get_field_types())
        and is_array( $field_types ) )
        {
            $mysql_type = strtolower( trim( $mysql_type ) );
            foreach( $field_types as $field_type => $field_arr )
            {
                if( empty( $field_arr['title'] ) )
                    continue;

                if( $field_arr['title'] == $mysql_type )
                {
                    $return_arr['type'] = $field_type;
                    break;
                }
            }
        }

        if( !($field_arr = self::valid_field_type( $return_arr['type'] )) )
            return $return_arr;

        if( !empty( $mysql_length ) )
        {
            $length_arr = array();
            if( ($parts_arr = explode( ',', $mysql_length ))
            and is_array( $parts_arr ) )
            {
                foreach( $parts_arr as $part )
                {
                    $part = trim( $part );
                    if( $part === '' )
                        continue;

                    $length_arr[] = $part;
                }
            }

            $return_arr['length'] = implode( ',', $length_arr );
        }

        return $return_arr;
    }

    public function parse_mysql_field_result( $field_arr )
    {
        $field_arr = self::validate_array( $field_arr, self::default_mysql_table_field_fields() );
        $model_field_arr = self::default_field_arr();

        if( !($model_field_type = self::get_type_from_mysql_field_type( $field_arr['Type'] )) )
            $model_field_arr['type'] = self::FTYPE_UNKNOWN;
        else
        {
            $model_field_arr['type'] = $model_field_type['type'];
            $model_field_arr['length'] = $model_field_type['length'];
        }

        $model_field_arr['nullable'] = ((!empty( $field_arr['Null'] ) and strtolower( $field_arr['Null'] ) == 'yes')?true:false);
        $model_field_arr['primary'] = ((!empty( $field_arr['Key'] ) and strtolower( $field_arr['Key'] ) == 'pri')?true:false);
        $model_field_arr['auto_increment'] = ((!empty( $field_arr['Extra'] ) and strtolower( $field_arr['Extra'] ) == 'auto_increment')?true:false);
        $model_field_arr['index'] = ((!empty( $field_arr['Key'] ) and strtolower( $field_arr['Key'] ) == 'mul')?true:false);
        $model_field_arr['default'] = $field_arr['Default'];
        $model_field_arr['comment'] = (!empty( $field_arr['Comment'] )?$field_arr['Comment']:'');

        return $model_field_arr;
    }

    public function get_table_columns_as_definition( $flow_params = false, $force = false )
    {
        $this->reset_error();

        if( !($flow_params = $this->fetch_default_flow_params( $flow_params ))
         or !($flow_table_name = $this->get_flow_table_name( $flow_params )) )
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
        if( empty( self::$tables_arr[$flow_table_name] ) or !is_array( self::$tables_arr[$flow_table_name] ) )
            self::$tables_arr[$flow_table_name] = array();

        if( empty( $force )
        and !empty( self::$tables_arr[$flow_table_name] ) and is_array( self::$tables_arr[$flow_table_name] )
        and count( self::$tables_arr[$flow_table_name] ) > 1 )
            return self::$tables_arr[$flow_table_name];

        if( ($qid = db_query( 'SHOW FULL COLUMNS FROM `'.$flow_table_name.'`', $flow_params['db_connection'] )) )
        {
            while( ($field_arr = db_fetch_assoc( $qid )) )
            {
                if( !is_array( $field_arr )
                 or empty( $field_arr['Field'] ) )
                    continue;

                self::$tables_arr[$flow_table_name][$field_arr['Field']] = $this->parse_mysql_field_result( $field_arr );
            }
        }

        return self::$tables_arr[$flow_table_name];
    }

    public function check_column_exists( $field, $flow_params = false, $force = false )
    {
        $this->reset_error();

        if( !($flow_params = $this->fetch_default_flow_params( $flow_params ))
         or !($flow_table_name = $this->get_flow_table_name( $flow_params )) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Failed validating flow parameters.' ) );
            return false;
        }
        
        if( !($table_definition = $this->get_table_columns_as_definition( $flow_params, $force ))
         or !is_array( $table_definition ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Couldn\'t get definition for table %s.', $flow_table_name ) );
            return false;
        }
        
        if( !array_key_exists( $field, $table_definition ) )
            return false;

        return $table_definition[$field];
    }

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
         or version_compare( $db_details['version'], $this->get_model_version(), '!=' ) )
            return $this->update( $db_details['version'], $this->get_model_version() );

        return true;
    }

    /**
     * This method hard-deletes a record from database. If additional work is required before hard-deleting record, self::HOOK_HARD_DELETE is called before deleting.
     *
     * @param array|int $existing_data Array with full database fields or index id
     * @param array|bool $params Parameters in the flow
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

        $db_connection = $this->get_db_connection( $params['db_connection'] );

        $result = false;
        if( db_query( 'DELETE FROM `'.$this->get_flow_table_name( $params ).'` WHERE `'.$params['table_index'].'` = \''.db_escape( $existing_arr[$params['table_index']], $db_connection ).'\'', $db_connection ) )
            $result = true;

        return $result;
    }

    static public function safe_escape( $str, $char = '\'' )
    {
        return str_replace( $char, '\\'.$char, str_replace( '\\'.$char, $char, $str ) );
    }

    static public function default_field_arr()
    {
        // if 'default_value' is set in field definition that value will be used for 'default' key
        return array(
            'type' => self::FTYPE_UNKNOWN,
            'editable' => true,
            'length' => null,
            'primary' => false,
            'auto_increment' => false,
            'index' => false,
            'default' => null,
            'raw_default' => null,
            'nullable' => false,
            'comment' => '',
            // in case we renamed the field from something else we add old name here...
            // we add all old names here so in case we update structure from an old version it would still recognise field names
            // update will check if current database structures field names in this array and if any match will rename old field with current definition
            // eg. old_names = array( 'old_field1', 'old_field2' ) =>
            //     if we find in current structure old_field1 or old_field2 as fields will rename them in current field and will apply current definition
            'old_names' => array(),
        );
    }

    static public function default_table_details_arr()
    {
        return array(
            'engine' => 'InnoDB',
            'charset' => 'utf8',
            'collate' => 'utf8_general_ci',
            'comment' => '',
        );
    }

    public static function fields_changed( $field1_arr, $field2_arr )
    {
        if( !($field1_arr = self::validate_field( $field1_arr ))
         or !($field2_arr = self::validate_field( $field2_arr )) )
            return true;

        if( intval( $field1_arr['type'] ) != intval( $field2_arr['type'] )
         // for lengths with comma
         or str_replace( ' ', '', $field1_arr['length'] ) != str_replace( ' ', '', $field2_arr['length'] )
         or $field1_arr['primary'] != $field1_arr['primary']
         or $field1_arr['auto_increment'] != $field1_arr['auto_increment']
         or $field1_arr['index'] != $field1_arr['index']
         or $field1_arr['default'] !== $field1_arr['default']
         or $field1_arr['nullable'] !== $field1_arr['nullable']
         or trim( $field1_arr['comment'] ) !== trim( $field1_arr['comment'] )
        )
            return true;

        return false;
    }

    public static function table_details_changed( $details1_arr, $details2_arr )
    {
        $default_table_details = self::default_table_details_arr();

        if( !($details1_arr = self::validate_array( $details1_arr, $default_table_details ))
         or !($details2_arr = self::validate_array( $details2_arr, $default_table_details )) )
            return array_keys( $default_table_details );

        $keys_changed = array();
        if( strtolower( trim( $details1_arr['engine'] ) ) != strtolower( trim( $details2_arr['engine'] ) ) )
            $keys_changed['engine'] = $details2_arr['engine'];
        if( strtolower( trim( $details1_arr['charset'] ) ) != strtolower( trim( $details2_arr['charset'] ) ) )
            $keys_changed['charset'] = $details2_arr['charset'];
        if( strtolower( trim( $details1_arr['collate'] ) ) != strtolower( trim( $details2_arr['collate'] ) ) )
            $keys_changed['collate'] = $details2_arr['collate'];
        if( trim( $details1_arr['comment'] ) != trim( $details2_arr['comment'] ) )
            $keys_changed['comment'] = $details2_arr['comment'];

        return (!empty( $keys_changed )?$keys_changed:false);
    }

    public static function validate_field( $field_arr )
    {
        if( empty( $field_arr ) or !is_array( $field_arr ) )
            $field_arr = array();

        $def_values = self::default_field_arr();
        $new_field_arr = array();
        foreach( $def_values as $key => $val )
        {
            if( !array_key_exists( $key, $field_arr ) )
                $new_field_arr[$key] = $val;
            else
                $new_field_arr[$key] = $field_arr[$key];
        }

        $field_arr = $new_field_arr;

        if( empty( $field_arr['type'] )
         or !($field_details = self::valid_field_type( $field_arr['type'] )) )
            return false;

        if( $field_details['default_length'] === null
        and isset( $field_arr['length'] ) )
            $field_arr['length'] = null;

        if( isset( $field_details['nullable'] ) )
            $field_arr['nullable'] = (!empty( $field_details['nullable'] )?true:false);

        if( !isset( $field_arr['length'] )
        and isset( $field_details['default_length'] ) )
            $field_arr['length'] = $field_details['default_length'];

        if( $field_arr['default'] === null
        and isset( $field_details['default_value'] ) )
            $field_arr['default'] = $field_details['default_value'];

        if( empty( $field_arr['raw_default'] )
        and !empty( $field_details['raw_default'] ) )
            $field_arr['raw_default'] = $field_details['raw_default'];

        if( !empty( $field_arr['primary'] ) )
        {
            $field_arr['editable'] = false;
            $field_arr['default'] = null;
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

        if( empty( $this->_definition[$params['table_name']] ) )
            return false;

        return $this->_definition[$params['table_name']];
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

    final static function default_table_fields_hook_args()
    {
        return self::validate_array_recursive( array(
            'model_id' => '',
            'flow_params' => array(),
            'fields_arr' => array(),
        ), PHS_Hooks::default_common_hook_args() );
    }

    final private function all_fields_definition( $flow_params )
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

        if( (
                // Check plugin hook
                (!empty( $plugin_instance_id )
                    and ($extra_fields_arr = PHS::trigger_hooks( self::HOOK_TABLE_FIELDS.'_'.$plugin_instance_id, $hook_params ))
                )
                or
                // Check model hook
                ($extra_fields_arr = PHS::trigger_hooks( self::HOOK_TABLE_FIELDS.'_'.$instance_id, $hook_params ))
                or
                // Check generic hook
                ($extra_fields_arr = PHS::trigger_hooks( self::HOOK_TABLE_FIELDS, $hook_params ))
            )
        and is_array( $extra_fields_arr ) and !empty( $extra_fields_arr['fields_arr'] ) )
            $fields_arr = self::merge_array_assoc( $extra_fields_arr['fields_arr'], $fields_arr );

        return $fields_arr;
    }

    private function validate_tables_definition()
    {
        if( !($all_tables_arr = $this->get_all_table_names())
         or !is_array( $all_tables_arr ) )
            return false;

        foreach( $all_tables_arr as $table_name )
        {
            if( !($flow_params = $this->fetch_default_flow_params( array( 'table_name' => $table_name ) )) )
            {
                $this->set_error( self::ERR_MODEL_FIELDS, self::_t( 'Couldn\'t fetch flow parameters for table %s.', $table_name ) );
                return false;
            }

            if( !$this->validate_definition( $flow_params ) )
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

        if( !empty( $this->_definition[$params['table_name']] ) )
            return true;

        if( !($model_fields = $this->all_fields_definition( $params ))
         or !is_array( $model_fields ) )
        {
            $this->set_error( self::ERR_MODEL_FIELDS, self::_t( 'Invalid fields definition for table %s.', $params['table_name'] ) );
            return false;
        }

        $this->_definition[$params['table_name']] = array();
        foreach( $model_fields as $field_name => $field_arr )
        {
            if( $field_name == self::T_DETAILS_KEY )
            {
                $this->_definition[$params['table_name']][$field_name] = self::validate_table_details( $field_arr );
                continue;
            }

            if( !($new_field_arr = self::validate_field( $field_arr )) )
            {
                $this->set_error( self::ERR_MODEL_FIELDS, self::_t( 'Field %s has an invalid definition.', $field_name ) );
                return false;
            }

            $this->_definition[$params['table_name']][$field_name] = $new_field_arr;
        }

        if( empty( $this->_definition[$params['table_name']][self::T_DETAILS_KEY] ) )
            $this->_definition[$params['table_name']][self::T_DETAILS_KEY] = self::default_table_details_arr();

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

        if( !$this->signal_defined( self::SIGNAL_UNINSTALL ) )
        {
            $this->define_signal( self::SIGNAL_UNINSTALL );
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

    public function fetch_default_flow_params( $params = false )
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

    static function validate_field_value( $value, $field_name, $field_details, $params = false )
    {
        self::st_reset_error();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

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
                $value = PHS_params::set_type( $value, PHS_params::T_INT, $phs_params_arr );
            break;

            case self::FTYPE_BIGINT:
                $value = trim( $value );
                if( @function_exists( 'bcmul' ) )
                    $value = bcmul( $value, 1, 0 );
            break;

            case self::FTYPE_DATE:
                if( empty_db_date( $value ) )
                    $value = null;
                else
                    $value = @date( self::DATE_DB, parse_db_date( $value ) );
            break;

            case self::FTYPE_DATETIME:
                if( empty_db_date( $value ) )
                    $value = null;
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

        if( !($params = $this->fetch_default_flow_params( $params ))
         or !($table_fields = $this->get_definition( $params ))
         or !is_array( $table_fields ) )
        {
            $this->set_error( self::ERR_MODEL_FIELDS, self::_t( 'Invalid table definition.' ) );
            return false;
        }

        $data_arr = array();
        foreach( $table_fields as $field_name => $field_details )
        {
            if( $field_name == self::T_DETAILS_KEY )
                continue;
            
            if( isset( $field_details['default'] ) )
                $data_arr[$field_name] = $field_details['default'];
            else
                $data_arr[$field_name] = self::validate_field_value( 0, $field_name, $field_details );
        }
        
        $hook_params = PHS_Hooks::default_model_empty_data_hook_args();
        $hook_params['data_arr'] = $data_arr;
        $hook_params['flow_params'] = $params;

        if( ($hook_result = PHS::trigger_hooks( PHS_Hooks::H_MODEL_EMPTY_DATA, $hook_params ))
        and is_array( $hook_result ) and !empty( $hook_result['data_arr'] ) )
            $data_arr = self::merge_array_assoc( $data_arr, $hook_result['data_arr'] );

        return $data_arr;
    }

    public function table_field_details( $field, $params = false )
    {
        $this->reset_error();

        $table = false;
        if( strstr( $field, '.' ) !== false )
            list( $table, $field ) = explode( '.', $field, 2 );

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        $params['table_name'] = $table;

        if( !($params = $this->fetch_default_flow_params( $params )) )
        {
            $this->set_error( self::ERR_MODEL_FIELDS, self::_t( 'Failed validating flow parameters.' ) );
            return false;
        }

        if( !($table_fields = $this->get_definition( $params ))
         or !is_array( $table_fields ) )
        {
            $this->set_error( self::ERR_MODEL_FIELDS, self::_t( 'Invalid table definition.' ) );
            return false;
        }

        if( empty( $table_fields[$field] ) or !is_array( $table_fields[$field] ) )
            return null;

        return $table_fields[$field];
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

        $hook_params = PHS_Hooks::default_model_validate_data_fields_hook_args();
        $hook_params['flow_params'] = $params;
        $hook_params['table_fields'] = $table_fields;

        if( ($trigger_result = PHS::trigger_hooks( PHS_Hooks::H_MODEL_VALIDATE_DATA_FIELDS, $hook_params ))
        and is_array( $trigger_result ) )
        {
            if( !empty( $trigger_result['flow_params'] ) and is_array( $trigger_result['flow_params'] ) )
                $params = self::merge_array_assoc( $params, $trigger_result['flow_params'] );
            if( !empty( $trigger_result['table_fields'] ) and is_array( $trigger_result['table_fields'] ) )
                $table_fields = self::merge_array_assoc( $table_fields, $trigger_result['table_fields'] );
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

        if( (
                @method_exists( $this, 'get_insert_prepare_params_'.$params['table_name'] )
                and
                !($params = @call_user_func( array( $this, 'get_insert_prepare_params_' . $params['table_name'] ), $params ))
            )

            or

            (
                !@method_exists( $this, 'get_insert_prepare_params_'.$params['table_name'] )
                and
                !($params = $this->get_insert_prepare_params( $params ))
            )
        )
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

        if( !($sql = db_quick_insert( $this->get_flow_table_name( $params ), $insert_arr ))
         or !($item_id = db_query_insert( $sql, $this->get_db_connection( $params ) )) )
        {
            if( @method_exists( $this, 'insert_failed_'.$params['table_name'] ) )
                @call_user_func( array( $this, 'insert_failed_' . $params['table_name'] ), $insert_arr, $params );
            else
                $this->insert_failed( $insert_arr, $params );

            if( !$this->has_error() )
                $this->set_error( self::ERR_INSERT, self::_t( 'Failed saving information to database.' ) );

            return false;
        }

        $db_insert_arr = $this->get_empty_data();
        foreach( $insert_arr as $key => $val )
            $db_insert_arr[$key] = $val;

        $insert_arr = $db_insert_arr;

        $insert_arr[$params['table_index']] = $item_id;

        // Set to tell future calls record was just added to database...
        $insert_arr['{new_in_db}'] = true;

        $insert_after_exists = (@method_exists( $this, 'insert_after_'.$params['table_name'] )?true:false);

        if( (
                $insert_after_exists
                and
                !($new_insert_arr = @call_user_func( array( $this, 'insert_after_' . $params['table_name'] ), $insert_arr, $params ))
            )

            or

            (
                !$insert_after_exists
                and
                !($new_insert_arr = $this->insert_after( $insert_arr, $params ))
            )
        )
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

        $edit_prepare_params_exists = (@method_exists( $this, 'get_edit_prepare_params_'.$params['table_name'] )?true:false);

        if( (
                $edit_prepare_params_exists
                and
                !($params = call_user_func( array( $this, 'get_edit_prepare_params_' . $params['table_name'] ), $existing_arr, $params ))
            )

            or

            (
                !$edit_prepare_params_exists
                and
                !($params = $this->get_edit_prepare_params( $existing_arr, $params ))
            )
        )
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

        $full_table_name = $this->get_flow_table_name( $params );

        $edit_arr = $validation_arr['data_arr'];
        if( !empty( $edit_arr )
        and (!($sql = db_quick_edit( $full_table_name, $edit_arr ))
                or !db_query( $sql.' WHERE `'.$full_table_name.'`.`'.$params['table_index'].'` = \''.$existing_arr[$params['table_index']].'\'', $this->get_db_connection( $params ) )
            ) )
        {
            if( @method_exists( $this, 'edit_failed_'.$params['table_name'] ) )
                @call_user_func( array( $this, 'edit_failed_' . $params['table_name'] ), $existing_arr, $edit_arr, $params );
            else
                $this->edit_failed( $existing_arr, $edit_arr, $params );

            if( !$this->has_error() )
                $this->set_error( self::ERR_EDIT, self::_t( 'Failed saving information to database.' ) );

            return false;
        }

        $edit_after_exists = (@method_exists( $this, 'edit_after_'.$params['table_name'] )?true:false);

        if( (
                $edit_after_exists
                and
                !($new_existing_arr = @call_user_func( array( $this, 'edit_after_' . $params['table_name'] ), $existing_arr, $edit_arr, $params ))
            )

            or

            (
                !$edit_after_exists
                and
                !($new_existing_arr = $this->edit_after( $existing_arr, $edit_arr, $params ))
            )
        )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_EDIT, self::_t( 'Failed actions after database edit.' ) );

            return false;
        }

        $existing_arr = $new_existing_arr;

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
            return $this->insert( $params );

        if( !array_key_exists( $params['table_index'], $existing_arr ) )
        {
            $this->set_error( self::ERR_EDIT, self::_t( 'Record doesn\'t have table index as key in result.' ) );
            return false;
        }

        if( !($new_edit_arr = $this->insert_or_edit_editing( $existing_arr, $constrain_arr, $params )) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_INSERT, self::_t( 'Failed actions before database edit.' ) );
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

        $db_connection = $this->get_db_connection( $params );

        if( !($params = $this->get_query_fields( $params ))
         or !($qid = db_query( 'SELECT '.$params['details'].
                               ' FROM '.$this->get_flow_table_name( $params ).
                               ' WHERE '.$params['extra_sql'].
                               (!empty( $params['order_by'] )?' ORDER BY '.$params['order_by']:'').
                               (isset( $params['limit'] )?' LIMIT 0, '.$params['limit']:''), $db_connection ))
         or !($item_count = db_num_rows( $qid, $db_connection )) )
            return false;

        $return_arr = array();
        $return_arr['params'] = $params;
        $return_arr['qid'] = $qid;
        $return_arr['item_count'] = $item_count;

        return $return_arr;
    }

    /**
     * @param array $constrain_arr Conditional db fields
     * @param array|bool $params Parameters in the flow
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

        $db_connection = $this->get_db_connection( $params );

        $id = intval( $id );
        if( empty( $id )
         or !($qid = db_query( 'SELECT '.$params['details'].' FROM `'.$this->get_flow_table_name( $params ).'` '.
                               ' WHERE `'.$params['table_index'].'` = \''.db_escape( $id, $params['db_connection'] ).'\'', $db_connection ))
         or !($item_arr = db_fetch_assoc( $qid, $db_connection )) )
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

        $db_connection = $this->get_db_connection( $params );
        $full_table_name = $this->get_flow_table_name( $params );

        if( empty( $params['extra_sql'] ) )
            $params['extra_sql'] = '';

        // Params used for {linkage} parameter (recurring)...
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
            if( empty( $field_name ) and $field_name !== '0' )
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

            if( !is_numeric( $field_name )
            and strstr( $field_name, '.' ) === false )
                $field_name = '`'.$full_table_name.'`.`'.$field_name.'`';

            if( !is_array( $field_val ) )
            {
                if( $field_val !== false )
                    $params['extra_sql'] .= (!empty( $params['extra_sql'] )?' '.$linkage_func.' ':'').' '.$field_name.' = \''.db_escape( $field_val, $db_connection ).'\' ';
            } else
            {
                if( !isset( $field_val['raw'] ) )
                    $field_val['raw'] = false;
                if( empty( $field_val['field'] ) )
                    $field_val['field'] = $field_name;
                if( empty( $field_val['check'] ) )
                    $field_val['check'] = '=';
                if( !isset( $field_val['value'] ) )
                    $field_val['value'] = false;
                if( !isset( $field_val['raw_value'] ) )
                    $field_val['raw_value'] = false;

                if( !empty( $field_val['raw'] ) )
                    $params['extra_sql'] .= (!empty( $params['extra_sql'] )?' '.$linkage_func.' ':'').$field_val['raw'];

                elseif( $field_val['value'] !== false or $field_val['raw_value'] !== false )
                {
                    $field_val['check'] = trim( $field_val['check'] );
                    if( $field_val['raw_value'] !== false )
                        $check_value = $field_val['raw_value'];
                    elseif( in_array( strtolower( $field_val['check'] ), array( 'in', 'is', 'between' ) ) )
                        $check_value = $field_val['value'];
                    else
                        $check_value = '\''.db_escape( $field_val['value'], $db_connection ).'\'';

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
        // Flags are interpretted in child model and alter extra_sql, join_sql and group_by parameters
        if( empty( $params['flags'] ) or !is_array( $params['flags'] ) )
            $params['flags'] = array();

        // Make sure db_fields index is defined as get_count_list_common_params will work with this index (related to listing method)
        if( empty( $params['db_fields'] ) )
            $params['db_fields'] = '';

        if( ($params = $this->get_count_list_common_params( $params )) === false
         or ($params = $this->get_count_prepare_params( $params )) === false
         or ($params = $this->get_query_fields( $params )) === false )
            return 0;

        if( empty( $params['extra_sql'] ) )
            $params['extra_sql'] = '';

        $db_connection = $this->get_db_connection( $params );

        $distinct_str = '';
        if( $params['count_field'] != '*' )
            $distinct_str = 'DISTINCT ';

        $ret = 0;
        if( ($qid = db_query( 'SELECT COUNT('.$distinct_str.$params['count_field'].') AS total_enregs '.
                              ' FROM `'.$this->get_flow_table_name( $params ).'` '.
                              $params['join_sql'].
                              (!empty( $params['extra_sql'] )?' WHERE '.$params['extra_sql']:'').
                              (!empty( $params['group_by'] )?' GROUP BY '.$params['group_by']:''), $db_connection
            ))
            and ($result = db_fetch_assoc( $qid, $db_connection )) )
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

        $db_connection = $this->get_db_connection( $params );
        $full_table_name = $this->get_flow_table_name( $params );

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
            $params['db_fields'] = '`'.$full_table_name.'`.*';
        if( empty( $params['offset'] ) )
            $params['offset'] = 0;
        if( empty( $params['enregs_no'] ) )
            $params['enregs_no'] = 1000;
        if( empty( $params['order_by'] ) )
            $params['order_by'] = '`'.$full_table_name.'`.`'.$params['table_index'].'` DESC';
        if( empty( $params['group_by'] ) )
            $params['group_by'] = '`'.$full_table_name.'`.`'.$params['table_index'].'`';

        if( empty( $params['fields'] ) )
            $params['fields'] = array();
        // Flags are interpretted in child model and alter extra_sql, join_sql and group_by parameters
        if( empty( $params['flags'] ) or !is_array( $params['flags'] ) )
            $params['flags'] = array();

        if( ($params = $this->get_count_list_common_params( $params )) === false
         or ($params = $this->get_list_prepare_params( $params )) === false
         or ($params = $this->get_query_fields( $params )) === false
         or !($qid = db_query( 'SELECT '.$params['db_fields'].' '.
                              ' FROM `'.$full_table_name.'` '.
                              $params['join_sql'].
                              (!empty( $params['extra_sql'] )?' WHERE '.$params['extra_sql']:'').
                              (!empty( $params['group_by'] )?' GROUP BY '.$params['group_by']:'').
                              (!empty( $params['order_by'] )?' ORDER BY '.$params['order_by']:'').
                              ' LIMIT '.$params['offset'].', '.$params['enregs_no'], $db_connection
                ))
        or !($rows_number = db_num_rows( $qid, $db_connection )) )
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

        if( isset( $common_arr['params'] ) )
            $params = $common_arr['params'];

        $db_connection = $this->get_db_connection( $params );

        $ret_arr = array();
        while( ($item_arr = db_fetch_assoc( $common_arr['qid'], $db_connection )) )
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

    /**
     * Returns an array containing mysql and keys string statement for a table field named $field_name and a structure provided in $field_arr
     *
     * @param string $field_name Name of mysql field
     * @param array $field_details Field details array
     *
     * @return bool|array Returns an array containing mysql statement for provided field and key string (if required) or false on failure
     */
    public function get_mysql_field_definition( $field_name, $field_details )
    {
        $field_details = self::validate_array( $field_details, self::default_field_arr() );

        if( $field_name == self::T_DETAILS_KEY
         or empty( $field_details ) or !is_array( $field_details )
         or !($type_details = self::valid_field_type( $field_details['type'] ))
         or !($field_details = $this->validate_field( $field_details )) )
            return false;

        $field_str = '';
        $keys_str = '';

        if( !empty( $field_details['primary'] ) )
            $keys_str = ' PRIMARY KEY (`'.$field_name.'`)';
        elseif( !empty( $field_details['index'] ) )
            $keys_str = ' KEY `'.$field_name.'` (`'.$field_name.'`)';

        $field_str .= '`'.$field_name.'` '.$type_details['title'];
        if( $field_details['length'] !== null
        and $field_details['length'] !== false
        and (!in_array( $field_details['type'], array( self::FTYPE_DATE, self::FTYPE_DATETIME ) )
                or $field_details['length'] !== 0
            ) )
            $field_str .= '('.$field_details['length'].')';

        if( !empty( $field_details['nullable'] ) )
            $field_str .= ' NULL';
        else
            $field_str .= ' NOT NULL';

        if( !empty( $field_details['auto_increment'] ) )
            $field_str .= ' AUTO_INCREMENT';

        if( empty( $field_details['primary'] )
        and $field_details['type'] != self::FTYPE_DATE )
        {
            if( !empty( $field_details['raw_default'] ) )
                $default_value = $field_details['raw_default'];
            elseif( $field_details['default'] === null )
                $default_value = 'NULL';
            elseif( $field_details['default'] === '' )
                $default_value = '\'\'';
            else
                $default_value = '\''.self::safe_escape( $field_details['default'] ).'\'';

            $field_str .= ' DEFAULT '.$default_value;
        }

        if( !empty( $field_details['comment'] ) )
            $field_str .= ' COMMENT \''.self::safe_escape( $field_details['comment'] ).'\'';

        return array(
            'field_str' => $field_str,
            'keys_str' => $keys_str,
        );
    }

    final public function alter_table_add_column( $field_name, $field_details, $flow_params = false, $params = false )
    {
        $this->reset_error();

        $field_details = self::validate_array( $field_details, self::default_field_arr() );

        if( empty( $field_name )
         or $field_name == self::T_DETAILS_KEY
         or !($flow_params = $this->fetch_default_flow_params( $flow_params ))
         or empty( $field_details ) or !is_array( $field_details )
         or !($field_details = self::validate_field( $field_details ))
         or !($mysql_field_arr = $this->get_mysql_field_definition( $field_name, $field_details ))
         or !($flow_table_name = $this->get_flow_table_name( $flow_params ))
         or empty( $mysql_field_arr['field_str'] ) )
        {
            PHS_Logger::logf( 'Invalid column definition ['.(!empty( $field_name )?$field_name:'???').'].', PHS_Logger::TYPE_MAINTENANCE );

            $this->set_error( self::ERR_ALTER, self::_t( 'Invalid column definition [%s].', (!empty( $field_name )?$field_name:'???') ) );
            return false;
        }

        if( $this->check_column_exists( $field_name, $flow_params ) )
        {
            PHS_Logger::logf( 'Column ['.$field_name.'] already exists.', PHS_Logger::TYPE_MAINTENANCE );

            $this->set_error( self::ERR_ALTER, self::_t( 'Column [%s] already exists.', $field_name ) );
            return false;
        }

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['after_column'] ) or strtolower( trim( $params['after_column'] ) ) == '`first`' )
            $params['after_column'] = ' FIRST';

        else
        {
            if( !$this->check_column_exists( $params['after_column'], $flow_params ) )
            {
                PHS_Logger::logf( 'Column ['.$params['after_column'].'] in alter table statement doesn\'t exist.', PHS_Logger::TYPE_MAINTENANCE );

                $this->set_error( self::ERR_ALTER, self::_t( 'Column [%s] in alter table statement doesn\'t exist.', $params['after_column'] ) );
                return false;
            }

            $params['after_column'] = ' AFTER `'.$params['after_column'].'`';
        }

        $db_connection = $this->get_db_connection( $flow_params );

        if( !db_query( 'ALTER TABLE `'.$flow_table_name.'` ADD COLUMN '.$mysql_field_arr['field_str'].$params['after_column'], $db_connection ) )
        {
            PHS_Logger::logf( 'Error altering table to add column ['.$field_name.'].', PHS_Logger::TYPE_MAINTENANCE );

            $this->set_error( self::ERR_ALTER, self::_t( 'Error altering table to add column [%s].', $field_name ) );
            return false;
        }

        if( !empty( $mysql_field_arr['keys_str'] ) )
        {
            if( !db_query( 'ALTER TABLE `' . $flow_table_name . '` ADD ' . $mysql_field_arr['keys_str'], $db_connection ) )
            {
                PHS_Logger::logf( 'Error altering table to add indexes for ['.$field_name.'].', PHS_Logger::TYPE_MAINTENANCE );

                $this->set_error( self::ERR_ALTER, self::_t( 'Error altering table to add indexes for [%s].', $field_name ) );
                return false;
            }
        }

        // Force reloading table columns to be sure changes are not cached
        $this->get_table_columns_as_definition( $flow_params, true );

        return true;
    }

    final public function alter_table_change_column( $field_name, $field_details, $old_field = false, $flow_params = false, $params = false )
    {
        $this->reset_error();

        $field_details = self::validate_array( $field_details, self::default_field_arr() );

        if( empty( $field_name )
         or $field_name == self::T_DETAILS_KEY
         or !($flow_params = $this->fetch_default_flow_params( $flow_params ))
         or !($flow_table_name = $this->get_flow_table_name( $flow_params ))
         or empty( $field_details ) or !is_array( $field_details )
         or !($field_details = self::validate_field( $field_details ))
         or !($mysql_field_arr = $this->get_mysql_field_definition( $field_name, $field_details ))
         or empty( $mysql_field_arr['field_str'] ) )
        {
            PHS_Logger::logf( 'Invalid column definition ['.(!empty( $field_name )?$field_name:'???').'].', PHS_Logger::TYPE_MAINTENANCE );

            $this->set_error( self::ERR_ALTER, self::_t( 'Invalid column definition [%s].', (!empty( $field_name )?$field_name:'???') ) );
            return false;
        }

        $db_connection = $this->get_db_connection( $flow_params );

        $old_field_name = false;
        $old_field_details = false;
        if( !empty( $old_field ) and is_array( $old_field )
        and !empty( $old_field['name'] )
        and !empty( $old_field['definition'] ) and is_array( $old_field['definition'] )
        and ($old_field_details = self::validate_field( $old_field['definition'] )) )
            $old_field_name = $old_field['name'];

        if( empty( $old_field_name ) )
            $db_old_field_name = $field_name;
        else
            $db_old_field_name = $old_field_name;

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !isset( $params['alter_indexes'] ) )
            $params['alter_indexes'] = true;
        else
            $params['alter_indexes'] = (!empty( $params['alter_indexes'] )?true:false);

        if( empty( $params['after_column'] ) )
            $params['after_column'] = '';

        elseif( strtolower( trim( $params['after_column'] ) ) == '`first`' )
            $params['after_column'] = ' FIRST';

        else
        {
            if( !$this->check_column_exists( $params['after_column'], $flow_params ) )
            {
                PHS_Logger::logf( 'Column ['.$params['after_column'].'] in alter table (change) statement doesn\'t exist in table structure.', PHS_Logger::TYPE_MAINTENANCE );

                $this->set_error( self::ERR_ALTER, self::_t( 'Column [%s] in alter table (change) statement doesn\'t exist in table structure.', $params['after_column'] ) );
                return false;
            }

            $params['after_column'] = ' AFTER `'.$params['after_column'].'`';
        }

        $sql = 'ALTER TABLE `'.$flow_table_name.'` CHANGE `'.$db_old_field_name.'` '.$mysql_field_arr['field_str'].$params['after_column'];
        if( !db_query( $sql, $db_connection ) )
        {
            PHS_Logger::logf( 'Error altering table to change column ['.$field_name.']: ('.$sql.')', PHS_Logger::TYPE_MAINTENANCE );

            $this->set_error( self::ERR_ALTER, self::_t( 'Error altering table to change column [%s].', $field_name ) );
            return false;
        }

        if( !empty( $params['alter_indexes'] )
        and !empty( $old_field_name )
        and !empty( $old_field_details ) and is_array( $old_field_details )
        and empty( $old_field_details['primary'] ) and !empty( $old_field_details['index'] ) )
        {
            if( !db_query( 'ALTER TABLE `' . $flow_table_name . '` DROP KEY `'.$old_field_name.'`', $db_connection ) )
            {
                PHS_Logger::logf( 'Error altering table (change) to drop OLD index for ['.$old_field_name.'].', PHS_Logger::TYPE_MAINTENANCE );

                $this->set_error( self::ERR_ALTER, self::_t( 'Error altering table (change) to drop OLD index for [%s].', $old_field_name ) );
                return false;
            }
        }

        if( !empty( $params['alter_indexes'] )
        and !empty( $mysql_field_arr['keys_str'] ) )
        {
            if( !db_query( 'ALTER TABLE `' . $flow_table_name . '` ADD ' . $mysql_field_arr['keys_str'], $db_connection ) )
            {
                PHS_Logger::logf( 'Error altering table (change) to add indexes for ['.$field_name.'].', PHS_Logger::TYPE_MAINTENANCE );

                $this->set_error( self::ERR_ALTER, self::_t( 'Error altering table (change) to add indexes for [%s].', $field_name ) );
                return false;
            }
        }

        // Force reloading table columns to be sure changes are not cached
        $this->get_table_columns_as_definition( $flow_params, true );

        return true;
    }

    final public function alter_table_drop_column( $field_name, $flow_params = false )
    {
        $this->reset_error();

        if( empty( $field_name )
         or $field_name == self::T_DETAILS_KEY
         or !($flow_params = $this->fetch_default_flow_params( $flow_params )) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Invalid parameters sent to drop column method.' ) );
            return false;
        }

        if( !$this->check_column_exists( $field_name, $flow_params ) )
            return true;

        $db_connection = $this->get_db_connection( $flow_params );

        if( !db_query( 'ALTER TABLE `'.$this->get_flow_table_name( $flow_params ).'` DROP COLUMN `'.$field_name.'`', $db_connection ) )
        {
            $this->set_error( self::ERR_ALTER, self::_t( 'Error altering table to drop column [%s].', $field_name ) );
            return false;
        }

        // Force reloading table columns to be sure changes are not cached
        $this->get_table_columns_as_definition( $flow_params, true );

        return true;
    }

    final public function install_tables()
    {
        $this->reset_error();

        if( !($model_id = $this->instance_id()) )
            return false;

        if( empty( $this->_definition ) or !is_array( $this->_definition ) )
            return true;

        PHS_Logger::logf( 'Installing tables for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE );

        foreach( $this->_definition as $table_name => $table_definition )
        {
            if( !($flow_params = $this->fetch_default_flow_params( array( 'table_name' => $table_name ) ))
             or !($full_table_name = $this->get_flow_table_name( $flow_params )) )
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

    final protected function install_table( $flow_params )
    {
        $this->reset_error();

        if( !($model_id = $this->instance_id()) )
            return false;

        if( empty( $this->_definition ) or !is_array( $this->_definition )
         or !($flow_params = $this->fetch_default_flow_params( $flow_params ))
         or empty( $flow_params['table_name'] )
         or !($full_table_name = $this->get_flow_table_name( $flow_params )) )
        {
            PHS_Logger::logf( 'Setup for model ['.$model_id.'] is invalid.', PHS_Logger::TYPE_MAINTENANCE );

            $this->set_error( self::ERR_TABLE_GENERATE, self::_t( 'Setup for model [%s] is invalid.', $model_id ) );
            return false;
        }

        $table_name = $flow_params['table_name'];

        PHS_Logger::logf( 'Installing table ['.$full_table_name.'] for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE );

        if( empty( $this->_definition[$table_name] ) )
        {
            PHS_Logger::logf( 'Model table ['.$table_name.'] not defined in model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE );

            $this->set_error( self::ERR_TABLE_GENERATE, self::_t( 'Model table [%s] not defined in model [%s].', $table_name, $model_id ) );
            return false;
        }

        $table_definition = $this->_definition[$table_name];

        $db_connection = $this->get_db_connection( $flow_params );

        if( empty( $table_definition[self::T_DETAILS_KEY] ) )
            $table_details = self::default_table_details_arr();
        else
            $table_details = $table_definition[self::T_DETAILS_KEY];

        $sql = 'CREATE TABLE IF NOT EXISTS `'.$full_table_name.'` ( '."\n";
        $all_fields_str = '';
        $keys_str = '';
        foreach( $table_definition as $field_name => $field_details )
        {
            if( !($field_definition = $this->get_mysql_field_definition( $field_name, $field_details ))
             or !is_array( $field_definition ) or empty( $field_definition['field_str'] ) )
                continue;

            $all_fields_str .= ($all_fields_str!=''?', '."\n":'').$field_definition['field_str'];

            if( !empty( $field_definition['keys_str'] ) )
                $keys_str .= ($keys_str!=''?',':'').$field_definition['keys_str'];
        }

        $sql .= $all_fields_str.(!empty( $keys_str )?', '."\n":'').$keys_str.(!empty( $keys_str )?"\n":'').
                ') ENGINE='.$table_details['engine'].
                ' DEFAULT CHARSET='.$table_details['charset'].
                (!empty( $table_details['collate'] )?' COLLATE '.$table_details['collate']:'').
                (!empty( $table_details['comment'] )?' COMMENT=\''.self::safe_escape( $table_details['comment'] ).'\'':'').';';

        if( !db_query( $sql, $db_connection ) )
        {
            PHS_Logger::logf( 'Error generating table ['.$full_table_name.'] for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE );

            $this->set_error( self::ERR_TABLE_GENERATE, self::_t( 'Error generating table %s for model %s.', $full_table_name, $this->instance_id() ) );
            return false;
        }

        // Re-cache table structure...
        $this->get_table_columns_as_definition( $flow_params, true );

        PHS_Logger::logf( 'DONE Installing table ['.$full_table_name.'] for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE );

        return true;
    }

    final public function update_tables()
    {
        $this->reset_error();

        if( !($model_id = $this->instance_id()) )
            return false;

        if( empty( $this->_definition ) or !is_array( $this->_definition ) )
            return true;

        PHS_Logger::logf( 'Updating tables for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE );

        foreach( $this->_definition as $table_name => $table_definition )
        {
            if( !($flow_params = $this->fetch_default_flow_params( array( 'table_name' => $table_name ) ))
             or !($full_table_name = $this->get_flow_table_name( $flow_params )) )
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

    final protected function update_table( $flow_params )
    {
        $this->reset_error();

        if( !($model_id = $this->instance_id()) )
            return false;

        if( empty( $this->_definition ) or !is_array( $this->_definition )
         or !($flow_params = $this->fetch_default_flow_params( $flow_params ))
         or empty( $flow_params['table_name'] )
         or !($full_table_name = $this->get_flow_table_name( $flow_params )) )
        {
            PHS_Logger::logf( 'Setup for model ['.$model_id.'] is invalid.', PHS_Logger::TYPE_MAINTENANCE );

            $this->set_error( self::ERR_UPDATE_TABLE, self::_t( 'Setup for model [%s] is invalid.', $model_id ) );
            return false;
        }

        if( !$this->check_table_exists( $flow_params ) )
            return $this->install_table( $flow_params );

        $table_name = $flow_params['table_name'];

        PHS_Logger::logf( 'Updating table ['.$full_table_name.'] for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE );

        if( empty( $this->_definition[$table_name] ) )
        {
            PHS_Logger::logf( 'Model table ['.$table_name.'] not defined in model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE );

            $this->set_error( self::ERR_UPDATE_TABLE, self::_t( 'Model table [%s] not defined in model [%s].', $table_name, $model_id ) );
            return false;
        }

        $table_definition = $this->_definition[$table_name];
        $db_table_definition = $this->get_table_columns_as_definition( $flow_params );

        // extracting old names so we get quick field definition from old names...
        $old_field_names_arr = array();
        $found_old_field_names_arr = array();
        foreach( $table_definition as $field_name => $field_definition )
        {
            if( empty( $field_definition['old_names'] ) or !is_array( $field_definition['old_names'] ) )
                continue;

            foreach( $field_definition['old_names'] as $old_field_name )
            {
                if( !empty( $found_old_field_names_arr[$old_field_name] ) )
                {
                    PHS_Logger::logf( 'Old field name '.$old_field_name.' found twice in same table model table ['.$table_name.'], model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE );

                    $this->set_error( self::ERR_UPDATE_TABLE,
                                      self::_t( 'Old field name %s found twice in same table model table %s, model %s.', $old_field_name, $table_name, $model_id ) );
                    return false;
                }

                // Check if in current table structure we have this old name...
                if( empty( $db_table_definition[$old_field_name] ) )
                    continue;

                $found_old_field_names_arr[$old_field_name] = true;
                $old_field_names_arr[$field_name] = $old_field_name;
            }
        }

        $db_connection = $this->get_db_connection( $flow_params );

        if( empty( $table_definition[self::T_DETAILS_KEY] ) )
            $table_details = self::default_table_details_arr();
        else
            $table_details = $table_definition[self::T_DETAILS_KEY];

        if( empty( $db_table_details[self::T_DETAILS_KEY] ) )
            $db_table_details = self::default_table_details_arr();
        else
            $db_table_details = $table_definition[self::T_DETAILS_KEY];

        if( ($changed_values = self::table_details_changed( $db_table_details, $table_details )) )
        {
            $sql = 'ALTER TABLE `'.$full_table_name.'`';
            if( !empty( $changed_values['engine'] ) )
                $sql .= ' ENGINE='.$changed_values['engine'];

            if( !empty( $changed_values['charset'] ) or !empty( $changed_values['collate'] ) )
            {
                $sql .= ' DEFAULT CHARSET=';
                if( !empty( $changed_values['charset'] ) )
                    $sql .= $changed_values['charset'];
                else
                    $sql .= $table_details['charset'];

                $sql .= ' COLLATE ';
                if( !empty( $changed_values['collate'] ) )
                    $sql .= $changed_values['collate'];
                else
                    $sql .= $table_details['collate'];
            }

            if( !empty( $changed_values['comment'] ) )
                $sql .= ' COMMENT=\''.self::safe_escape( $table_details['comment'] ).'\'';

            // ALTER TABLE `table_name` ENGINE=INNODB DEFAULT CHARSET=utf8 COLLATE utf8_general_ci COMMENT "New comment"
            if( !db_query( $sql, $db_connection ) )
            {
                PHS_Logger::logf( 'Error updating table properties ['.$full_table_name.'] for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE );

                $this->set_error( self::ERR_TABLE_GENERATE, self::_t( 'Error updating table properties %s for model %s.', $table_name, $this->instance_id() ) );
                return false;
            }
        }

        $after_field = '`first`';
        $fields_found_in_old_structure = array();
        // First we add or remove missing fields
        foreach( $table_definition as $field_name => $field_definition )
        {
            if( $field_name == self::T_DETAILS_KEY )
                continue;

            $field_extra_params = array();
            $field_extra_params['after_column'] = $after_field;

            $after_field = $field_name;

            if( empty( $db_table_definition[$field_name] ) )
            {
                // Field doesn't existin in db structure...
                // Check if we must rename it...
                if( !empty( $old_field_names_arr[$field_name] ) )
                {
                    $fields_found_in_old_structure[$old_field_names_arr[$field_name]] = true;

                    // Yep we rename it...
                    $old_field = array();
                    $old_field['name'] = $old_field_names_arr[$field_name];
                    $old_field['definition'] = $db_table_definition[$old_field_names_arr[$field_name]];

                    if( !$this->alter_table_change_column( $field_name, $field_definition, $old_field, $flow_params, $field_extra_params ) )
                    {
                        if( !$this->has_error() )
                        {
                            PHS_Logger::logf( 'Error changing column '.$old_field_names_arr[$field_name].', table '.$full_table_name.', model '.$model_id.'.', PHS_Logger::TYPE_MAINTENANCE );

                            $this->set_error( self::ERR_UPDATE_TABLE, self::_t( 'Error changing column %s, table %s, model %s.', $old_field_names_arr[$field_name], $full_table_name, $model_id ) );
                        }

                        return false;
                    }

                    continue;
                }

                // Didn't find old fields to rename... Just add it...
                if( !$this->alter_table_add_column( $field_name, $field_definition, $flow_params, $field_extra_params ) )
                {
                    if( !$this->has_error() )
                    {
                        PHS_Logger::logf( 'Error adding column '.$field_name.', table '.$full_table_name.', model '.$model_id.'.', PHS_Logger::TYPE_MAINTENANCE );

                        $this->set_error( self::ERR_UPDATE_TABLE, self::_t( 'Error adding column %s, table %s, model %s.', $field_name, $full_table_name, $model_id ) );
                    }

                    return false;
                }

                continue;
            }

            $fields_found_in_old_structure[$field_name] = true;

            $alter_params = $field_extra_params;
            $alter_params['alter_indexes'] = false;

            // Call alter table anyway as position might change...
            if( !$this->alter_table_change_column( $field_name, $field_definition, false, $flow_params, $alter_params ) )
            {
                if( !$this->has_error() )
                {
                    PHS_Logger::logf( 'Error updating column '.$field_name.', table '.$full_table_name.', model '.$model_id.'.', PHS_Logger::TYPE_MAINTENANCE );

                    $this->set_error( self::ERR_UPDATE_TABLE, self::_t( 'Error updating column %s, table %s, model %s.', $field_name, $full_table_name, $model_id ) );
                }

                return false;
            }
        }

        // Delete fields which we didn't find in new structure
        foreach( $db_table_definition as $field_name => $junk )
        {
            if( $field_name == self::T_DETAILS_KEY
             or !empty( $fields_found_in_old_structure[$field_name] ) )
                continue;

            if( !$this->alter_table_drop_column( $field_name, $flow_params ) )
            {
                if( !$this->has_error() )
                {
                    PHS_Logger::logf( 'Error dropping column '.$field_name.', table '.$full_table_name.', model '.$model_id.'.', PHS_Logger::TYPE_MAINTENANCE );

                    $this->set_error( self::ERR_UPDATE_TABLE, self::_t( 'Error dropping column %s, table %s, model %s.', $field_name, $full_table_name, $model_id ) );
                }

                return false;
            }
        }

        // Force reloading table columns to be sure changes are not cached
        $this->get_table_columns_as_definition( $flow_params, true );

        PHS_Logger::logf( 'DONE Updating table ['.$full_table_name.'] for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE );

        return true;
    }

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

        if( $this_instance_id == $plugins_model_id )
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

        $check_arr = array();
        $check_arr['instance_id'] = $this_instance_id;

        db_supress_errors( $this->_plugins_instance->get_db_connection() );
        if( !($db_details = $this->_plugins_instance->get_details_fields( $check_arr ))
         or empty( $db_details['type'] )
         or $db_details['type'] != self::INSTANCE_TYPE_MODEL )
        {
            db_restore_errors_state( $this->_plugins_instance->get_db_connection() );

            PHS_Logger::logf( 'Model doesn\'t seem to be installed. ['.$this_instance_id.']', PHS_Logger::TYPE_MAINTENANCE );

            return true;
        }

        db_restore_errors_state( $this->_plugins_instance->get_db_connection() );

        PHS_Logger::logf( 'Triggering uninstall signal ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

        $this->signal_trigger( self::SIGNAL_UNINSTALL );

        PHS_Logger::logf( 'DONE triggering uninstall signal ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

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
    public function uninstall_tables()
    {
        $this->reset_error();

        if( empty( $this->_definition ) or !is_array( $this->_definition )
         or !($flow_params = $this->fetch_default_flow_params()) )
            return true;

        PHS_Logger::logf( 'Uninstalling tables for model ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

        foreach( $this->_definition as $table_name => $table_definition )
        {
            $flow_params['table_name'] = $table_name;

            $db_connection = $this->get_db_connection( $flow_params );
            $full_table_name = $this->get_flow_table_name( $flow_params );
            if( empty( $full_table_name ) )
                continue;

            $sql = 'DROP TABLE IF EXISTS `'.$full_table_name.'`;';

            if( !db_query( $sql, $db_connection ) )
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

        PHS_Logger::logf( 'Updating model ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

        if( !$this->custom_update( $old_version, $new_version ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_UPDATE, self::_t( 'Model custom update functionality failed.' ) );

            PHS_Logger::logf( '!!! Error in model custom update functionality. ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

            return false;
        }

        if( !$this->_load_plugins_instance() )
        {
            PHS_Logger::logf( '!!! Error instantiating plugins model. ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

            if( !$this->has_error() )
                $this->set_error( self::ERR_UPDATE, self::_t( 'Error instantiating plugins model.' ) );
            return false;
        }

        // This will only create non-existing tables...
        if( !$this->update_tables() )
            return false;

        $plugin_details = array();
        $plugin_details['instance_id'] = $this_instance_id;
        $plugin_details['plugin'] = $this->instance_plugin_name();
        $plugin_details['type'] = $this->instance_type();
        $plugin_details['is_core'] = ($this->instance_is_core() ? 1 : 0);
        $plugin_details['version'] = $this->get_model_version();

        if( !($db_details = $this->_plugins_instance->update_db_details( $plugin_details ))
         or empty( $db_details['new_data'] ) )
        {
            if( $this->_plugins_instance->has_error() )
                $this->copy_error( $this->_plugins_instance );
            else
                $this->set_error( self::ERR_UPDATE, self::_t( 'Error saving model details to database.' ) );

            PHS_Logger::logf( '!!! Error ['.$this->get_error_message().'] ['.$this->instance_id().']', PHS_Logger::TYPE_MAINTENANCE );

            return false;
        }

        return $db_details['new_data'];
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
