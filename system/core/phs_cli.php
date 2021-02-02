<?php

namespace phs;

use phs\libraries\PHS_Error;
use \phs\libraries\PHS_Registry;

abstract class PHS_Cli extends PHS_Registry
{
    const APP_NAME = 'PHSCLIAPP',
          APP_VERSION = '1.0.0',
          APP_DESCRIPTION = 'This is a PHS CLI application.';

    // Verbose levels... 0 means quiet, Higher level, more details
    const VERBOSE_L0 = 0, VERBOSE_L1 = 1, VERBOSE_L2 = 2, VERBOSE_L3 = 3;

    /** @var int How much verbosity do we need */
    protected $_verbose_level = self::VERBOSE_L1;

    /** @var int|bool Instead of sending verbose level for each echo, we can set it as long as we don't change it again */
    protected $_block_verbose = false;

    /** @var bool Flush _echo commands as soon as received without buffering output */
    protected $_continous_flush = false;

    /** @var string|bool Name of the script in command line */
    protected $_cli_script = false;

    /** @var bool Tells if application output can support colors */
    protected $_output_colors = true;

    /**
     * Options passed to this app in command line
     * @var bool|array
     */
    private $_options = false;

    /**
     * Definition of app options that can be passed in command line
     * @var bool|array
     */
    private $_options_definition = false;

    /**
     * Index array for available options definitions.
     * Used to quickly get to an option definition knowing short or long argument
     * @var bool|array
     */
    private $_options_as_keys = false;

    /**
     * Command passed to this app in command line
     * @var bool|array
     */
    private $_command = false;

    /**
     * Definition of app commands that can be passed in command line
     * @var bool|array
     */
    private $_commands_definition = false;

    /** @var bool|array A result after application runs which will be presented to end-user */
    protected $_app_result = false;

    /** @var PHS_Cli[] */
    private static $_my_instances = [];

    /**
     * Returns directory where app script is
     * @return string
     */
    abstract public function get_app_dir();

    /**
     * Initializes application before starting command line processing
     * If method returns false application will stop, if it returns true all is good
     * If any errors should be displayed in console, $this->set_error() method should be used
     * @return bool
     */
    abstract protected function _init_app();

    /**
     * This method defines command line options which this application expects
     * @return array
     * @see self::
     */
    abstract protected function _get_app_options_definition();

    /**
     * This method defines command line commands which this application expects
     * @return array
     */
    abstract protected function _get_app_commands_definition();

    /**
     * PHS_Cli constructor.
     */
    public function __construct()
    {
        parent::__construct();

        if( empty( $_SERVER ) || !is_array( $_SERVER ) )
            $_SERVER = [];
        if( empty( $_SERVER['argv'] ) || !is_array( $_SERVER['argv'] ) )
            $_SERVER['argv'] = [];

        $cli_arguments = $_SERVER['argv'];

        if( !empty( $cli_arguments[0] ) )
            $this->_cli_script = $cli_arguments[0];

        @array_shift( $cli_arguments );
        if( empty( $cli_arguments ) )
            $cli_arguments = [];

        if( !$this->_init_app() )
            return;

        $this->_validate_app_options( $this->_get_app_options_definition() );

        $this->_validate_app_commands( $this->_get_app_commands_definition() );

        $this->_extract_app_options( $cli_arguments );

        $this->_process_app_options_on_init();

        $this->_extract_app_command( $cli_arguments );
    }

    public function run()
    {
        if( empty( $_SERVER ) || !is_array( $_SERVER )
         || empty( $_SERVER['argc'] )
         || empty( $_SERVER['argv'] ) || !is_array( $_SERVER['argv'] ) )
        {
            $this->_echo( $this->cli_color( 'ERROR', 'red' ).': '.'argv and argc are not set.' );
            $this->_output_result_and_exit();
        }

        if( $_SERVER['argc'] <= 1 )
        {
            $this->cli_option_help();
            $this->_output_result_and_exit();
        }

        $this->_process_app_options_on_run();

        if( !$this->_had_option_as_command()
         && !$this->get_app_command() )
        {
            $this->_echo( $this->cli_color( 'ERROR', 'red' ).': '.self::_t( 'Please provide a command.' ) );

            $this->cli_option_help();
        }

        if( ($command_arr = $this->get_app_command()) )
        {
            $this->_reset_output();

            if( empty( $command_arr['callback'] )
             && !@is_callable( $command_arr['callback'] ) )
            {
                $this->_echo( $this->cli_color( 'ERROR', 'red' ).': '.self::_t( 'Provided command doesn\'t have a callback function.' ) );
                $this->_output_result_and_exit();
            }

            if( false === ($save_result = @call_user_func( $command_arr['callback'] )) )
            {
                $this->_echo( self::_t( '[COMMAND_RUN] Executing command [%s] returned false value.', $command_arr['command_name'] ), [ 'verbose_lvl' => self::VERBOSE_L3 ] );
            }
        }

        $this->_output_result_and_exit();
    }

