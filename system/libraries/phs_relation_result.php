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

    private bool $_data_read = false;

    private int $read_offset = 0;

    public function __construct(
        readonly private PHS_Relation $relation,
        private readonly Closure $read_fn,
        private readonly mixed $read_value,
        private int $read_limit = 20,
    ) {
    }

    public function current() : null | array | PHS_Record_data
    {
        if (!$this->_data_read) {
            $this->read();
        }

        return $this->_data;
    }

    #[ReturnTypeWillChange]
    public function next() : null | array | PHS_Record_data
    {
        $this->read_offset += $this->read_limit;

        return $this->read()->current();
    }

    public function read(int $offset = -1, int $limit = 0) : static
    {
        if ($offset < 0) {
            $offset = $this->read_offset;
        }
        if ($limit <= 0) {
            $limit = $this->read_limit;
        }

        $this->read_offset = $offset;
        $this->read_limit = $limit;

        $this->_data = ($this->read_fn)($this->read_value, $offset, $limit);
        $this->_data_read = true;

        return $this;
    }

    public function cast_to_array() : array
    {
        if (!($current = $this->current())) {
            return [];
        }

        return is_array($current) ? $current : $current->cast_to_array();
    }

    public function yield() : ?Generator
    {
        if ( !is_array(($current = $this->current())) ) {
            return $current;
        }

        do {
            foreach ($current as $current_item) {
                yield $current_item;
            }
        } while ( ($current = $this->next()) && is_array($current) );

        return null;
    }

    public function count() : int
    {
        if ($this->_data === null) {
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

    public function __call(string $name, array $arguments) : mixed
    {
        return $this->current()?->$name(...$arguments);
    }

    public function __debugInfo()
    {
        if (!($this->_data instanceof PHS_Record_data)) {
            return $this->_data;
        }

        return $this->_data->cast_to_array();
    }
}
