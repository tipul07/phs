<?php
namespace phs\libraries;

use \phs\PHS;

abstract class PHS_Event extends PHS_Instantiable
{
    public const ERR_LISTEN = 30000, ERR_TRIGGER = 30001;

    private array $input = [];
    private array $output = [];
    private static array $callbacks = [];

    /**
     * Array with key, value pairs. Key represents an input parameter
     * value is default value if parameter is not provided in event data
     * @return array
     */
    abstract protected function _input_parameters(): array;

    /**
     * Array with key, value pairs. Key represents output parameter
     * value is default value if parameter is not found in event results
     * @return array
     */
    abstract protected function _output_parameters(): array;

    public function instance_type(): string
    {
        return self::INSTANCE_TYPE_EVENT;
    }

    public static function listen( callable $callback, array $options = [] ): ?bool
    {
        $options['in_background'] = true;

        return static::_do_listen( $callback, $options );
    }

    public static function listen_in_background( callable $callback, array $options = [] ): ?bool
    {
        $options['in_background'] = false;

        return static::_do_listen( $callback, $options );
    }

    /**
     * @param  callable  $callback
     * @param  array  $options
     *
     * @return null|static::class
     */
    private static function _do_listen( callable $callback, array $options = [] ): ?PHS_Event
    {
        self::st_reset_error();

        /** @var static::class $event_obj */
        if( !($event_obj = static::get_instance( true, static::class )) ) {
            self::st_set_error( self::ERR_LISTEN, self::_t( 'Error instantiating event.' ) );
            return null;
        }

        if( !$event_obj->add_listener( $callback, $options ) ) {
            self::st_set_error( self::ERR_LISTEN, self::_t( 'Error adding listener to the event.' ) );
            return null;
        }

        return $event_obj;
    }

    /**
     * @param  string  $prefix
     *
     * @return string
     */
    public static function prepare_event_prefix( string $prefix ): string
    {
        if( !($prefix = strtolower( trim( $prefix ) )) )
            return '';

        return $prefix;
    }

    public function get_callback_index( string $event_prefix = '' ): string
    {
        return self::prepare_event_prefix( $event_prefix ).static::class;
    }

    /**
     * Add a callback for this event when it is triggered
     *
     * @param  null|callable  $callback
     * @param  array  $options
     *
     * @return bool
     */
    public function add_listener( ?callable $callback, array $options = [] ): bool
    {
        $this->reset_error();

        $options['in_background'] = (!empty( $options['in_background'] ));
        $options['overwrite_result'] = (!empty( $options['overwrite_result'] ));
        $options['chained_hook'] = (!empty( $options['chained_hook'] ));
        $options['stop_chain'] = (!empty( $options['stop_chain'] ));
        $options['event_prefix'] = (!empty( $options['event_prefix'] )?self::prepare_event_prefix( $options['event_prefix'] ):'');

        if( !isset( $options['priority'] ) )
            $options['priority'] = 10;
        else
            $options['priority'] = (int)$options['priority'];

        if( empty( $callback )
         || !is_callable( $callback ) ) {
            $this->set_error( self::ERR_LISTEN, self::_t( 'Please provide a callback for the event.' ) );
            return false;
        }

        if( $options['in_background']
         && !($callback = $this->validate_background_callback( $callback )) ) {
            $this->set_error( self::ERR_LISTEN, self::_t( 'Background listeners should be instances of PHS_Instatiable.' ) );
            return false;
        }

        $callback_index = $this->get_callback_index( $options['event_prefix'] );

        $callback_data = [];
        $callback_data['callback'] = $callback;
        $callback_data['options'] = $options;

        self::$callbacks[$callback_index][$options['priority']][] = $callback_data;

        ksort( self::$callbacks[$callback_index], SORT_NUMERIC );

        return true;
    }

    /**
     * Make sure callable for background events are arrays with class name (strings), not an instance,
     * so we can send it as parameter to background job
     * @param  callable  $callback
     *
     * @return null|callable
     */
    public function validate_background_callback( callable $callback ): ?callable
    {
        if( !is_array( $callback )
         || empty( $callback[0] ) || empty( $callback[1] ) ) {
            return null;
        }

        if( is_string( $callback[0] ) ) {
            if( !($listener_obj = $callback[0]::get_instance())
             || !($listener_obj instanceof PHS_Instantiable) ) {
                $this->set_error( self::ERR_LISTEN, self::_t( 'Background listeners should be instances of PHS_Instatiable.' ) );
                return null;
            }

            return $callback;
        }

        if( is_object( $callback[0] ) ) {
            if( !($callback[0] instanceof PHS_Instantiable) ) {
                $this->set_error( self::ERR_LISTEN, self::_t( 'Background listeners should be instances of PHS_Instatiable.' ) );
                return null;
            }

            $callback[0] = @get_class( $callback[0] );

            return $callback;
        }

        return null;
    }

