<?php

final class PHS extends PHS_Registry
{
    const ERR_HOOK_REGISTRATION = 2000, ERR_LOAD_MODEL = 2001;

    const REQUEST_FULL_HOST = 'request_full_host', REQUEST_HOST = 'request_host', REQUEST_PORT = 'request_port', REQUEST_HTTPS = 'request_https',
          COOKIE_DOMAIN = 'cookie_domain';

    private static $inited = false;
    private static $instance = false;
    private static $hooks = array();

    function __construct()
    {
        parent::__construct();

        self::init();
    }

    /**
     * Check what server receives in request
     */
    public static function init()
    {
        if( self::$inited )
            return;

        self::reset_registry();

        $cookie_domain = PHS_DEFAULT_DOMAIN;
        $request_full_host = '';
        $request_host = '';
        $request_port = '';

        if( empty( $_SERVER['HTTP_HOST'] ) )
        {
            $request_full_host = $cookie_domain;
            $request_host = $cookie_domain;
        } else
        {
            // sanity cleaning (request comes from outside), $request_full_host will be used to load domain specific settings...
            $request_full_host = str_replace(
                                    array( '..', '/', '~', '<', '>', '|' ),
                                    array( '.',  '',  '',  '',  '',  '' ),
                                    $_SERVER['HTTP_HOST'] );

            if( strstr( $request_full_host, ':' ) !== false
            and ($host_details = explode( ':', $request_full_host, 2 )) )
            {
                $request_host = $host_details[0];
                $request_port = (!empty( $host_details[1] )?$host_details[1]:'');
            } else
            {
                $request_host = $request_full_host;
            }

            $cookie_domain = $request_host;
        }

        if( isset( $_SERVER['HTTPS'] )
        and ($_SERVER['HTTPS'] == 'on' or $_SERVER['HTTPS'] == '1') )
            self::set_data( self::REQUEST_HTTPS, true );
        else
            self::set_data( self::REQUEST_HTTPS, false );

        self::set_data( self::REQUEST_FULL_HOST, $request_full_host );
        self::set_data( self::REQUEST_HOST, $request_host );
        self::set_data( self::REQUEST_PORT, $request_port );
        self::set_data( self::COOKIE_DOMAIN, $cookie_domain );

        self::$inited = true;
    }

    private static function reset_registry()
    {
        self::set_data( self::REQUEST_FULL_HOST, '' );
        self::set_data( self::REQUEST_HOST, '' );
        self::set_data( self::REQUEST_PORT, '' );
        self::set_data( self::COOKIE_DOMAIN, '' );
        self::set_data( self::REQUEST_HTTPS, false );
    }

    public static function domain_constants()
    {
        return array(
            // configuration constants
            'PHS_DOMAIN' => 'PHS_DEFAULT_DOMAIN',
            'PHS_PORT' => 'PHS_DEFAULT_PORT',
            'PHS_DOMAIN_PATH' => 'PHS_DEFAULT_DOMAIN_PATH',

            'PHS_THEME' => 'PHS_DEFAULT_THEME',

            'PHS_CRYPT_KEY' => 'PHS_DEFAULT_CRYPT_KEY',

            'PHS_DB_CONNECTION' => 'PHS_DB_DEFAULT_CONNECTION',

            'PHS_SESSION_DIR' => 'PHS_DEFAULT_SESSION_DIR',
            'PHS_SESSION_NAME' => 'PHS_DEFAULT_SESSION_NAME',
            'PHS_SESSION_COOKIE_LIFETIME' => 'PHS_DEFAULT_SESSION_COOKIE_LIFETIME',
            'PHS_SESSION_COOKIE_PATH' => 'PHS_DEFAULT_SESSION_COOKIE_PATH',
            'PHS_SESSION_AUTOSTART' => 'PHS_DEFAULT_SESSION_AUTOSTART',
        );
    }

    public static function define_constants()
    {
        $constants_arr = self::domain_constants();
        foreach( $constants_arr as $domain_constant => $default_constant )
        {
            if( defined( $domain_constant ) )
                continue;

            $constant_value = '';
            if( defined( $default_constant ) )
                $constant_value = constant( $default_constant );

            define( $domain_constant, $constant_value );
        }
    }

    public static function get_base_url( $force_https = false )
    {
        if( !empty( $force_https )
         or self::get_data( self::REQUEST_HTTPS ) )
        {
            // if domain settings are set
            if( defined( 'PHS_HTTPS' ) )
                return PHS_HTTPS;
            // if default domain settings are set
            elseif( defined( 'PHS_DEFAULT_HTTPS' ) )
                return PHS_DEFAULT_HTTPS;
        } else
        {
            // if domain settings are set
            if( defined( 'PHS_HTTP' ) )
                return PHS_HTTP;
            // if default domain settings are set
            elseif( defined( 'PHS_DEFAULT_HTTP' ) )
                return PHS_DEFAULT_HTTP;
        }

        return false;
    }

    public static function get_instance()
    {
        if( !empty( self::$instance ) )
            return self::$instance;

        self::$instance = new PHS();
        return self::$instance;
    }

