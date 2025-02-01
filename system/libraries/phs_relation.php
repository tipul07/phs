<?php
namespace phs\libraries;

use Closure;

class PHS_Relation
{
    public const ONE_TO_ONE = 1, REVERSE_ONE_TO_ONE = 2, ONE_TO_MANY = 3, MANY_TO_MANY = 4, DYNAMIC = 5;

    private ?PHS_Model_Core_base $dest_model_obj = null;

    private ?PHS_Model_Core_base $link_model_obj = null;

    public function __construct(
        readonly private string $key = '',
        readonly private string $dest_model_class = '',
        readonly private ?array $dest_flow_arr = [],
        readonly private int $type = self::ONE_TO_ONE,
        readonly private string $dest_key = '',
        readonly private string $link_model_class = '',
        readonly private ?array $link_flow_arr = [],
        readonly private string $link_key = '',
        readonly private string $reverse_key = '',
        readonly private string $link_dest_key = '',
        readonly private ?array $source_flow = [],
        readonly private string $source_key = '',
        readonly private ?PHS_Model_Core_base $source_model = null,
        readonly private ?Closure $filter_fn = null,
        readonly private ?Closure $read_fn = null,
        readonly private int $read_limit = 20,
        private array $options = [],
    ) {
        $this->options = PHS_Registry::validate_array($this->options, $this->_default_options_array());
    }

    public function load_relation_result(mixed $key_value, PHS_Record_data $for_record_data) : ?PHS_Relation_result
    {
        $this->_load_models();

        $is_dynamic = $this->get_type() === self::DYNAMIC;
        if ((!$this->dest_model_obj && !$is_dynamic)
            || ($is_dynamic && !$this->read_fn)) {
            return null;
        }

        return new PHS_Relation_result(
            for_record_data: $for_record_data,
            relation: $this,
            read_fn: $this->read_fn ?? function(mixed $read_value, int $offset = 0, int $limit = 0) : null | array | PHS_Record_data {
                $result = match ($this->get_type()) {
                    self::ONE_TO_ONE         => $this->_get_one_to_one_record($read_value),
                    self::REVERSE_ONE_TO_ONE => $this->_get_reverse_one_to_one_record($read_value),
                    self::ONE_TO_MANY        => $this->_get_one_to_many_records($read_value, $offset, $limit),
                    self::MANY_TO_MANY       => $this->_get_many_to_many_records($read_value, $offset, $limit),
                    default                  => null,
                };

                if (!($filter_fn = $this->get_filter_fn())) {
                    return $result;
                }

                if (!is_array($result)) {
                    return $filter_fn($result, $read_value);
                }

                $return_arr = [];
                $merge_results = $this->_get_options_value('merge_relation_results');
                foreach ($result as $key => $result_item) {
                    if (null === ($filtered_result = $filter_fn($result_item, $read_value))) {
                        continue;
                    }

                    if ($merge_results && is_array($filtered_result)) {
                        $return_arr[] = $filtered_result;
                    } else {
                        $return_arr[$key] = $filtered_result;
                    }
                }

                if ($merge_results) {
                    $return_arr = array_merge(...$return_arr);

                    if ($this->_get_options_value('merge_unique_results')) {
                        $return_arr = array_values(array_unique($return_arr));
                    }
                }

                return $return_arr;
            },
            read_value: $key_value,
            read_limit: $this->read_limit,
        );
    }

    public function get_type() : int
    {
        return $this->type;
    }

    public function get_key() : string
    {
        return $this->key;
    }

    public function get_dest_key() : string
    {
        return $this->dest_key;
    }

    public function get_link_key() : string
    {
        return $this->link_key;
    }

    public function get_reverse_key() : string
    {
        return $this->reverse_key;
    }

    public function get_link_dest_key() : string
    {
        return $this->link_dest_key;
    }

    public function get_filter_fn() : ?Closure
    {
        return $this->filter_fn;
    }

    public function get_source_flow() : ?array
    {
        return $this->source_flow;
    }

    public function get_source_key() : string
    {
        return $this->source_key;
    }

    public function get_source_model() : ?PHS_Model_Core_base
    {
        return $this->source_model;
    }