    protected function _extract_app_command( $argv )
    {
        $this->reset_error();

        $old_verbosity = $this->_verbosity_block( self::VERBOSE_L3 );

        // Obtain command line options and match them against what application has defined as options
        $this->_command = false;
        $command_name = '';
        $command_arguments_arr = [];
        foreach( $argv as $cli_argument )
        {
            // check if argument starts with - or -- (is an option)
            if( 0 === strpos( $cli_argument, '-' )
             || 0 === strpos( $cli_argument, '--' ) )
                continue;

            if( $this->_command !== false
            || ('' !== ($low_cli_argument = strtolower( trim( $cli_argument ) ))
                    && empty( $this->_commands_definition[$low_cli_argument] )) )
            {
                $command_arguments_arr[] = $cli_argument;
                continue;
            }

            $this->_command = $this->_commands_definition[$low_cli_argument];
            $command_name = $low_cli_argument;
        }

        $this->_verbosity_block( $old_verbosity );

        // If we didn't find any command in command line arguments, don't set here the error
        // Error will be displayed in run() method
        if( false === $this->_command )
        {
            return true;
        }

        $this->_command = self::validate_array( $this->_command, self::get_app_selected_command_definition() );
        $this->_command['command_name'] = $command_name;
        $this->_command['arguments'] = $command_arguments_arr;

        return true;
    }

    protected function _extract_app_options( $argv )
    {
        // Obtain command line options and match them against what application has defined as options
        $this->_options = [];
        $old_verbosity = $this->_verbosity_block( self::VERBOSE_L3 );
        foreach( $argv as $cli_argument )
        {
            if( !($parsed_option = $this->_extract_option_from_argument( $cli_argument )) )
            {
                continue;
            }

            $app_option_name = $parsed_option['option'];
            $index_arr = false;
            if( !empty( $parsed_option['is_short'] ) )
            {
                if( !empty( $this->_options_as_keys['short'][$parsed_option['option']] ) )
                    $index_arr = $this->_options_as_keys['short'][$parsed_option['option']];
            } else
            {
                if( !empty( $this->_options_as_keys['long'][$parsed_option['option']] ) )
                    $index_arr = $this->_options_as_keys['long'][$parsed_option['option']];
            }

            if( !empty( $index_arr )
             && !empty( $this->_options_definition[$index_arr['option_name']] ) )
            {
                $app_option_name = $index_arr['option_name'];
                $parsed_option['app_option'] = $this->_options_definition[$index_arr['option_name']];
            }

            $this->_options[$app_option_name] = $parsed_option;
        }

        $this->_verbosity_block( $old_verbosity );
    }

    private function _extract_option_from_argument( $arg )
    {
        if( empty( $arg ) || !is_string( $arg ) )
            return false;

        $is_short_arg = false;
        if( strpos( $arg, '--' ) === 0 )
        {
            $arg = substr( $arg, 2 );
        } elseif( strpos( $arg, '-' ) === 0 )
        {
            $is_short_arg = true;
            $arg = substr( $arg, 1 );
        } else
        {
            return false;
        }

        $return_arr = self::get_command_line_option_node_definition();
        $return_arr['is_short'] = $is_short_arg;
        $return_arr['option'] = $arg;
        $return_arr['value'] = true;

        if( ($arg_arr = @explode( '=', $arg, 2 ))
         && isset( $arg_arr[0] ) && $arg_arr[0] !== '' )
        {
            $return_arr['option'] = $arg_arr[0];
            if( isset( $arg_arr[1] ) )
            {
                $return_arr['value'] = $arg_arr[1];
                if( $return_arr['value'] === 'null' )
                    $return_arr['value'] = null;
                elseif( $return_arr['value'] === 'false' )
                    $return_arr['value'] = false;
                elseif( $return_arr['value'] === 'true' )
                    $return_arr['value'] = true;
                elseif( is_numeric( $return_arr['value'] ) )
                {
                    if( false === strpos( $return_arr['value'], '.' ) )
                        $return_arr['value'] = (int)$return_arr['value'];
                    else
                        $return_arr['value'] = (float)$return_arr['value'];
                }
            }
        }

        $return_arr['option'] = strtolower( $return_arr['option'] );

        return $return_arr;
    }

