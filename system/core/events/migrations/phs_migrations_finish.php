<?php
namespace phs\system\core\events\migrations;

use phs\libraries\PHS_Event;
use phs\libraries\PHS_Model;
use phs\libraries\PHS_Plugin;

class PHS_Event_Migrations_finish extends PHS_Event
{
    public function result_has_error() : bool
    {
        return (bool)$this->get_output('has_error');
    }

    public function get_result_errors() : array
    {
        return $this->get_output('errors_arr') ?: [];
    }

    public function get_result_errors_as_string() : string
    {
        return implode("\n\t- ", $this->get_result_errors());
    }

    public function is_dry_update() : bool
    {
        return (bool)$this->get_input('is_dry_update');
    }

    public function is_forced() : bool
    {
        return (bool)$this->get_input('is_forced');
    }

    public function add_result_error(string $error_msg) : void
    {
        $output_arr = $this->get_output() ?: [];
        $output_arr['has_error'] = true;
        $output_arr['errors_arr'] ??= [];
        $output_arr['errors_arr'][] = $error_msg;

        $this->set_output($output_arr);
    }

    /**
     * @inheritdoc
     */
    public function supports_background_listeners() : bool
    {
        return false;
    }

    protected function _input_parameters() : array
    {
        return [
            // Tells if migration script is forced from interface or CLI
            'is_forced' => false,
            // Tells if migration script runs in a dry update run
            'is_dry_update' => false,
        ];
    }

    protected function _output_parameters() : array
    {
        return [
            'has_error'  => false,
            'errors_arr' => [],
        ];
    }
}
