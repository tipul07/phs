<?php
namespace phs;

use phs\libraries\PHS_Registry;

abstract class PHS_Cli extends PHS_Registry
{
    public const APP_NAME = 'PHSCLIAPP',
        APP_VERSION = '1.0.0',
        APP_DESCRIPTION = 'This is a PHS CLI application.';

    // Verbose levels... 0 means quiet, Higher level, more details
    public const VERBOSE_L0 = 0, VERBOSE_L1 = 1, VERBOSE_L2 = 2, VERBOSE_L3 = 3;

    /** @var int How much verbosity do we need */
    protected int $_verbose_level = self::VERBOSE_L1;

    /** @var int|bool Instead of sending verbose level for each echo, we can set it as long as we don't change it again */
    protected $_block_verbose = false;

    /** @var bool Flush _echo commands as soon as received without buffering output */
    protected bool $_continous_flush = false;

    /** @var string Name of the script in command line */
    protected string $_cli_script = '';

    /** @var bool Tells if application output can support colors */
    protected bool $_output_colors = true;

    /** @var array A result after application runs which will be presented to end-user */
    protected array $_app_result = [];

    /**
     * Options passed to this app in command line
     * @var array
     */
    private array $_options = [];

    /**
     * Options passed to this app in command line (which are not matching application options - might be command options)
     * @var array
     */
    private array $_command_options = [];

    /**
     * Definition of app options that can be passed in command line
     * @var array
     */
    private array $_options_definition = [];

    /**
     * Index array for available options definitions.
     * Used to quickly get to an option definition knowing short or long argument
     * @var array
     */
    private array $_options_as_keys = [];

    /**
     * Command passed to this app in command line
     * @var array
     */
    private array $_command = [];

    /**
     * Definition of app commands that can be passed in command line
     * @var array
     */
    private array $_commands_definition = [];

    /** @var PHS_Cli[] */
    private static array $_my_instances = [];

    /**
     * PHS_Cli constructor.
     */
    public function __construct()
    {
        parent::__construct();

        if (empty($_SERVER) || !is_array($_SERVER)) {
            $_SERVER = [];
        }
        if (empty($_SERVER['argv']) || !is_array($_SERVER['argv'])) {
            $_SERVER['argv'] = [];
        }

        $cli_arguments = $_SERVER['argv'];

        if (!empty($cli_arguments[0])) {
            $this->_cli_script = $cli_arguments[0];
        }

        @array_shift($cli_arguments);
        if (empty($cli_arguments)) {
            $cli_arguments = [];
        }

        if (!$this->_init_app()) {
            return;
        }

        $this->_validate_app_options($this->_get_app_options_definition());

        $this->_validate_app_commands($this->_get_app_commands_definition());

        if (($new_cli_arguments = $this->_extract_app_and_command_options($cli_arguments))) {
            $cli_arguments = $new_cli_arguments;
        }

        $this->_process_app_options_on_init();

        $this->_extract_app_command($cli_arguments);
    }

    /**
     * Returns directory where app script is
     * @return string
     */
    abstract public function get_app_dir() : string;

    /**
     * Initializes application before starting command line processing
     * If method returns false application will stop, if it returns true all is good
     * If any errors should be displayed in console, $this->set_error() method should be used
     * @return bool
     */
    abstract protected function _init_app() : bool;

    /**
     * This method defines command line options which this application expects
     * @return array
     * @see self::
     */
    abstract protected function _get_app_options_definition() : array;

    /**
     * This method defines command line commands which this application expects
     * @return array
     */
    abstract protected function _get_app_commands_definition() : array;