    public function get_record_data_relation_key() : string
    {
        return $this->get_source_key()
            ?: $this->get_source_model()?->get_primary_key($this->get_source_flow())
                ?: '';
    }

    protected function _get_one_to_one_record(mixed $key_value) : ?PHS_Record_data
    {
        return $this->dest_model_obj->data_to_record_data($key_value, $this->dest_flow_arr);
    }

    protected function _get_reverse_one_to_one_record(mixed $key_value) : ?PHS_Record_data
    {
        if (!($reverse_key = $this->get_reverse_key())) {
            return null;
        }

        $dest_flow_arr = ($this->dest_flow_arr ?? []) ?: [];
        $dest_flow_arr['fields'] ??= [];
        $dest_flow_arr['fields'][$reverse_key] = $key_value;

        if (!($data_arr = $this->dest_model_obj->get_details_fields($dest_flow_arr['fields'], $this->dest_flow_arr ?? []))) {
            return null;
        }

        return $this->dest_model_obj->record_data_from_array($data_arr, $this->dest_flow_arr ?? []);
    }

    protected function _get_one_to_many_records(mixed $key_value, int $offset = 0, int $limit = 0) : array
    {
        if (!($dest_key = $this->get_dest_key())) {
            return [];
        }

        $list_arr = $this->dest_flow_arr ?: [];
        $list_arr['offset'] = $offset;
        $list_arr['enregs_no'] = $this->_fix_limit($limit);
        $list_arr['return_record_data_items'] = true;
        $list_arr['fields'] ??= [];
        $list_arr['fields'][$dest_key] = $key_value;

        return $this->dest_model_obj->get_list($list_arr) ?: [];
    }

    protected function _get_many_to_many_records(mixed $key_value, int $offset = 0, int $limit = 0) : array
    {
        if (!$this->dest_model_obj
            || !$this->link_model_obj
            || !($link_key = $this->get_link_key())
            || !($link_dest_key = $this->get_link_dest_key())
            || !($dest_flow = $this->dest_model_obj->fetch_default_flow_params($this->dest_flow_arr ?: []))
            || !($link_flow = $this->link_model_obj->fetch_default_flow_params($this->link_flow_arr ?: []))
            || !($dest_table_name = $this->dest_model_obj->get_flow_table_name($dest_flow))
            || !($link_table_name = $this->link_model_obj->get_flow_table_name($link_flow))
            || !($dest_key = $this->get_dest_key() ?: $this->dest_model_obj->get_primary_key($dest_flow))
        ) {
            return [];
        }

        $list_arr = $this->dest_flow_arr ?: [];
        $list_arr['offset'] = $offset;
        $list_arr['enregs_no'] = $this->_fix_limit($limit);
        $list_arr['return_record_data_items'] = true;
        $list_arr['extra_sql'] = 'EXISTS (SELECT 1 FROM `'.$link_table_name.'` WHERE '
                                 .'`'.$link_table_name.'`.`'.$link_dest_key.'` = \''.prepare_data($key_value).'\''
                                 .' AND `'.$link_table_name.'`.`'.$link_key.'` = `'.$dest_table_name.'`.`'.$dest_key.'`'
                                 .' LIMIT 0, 1)';

        return $this->dest_model_obj->get_list($list_arr) ?: [];
    }

    private function _load_models() : void
    {
        if (!$this->dest_model_obj) {
            $this->dest_model_obj = $this->dest_model_class !== ''
                ? $this->_load_models_by_class_name($this->dest_model_class)
                : null;
        }

        if (!$this->link_model_obj) {
            $this->link_model_obj = $this->link_model_class !== ''
                ? $this->_load_models_by_class_name($this->link_model_class)
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

    private function _fix_limit(int $limit) : int
    {
        if ($limit <= 0) {
            $limit = $this->read_limit <= 0
                ? 1
                : $this->read_limit;
        }

        return $limit;
    }

    private function _get_options_value(string $key) : mixed
    {
        return $this->options[$key] ?? null;
    }

    private function _default_options_array() : array
    {
        return [
            'merge_relation_results' => false,
            'merge_unique_results'   => true,
            'cache_results'          => true,
        ];
    }
}
