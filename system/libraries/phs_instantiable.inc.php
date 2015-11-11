<?php

abstract class PHS_Instantiable extends PHS_Registry
{
    const ERR_INSTANCE = 10000, ERR_INSTANCE_ID = 10001, ERR_INSTANCE_CLASS = 10002;

    const INSTANCE_TYPE_PLUGIN = 'plugin', INSTANCE_TYPE_MODEL = 'model', INSTANCE_TYPE_CONTROLLER = 'controller';

    // String values will be used when generating instance_id
    private static $INSTANCE_TYPES_ARR = array(
        self::INSTANCE_TYPE_PLUGIN => 'Plugin',
        self::INSTANCE_TYPE_MODEL => 'Model',
        self::INSTANCE_TYPE_CONTROLLER => 'Controller',
    );

    protected static $instances = array();

    private $instance_details = array();

    /**
     * @return int Should return INSTANCE_TYPE_* constant
     */
    abstract protected function instance_type();

    static public function get_instance_types()
    {
        return self::$INSTANCE_TYPES_ARR;
    }

    static public function valid_instance_type( $type )
    {
        if( empty( $type )
         or !($types_arr = self::get_instance_types())
         or empty( $types_arr[$type] ) )
            return false;

        return $types_arr[$type];
    }

    function __construct( $instance_details = false )
    {
        parent::__construct();

        if( empty( $instance_details ) or !is_array( $instance_details ) )
            $instance_details = self::empty_instance_details();

        $this->set_instance_details( $instance_details );
    }

    final public function instance_id()
    {
        if( empty( $this->instance_details ) or !is_array( $this->instance_details )
         or empty( $this->instance_details['instance_id'] ) )
            return '';

        return $this->instance_details['instance_id'];
    }

    final public function instance_is_core()
    {
        if( empty( $this->instance_details ) or !is_array( $this->instance_details )
         or empty( $this->instance_details['plugin_name'] ) )
            return false;

        return ($this->instance_details['plugin_name']=='core');
    }

    /**
     * @param string $instance_name Part of class name after predefined prefix (eg. phs_model_ for models, phs_controller_ for controller etc)
     * @param string $plugin_name Plugin name or 'core'
     * @return string|false Returns generated string from $instance_name and $plugin_name. This will uniquely identify the file we have to load. false on error
     */
    public static function generate_instance_id( $instance_type, $instance_name, $plugin_name = false )
    {
        self::st_reset_error();

        if( $plugin_name !== false
        and (!is_string( $plugin_name ) or empty( $plugin_name )) )
        {
            self::st_set_error( self::ERR_INSTANCE, self::_t( 'Please provide a valid plugin name.' ) );
            return false;
        }

        if( !is_string( $instance_name ) or empty( $instance_name ) )
        {
            self::st_set_error( self::ERR_INSTANCE, self::_t( 'Please provide a valid instance name.' ) );
            return false;
        }

        if( !self::valid_instance_type( $instance_type ) )
        {
            self::st_set_error( self::ERR_INSTANCE_ID, self::_t( 'Please provide a valid instance type.' ) );
            return false;
        }

        return $instance_type.':'.$plugin_name.':'.$instance_name;
    }

    final private function set_instance_details( $details_arr )
    {
        $this->instance_details = $details_arr;
    }

    final public function instance_details()
    {
        return $this->instance_details;
    }

    public static function empty_instance_details()
    {
        return array(
            'plugin_name' => '',
            'plugin_www' => '',
            'plugin_path' => '',
            'instance_path' => '',
            'instance_class' => '',
            'instance_name' => '',
            'instance_file_name' => '',
            'instance_id' => '',
        );
    }

