<?php
namespace phs\libraries;

use Iterator;
use Exception;
use ArrayObject;
use ArrayIterator;
use JsonSerializable;
use ReturnTypeWillChange;

class PHS_Record_data extends ArrayObject implements JsonSerializable
{
    private array $_data = [];

    private array $_data_structure = [];

    private array $_flow_arr = [];

    private array $_allowed_extra_keys = [];

    private ?PHS_Model_Core_base $_model = null;

    private string $_model_class = '';

    private bool $_new_record;

    private bool $_has_relations = false;

    private array $_relation_keys = [];

    public function __construct(
        array $data = [],
        ?PHS_Model_Core_base $model = null,
        string $model_class = '',
        null | bool | array $flow_arr = [],
        int $arro_flags = 0,
        string $arro_iterator_class = ArrayIterator::class,
    ) {
        parent::__construct([], $arro_flags, $arro_iterator_class);

        if ($model !== null) {
            $this->_model = $model;
        } elseif ($model_class
                  && ($modelobj = $model_class::get_instance())
                  && ($modelobj instanceof PHS_Model_Core_base)) {
            $this->_model = $modelobj;
        }

        if ($flow_arr && is_array($flow_arr)) {
            $this->_flow_arr = $flow_arr;
        }

        if ($this->_model) {
            $this->_flow_arr = $this->_model->fetch_default_flow_params($this->_flow_arr);
            $this->_model_class = $this->_model::class;
        }

        $this->_data_structure_definition();
        $this->_check_allowed_extra_keys();
        $this->_extract_relations_details();

        $this->_new_record = !empty($data[PHS_Model_Mysqli::RECORD_NEW_INSERT_KEY]);

        if ($data) {
            $this->set_data($data);
        }
    }

    public function record_has_relations() : bool
    {
        return $this->_has_relations;
    }

    public function record_is_new() : bool
    {
        return $this->_new_record;
    }

    public function mark_as_not_new() : void
    {
        $this->_new_record = false;
    }

    public function data_key_exists(string $key) : bool
    {
        return array_key_exists($key, $this->_data);
    }

    public function data_key_is_allowed(string $key) : bool
    {
        return in_array($key, $this->_allowed_extra_keys, true);
    }

    public function is_data_structure_key(string $key) : bool
    {
        return array_key_exists($key, $this->_data_structure);
    }

    public function is_relation_key(string $key) : bool
    {
        return in_array($key, $this->_relation_keys, true);
    }

    public function get_flow_table_name() : ?string
    {
        return $this->_model?->get_flow_table_name($this->_flow_arr);
    }

    public function fetch_default_flow_params() : ?array
    {
        return $this->_model?->fetch_default_flow_params($this->_flow_arr);
    }

    public function get_simple_table_name_from_flow() : ?string
    {
        return $this->_model?->get_table_name($this->_flow_arr);
    }

    public function get_record_data_model_class() : string
    {
        return $this->_model_class;
    }

    public function get_record_data_model_and_table() : string
    {
        return $this->_model_class.'::'.($this->get_simple_table_name_from_flow() ?? '');
    }

    public function set_data(array $data) : void
    {
        foreach ($data as $key => $value) {
            $this->set_data_key($key, $value);
        }
    }

    public function set_data_key(string $key, mixed $value) : void
    {
        if ($this->_model
            && !$this->is_data_structure_key($key)
            && !$this->data_key_is_allowed($key)
            && !$this->is_relation_key($key)) {
            return;
        }

        $this->_data[$key] = $value;
    }

    // region Countable
    public function count() : int
    {
        return count($this->_data);
    }
    // endregion Countable

    // region IteratorAggregate
    public function getIterator() : Iterator
    {
        return new ArrayIterator($this->_data);
    }
    // endregion IteratorAggregate

    // region ArrayAccess
    public function offsetSet(mixed $key, mixed $value) : void
    {
        $this->set_data_key($key, $value);
    }

    public function offsetExists(mixed $key) : bool
    {
        return array_key_exists($key, $this->_data);
    }

    public function offsetUnset(mixed $key) : void
    {
        unset($this->_data[$key]);
    }

