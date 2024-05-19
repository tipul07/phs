<?php
namespace phs\traits;

/**
 * In case a model handles more tables you can define an array which will tell this trait
 * how to handle is_*, can_* and act_* methods based on record types model handles
 *
 * @static $RECORD_TYPES_ARR
 */
trait PHS_Model_Trait_record_types
{
    /**
     * @param bool|string $lang
     *
     * @return array
     */
    public function get_record_types(bool | string $lang = false) : array
    {
        static $record_types_arr = [];

        if (empty(self::$RECORD_TYPES_ARR)) {
            return [];
        }

        if ($lang === false
            && !empty($record_types_arr)) {
            return $record_types_arr;
        }

        $result_arr = $this->translate_array_keys(self::$RECORD_TYPES_ARR, ['title'], $lang);

        if ($lang === false) {
            $record_types_arr = $result_arr;
        }

        return $result_arr;
    }

    /**
     * @param bool|string $lang
     *
     * @return array
     */
    public function get_record_types_as_key_val(bool | string $lang = false) : array
    {
        static $record_types_key_val_arr = [];

        if ($lang === false
            && !empty($record_types_key_val_arr)) {
            return $record_types_key_val_arr;
        }

        $key_val_arr = [];
        if (($record_types_arr = $this->get_record_types($lang))) {
            foreach ($record_types_arr as $key => $val) {
                if (!is_array($val)) {
                    continue;
                }

                $key_val_arr[$key] = $val['title'];
            }
        }

        if ($lang === false) {
            $record_types_key_val_arr = $key_val_arr;
        }

        return $key_val_arr;
    }

    /**
     * @param string $record_type
     * @param bool|string $lang
     *
     * @return null|array
     */
    public function valid_record_type(string $record_type, bool | string $lang = false) : ?array
    {
        $all_record_types = $this->get_record_types($lang);
        if (empty($record_type)
            || !isset($all_record_types[$record_type])) {
            return null;
        }

        return $all_record_types[$record_type];
    }

    /**
     * @param int|string|array $record_data
     * @param string $record_type
     * @param array $params_arr
     *
     * @return array|bool
     */
    public function record_data_to_array(int | string | array $record_data, string $record_type, array $params_arr = []) : ?array
    {
        if (!($record_table = $this->_record_type_to_table_name($record_type))
         || !($record_arr = $this->data_to_array($record_data, self::merge_array_assoc($params_arr, ['table_name' => $record_table])))) {
            return null;
        }

        return $record_arr;
    }

    /**
     * @param array $constrain_arr
     * @param string $record_type
     * @param array $params_arr
     *
     * @return null|array
     */
    public function record_get_details_fields(array $constrain_arr, string $record_type, array $params_arr = []) : ?array
    {
        if (!($record_table = $this->_record_type_to_table_name($record_type))
            || !($record_arr = $this->get_details_fields($constrain_arr, self::merge_array_assoc($params_arr, ['table_name' => $record_table])))) {
            return null;
        }

        return $record_arr;
    }

    /**
     * @param int|string|array $record_data
     * @param string $record_type
     * @param array $params_arr
     *
     * @return bool
     */
    public function record_hard_delete(int | string | array $record_data, string $record_type, array $params_arr = []) : bool
    {
        if (!($record_table = $this->_record_type_to_table_name($record_type))) {
            return false;
        }

        return $this->hard_delete($record_data, self::merge_array_assoc($params_arr, ['table_name' => $record_table]));
    }

    /**
     * @param int|string $record_id
     * @param string $record_type
     * @param array $params_arr
     *
     * @return null|array
     */
    public function record_get_details(int | string $record_id, string $record_type, array $params_arr = []) : ?array
    {
        if (empty($record_id)
            || !($record_table = $this->_record_type_to_table_name($record_type))
            || !($record_arr = $this->get_details($record_id, self::merge_array_assoc($params_arr, ['table_name' => $record_table])))
        ) {
            return null;
        }

        return $record_arr;
    }

    /**
     * @param string $record_type
     *
     * @return null|string
     */
    public function record_type_to_table_name(string $record_type) : ?string
    {
        return $this->_record_type_to_table_name($record_type);
    }

    /**
     * @param string $record_type
     *
     * @return null|string
     */
    protected function _record_type_to_table_name(string $record_type) : ?string
    {
        if (!($record_type_arr = $this->valid_record_type($record_type))
            || empty($record_type_arr['table_name'])) {
            return null;
        }

        return $record_type_arr['table_name'];
    }
}