    public static function get_instance_details( $class, $plugin_name = false, $instance_type = false )
    {
        if( $plugin_name !== false
        and (!is_string( $plugin_name ) or empty( $plugin_name )) )
        {
            self::st_set_error( self::ERR_INSTANCE, self::_t( 'Please provide a valid plugin name.' ) );
            return false;
        }

        if( !self::valid_instance_type( $instance_type ) )
        {
            self::st_set_error( self::ERR_INSTANCE, self::_t( 'Please provide a valid instance type.' ) );
            return false;
        }

        if( empty( $plugin_name ) )
            $plugin_name = 'core';
        else
            $plugin_name = self::safe_escape_name( $plugin_name );

        $return_arr = self::empty_instance_details();
        $return_arr['plugin_name'] = $plugin_name;
        $return_arr['instance_class'] = $class;

        switch( $instance_type )
        {
            default:
                self::st_set_error( self::ERR_INSTANCE, self::_t( 'Unknown instance type.' ) );
                return false;
            break;

            case self::INSTANCE_TYPE_MODEL:

                if( empty( $class )
                 or strtolower( substr( $class, 0, 10 ) ) != 'phs_model_' )
                {
                    self::st_set_error( self::ERR_INSTANCE, self::_t( 'Class name is not a framework models.' ) );
                    return false;
                }

                $return_arr['instance_name'] = strtolower( substr( $class, 10 ) );

                if( $plugin_name == 'core' )
                {
                    $return_arr['instance_path'] = PHS_CORE_MODEL_DIR;
                } else
                {
                    $return_arr['plugin_www'] = PHS_PLUGINS_WWW . $plugin_name.'/';
                    $return_arr['plugin_path'] = PHS_PLUGINS_DIR . $plugin_name.'/';
                    $return_arr['instance_path'] = PHS_PLUGINS_DIR . $plugin_name.'/models/';
                }
            break;

            case self::INSTANCE_TYPE_CONTROLLER:

                if( empty( $class )
                 or strtolower( substr( $class, 0, 15 ) ) != 'phs_controller_' )
                {
                    self::st_set_error( self::ERR_INSTANCE, self::_t( 'Class name is not a framework controller.' ) );
                    return false;
                }

                $return_arr['instance_name'] = strtolower( substr( $class, 15 ) );

                if( $plugin_name == 'core' )
                {
                    $return_arr['instance_path'] = PHS_CORE_CONTROLLER_DIR;
                } else
                {
                    $return_arr['plugin_www'] = PHS_PLUGINS_WWW . $plugin_name.'/';
                    $return_arr['plugin_path'] = PHS_PLUGINS_DIR . $plugin_name.'/';
                    $return_arr['instance_path'] = PHS_PLUGINS_DIR . $plugin_name.'/controllers/';
                }
            break;

            case self::INSTANCE_TYPE_PLUGIN:

                if( empty( $class )
                 or strtolower( substr( $class, 0, 11 ) ) != 'phs_plugin_' )
                {
                    self::st_set_error( self::ERR_INSTANCE, self::_t( 'Class name is not a framework plugin.' ) );
                    return false;
                }

                $return_arr['instance_name'] = strtolower( substr( $class, 11 ) );

                if( $plugin_name == 'core' )
                {
                    $return_arr['instance_path'] = PHS_CORE_PLUGIN_DIR;
                } else
                {
                    $return_arr['plugin_www'] = PHS_PLUGINS_WWW . $plugin_name.'/';
                    $return_arr['plugin_path'] = PHS_PLUGINS_DIR . $plugin_name.'/';
                    $return_arr['instance_path'] = PHS_PLUGINS_DIR . $plugin_name.'/';
                }
            break;
        }

        if( !($instance_id = self::generate_instance_id( $instance_type, $return_arr['instance_name'], $plugin_name )) )
            return false;

        $return_arr['instance_id'] = $instance_id;
        $return_arr['instance_file_name'] = 'phs_'.$return_arr['instance_name'].'.inc.php';

        return $return_arr;
    }

    public static function safe_escape_name( $name )
    {
        if( empty( $name ) or !is_string( $name )
         or preg_match( '/[^a-zA-Z0-9]/', $name ) )
            return false;

        return strtolower( $name );
    }

    final public static function get_instance( $class = null, $plugin_name = false, $instance_type = false, $singleton = true )
    {
        self::st_reset_error();

        if( is_null( $class ) )
            $class = get_called_class();

        if( !($instance_details = self::get_instance_details( $class, $plugin_name, $instance_type ))
         or empty( $instance_details['instance_id'] ) )
            return false;

        $instance_file_path = $instance_details['instance_path'].$instance_details['instance_file_name'];

        if( !@file_exists( $instance_file_path ) )
        {
            self::st_set_error( self::ERR_INSTANCE_CLASS, self::_t( 'Couldn\'t load instance %s from plugin %s.', $instance_details['instance_name'], $instance_details['plugin_name'] ) );
            return false;
        }

        ob_start();
        include_once( $instance_file_path );
        ob_end_clean();

        if( !class_exists( $instance_details['instance_class'], false ) )
        {
            self::st_set_error( self::ERR_INSTANCE_CLASS, self::_t( 'Class %s not defined in %s file.', $instance_details['instance_class'], $instance_details['instance_file_name'] ) );
            return false;
        }

        /** @var PHS_Model $model_instance */
        if( !empty( $singleton )
        and isset( self::$instances[$instance_details['instance_id']] ) )
            $instance_obj = self::$instances[$instance_details['instance_id']];
        else
        {
            $instance_class = $instance_details['instance_class'];

            /** @var PHS_Instantiable $instance_obj */
            $instance_obj = new $instance_class( $instance_details );

            if( $instance_obj->has_error() )
            {
                self::st_copy_error( $model_instance );
                return false;
            }
        }

        if( !($instance_obj instanceof PHS_Instantiable) )
        {
            self::st_set_error( self::ERR_INSTANCE_CLASS, self::_t( 'Loaded class doesn\'t appear to be a PHS instance.' ) );
            return false;
        }

        if( !empty( $singleton ) )
        {
            self::$instances[$instance_details['instance_id']] = $instance_obj;
            return self::$instances[$instance_details['instance_id']];
        }

        return $instance_obj;
    }
}