    public function offsetGet(mixed $key) : mixed
    {
        return $this->_return_relation_value($key);
    }
    // endregion ArrayAccess

    // region ArrayObject
    public function append(mixed $value) : void
    {
    }

    #[ReturnTypeWillChange]
    public function asort(int $flags = SORT_REGULAR)
    {
        asort($this->_data, $flags);

        return true;
    }

    #[ReturnTypeWillChange]
    public function ksort(int $flags = SORT_REGULAR)
    {
        ksort($this->_data, $flags);

        return true;
    }

    #[ReturnTypeWillChange]
    public function natcasesort()
    {
        natcasesort($this->_data);

        return true;
    }

    #[ReturnTypeWillChange]
    public function natsort()
    {
        natsort($this->_data);

        return true;
    }

    #[ReturnTypeWillChange]
    public function uasort(callable $callback)
    {
        uasort($this->_data, $callback);

        return true;
    }

    #[ReturnTypeWillChange]
    public function uksort(callable $callback)
    {
        uksort($this->_data, $callback);

        return true;
    }

    public function exchangeArray(array | object $array) : array
    {
        $this->set_data((array)$array);

        return $this->_data;
    }

    public function getArrayCopy() : array
    {
        return $this->_data;
    }
    // endregion ArrayObject

    // region Serializable
    public function serialize() : string
    {
        try {
            $data = @json_encode($this->_data, JSON_THROW_ON_ERROR);
        } catch (Exception) {
            return '';
        }

        return $data;
    }

    public function unserialize(string $data) : void
    {
        try {
            if (($data = @json_decode($data, true, 512, JSON_THROW_ON_ERROR))) {
                $this->set_data($data);
            }
        } catch (Exception) {
        }
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize() : mixed
    {
        return $this->_data;
    }
    // endregion Serializable

    public function cast_to_array() : array
    {
        return $this->_data;
    }

    private function _data_structure_definition() : void
    {
        if (!$this->_model
            || !($data_structure = $this->_model->get_empty_data($this->_flow_arr))) {
            return;
        }

        $this->_data_structure = $data_structure;
    }

    private function _check_allowed_extra_keys() : void
    {
        if (!$this->_model) {
            return;
        }

        $this->_allowed_extra_keys = $this->_model->allow_record_data_keys($this->_flow_arr);
    }

    private function _extract_relations_details() : void
    {
        if (!$this->_model
            || !($relations = $this->_model->relations())) {
            return;
        }

        $relation_keys = [];
        /** @var PHS_Relation $relation */
        foreach ($relations as $relation) {
            if ($this->_model->get_table_name($relation->get_source_flow()) !== $this->get_simple_table_name_from_flow()) {
                continue;
            }

            $relation_keys[] = $relation->get_key();
        }

        $this->_has_relations = (bool)$relation_keys;
        $this->_relation_keys = $relation_keys;
    }

    private function _load_relation(string $key) : void
    {
        if (!$this->_has_relations
            || !$this->_model
            || !empty($this->_data[$key])) {
            return;
        }

        $this->_model->load_relation($this, $key);
    }

    private function _load_and_read_relation(string $key, ...$args) : ?PHS_Relation_result
    {
        $this->_load_relation($key);

        if (($relation_result = $this->_data[$key] ?? null)
           && $relation_result instanceof PHS_Relation_result) {
            return $relation_result->read(...$args);
        }

        return null;
    }

    private function _return_relation_value(string $key, ...$arguments) : mixed
    {
        if ($this->is_relation_key($key)
            && ($relation_result = $this->_load_and_read_relation($key, ...$arguments))
            && $relation_result->has_dynamic_relation()) {
            return $relation_result->current();
        }

        return $this->_data[$key] ?? null;
    }

    public function __debugInfo() : array
    {
        return $this->_data;
    }

    /**
     * @inheritDoc
     */
    public function __serialize() : array
    {
        return $this->_data;
    }

    /**
     * @inheritDoc
     */
    public function __unserialize(array $data) : void
    {
        $this->set_data($data);
    }

    public function __toString() : string
    {
        return $this->serialize();
    }

    public function __call(string $name, array $arguments) : mixed
    {
        return $this->_return_relation_value($name, ...$arguments);
    }
}
