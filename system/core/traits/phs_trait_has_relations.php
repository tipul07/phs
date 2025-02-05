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
        string $key,
        string $dest_model, string $source_key,
        string $dest_key = '', ?array $dest_flow = [],
        ?array $source_flow = [],
        ?Closure $filter_fn = null,
        ?Closure $read_fn = null,
        array $options = [],
    ) : bool {
        if (!($this instanceof PHS_Model_Core_base)
            || !empty($this->_relations[$key])) {
            return false;
        }

        $this->_relations[$key] = new PHS_Relation($key, $dest_model, $dest_flow, PHS_Relation::ONE_TO_ONE, $dest_key,
            source_flow: $source_flow, source_key: $source_key, source_model: $this,
            filter_fn: $filter_fn, read_fn: $read_fn, options: $options);

        return true;
    }

    public function relation_reverse_one_to_one(
        string $key,
        string $dest_model, string $reverse_key, ?array $dest_flow = [], string $dest_key = '',
        ?array $source_flow = [], string $source_key = '',
        ?Closure $filter_fn = null,
        ?Closure $read_fn = null,
        array $options = [],
    ) : bool {
        if (!($this instanceof PHS_Model_Core_base)
            || !empty($this->_relations[$key])) {
            return false;
        }

        $this->_relations[$key] = new PHS_Relation($key, $dest_model, $dest_flow,
            PHS_Relation::REVERSE_ONE_TO_ONE, $dest_key,
            reverse_key: $reverse_key,
            source_flow: $source_flow, source_key: $source_key, source_model: $this,
            filter_fn: $filter_fn, read_fn: $read_fn, options: $options);

        return true;
    }

    public function relation_one_to_many(
        string $key,
        string $dest_model, string $dest_key, ?array $dest_flow = [],
        ?array $source_flow = [], string $source_key = '',
        ?Closure $filter_fn = null,
        ?Closure $read_fn = null,
        int $read_limit = 20,
        array $options = [],
    ) : bool {
        if (!($this instanceof PHS_Model_Core_base)
            || !empty($this->_relations[$key])) {
            return false;
        }

        $this->_relations[$key] = new PHS_Relation($key, $dest_model, $dest_flow,
            PHS_Relation::ONE_TO_MANY, $dest_key,
            source_flow: $source_flow, source_key: $source_key, source_model: $this,
            filter_fn: $filter_fn, read_fn: $read_fn, read_limit: $read_limit, options: $options);

        return true;
    }

    public function relation_many_to_many(
        string $key,
        string $dest_model, string $dest_key,
        string $link_model, string $link_key, string $link_dest_key,
        ?array $dest_flow = [], ?array $link_flow = [],
        ?array $source_flow = [], string $source_key = '',
        ?Closure $filter_fn = null,
        ?Closure $read_fn = null,
        int $read_limit = 20,
        array $options = [],
    ) : bool {
        if (!($this instanceof PHS_Model_Core_base)
            || !empty($this->_relations[$key])) {
            return false;
        }

        $this->_relations[$key] = new PHS_Relation($key, $dest_model, $dest_flow,
            PHS_Relation::MANY_TO_MANY, $dest_key,
            $link_model, $link_flow, $link_key,
            link_dest_key: $link_dest_key,
            source_flow: $source_flow, source_key: $source_key, source_model: $this,
            filter_fn: $filter_fn, read_fn: $read_fn, read_limit: $read_limit, options: $options);

        return true;
    }

    public function relation_dynamic(
        string $key,
        ?Closure $read_fn = null,
        ?array $source_flow = [], string $source_key = '',
        array $options = [],
    ) : bool {
        if (!($this instanceof PHS_Model_Core_base)
            || !empty($this->_relations[$key])) {
            return false;
        }

        $this->_relations[$key] = new PHS_Relation(
            $key, type: PHS_Relation::DYNAMIC,
            source_flow: $source_flow, source_key: $source_key, source_model: $this,
            read_fn: $read_fn, options: $options);

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

        $record_data[$relation_key] = $relation->load_relation_result($record_data[$record_key], $record_data);
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
