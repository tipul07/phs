<?php

namespace phs\libraries;

use \phs\PHS;
use \phs\PHS_Db;

abstract class PHS_Model_Mysqli extends PHS_Model_Core_Base
{
    const FTYPE_UNKNOWN = 0,
          FTYPE_TINYINT = 1, FTYPE_SMALLINT = 2, FTYPE_MEDIUMINT = 3, FTYPE_INT = 4, FTYPE_BIGINT = 5, FTYPE_DECIMAL = 6, FTYPE_FLOAT = 7, FTYPE_DOUBLE = 8, FTYPE_REAL = 9,
          FTYPE_DATE = 10, FTYPE_DATETIME = 11, FTYPE_TIMESTAMP = 12,
          FTYPE_CHAR = 13, FTYPE_VARCHAR = 14, FTYPE_TEXT = 15, FTYPE_MEDIUMTEXT = 16, FTYPE_LONGTEXT = 17,
          FTYPE_BINARY = 18, FTYPE_VARBINARY = 19,
          FTYPE_TINYBLOB = 20, FTYPE_MEDIUMBLOB = 21, FTYPE_BLOB = 22, FTYPE_LONGBLOB = 23,
          FTYPE_ENUM = 24;

    private static $FTYPE_ARR = [
        self::FTYPE_TINYINT => [
            'title' => 'tinyint', 'default_length' => 4, 'default_value' => 0,
            'max_value' => [ 'signed' => '127', 'unsigned' => '255' ],
            'min_value' => [ 'signed' => '-128', 'unsigned' => '0' ],
        ],
        self::FTYPE_SMALLINT => [
            'title' => 'smallint', 'default_length' => 6, 'default_value' => 0,
            'max_value' => [ 'signed' => '32767', 'unsigned' => '65535' ],
            'min_value' => [ 'signed' => '-32768', 'unsigned' => '0' ],
        ],
        self::FTYPE_MEDIUMINT => [
            'title' => 'mediumint', 'default_length' => 9, 'default_value' => 0,
            'max_value' => [ 'signed' => '8388607', 'unsigned' => '16777215' ],
            'min_value' => [ 'signed' => '-8388608', 'unsigned' => '0' ],
        ],
        self::FTYPE_INT => [
            'title' => 'int', 'default_length' => 11, 'default_value' => 0,
            'max_value' => [ 'signed' => '2147483647', 'unsigned' => '4294967295' ],
            'min_value' => [ 'signed' => '-2147483648', 'unsigned' => '0' ],
        ],
        self::FTYPE_BIGINT => [
            'title' => 'bigint', 'default_length' => 20, 'default_value' => 0,
            'max_value' => [ 'signed' => '9223372036854775807', 'unsigned' => '18446744073709551615' ],
            'min_value' => [ 'signed' => '-9223372036854775808', 'unsigned' => '0' ],
        ],
        self::FTYPE_DECIMAL => [ 'title' => 'decimal', 'default_length' => '5,2', 'default_value' => 0.0, ],
        self::FTYPE_FLOAT => [ 'title' => 'float', 'default_length' => '5,2', 'default_value' => 0.0, ],
        self::FTYPE_DOUBLE => [ 'title' => 'double', 'default_length' => '5,2', 'default_value' => 0.0, ],

        self::FTYPE_DATE => [ 'title' => 'date', 'default_length' => null, 'default_value' => null, 'nullable' => true, ],
        self::FTYPE_DATETIME => [ 'title' => 'datetime', 'default_length' => null, 'default_value' => null, 'nullable' => true, ],
        self::FTYPE_TIMESTAMP => [ 'title' => 'timestamp', 'default_length' => 0, 'default_value' => 0, 'nullable' => true, ],

        self::FTYPE_CHAR => [ 'title' => 'char', 'default_length' => 255, 'default_value' => '', 'max_bytes' => 255, 'nullable' => true, ],
        self::FTYPE_VARCHAR => [ 'title' => 'varchar', 'default_length' => 255, 'default_value' => '', 'max_bytes' => 255, 'nullable' => true, ],
        self::FTYPE_TEXT => [ 'title' => 'text', 'default_length' => null, 'default_value' => null, 'max_bytes' => 65535, 'nullable' => true, ],
        self::FTYPE_MEDIUMTEXT => [ 'title' => 'mediumtext', 'default_length' => null, 'default_value' => null, 'max_bytes' => 16777215, 'nullable' => true, ],
        self::FTYPE_LONGTEXT => [ 'title' => 'longtext', 'default_length' => null, 'default_value' => null, 'max_bytes' => 4294967295, 'nullable' => true, ],

        self::FTYPE_BINARY => [ 'title' => 'binary', 'default_length' => 255, 'default_value' => null, 'nullable' => true, ],
        self::FTYPE_VARBINARY => [ 'title' => 'varbinary', 'default_length' => 255, 'default_value' => null, 'nullable' => true, ],

        self::FTYPE_TINYBLOB => [ 'title' => 'tinyblob', 'default_length' => null, 'default_value' => null, 'nullable' => true, ],
        self::FTYPE_MEDIUMBLOB => [ 'title' => 'mediumblob', 'default_length' => null, 'default_value' => null, 'nullable' => true, ],
        self::FTYPE_BLOB => [ 'title' => 'blob', 'default_length' => null, 'default_value' => null, 'nullable' => true, ],
        self::FTYPE_LONGBLOB => [ 'title' => 'longblob', 'default_length' => null, 'default_value' => null, 'nullable' => true, ],

        self::FTYPE_ENUM => [ 'title' => 'enum', 'default_length' => '', 'default_value' => null, 'nullable' => true, ], ];

    const RECORD_NEW_INSERT_KEY = '{new_in_db}';

    //
    //  region Abstract model specific methods
    //
    /**
     * @inheritdoc
     */
    public function get_model_driver()
    {
        return PHS_Db::DB_DRIVER_MYSQLI;
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
     * (override the method if not `id`)
     */
    public function get_primary_key( $params = false )
    {
        return 'id';
    }

    /**
     * @inheritdoc
     * @return int
     * Default primary key an INT, override this method if otherwise
     */
    public function prepare_primary_key( $id, $params = false )
    {
        return (int)$id;
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
         || !($fields_arr = $this->get_field_types())
         || empty( $fields_arr[$type] ) || !is_array( $fields_arr[$type] ) )
            return false;

        return $fields_arr[$type];
    }

    /**
     * @inheritdoc
     */
    protected function _get_details_for_model( $id, $params = false )
    {
        if( !($params = $this->fetch_default_flow_params( $params )) )
            return false;

        if( empty( $params['details'] ) )
            $params['details'] = '*';

        $db_connection = $this->get_db_connection( $params );

        if( empty( $id )
         || !($qid = db_query( 'SELECT '.$params['details'].' FROM `'.$this->get_flow_table_name( $params ).'` '.
                               ' WHERE `'.$params['table_index'].'` = \''.$id.'\'', $db_connection ))
         || !($item_arr = db_fetch_assoc( $qid, $db_connection )) )
            return false;

        return $item_arr;
    }

    /**
     * @inheritdoc
     */
    protected function _get_details_fields_for_model( $constrain_arr, $params = false )
    {
        if( !($params = $this->fetch_default_flow_params( $params ))
         || !($common_arr = $this->get_details_common( $constrain_arr, $params ))
         || !is_array( $common_arr )
         || (empty( $params['return_query_string'] ) && empty( $common_arr['qid'] )) )
            return false;

        if( !empty( $params['return_query_string'] ) )
            return $common_arr;

        if( !empty( $common_arr['params'] ) )
            $params = $common_arr['params'];

        if( $params['result_type'] === 'single' )
            return @mysqli_fetch_assoc( $common_arr['qid'] );

        $item_arr = [];
        while( ($row_arr = @mysqli_fetch_assoc( $common_arr['qid'] )) )
        {
            $item_arr[$row_arr[$params['result_key']]] = $row_arr;
        }

        return $item_arr;
    }