    public function run() : void
    {
        if (empty($_SERVER) || !is_array($_SERVER)
         || empty($_SERVER['argc'])
         || empty($_SERVER['argv']) || !is_array($_SERVER['argv'])) {
            $this->_echo($this->cli_color('ERROR', 'red').': '.'argv and argc are not set.');
            $this->_output_result_and_exit();
        }

        if ($_SERVER['argc'] <= 1) {
            $this->cli_option_help();
            $this->_output_result_and_exit();
        }

        $this->_process_app_options_on_run();

        if (!$this->_had_option_as_command()
         && !$this->get_app_command()) {
            $this->_echo($this->cli_color('ERROR', 'red').': '.self::_t('Please provide a command.'));

            $this->cli_option_help();
        }

        if (($command_arr = $this->get_app_command())) {
            $this->_reset_output();

            if (empty($command_arr['callback'])
             && !@is_callable($command_arr['callback'])) {
                $this->_echo($this->cli_color('ERROR', 'red').': '
                              .self::_t('Provided command doesn\'t have a callback function.'));
                $this->_output_result_and_exit();
            }

            if (false === @call_user_func($command_arr['callback'])) {
                $this->_echo(self::_t('[COMMAND_RUN] Executing command [%s] returned false value.', $command_arr['command_name']),
                    ['verbose_lvl' => self::VERBOSE_L3]);
            }
        }

        $this->_output_result_and_exit();
    }

    public function get_app_name() : string
    {
        return static::APP_NAME;
    }

    public function get_app_version() : string
    {
        return static::APP_VERSION;
    }

    public function get_app_description() : string
    {
        return static::APP_DESCRIPTION;
    }

    public function get_app_options() : array
    {
        return $this->_options;
    }

    public function get_app_command() : array
    {
        return $this->_command;
    }

    public function get_app_options_definition() : array
    {
        return $this->_options_definition;
    }

    public function get_app_commands_definition() : array
    {
        return $this->_commands_definition;
    }

    public function get_app_verbosity() : int
    {
        return $this->_verbose_level;
    }

    public function get_app_cli_script() : string
    {
        return $this->_cli_script;
    }

    public function get_app_output_colors() : bool
    {
        return $this->_output_colors;
    }

    public function command_option(string $option)
    {
        if (empty($this->_command['options'][$option]['value'])) {
            return null;
        }

        return $this->_command['options'][$option]['value'];
    }

    /**
     * @param array $args
     *
     * @return bool
     */
    public function cli_option_verbosity($args) : bool
    {
        if (empty($args) || !is_array($args)
         || !isset($args['value'])) {
            return false;
        }

        $this->set_verbosity((int)$args['value']);

        return true;
    }

    public function set_verbosity(int $v) : void
    {
        $this->_verbose_level = $v;
    }

    /**
     * @param array $args
     *
     * @return bool
     */
    public function cli_option_output_colors($args) : bool
    {
        if (empty($args) || !is_array($args)
         || !isset($args['value'])) {
            return false;
        }

        $this->set_output_colors(empty($args['value']));

        return true;
    }

    public function set_output_colors(bool $c) : void
    {
        $this->_output_colors = $c;
    }

    /**
     * @param array $args
     *
     * @return bool
     */
    public function cli_option_continous_flush($args) : bool
    {
        if (empty($args) || !is_array($args)
         || !isset($args['value'])) {
            return false;
        }

        $this->set_continous_flush(empty($args['value']));

        return true;
    }

    public function set_continous_flush(bool $f) : void
    {
        $this->_continous_flush = $f;
    }

    /**
     * @return bool
     */
    public function cli_option_help() : bool
    {
        if (!$this->cli_option_version()) {
            return false;
        }

        $this->_echo('');
        $this->_echo('Usage: '.$this->get_app_cli_script().' [options] [command] [arguments]');
        $this->_echo(self::_t('There can be only one command per script run.'));
        $this->_echo('');

        if (($commands_arr = $this->get_app_commands_definition())) {
            $this->_echo(self::_t('Commands').':');
            foreach ($commands_arr as $command_name => $command_arr) {
                $this->_echo($command_name);

                if (!empty($command_arr['description'])) {
                    $this->_echo('  '.$command_arr['description']);
                }
            }

            $this->_echo('');
        }

        if (($options_arr = $this->get_app_options_definition())) {
            $this->_echo(self::_t('Options').':');
            foreach ($options_arr as $option_name => $option_arr) {
                $option_vars = '';
                if (!empty($option_arr['short'])) {
                    $option_vars .= '-'.$option_arr['short'];
                }
                if (!empty($option_arr['long'])) {
                    $option_vars .= ($option_vars !== '' ? ', ' : '').'--'.$option_arr['long'];
                }

                $this->_echo($option_vars);

                if (!empty($option_arr['description'])) {
                    $this->_echo('  '.$option_arr['description']);
                }
            }

            $this->_echo('');
        }

        return true;
    }

