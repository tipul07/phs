<?php

namespace phs\libraries;

use \phs\PHS;
use \phs\PHS_Db;

abstract class PHS_Model_Mongo extends PHS_Model_Core_Base
{
    const FTYPE_DOUBLE = 1, FTYPE_STRING = 2, FTYPE_OBJECT = 3, FTYPE_ARRAY = 4, FTYPE_BINARY_DATA = 5, FTYPE_UNDEFINED = 6,
          FTYPE_OBJECT_ID = 7, FTYPE_BOOLEAN = 8, FTYPE_DATE = 9, FTYPE_NULL = 10, FTYPE_REGULAR_EXPRESSION = 11, FTYPE_JAVASCRIPT = 12,
          FTYPE_SYMBOL = 13, FTYPE_SCOPE_JAVASCRIPT = 14, FTYPE_INTEGER = 15, FTYPE_TIMESTAMP = 16, FTYPE_MIN_KEY = 17, FTYPE_MAX_KEY = 18;

    private static $FTYPE_ARR = array(

        self::FTYPE_DOUBLE => array( 'title' => 'Double', 'type_ids' => array( 1 ), 'default_value' => 0, ),
        self::FTYPE_STRING => array( 'title' => 'String', 'type_ids' => array( 2 ), 'default_value' => '', ),
        self::FTYPE_OBJECT => array( 'title' => 'Object', 'type_ids' => array( 3 ), 'default_value' => null, ),
        self::FTYPE_ARRAY => array( 'title' => 'Array', 'type_ids' => array( 4 ), 'default_value' => array(), ),
        self::FTYPE_BINARY_DATA => array( 'title' => 'Binary data', 'type_ids' => array( 5 ), 'default_value' => '', ),
        self::FTYPE_UNDEFINED => array( 'title' => 'Undefined', 'type_ids' => array( 6 ), 'default_value' => 'undefined', ),
        self::FTYPE_OBJECT_ID => array( 'title' => 'Object Id', 'type_ids' => array( 7 ), 'default_value' => '', ),
        self::FTYPE_BOOLEAN => array( 'title' => 'Boolean', 'type_ids' => array( 9 ), 'default_value' => false, ),
        self::FTYPE_DATE => array( 'title' => 'Date', 'type_ids' => array( 10 ), 'default_value' => null, ),
        self::FTYPE_NULL => array( 'title' => 'Null', 'type_ids' => array( 11 ), 'default_value' => null, ),
        self::FTYPE_REGULAR_EXPRESSION => array( 'Regular Expression' => 'Integer', 'type_ids' => array( 12 ), 'default_value' => null, ),
        self::FTYPE_JAVASCRIPT => array( 'title' => 'JavaScript', 'type_ids' => array( 13 ), 'default_value' => '', ),
        self::FTYPE_SYMBOL => array( 'title' => 'Symbol', 'type_ids' => array( 14 ), 'default_value' => '', ),
        self::FTYPE_SCOPE_JAVASCRIPT => array( 'title' => 'JavaScript with scope', 'type_ids' => array( 15 ), 'default_value' => '', ),
        self::FTYPE_INTEGER => array( 'title' => 'Integer', 'type_ids' => array( 16, 18 ), 'default_value' => 0, ),
        self::FTYPE_TIMESTAMP => array( 'title' => 'Timestamp', 'type_ids' => array( 10 ), 'default_value' => 0, ),
        self::FTYPE_MIN_KEY => array( 'title' => 'Min key', 'type_ids' => array( 255 ), 'default_value' => '', ),
        self::FTYPE_MAX_KEY => array( 'title' => 'Max key', 'type_ids' => array( 127 ), 'default_value' => '', ),
    );

    //
    //  region Abstract model specific methods
    //
    /**
     * @inheritdoc
     */
    public function get_model_driver()
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
    public function get_primary_key( $params = false )
    {
        return '_id';
    }

    /**
     * @inheritdoc
     *
     * Default primary key a hash, override this method if otherwise
     */
    public function prepare_primary_key( $id, $params = false )
    {
        return trim( $id );
    }

    /**
     * @inheritdoc
     */
    public function get_field_types()
    {
        return self::$FTYPE_ARR;
    }

    /**
     * @inheritdoc
     */
    public function valid_field_type( $type )
    {
        if( empty( $type )
         or !($fields_arr = $this->get_field_types())
         or empty( $fields_arr[$type] ) or !is_array( $fields_arr[$type] ) )
            return false;

        return $fields_arr[$type];
    }

    /**
     * @inheritdoc
     */
    protected function _get_details_for_model( $id, $params = false )
    {
        $this->reset_error();

        if( !($params = $this->fetch_default_flow_params( $params )) )
            return false;

        $db_connection = $this->get_db_connection( $params );

        /** @var \phs\libraries\PHS_Db_mongo $mongo_driver */
        if( empty( $id )
         or !($mongo_driver = PHS_Db::db( $db_connection )) )
            return false;

        $id_obj = false;
        try
        {
            if( @class_exists( '\\MongoDB\\BSON\\ObjectID', false ) )
                $id_obj = new \MongoDB\BSON\ObjectID( $id );

            elseif( @class_exists( '\\MongoDB\\BSON\\ObjectId', false ) )
                $id_obj = new \MongoDB\BSON\ObjectId( $id );
        } catch( \Exception $e )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Cannot obtain Object Id instance.' ) );
            return false;
        }

