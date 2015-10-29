<?php

abstract class PHS_Model extends PHS_Language
{
    const MODEL_BASE_VERSION = '1.0.0';
    const ERR_MODEL_FIELDS = 1000, ERR_STATIC_INSTANCE = 1001;

    const FTYPE_UNKNOWN = 0,
          FTYPE_TINYINT = 1, FTYPE_SMALLINT = 2, FTYPE_MEDIUMINT = 3, FTYPE_INT = 4, FTYPE_BIGINT = 5, FTYPE_DECIMAL = 6, FTYPE_FLOAT = 7, FTYPE_DOUBLE = 8, FTYPE_REAL = 9,
          FTYPE_DATE = 10, FTYPE_DATETIME = 11, FTYPE_TIMESTAMP = 12,
          FTYPE_VARCHAR = 13, FTYPE_TEXT = 14, FTYPE_MEDIUMTEXT = 15, FTYPE_LONGTEXT = 16,
          FTYPE_BINARY = 17, FTYPE_VARBINARY = 18,
          FTYPE_TINYBLOB = 19, FTYPE_MEDIUMBLOB = 20, FTYPE_BLOB = 21, FTYPE_LONGBLOB = 22,
          FTYPE_ENUM = 23;

    protected static $_definition = array();
    private static $instances = array();

