<?php

namespace phs\libraries;

abstract class PHS_Signal_and_slot extends PHS_Instantiable
{
    const ERR_SIGNAL_DEFINITION = 30000, ERR_SIGNAL_CONNECTION = 30001, ERR_SIGNAL_TRIGGER = 30002;

    private static $_signals = array();
    private $_connections = array();
    private static $signal_backtrace = false;

    /**
     * @return array
     */
    final public static function default_signal_response()
    {
        return array(
            'stop_propagation' => false,
            'stop_process' => false,
            'error_arr' => false,
            // null|mixed, Tells to replace return of method which triggered the signal (stop_proccess should be true and this value should be != null)
            'replace_result' => null,
            'instance_responses' => array(),
        );
    }

    /**
     * @param string|bool $instance_id
     *
     * @return array|bool|mixed
     */
    final protected function connected_with( $instance_id = false )
    {
        if( $instance_id === false )
            return $this->_connections;

        if( empty( $this->_connections ) or empty( $this->_connections[$instance_id] ) )
            return false;

        return $this->_connections[$instance_id];
    }

    /**
     * @param string $class
     * @param bool|string $plugin_name
     * @param bool|string $instance_type
     *
     * @return bool
     */
    final public function add_connection( $class, $plugin_name = false, $instance_type = false )
    {
        $this->reset_error();

        if( !($instance_details = self::get_instance_details( $class, $plugin_name, $instance_type ))
         or empty( $instance_details['instance_id'] ) )
        {
            if( self::st_has_error() )
                $this->copy_static_error( self::ERR_SIGNAL_CONNECTION );
            else
                $this->set_error( self::ERR_SIGNAL_CONNECTION, self::_t( 'Error connecting with class.' ) );

            return false;
        }

        $this->_connections[$instance_details['instance_id']] = $instance_details;

        return $instance_details['instance_id'];
    }

    /**
     * @param string $signal Signal to be defined
     * @param array|bool $default_signal_params Default parameters that should be passed when a signal is called
     *
     * @return bool
     */
    protected function define_signal( $signal, $default_signal_params = false )
    {
        $this->reset_error();

        if( $this->signal_defined( $signal ) )
        {
            $this->set_error( self::ERR_SIGNAL_DEFINITION, self::_t( 'Signal already defined.' ) );
            return false;
        }

        if( empty( $default_signal_params ) or !is_array( $default_signal_params ) )
            $default_signal_params = array();

        self::$_signals[$signal] = $default_signal_params;

        return true;
    }

    protected function signal_defined( $signal )
    {
        if( !is_string( $signal )
         or !array_key_exists( $signal, self::$_signals ) )
            return false;

        return self::$_signals[$signal];
    }

    /**
     * @param string $signal
     * @param bool|array $signal_params
     *
     * @return array|bool
     */
    final protected function signal_trigger( $signal, $signal_params = false )
    {
        static $signal_level = 0;

        if( empty( self::$signal_backtrace ) )
            self::$signal_backtrace = array();

        if( ($default_signal_params = $this->signal_defined( $signal )) === false  )
        {
            $this->set_error( self::ERR_SIGNAL_TRIGGER, self::_t( 'Signal not defined.' ) );
            return false;
        }

        if( empty( $this->_connections ) or !is_array( $this->_connections ) )
            return true;

        if( empty( $default_signal_params ) or !is_array( $default_signal_params ) )
            $default_signal_params = array();

        $signal_params = self::validate_array( $signal_params, $default_signal_params );
        $signal_response = $default_signal_response = self::default_signal_response();

        foreach( $this->_connections as $instance_id => $instance_details )
        {
            // Make sure we don't get into a loop
            if( $instance_id === $this->instance_id()
             or !empty( self::$signal_backtrace[$signal.'_'.$instance_id] ) )
                continue;

            self::$signal_backtrace[$signal.'_'.$instance_id] = true;

            $signal_level++;

            if( !($instance_obj = self::get_instance( $instance_details['instance_class'], $instance_details['plugin_name'], $instance_details['instance_type'] )) )
            {
                $this->set_error( self::ERR_SIGNAL_TRIGGER, self::_t( 'Couldn\'t obtain instance to send signal.' ) );
                return false;
            }

            if( !($signal_result = $instance_obj->signal_receive( $this, $signal, $signal_params ))
             or !is_array( $signal_result ) )
            {
                $this->set_error( self::ERR_SIGNAL_TRIGGER, self::_t( 'Signal receive not implemented correctly.' ) );
                return false;
            }

            $signal_level--;

            $signal_result = self::validate_array( $signal_result, $default_signal_response );

            $signal_response['instance_responses'][$instance_id] = $signal_result;

            if( !empty( $signal_result['error_arr'] ) and is_array( $signal_result['error_arr'] ) )
                $this->copy_error_from_array( $signal_result['error_arr'], self::ERR_SIGNAL_TRIGGER );

            if( !empty( $signal_result['stop_process'] ) )
            {
                $signal_response['stop_process'] = true;

                if( !empty( $signal_result['error_arr'] ) )
                    $this->copy_error_from_array( $signal_result['error_arr'] );
                if( $signal_result['replace_result'] !== null )
                    $signal_response['replace_result'] = $signal_result['replace_result'];

                break;
            }
        }

        if( !$signal_level )
            self::$signal_backtrace = array();

        return $signal_response;
    }

    protected function signal_receive( $sender, $signal, $signal_params = false )
    {
        return self::default_signal_response();
    }
}