    /**
     * @return bool
     */
    public function cli_option_version() : bool
    {
        $this->_echo($this->get_app_name().' - version '.$this->get_app_version());
        $this->_echo($this->get_app_description());

        return true;
    }

    /**
     * @param string $msg
     * @param array $params
     *
     * @return bool
     */
    public function _echo_error(string $msg, array $params = []) : bool
    {
        return $this->_echo($this->cli_color(self::_t('ERROR'), 'red').': '.$msg, $params);
    }

    /**
     * @param string $msg
     * @param array $params
     *
     * @return bool
     */
    public function _echo(string $msg, array $params = []) : bool
    {
        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        if (!isset($params['verbose_lvl'])) {
            if (false === ($params['verbose_lvl'] = $this->_verbosity_block())) {
                $params['verbose_lvl'] = self::VERBOSE_L1;
            }
        } else {
            $params['verbose_lvl'] = (int)$params['verbose_lvl'];
        }

        if (!($now_verbosity = $this->get_app_verbosity())
         || $now_verbosity < $params['verbose_lvl']) {
            return true;
        }

        $params['force_echo'] = (!empty($params['force_echo']));
        $params['flush_output'] = (!empty($params['flush_output']));

        $msg .= "\n";

        $this->_add_buffer_to_result($msg);

        if (!empty($params['flush_output'])
         || $this->_continous_flush()) {
            $this->_flush_output();
        }

        if (!empty($params['force_echo'])) {
            echo $msg;
        }

        return true;
    }

    /**
     * Sets colors for
     *
     * @param string $str
     * @param string $color
     * @param string $background
     *
     * @return string
     */
    public function cli_color(string $str, string $color, string $background = '') : string
    {
        if (!$this->get_app_output_colors()) {
            return $str;
        }

        return self::st_cli_color($str, $color, $background);
    }

    protected function _extract_app_command($argv) : void
    {
        $this->reset_error();

        $old_verbosity = $this->_verbosity_block(self::VERBOSE_L3);

        // Obtain command line options and match them against what application has defined as options
        $this->_command = [];
        $command_name = '';
        $command_arguments_arr = [];
        foreach ($argv as $cli_argument) {
            // check if argument starts with - or -- (is an option)
            if (0 === strpos($cli_argument, '-')
             || 0 === strpos($cli_argument, '--')) {
                continue;
            }

            if ($this->_command
            || ('' !== ($low_cli_argument = strtolower(trim($cli_argument)))
                    && empty($this->_commands_definition[$low_cli_argument]))) {
                $command_arguments_arr[] = $cli_argument;
                continue;
            }

            $this->_command = $this->_commands_definition[$low_cli_argument];
            $command_name = $low_cli_argument;
        }

        $this->_verbosity_block($old_verbosity);

        // If we didn't find any command in command line arguments, don't set here the error
        // as it will be displayed in run() method
        if (!$this->_command) {
            return;
        }

        $this->_command = self::validate_array($this->_command, self::get_app_selected_command_definition());
        $this->_command['command_name'] = $command_name;
        $this->_command['arguments'] = $command_arguments_arr;

        $this->_extract_command_options();
    }

