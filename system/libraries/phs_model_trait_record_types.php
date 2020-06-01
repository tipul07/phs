<?php

namespace phs\traits;

/**
 * In case a model handles more tables you can define an array which will tell this trait
 * how to handle is_*, can_* and act_* methods based on record types model handles
 *
 * @static $RECORD_TYPES_ARR
 * @package phs\libraries
 */
trait PHS_Model_Trait_record_types
{
    /**
     * @param bool|string $lang
     *
     * @return array
     */
    public function get_record_types( $lang = false )
    {
        static $record_types_arr = array();

        if( empty( self::$RECORD_TYPES_ARR ) )
            return array();

        if( $lang === false
        and !empty( $record_types_arr ) )
            return $record_types_arr;

        $result_arr = $this->translate_array_keys( self::$RECORD_TYPES_ARR, array( 'title' ), $lang );

        if( $lang === false )
            $record_types_arr = $result_arr;

        return $result_arr;
    }

    /**
     * @param bool|string $lang
     *
     * @return array
     */
    public function get_record_types_as_key_val( $lang = false )
    {
        static $record_types_key_val_arr = false;

        if( $lang === false
        and $record_types_key_val_arr !== false )
            return $record_types_key_val_arr;

        $key_val_arr = array();
        if( ($record_types_arr = $this->get_record_types( $lang )) )
        {
            foreach( $record_types_arr as $key => $val )
            {
                if( !is_array( $val ) )
                    continue;

                $key_val_arr[$key] = $val['title'];
            }
        }

        if( $lang === false )
            $record_types_key_val_arr = $key_val_arr;

        return $key_val_arr;
    }

    /**
     * @param int $status
     * @param bool|string $lang
     *
     * @return bool|array
     */
    public function valid_record_type( $record_type, $lang = false )
    {
        $all_record_types = $this->get_record_types( $lang );
        if( empty( $record_type )
         or !isset( $all_record_types[$record_type] ) )
            return false;

        return $all_record_types[$record_type];
    }

    /**
     * @param int|array $record_data
     * @param string $record_type
     *
     * @return array|bool
     */
    public function record_data_to_array( $record_data, $record_type )
    {
        if( !($record_table = $this->_record_type_to_table_name( $record_type ))
         || !($record_arr = $this->data_to_array( $record_data, [ 'table_name' => $record_table ] )) )
            return false;

        return $record_arr;
    }

    /**
     * @param string $record_type
     *
     * @return bool|string
     */
    protected function _record_type_to_table_name( $record_type )
    {
        if( !($record_type = $this->valid_record_type( $record_type ))
         || empty( $record_type['table_name'] ) )
            return false;

        return $record_type['table_name'];
    }
}
