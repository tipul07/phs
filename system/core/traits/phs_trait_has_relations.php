<?php

namespace phs\traits;

use phs\libraries\PHS_Relation;
use phs\libraries\PHS_Record_data;

/** @property array $_relations */
trait PHS_Trait_Has_relations
{
    private array $_relations = [];

    abstract protected function _relations_definition() : void;

    public function relation_one_to_one(string $key, string $with_model, string $with_key, ?array $with_flow = []) : bool
    {
        if (!empty($this->_relations[$key])) {
            return false;
        }

        $this->_relations[$key] = new PHS_Relation($key, $with_model, $with_flow, PHS_Relation::ONE_TO_ONE, $with_key);

        return true;
    }

    public function relation_reverse_one_to_one(string $key, string $with_model, string $reverse_key, ?array $with_flow = [], string $with_key = '') : bool
    {
        if (!empty($this->_relations[$key])) {
            return false;
        }

        $this->_relations[$key] = new PHS_Relation($key, $with_model, $with_flow, PHS_Relation::REVERSE_ONE_TO_ONE, $with_key, reverse_key: $reverse_key);

        return true;
    }

    public function relation_one_to_many(string $key, string $with_model, string $with_key, ?array $with_flow = [], int $read_limit = 20) : bool
    {
        if (!empty($this->_relations[$key])) {
            return false;
        }

        $this->_relations[$key] = new PHS_Relation($key, $with_model, $with_flow, PHS_Relation::ONE_TO_MANY, $with_key, read_limit: $read_limit);

        return true;
    }

    public function relation_many_to_many(
        string $key,
        string $with_model, string $with_key,
        string $using_model, string $using_key,
        ?array $with_flow = [], ?array $using_flow = [],
        int $read_limit = 20
    ) : bool {
        if (!empty($this->_relations[$key])) {
            return false;
        }

        $this->_relations[$key] = new PHS_Relation($key, $with_model, $with_flow, PHS_Relation::MANY_TO_MANY, $with_key, $using_model, $using_flow, $using_key, read_limit: $read_limit);

        return true;
    }

    public function relations() : array
    {
        return $this->_relations;
    }

    public function relation(string $key) : ?PHS_Relation
    {
        return $this->_relations[$key] ?? null;
    }

    public function load_relation(array | PHS_Record_data $record_arr, string $relation_key) : array | PHS_Record_data
    {
        if (empty($record_arr)
            || static::key_exists_for_record($relation_key, $record_arr)
            || !($relation = $this->relation($relation_key))
            || !($record_key = $relation->get_record_data_relation_key())
            || !static::key_exists_for_record($record_key, $record_arr)) {
            return $record_arr;
        }

        $record_arr[$relation_key] = $relation->get_value($record_arr[$record_key]);

        return $record_arr;
    }

    public function load_relations(array | PHS_Record_data $record_arr, array $relations_key) : array | PHS_Record_data
    {
        foreach ($relations_key as $relation_key) {
            $record_arr = $this->load_relation($record_arr, $relation_key);
        }

        return $record_arr;
    }

    public function load_all_relations(array | PHS_Record_data $record_arr) : array | PHS_Record_data
    {
        if (!($relations = $this->relations())) {
            return $record_arr;
        }

        foreach ($relations as $relation_key => $relation) {
            $record_arr = $this->load_relation($record_arr, $relation_key);
        }

        return $record_arr;
    }

    public static function key_exists_for_record(string $key, array | PHS_Record_data $record_arr) : bool
    {
        if (empty($key) || empty($record_arr)) {
            return false;
        }

        if ($record_arr instanceof PHS_Record_data) {
            return $record_arr->data_key_exists($key);
        }

        return array_key_exists($key, $record_arr);
    }
}
