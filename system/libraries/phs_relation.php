<?php

namespace phs\libraries;

class PHS_Relation
{
    public const ONE_TO_ONE = 1, REVERSE_ONE_TO_ONE = 2, ONE_TO_MANY = 3, MANY_TO_MANY = 4;

    private ?PHS_Model_Core_base $with_model_obj = null;

    private ?PHS_Model_Core_base $using_model_obj = null;

    private ?PHS_Relation_result $result = null;

    public function __construct(
        readonly private string $key = '',
        readonly private string $with_model_class = '',
        readonly private ?array $with_flow_arr = null,
        readonly private int $type = self::ONE_TO_ONE,
        private string $with_key = '',
        readonly private string $using_model_class = '',
        readonly private ?array $using_flow_arr = null,
        readonly private string $using_key = '',
        readonly private string $reverse_key = '',
        readonly private int $read_limit = 20,
    ) {
    }

    public function get_value(mixed $key_value) : ?PHS_Relation_result
    {
        $this->_load_models();

        if (!$this->with_model_obj) {
            return null;
        }

        if ($this->result !== null) {
            return $this->result;
        }

        $relation = $this;

        $this->result = new PHS_Relation_result(
            relation: $this,
            read_fn: function(int $offset = 0, int $limit = 0) use ($relation, $key_value) : null | PHS_Record_data | array {
                return match ($relation->get_type()) {
                    self::ONE_TO_ONE         => $relation->get_one_to_one_record($key_value),
                    self::REVERSE_ONE_TO_ONE => $relation->get_reverse_one_to_one_record($key_value),
                    self::ONE_TO_MANY        => $relation->get_one_to_many_records($key_value, $offset, $limit),
                    self::MANY_TO_MANY       => $relation->get_many_to_many_records($key_value, $offset, $limit),
                    default                  => null,
                };
            },
            read_limit: $this->read_limit,
        );

        return $this->result;
    }

    public function get_one_to_one_record(mixed $key_value) : ?PHS_Record_data
    {
        return $this->with_model_obj->data_to_record_data($key_value, $this->with_flow_arr);
    }

    public function get_reverse_one_to_one_record(mixed $key_value) : ?PHS_Record_data
    {
        if (!($reverse_key = $this->get_reverse_key())) {
            return null;
        }

        $with_flow_arr = ($this->with_flow_arr ?? []) ?: [];
        $with_flow_arr['fields'] ??= [];
        $with_flow_arr['fields'][$reverse_key] = $key_value;

        if (!($data_arr = $this->with_model_obj->get_details_fields($with_flow_arr['fields'], $this->with_flow_arr ?? []))) {
            return null;
        }

        return $this->with_model_obj->record_data_from_array($data_arr, $this->with_flow_arr ?? []);
    }

    public function get_one_to_many_records(mixed $key_value, int $offset = 0, int $limit = 0) : array
    {
        if ($this->type !== self::ONE_TO_MANY
            || !$this->with_model_obj
            || !($with_key = $this->get_with_key())) {
            return [];
        }

        if ( $limit <= 0 ) {
            $limit = $this->read_limit <= 0
                ? 1
                : $this->read_limit;
        }

        $list_arr = $this->with_flow_arr ?: [];
        $list_arr['offset'] = $offset;
        $list_arr['enregs_no'] = $limit;
        $list_arr['return_record_data_items'] = true;
        $list_arr['fields'] ??= [];
        $list_arr['fields'][$with_key] = $key_value;

        return $this->with_model_obj->get_list($list_arr) ?: [];
    }

    public function get_many_to_many_records(mixed $key_value, int $offset = 0, int $limit = 0) : array
    {
        if (!$this->with_model_obj
            || !$this->using_model_obj
            || !($using_key = $this->get_using_key())
            || !($with_flow = $this->with_model_obj->fetch_default_flow_params($this->with_flow_arr ?: []))
            || !($using_flow = $this->using_model_obj->fetch_default_flow_params($this->using_flow_arr ?: []))
            || !($with_table_name = $this->with_model_obj->get_flow_table_name($with_flow))
            || !($using_table_name = $this->using_model_obj->get_flow_table_name($using_flow))
            || !($with_key = $this->get_with_key() ?: $this->with_model_obj->get_primary_key($with_flow))
        ) {
            return [];
        }

        if ( $limit <= 0 ) {
            $limit = $this->read_limit <= 0
                ? 1
                : $this->read_limit;
        }

        $list_arr = $this->with_flow_arr ?: [];
        $list_arr['offset'] = $offset;
        $list_arr['enregs_no'] = $limit;
        $list_arr['return_record_data_items'] = true;
        $list_arr['extra_sql'] = 'EXISTS (SELECT 1 FROM `'.$using_table_name.'` WHERE `'.$using_table_name.'`.`'.$using_key.'` = `'.$with_table_name.'`.`'.$with_key.'`)';
        $list_arr['fields'] ??= [];
        $list_arr['fields'][$this->get_using_key()] = $key_value;

        return $this->with_model_obj->get_list($list_arr) ?: [];
    }

    public function get_type() : int
    {
        return $this->type;
    }

    public function get_key() : string
    {
        return $this->key;
    }

    public function get_with_key() : string
    {
        return $this->with_key;
    }

    public function get_using_key() : string
    {
        return $this->using_key;
    }

    public function get_reverse_key() : string
    {
        return $this->reverse_key;
    }

    public function get_record_data_relation_key() : string
    {
        if (($with_key = $this->get_with_key())) {
            return $with_key;
        }

        if ($this->type === self::REVERSE_ONE_TO_ONE) {
            $this->_load_models();

            $this->with_key = $this->with_model_obj->get_primary_key($this->with_flow_arr);

            return $this->with_key;
        }

        return '';
    }

    private function _load_models() : void
    {
        if (!$this->with_model_obj) {
            $this->with_model_obj = $this->with_model_class !== ''
                ? $this->_load_models_by_class_name($this->with_model_class)
                : null;
        }

        if (!$this->using_model_obj) {
            $this->using_model_obj = $this->using_model_class !== ''
                ? $this->_load_models_by_class_name($this->using_model_class)
                : null;
        }
    }

    private function _load_models_by_class_name(string $class_name) : ?PHS_Model_Core_base
    {
        if (empty($class_name)) {
            return null;
        }

        if (!($loaded_model = $class_name::get_instance())
            || !($loaded_model instanceof PHS_Model_Core_base)) {
            return null;
        }

        return $loaded_model;
    }
}
