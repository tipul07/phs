<?php

namespace phs\setup\libraries;

class PHS_Setup
{
    private $setup_config = false;
    private $framework_config = false;

    private $c_step = 0;
    private $max_steps = 0;

    private static $setup_instance_obj = false;

    private static $STEPS_ARR = array();

    function __construct()
    {
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

        if( empty( $this->c_step ) )
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
