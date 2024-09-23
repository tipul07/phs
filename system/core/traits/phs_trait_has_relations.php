<?php

namespace phs\traits;

use Closure;
use phs\libraries\PHS_Relation;
use phs\libraries\PHS_Record_data;
use phs\libraries\PHS_Model_Core_base;

trait PHS_Trait_Has_relations
{
    private array $_relations = [];

    abstract protected function _relations_definition() : void;

    public function relation_one_to_one(
        string $key, string $with_model, string $with_key,
        ?array $with_flow = [],
        ?array $for_flow = [],
        ?Closure $filter_fn = null,
    ) : bool {
        if (!($this instanceof PHS_Model_Core_base)
            || !empty($this->_relations[$key])) {
            return false;
        }

        $this->_relations[$key] = new PHS_Relation($key, $with_model, $with_flow, PHS_Relation::ONE_TO_ONE, $with_key,
            for_flow: $for_flow, filter_fn: $filter_fn );

        return true;
    }

    public function relation_reverse_one_to_one(
        string $key, string $with_model, string $reverse_key,
        ?array $with_flow = [], string $with_key = '',
        ?array $for_flow = [],
        ?Closure $filter_fn = null,
    ) : bool {
        if (!($this instanceof PHS_Model_Core_base)
            || !empty($this->_relations[$key])) {
            return false;
        }

        $this->_relations[$key] = new PHS_Relation($key, $with_model, $with_flow, PHS_Relation::REVERSE_ONE_TO_ONE, $with_key,
            reverse_key: $reverse_key, for_flow: $for_flow, filter_fn: $filter_fn);

        return true;
    }

    public function relation_one_to_many(
        string $key, string $with_model, string $with_key, ?array $with_flow = [],
        ?array $for_flow = [],
        ?Closure $filter_fn = null, int $read_limit = 20
    ) : bool {
        if (!($this instanceof PHS_Model_Core_base)
            || !empty($this->_relations[$key])) {
            return false;
        }

        $this->_relations[$key] = new PHS_Relation($key, $with_model, $with_flow, PHS_Relation::ONE_TO_MANY, $with_key,
            for_flow: $for_flow, filter_fn: $filter_fn, read_limit: $read_limit );

        return true;
    }

    public function relation_many_to_many(
        string $key,
        string $with_model, string $with_key,
        string $using_model, string $using_key, string $using_with_key,
        ?array $with_flow = [], ?array $using_flow = [],
        ?array $for_flow = [],
        ?Closure $filter_fn = null, int $read_limit = 20
    ) : bool {
        if (!($this instanceof PHS_Model_Core_base)
            || !empty($this->_relations[$key])) {
            return false;
        }

        $this->_relations[$key] = new PHS_Relation($key, $with_model, $with_flow,
            PHS_Relation::MANY_TO_MANY, $with_key,
            $using_model, $using_flow, $using_key,
            using_with_key: $using_with_key,
            for_flow: $for_flow, filter_fn: $filter_fn, read_limit: $read_limit);

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

    public function load_relation(PHS_Record_data $record_data, string $relation_key) : void
    {
        if (static::key_exists_for_record($relation_key, $record_data)
            || !($relation = $this->relation($relation_key))
            || !($record_key = $relation->get_record_data_relation_key())
            || !static::key_exists_for_record($record_key, $record_data)) {
            return;
        }

        var_dump($relation_key, $record_key, $record_data[$record_key], $record_data[$relation_key] ?? null);

        $record_data[$relation_key] = $relation->get_value($record_data[$record_key]);

        var_dump('canci', $record_data[$relation_key] ?? null);
    }

    public function load_relations(PHS_Record_data $record_data, array $relations_key) : void
    {
        foreach ($relations_key as $relation_key) {
            $this->load_relation($record_data, $relation_key);
        }
    }

    public function load_all_relations(PHS_Record_data $record_data) : void
    {
        if (!($relations = $this->relations())) {
            return;
        }

        foreach ($relations as $relation_key => $relation) {
            $this->load_relation($record_data, $relation_key);
        }
    }

    public static function key_exists_for_record(string $key, PHS_Record_data $record_data) : bool
    {
        if (empty($key)) {
            return false;
        }

        return $record_data->data_key_exists($key);
    }
}