    protected function _extract_app_and_command_options($argv) : array
    {
        $old_verbosity = $this->_verbosity_block(self::VERBOSE_L3);

        // Obtain command line options and match them against what application has defined as options
        $this->_options = [];
        $this->_command_options = [];
        $new_argv = [];

        foreach ($argv as $cli_argument) {
            if (!($parsed_option = $this->_extract_option_from_argument($cli_argument))) {
                $new_argv[] = $cli_argument;

                continue;
            }

            $app_option_name = $parsed_option['option'];
            $index_arr = false;
            if (!empty($parsed_option['is_short'])) {
                if (!empty($this->_options_as_keys['short'][$parsed_option['option']])) {
                    $index_arr = $this->_options_as_keys['short'][$parsed_option['option']];
                }
            } else {
                if (!empty($this->_options_as_keys['long'][$parsed_option['option']])) {
                    $index_arr = $this->_options_as_keys['long'][$parsed_option['option']];
                }
            }

            if (!empty($index_arr)
             && !empty($this->_options_definition[$index_arr['option_name']])) {
                $app_option_name = $index_arr['option_name'];
                $parsed_option['matched_option'] = $this->_options_definition[$index_arr['option_name']];
            } else {
                $this->_command_options[$app_option_name] = $parsed_option;
                continue;
            }

            $this->_options[$app_option_name] = $parsed_option;
        }

        $this->_verbosity_block($old_verbosity);

        return $new_argv;
    }

    protected function _extract_command_options() : void
    {
        if (!$this->_validate_command_options()
         || empty($this->_command_options)) {
            return;
        }

        $old_verbosity = $this->_verbosity_block(self::VERBOSE_L3);

        $this->_command['options'] = [];

        foreach ($this->_command_options as $parsed_option) {
            if (empty($parsed_option['option'])) {
                continue;
            }

            $index_arr = null;
            if (!empty($parsed_option['is_short'])) {
                if (!empty($this->_command['options_as_keys']['short'][$parsed_option['option']])) {
                    $index_arr = $this->_command['options_as_keys']['short'][$parsed_option['option']];
                }
            } else {
                if (!empty($this->_command['options_as_keys']['long'][$parsed_option['option']])) {
                    $index_arr = $this->_command['options_as_keys']['long'][$parsed_option['option']];
                }
            }

            if (empty($index_arr)
             || empty($this->_command['options_definition'][$index_arr['option_name']])) {
                continue;
            }

            $parsed_option['matched_option'] = $this->_command['options_definition'][$index_arr['option_name']];
            $this->_command['options'][$index_arr['option_name']] = $parsed_option;
        }

        $this->_verbosity_block($old_verbosity);
    }

    protected function _output_result_and_exit() : void
    {
        if (!$this->_app_result) {
            $this->_reset_app_result();
        }

        // Exit statuses should be in the range 0 to 254, the exit status 255 is reserved by PHP and shall not be used.
        // The status 0 is used to terminate the program successfully.
        if (254 < ($exit_code = (int)$this->get_error_code())) {
            $exit_code = 254;
        }

        if ($this->_app_result['buffer'] === ''
         || self::VERBOSE_L0 === $this->get_app_verbosity()) {
            exit($exit_code);
        }

        $this->_flush_output();

        exit($exit_code);
    }

    protected function _flush_output() : void
    {
        if (!$this->_app_result) {
            $this->_reset_app_result();
        }

        echo $this->_app_result['buffer'];
        $this->_app_result['buffer'] = '';
    }

    protected function _reset_output() : void
    {
        if (!$this->_app_result) {
            $this->_reset_app_result();
        }

        $this->_app_result['buffer'] = '';
    }

    protected function _add_buffer_to_result($buf) : void
    {
        if (!$this->_app_result) {
            $this->_reset_app_result();
        }

        $this->_app_result['buffer'] .= $buf;
    }

    protected function _had_option_as_command($val = null) : bool
    {
        if (!$this->_app_result) {
            $this->_reset_app_result();
        }

        if ($val === null) {
            return (bool)$this->_app_result['had_option_as_command'];
        }

        $this->_app_result['had_option_as_command'] = (!empty($val));

        return true;
    }

    protected function _reset_app_result() : void
    {
        $this->_app_result = self::get_app_result_definition();
    }

