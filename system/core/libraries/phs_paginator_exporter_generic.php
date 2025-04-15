<?php
namespace phs\system\core\libraries;

use phs\libraries\PHS_Paginator_exporter_library;

class PHS_Paginator_exporter_generic extends PHS_Paginator_exporter_library
{
    private array $_callbacks = [
        'start_output_before'     => null,
        'start_output_after'      => null,
        'record_to_output_before' => null,
        'record_to_output_after'  => null,
        'record_to_buffer'        => null,
        'record_error_before'     => null,
        'record_error_after'      => null,
        'finish_output_before'    => null,
        'finish_output_after'     => null,
    ];

    public function __construct(?array $init_params = null)
    {
        if (!empty($init_params['callbacks']) && is_array($init_params['callbacks'])) {
            $this->_set_callbacks($init_params['callbacks']);
        }

        parent::__construct($init_params);
    }

    /**
     * @inheritdoc
     */
    public function start_output() : bool
    {
        if (($callback = $this->_callbacks['start_output_before'])
           && !$callback($this)) {
            return false;
        }

        $result = parent::start_output();

        if (($callback = $this->_callbacks['start_output_after'])
           && !$callback($this)) {
            return false;
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function record_to_buffer(array $record_data, ?array $params = null) : string
    {
        return !($callback = $this->_callbacks['record_to_buffer'])
               || !($buf = $callback($this, $record_data, $params))
            ? ''
            : $buf;
    }

    /**
     * @inheritdoc
     */
    public function record_to_output(array $record_data) : bool
    {
        if (($callback = $this->_callbacks['record_to_output_before'])) {
            if (!($new_record_data = $callback($this, $record_data))) {
                return false;
            }

            $record_data = $new_record_data;
        }

        $result = parent::record_to_output($record_data);

        if (($callback = $this->_callbacks['record_to_output_after'])
           && !$callback($this, $record_data)) {
            return false;
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function record_error(?array $record_data, string $error_buf) : void
    {
        if (($callback = $this->_callbacks['record_error_before'])) {
            $callback($this, $record_data, $error_buf);
        }

        parent::record_error($record_data, $error_buf);

        if (($callback = $this->_callbacks['record_error_after'])) {
            $callback($this, $record_data, $error_buf);
        }
    }

    /**
     * @inheritdoc
     */
    public function finish_output() : bool
    {
        if (($callback = $this->_callbacks['finish_output_before'])
           && !$callback($this)) {
            return false;
        }

        $result = parent::finish_output();

        if (($callback = $this->_callbacks['finish_output_after'])
           && !$callback($this)) {
            return false;
        }

        return $result;
    }

    private function _set_callbacks(array $callbacks) : void
    {
        foreach ($this->_callbacks as $callback_name => $callback_value) {
            if (isset($callbacks[$callback_name]) && @is_callable($callbacks[$callback_name])) {
                $this->_callbacks[$callback_name] = $callbacks[$callback_name];
            }
        }
    }
}