    /**
     * @param string $model_name
     *
     * @return false|PHS_Model Returns false on error or an instance of loaded model
     */
    public static function load_model( $model, $plugin = false )
    {
        if( empty( $model )
         or !($model_name = PHS_Instantiable::safe_escape_name( $model )) )
        {
            self::st_set_error( self::ERR_LOAD_MODEL, self::_t( 'Couldn\'t load model %s from plugin %s.', $model, (empty( $plugin )?'CORE':$plugin) ) );
            return false;
        }

        $class_name = 'PHS_Model_'.ucfirst( $model_name );

        if( !($instance_obj = PHS_Instantiable::get_instance( $class_name, $plugin, PHS_Instantiable::INSTANCE_TYPE_MODEL )) )
        {
            if( !self::st_has_error() )
                self::st_set_error( self::ERR_LOAD_MODEL, self::_t( 'Couldn\'t obtain instance for model %s from plugin %s .', $model, (empty( $plugin )?'CORE':$plugin) ) );
            return false;
        }

        return $instance_obj;
    }

    /**
     * Validates a hook name and returns valid value or false if hook name is not valid.
     *
     * @param string $hook_name
     *
     * @return bool|string Valid hook name or false if hook_name is not valid.
     */
    public static function prepare_hook_name( $hook_name )
    {
        if( !is_string( $hook_name )
         or !($hook_name = strtolower( trim( $hook_name ) )) )
            return false;

        return $hook_name;
    }

    /**
     * Adds a hook in call queue. When a hook is fired, script will call each callback function in order of their
     * priority. Along with standard hook parameters (check each hook definition to see which are these) you can add
     * extra parameters which you pass at hook definition
     *
     * @param string $hook_name             Hook name
     * @param callback $hook_callback       Method/Function to be called
     * @param null|array $hook_extra_args   Extra arguments to be passed when hook is fired
     * @param bool $chained_hook            If true result of hook call will overwrite parameters of next hook callback (can be used as filters)
     * @param int $priority                 Order in which hooks are fired is given by $priority parameter
     *
     * @return bool                     True if hook was added with success or false otherwise
     */
    public static function register_hook( $hook_name, $hook_callback = null, $hook_extra_args = null, $chained_hook = false, $priority = 10 )
    {
        self::st_reset_error();

        if( !($hook_name = self::prepare_hook_name( $hook_name )) )
        {
            self::st_set_error( self::ERR_HOOK_REGISTRATION, self::_t( 'Please provide a valid hook name.' ) );
            return false;
        }

        if( !is_null( $hook_callback ) and !is_callable( $hook_callback ) )
        {
            self::st_set_error( self::ERR_HOOK_REGISTRATION, self::_t( 'Couldn\'t add callback for hook %s.', $hook_name ) );
            return false;
        }

        $hookdata = array();
        $hookdata['callback'] = $hook_callback;
        $hookdata['args'] = $hook_extra_args;
        $hookdata['chained'] = (!empty( $chained_hook )?true:false);

        self::$hooks[$hook_name][$priority][] = $hookdata;

        ksort( self::$hooks[$hook_name], SORT_NUMERIC );

        return true;
    }

    public static function unregister_hooks( $hook_name = false )
    {
        if( $hook_name === false )
        {
            self::$hooks[$hook_name] = array();
            return true;
        }

        if( !($hook_name = self::prepare_hook_name( $hook_name ))
         or !isset( self::$hooks[$hook_name] ) )
            return false;

        unset( self::$hooks[$hook_name] );

        return true;
    }

    public static function trigger_hooks( $hook_name, array $hook_args = array() )
    {
        if( !($hook_name = self::prepare_hook_name( $hook_name ))
            or empty( self::$hooks[$hook_name] ) or !is_array( self::$hooks[$hook_name] ) )
            return null;

        if( empty( $hook_args ) or !is_array( $hook_args ) )
            $hook_args = array();

        foreach( self::$hooks[$hook_name] as $priority => $hooks_array )
        {
            if( empty( $hooks_array ) or !is_array( $hooks_array ) )
                continue;

            foreach( $hooks_array as $hook_callback )
            {
                if( empty( $hook_callback ) or !is_array( $hook_callback )
                    or empty( $hook_callback['callback'] ) )
                    continue;

                if( empty( $hook_callback['args'] ) )
                    $hook_callback['args'] = array();

                $call_hook_args = array_merge( $hook_callback['args'], $hook_args );

                $result = @call_user_func( $hook_callback['callback'], $call_hook_args );

                if( !empty( $hook_callback['chained'] )
                    and is_array( $result ) )
                    $hook_args = array_merge( $hook_args, $result );
            }
        }

        // Return final hook arguments as result of hook calls
        return $hook_args;
    }

    public static function error_handler( $errno, $errstr, $errfile, $errline, $errcontext )
    {
        if( !error_reporting() )
            return true;

        echo '<pre>'.self::st_debug_call_backtrace().'</pre>';

        switch( $errno )
        {
            case E_ERROR:
            case E_USER_ERROR:
                // end all buffers
                while( @ob_end_flush() );

                echo "<b>ERROR</b> [$errno] ($errfile:$errline) $errstr<br />\n";
                exit(1);
            break;

            case E_WARNING:
            case E_USER_WARNING:
                echo "<b>WARNING</b> [$errno] ($errfile:$errline) $errstr<br />\n";
            break;

            case E_NOTICE:
            case E_USER_NOTICE:
                echo "<b>NOTICE</b> [$errno] ($errfile:$errline) $errstr<br />\n";
            break;

            default:
                echo "Unknown error type: [$errno] ($errfile:$errline) $errstr<br />\n";
            break;
        }

        return true;
    }
}