    protected function _process_app_options_on_init() : void
    {
        // Start processing each parameter based on priority
        if (!empty($this->_options)) {
            foreach ($this->_options as $app_option_name => $app_option_details) {
                $this->_echo(self::_t('[INIT_LEVEL] Executing option [%s]', $app_option_name),
                    ['verbose_lvl' => self::VERBOSE_L3]);

                if (empty($app_option_details['matched_option']['callback_init'])
                 || !is_callable($app_option_details['matched_option']['callback_init'])) {
                    $this->_echo(self::_t('[INIT_LEVEL] Nothing to execute for option [%s]', $app_option_name),
                        ['verbose_lvl' => self::VERBOSE_L3]);
                    continue;
                }

                if (!empty($app_option_details['matched_option']['behaves_as_command'])) {
                    $this->_had_option_as_command(true);
                }

                if (false === @call_user_func($app_option_details['matched_option']['callback_init'], $app_option_details)) {
                    $this->_echo(self::_t('[INIT_LEVEL] Executing option [%s] returned false value.', $app_option_name),
                        ['verbose_lvl' => self::VERBOSE_L3]);
                }
            }
        }
    }

    protected function _process_app_options_on_run() : void
    {
        // Start processing options that have callbacks for run() method
        if (empty($this->_options)) {
            return;
        }

        foreach ($this->_options as $app_option_name => $app_option_details) {
            $this->_echo(self::_t('[RUN_LEVEL] Executing option [%s]', $app_option_name),
                ['verbose_lvl' => self::VERBOSE_L3]);

            if (empty($app_option_details['matched_option']['callback_run'])
             || !is_callable($app_option_details['matched_option']['callback_run'])) {
                $this->_echo(self::_t('[RUN_LEVEL] Nothing to execute for option [%s]', $app_option_name),
                    ['verbose_lvl' => self::VERBOSE_L3]);
                continue;
            }

            if (!empty($app_option_details['matched_option']['behaves_as_command'])) {
                $this->_had_option_as_command(true);
            }

            if (false === @call_user_func($app_option_details['matched_option']['callback_run'], $app_option_details)) {
                $this->_echo(self::_t('[RUN_LEVEL] Executing option [%s] returned false value.', $app_option_name),
                    ['verbose_lvl' => self::VERBOSE_L3]);
            }
        }
    }

    protected function _default_app_commands() : array
    {
        return [
            'help' => [
                'description' => 'Gets more help about commands. Try help {other command}.',
                'callback'    => [$this, 'cli_command_help'],
            ],
        ];
    }

    protected function _default_app_options() : array
    {
        return [
            'verbosity' => [
                'short'         => 'vb',
                'long'          => 'verbose',
                'description'   => 'Verbosity level for this application',
                'callback_init' => [$this, 'cli_option_verbosity'],
            ],
            'output_colors' => [
                'short'         => 'bw',
                'long'          => 'black-white',
                'description'   => 'Output will have no colors',
                'callback_init' => [$this, 'cli_option_output_colors'],
            ],
            'continous_flush' => [
                'short'         => 'cf',
                'long'          => 'continous-flush',
                'description'   => 'Output will not get buffered, but displayed directly',
                'callback_init' => [$this, 'cli_option_continous_flush'],
            ],
            'version' => [
                'short'              => 'v',
                'long'               => 'version',
                'behaves_as_command' => true,
                'description'        => 'Version details',
                'callback_run'       => [$this, 'cli_option_version'],
            ],
            'help' => [
                'short'              => 'h',
                'long'               => 'help',
                'behaves_as_command' => true,
                'description'        => 'List help page',
                'callback_run'       => [$this, 'cli_option_help'],
            ],
        ];
    }

    /**
     * When passing another verbosity level, old verbosity level is returned
     * @param bool|int $lvl
     * @return int
     */
    protected function _verbosity_block($lvl = false)
    {
        if ($lvl === false) {
            return $this->_block_verbose;
        }

        $old_verbosity = ($this->_block_verbose === false ? $this->get_app_verbosity() : $this->_block_verbose);
        $this->_block_verbose = (int)$lvl;

        return $old_verbosity;
    }