    private static $FTYPE_ARR = array(
        self::FTYPE_TINYINT => array( 'title' => 'tinyint', 'default_length' => 4, 'default_value' => 0 ),
        self::FTYPE_SMALLINT => array( 'title' => 'smallint', 'default_length' => 6, 'default_value' => 0, ),
        self::FTYPE_MEDIUMINT => array( 'title' => 'mediumint', 'default_length' => 9, 'default_value' => 0, ),
        self::FTYPE_INT => array( 'title' => 'int', 'default_length' => 11, 'default_value' => 0, ),
        self::FTYPE_BIGINT => array( 'title' => 'bigint', 'default_length' => 20, 'default_value' => 0, ),
        self::FTYPE_DECIMAL => array( 'title' => 'decimal', 'default_length' => '5,2', 'default_value' => 0, ),
        self::FTYPE_FLOAT => array( 'title' => 'float', 'default_length' => '5,2', 'default_value' => 0, ),
        self::FTYPE_DOUBLE => array( 'title' => 'double', 'default_length' => '5,2', 'default_value' => 0, ),

        self::FTYPE_DATE => array( 'title' => 'date', 'default_length' => null, 'default_value' => null, ),
        self::FTYPE_DATETIME => array( 'title' => 'datetime', 'default_length' => 0, 'default_value' => null, ),
        self::FTYPE_TIMESTAMP => array( 'title' => 'timestamp', 'default_length' => 0, 'default_value' => null, ),

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

    /**
     * @return array of string Returns an array of strings containing all tables that model will handle
     */
    abstract function get_all_table_names();

    /**
     * @param array|false $params Parameters in the flow
     *
     * @return string Returns main table name used (table name can be passed to $params array of each method in 'table_name' index)
     */
    abstract function get_table_name( $params = false );

    /**
     * @param array|false $params Parameters in the flow
     *
     * @return array Returns an array with table fields
     */
    abstract protected function fields_definition( $params = false );

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

    static public function default_field_arr()
    {
        return array(
            'type' => self::FTYPE_UNKNOWN,
            'length' => null,
            'primary' => false,
            'auto_increment' => false,
            'index' => false,
            'default' => null,
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

        if( !($model_fields = $this->fields_definition( $params ))
         or !is_array( $model_fields ) )
        {
            $this->set_error( self::ERR_MODEL_FIELDS, self::_t( 'Invalid fields definition for table %s.', $params['table_name'] ) );
            return false;
        }

        self::$_definition[$params['table_name']] = array();
        foreach( $model_fields as $field_name => $field_arr )
        {
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

            if( !isset( $new_field_arr['default'] )
            and isset( $field_details['default_value'] ) )
                $new_field_arr['default'] = $field_details['default_value'];

            if( !empty( $new_field_arr['primary'] ) )
                $new_field_arr['default'] = null;

            self::$_definition[$params['table_name']][$field_name] = $new_field_arr;
        }

        return true;
    }

    function __construct()
    {
        parent::__construct();
        $this->validate_tables_definition();
    }

    public static function get_instance( $module = null, $singleton = true )
    {
        self::st_reset_error();

        if( is_null( $module ) )
            $module = get_called_class();

        if( empty( $module )
         or strtolower( substr( $module, 0, 10 ) ) != 'phs_model_' )
        {
            self::st_set_error( self::ERR_STATIC_INSTANCE, self::_t( 'Couldn\'t instantiate model.' ) );
            return false;
        }

        /** @var PHS_Model $module_instance */
        if( !empty( $singleton )
        and isset( self::$instances[$module] ) )
            $module_instance = self::$instances[$module];
        else
        {
            $module_instance = new $module();

            if( $module_instance->has_error() )
            {
                self::st_copy_error( $module_instance );
                return false;
            }
        }

        if( !($module_instance instanceof PHS_Model) )
        {
            self::st_set_error( self::ERR_STATIC_INSTANCE, self::_t( 'Loaded class doesn\'t appear to be a PHS model.' ) );
            return false;
        }

        if( !empty( $singleton ) )
        {
            self::$instances[$module] = $module_instance;
            return self::$instances[$module];
        }

        return $module_instance;
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

        if( empty( $params['table_index'] ) or empty( $params['table_name'] ) or !isset( $params['db_connection'] ) )
            return false;

        return $params;
    }

    private function get_details_common( $constrain_arr, $params = false )
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
     * @return Generator|null array of records matching conditions acting as generator
     */
    function get_details_fields_gen( $constrain_arr, $params = false )
    {
        if( !($common_arr = $this->get_details_common( $constrain_arr, $params ))
         or !is_array( $common_arr ) or empty( $common_arr['qid'] ) )
            return;

        if( $params['result_type'] == 'single' )
            yield db_fetch_assoc( $common_arr['qid'], $params['db_connection'] );

        else
        {
            while( ($row_arr = db_fetch_assoc( $common_arr['qid'], $params['db_connection'] )) )
            {
                yield $row_arr;
            }
        }
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

    private function get_list_common( $params = false )
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

    public function get_list_gen( $params = false )
    {
        if( !($common_arr = $this->get_list_common( $params ))
         or !is_array( $common_arr ) or empty( $common_arr['qid'] ) )
            return;

        while( ($item_arr = db_fetch_assoc( $common_arr['qid'], $params['db_connection'] )) )
        {
            yield $item_arr;
        }
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

    public function install_tables()
    {
        if( empty( self::$_definition ) or !is_array( self::$_definition )
         or !($flow_params = $this->fetch_default_flow_params()) )
            return true;

        foreach( self::$_definition as $table_name => $table_definition )
        {
            $flow_params['table_name'] = $table_name;

            if( !($db_connection = $this->get_db_connection( $flow_params ))
             or !($db_settings = db_settings( $db_connection ))
             or !is_array( $db_settings ) )
                continue;

            if( empty( $db_settings['prefix'] ) )
                $db_settings['prefix'] = '';

            $sql = 'CREATE TABLE IF NOT EXISTS `'.$db_settings['prefix'].$table_name.'` ( ';
        //`id` int(11) NOT NULL AUTO_INCREMENT,
        //        `method_id` int(11) NOT NULL DEFAULT '0',
        //        `enabled` tinyint(2) NOT NULL DEFAULT '0',
        //        `surcharge_percent` decimal(6,2) NOT NULL DEFAULT '0.00',
        //        `surcharge_amount` decimal(6,2) NOT NULL DEFAULT '0.00' COMMENT 'Amount of surcharge',
        //        `surcharge_currency` varchar(3) DEFAULT NULL COMMENT 'ISO 3 currency code of fixed surcharge amount',
        //        `priority` tinyint(4) NOT NULL DEFAULT '10' COMMENT '1 means first',
        //        `last_update` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
        //        `configured` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
        //        PRIMARY KEY (`id`), KEY `method_id` (`method_id`), KEY `enabled` (`enabled`)
        //        ) ENGINE="._MYSQL_ENGINE_." DEFAULT CHARSET=utf8 COMMENT='Smart2Pay method configurations';
        }

        return true;
    }
}
