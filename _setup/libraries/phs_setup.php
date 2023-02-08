<?php
namespace phs\setup\libraries;

use phs\libraries\PHS_Params;

if (!defined('PHS_SETUP_FLOW') || !constant('PHS_SETUP_FLOW')) {
    exit;
}

class PHS_Setup
{
    private $setup_config = false;

    private $framework_config = false;

    /** @var int Current step in setup */
    private $c_step = 0;

    /** @var int Force a certain step in the setup */
    private $forced_step = 0;

    /** @var int Maximum number of steps in setup */
    private $max_steps = 0;

    /** @var bool Tells if all setup steps did pass */
    private $all_steps_passed = false;

    private static $setup_instance_obj = false;

    private static $STEPS_ARR = [];

    public function check_prerequisites()
    {
        $error_arr = [];

        if (!defined('PHS_SETUP_PATH')
         || !defined('PHS_SETUP_CONFIG_DIR') || !constant('PHS_SETUP_CONFIG_DIR')) {
            ob_start();
            ?>Some paths were not correctly detected / defined:<br/>
            <ul>
                <?php
                if (!defined('PHS_SETUP_PATH')) {
                    ?><li><em>PHS_SETUP_PATH</em> - not defined correctly</li><?php
                }
                if (!defined('PHS_SETUP_CONFIG_DIR') || !constant('PHS_SETUP_CONFIG_DIR')) {
                    ?><li><em>PHS_SETUP_CONFIG_DIR</em> - not defined or empty</li><?php
                }
            ?>
            </ul>
            Please try setting up _setup/main.php file manually (recommended) or setup the framework manually skipping setup script.
            <?php
            $check_err_msg = ob_get_clean();

            $error_arr[] = $check_err_msg;
        } elseif (!($setup_config_dir = rtrim(PHS_SETUP_CONFIG_DIR, '/\\'))
               || !@is_dir($setup_config_dir)
               || !@is_writable($setup_config_dir)) {
            ob_start();
            ?>Setup script will write all configuration files in directory <strong><?php echo !empty($setup_config_dir) ? $setup_config_dir : '_setup/config'; ?></strong>.
            Please make this directory writable by PHP before continuing.<?php
            $check_err_msg = ob_get_clean();

            $error_arr[] = $check_err_msg;
        }

        if (!empty($error_arr)) {
            $data = [];
            $data['error_message_arr'] = $error_arr;
            $data['error_title'] = 'Setup Errors...';

            echo PHS_Setup_layout::get_instance()->render('error_only', $data, true);
            exit;
        }
    }

    public function goto_next_step()
    {
        if (!@headers_sent()) {
            @header('Location: index.php');
            exit;
        }

        ?>
        <script type="text/javascript">
        document.location = document.location;
        </script>
        <?php
    }

    public function goto_step($step)
    {
        $step = (int)$step;
        if ($step < 0) {
            $step = 0;
        } elseif ($step > $this->max_steps()) {
            $step = $this->max_steps();
        }

        if (!@headers_sent()) {
            @header('Location: index.php?forced_step='.$step);
            exit;
        }

        ?>
        <script type="text/javascript">
        document.location = document.location + "&forced_step=<?php echo $step; ?>";
        </script>
        <?php
    }

    public function load_steps()
    {
        $this->all_steps_passed = true;
        for ($step_i = 1; @file_exists(PHS_SETUP_LIBRARIES_DIR.'phs_step_'.$step_i.'.php'); $step_i++) {
            $step_obj = $this->load_step_instance($step_i);

            if (empty($this->c_step)
             && (empty($step_obj) || !$step_obj->step_config_passed() || !$step_obj->load_current_configuration())) {
                $this->all_steps_passed = false;
                $this->c_step = $step_i;
            }

            $step_arr = [
                'instance' => $step_obj,
            ];

            self::$STEPS_ARR[$step_i] = $step_arr;

            $this->max_steps++;
        }

        if (empty($this->c_step)) {
            $this->c_step = $this->max_steps;
        }

        if (!($this->forced_step = PHS_Params::_gp('forced_step', PHS_Params::T_INT))
         || $this->forced_step < 0 || $this->forced_step > $this->max_steps
         // Currently c_step holds maximum configured step (we cannot go over this)
         || $this->forced_step > $this->c_step) {
            $this->forced_step = 0;
        }

        if (!empty($this->forced_step)) {
            $this->c_step = $this->forced_step;
        } elseif (empty($this->c_step)) {
            $this->c_step = $this->max_steps;
        }

        return true;
    }

    /**
     * @return bool|\phs\setup\libraries\PHS_Step
     */
    public function get_current_step_instance()
    {
        if (empty($this->forced_step)
        && $this->all_steps_passed) {
            return $this->load_step_instance('finish');
        }

        if (empty($this->c_step)
         || empty(self::$STEPS_ARR) || !is_array(self::$STEPS_ARR)
         || empty(self::$STEPS_ARR[$this->c_step]) || !is_array(self::$STEPS_ARR[$this->c_step])
         || empty(self::$STEPS_ARR[$this->c_step]['instance'])) {
            return false;
        }

        return self::$STEPS_ARR[$this->c_step]['instance'];
    }

    /**
     * @param mixed $step
     * @return bool|\phs\setup\libraries\PHS_Step
     */
    public function get_step_instance($step)
    {
        if (empty($step)
         || empty(self::$STEPS_ARR) || !is_array(self::$STEPS_ARR)
         || empty(self::$STEPS_ARR[$step]) || !is_array(self::$STEPS_ARR[$step])
         || empty(self::$STEPS_ARR[$step]['instance'])) {
            return false;
        }

        return self::$STEPS_ARR[$step]['instance'];
    }

    public function current_step()
    {
        return $this->c_step;
    }

    public function max_steps()
    {
        return $this->max_steps;
    }

    public function all_steps_passed()
    {
        return $this->all_steps_passed;
    }

    private function load_step_instance($step)
    {
        ob_start();
        include_once PHS_SETUP_LIBRARIES_DIR.'phs_step_'.$step.'.php';
        ob_end_clean();

        $class_name = '\\phs\\setup\\libraries\\PHS_Step_'.$step;
        if (!@class_exists($class_name)) {
            echo 'Class for setup step "'.$step.'" not defined...';
            exit;
        }

        /** @var \phs\setup\libraries\PHS_Step $step_obj */
        if (!($step_obj = new $class_name($this))) {
            echo 'Couldn\'t instantiate class for step '.$step.'...';
            exit;
        }

        return $step_obj;
    }

    public static function default_setup_config()
    {
        return [
            '',
        ];
    }

    public static function get_instance()
    {
        if (self::$setup_instance_obj !== false) {
            return self::$setup_instance_obj;
        }

        self::$setup_instance_obj = new self();

        return self::$setup_instance_obj;
    }
}