    protected function _output_result_and_exit()
    {
        if( $this->_app_result === false )
            $this->_reset_app_result();

        // Exit statuses should be in the range 0 to 254, the exit status 255 is reserved by PHP and shall not be used.
        // The status 0 is used to terminate the program successfully.
        if( 254 < ($exit_code = (int)$this->get_error_code()) )
            $exit_code = 254;

        if( self::VERBOSE_L0 === $this->get_app_verbosity()
         || $this->_app_result['buffer'] === '' )
            exit( $exit_code );

        $this->_flush_output();

        exit( $exit_code );
    }

    protected function _flush_output()
    {
        if( $this->_app_result === false )
            $this->_reset_app_result();

        echo $this->_app_result['buffer'];
        $this->_app_result['buffer'] = '';
    }

    protected function _reset_output()
    {
        if( $this->_app_result === false )
            $this->_reset_app_result();

        $this->_app_result['buffer'] = '';
    }

    protected function _add_buffer_to_result( $buf )
    {
        if( $this->_app_result === false )
            $this->_reset_app_result();

        $this->_app_result['buffer'] .= $buf;
    }

    protected function _had_option_as_command( $val = null )
    {
        if( $this->_app_result === false )
            $this->_reset_app_result();

        if( $val === null )
            return (bool)$this->_app_result['had_option_as_command'];

        $this->_app_result['had_option_as_command'] = (!empty( $val ));

        return true;
    }

    protected function _reset_app_result()
    {
        $this->_app_result = self::get_app_result_definition();
    }

    protected function _process_app_options_on_init()
    {
        // Start processing each parameter based on priority
        if( !empty( $this->_options ) )
        {
            foreach( $this->_options as $app_option_name => $app_option_details )
            {
                $this->_echo( self::_t( '[INIT_LEVEL] Executing option [%s]', $app_option_name ), [ 'verbose_lvl' => self::VERBOSE_L3 ] );

                if( empty( $app_option_details['app_option'] )
                 || !is_array( $app_option_details['app_option'] )
                 || empty( $app_option_details['app_option']['callback_init'] )
                 || !is_callable( $app_option_details['app_option']['callback_init'] ) )
                {
                    $this->_echo( self::_t( '[INIT_LEVEL] Nothing to execute for option [%s]', $app_option_name ), [ 'verbose_lvl' => self::VERBOSE_L3 ] );
                    continue;
                }

                if( !empty( $app_option_details['app_option']['behaves_as_command'] ) )
                    $this->_had_option_as_command( true );

                if( false === ($save_result = @call_user_func( $app_option_details['app_option']['callback_init'], $app_option_details )) )
                {
                    $this->_echo( self::_t( '[INIT_LEVEL] Executing option [%s] returned false value.', $app_option_name ), [ 'verbose_lvl' => self::VERBOSE_L3 ] );
                }
            }
        }
    }

    protected function _process_app_options_on_run()
    {
        // Start processing options that have callbacks for run() method
        if( empty( $this->_options ) )
            return;

        foreach( $this->_options as $app_option_name => $app_option_details )
        {
            $this->_echo( self::_t( '[RUN_LEVEL] Executing option [%s]', $app_option_name ), [ 'verbose_lvl' => self::VERBOSE_L3 ] );

            if( empty( $app_option_details['app_option'] )
             || !is_array( $app_option_details['app_option'] )
             || empty( $app_option_details['app_option']['callback_run'] )
             || !is_callable( $app_option_details['app_option']['callback_run'] ) )
            {
                $this->_echo( self::_t( '[RUN_LEVEL] Nothing to execute for option [%s]', $app_option_name ), [ 'verbose_lvl' => self::VERBOSE_L3 ] );
                continue;
            }

            if( !empty( $app_option_details['app_option']['behaves_as_command'] ) )
                $this->_had_option_as_command( true );

            if( false === ($save_result = @call_user_func( $app_option_details['app_option']['callback_run'], $app_option_details )) )
            {
                $this->_echo( self::_t( '[RUN_LEVEL] Executing option [%s] returned false value.', $app_option_name ), [ 'verbose_lvl' => self::VERBOSE_L3 ] );
            }
        }
    }

