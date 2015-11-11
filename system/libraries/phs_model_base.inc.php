<?php

abstract class PHS_Model_Core_Base extends PHS_Instantiable
{
    // Default Model version. This constant should be overwritten by child class (model)
    const MODEL_VERSION = '1.0.0';

    // DON'T OVERWRITE THIS CONSTANT. IT REPRESENTS BASE MODEL VERSION
    const MODEL_BASE_VERSION = '1.0.0';

    const ERR_MODEL_FIELDS = 1000, ERR_STATIC_INSTANCE = 1001, ERR_TABLE_GENERATE = 1002, ERR_INSTALL = 1003, ERR_INSERT = 1004;

    const FTYPE_UNKNOWN = 0,
          FTYPE_TINYINT = 1, FTYPE_SMALLINT = 2, FTYPE_MEDIUMINT = 3, FTYPE_INT = 4, FTYPE_BIGINT = 5, FTYPE_DECIMAL = 6, FTYPE_FLOAT = 7, FTYPE_DOUBLE = 8, FTYPE_REAL = 9,
          FTYPE_DATE = 10, FTYPE_DATETIME = 11, FTYPE_TIMESTAMP = 12,
          FTYPE_VARCHAR = 13, FTYPE_TEXT = 14, FTYPE_MEDIUMTEXT = 15, FTYPE_LONGTEXT = 16,
          FTYPE_BINARY = 17, FTYPE_VARBINARY = 18,
          FTYPE_TINYBLOB = 19, FTYPE_MEDIUMBLOB = 20, FTYPE_BLOB = 21, FTYPE_LONGBLOB = 22,
          FTYPE_ENUM = 23;

    const DATE_EMPTY = '0000-00-00', DATETIME_EMPTY = '0000-00-00 00:00:00',
          DATE_DB = 'Y-m-d', DATETIME_DB = 'Y-m-d H:i:s';

    const HOOK_RAW_PARAMETERS = 'phs_model_raw_parameters', HOOK_INSERT_BEFORE_DB = 'phs_model_insert_before_db',
          HOOK_TABLES = 'phs_model_tables', HOOK_TABLE_FIELDS = 'phs_model_table_fields';

    protected static $_definition = array();

    private $model_tables_arr = array();

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

    const T_DETAILS_KEY = '<details>';

    /**
     * @return array of string Returns an array of strings containing tables that model will handle
     */
    abstract public function get_table_names();

    /**
     * @return string Returns main table name used when calling insert with no table name
     */
    abstract function get_main_table_name();

    /**
     * @param array|false $params Parameters in the flow
     *
     * @return array Returns an array with table fields
     */
    abstract protected function fields_definition( $params = false );

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
     * @param array $insert_arr Data array which should be added to database
     * @param array $params Flow parameters
     */
    protected function insert_failed( $insert_arr, $params )
    {
    }

    /**
     * Called right after a successfull insert in database. Some model must do more database work after successfully adding records in database or eventually chaining
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

    static public function safe_escape( $str, $char = '\'' )
    {
        return str_replace( $char, '\\'.$char, str_replace( '\\'.$char, $char, $str ) );
    }

    static public function default_field_arr()
    {
        return array(
            'type' => self::FTYPE_UNKNOWN,
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
                $new_field_arr['default'] = null;

            self::$_definition[$params['table_name']][$field_name] = $new_field_arr;
        }

        if( empty( self::$_definition[$params['table_name']][self::T_DETAILS_KEY] ) )
            self::$_definition[$params['table_name']][self::T_DETAILS_KEY] = self::default_table_details_arr();

        return true;
    }

    function __construct( $class_name, $plugin = false )
    {
        parent::__construct( $class_name, $plugin );

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
                $value = @date( self::DATE_DB, parse_db_date( $value ) );
            break;

            case self::FTYPE_DATETIME:
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

    protected function validate_data_for_fields( $params )
    {

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
            if( array_key_exists( $field_name, $params['fields'] ) )
            {
                $data_arr[$field_name] = self::validate_field_value( $params['fields'][ $field_name ], $field_name, $field_details );
                $validated_fields[] = $field_name;
            } elseif( isset( $field_details['default'] ) )
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
            $this->set_error( self::ERR_INSERT, self::_t( 'Bad parameters.' ) );
            return false;
        }

        $params['action'] = 'insert';

        if( !($params = $this->get_insert_prepare_params( $params )) )
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

        if( !($new_insert_arr = $this->insert_after( $insert_arr, $params )) )
        {
            // TODO: Move all queries to a higher level so we can have database connections with different drivers...
            db_query( 'DELETE FROM `'.$params['table_name'].'` WHERE `'.$params['table_index'].'` = \''.$item_id.'\'', $params['db_connection'] );

            if( !$this->has_error() )
                $this->set_error( self::ERR_INSERT, self::_t( 'Failed actions after database insert.' ) );
            return false;
        }

        $insert_arr = $new_insert_arr;

        return $insert_arr;
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
     * @return array|false|Generator|null Returns single record as array (first matching conditions), array of records matching conditions or acts as generator
     */
    function get_details_fields( $constrain_arr, $params = false )
    {
        if( !($common_arr = $this->get_details_common( $constrain_arr, $params ))
         or !is_array( $common_arr ) or empty( $common_arr['qid'] ) )
            return false;

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
        if( !empty( $params['fields']['<linkage_func>'] )
        and in_array( strtolower( $params['fields']['<linkage_func>'] ), self::linkage_db_functions() ) )
            $linkage_func = strtoupper( $params['fields']['<linkage_func>'] );

        if( isset( $params['fields']['<linkage_func>'] ) )
            unset( $params['fields']['<linkage_func>'] );

        foreach( $params['fields'] as $field_name => $field_val )
        {
            $field_name = trim( $field_name );
            if( empty( $field_name ) )
                continue;

            if( $field_name == '<linkage>' )
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
            $params['db_fields'] = '`'.$params['table_index'].'`.*';
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

    public function install()
    {
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

        if( !$this->install_tables() )
            return false;

        /** @var PHS_Model_Plugins $plugins_model */
        if( $this_instance_id == $plugins_model_id )
            $plugins_model = $this;

        elseif( !($plugins_model = PHS::load_model( 'plugins' )) )
        {
            $this->set_error( self::ERR_INSTALL, self::_t( 'Error instantiating plugins model.' ) );
            return false;
        }

        $fields_arr = array();
        $fields_arr['instance_id'] = $this_instance_id;
        $fields_arr['is_core'] = ($this->instance_is_core()?1:0);
        $fields_arr['status'] = PHS_Model_Plugins::STATUS_INSTALLED;

        $insert_arr = array();
        $insert_arr['fields'] = $fields_arr;

        return $plugins_model->insert( $insert_arr );
    }

    public function install_tables()
    {
        $this->reset_error();

        if( empty( self::$_definition ) or !is_array( self::$_definition )
         or !($flow_params = $this->fetch_default_flow_params()) )
            return true;

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
                $this->set_error( self::ERR_TABLE_GENERATE, self::_t( 'Error generating table %s.', $table_name ) );
                return false;
            }
        }

        return true;
    }
}
