<?php

namespace phs\setup\libraries;

use \phs\libraries\PHS_params;

if( !defined( 'PHS_SETUP_FLOW' ) or !constant( 'PHS_SETUP_FLOW' ) )
    exit;

class PHS_Setup
{
    private $setup_config = false;
    private $framework_config = false;

    /** @var int $c_step Current step in setup */
    private $c_step = 0;
    /** @var int $forced_step Force a certain step in the setup */
    private $forced_step = 0;
    /** @var int $max_steps Maximum number of steps in setup */
    private $max_steps = 0;

    private static $setup_instance_obj = false;

    private static $STEPS_ARR = array();

    function __construct()
    {
    }

    public function check_prerequisites()
    {
        $error_arr = array();

        if( !defined( 'PHS_SETUP_PATH' )
         or !defined( 'PHS_SETUP_CONFIG_DIR' ) or !constant( 'PHS_SETUP_CONFIG_DIR' ) )
        {
            ob_start();
            ?>Some paths were not correctly detected / defined:<br/>
            <ul>
                <?php
                if( !defined( 'PHS_SETUP_PATH' ) )
                {
                     ?><li><em>PHS_SETUP_PATH</em> - not defined correctly</li><?php
                }
                if( !defined( 'PHS_SETUP_CONFIG_DIR' ) or !constant( 'PHS_SETUP_CONFIG_DIR' ) )
                {
                    ?><li><em>PHS_SETUP_CONFIG_DIR</em> - not defined or empty</li><?php
                }
                ?>
            </ul>
            Please try setting up _setup/main.php file manually (recommended) or setup the framework manually skipping setup script.
            <?php
            $check_err_msg = ob_get_clean();

            $error_arr[] = $check_err_msg;
        } elseif( !($setup_config_dir = rtrim( PHS_SETUP_CONFIG_DIR, '/\\' ))
               or !@is_dir( $setup_config_dir )
               or !@is_writable( $setup_config_dir ) )
        {
            ob_start();
            ?>Setup script will write all configuration files in directory <strong><?php echo (!empty( $setup_config_dir )?$setup_config_dir:'_setup/config')?></strong>.
            Please make this directory writable by PHP before continuing.<?php
            $check_err_msg = ob_get_clean();

            $error_arr[] = $check_err_msg;
        }

        if( !empty( $error_arr ) )
        {
            $data = array();
            $data['error_message_arr'] = $error_arr;
            $data['error_title'] = 'Setup Errors...';

            echo PHS_Setup_layout::get_instance()->render( 'error_only', $data, true );
            exit;
        }
    }

    public static function default_setup_config()
    {
        return array(
            ''
        );
    }

    public function load_steps()
    {
        for( $step_i = 1; @file_exists( PHS_SETUP_LIBRARIES_DIR.'phs_step_'.$step_i.'.php' ); $step_i++ )
        {
            include( PHS_SETUP_LIBRARIES_DIR.'phs_step_'.$step_i.'.php' );

            $class_name = '\\phs\\setup\\libraries\\PHS_Step_'.$step_i;
            if( !@class_exists( $class_name ) )
            {
                echo 'Setup step '.$step_i.' class not defined...';
                exit;
            }

            /** @var \phs\setup\libraries\PHS_Step $step_obj */
            if( !($step_obj = new $class_name( $this )) )
            {
                echo 'Couldn\'t instantiate class for step '.$step_i.'...';
                exit;
            }

            if( empty( $this->c_step )
            and (!$step_obj->step_config_passed() or !$step_obj->load_current_configuration()) )
                $this->c_step = $step_i;

            $step_arr = array(
                'instance' => $step_obj,
            );

            self::$STEPS_ARR[$step_i] = $step_arr;

            $this->max_steps++;
        }

        if( !($this->forced_step = PHS_params::_gp( 'forced_step', PHS_params::T_INT ))
         or $this->forced_step < 0 or $this->forced_step >= $this->max_steps
         // Currently c_step holds maximum configured step (we cannot go over this)
         or $this->forced_step > $this->c_step )
            $this->forced_step = $this->c_step;

        if( !empty( $this->forced_step ) )
            $this->c_step = $this->forced_step;
        elseif( empty( $this->c_step ) )
            $this->c_step = $this->max_steps;

        return true;
    }

    /**
     * @return bool|\phs\setup\libraries\PHS_Step
     */
    public function get_current_step_instance()
    {
        if( empty( $this->c_step )
         or empty( self::$STEPS_ARR ) or !is_array( self::$STEPS_ARR )
         or empty( self::$STEPS_ARR[$this->c_step] ) or !is_array( self::$STEPS_ARR[$this->c_step] )
         or empty( self::$STEPS_ARR[$this->c_step]['instance'] ) )
            return false;

        return self::$STEPS_ARR[$this->c_step]['instance'];
    }

    /**
     * @return bool|\phs\setup\libraries\PHS_Step
     */
    public function get_step_instance( $step )
    {
        if( empty( $step )
         or empty( self::$STEPS_ARR ) or !is_array( self::$STEPS_ARR )
         or empty( self::$STEPS_ARR[$step] ) or !is_array( self::$STEPS_ARR[$step] )
         or empty( self::$STEPS_ARR[$step]['instance'] ) )
            return false;

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

    public static function get_instance()
    {
        if( self::$setup_instance_obj !== false )
            return self::$setup_instance_obj;

        self::$setup_instance_obj = new PHS_Setup();

        return self::$setup_instance_obj;
    }
}