    public function get_app_name()
    {
        return static::APP_NAME;
    }

    public function get_app_version()
    {
        return static::APP_VERSION;
    }

    public function get_app_description()
    {
        return static::APP_DESCRIPTION;
    }

    public function get_app_options()
    {
        return $this->_options;
    }

    public function get_app_command()
    {
        return $this->_command;
    }

    public function get_app_options_definition()
    {
        return $this->_options_definition;
    }

    public function get_app_commands_definition()
    {
        return $this->_commands_definition;
    }

    public function get_app_verbosity()
    {
        return $this->_verbose_level;
    }

    public function get_app_cli_script()
    {
        return $this->_cli_script;
    }

    public function get_app_output_colors()
    {
        return $this->_output_colors;
    }

    public static function get_app_result_definition()
    {
        return [
            // Buffer that will be displayed as result
            'buffer' => '',
            // Tells if we run an option which behaves as command
            'had_option_as_command' => false,
        ];
    }

    public static function get_app_command_node_definition()
    {
        return [
            // Description used when building --help option
            'description' => 'No description.',
            // When this option is present we will call this callback (if present)
            'callback' => false,
        ];
    }

    public static function get_app_selected_command_definition()
    {
        return self::validate_array( [
             // Keep command name in selected command array
             'command_name' => '',
             // If arguments from command line doesn't match an option or a command they will be added to command arguments
             'arguments' => [], ], self::get_app_command_node_definition() );
    }

    public static function get_app_option_node_definition()
    {
        return [
            // short parameter passed in cli line (eg. -u)
            'short' => '',
            // long description passed in cli line (eg. --user)
            'long' => '',
            // for options which behaves as a command (eg. -h, --help, -v, --version, etc)
            'behaves_as_command' => false,
            // Description used when building --help option
            'description' => 'No description.',
            // Default value if not present as option
            'default' => null,
            // If a callback is given here, this callback will be called in constructor @see self::_process_app_options_on_init()
            'callback_init' => false,
            // If a callback is given here, this callback will be called in run() method @see self::_process_app_options_on_run()
            'callback_run' => false,
        ];
    }

    public static function get_command_line_option_node_definition()
    {
        return [
            // Tells if parameter passed to application was short version or not (eg. -u=5 or --user=5)
            'is_short' => true,
            // What parameter was extracted from command line (eg. u or user)
            'option' => '',
            // Value extracted after = sign from command line argument (eg. 5)
            'value' => null,
            // App option that matched this argument (this is app parameter node definition, @see self::get_app_option_node_definition())
            // if this is null, means no parameter matched this and maybe this argument is used by other app parameter
            'app_option' => null,
        ];
    }

    protected function _default_app_commands()
    {
        return [
            'help' => [
                'description' => 'Gets more help about commands. Try help {other command}.',
                'callback' => [ $this, 'cli_command_help' ],
            ],
        ];
    }

    protected function _default_app_options()
    {
        return [
            'verbosity' => [
                'short' => 'vb',
                'long' => 'verbose',
                'description' => 'Verbosity level for this application',
                'callback_init' => [ $this, 'cli_option_verbosity' ],
            ],
            'output_colors' => [
                'short' => 'bw',
                'long' => 'black-white',
                'description' => 'Output will have no colors',
                'callback_init' => [ $this, 'cli_option_output_colors' ],
            ],
            'continous_flush' => [
                'short' => 'cf',
                'long' => 'continous-flush',
                'description' => 'Output will not get buffered, but displayed directly',
                'callback_init' => [ $this, 'cli_option_continous_flush' ],
            ],
            'version' => [
                'short' => 'v',
                'long' => 'version',
                'behaves_as_command' => true,
                'description' => 'Version details',
                'callback_run' => [ $this, 'cli_option_version' ],
            ],
            'help' => [
                'short' => 'h',
                'long' => 'help',
                'behaves_as_command' => true,
                'description' => 'List help page',
                'callback_run' => [ $this, 'cli_option_help' ],
            ],
        ];
    }