    /**
     * @inheritdoc
     */
    protected function _check_table_exists_for_model( $flow_params = false, $force = false )
    {
        $this->reset_error();

        if( !($flow_params = $this->fetch_default_flow_params( $flow_params ))
         || !($flow_table_name = $this->get_flow_table_name( $flow_params ))
         || !($my_driver = $this->get_model_driver()) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Failed validating flow parameters.' ) );
            return false;
        }

        db_supress_errors( $this->get_db_connection( $flow_params ) );
        if( (empty( self::$tables_arr[$my_driver] ) || !empty( $force ))
         && ($qid = db_query( 'SHOW TABLES', $this->get_db_connection( $flow_params ) )) )
        {
            self::$tables_arr[$my_driver] = [];
            while( ($table_name = @mysqli_fetch_assoc( $qid )) )
            {
                if( !is_array( $table_name ) )
                    continue;

                $table_arr = array_values( $table_name );
                self::$tables_arr[$my_driver][$table_arr[0]] = [];

                self::$tables_arr[$my_driver][$table_arr[0]][self::T_DETAILS_KEY] = $this->_parse_mysql_table_details( $table_arr[0] );
            }
        }

        db_restore_errors_state( $this->get_db_connection( $flow_params ) );

        if( !empty( self::$tables_arr[$my_driver] )
         && is_array( self::$tables_arr[$my_driver] )
         && array_key_exists( $flow_table_name, self::$tables_arr[$my_driver] ) )
            return true;

        return false;
    }

