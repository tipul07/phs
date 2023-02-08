<?php
namespace phs\setup\libraries;

use phs\libraries\PHS_Registry;

abstract class PHS_Step extends PHS_Registry
{
    /** @var bool|\phs\setup\libraries\PHS_Setup */
    private $setup_obj = false;

    private $config_file_loaded = false;

    public function __construct($setup_inst = false)
    {
        parent::__construct();

        $this->setup_instance($setup_inst);
    }

    abstract public function step_details();

    abstract public function get_config_file();

    abstract public function step_config_passed();

    abstract public function load_current_configuration();

    abstract protected function render_step_interface($data = false);

    public function config_file_loaded($loaded = null)
    {
        if ($loaded === null) {
            return $this->config_file_loaded;
        }

        $this->config_file_loaded = (!empty($loaded));

        return $this->config_file_loaded;
    }

    public function setup_instance($setup_inst = false)
    {
        if ($setup_inst === false) {
            return $this->setup_obj;
        }

        $this->setup_obj = $setup_inst;

        return $this->setup_obj;
    }

    /**
     * @param false|array $data
     *
     * @return false|string
     */
    public function render($data = false)
    {
        if (empty($data) || !is_array($data)) {
            $data = [];
        }

        if (!($step_interface_buf = $this->render_step_interface($data))) {
            $step_interface_buf = '';
        }

        $data['step_interface_buf'] = $step_interface_buf;
        $data['step_instance'] = $this;

        return PHS_Setup_layout::get_instance()->render('template_steps', $data, true);
    }

    public function has_success_msgs()
    {
        return PHS_Setup_layout::get_instance()->has_success_msgs();
    }

    public function has_error_msgs()
    {
        return PHS_Setup_layout::get_instance()->has_error_msgs();
    }

    public function has_notice_msgs()
    {
        return PHS_Setup_layout::get_instance()->has_notices_msgs();
    }

    public function reset_success_msgs()
    {
        return PHS_Setup_layout::get_instance()->reset_success_msgs();
    }

    public function reset_error_msgs()
    {
        return PHS_Setup_layout::get_instance()->reset_error_msgs();
    }

    public function reset_notice_msgs()
    {
        return PHS_Setup_layout::get_instance()->reset_notice_msgs();
    }

    public function add_success_msg($msg)
    {
        return PHS_Setup_layout::get_instance()->add_success_msg($msg);
    }

    public function add_error_msg($msg)
    {
        return PHS_Setup_layout::get_instance()->add_error_msg($msg);
    }

    public function add_notice_msg($msg)
    {
        return PHS_Setup_layout::get_instance()->add_notice_msg($msg);
    }

    protected function save_step_config_file($params)
    {
        $this->reset_error();

        if (empty($params) || !is_array($params)) {
            $this->set_error(self::ERR_PARAMETERS, 'Invalid parameters sent to save config file method.');

            return false;
        }

        if (!($config_file = $this->get_config_file())) {
            $this->set_error(self::ERR_PARAMETERS, 'Couldn\'t obtain config file name for current step.');

            return false;
        }

        if (!($fil = @fopen(PHS_SETUP_CONFIG_DIR.$config_file, 'wb'))) {
            $this->set_error(self::ERR_PARAMETERS, 'Couldn\'t create config file with write rights ('.PHS_SETUP_CONFIG_DIR.$config_file.'). Please make sure PHP has rights to write in that file.');

            return false;
        }

        @fwrite($fil, '<?php'."\n\n");

        if (($step_details = $this->step_details())) {
            @fwrite($fil, '//'."\n"
                           .'// '.$step_details['title']."\n"
                           .'// '.str_replace("\n", ' ', $step_details['description'])."\n"
                           .'//'."\n\n");
        }

        foreach ($params as $definition_block) {
            if (empty($definition_block) || !is_array($definition_block)) {
                continue;
            }

            foreach ($definition_block as $block_type => $block_arr) {
                if (empty($block_arr)
                 || !in_array($block_type, ['defines', 'raw'])) {
                    continue;
                }

                switch ($block_type) {
                    case 'raw':
                        if (!is_array($block_arr)) {
                            $block_arr = ['value' => $block_arr];
                        }

                        $did_write_something = false;

                        if ($this->_save_common_config_line_details_before($fil, $block_arr)) {
                            $did_write_something = true;
                        }

                        if (isset($block_arr['value'])) {
                            $did_write_something = true;
                            @fwrite($fil, $block_arr['value']);
                        }

                        if ($this->_save_common_config_line_details_after($fil, $block_arr)) {
                            $did_write_something = true;
                        }

                        if ($did_write_something) {
                            @fwrite($fil, "\n");
                        }
                        break;

                    case 'defines':
                        if (empty($block_arr) || !is_array($block_arr)) {
                            continue 2;
                        }

                        foreach ($block_arr as $define_key => $definition_info) {
                            if (!is_array($definition_info)) {
                                $definition_info = ['value' => $definition_info];
                            }

                            if (!isset($definition_info['value'])
                             && !isset($definition_info['raw'])
                             && !isset($definition_info['line_comment'])
                             && !isset($definition_info['block_comment'])) {
                                continue;
                            }

                            $did_write_something = false;

                            if ($this->_save_common_config_line_details_before($fil, $definition_info)) {
                                $did_write_something = true;
                            }

                            if (isset($definition_info['value']) || isset($definition_info['raw'])) {
                                $did_write_something = true;
                                if (isset($definition_info['value'])) {
                                    $define_val = '\''.str_replace('\'', '\\\'', $definition_info['value']).'\'';
                                } else {
                                    $define_val = $definition_info['raw'];
                                }

                                @fwrite($fil, 'define( \''.$define_key.'\', '.$define_val.' );');
                            }

                            if ($this->_save_common_config_line_details_after($fil, $definition_info)) {
                                $did_write_something = true;
                            }

                            if ($did_write_something) {
                                @fwrite($fil, "\n");
                            }
                        }
                        break;
                }
            }
        }

        @fwrite($fil, "\n\n");

        @fclose($fil);
        @fflush($fil);

        return true;
    }

    private function _save_common_config_line_details_before($fil, $line_arr)
    {
        if (empty($fil)
         || empty($line_arr) || !is_array($line_arr)) {
            return false;
        }

        $did_write_something = false;

        if (isset($line_arr['line_comment'])) {
            $did_write_something = true;
            @fwrite($fil, '// '.str_replace("\n", '', $line_arr['line_comment']).' '."\n");
        }

        if (isset($line_arr['block_comment'])) {
            $did_write_something = true;
            @fwrite($fil, "\n\n"
                           .'//'."\n"
                           .'// '.trim(str_replace("\n", "\n// ", $line_arr['block_comment']))."\n"
                           .'//'."\n"
                           ."\n");
        }

        return $did_write_something;
    }

    private function _save_common_config_line_details_after($fil, $line_arr)
    {
        if (empty($fil)
         || empty($line_arr) || !is_array($line_arr)) {
            return false;
        }

        $did_write_something = false;
        if (isset($line_arr['quick_comment'])) {
            $did_write_something = true;
            @fwrite($fil, ' // '.str_replace("\n", '', $line_arr['quick_comment']));
        }

        return $did_write_something;
    }
}