    /**
     * @param array $app_options
     *
     * @return array|bool
     */
    private function _validate_app_options( $app_options )
    {
        $this->reset_error();

        if( empty( $app_options ) || !is_array( $app_options ) )
            $app_options = [];

        $app_options = self::validate_array( $app_options, $this->_default_app_options() );

        $node_definition = self::get_app_option_node_definition();

        $option_count = 0;
        $return_arr = [];
        foreach( $app_options as $option_name => $option_arr )
        {
            $option_count++;

            if( !is_string( $option_name )
             || $option_name === '' )
            {
                $this->set_error( self::ERR_PARAMETERS, self::_t( 'Option %s should have a string in key array definition.', $option_count ) );
                return false;
            }

            if( empty( $option_arr ) || !is_array( $option_arr ) )
            {
                $this->set_error( self::ERR_PARAMETERS, self::_t( 'Option %s should have an array as definition.', $option_name ) );
                return false;
            }

            $option_arr = self::validate_array( $option_arr, $node_definition );

            // All CLI parameters are case insensitive (convert all to lowercase)
            $return_arr[strtolower($option_name)] = $option_arr;
        }

        $this->_options_definition = $return_arr;

        // Make indexes after we sort by priority so they get overwritten (eventually) depending priority
        $this->_options_as_keys = [];
        foreach( $return_arr as $option_name => $option_arr )
        {
            if( !empty( $option_arr['short'] ) )
            {
                $this->_options_as_keys['short'][strtolower( $option_arr['short'] )] = [ 'option_name' => $option_name, ];
            }

            if( !empty( $option_arr['long'] ) )
            {
                $this->_options_as_keys['long'][strtolower( $option_arr['long'] )] = [ 'option_name' => $option_name, ];
            }
        }

        return $this->_options_definition;
    }

    /**
     * @param array $app_commands
     *
     * @return array|bool
     */
    private function _validate_app_commands( $app_commands )
    {
        $this->reset_error();

        if( empty( $app_commands ) || !is_array( $app_commands ) )
            $app_commands = [];

        $app_commands = self::validate_array( $app_commands, $this->_default_app_commands() );

        $node_definition = self::get_app_command_node_definition();

        $command_count = 0;
        $return_arr = [];
        foreach( $app_commands as $command_name => $command_arr )
        {
            $command_count++;

            if( !is_string( $command_name )
             || $command_name === '' )
            {
                $this->set_error( self::ERR_PARAMETERS, self::_t( 'Command %s should have a string in key array definition.', $command_count ) );
                return false;
            }

            if( empty( $command_arr ) || !is_array( $command_arr ) )
            {
                $this->set_error( self::ERR_PARAMETERS, self::_t( 'Command %s should have an array as definition.', $command_name ) );
                return false;
            }

            $command_arr = self::validate_array( $command_arr, $node_definition );

            // All CLI parameters are case insensitive (convert all to lowercase)
            $return_arr[strtolower($command_name)] = $command_arr;
        }

        $this->_commands_definition = $return_arr;

        return $this->_commands_definition;
    }

    /**
     * @param array $args
     *
     * @return bool
     */
    public function cli_option_verbosity( $args )
    {
        if( empty( $args ) || !is_array( $args )
         || !isset( $args['value'] ) )
            return false;

        $this->_verbose_level = (int)$args['value'];
        return true;
    }

    /**
     * @param array $args
     *
     * @return bool
     */
    public function cli_option_output_colors( $args )
    {
        if( empty( $args ) || !is_array( $args )
         || !isset( $args['value'] ) )
            return false;

        $this->_output_colors = (empty( $args['value'] ));
        return true;
    }

    /**
     * @param array $args
     *
     * @return bool
     */
    public function cli_option_continous_flush( $args )
    {
        if( empty( $args ) || !is_array( $args )
         || !isset( $args['value'] ) )
            return false;

        $this->_continous_flush = (empty( $args['value'] ));
        return true;
    }

