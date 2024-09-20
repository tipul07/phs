<?php

namespace phs\libraries;

use Closure;

class PHS_Relation_result
{
    private null | array | PHS_Record_data $_data = null;

    private bool $_data_read = false;

    private int $read_offset = 0;

    public function __construct(
        readonly private PHS_Relation $relation,
        private readonly Closure $read_fn,
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

        $this->_data = ($this->read_fn)($offset, $limit);
        $this->_data_read = true;

        return $this;
    }

    public function cast_to_array() : ?array
    {
        return $this->current()?->cast_to_array();
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