        if( empty( $id_obj ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Cannot obtain Object Id instance.' ) );
            return false;
        }

        $query_arr = $mongo_driver::default_query_arr();
        $query_arr['table_name'] = $this->get_flow_table_name( $params );
        $query_arr['filter'] = array(
            $params['table_index'] => $id_obj,
        );
        $query_arr['query_options']['limit'] = 1;

        if( !($qid = $mongo_driver->query( $query_arr, $db_connection ))
         or !($item_arr = $mongo_driver->fetch_assoc( $qid )) )
            return false;

        return $item_arr;
    }

    /**
     * @inheritdoc
     */
    protected function _get_details_fields_for_model( $constrain_arr, $params = false )
    {
        if( !($params = $this->fetch_default_flow_params( $params ))
         or !($common_arr = $this->get_details_common( $constrain_arr, $params ))
         or !is_array( $common_arr )
         or (empty( $params['return_query'] ) and empty( $common_arr['qid'] )) )
            return false;

        if( !empty( $params['return_query'] ) )
            return $common_arr;

        if( !empty( $common_arr['params'] ) )
            $params = $common_arr['params'];

        //var_dump( $common_arr['qid'] );
        //var_dump( $params );

        /** @var \MongoDB\Driver\Cursor $qid */
        $qid = $common_arr['qid'];

        if( $params['result_type'] == 'single' )
        {
            try {
                if( !($result_arr = $qid->toArray())
                 or !is_array( $result_arr ) or empty( $result_arr[0] ) )
                    return false;

                return $result_arr[0];
            } catch( \Exception $e )
            {
                return false;
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
    protected function _check_table_exists_for_model( $flow_params = false, $force = false )
    {
        $this->reset_error();

        if( !($flow_params = $this->fetch_default_flow_params( $flow_params ))
         or !($flow_table_name = $this->get_flow_table_name( $flow_params ))
         or !($my_driver = $this->get_model_driver()) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Failed validating flow parameters.' ) );
            return false;
        }

        db_supress_errors( $this->get_db_connection( $flow_params ) );
        if( (empty( self::$tables_arr[$my_driver] ) or !empty( $force ))
        and ($qid = db_query( 'SHOW TABLES', $this->get_db_connection( $flow_params ) )) )
        {
            self::$tables_arr[$my_driver] = array();
            while( ($table_name = @mysqli_fetch_assoc( $qid )) )
            {
                if( !is_array( $table_name ) )
                    continue;

                $table_arr = array_values( $table_name );
                self::$tables_arr[$my_driver][$table_arr[0]] = array();

                self::$tables_arr[$my_driver][$table_arr[0]][self::T_DETAILS_KEY] = $this->_parse_mysql_table_details( $table_arr[0] );
            }
        }

        db_restore_errors_state( $this->get_db_connection( $flow_params ) );

        if( is_array( self::$tables_arr[$my_driver] )
        and array_key_exists( $flow_table_name, self::$tables_arr[$my_driver] ) )
            return true;

        return false;
    }

    protected function _install_table_for_model( $flow_params )
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

        PHS_Logger::logf( 'Installing table ['.$full_table_name.'] for model ['.$model_id.']['.$this->get_model_driver().']', PHS_Logger::TYPE_MAINTENANCE );

        if( empty( $this->_definition[$table_name] ) )
        {
            PHS_Logger::logf( 'Model table ['.$table_name.'] not defined in model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE );

            $this->set_error( self::ERR_TABLE_GENERATE, self::_t( 'Model table [%s] not defined in model [%s].', $table_name, $model_id ) );
            return false;
        }

        $table_definition = $this->_definition[$table_name];

        $db_connection = $this->get_db_connection( $flow_params );

        if( empty( $table_definition[self::T_DETAILS_KEY] ) )
            $table_details = $this->_default_table_details_arr();
        else
            $table_details = $table_definition[self::T_DETAILS_KEY];

        $sql = 'CREATE TABLE IF NOT EXISTS `'.$full_table_name.'` ( '."\n";
        $all_fields_str = '';
        $keys_str = '';
        foreach( $table_definition as $field_name => $field_details )
        {
            if( !($field_definition = $this->_get_mysql_field_definition( $field_name, $field_details ))
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

        if( !$this->_create_table_extra_indexes( $flow_params ) )
            return false;

        // Re-cache table structure...
        $this->get_table_columns_as_definition( $flow_params, true );

        PHS_Logger::logf( 'DONE Installing table ['.$full_table_name.'] for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE );

        return true;
    }

    protected function _update_table_for_model( $flow_params )
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
            if( $field_name == self::T_DETAILS_KEY
             or $field_name == self::EXTRA_INDEXES_KEY
             or !is_array( $field_definition )
             or empty( $field_definition['old_names'] ) or !is_array( $field_definition['old_names'] ) )
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
            $table_details = $this->_default_table_details_arr();
        else
            $table_details = $table_definition[self::T_DETAILS_KEY];

        if( empty( $db_table_details[self::T_DETAILS_KEY] ) )
            $db_table_details = $this->_default_table_details_arr();
        else
            $db_table_details = $table_definition[self::T_DETAILS_KEY];

        if( ($changed_values = $this->_table_details_changed( $db_table_details, $table_details )) )
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
            if( $field_name == self::T_DETAILS_KEY
             or $field_name == self::EXTRA_INDEXES_KEY )
                continue;

            $field_extra_params = array();
            $field_extra_params['after_column'] = $after_field;

            $after_field = $field_name;

            if( empty( $db_table_definition[$field_name] ) )
            {
                // Field doesn't exist in in db structure...
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
             or $field_name == self::EXTRA_INDEXES_KEY
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

        // Check extra indexes...
        if( !empty( $table_definition[self::EXTRA_INDEXES_KEY] )
         or !empty( $db_table_definition[self::EXTRA_INDEXES_KEY] ) )
        {
            if( !empty( $table_definition[self::EXTRA_INDEXES_KEY] )
            and empty( $db_table_definition[self::EXTRA_INDEXES_KEY] ) )
            {
                // new extra indexes
                if( !$this->_create_table_extra_indexes( $flow_params ) )
                    return false;
            } elseif( empty( $table_definition[self::EXTRA_INDEXES_KEY] )
                  and !empty( $db_table_definition[self::EXTRA_INDEXES_KEY] ) )
            {
                // delete existing extra indexes
                if( !$this->delete_table_extra_indexes_from_array( $db_table_definition[self::EXTRA_INDEXES_KEY], $flow_params ) )
                    return false;
            } else
            {
                // do the diff on extra indexes...
                $current_indexes = array();
                foreach( $db_table_definition[self::EXTRA_INDEXES_KEY] as $index_name => $index_arr )
                {
                    if( empty( $index_arr['fields'] ) or !is_array( $index_arr['fields'] ) )
                        $index_arr['fields'] = array();

                    if( array_key_exists( $index_name, $table_definition[self::EXTRA_INDEXES_KEY] )
                    and !empty( $table_definition[self::EXTRA_INDEXES_KEY][$index_name]['fields'] )
                    and is_array( $table_definition[self::EXTRA_INDEXES_KEY][$index_name]['fields'] )
                    and !($index_arr['unique'] xor $table_definition[self::EXTRA_INDEXES_KEY][$index_name]['unique'])
                    and self::arrays_have_same_values( $index_arr['fields'], $table_definition[self::EXTRA_INDEXES_KEY][$index_name]['fields'] ) )
                    {
                        $current_indexes[$index_name] = true;
                        continue;
                    }

                    $this->delete_table_extra_index( $index_name, $flow_params );
                }

                // add new extra indexes after we did the diff with existing ones...
                foreach( $table_definition[self::EXTRA_INDEXES_KEY] as $index_name => $index_arr )
                {
                    if( !empty( $current_indexes[$index_name] ) )
                        continue;

                    if( !$this->_create_table_extra_index( $index_name, $index_arr, $flow_params ) )
                        return false;
                }
            }
        }

        // Force reloading table columns to be sure changes are not cached
        $this->get_table_columns_as_definition( $flow_params, true );

        PHS_Logger::logf( 'DONE Updating table ['.$full_table_name.'] for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE );

        return true;
    }

    protected function _update_missing_table_for_model( $flow_params )
    {
        return $this->_install_table_for_model( $flow_params );
    }

    /**
     * @inheritdoc
     */
    protected function _uninstall_table_for_model( $flow_params )
    {
        $this->reset_error();

        if( empty( $this->_definition ) or !is_array( $this->_definition )
         or !($flow_params = $this->fetch_default_flow_params( $flow_params ))
         or !($db_connection = $this->get_db_connection( $flow_params ))
         or !($full_table_name = $this->get_flow_table_name( $flow_params )) )
            return true;

        if( !db_query( 'DROP TABLE IF EXISTS `'.$full_table_name.'`;', $db_connection ) )
        {
            $this->set_error( self::ERR_UNINSTALL_TABLE, self::_t( 'Error dropping table %s.', $full_table_name ) );
            return false;
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    protected function _get_table_columns_as_definition_for_model( $flow_params = false, $force = false )
    {
        $this->reset_error();

        if( !($flow_params = $this->fetch_default_flow_params( $flow_params ))
         or !($flow_table_name = $this->get_flow_table_name( $flow_params ))
         or !($flow_database_name = $this->get_db_database( $flow_params ))
         or !($my_driver = $this->get_model_driver()) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Failed validating flow parameters.' ) );
            return false;
        }

        if( ($qid = db_query( 'SHOW FULL COLUMNS FROM `'.$flow_table_name.'`', $flow_params['db_connection'] )) )
        {
            while( ($field_arr = db_fetch_assoc( $qid, $flow_params['db_connection'] )) )
            {
                if( !is_array( $field_arr )
                 or empty( $field_arr['Field'] ) )
                    continue;

                self::$tables_arr[$my_driver][$flow_table_name][$field_arr['Field']] = $this->_parse_mysql_field_result( $field_arr );
            }
        }

        // Get extra indexes...
        if( ($qid = db_query( 'SELECT * FROM information_schema.statistics '.
                              ' WHERE '.
                              ' table_schema = \''.$flow_database_name.'\' AND table_name = \''.$flow_table_name.'\''.
                              ' AND SEQ_IN_INDEX > 1', $flow_params['db_connection'] )) )
        {
            while( ($index_arr = db_fetch_assoc( $qid, $flow_params['db_connection'] )) )
            {
                if( !is_array( $index_arr )
                 or empty( $index_arr['INDEX_NAME'] )
                 or empty( $index_arr['COLUMN_NAME'] ) )
                    continue;

                if( empty( self::$tables_arr[$my_driver][$flow_table_name][self::EXTRA_INDEXES_KEY] )
                 or !is_array( self::$tables_arr[$my_driver][$flow_table_name][self::EXTRA_INDEXES_KEY] ) )
                    self::$tables_arr[$my_driver][$flow_table_name][self::EXTRA_INDEXES_KEY] = array();

                if( empty( self::$tables_arr[$my_driver][$flow_table_name][self::EXTRA_INDEXES_KEY][$index_arr['INDEX_NAME']] )
                 or !is_array( self::$tables_arr[$my_driver][$flow_table_name][self::EXTRA_INDEXES_KEY][$index_arr['INDEX_NAME']] ) )
                    self::$tables_arr[$my_driver][$flow_table_name][self::EXTRA_INDEXES_KEY][$index_arr['INDEX_NAME']] = array();

                if( empty( self::$tables_arr[$my_driver][$flow_table_name][self::EXTRA_INDEXES_KEY][$index_arr['INDEX_NAME']]['fields'] )
                 or !is_array( self::$tables_arr[$my_driver][$flow_table_name][self::EXTRA_INDEXES_KEY][$index_arr['INDEX_NAME']]['fields'] ) )
                    self::$tables_arr[$my_driver][$flow_table_name][self::EXTRA_INDEXES_KEY][$index_arr['INDEX_NAME']]['fields'] = array();

                self::$tables_arr[$my_driver][$flow_table_name][self::EXTRA_INDEXES_KEY][$index_arr['INDEX_NAME']]['fields'][] = $index_arr['COLUMN_NAME'];

                if( empty( $index_arr['NON_UNIQUE'] ) )
                    self::$tables_arr[$my_driver][$flow_table_name][self::EXTRA_INDEXES_KEY][$index_arr['INDEX_NAME']]['unique'] = true;
                else
                    self::$tables_arr[$my_driver][$flow_table_name][self::EXTRA_INDEXES_KEY][$index_arr['INDEX_NAME']]['unique'] = false;
            }
        }

        return self::$tables_arr[$my_driver][$flow_table_name];
    }

    /**
     * @inheritdoc
     */
    protected function _hard_delete_for_model( $existing_data, $params = false )
    {
        self::st_reset_error();
        $this->reset_error();

        if( empty( $existing_data ) or !is_array( $existing_data )
         or !($params = $this->fetch_default_flow_params( $params ))
         or empty( $params['table_index'] )
         or !isset( $existing_data[$params['table_index']] ) )
            return false;

        $db_connection = $this->get_db_connection( $params['db_connection'] );

        $result = false;
        if( db_query( 'DELETE FROM `'.$this->get_flow_table_name( $params ).'` WHERE `'.$params['table_index'].'` = \''.db_escape( $existing_data[$params['table_index']], $db_connection ).'\'', $db_connection ) )
            $result = true;

        return $result;
    }

    /**
     * @inheritdoc
     */
    protected function _default_table_details_arr()
    {
        return array();
    }

    /**
     * @inheritdoc
     */
    protected function _default_table_extra_index_arr()
    {
        return array(
            'unique' => false,
            'fields' => array(),
        );
    }

    /**
     * @inheritdoc
     */
    protected function _validate_field( $field_arr )
    {
        if( empty( $field_arr ) or !is_array( $field_arr ) )
            $field_arr = array();

        $def_values = self::_default_field_arr();
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
         or !($field_details = $this->valid_field_type( $field_arr['type'] )) )
            return false;

        if( isset( $field_details['nullable'] ) )
            $field_arr['nullable'] = (!empty( $field_details['nullable'] )?true:false);

        if( $field_arr['default'] === null
        and isset( $field_details['default_value'] ) )
            $field_arr['default'] = $field_details['default_value'];

        if( empty( $field_arr['raw_default'] )
        and !empty( $field_details['raw_default'] ) )
            $field_arr['raw_default'] = $field_details['raw_default'];

        return $field_arr;
    }

    /**
     * @inheritdoc
     */
    protected function _validate_field_value( $value, $field_name, $field_details, $params = false )
    {
        $this->reset_error();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $field_name ) )
            $field_name = self::_t( 'N/A' );

        if( !($field_details = $this->_validate_field( $field_details ))
         or empty( $field_details['type'] )
         or !($field_type_arr = $this->valid_field_type( $field_details['type'] )) )
        {
            self::st_set_error( self::ERR_MODEL_FIELDS, self::_t( 'Couldn\'t validate field %s.', $field_name ) );
            return false;
        }

        $phs_params_arr = array();
        $phs_params_arr['trim_before'] = true;

        switch( $field_details['type'] )
        {
            case self::FTYPE_INTEGER:
                if( ($value = PHS_Params::set_type( $value, PHS_Params::T_INT, $phs_params_arr )) === null )
                    $value = 0;
            break;

            case self::FTYPE_DATE:
                if( empty_db_date( $value ) )
                    $value = null;
                else
                    $value = @date( self::DATE_DB, parse_db_date( $value ) );
            break;

            case self::FTYPE_DOUBLE:

                $digits = 0;
                if( !empty( $field_details['length'] )
                and is_string( $field_details['length'] ) )
                {
                    $length_arr = explode( ',', $field_details['length'] );
                    $digits = (!empty( $length_arr[1] )?intval(trim( $length_arr[1] )):0);
                }

                $phs_params_arr['digits'] = $digits;

                if( ($value = PHS_Params::set_type( $value, PHS_Params::T_FLOAT, $phs_params_arr )) === null )
                    $value = 0;
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
     * @param array|bool $params Parameters in the flow
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
     * @return array|bool Returns data array added in database (with changes, if required) or false if record should be deleted from database.
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
     * @param array|bool $params Parameters in the flow
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
     * @param array|bool $params Parameters in the flow
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
     * @param array|bool $params Parameters in the flow
     *
     * @return array Flow parameters array
     */
    protected function get_count_list_common_params( $params = false )
    {
        return $params;
    }
    //
    //  endregion Methods that will be overridden in child classes
    //

    //
    // region Database structure methods
    //
    private function _parse_mysql_table_details( $table_name, $flow_params = false )
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

        $table_details = $this->_default_table_details_arr();
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

    private static function _default_mysql_table_field_fields()
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

    private function _get_type_from_mysql_field_type( $type )
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
        and ($field_types = $this->get_field_types())
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

        if( !($field_arr = $this->valid_field_type( $return_arr['type'] )) )
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

    private function _parse_mysql_field_result( $field_arr )
    {
        $field_arr = self::validate_array( $field_arr, self::_default_mysql_table_field_fields() );
        $model_field_arr = self::_default_field_arr();

        if( !($model_field_type = $this->_get_type_from_mysql_field_type( $field_arr['Type'] )) )
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

    public function check_extra_index_exists( $index_name, $flow_params = false, $force = false )
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

        if( empty( $table_definition[self::EXTRA_INDEXES_KEY] )
         or !array_key_exists( $index_name, $table_definition[self::EXTRA_INDEXES_KEY] ) )
            return false;

        return $table_definition[self::EXTRA_INDEXES_KEY][$index_name];
    }

    private static function _default_field_arr()
    {
        // if 'default_value' is set in field definition that value will be used for 'default' key
        return array(
            'type' => self::FTYPE_UNDEFINED,
            'editable' => true,
            'default' => null,
            'raw_default' => null,
            'nullable' => false,
            'comment' => '',
        );
    }

    private function _fields_changed( $field1_arr, $field2_arr )
    {
        if( !($field1_arr = $this->_validate_field( $field1_arr ))
         or !($field2_arr = $this->_validate_field( $field2_arr )) )
            return true;

        if( (int)$field1_arr['type'] !== (int)$field2_arr['type']
         // for lengths with comma
         or str_replace( ' ', '', $field1_arr['length'] ) !== str_replace( ' ', '', $field2_arr['length'] )
         or $field1_arr['primary'] !== $field2_arr['primary']
         or $field1_arr['auto_increment'] !== $field2_arr['auto_increment']
         or $field1_arr['index'] !== $field2_arr['index']
         or $field1_arr['default'] !== $field2_arr['default']
         or $field1_arr['nullable'] !== $field2_arr['nullable']
         or trim( $field1_arr['comment'] ) !== trim( $field2_arr['comment'] )
        )
            return true;

        return false;
    }

    private function _table_details_changed( $details1_arr, $details2_arr )
    {
        $default_table_details = $this->_default_table_details_arr();

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
        $hook_params['driver'] = $this->get_model_driver();
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
        $has_raw_fields = false;
        foreach( $table_fields as $field_name => $field_details )
        {
            if( empty( $field_details['editable'] )
            and $params['action'] === 'edit' )
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
                    and array_key_exists( 'value', $params['fields'][$field_name] ) )
                        $field_value['value'] = $this->_validate_field_value( $params['fields'][$field_name]['value'], $field_name, $field_details );
                }

                $data_arr[$field_name] = $field_value;
                $validated_fields[] = $field_name;
            } elseif( isset( $field_details['default'] )
                  and $params['action'] === 'insert' )
                // When editing records only passed fields will be saved in database...
                $data_arr[$field_name] = $field_details['default'];
        }

        $return_arr = array();
        $return_arr['has_raw_fields'] = $has_raw_fields;
        $return_arr['data_arr'] = $data_arr;
        $return_arr['validated_fields'] = $validated_fields;

        return $return_arr;
    }

    /**
     * Returns an array containing mysql and keys string statement for a table field named $field_name and a structure provided in $field_arr
     *
     * @param string $field_name Name of mysql field
     * @param array $field_details Field details array
     *
     * @return bool|array Returns an array containing mysql statement for provided field and key string (if required) or false on failure
     */
    private function _get_mysql_field_definition( $field_name, $field_details )
    {
        $field_details = self::validate_array( $field_details, self::_default_field_arr() );

        if( $field_name == self::T_DETAILS_KEY
         or $field_name == self::EXTRA_INDEXES_KEY
         or empty( $field_details ) or !is_array( $field_details )
         or !($type_details = $this->valid_field_type( $field_details['type'] ))
         or !($field_details = $this->_validate_field( $field_details )) )
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

        $field_details = self::validate_array( $field_details, self::_default_field_arr() );

        if( empty( $field_name )
         or $field_name == self::T_DETAILS_KEY
         or $field_name == self::EXTRA_INDEXES_KEY
         or !($flow_params = $this->fetch_default_flow_params( $flow_params ))
         or empty( $field_details ) or !is_array( $field_details )
         or !($field_details = $this->_validate_field( $field_details ))
         or !($mysql_field_arr = $this->_get_mysql_field_definition( $field_name, $field_details ))
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

        $field_details = self::validate_array( $field_details, self::_default_field_arr() );

        if( empty( $field_name )
         or $field_name == self::T_DETAILS_KEY
         or $field_name == self::EXTRA_INDEXES_KEY
         or !($flow_params = $this->fetch_default_flow_params( $flow_params ))
         or !($flow_table_name = $this->get_flow_table_name( $flow_params ))
         or empty( $field_details ) or !is_array( $field_details )
         or !($field_details = $this->_validate_field( $field_details ))
         or !($mysql_field_arr = $this->_get_mysql_field_definition( $field_name, $field_details ))
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
        and ($old_field_details = $this->_validate_field( $old_field['definition'] )) )
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
         or $field_name == self::EXTRA_INDEXES_KEY
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

    protected function _create_table_extra_indexes( $flow_params )
    {
        $this->reset_error();

        if( !($model_id = $this->instance_id())
         or empty( $this->_definition ) or !is_array( $this->_definition )
         or !($flow_params = $this->fetch_default_flow_params( $flow_params ))
         or empty( $flow_params['table_name'] )
         or empty( $this->_definition[$flow_params['table_name']] )
         or !($full_table_name = $this->get_flow_table_name( $flow_params )) )
            return false;

        $table_definition = $this->_definition[$flow_params['table_name']];

        if( empty( $table_definition[self::EXTRA_INDEXES_KEY] )
         or !is_array( $table_definition[self::EXTRA_INDEXES_KEY] )
         or !($database_name = $this->get_db_database( $flow_params )) )
            return true;

        foreach( $table_definition[self::EXTRA_INDEXES_KEY] as $index_name => $index_arr )
        {
            if( !$this->_create_table_extra_index( $index_name, $index_arr, $flow_params ) )
                return false;
        }

        return true;
    }

    public function create_table_extra_indexes_from_array( $indexes_array, $flow_params = false )
    {
        $this->reset_error();

        if( empty( $indexes_array ) or !is_array( $indexes_array ) )
            return true;

        foreach( $indexes_array as $index_name => $index_arr )
        {
            if( empty( $index_arr ) or !is_array( $index_arr ) )
                continue;

            if( !$this->_create_table_extra_index( $index_name, $index_arr, $flow_params ) )
                return false;
        }

        return true;
    }

    private function _create_table_extra_index( $index_name, $index_arr, $flow_params = false )
    {
        $this->reset_error();

        if( !($model_id = $this->instance_id())
         or empty( $index_name )
         or empty( $index_arr ) or !is_array( $index_arr )
         or !($flow_params = $this->fetch_default_flow_params( $flow_params ))
         or !($index_arr = $this->validate_table_extra_index( $index_arr ))
         or empty( $index_arr['fields'] ) or !is_array( $index_arr['fields'] )
         or empty( $flow_params['table_name'] )
         or empty( $this->_definition[$flow_params['table_name']] )
         or !($full_table_name = $this->get_flow_table_name( $flow_params ))
         or !($database_name = $this->get_db_database( $flow_params )) )
        {
            PHS_Logger::logf( 'Error creating extra index bad parameters sent to method for model ['.(!empty( $model_id )?$model_id:'N/A').'].', PHS_Logger::TYPE_MAINTENANCE );

            $this->set_error( self::ERR_TABLE_GENERATE, self::_t( 'Error creating extra index for model %s.', (!empty( $model_id )?$model_id:'N/A') ) );
            return false;
        }

        $db_connection = $this->get_db_connection( $flow_params );

        $fields_str = '';
        foreach( $index_arr['fields'] as $field_name )
        {
            $fields_str .= ($fields_str!=''?',':'').'`'.$field_name.'`';
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
        //     PHS_Logger::logf( 'Error creating extra index ['.$index_name.'] for table ['.$full_table_name.'] for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE );
        //
        //     $this->set_error( self::ERR_TABLE_GENERATE, self::_t( 'Error creating extra index %s for table %s for model %s.', $index_name, $full_table_name, $this->instance_id() ) );
        //     return false;
        // }

        if( ($qid = db_query( 'SELECT DISTINCT index_name '.
                               ' FROM information_schema.statistics '.
                               ' WHERE table_schema = \''.$database_name.'\' AND table_name = \''.$full_table_name.'\' '.
                               ' AND index_name LIKE \''.$index_name.'\'', $db_connection ))
        and @mysqli_num_rows( $qid ) )
        {
            PHS_Logger::logf( 'Extra index ['.$index_name.'] for table ['.$full_table_name.'] for model ['.$model_id.'] already exists.', PHS_Logger::TYPE_MAINTENANCE );

            $this->set_error( self::ERR_TABLE_GENERATE, self::_t( 'Extra index %s for table %s for model %s already exists.', $index_name, $full_table_name, $this->instance_id() ) );
            return false;
        }

        if( !db_query( 'CREATE '.(!empty( $index_arr['unique'] )?'UNIQUE':'').' INDEX `'.$index_name.'` ON `'.$full_table_name.'` ('.$fields_str.')', $db_connection ) )
        {
            PHS_Logger::logf( 'Error creating extra index ['.$index_name.'] for table ['.$full_table_name.'] for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE );

            $this->set_error( self::ERR_TABLE_GENERATE, self::_t( 'Error creating extra index %s for table %s for model %s.', $index_name, $full_table_name, $this->instance_id() ) );
            return false;
        }

        return true;
    }

    protected function delete_table_extra_indexes_from_array( $indexes_array, $flow_params = false )
    {
        $this->reset_error();

        if( empty( $indexes_array ) or !is_array( $indexes_array ) )
            return true;

        foreach( $indexes_array as $index_name => $index_arr )
        {
            if( empty( $index_arr ) or !is_array( $index_arr ) )
                continue;

            if( !$this->delete_table_extra_index( $index_name, $flow_params ) )
                return false;
        }

        return true;
    }

    public function delete_table_extra_index( $index_name, $flow_params = false )
    {
        $this->reset_error();

        if( !($model_id = $this->instance_id())
         or empty( $index_name )
         or !($flow_params = $this->fetch_default_flow_params( $flow_params ))
         or empty( $flow_params['table_name'] )
         or !($full_table_name = $this->get_flow_table_name( $flow_params ))
         or !($database_name = $this->get_db_database( $flow_params )) )
        {
            PHS_Logger::logf( 'Error deleting extra index bad parameters sent to method for model ['.(!empty( $model_id )?$model_id:'N/A').'].', PHS_Logger::TYPE_MAINTENANCE );

            $this->set_error( self::ERR_TABLE_GENERATE, self::_t( 'Error deleting extra index for model %s.', (!empty( $model_id )?$model_id:'N/A') ) );
            return false;
        }

        $db_connection = $this->get_db_connection( $flow_params );

        if( !db_query( 'ALTER TABLE `'.$full_table_name.'` DROP INDEX `'.$index_name.'`', $db_connection ) )
        {
            PHS_Logger::logf( 'Error deleting extra index ['.$index_name.'] for table ['.$full_table_name.'] for model ['.$model_id.']', PHS_Logger::TYPE_MAINTENANCE );

            $this->set_error( self::ERR_TABLE_GENERATE, self::_t( 'Error creating extra index %s for table %s for model %s.', $index_name, $full_table_name, $this->instance_id() ) );
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
        $db_connection = $this->get_db_connection( $params );

        if( !($sql = db_quick_insert( $this->get_flow_table_name( $params ), $insert_arr, $db_connection ))
         or !($item_id = db_query_insert( $sql, $db_connection )) )
        {
            if( @method_exists( $this, 'insert_failed_'.$params['table_name'] ) )
                @call_user_func( array( $this, 'insert_failed_' . $params['table_name'] ), $insert_arr, $params );
            else
                $this->insert_failed( $insert_arr, $params );

            if( !$this->has_error() )
                $this->set_error( self::ERR_INSERT, self::_t( 'Failed saving information to database.' ) );

            return false;
        }

        if( !empty( $validation_arr['has_raw_fields'] ) )
        {
            // there are raw fields, so we query for existing data in table...
            if( !($db_insert_arr = $this->get_details( $item_id, $params )) )
            {
                if( !$this->has_error() )
                    $this->set_error( self::ERR_INSERT, self::_t( 'Failed saving information to database.' ) );

                return false;
            }
        } else
        {
            $db_insert_arr = $this->get_empty_data( $params );
            foreach( $insert_arr as $key => $val )
                $db_insert_arr[$key] = $val;
        }

        $insert_arr = $db_insert_arr;

        $insert_arr[$params['table_index']] = $item_id;

        // Set to tell future calls record was just added to database...
        $insert_arr[self::RECORD_NEW_INSERT_KEY] = true;

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

    public function record_is_new( $record_arr )
    {
        if( empty( $record_arr ) or !is_array( $record_arr )
            or empty( $record_arr[self::RECORD_NEW_INSERT_KEY] ) )
            return false;

        return true;
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
            $this->set_error( self::ERR_EDIT, self::_t( 'Existing record not found in database.' ) );
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
         or !isset( $validation_arr['data_arr'] ) or !is_array( $validation_arr['data_arr'] ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_EDIT, self::_t( 'Error validating parameters.' ) );
            return false;
        }

        $full_table_name = $this->get_flow_table_name( $params );
        $db_connection = $this->get_db_connection( $params );

        $new_existing_arr = $existing_arr;

        $edit_arr = $validation_arr['data_arr'];
        if( !empty( $edit_arr )
        and (!($sql = db_quick_edit( $full_table_name, $edit_arr, $db_connection ))
                or !db_query( $sql.' WHERE `'.$full_table_name.'`.`'.$params['table_index'].'` = \''.$existing_arr[$params['table_index']].'\'', $db_connection )
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
            if( !empty( $validation_arr['has_raw_fields'] ) )
            {
                // there are raw fields, so we query for existing data in table...
                if( !($existing_arr = $this->get_details( $existing_arr['id'], $params )) )
                {
                    if( !$this->has_error() )
                        $this->set_error( self::ERR_INSERT, self::_t( 'Failed saving information to database.' ) );

                    return false;
                }
            } else
            {
                foreach( $edit_arr as $key => $val )
                    $existing_arr[$key] = $val;
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
    //
    //  endregion CRUD functionality
    //

    //
    //  region Querying database functionality
    //
    protected function get_details_common( $constrain_arr, $params = false )
    {
        if( !($params = $this->fetch_default_flow_params( $params )) )
            return false;

        if( empty( $params['query_fields'] ) or !is_array( $params['query_fields'] ) )
            $params['query_fields'] = array();

        if( !isset( $params['result_type'] ) )
            $params['result_type'] = 'single';
        if( !isset( $params['result_key'] ) )
            $params['result_key'] = $params['table_index'];
        if( !isset( $params['return_query'] ) )
            $params['return_query'] = false;
        else
            $params['return_query'] = (!empty( $params['return_query'] )?true:false);

        if( !isset( $params['limit'] )
         or $params['result_type'] == 'single' )
            $params['limit'] = 1;
        else
        {
            $params['limit'] = intval( $params['limit'] );
            $params['result_type'] = 'list';
        }

        $db_connection = $this->get_db_connection( $params );

        /** @var \phs\libraries\PHS_Db_mongo $mongo_driver */
        if( empty( $constrain_arr ) or !is_array( $constrain_arr )
         or !($mongo_driver = PHS_Db::db( $db_connection )) )
            return false;

        if( empty( $params['query_fields']['read_preference'] ) or !is_array( $params['query_fields']['read_preference'] ) )
            $params['query_fields']['read_preference'] = false;

        if( empty( $params['query_fields']['query_options'] ) or !is_array( $params['query_fields']['query_options'] ) )
            $params['query_fields']['query_options'] = $mongo_driver::default_query_options_arr();

        $params['query_fields']['query_options']['limit'] = $params['limit'];

        $query_arr = $mongo_driver::default_query_arr();
        $query_arr['table_name'] = $this->get_flow_table_name( $params );
        $query_arr['filter'] = $constrain_arr;
        $query_arr['query_options'] = $params['query_fields']['query_options'];

        if( !empty( $params['query_fields']['read_preference'] ) and is_array( $params['query_fields']['read_preference'] ) )
            $query_arr['read_preference'] = $params['query_fields']['read_preference'];
        if( !empty( $params['query_fields']['cursor_type_map'] ) and is_array( $params['query_fields']['cursor_type_map'] ) )
            $query_arr['cursor_type_map'] = $params['query_fields']['cursor_type_map'];

        $qid = false;
        $item_count = 0;

        if( empty( $params['return_query'] )
        and (!($qid = $mongo_driver->query( $query_arr, $db_connection ))
                // or !($item_count = $mongo_driver->num_rows( $qid ))
            ) )
            return false;

        $return_arr = array();
        $return_arr['query'] = $query_arr;
        $return_arr['params'] = $params;
        $return_arr['qid'] = $qid;
        $return_arr['item_count'] = $item_count;

        return $return_arr;
    }

    public static function get_count_default_params()
    {
        return array(
            'count_field' => '*',
            'extra_sql' => '',
            'join_sql' => '',
            'group_by' => '',

            'db_fields' => '',

            'fields' => array(),

            'flags' => array(),
        );
    }

    public function get_count( $params = false )
    {
        $this->reset_error();

        if( !($params = $this->fetch_default_flow_params( $params ))
         or !($params = self::validate_array( $params, self::get_count_default_params() ))
         or ($params = $this->get_count_list_common_params( $params )) === false
         or ($params = $this->get_count_prepare_params( $params )) === false
         or ($params = $this->get_query_fields( $params )) === false )
            return 0;

        if( empty( $params['extra_sql'] ) )
            $params['extra_sql'] = '';

        if( !isset( $params['return_query'] ) )
            $params['return_query'] = false;
        else
            $params['return_query'] = (!empty( $params['return_query'] )?true:false);

        $db_connection = $this->get_db_connection( $params );

        $distinct_str = '';
        if( $params['count_field'] != '*' )
            $distinct_str = 'DISTINCT ';

        $sql = 'SELECT COUNT('.$distinct_str.$params['count_field'].') AS total_enregs '.
               ' FROM `'.$this->get_flow_table_name( $params ).'` '.
               $params['join_sql'].
               (!empty( $params['extra_sql'] )?' WHERE '.$params['extra_sql']:'').
               (!empty( $params['group_by'] )?' GROUP BY '.$params['group_by']:'').
               (!empty( $params['having_sql'] )?' HAVING '.$params['having_sql']:'');

        if( !empty( $params['return_query'] ) )
        {
            $return_arr = array();
            $return_arr['query'] = $sql;
            $return_arr['params'] = $params;

            return $return_arr;
        }

        $ret = 0;
        if( ($qid = db_query( $sql, $db_connection ))
        and ($result = db_fetch_assoc( $qid, $db_connection )) )
        {
            $ret = $result['total_enregs'];
        }

        return $ret;
    }

    public static function get_list_default_params()
    {
        return array(
            'get_query_id' => false,
            // will get populated in get_list_common
            'arr_index_field' => '',

            'extra_sql' => '',
            'join_sql' => '',
            'having_sql' => '',
            'group_by' => '',
            'order_by' => '',

            'db_fields' => '',

            'offset' => 0,
            'enregs_no' => 1000,

            'fields' => array(),

            'flags' => array(),
        );
    }

    protected function get_list_common( $params = false )
    {
        $this->reset_error();

        if( !($params = $this->fetch_default_flow_params( $params ))
         or !($params = self::validate_array( $params, self::get_list_default_params() )) )
            return false;

        $db_connection = $this->get_db_connection( $params );
        $full_table_name = $this->get_flow_table_name( $params );

        if( !isset( $params['return_query'] ) )
            $params['return_query'] = false;
        else
            $params['return_query'] = (!empty( $params['return_query'] )?true:false);

        // Field which will be used as key in result array (be sure is unique)
        if( empty( $params['arr_index_field'] ) )
            $params['arr_index_field'] = $params['table_index'];

        if( ($params = $this->get_count_list_common_params( $params )) === false
         or ($params = $this->get_list_prepare_params( $params )) === false )
            return false;

        $sql = 'SELECT '.$params['db_fields'].' '.
               ' FROM `'.$full_table_name.'` '.
               $params['join_sql'].
               (!empty( $params['extra_sql'] )?' WHERE '.$params['extra_sql']:'').
               (!empty( $params['group_by'] )?' GROUP BY '.$params['group_by']:'').
               (!empty( $params['having_sql'] )?' HAVING '.$params['having_sql']:'').
               (!empty( $params['order_by'] )?' ORDER BY '.$params['order_by']:'').
               ' LIMIT '.$params['offset'].', '.$params['enregs_no'];

        $qid = false;
        $rows_number = 0;

        if( empty( $params['return_query'] )
        and (!($qid = db_query( $sql, $db_connection ))
                or !($rows_number = db_num_rows( $qid, $db_connection ))
            ) )
            return false;

        $return_arr = array();
        $return_arr['query'] = $sql;
        $return_arr['params'] = $params;
        $return_arr['qid'] = $qid;
        $return_arr['item_count'] = $rows_number;

        return $return_arr;
    }

    public function get_list( $params = false )
    {
        $this->reset_error();

        if( !($common_arr = $this->get_list_common( $params ))
         or !is_array( $common_arr ) or empty( $common_arr['qid'] )
         or (empty( $params['return_query'] ) and empty( $common_arr['qid'] )) )
            return false;

        if( !empty( $params['return_query'] ) )
            return $common_arr;

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

    static public function safe_escape( $str, $char = '\'' )
    {
        return str_replace( $char, '\\'.$char, str_replace( '\\'.$char, $char, $str ) );
    }
    //
    //  endregion Querying database functionality
    //
}