    /**
     * @return bool
     */
    public function cli_option_help()
    {
        if( !$this->cli_option_version() )
            return false;

        $this->_echo( '' );
        $this->_echo( 'Usage: '.$this->get_app_cli_script().' [options] [command] [arguments]' );
        $this->_echo( self::_t( 'There can be only one command per script run.' ) );
        $this->_echo( '' );

        if( ($commands_arr = $this->get_app_commands_definition()) )
        {
            $this->_echo( self::_t( 'Commands' ).':' );
            foreach( $commands_arr as $command_name => $command_arr )
            {
                $this->_echo( $command_name );

                if( !empty( $command_arr['description'] ) )
                    $this->_echo( '  '.$command_arr['description'] );
            }

            $this->_echo( '' );
        }

        if( ($options_arr = $this->get_app_options_definition()) )
        {
            $this->_echo( self::_t( 'Options' ).':' );
            foreach( $options_arr as $option_name => $option_arr )
            {
                $option_vars = '';
                if( !empty( $option_arr['short'] ) )
                    $option_vars .= '-'.$option_arr['short'];
                if( !empty( $option_arr['long'] ) )
                    $option_vars .= ($option_vars!==''?', ':'').'--'.$option_arr['long'];

                $this->_echo( $option_vars );

                if( !empty( $option_arr['description'] ) )
                    $this->_echo( '  '.$option_arr['description'] );
            }

            $this->_echo( '' );
        }

        return true;
    }

    /**
     * @return bool
     */
    public function cli_option_version()
    {
        $this->_echo( $this->get_app_name().' - version '.$this->get_app_version() );
        $this->_echo( $this->get_app_description() );

        return true;
    }

    /**
     * @return bool
     */
    public static function running_in_cli()
    {
        return (PHP_SAPI==='cli');
    }

    /**
     * When passing another verbosity level, old verbosity level is returned
     * @param bool|int $lvl
     * @return int
     */
    protected function _verbosity_block( $lvl = false )
    {
        if( $lvl === false )
            return $this->_block_verbose;

        $old_verbosity = ($this->_block_verbose===false?$this->get_app_verbosity():$this->_block_verbose);
        $this->_block_verbose = (int)$lvl;
        return $old_verbosity;
    }

    /**
     * Get or set conitnous flush status: flush output when calling _echo commands rather than buffering output.
     * When changing continous flush status, method returns old settings.
     * @param bool|null $flush
     * @return bool
     */
    protected function _continous_flush( $flush = null )
    {
        if( $flush === null )
            return $this->_continous_flush;

        $old_continous_flush = $this->_continous_flush;
        $this->_continous_flush = (!empty( $flush ));
        return $old_continous_flush;
    }

    /**
     * @return bool|int
     */
    protected function _reset_verbosity_block()
    {
        $old_verbosity = $this->_block_verbose;
        $this->_block_verbose = false;
        return $old_verbosity;
    }

    /**
     * Extract first argument available in an array of arguments and return an array
     * with arg key first available string untill first space and rest key with rest of arguments
     * @param array $args_arr
     * @return bool|array
     */
    protected static function _get_one_argument( $args_arr )
    {
        if( empty( $args_arr )
         || !is_array( $args_arr )
         || null === ($first_arg = @array_shift( $args_arr ))
         || !is_string( $first_arg ) )
            return false;

        if( '' === ($first_arg = trim( $first_arg )) )
        {
            if( empty( $args_arr ) )
                return false;

            return static::_get_one_argument( $args_arr );
        }

        return [
            'arg' => $first_arg,
            'rest' => $args_arr,
        ];
    }

    /**
     * Useful when wanting to get command line arguments (array of arguments) one by one in consecutive method calls.
     * eg. $this->_get_argument_chained( [ 'arg1', 'arg2', 'arg3' ] ); $this->_get_argument_chained(); $this->_get_argument_chained()
     * If methods are called consecutively each call will return arg1, arg2 and arg3 respectively
     * @param bool|array $args_arr
     *
     * @return bool|string
     */
    protected function _get_argument_chained( $args_arr = false )
    {
        /** @var array|bool $arguments */
        static $arguments = false;

        if( false !== $args_arr )
            $arguments = $args_arr;

        if( empty( $arguments )
         || !($args_result = $this::_get_one_argument( $arguments )) )
            return false;

        $arguments = $args_result['rest'];

        return $args_result['arg'];
    }

    /**
     * @param string $msg
     * @param bool|array $params
     *
     * @return bool
     */
    public function _echo_error( $msg, $params = false )
    {
        return $this->_echo( $this->cli_color( self::_t( 'ERROR' ), 'red' ).': '.$msg, $params );
    }