    /**
     * Get or set conitnous flush status: flush output when calling _echo commands rather than buffering output.
     * When changing continous flush status, method returns old settings.
     *
     * @param null|bool $flush
     *
     * @return bool
     */
    protected function _continous_flush(?bool $flush = null) : bool
    {
        if ($flush === null) {
            return $this->_continous_flush;
        }

        $old_continous_flush = $this->_continous_flush;
        $this->_continous_flush = (!empty($flush));

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
     * Useful when wanting to get command line arguments (array of arguments) one by one in consecutive method calls.
     * eg. $this->_get_argument_chained( [ 'arg1', 'arg2', 'arg3' ] ); $this->_get_argument_chained(); $this->_get_argument_chained()
     * If methods are called consecutively each call will return arg1, arg2 and arg3 respectively
     *
     * @param null|array $args_arr
     *
     * @return string
     */
    protected function _get_argument_chained(?array $args_arr = null) : string
    {
        static $arguments = [];

        if (null !== $args_arr) {
            $arguments = $args_arr;
        }

        if (empty($arguments)
         || !($args_result = $this::_get_one_argument($arguments))) {
            return '';
        }

        $arguments = $args_result['rest'];

        return $args_result['arg'];
    }

    private function _extract_option_from_argument($arg) : ?array
    {
        if (empty($arg) || !is_string($arg)) {
            return null;
        }

        $is_short_arg = false;
        if (strpos($arg, '--') === 0) {
            $arg = substr($arg, 2);
        } elseif (strpos($arg, '-') === 0) {
            $is_short_arg = true;
            $arg = substr($arg, 1);
        } else {
            return null;
        }

        $return_arr = self::get_command_line_option_node_definition();
        $return_arr['is_short'] = $is_short_arg;
        $return_arr['option'] = $arg;
        $return_arr['value'] = true;

        if (($arg_arr = @explode('=', $arg, 2))
         && isset($arg_arr[0]) && $arg_arr[0] !== '') {
            $return_arr['option'] = $arg_arr[0];
            if (isset($arg_arr[1])) {
                $return_arr['value'] = $arg_arr[1];
                if ($return_arr['value'] === 'null') {
                    $return_arr['value'] = null;
                } elseif ($return_arr['value'] === 'false') {
                    $return_arr['value'] = false;
                } elseif ($return_arr['value'] === 'true') {
                    $return_arr['value'] = true;
                } elseif (is_numeric($return_arr['value'])) {
                    if (false === strpos($return_arr['value'], '.')) {
                        $return_arr['value'] = (int)$return_arr['value'];
                    } else {
                        $return_arr['value'] = (float)$return_arr['value'];
                    }
                }
            }
        }

        $return_arr['option'] = strtolower($return_arr['option']);

        return $return_arr;
    }

    /**
     * @param array $app_options
     *
     * @return array|false
     */
    private function _validate_app_options(array $app_options)
    {
        $this->reset_error();

        if (empty($app_options)) {
            $app_options = [];
        }

        $app_options = self::validate_array($app_options, $this->_default_app_options());

        $node_definition = self::get_option_node_definition();

        $option_count = 0;
        $return_arr = [];
        foreach ($app_options as $option_name => $option_arr) {
            $option_count++;

            if (!is_string($option_name)
             || $option_name === '') {
                $this->set_error(self::ERR_PARAMETERS, self::_t('Option %s should have a string in key array definition.', $option_count));

                return false;
            }

            if (empty($option_arr) || !is_array($option_arr)) {
                $this->set_error(self::ERR_PARAMETERS, self::_t('Option %s should have an array as definition.', $option_name));

                return false;
            }

            $option_arr = self::validate_array($option_arr, $node_definition);

            // All CLI parameters are case-insensitive (convert all to lowercase)
            $return_arr[strtolower($option_name)] = $option_arr;
        }

        $this->_options_definition = $return_arr;

        // Make indexes after we sort by priority, so they get overwritten (eventually) depending on priority
        $this->_options_as_keys = [];
        foreach ($return_arr as $option_name => $option_arr) {
            if (!empty($option_arr['short'])) {
                $this->_options_as_keys['short'][strtolower($option_arr['short'])] = ['option_name' => $option_name, ];
            }

            if (!empty($option_arr['long'])) {
                $this->_options_as_keys['long'][strtolower($option_arr['long'])] = ['option_name' => $option_name, ];
            }
        }

        return $this->_options_definition;
    }

    private function _validate_command_options() : bool
    {
        if (empty($this->_command)
            || empty($this->_command['options_definition'])
            || !is_array($this->_command['options_definition'])) {
            return true;
        }

        $node_definition = self::get_option_node_definition();

        $option_count = 0;
        $return_arr = [];
        foreach ($this->_command['options_definition'] as $option_name => $option_arr) {
            $option_count++;

            if (!is_string($option_name)
             || $option_name === '') {
                $this->set_error(self::ERR_PARAMETERS,
                    self::_t('Command %s, option %s should have a string in key array definition.',
                        $this->_command['command_name'] ?? 'N/A', $option_count));

                return false;
            }

            if (empty($option_arr) || !is_array($option_arr)) {
                $this->set_error(self::ERR_PARAMETERS,
                    self::_t('Command %s, option %s should have an array as definition.',
                        $this->_command['command_name'] ?? 'N/A', $option_name));

                return false;
            }

            $option_arr = self::validate_array($option_arr, $node_definition);

            // All CLI parameters are case-insensitive (convert all to lowercase)
            $return_arr[strtolower($option_name)] = $option_arr;
        }

        $this->_command['options_definition'] = $return_arr;
        $this->_command['options_as_keys'] = [];

        foreach ($return_arr as $option_name => $option_arr) {
            if (!empty($option_arr['short'])) {
                $this->_command['options_as_keys']['short'][strtolower($option_arr['short'])] = ['option_name' => $option_name, ];
            }

            if (!empty($option_arr['long'])) {
                $this->_command['options_as_keys']['long'][strtolower($option_arr['long'])] = ['option_name' => $option_name, ];
            }
        }

        return true;
    }

    /**
     * @param array $app_commands
     *
     * @return null|array
     */
    private function _validate_app_commands(array $app_commands) : ?array
    {
        $this->reset_error();

        if (empty($app_commands)) {
            $app_commands = [];
        }

        $app_commands = self::validate_array($app_commands, $this->_default_app_commands());

        $node_definition = self::get_app_command_node_definition();

        $command_count = 0;
        $return_arr = [];
        foreach ($app_commands as $command_name => $command_arr) {
            $command_count++;

            if (!is_string($command_name)
             || $command_name === '') {
                $this->set_error(self::ERR_PARAMETERS, self::_t('Command %s should have a string in key array definition.', $command_count));

                return null;
            }

            if (empty($command_arr) || !is_array($command_arr)) {
                $this->set_error(self::ERR_PARAMETERS, self::_t('Command %s should have an array as definition.', $command_name));

                return null;
            }

            $command_arr = self::validate_array($command_arr, $node_definition);

            // All CLI parameters are case-insensitive (convert all to lowercase)
            $return_arr[strtolower($command_name)] = $command_arr;
        }

        $this->_commands_definition = $return_arr;

        return $this->_commands_definition;
    }

    public static function get_app_result_definition() : array
    {
        return [
            // Buffer that will be displayed as result
            'buffer' => '',
            // Tells if we run an option which behaves as command
            'had_option_as_command' => false,
        ];
    }

    public static function get_app_command_node_definition() : array
    {
        return [
            // Description used when building --help option
            'description' => 'No description.',
            // When this option is present we will call this callback (if present)
            'callback' => false,
            // An array of command option definition (if any) @see self::get_option_node_definition()
            'options_definition' => null,
            // An array of command option as keys to easy find options passed in CLI as parameters
            'options_as_keys' => null,
        ];
    }

    public static function get_app_selected_command_definition() : array
    {
        return self::validate_array([
            // Keep command name in selected command array
            'command_name' => '',
            // If arguments from command line doesn't match an option or a command they will be added to command arguments
            'arguments' => [],
            // If there are any command specific options, they will be added to this array
            'options' => [],
        ], self::get_app_command_node_definition());
    }

    public static function get_option_node_definition() : array
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

    public static function get_command_line_option_node_definition() : array
    {
        return [
            // Tells if parameter passed to application was short version or not (eg. -u=5 or --user=5)
            'is_short' => true,
            // What parameter was extracted from command line (eg. u or user)
            'option' => '',
            // Value extracted after = sign from command line argument (eg. 5)
            'value' => null,
            // Option that matched this argument (this is app parameter node definition, @see self::get_option_node_definition())
            // if this is null, means no parameter matched this and maybe this argument is used by other app parameter
            'matched_option' => null,
        ];
    }

    /**
     * @return bool
     */
    public static function running_in_cli() : bool
    {
        return PHP_SAPI === 'cli';
    }

    /**
     * Sets colors for
     *
     * @param string $str
     * @param string $color
     * @param string $background
     *
     * @return string
     */
    public static function st_cli_color(string $str, string $color, string $background = '') : string
    {
        $colors_arr = self::get_cli_colors_definition();

        if (empty($colors_arr['color'][$color])
         && empty($colors_arr['background'][$background])) {
            return $str;
        }

        $color = $colors_arr['color'][$color] ?? '';
        $background = $colors_arr['background'][$background] ?? '';

        $colored_str = '';
        if ($color !== '') {
            $colored_str .= "\033[".$color.'m';
        }
        if ($background !== '') {
            $colored_str .= "\033[".$background.'m';
        }

        $colored_str .= $str."\033[0m";

        return $colored_str;
    }

    public static function get_cli_colors_definition() : array
    {
        return [
            'color' => [
                'black'        => '0;30',
                'dark_gray'    => '1;30',
                'blue'         => '0;34',
                'light_blue'   => '1;34',
                'light_green'  => '1;32',
                'green'        => '0;32',
                'cyan'         => '0;36',
                'light_cyan'   => '1;36',
                'red'          => '0;31',
                'light_red'    => '1;31',
                'purple'       => '0;35',
                'light_purple' => '1;35',
                'brown'        => '0;33',
                'yellow'       => '1;33',
                'light_gray'   => '0;37',
                'white'        => '1;37',
            ],
            'background' => [
                'black'      => '40',
                'red'        => '41',
                'green'      => '42',
                'yellow'     => '43',
                'blue'       => '44',
                'magenta'    => '45',
                'cyan'       => '46',
                'light_gray' => '47',
            ],
        ];
    }

    /**
     * @param bool|string $app_class_name
     *
     * @return bool|PHS_Cli
     */
    public static function get_instance($app_class_name = false)
    {
        // Late Static Bindings (static::) added in 5.3
        // ::class added in 5.5 => we will add PHP 5.5 dependency if we use static::class,
        // so we use get_called_class() added in PHP 5.3
        // Update: PHP min version now is 5.6+
        if ($app_class_name === false
         && !($app_class_name = static::class)) {
            self::st_copy_error(self::_t('Cannot obtain called class name.'));

            return false;
        }

        if (!empty(self::$_my_instances[$app_class_name])) {
            return self::$_my_instances[$app_class_name];
        }

        /** @var PHS_Cli $app_instance */
        $app_instance = new $app_class_name();
        if ($app_instance->has_error()) {
            self::st_copy_error($app_instance);

            return false;
        }

        self::$_my_instances[$app_class_name] = $app_instance;

        return self::$_my_instances[$app_class_name];
    }

    /**
     * Extract first argument available in an array of arguments and return an array
     * with arg key first available string untill first space and rest key with rest of arguments
     *
     * @param array $args_arr
     *
     * @return bool|array
     */
    protected static function _get_one_argument(array $args_arr)
    {
        if (empty($args_arr)
         || null === ($first_arg = @array_shift($args_arr))
         || !is_string($first_arg)) {
            return false;
        }

        if ('' === ($first_arg = trim($first_arg))) {
            return static::_get_one_argument($args_arr);
        }

        return [
            'arg'  => $first_arg,
            'rest' => $args_arr,
        ];
    }
}
