<?php
namespace phs\setup\libraries;

class PHS_Step_finish extends PHS_Step
{
    public function step_details() : array
    {
        return [
            'title'       => 'Framework Setup Completed',
            'description' => 'Congratulations you finished setting up framework...',
        ];
    }

    public function get_config_file() : string
    {
        return 'main_finish.php';
    }

    public function step_config_passed() : bool
    {
        return false;
    }

    public function load_current_configuration() : bool
    {
        return true;
    }

    protected function render_step_interface(array $data = []) : string
    {
        return PHS_Setup_layout::get_instance()->render('step_finish', $data);
    }
}