    /**
     * @param string $msg
     * @param bool|array $params
     *
     * @return bool
     */
    public function _echo( $msg, $params = false )
    {
        if( !is_string( $msg ) )
            return false;

        if( empty( $params ) || !is_array( $params ) )
            $params = [];

        if( !isset( $params['verbose_lvl'] ) )
        {
            if( false === ($params['verbose_lvl'] = $this->_verbosity_block()) )
                $params['verbose_lvl'] = self::VERBOSE_L1;
        } else
            $params['verbose_lvl'] = (int)$params['verbose_lvl'];

        if( !($now_verbosity = $this->get_app_verbosity())
         || $now_verbosity < $params['verbose_lvl'] )
            return true;

        if( empty( $params['force_echo'] ) )
            $params['force_echo'] = false;
        else
            $params['force_echo'] = (!empty( $params['force_echo'] )?true:false);

        if( empty( $params['flush_output'] ) )
            $params['flush_output'] = false;
        else
            $params['flush_output'] = (!empty( $params['flush_output'] )?true:false);

        $msg .= "\n";

        $this->_add_buffer_to_result( $msg );

        if( !empty( $params['flush_output'] )
         || $this->_continous_flush() )
            $this->_flush_output();

        if( !empty( $params['force_echo'] ) )
            echo $msg;

        return true;
    }

    /**
     * Sets colors for
     * @param string $str
     * @param string $color
     * @param bool|string $background
     *
     * @return string
     */
    public function cli_color( $str, $color, $background = false )
    {
        if( !$this->get_app_output_colors() )
            return $str;

        return self::st_cli_color( $str, $color, $background );
    }

    /**
     * Sets colors for
     * @param string $str
     * @param string $color
     * @param bool|string $background
     *
     * @return string
     */
    public static function st_cli_color( $str, $color, $background = false )
    {
        $colors_arr = self::get_cli_colors_definition();

        if( empty( $colors_arr['color'][$color] )
         && empty( $colors_arr['background'][$background] ) )
            return $str;

        if( empty( $colors_arr['color'][$color] ) )
            $color = false;
        else
            $color = $colors_arr['color'][$color];

        if( empty( $colors_arr['background'][$background] ) )
            $background = false;
        else
            $background = $colors_arr['background'][$background];

        $colored_str = '';
        if( $color !== false )
            $colored_str .= "\033[".$color.'m';
        if( $background !== false )
            $colored_str .= "\033[".$background.'m';

        $colored_str .= $str."\033[0m";

        return $colored_str;
    }

    public static function get_cli_colors_definition()
    {
        return [
            'color' => [
                'black' => '0;30',
			    'dark_gray' => '1;30',
			    'blue' => '0;34',
			    'light_blue' => '1;34',
                'light_green' => '1;32',
                'green' => '0;32',
                'cyan' => '0;36',
			    'light_cyan' => '1;36',
			    'red' => '0;31',
			    'light_red' => '1;31',
			    'purple' => '0;35',
			    'light_purple' => '1;35',
			    'brown' => '0;33',
			    'yellow' => '1;33',
			    'light_gray' => '0;37',
			    'white' => '1;37',
            ],
            'background' => [
                'black' => '40',
                'red' => '41',
                'green' => '42',
                'yellow' => '43',
                'blue' => '44',
                'magenta' => '45',
                'cyan' => '46',
                'light_gray' => '47',
            ],
        ];
    }

    /**
     * @param bool|string $app_class_name
     *
     * @return bool|\phs\PHS_Cli
     */
    public static function get_instance( $app_class_name = false )
    {
        // Late Static Bindings (static::) added in 5.3
        // ::class added in 5.5 => we will add PHP 5.5 dependency if we use static::class
        // so we use get_called_class() added in PHP 5.3
        if( $app_class_name === false )
        {
            if( !($app_class_name = @get_called_class()) )
            {
                self::st_copy_error( self::_t( 'Cannot obtain called class name.' ) );
                return false;
            }
        }

        if( !empty( self::$_my_instances[$app_class_name] ) )
            return self::$_my_instances[$app_class_name];

        /** @var \phs\PHS_Cli $app_instance */
        $app_instance = new $app_class_name();
        if( $app_instance->has_error() )
        {
            self::st_copy_error( $app_instance );
            return false;
        }

        self::$_my_instances[$app_class_name] = $app_instance;

        return self::$_my_instances[$app_class_name];
    }
}