    /**
     * @param array $flow_params
     *
     * @return bool
     */
    protected function _install_table_for_model( $flow_params )
    {
        $this->reset_error();

        if( !($model_id = $this->instance_id()) )
            return false;

        if( empty( $this->_definition ) || !is_array( $this->_definition )
         || !($flow_params = $this->fetch_default_flow_params( $flow_params ))
         || empty( $flow_params['table_name'] )
         || !($full_table_name = $this->get_flow_table_name( $flow_params )) )
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
            $table_details = $this->_default_table_details_arr();
        else
            $table_details = $table_definition[self::T_DETAILS_KEY];

        $sql = 'CREATE TABLE IF NOT EXISTS `'.$full_table_name.'` ( '."\n";
        $all_fields_str = '';
        $keys_str = '';
        foreach( $table_definition as $field_name => $field_details )
        {
            if( !($field_definition = $this->_get_mysql_field_definition( $field_name, $field_details ))
             || !is_array( $field_definition ) || empty( $field_definition['field_str'] ) )
                continue;

            $all_fields_str .= ($all_fields_str!==''?', '."\n":'').$field_definition['field_str'];

            if( !empty( $field_definition['keys_str'] ) )
                $keys_str .= ($keys_str!==''?',':'').$field_definition['keys_str'];
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

    /**
     * @param array $flow_params
     *
     * @return bool
     */
    protected function _update_table_for_model( $flow_params )
    {
        $this->reset_error();

        if( !($model_id = $this->instance_id()) )
            return false;

        if( empty( $this->_definition ) || !is_array( $this->_definition )
         || !($flow_params = $this->fetch_default_flow_params( $flow_params ))
         || empty( $flow_params['table_name'] )
         || !($full_table_name = $this->get_flow_table_name( $flow_params )) )
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
        $old_field_names_arr = [];
        $found_old_field_names_arr = [];
        foreach( $table_definition as $field_name => $field_definition )
        {
            if( $field_name === self::T_DETAILS_KEY
             || $field_name === self::EXTRA_INDEXES_KEY
             || !is_array( $field_definition )
             || empty( $field_definition['old_names'] ) || !is_array( $field_definition['old_names'] ) )
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

            if( !empty( $changed_values['charset'] ) || !empty( $changed_values['collate'] ) )
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
        $fields_found_in_old_structure = [];
        // First we add or remove missing fields
        foreach( $table_definition as $field_name => $field_definition )
        {
            if( $field_name === self::T_DETAILS_KEY
             || $field_name === self::EXTRA_INDEXES_KEY )
                continue;

            $field_extra_params = [];
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
                    $old_field = [];
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
            if( $field_name === self::T_DETAILS_KEY
             || $field_name === self::EXTRA_INDEXES_KEY
             || !empty( $fields_found_in_old_structure[$field_name] ) )
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
         || !empty( $db_table_definition[self::EXTRA_INDEXES_KEY] ) )
        {
            if( !empty( $table_definition[self::EXTRA_INDEXES_KEY] )
             && empty( $db_table_definition[self::EXTRA_INDEXES_KEY] ) )
            {
                // new extra indexes
                if( !$this->_create_table_extra_indexes( $flow_params ) )
                    return false;
            } elseif( empty( $table_definition[self::EXTRA_INDEXES_KEY] )
                   && !empty( $db_table_definition[self::EXTRA_INDEXES_KEY] ) )
            {
                // delete existing extra indexes
                if( !$this->delete_table_extra_indexes_from_array( $db_table_definition[self::EXTRA_INDEXES_KEY], $flow_params ) )
                    return false;
            } else
            {
                // do the diff on extra indexes...
                $current_indexes = [];
                foreach( $db_table_definition[self::EXTRA_INDEXES_KEY] as $index_name => $index_arr )
                {
                    if( empty( $index_arr['fields'] ) || !is_array( $index_arr['fields'] ) )
                        $index_arr['fields'] = [];

                    if( array_key_exists( $index_name, $table_definition[self::EXTRA_INDEXES_KEY] )
                     && !empty( $table_definition[self::EXTRA_INDEXES_KEY][$index_name]['fields'] )
                     && is_array( $table_definition[self::EXTRA_INDEXES_KEY][$index_name]['fields'] )
                     && !($index_arr['unique'] xor $table_definition[self::EXTRA_INDEXES_KEY][$index_name]['unique'])
                     && self::arrays_have_same_values( $index_arr['fields'], $table_definition[self::EXTRA_INDEXES_KEY][$index_name]['fields'] ) )
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

    /**
     * @param array $flow_params
     *
     * @return bool
     */
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

        if( empty( $this->_definition ) || !is_array( $this->_definition )
         || !($flow_params = $this->fetch_default_flow_params( $flow_params ))
         || !($db_connection = $this->get_db_connection( $flow_params ))
         || !($full_table_name = $this->get_flow_table_name( $flow_params )) )
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
         || !($flow_table_name = $this->get_flow_table_name( $flow_params ))
         || !($flow_database_name = $this->get_db_database( $flow_params ))
         || !($my_driver = $this->get_model_driver()) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Failed validating flow parameters.' ) );
            return false;
        }

        if( ($qid = db_query( 'SHOW FULL COLUMNS FROM `'.$flow_table_name.'`', $flow_params['db_connection'] )) )
        {
            while( ($field_arr = db_fetch_assoc( $qid, $flow_params['db_connection'] )) )
            {
                if( !is_array( $field_arr )
                 || empty( $field_arr['Field'] ) )
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
                 || empty( $index_arr['INDEX_NAME'] )
                 || empty( $index_arr['COLUMN_NAME'] ) )
                    continue;

                if( empty( self::$tables_arr[$my_driver][$flow_table_name][self::EXTRA_INDEXES_KEY] )
                 || !is_array( self::$tables_arr[$my_driver][$flow_table_name][self::EXTRA_INDEXES_KEY] ) )
                    self::$tables_arr[$my_driver][$flow_table_name][self::EXTRA_INDEXES_KEY] = [];

                if( empty( self::$tables_arr[$my_driver][$flow_table_name][self::EXTRA_INDEXES_KEY][$index_arr['INDEX_NAME']] )
                 || !is_array( self::$tables_arr[$my_driver][$flow_table_name][self::EXTRA_INDEXES_KEY][$index_arr['INDEX_NAME']] ) )
                    self::$tables_arr[$my_driver][$flow_table_name][self::EXTRA_INDEXES_KEY][$index_arr['INDEX_NAME']] = [];

                if( empty( self::$tables_arr[$my_driver][$flow_table_name][self::EXTRA_INDEXES_KEY][$index_arr['INDEX_NAME']]['fields'] )
                 || !is_array( self::$tables_arr[$my_driver][$flow_table_name][self::EXTRA_INDEXES_KEY][$index_arr['INDEX_NAME']]['fields'] ) )
                    self::$tables_arr[$my_driver][$flow_table_name][self::EXTRA_INDEXES_KEY][$index_arr['INDEX_NAME']]['fields'] = [];

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

        if( empty( $existing_data ) || !is_array( $existing_data )
         || !($params = $this->fetch_default_flow_params( $params ))
         || empty( $params['table_index'] )
         || !isset( $existing_data[$params['table_index']] ) )
            return false;

        $db_connection = $this->get_db_connection( $params['db_connection'] );

        if( !db_query( 'DELETE FROM `'.$this->get_flow_table_name( $params ).'` '.
                      ' WHERE `'.$params['table_index'].'` = \''.db_escape( $existing_data[$params['table_index']], $db_connection ).'\'', $db_connection ) )
            return false;

        $hook_params = PHS_Hooks::default_model_hard_delete_data_hook_args();
        $hook_params['table_name'] = $params['table_name'];
        $hook_params['db_record'] = $existing_data;

        // Table level trigger
        PHS::trigger_hooks( PHS_Hooks::H_MODEL_HARD_DELETE_DATA.'_'.$params['table_name'], $hook_params );

        // Generic level trigger
        PHS::trigger_hooks( PHS_Hooks::H_MODEL_HARD_DELETE_DATA, $hook_params );

        return true;
    }

    /**
     * @inheritdoc
     */
    protected function _default_table_details_arr()
    {
        return [
            'engine' => 'InnoDB',
            'charset' => 'utf8',
            'collate' => 'utf8_general_ci',
            'comment' => '',
        ];
    }

    /**
     * @inheritdoc
     */
    protected function _default_table_extra_index_arr()
    {
        return [
            'unique' => false,
            'fields' => [],
        ];
    }

    /**
     * @inheritdoc
     */
    protected function _validate_field( $field_arr )
    {
        if( empty( $field_arr ) || !is_array( $field_arr ) )
            $field_arr = [];

        $def_values = self::_default_field_arr();
        $new_field_arr = [];
        foreach( $def_values as $key => $val )
        {
            if( !array_key_exists( $key, $field_arr ) )
                $new_field_arr[$key] = $val;
            else
                $new_field_arr[$key] = $field_arr[$key];
        }

        $field_arr = $new_field_arr;

        if( empty( $field_arr['type'] )
         || !($field_details = $this->valid_field_type( $field_arr['type'] )) )
            return false;

        if( $field_details['default_length'] === null
         && isset( $field_arr['length'] ) )
            $field_arr['length'] = null;

        if( isset( $field_details['nullable'] ) )
            $field_arr['nullable'] = (!empty( $field_details['nullable'] ));

        if( !isset( $field_arr['length'] )
         && isset( $field_details['default_length'] ) )
            $field_arr['length'] = $field_details['default_length'];

        if( $field_arr['default'] === null
         && isset( $field_details['default_value'] ) )
            $field_arr['default'] = $field_details['default_value'];

        if( empty( $field_arr['raw_default'] )
         && !empty( $field_details['raw_default'] ) )
            $field_arr['raw_default'] = $field_details['raw_default'];

        if( !empty( $field_arr['primary'] ) )
        {
            $field_arr['editable'] = false;
            $field_arr['default'] = null;
        }

        return $field_arr;
    }

    /**
     * Check if mysql field boundaries are over the limits of mysql field.
     * If strict SQL mode is enabled, MySQL rejects the out-of-range value with an error,
     * and the insert fails, in accordance with the SQL standard.
     *
     * @param int|float $field_val
     * @param array $field_details
     * @param array $mysql_type
     *
     * @return string|int|float
     */
    protected function _validate_against_boundries( $field_val, $field_details, $mysql_type )
    {
        // Check upper boundry
        if( !empty( $mysql_type['max_value'] )
         && is_array( $mysql_type['max_value'] ) )
        {
            if( !empty( $field_details['unsigned'] ) )
            {
                if( !empty( $mysql_type['max_value']['unsigned'] )
                 && 1 === PHS_Utils::numeric_string_compare( $field_val, $mysql_type['max_value']['unsigned'] ) )
                    return $mysql_type['max_value']['unsigned'];
            } else
            {
                if( !empty( $mysql_type['max_value']['signed'] )
                 && 1 === PHS_Utils::numeric_string_compare( $field_val, $mysql_type['max_value']['signed'] ) )
                    return $mysql_type['max_value']['signed'];
            }
        }

        // Check lower boundry
        if( !empty( $mysql_type['min_value'] )
         && is_array( $mysql_type['min_value'] ) )
        {
            if( !empty( $field_details['unsigned'] ) )
            {
                if( !empty( $mysql_type['min_value']['unsigned'] )
                 && -1 === PHS_Utils::numeric_string_compare( $field_val, $mysql_type['min_value']['unsigned'] ) )
                    return $mysql_type['min_value']['unsigned'];
            } else
            {
                if( !empty( $mysql_type['min_value']['signed'] )
                 && -1 === PHS_Utils::numeric_string_compare( $field_val, $mysql_type['min_value']['signed'] ) )
                    return $mysql_type['min_value']['signed'];
            }
        }

        return $field_val;
    }

    /**
     * @inheritdoc
     */
    protected function _validate_field_value( $value, $field_name, $field_details, $params = false )
    {
        $this->reset_error();

        if( empty( $params ) || !is_array( $params ) )
            $params = [];

        if( empty( $field_name ) )
            $field_name = self::_t( 'N/A' );

        if( !($field_details = $this->_validate_field( $field_details ))
         || empty( $field_details['type'] )
         || !($mysql_type = $this->valid_field_type( $field_details['type'] )) )
        {
            self::st_set_error( self::ERR_MODEL_FIELDS, self::_t( 'Couldn\'t validate field %s.', $field_name ) );
            return false;
        }

        $phs_params_arr = [];
        $phs_params_arr['trim_before'] = true;

        switch( $field_details['type'] )
        {
            case self::FTYPE_TINYINT:
            case self::FTYPE_SMALLINT:
            case self::FTYPE_MEDIUMINT:
            case self::FTYPE_INT:
                if( !($value = PHS_Params::set_type( $value, PHS_Params::T_INT, $phs_params_arr )) )
                    $value = 0;

                elseif( PHS_Db::check_db_fields_boundaries() )
                    $value = $this->_validate_against_boundries( $value, $field_details, $mysql_type );
            break;

            case self::FTYPE_BIGINT:
                $value = trim( $value );
                if( @function_exists( 'bcmul' ) )
                    $value = @bcmul( $value, 1, 0 );

                if( empty( $value ) )
                    $value = 0;

                elseif( PHS_Db::check_db_fields_boundaries() )
                    $value = $this->_validate_against_boundries( $value, $field_details, $mysql_type );
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

                // The maximum number of digits (M) for DECIMAL is 65.
                // The maximum number of supported decimals (D) is 30.
                // If D is omitted, the default is 0. If M is omitted, the default is 10.
                $m_val = 10;
                $d_val = 0;
                if( !empty( $field_details['length'] ) )
                {
                    if( is_string( $field_details['length'] )
                     && false !== strpos( $field_details['length'], ',' )
                     && ($length_arr = explode( ',', $field_details['length'], 2 )) )
                    {
                        $m_val = (!empty( $length_arr[0] )?(int)trim( $length_arr[0] ):10);
                        $d_val = (!empty( $length_arr[1] )?(int)trim( $length_arr[1] ):0);
                    } else
                    {
                        // consider we are given only M part
                        $m_val = (int)$field_details['length'];
                    }
                }

                $phs_params_arr['digits'] = $d_val;

                if( null === ($value = PHS_Params::set_type( $value, PHS_Params::T_FLOAT, $phs_params_arr )) )
                    $value = 0;

                // resolve the m part (if too big)
                // this should be really validated in model if you don't want errors...
                elseif( false !== strpos( $value, '.' )
                     && ($digits_value_arr = explode( '.', $value, 2 ))
                     && strlen( $digits_value_arr[0] ) > $m_val - $d_val )
                {
                    $value = substr( $digits_value_arr[0], 0, $m_val - $d_val ).(isset( $digits_value_arr[1] )?'.'.$digits_value_arr[1]:'');
                }
            break;

            case self::FTYPE_CHAR:
            case self::FTYPE_VARCHAR:
            case self::FTYPE_TEXT:
            case self::FTYPE_MEDIUMTEXT:
            case self::FTYPE_LONGTEXT:

                if( !empty( $mysql_type['max_bytes'] )
                 && (empty( $field_details['length'] )
                        || 0 <= PHS_Utils::numeric_string_compare( $field_details['length'], $mysql_type['max_bytes'] )
                    ) )
                    $max_bytes = $mysql_type['max_bytes'];
                else
                    $max_bytes = $field_details['length'];

                if( strlen( $value ) > $max_bytes )
                    $value = substr( $value, 0, $max_bytes );
            break;

            case self::FTYPE_ENUM:

                $values_arr = [];
                if( !empty( $field_details['length'] )
                 && is_string( $field_details['length'] ) )
                {
                    $values_arr = explode( ',', $field_details['length'] );
                    $trim_value = trim( $value );
                    $lower_value = strtolower( $trim_value );
                    $value_valid = false;
                    foreach( $values_arr as $possible_value )
                    {
                        $trim_possible_value = trim( $value );
                        $lower_possible_value = strtolower( $trim_value );

                        if( $value === $possible_value
                         || $trim_value === $trim_possible_value
                         || $lower_value === $lower_possible_value )
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
         && ($result_arr = @mysqli_fetch_assoc( $qid )) )
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
        return [
            'Field' => '',
            'Type' => '',
            'Collation' => '',
            'Null' => '',
            'Key' => '',
            'Default' => '',
            'Extra' => '',
            'Privileges' => '',
            'Comment' => '',
        ];
    }

    private function _get_type_from_mysql_field_type( $type )
    {
        $type = trim( $type );
        if( empty( $type ) )
            return false;

        $return_arr = [];
        $return_arr['type'] = self::FTYPE_UNKNOWN;
        $return_arr['length'] = null;
        $return_arr['unsigned'] = false;

        $mysql_type = '';
        $mysql_length = '';
        //if( !preg_match( '@([a-z]+)([\(\s*[0-9,\s]+\s*\)]*)@i', $type, $matches ) )
        if( !preg_match( '@([a-z]+)([\([0-9,\s]+\)]?)*(.*)@i', $type, $matches ) )
            $mysql_type = $type;

        else
        {
            if( !empty( $matches[1] ) )
                $mysql_type = strtolower( trim( $matches[1] ) );

            if( !empty( $matches[2] ) )
                $mysql_length = trim( $matches[2], ' ()' );

            if( !empty( $matches[3] ) )
            {
                $type_extras_str = trim( $matches[3] );
                if( ($type_extras_arr = explode( strtolower( str_replace( '  ', ' ', $type_extras_str ) ), ' ' )) )
                {
                    if( in_array( 'unsigned', $type_extras_arr, true ) )
                        $return_arr['unsigned'] = true;
                }
            }
        }

        if( !empty( $mysql_type )
         && ($field_types = $this->get_field_types())
         && is_array( $field_types ) )
        {
            $mysql_type = strtolower( trim( $mysql_type ) );
            foreach( $field_types as $field_type => $field_arr )
            {
                if( empty( $field_arr['title'] ) )
                    continue;

                if( $field_arr['title'] === $mysql_type )
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
            $length_arr = [];
            if( ($parts_arr = explode( ',', $mysql_length ))
             && is_array( $parts_arr ) )
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
            $model_field_arr['unsigned'] = (!empty( $model_field_type['unsigned'] ));
        }

        $model_field_arr['nullable'] = (!empty( $field_arr['Null'] ) && strtolower( $field_arr['Null'] ) === 'yes');
        $model_field_arr['primary'] = (!empty( $field_arr['Key'] ) && strtolower( $field_arr['Key'] ) === 'pri');
        $model_field_arr['auto_increment'] = (!empty( $field_arr['Extra'] ) && strtolower( $field_arr['Extra'] ) === 'auto_increment');
        $model_field_arr['index'] = (!empty( $field_arr['Key'] ) && strtolower( $field_arr['Key'] ) === 'mul');
        $model_field_arr['default'] = $field_arr['Default'];
        $model_field_arr['comment'] = (!empty( $field_arr['Comment'] )?$field_arr['Comment']:'');

        return $model_field_arr;
    }

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

    private static function _default_field_arr()
    {
        // if 'default_value' is set in field definition that value will be used for 'default' key
        return [
            'type' => self::FTYPE_UNKNOWN,
            'editable' => true,
            'length' => null,
            'unsigned' => false,
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
            'old_names' => [],
        ];
    }

    private function _fields_changed( $field1_arr, $field2_arr )
    {
        if( !($field1_arr = $this->_validate_field( $field1_arr ))
         || !($field2_arr = $this->_validate_field( $field2_arr )) )
            return true;

        if( (int)$field1_arr['type'] !== (int)$field2_arr['type']
         // for lengths with comma
         || str_replace( ' ', '', $field1_arr['length'] ) !== str_replace( ' ', '', $field2_arr['length'] )
         || $field1_arr['primary'] !== $field2_arr['primary']
         || $field1_arr['auto_increment'] !== $field2_arr['auto_increment']
         || $field1_arr['index'] !== $field2_arr['index']
         || $field1_arr['default'] !== $field2_arr['default']
         || $field1_arr['nullable'] !== $field2_arr['nullable']
         || trim( $field1_arr['comment'] ) !== trim( $field2_arr['comment'] )
        )
            return true;

        return false;
    }

    private function _table_details_changed( $details1_arr, $details2_arr )
    {
        $default_table_details = $this->_default_table_details_arr();

        if( !($details1_arr = self::validate_array( $details1_arr, $default_table_details ))
         || !($details2_arr = self::validate_array( $details2_arr, $default_table_details )) )
            return array_keys( $default_table_details );

        $keys_changed = [];
        if( strtolower( trim( $details1_arr['engine'] ) ) !== strtolower( trim( $details2_arr['engine'] ) ) )
            $keys_changed['engine'] = $details2_arr['engine'];
        if( strtolower( trim( $details1_arr['charset'] ) ) !== strtolower( trim( $details2_arr['charset'] ) ) )
            $keys_changed['charset'] = $details2_arr['charset'];
        if( strtolower( trim( $details1_arr['collate'] ) ) !== strtolower( trim( $details2_arr['collate'] ) ) )
            $keys_changed['collate'] = $details2_arr['collate'];
        if( trim( $details1_arr['comment'] ) !== trim( $details2_arr['comment'] ) )
            $keys_changed['comment'] = $details2_arr['comment'];

        return (!empty( $keys_changed )?$keys_changed:false);
    }

    /**
     * @param bool|array $params
     *
     * @return array|bool
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
     * @param string $field
     * @param bool|array $params
     *
     * @return array|bool|mixed|null
     */
    public function table_field_details( $field, $params = false )
    {
        $this->reset_error();

        $table = false;
        if( strpos( $field, '.' ) !== false )
            list( $table, $field ) = explode( '.', $field, 2 );

        if( empty( $params ) || !is_array( $params ) )
            $params = [];

        if( $table !== false )
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
        $hook_params['driver'] = $this->get_model_driver();
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

        if( $field_name === self::T_DETAILS_KEY
         || $field_name === self::EXTRA_INDEXES_KEY
         || empty( $field_details ) || !is_array( $field_details )
         || !($type_details = $this->valid_field_type( $field_details['type'] ))
         || !($field_details = $this->_validate_field( $field_details )) )
            return false;

        $field_str = '';
        $keys_str = '';

        $field_details['type'] = (int)$field_details['type'];

        if( !empty( $field_details['primary'] ) )
            $keys_str = ' PRIMARY KEY (`'.$field_name.'`)';
        elseif( !empty( $field_details['index'] ) )
            $keys_str = ' KEY `'.$field_name.'` (`'.$field_name.'`)';

        $field_str .= '`'.$field_name.'` '.$type_details['title'];
        if( $field_details['length'] !== null
         && $field_details['length'] !== false
         && (!in_array( $field_details['type'], [ self::FTYPE_DATE, self::FTYPE_DATETIME ], true )
                || $field_details['length'] !== 0
            ) )
            $field_str .= '('.$field_details['length'].')';

        if( !empty( $field_details['unsigned'] ) )
            $field_str .= ' UNSIGNED';

        if( !empty( $field_details['nullable'] ) )
            $field_str .= ' NULL';
        else
            $field_str .= ' NOT NULL';

        if( !empty( $field_details['auto_increment'] ) )
            $field_str .= ' AUTO_INCREMENT';

        if( empty( $field_details['primary'] )
         && $field_details['type'] !== self::FTYPE_DATE )
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

        return [
            'field_str' => $field_str,
            'keys_str' => $keys_str,
        ];
    }

    /**
     * @param string $field_name
     * @param array $field_details
     * @param bool|array $flow_params
     * @param bool|array $params
     *
     * @return bool
     */
    final public function alter_table_add_column( $field_name, $field_details, $flow_params = false, $params = false )
    {
        $this->reset_error();

        $field_details = self::validate_array( $field_details, self::_default_field_arr() );

        if( empty( $field_name )
         || $field_name === self::T_DETAILS_KEY
         || $field_name === self::EXTRA_INDEXES_KEY
         || !($flow_params = $this->fetch_default_flow_params( $flow_params ))
         || empty( $field_details ) || !is_array( $field_details )
         || !($field_details = $this->_validate_field( $field_details ))
         || !($mysql_field_arr = $this->_get_mysql_field_definition( $field_name, $field_details ))
         || !($flow_table_name = $this->get_flow_table_name( $flow_params ))
         || empty( $mysql_field_arr['field_str'] ) )
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

        if( empty( $params ) || !is_array( $params ) )
            $params = [];

        if( empty( $params['after_column'] ) || strtolower( trim( $params['after_column'] ) ) === '`first`' )
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

    /**
     * @param string $field_name
     * @param array $field_details
     * @param bool|array $old_field
     * @param bool|array $flow_params
     * @param bool|array $params
     *
     * @return bool
     */
    final public function alter_table_change_column( $field_name, $field_details, $old_field = false, $flow_params = false, $params = false )
    {
        $this->reset_error();

        $field_details = self::validate_array( $field_details, self::_default_field_arr() );

        if( empty( $field_name )
         || $field_name === self::T_DETAILS_KEY
         || $field_name === self::EXTRA_INDEXES_KEY
         || !($flow_params = $this->fetch_default_flow_params( $flow_params ))
         || !($flow_table_name = $this->get_flow_table_name( $flow_params ))
         || empty( $field_details ) || !is_array( $field_details )
         || !($field_details = $this->_validate_field( $field_details ))
         || !($mysql_field_arr = $this->_get_mysql_field_definition( $field_name, $field_details ))
         || empty( $mysql_field_arr['field_str'] ) )
        {
            PHS_Logger::logf( 'Invalid column definition ['.(!empty( $field_name )?$field_name:'???').'].', PHS_Logger::TYPE_MAINTENANCE );

            $this->set_error( self::ERR_ALTER, self::_t( 'Invalid column definition [%s].', (!empty( $field_name )?$field_name:'???') ) );
            return false;
        }

        $db_connection = $this->get_db_connection( $flow_params );

        $old_field_name = false;
        $old_field_details = false;
        if( !empty( $old_field ) && is_array( $old_field )
         && !empty( $old_field['name'] )
         && !empty( $old_field['definition'] ) && is_array( $old_field['definition'] )
         && ($old_field_details = $this->_validate_field( $old_field['definition'] )) )
            $old_field_name = $old_field['name'];

        if( empty( $old_field_name ) )
            $db_old_field_name = $field_name;
        else
            $db_old_field_name = $old_field_name;

        if( empty( $params ) || !is_array( $params ) )
            $params = [];

        if( !isset( $params['alter_indexes'] ) )
            $params['alter_indexes'] = true;
        else
            $params['alter_indexes'] = (!empty( $params['alter_indexes'] )?true:false);

        if( empty( $params['after_column'] ) )
            $params['after_column'] = '';

        elseif( strtolower( trim( $params['after_column'] ) ) === '`first`' )
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
         && !empty( $old_field_name )
         && !empty( $old_field_details ) && is_array( $old_field_details )
         && empty( $old_field_details['primary'] ) && !empty( $old_field_details['index'] ) )
        {
            if( !db_query( 'ALTER TABLE `' . $flow_table_name . '` DROP KEY `'.$old_field_name.'`', $db_connection ) )
            {
                PHS_Logger::logf( 'Error altering table (change) to drop OLD index for ['.$old_field_name.'].', PHS_Logger::TYPE_MAINTENANCE );

                $this->set_error( self::ERR_ALTER, self::_t( 'Error altering table (change) to drop OLD index for [%s].', $old_field_name ) );
                return false;
            }
        }

        if( !empty( $params['alter_indexes'] )
         && !empty( $mysql_field_arr['keys_str'] ) )
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

    /**
     * @param string $field_name
     * @param bool|array $flow_params
     *
     * @return bool
     */
    final public function alter_table_drop_column( $field_name, $flow_params = false )
    {
        $this->reset_error();

        if( empty( $field_name )
         || $field_name === self::T_DETAILS_KEY
         || $field_name === self::EXTRA_INDEXES_KEY
         || !($flow_params = $this->fetch_default_flow_params( $flow_params )) )
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

    /**
     * @param array $flow_params
     *
     * @return bool
     */
    protected function _create_table_extra_indexes( $flow_params )
    {
        $this->reset_error();

        if( !($model_id = $this->instance_id())
         || empty( $this->_definition ) || !is_array( $this->_definition )
         || !($flow_params = $this->fetch_default_flow_params( $flow_params ))
         || empty( $flow_params['table_name'] )
         || empty( $this->_definition[$flow_params['table_name']] )
         || !($full_table_name = $this->get_flow_table_name( $flow_params )) )
            return false;

        $table_definition = $this->_definition[$flow_params['table_name']];

        if( empty( $table_definition[self::EXTRA_INDEXES_KEY] )
         || !is_array( $table_definition[self::EXTRA_INDEXES_KEY] )
         || !($database_name = $this->get_db_database( $flow_params )) )
            return true;

        foreach( $table_definition[self::EXTRA_INDEXES_KEY] as $index_name => $index_arr )
        {
            if( !$this->_create_table_extra_index( $index_name, $index_arr, $flow_params ) )
                return false;
        }

        return true;
    }

    /**
     * @param array $indexes_array
     * @param bool|array $flow_params
     *
     * @return bool
     */
    public function create_table_extra_indexes_from_array( $indexes_array, $flow_params = false )
    {
        $this->reset_error();

        if( empty( $indexes_array ) || !is_array( $indexes_array ) )
            return true;

        foreach( $indexes_array as $index_name => $index_arr )
        {
            if( empty( $index_arr ) || !is_array( $index_arr ) )
                continue;

            if( !$this->_create_table_extra_index( $index_name, $index_arr, $flow_params ) )
                return false;
        }

        return true;
    }

    /**
     * @param string $index_name
     * @param array $index_arr
     * @param bool|array $flow_params
     *
     * @return bool
     */
    private function _create_table_extra_index( $index_name, $index_arr, $flow_params = false )
    {
        $this->reset_error();

        if( !($model_id = $this->instance_id())
         || empty( $index_name )
         || empty( $index_arr ) || !is_array( $index_arr )
         || !($flow_params = $this->fetch_default_flow_params( $flow_params ))
         || !($index_arr = $this->_validate_table_extra_index( $index_arr ))
         || empty( $index_arr['fields'] ) || !is_array( $index_arr['fields'] )
         || empty( $flow_params['table_name'] )
         || empty( $this->_definition[$flow_params['table_name']] )
         || !($full_table_name = $this->get_flow_table_name( $flow_params ))
         || !($database_name = $this->get_db_database( $flow_params )) )
        {
            PHS_Logger::logf( 'Error creating extra index bad parameters sent to method for model ['.(!empty( $model_id )?$model_id:'N/A').'].', PHS_Logger::TYPE_MAINTENANCE );

            $this->set_error( self::ERR_TABLE_GENERATE, self::_t( 'Error creating extra index for model %s.', (!empty( $model_id )?$model_id:'N/A') ) );
            return false;
        }

        $db_connection = $this->get_db_connection( $flow_params );

        $fields_str = '';
        foreach( $index_arr['fields'] as $field_name )
        {
            $fields_str .= ($fields_str!==''?',':'').'`'.$field_name.'`';
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
         && @mysqli_num_rows( $qid ) )
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

    /**
     * @param array $indexes_array
     * @param bool|array $flow_params
     *
     * @return bool
     */
    protected function delete_table_extra_indexes_from_array( $indexes_array, $flow_params = false )
    {
        $this->reset_error();

        if( empty( $indexes_array ) || !is_array( $indexes_array ) )
            return true;

        foreach( $indexes_array as $index_name => $index_arr )
        {
            if( empty( $index_arr ) || !is_array( $index_arr ) )
                continue;

            if( !$this->delete_table_extra_index( $index_name, $flow_params ) )
                return false;
        }

        return true;
    }

    /**
     * @param string $index_name
     * @param bool|array $flow_params
     *
     * @return bool
     */
    public function delete_table_extra_index( $index_name, $flow_params = false )
    {
        $this->reset_error();

        if( !($model_id = $this->instance_id())
         || empty( $index_name )
         || !($flow_params = $this->fetch_default_flow_params( $flow_params ))
         || empty( $flow_params['table_name'] )
         || !($full_table_name = $this->get_flow_table_name( $flow_params ))
         || !($database_name = $this->get_db_database( $flow_params )) )
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
    /**
     * @param array $params
     *
     * @return array|bool
     */
    public function insert( $params )
    {
        $this->reset_error();

        if( !($params = $this->fetch_default_flow_params( $params ))
         || !isset( $params['fields'] ) || !is_array( $params['fields'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Failed validating flow parameters.' ) );
            return false;
        }

        $params['action'] = 'insert';

        if( (
                @method_exists( $this, 'get_insert_prepare_params_'.$params['table_name'] )
                &&
                !($params = @call_user_func( [ $this, 'get_insert_prepare_params_' . $params['table_name'] ], $params ))
            )

            ||

            (
                !@method_exists( $this, 'get_insert_prepare_params_'.$params['table_name'] )
                &&
                !($params = $this->get_insert_prepare_params( $params ))
            )
        )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_INSERT, self::_t( 'Couldn\'t parse parameters for database insert.' ) );

            return false;
        }

        if( !($validation_arr = $this->validate_data_for_fields( $params ))
         || empty( $validation_arr['data_arr'] ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_INSERT, self::_t( 'Error validating parameters.' ) );
            return false;
        }

        $insert_arr = $validation_arr['data_arr'];
        $db_connection = $this->get_db_connection( $params );

        if( !($sql = db_quick_insert( $this->get_flow_table_name( $params ), $insert_arr, $db_connection ))
         || !($item_id = db_query_insert( $sql, $db_connection )) )
        {
            if( @method_exists( $this, 'insert_failed_'.$params['table_name'] ) )
                @call_user_func( [ $this, 'insert_failed_' . $params['table_name'] ], $insert_arr, $params );
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

        // Mark record as just added to database...
        $insert_arr[self::RECORD_NEW_INSERT_KEY] = true;

        $insert_after_exists = (@method_exists( $this, 'insert_after_'.$params['table_name'] )?true:false);

        if( (
                $insert_after_exists
                &&
                !($new_insert_arr = @call_user_func( [ $this, 'insert_after_' . $params['table_name'] ], $insert_arr, $params ))
            )

            ||

            (
                !$insert_after_exists
                &&
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

        $hook_params = PHS_Hooks::default_model_insert_data_hook_args();
        $hook_params['fields_arr'] = $validation_arr['data_arr'];
        $hook_params['table_name'] = $params['table_name'];
        $hook_params['new_db_record'] = $insert_arr;

        // Table level trigger
        PHS::trigger_hooks( PHS_Hooks::H_MODEL_INSERT_DATA.'_'.$params['table_name'], $hook_params );

        // Generic level trigger
        PHS::trigger_hooks( PHS_Hooks::H_MODEL_INSERT_DATA, $hook_params );

        return $insert_arr;
    }

    /**
     * @param array $record_arr
     *
     * @return bool
     */
    public function record_is_new( $record_arr )
    {
        if( empty( $record_arr ) || !is_array( $record_arr )
         || empty( $record_arr[self::RECORD_NEW_INSERT_KEY] ) )
            return false;

        return true;
    }

    /**
     * @param int|array $existing_data
     * @param array $params
     *
     * @return array|bool
     */
    public function edit( $existing_data, $params )
    {
        $this->reset_error();

        if( !($params = $this->fetch_default_flow_params( $params ))
         || !isset( $params['fields'] ) || !is_array( $params['fields'] ) )
        {
            $this->set_error( self::ERR_EDIT, self::_t( 'Failed validating flow parameters.' ) );
            return false;
        }

        if( !($existing_arr = $this->data_to_array( $existing_data, $params ))
         || !array_key_exists( $params['table_index'], $existing_arr ) )
        {
            $this->set_error( self::ERR_EDIT, self::_t( 'Existing record not found in database.' ) );
            return false;
        }

        $params['action'] = 'edit';

        $original_record_arr = $existing_arr;

        // If this was a new record in db, it is not anymore...
        if( isset( $existing_arr[self::RECORD_NEW_INSERT_KEY] ) )
            unset( $existing_arr[self::RECORD_NEW_INSERT_KEY] );

        $edit_prepare_params_exists = (@method_exists( $this, 'get_edit_prepare_params_'.$params['table_name'] )?true:false);

        if( (
                $edit_prepare_params_exists
                &&
                !($params = @call_user_func( [ $this, 'get_edit_prepare_params_' . $params['table_name'] ], $existing_arr, $params ))
            )

            ||

            (
                !$edit_prepare_params_exists
                &&
                !($params = $this->get_edit_prepare_params( $existing_arr, $params ))
            )
        )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_EDIT, self::_t( 'Couldn\'t parse parameters for database edit.' ) );

            return false;
        }

        if( !($validation_arr = $this->validate_data_for_fields( $params ))
         || !isset( $validation_arr['data_arr'] ) || !is_array( $validation_arr['data_arr'] ) )
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
         && (!($sql = db_quick_edit( $full_table_name, $edit_arr, $db_connection ))
                || !db_query( $sql.' WHERE `'.$full_table_name.'`.`'.$params['table_index'].'` = \''.$existing_arr[$params['table_index']].'\'', $db_connection )
            ) )
        {
            if( @method_exists( $this, 'edit_failed_'.$params['table_name'] ) )
                @call_user_func( [ $this, 'edit_failed_' . $params['table_name'] ], $existing_arr, $edit_arr, $params );
            else
                $this->edit_failed( $existing_arr, $edit_arr, $params );

            if( !$this->has_error() )
                $this->set_error( self::ERR_EDIT, self::_t( 'Failed saving information to database.' ) );

            return false;
        }

        $edit_after_exists = (@method_exists( $this, 'edit_after_'.$params['table_name'] )?true:false);

        if( (
                $edit_after_exists
                &&
                !($new_existing_arr = @call_user_func( [ $this, 'edit_after_' . $params['table_name'] ], $existing_arr, $edit_arr, $params ))
            )

            ||

            (
                !$edit_after_exists
                &&
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
                if( !($db_existing_arr = $this->get_details( $existing_arr['id'], $params )) )
                {
                    if( !$this->has_error() )
                        $this->set_error( self::ERR_INSERT, self::_t( 'Failed saving information to database.' ) );

                    return false;
                }

                // Overwrite only fields from table structure (as there might be other keys in array not related to table structure)
                foreach( $db_existing_arr as $key => $val )
                    $existing_arr[$key] = $val;

            } else
            {
                foreach( $edit_arr as $key => $val )
                    $existing_arr[$key] = $val;
            }
        }

        $hook_params = PHS_Hooks::default_model_edit_data_hook_args();
        $hook_params['fields_arr'] = $validation_arr['data_arr'];
        $hook_params['table_name'] = $params['table_name'];
        $hook_params['new_db_record'] = $existing_arr;
        $hook_params['old_db_record'] = $original_record_arr;

        // Table level trigger
        PHS::trigger_hooks( PHS_Hooks::H_MODEL_EDIT_DATA.'_'.$params['table_name'], $hook_params );

        // Generic level trigger
        PHS::trigger_hooks( PHS_Hooks::H_MODEL_EDIT_DATA, $hook_params );

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
         || ! isset($params['fields']) || ! is_array( $params['fields'] ) )
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
    /**
     * @param array $constrain_arr
     * @param bool|array $params
     *
     * @return array|bool
     */
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
        if( empty( $params['join_sql'] ) )
            $params['join_sql'] = '';
        if( empty( $params['extra_sql'] ) )
            $params['extra_sql'] = '';
        if( empty( $params['order_by'] ) )
            $params['order_by'] = '';
        if( empty( $params['group_by'] ) )
            $params['group_by'] = '';
        if( empty( $params['having_sql'] ) )
            $params['having_sql'] = '';
        if( !isset( $params['return_query_string'] ) )
            $params['return_query_string'] = false;
        else
            $params['return_query_string'] = (!empty( $params['return_query_string'] ));

        if( !isset( $params['limit'] )
         || $params['result_type'] === 'single' )
            $params['limit'] = 1;
        else
        {
            $params['limit'] = (int)$params['limit'];
            $params['result_type'] = 'list';
        }

        if( empty( $constrain_arr ) || !is_array( $constrain_arr ) )
            return false;

        $params['fields'] = $constrain_arr;

        $db_connection = $this->get_db_connection( $params );

        if( !($params = $this->get_query_fields( $params )) )
            return false;

        $sql = 'SELECT '.$params['details'].
               ' FROM '.$this->get_flow_table_name( $params ).
               $params['join_sql'].
               ' WHERE '.$params['extra_sql'].
               (!empty( $params['group_by'] )?' GROUP BY '.$params['group_by']:'').
               (!empty( $params['having_sql'] )?' HAVING '.$params['having_sql']:'').
               (!empty( $params['order_by'] )?' ORDER BY '.$params['order_by']:'').
               (isset( $params['limit'] )?' LIMIT 0, '.$params['limit']:'');

        $qid = false;
        $item_count = 0;

        if( empty( $params['return_query_string'] )
         && (!($qid = db_query( $sql, $db_connection ))
                || !($item_count = db_num_rows( $qid, $db_connection ))
            ) )
            return false;

        $return_arr = [];
        $return_arr['query'] = $sql;
        $return_arr['params'] = $params;
        $return_arr['qid'] = $qid;
        $return_arr['item_count'] = $item_count;

        return $return_arr;
    }

    /**
     * @return array
     */
    protected static function linkage_db_functions()
    {
        return [ 'and', 'or' ];
    }

    /**
     * @param string $field_name
     * @param array $field_val
     * @param bool|array $params
     *
     * @return bool|string
     */
    protected function _get_query_field_value( $field_name, $field_val, $params = false )
    {
        if( empty( $params ) || !is_array( $params ) )
            $params = [];

        if( empty( $params['db_connection'] ) )
            $params['db_connection'] = false;
        if( empty( $params['linkage_func'] ) )
            $params['linkage_func'] = 'AND';

        $db_connection = $params['db_connection'];
        $linkage_func = $params['linkage_func'];

        $result_str = '';
        // check if we have multiple complex values for current fields
        // This means we have more values set in numeric indexes and linkage_func index (optional) in case we want to change logical linkage function
        if( is_array( $field_val )
         && !empty( $field_val[0] ) && is_array( $field_val[0] ) )
        {
            $linkage_func = 'OR';
            if( !empty( $field_val['linkage_func'] )
             && in_array( strtolower( $field_val['linkage_func'] ), self::linkage_db_functions(), true ) )
                $linkage_func = strtoupper( $field_val['linkage_func'] );

            $recurring_params = $params;
            $recurring_params['linkage_func'] = $linkage_func;

            $linkage_str = '';
            foreach( $field_val as $value_index => $value_arr )
            {
                // skip non integer indexes
                if( (string)((int)$value_index) !== (string)$value_index )
                    continue;

                if( ($recurring_result = $this->_get_query_field_value( $field_name, $value_arr, $recurring_params )) )
                    $linkage_str .= (!empty( $linkage_str )?' '.$linkage_func.' ':'').' '.$recurring_result;
            }

            if( !empty( $linkage_str ) )
                $result_str = '('.$linkage_str.')';
        } elseif( !is_array( $field_val ) )
        {
            if( $field_val !== false )
                $result_str = ' '.$field_name.' = \''.db_escape( $field_val, $db_connection ).'\' ';
        } else
        {
            // If we don\'t have value key set, it means array passed is an array of values
            if( !isset( $field_val['value'] )
             && !isset( $field_val['raw'] )
             && !isset( $field_val['raw_value'] ) )
                $field_val = [ 'value' => $field_val ];

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
            // Use linkage function used in current linkage (by default) if more values are provided
            if( !isset( $field_val['linkage_func'] )
             || !in_array( strtolower( $field_val['linkage_func'] ), self::linkage_db_functions(), true ) )
                $field_val['linkage_func'] = false;

            if( !empty( $field_val['raw'] ) )
                $result_str = $field_val['raw'];

            elseif( $field_val['value'] !== false || $field_val['raw_value'] !== false )
            {
                $check_value = false;
                $field_val['check'] = trim( $field_val['check'] );
                if( $field_val['raw_value'] !== false )
                    $check_value = $field_val['raw_value'];

                elseif( in_array( strtolower( $field_val['check'] ), [ 'in', 'is', 'between' ] ) )
                    $check_value = $field_val['value'];

                elseif( !is_array( $field_val['value'] ) )
                    $check_value = '\''.db_escape( $field_val['value'], $db_connection ).'\'';

                if( !empty( $check_value ) )
                    $result_str = ' '.$field_val['field'].' '.$field_val['check'].' '.$check_value.' ';

                elseif( is_array( $field_val['value'] )
                     && !empty( $field_val['value'] ) )
                {
                    // If linkage function is not provided for same field, we assume we should check if field is one of
                    // provided values so we should use OR in linkage
                    if( $field_val['linkage_func'] === false )
                        $field_val['linkage_func'] = 'OR';
                    else
                        $field_val['linkage_func'] = $linkage_func;

                    $linkage_str = '';
                    foreach( $field_val['value'] as $field_arr_val )
                        $linkage_str .= (!empty( $linkage_str )?' '.$field_val['linkage_func'].' ':'').' '.$field_val['field'].' '.$field_val['check'].' \''.db_escape( $field_arr_val, $db_connection ).'\' ';

                    if( !empty( $linkage_str ) )
                        $result_str = '('.$linkage_str.')';
                }
            }
        }

        return $result_str;
    }

    /**
     * @param array $params
     *
     * @return array|bool
     */
    public function get_query_fields( $params )
    {
        if( empty( $params ) || !is_array( $params )
         || empty( $params['fields'] ) || !is_array( $params['fields'] )
         || !($new_params = $this->fetch_default_flow_params( $params )) )
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
         && in_array( strtolower( $params['fields']['{linkage_func}'] ), self::linkage_db_functions(), true ) )
            $linkage_func = strtoupper( $params['fields']['{linkage_func}'] );

        if( isset( $params['fields']['{linkage_func}'] ) )
            unset( $params['fields']['{linkage_func}'] );

        $query_field_value_params = [];
        $query_field_value_params['db_connection'] = $db_connection;
        $query_field_value_params['linkage_func'] = $linkage_func;

        foreach( $params['fields'] as $field_name => $field_val )
        {
            $field_name = trim( $field_name );
            if( empty( $field_name ) && $field_name !== '0' )
                continue;

            if( $field_name === '{linkage}' )
            {
                if( empty( $field_val ) || !is_array( $field_val )
                 || empty( $field_val['fields'] ) || !is_array( $field_val['fields'] ) )
                    continue;

                $recurring_params = $params;
                $recurring_params['fields'] = $field_val['fields'];
                $recurring_params['extra_sql'] = '';
                $recurring_params['recurring_level']++;

                if( ($recurring_result = $this->get_query_fields( $recurring_params ))
                 && is_array( $recurring_result ) && !empty( $recurring_result['extra_sql'] ) )
                {
                    $params['extra_sql'] .= (!empty( $params['extra_sql'] )?' '.$linkage_func.' ':'').' ('.$recurring_result['extra_sql'].') ';
                }

                continue;
            }

            // Handle field name
            if( !is_numeric( $field_name )
             && strpos( $field_name, '.' ) === false )
                $field_name = '`'.$full_table_name.'`.`'.$field_name.'`';

            // Handle field value
            if( ($field_val_query = $this->_get_query_field_value( $field_name, $field_val, $query_field_value_params )) )
                $params['extra_sql'] .= (!empty( $params['extra_sql'] )?' '.$linkage_func.' ':'').$field_val_query;
        }

        return $params;
    }

    /**
     * @return array
     */
    public static function get_count_default_params()
    {
        return [
            'count_field' => '*',
            'extra_sql' => '',
            'join_sql' => '',
            'group_by' => '',

            'db_fields' => '',

            'fields' => [],

            'flags' => [],
        ];
    }

    /**
     * @param bool|array $params
     *
     * @return array|int
     */
    public function get_count( $params = false )
    {
        $this->reset_error();

        if( !($params = $this->fetch_default_flow_params( $params ))
         || !($params = self::validate_array( $params, self::get_count_default_params() ))
         || ($params = $this->get_count_list_common_params( $params )) === false
         || ($params = $this->get_count_prepare_params( $params )) === false
         || ($params = $this->get_query_fields( $params )) === false )
            return 0;

        if( empty( $params['extra_sql'] ) )
            $params['extra_sql'] = '';
        if( empty( $params['join_sql'] ) )
            $params['join_sql'] = '';

        if( !isset( $params['return_query_string'] ) )
            $params['return_query_string'] = false;
        else
            $params['return_query_string'] = (!empty( $params['return_query_string'] )?true:false);

        $db_connection = $this->get_db_connection( $params );

        $distinct_str = '';
        if( $params['count_field'] !== '*' )
            $distinct_str = 'DISTINCT ';

        $sql = 'SELECT COUNT('.$distinct_str.$params['count_field'].') AS total_enregs '.
               ' FROM `'.$this->get_flow_table_name( $params ).'` '.
               $params['join_sql'].
               (!empty( $params['extra_sql'] )?' WHERE '.$params['extra_sql']:'').
               (!empty( $params['group_by'] )?' GROUP BY '.$params['group_by']:'').
               (!empty( $params['having_sql'] )?' HAVING '.$params['having_sql']:'');

        if( !empty( $params['return_query_string'] ) )
        {
            $return_arr = [];
            $return_arr['query'] = $sql;
            $return_arr['params'] = $params;

            return $return_arr;
        }

        $ret = 0;
        if( ($qid = db_query( $sql, $db_connection ))
         && ($result = db_fetch_assoc( $qid, $db_connection )) )
        {
            $ret = $result['total_enregs'];
        }

        return $ret;
    }

    /**
     * @return array
     */
    public static function get_list_default_params()
    {
        return [
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

            'fields' => [],

            'flags' => [],
        ];
    }

    /**
     * @param array|bool $params
     *
     * @return array|bool
     */
    protected function get_list_common( $params = false )
    {
        $this->reset_error();

        if( !($params = $this->fetch_default_flow_params( $params ))
         || !($params = self::validate_array( $params, self::get_list_default_params() )) )
            return false;

        $db_connection = $this->get_db_connection( $params );
        $full_table_name = $this->get_flow_table_name( $params );

        if( !isset( $params['return_query_string'] ) )
            $params['return_query_string'] = false;
        else
            $params['return_query_string'] = (!empty( $params['return_query_string'] ));

        // Field which will be used as key in result array (be sure is unique)
        if( empty( $params['arr_index_field'] ) )
            $params['arr_index_field'] = $params['table_index'];

        if( empty( $params['db_fields'] ) )
            $params['db_fields'] = '`'.$full_table_name.'`.*';
        if( empty( $params['order_by'] ) )
            $params['order_by'] = '`'.$full_table_name.'`.`'.$params['table_index'].'` DESC';
        if( empty( $params['group_by'] ) )
            $params['group_by'] = '`'.$full_table_name.'`.`'.$params['table_index'].'`';

        if( ($params = $this->get_count_list_common_params( $params )) === false
         || ($params = $this->get_list_prepare_params( $params )) === false
         || ($params = $this->get_query_fields( $params )) === false )
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
        if( empty( $params['return_query_string'] )
         && !($qid = db_query( $sql, $db_connection )) )
            return false;

        if( empty( $qid )
         || !($rows_number = db_num_rows( $qid, $db_connection )) )
            $rows_number = 0;

        $return_arr = [];
        $return_arr['query'] = $sql;
        $return_arr['params'] = $params;
        $return_arr['qid'] = $qid;
        $return_arr['item_count'] = $rows_number;

        return $return_arr;
    }

    /**
     * @param bool|array $params
     *
     * @return array|bool|\mysqli_result
     */
    public function get_list( $params = false )
    {
        $this->reset_error();

        if( !($common_arr = $this->get_list_common( $params ))
         || !is_array( $common_arr )
         || (empty( $params['return_query_string'] ) && empty( $common_arr['qid'] )) )
            return false;

        if( !empty( $params['return_query_string'] ) )
            return $common_arr;

        if( !empty( $params['get_query_id'] ) )
            return $common_arr['qid'];

        if( isset( $common_arr['params'] ) )
            $params = $common_arr['params'];

        $db_connection = $this->get_db_connection( $params );

        $ret_arr = [];
        while( ($item_arr = db_fetch_assoc( $common_arr['qid'], $db_connection )) )
        {
            $key = $params['table_index'];
            if( isset( $item_arr[$params['arr_index_field']] ) )
                $key = $params['arr_index_field'];

            $ret_arr[$item_arr[$key]] = $item_arr;
        }

        return $ret_arr;
    }

    /**
     * @param string $str
     * @param string $char
     *
     * @return string
     */
    public static function safe_escape( $str, $char = '\'' )
    {
        return str_replace( $char, '\\'.$char, str_replace( '\\'.$char, $char, $str ) );
    }
    //
    //  endregion Querying database functionality
    //
}