    public function validate_event_input( array $input ): array
    {
        if( empty( $input ) ) {
            return [];
        }

        return self::validate_array( $input, $this->_input_parameters() );
    }

    public function validate_event_output( array $output ): array
    {
        if( empty( $output ) ) {
            return [];
        }

        return self::validate_array( $output, $this->_output_parameters() );
    }

    public static function trigger( array $input = [], string $event_prefix = '', array $params = [] ): ?PHS_Event
    {
        self::st_reset_error();

        /** @var static::class $event_obj */
        if( !($event_obj = static::get_instance( true, static::class )) ) {
            self::st_set_error( self::ERR_TRIGGER, self::_t( 'Error instantiating event.' ) );
            return null;
        }

        if( !$event_obj->do_trigger( $input, $event_prefix, $params ) ) {
            self::st_set_error( self::ERR_TRIGGER, self::_t( 'Error triggering the event.' ) );
            return null;
        }

        return $event_obj;
    }

    public function do_trigger( array $input = [], string $event_prefix = '', array $params = [] )
    {
        $this->reset_error();

        $are_we_in_background = PHS::are_we_in_a_background_thread();

        $params['stop_on_first_error'] = (!empty( $params['stop_on_first_error'] ));

        if( !($callbacks = $this->get_callbacks( $event_prefix )) ) {
            return true;
        }

        if( !($input = $this->validate_event_input( $input )) )
            $input = [];

        $this->set_input( $input );

        $background_listeners = [];
        foreach( $callbacks as $priority => $priority_callbacks )
        {
            if( empty( $priority_callbacks ) || !is_array( $priority_callbacks ) )
                continue;

            foreach( $priority_callbacks as $callback )
            {
                if( empty( $callback ) || !is_array( $callback )
                 || empty( $callback['callback'] ) )
                    continue;

                $in_background = !empty( $callback['options']['in_background'] );

                if( $in_background && !$are_we_in_background ) {
                    $background_listeners[] = $callback;
                    continue;
                }

                if( ($result = @call_user_func( $callback['callback'], $this )) === null
                 && $params['stop_on_first_error'] ) {
                    return false;
                }

            }
        }
    }

    /**
     * @param  string  $event_prefix
     *
     * @return null|array
     */
    public function get_callbacks( string $event_prefix = '' ): ?array
    {
        if( !($callback_index = $this->get_callback_index( $event_prefix ))
            || empty( self::$callbacks[$callback_index] ) )
            return null;

        return self::$callbacks[$callback_index];
    }

    /**
     * @param  null|string  $key
     *
     * @return mixed|null
     */
    public function get_input( string $key = null )
    {
        if( $key === null ) {
            return $this->input;
        }

        return $this->input[$key] ?? null;
    }

    /**
     * @param string|array $key Key for which we want to change value or full array with key/value pairs
     * @param null|mixed $val Value of provided key or null in case we receive a full array of key/value pairs
     *
     * @return bool
     */
    public function set_input( $key, $val = null ): bool
    {
        if( $val === null )
        {
            if( !is_array( $key ) )
                return false;

            foreach( $key as $kkey => $kval )
            {
                if( !is_scalar( $kkey ) )
                    continue;

                $this->input[$kkey] = $kval;
            }

            return true;
        }

        if( !is_scalar( $key ) )
            return false;

        $this->input[$key] = $val;

        return true;
    }

    /**
     * @param  null|string  $key
     *
     * @return mixed|null
     */
    public function get_output( string $key = null )
    {
        if( $key === null ) {
            return $this->output;
        }

        return $this->output[$key] ?? null;
    }

    /**
     * @param string|array $key Key for which we want to change value or full array with key/value pairs
     * @param null|mixed $val Value of provided key or null in case we receive a full array of key/value pairs
     *
     * @return bool
     */
    public function set_output( $key, $val = null ): bool
    {
        if( $val === null )
        {
            if( !is_array( $key ) )
                return false;

            foreach( $key as $kkey => $kval )
            {
                if( !is_scalar( $kkey ) )
                    continue;

                $this->output[$kkey] = $kval;
            }

            return true;
        }

        if( !is_scalar( $key ) )
            return false;

        $this->output[$key] = $val;

        return true;
    }
}