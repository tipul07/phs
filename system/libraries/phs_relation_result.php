<?php
namespace phs\libraries;

use Closure;
use Iterator;
use Countable;
use Generator;
use ReturnTypeWillChange;

class PHS_Relation_result implements Countable, Iterator
{
    private null | array | PHS_Record_data $_data = null;

    private null | int | float | bool | array | string | PHS_Record_data $_dynamic_data = null;

    private bool $_data_read = false;

    private array $_read_args = [];

    private int $read_offset = 0;

    private int $next_read_limit = 0;

    public function __construct(
        private readonly PHS_Record_data $for_record_data,
        private readonly PHS_Relation $relation,
        private readonly Closure $read_fn,
        private readonly mixed $read_value,
        private int $read_limit = 20,
    ) {
    }

    public function current() : null | int | float | bool | array | string | PHS_Record_data
    {
        if (!$this->_data_read) {
            $this->read();
        }

        return $this->has_dynamic_relation() ? $this->_dynamic_data : $this->_data;
    }

    #[ReturnTypeWillChange]
    public function next(int $offset = -1, int $limit = 0) : null | int | float | bool | array | string | PHS_Record_data
    {
        if ($this->has_dynamic_relation()) {
            return $this->current();
        }

        if ($limit <= 0) {
            $limit = $this->next_read_limit ?: $this->read_limit;
        }
        if ($offset < 0) {
            $offset = $this->read_offset + $limit;
        }

        return $this->_read_list($offset, $limit, false, ...$this->_read_args)->current();
    }

    public function read(...$args) : static
    {
        if ($this->has_dynamic_relation()) {
            $this->_read_dynamic(...$args);
        } else {
            $indexes = 0;
            if (null !== ($args['offset'] ?? $args[0] ?? null)) {
                $offset = $args['offset'] ?? $args[0] ?? -1;
                $indexes++;
            }
            if (null !== ($args['limit'] ?? $args[1] ?? null)) {
                $limit = $args['limit'] ?? $args[1] ?? 0;
                $indexes++;
            }
            if (null !== ($args['reload'] ?? $args[2] ?? null)) {
                $reload = $args['reload'] ?? $args[2] ?? false;
                $indexes++;
            }

            if ($indexes) {
                $args = array_slice($args, $indexes);
            }

            $this->_read_list((int)($offset ?? -1), (int)($limit ?? 0), (bool)($reload ?? false), ...$args);
        }

        $this->_data_read = true;

        return $this;
    }

    public function has_dynamic_relation() : bool
    {
        return $this->relation->get_type() === PHS_Relation::DYNAMIC;
    }

    public function cast_to_array() : array
    {
        if (null === ($current = $this->current())) {
            return [];
        }

        if (is_array($current)) {
            return $current;
        }

        if ($current instanceof PHS_Record_data) {
            return $current->cast_to_array();
        }

        return [$current];
    }

    public function yield() : ?Generator
    {
        if (!is_array(($current = $this->current()))
            || $this->has_dynamic_relation()) {
            return $current;
        }

        do {
            foreach ($current as $current_item) {
                yield $current_item;
            }
        } while (
            count($current) === $this->next_read_limit
            && ($current = $this->next())
            && is_array($current)
        );

        $this->_reset_data();

        return null;
    }

    public function count() : int
    {
        $data = $this->has_dynamic_relation()
            ? $this->_dynamic_data
            : $this->_data;

        if ($this->_data === null
            && $this->_dynamic_data === null) {
            return 0;
        }

        if (is_array($this->_data)) {
            return count($this->_data);
        }

        return 1;
    }

    public function key() : mixed
    {
        return $this->read_offset;
    }

    public function valid() : bool
    {
        return (bool)($this->current() ?: false);
    }

    public function rewind() : void
    {
        $this->read_offset = 0;
    }

    private function _read_dynamic(...$args) : static
    {
        if ($this->_data_read
           && PHS_Utils::arrays_are_same($args, $this->_read_args)) {
            return $this;
        }

        $this->_read_args = $args;
        $this->_dynamic_data = ($this->read_fn)($this->for_record_data, ...$args);

        return $this;
    }

    private function _read_list(int $offset = -1, int $limit = 0, bool $reload = false, ...$args) : static
    {
        if ($offset < 0) {
            $offset = $this->read_offset;
        }
        if ($limit <= 0) {
            $limit = $this->next_read_limit ?: $this->read_limit;
        }

        if (!$reload
            && $this->_data_read
            && $this->read_offset === $offset
            && $this->next_read_limit === $limit
            && PHS_Utils::arrays_are_same($args, $this->_read_args)) {
            return $this;
        }

        $this->read_offset = $offset;
        $this->next_read_limit = $limit;
        $this->_read_args = $args;

        $this->_data = ($this->read_fn)($this->read_value, $offset, $limit, ...$args);
        $this->_data_read = true;

        return $this;
    }

    private function _reset_data() : void
    {
        $this->_data = null;
        $this->_dynamic_data = null;
        $this->_data_read = false;
        $this->_read_args = [];
        $this->read_offset = 0;
        $this->next_read_limit = 0;
    }

    public function __call(string $name, array $arguments) : mixed
    {
        if (!($current = $this->current()) instanceof PHS_Record_data) {
            return null;
        }

        return $current->$name(...$arguments);
    }

    public function __debugInfo()
    {
        $data = $this->has_dynamic_relation()
            ? $this->_dynamic_data
            : $this->_data;

        if (($data instanceof PHS_Record_data)) {
            return $data->cast_to_array();
        }

        if (is_array($data)) {
            return $data;
        }

        return [$data];
    }
}
