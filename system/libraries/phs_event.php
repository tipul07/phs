<?php

namespace phs\libraries;

use Closure;
use phs\PHS;
use phs\PHS_Bg_jobs;

abstract class PHS_Event extends PHS_Instantiable implements PHS_Event_interface
{
    public const ERR_LISTEN = 30000, ERR_TRIGGER = 30001, ERR_NOT_UNIQUE = 30002;

    /**
     * Keeps record of all event listners ids (for unicity)
     * @var array
     */
    private array $event_listeners_ids = [];

    /**
     * Keeps record of background listners
     * @var array
     */
    private array $background_listeners = [];

    /**
     * Actual input received from triggering the event
     * @var array
     */
    private array $input = [];

    /**
     * Actual output set by listeners of the event
     * @var array
     */
    private array $output = [];

    /**
     * Actual "listeners" of all events... What callback responds as listener.
     * array[callback_id][event_prefix][priority][]
     * @var array
     */
    private static array $callbacks = [];

    /**
     * Array with key, value pairs. Key represents an input parameter
     * value is default value if parameter is not provided in event data
     * @return array
     */
    abstract protected function _input_parameters() : array;

    /**
     * Array with key, value pairs. Key represents output parameter
     * value is default value if parameter is not found in event results
     * @return array
     */
    abstract protected function _output_parameters() : array;

    public function instance_type() : string
    {
        return self::INSTANCE_TYPE_EVENT;
    }

    public function has_listeners() : bool
    {
        return !empty($this->event_listeners_ids);
    }

    public function has_background_listeners() : bool
    {
        return !empty($this->background_listeners);
    }

    /**
     * !!! DO NOT CALL THIS METHOD DIRECTLY
     * Add a callback for this event when it is triggered from the static ::listen() method or from the background job
     *
     * @param null|callable|array $callback
     * @param string $event_prefix
     * @param array $options
     *
     * @return bool
     */
    public function add_listener($callback, string $event_prefix = '', array $options = []) : bool
    {
        $this->reset_error();

        $options['unique'] = (!empty($options['unique']));
        $options['in_background'] = (!empty($options['in_background']));

        if (!isset($options['priority'])) {
            $options['priority'] = 10;
        } else {
            $options['priority'] = (int)$options['priority'];
        }

        if (!empty($options['in_background'])
            && !$this->supports_background_listeners()) {
            $this->set_error(self::ERR_LISTEN,
                self::_t('Event doesn\'t support background listeners.'));

            return false;
        }

        if (empty($callback)
         || !($callback_details = $this->_get_callback_details($callback))) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_LISTEN,
                    self::_t('Invalid callback provided as listener.'));
            }

            return false;
        }

        if ($options['unique']
         && in_array($callback_details['callback_id'], $this->event_listeners_ids, true)) {
            $this->set_error(self::ERR_NOT_UNIQUE,
                self::_t('Callback already provided as listener for this event.'));

            return false;
        }

        $this->event_listeners_ids[] = $callback_details['callback_id'];

        $event_prefix = self::_prepare_event_prefix($event_prefix);
        $callback_index = $this->_get_callback_index();

        $callback_data = [];
        $callback_data['callback'] = $callback_details['callback'];
        $callback_data['options'] = $options;
        $callback_data['event_prefix'] = $event_prefix;

        if ($options['in_background']) {
            $this->background_listeners[] = $callback_data;
        }

        self::$callbacks[$callback_index][$event_prefix][$options['priority']][] = $callback_data;

        ksort(self::$callbacks[$callback_index][$event_prefix], SORT_NUMERIC);

        return true;
    }

    /**
     * !!! DO NOT CALL THIS METHOD DIRECTLY
     * This trigger is ment to be called only from background job...
     * @param array $input
     * @param string $event_prefix
     * @param array $params
     *
     * @return null|array
     */
    public function do_trigger_from_background(array $input = [], string $event_prefix = '', array $params = []) : ?array
    {
        if (!PHS::are_we_in_a_background_thread()) {
            return $this->_validate_event_output();
        }

        $this->_set_input($input);

        if (($new_input = $this->_unserialize_input_for_background())) {
            $this->_set_input($new_input);
            $input = $new_input;
        }

        return $this->do_trigger($input, $event_prefix, $params);
    }

    public function do_trigger(array $input = [], string $event_prefix = '', array $params = []) : ?array
    {
        $this->reset_error();
        $this->_reset_output();

        $are_we_in_background = PHS::are_we_in_a_background_thread();

        $params['stop_on_first_error'] = (!empty($params['stop_on_first_error']));
        $params['force_sync_trigger'] = (!empty($params['force_sync_trigger']));
        $params['only_background_listeners'] = (!empty($params['only_background_listeners']));
        $params['include_listeners_without_prefix'] = (!isset($params['include_listeners_without_prefix'])
                                                       || !empty($params['include_listeners_without_prefix']));

        // As we trigger the event, try triggering old hooks
        if (empty($params['old_hooks']) || !is_array($params['old_hooks'])) {
            $params['old_hooks'] = [];
        }

        if (($old_hook = $this->_auto_trigger_hook_name())) {
            $params['old_hooks'][] = $old_hook;
        }

        if (!($callbacks = $this->get_callbacks($event_prefix, $params['include_listeners_without_prefix']))) {
            $callbacks = [];
            if (empty($params['old_hooks'])) {
                return $this->_validate_event_output();
            }
        }

        if (!($input = $this->_validate_event_input($input))) {
            $input = [];
        }

        $this->_set_input($input);

        if (!$this->_pre_trigger_condition()) {
            return $this->_validate_event_output();
        }

        $output_validated = false;
        foreach ($callbacks as $priority_callbacks) {
            if (empty($priority_callbacks) || !is_array($priority_callbacks)) {
                continue;
            }

            foreach ($priority_callbacks as $callback) {
                if (empty($callback) || !is_array($callback)
                 || empty($callback['callback'])
                 || (empty($callback['options']['in_background'])
                     && $params['only_background_listeners'])
                 || (!empty($callback['options']['in_background'])
                     && !$are_we_in_background
                     && !$params['only_background_listeners']
                     && !$params['force_sync_trigger'])
                 || !($callback_instance = $this->_instantiate_callback($callback['callback']))
                ) {
                    continue;
                }

                if ($callback_instance($this) === null
                 && $params['stop_on_first_error']) {
                    return $this->_validate_event_output($this->output);
                }

                // Revalidate output after each callback...
                $this->output = $this->_validate_event_output($this->output);
                $output_validated = true;
            }
        }

        if (!$are_we_in_background && !$params['force_sync_trigger']
         && $this->has_background_listeners()) {
            $job_params = [];
            $job_params['event'] = static::class;
            $job_params['event_prefix'] = $event_prefix;
            $job_params['params'] = $params;
            $job_params['input'] = $this->_serialize_input_for_background();
            // Listeners might not be set in background job... we force them in this thread for background job
            $job_params['listeners'] = $this->background_listeners;

            if (!PHS_Bg_jobs::run(['a' => 'trigger_event_bg', 'c' => 'index_bg'], $job_params)) {
                if (self::st_has_error()) {
                    $error_msg = self::st_get_error_message();
                } else {
                    $error_msg = $this->_pt('Error launching background listners.');
                }

                PHS_Logger::error('Error launching background job for event '.static::class.' ['.($event_prefix ?? 'N/A').']: '.$error_msg,
                    PHS_Logger::TYPE_DEBUG);
                $this->set_error(self::ERR_FUNCTIONALITY, $error_msg);
            }
        }

        if (!empty($params['old_hooks'])) {
            $this->_trigger_old_hooks($params['old_hooks']);
        }

        if (!$output_validated) {
            // Make sure we have a validated output (all listeners might be in background)
            $this->output = $this->_validate_event_output($this->output);
        }

        return $this->output;
    }

    /**
     * @param string $event_prefix
     * @param bool $listeners_without_prefix
     *
     * @return null|array
     */
    public function get_callbacks(string $event_prefix = '', bool $listeners_without_prefix = false) : ?array
    {
        if (!($callback_index = $this->_get_callback_index())
         || empty(self::$callbacks[$callback_index])) {
            return null;
        }

        $callbacks_arr = [];
        if (!empty(self::$callbacks[$callback_index][$event_prefix])) {
            $callbacks_arr = self::$callbacks[$callback_index][$event_prefix];
        }

        if ($listeners_without_prefix
         && $event_prefix !== ''
         && !empty(self::$callbacks[$callback_index][''])) {
            foreach (self::$callbacks[$callback_index][''] as $priority => $priority_callbacks) {
                if (empty($callbacks_arr[$priority])) {
                    $callbacks_arr[$priority] = [];
                }

                $callbacks_arr[$priority] = array_merge($callbacks_arr[$priority], $priority_callbacks);
            }

            ksort($callbacks_arr, SORT_NUMERIC);
        }

        return $callbacks_arr;
    }

    /**
     * Tells if provided callback is registered as listener of this event
     *
     * @param callable|array $callback
     *
     * @return bool
     */
    public function is_callback_of_event($callback) : bool
    {
        return !empty($callback)
            && ($callback_details = $this->_get_callback_details($callback))
            && !empty($callback_details['callback_id'])
            && in_array($callback_details['callback_id'], $this->event_listeners_ids, true);
    }

    /**
     * @param null|string $key
     *
     * @return null|mixed
     */
    public function get_input(?string $key = null)
    {
        if ($key === null) {
            return $this->input;
        }

        return $this->input[$key] ?? null;
    }

    /**
     * @param null|string $key
     *
     * @return null|mixed
     */
    public function get_output(?string $key = null)
    {
        if ($key === null) {
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
    public function set_output($key, $val = null) : bool
    {
        if ($val === null) {
            if (!is_array($key)) {
                return false;
            }

            if (empty($this->output)) {
                $this->output = $this->_output_parameters();
            }

            foreach ($key as $kkey => $kval) {
                if (!is_scalar($kkey)) {
                    continue;
                }

                $this->output[$kkey] = $kval;
            }

            return true;
        }

        if (!is_scalar($key)) {
            return false;
        }

        if (empty($this->output)) {
            $this->output = $this->_output_parameters();
        }

        $this->output[$key] = $val;

        return true;
    }

    /**
     * Override this method if event should not accept background listeners
     * @return bool
     */
    public function supports_background_listeners() : bool
    {
        return true;
    }

    /**
     * Override this method if you want the event have an internal logic before trigger.
     * If this logic returns false the triggering will stop before doing anything.
     * Useful for events with background listeners as the trigger will be stopped before launching the background job.
     * @return bool
     */
    protected function _pre_trigger_condition() : bool
    {
        return true;
    }

    /**
     * Override this method if event should by default trigger an old hook
     * Method should return old hook name
     * @return ?string
     */
    protected function _auto_trigger_hook_name() : ?string
    {
        return null;
    }

    /**
     * Override this method if event should by default trigger an old hook
     * and hook arguments should be prepared from event input + output using array map returned by this method
     * Should return an array with keys an index in input + output array and value is an index in hook arguments
     * e.g. [ 'inout_key' => 'hookparam' ] => $hook_args['hookparam'] = $input['inout_key'] ?? $output['inout_key'] ?? null;
     * @return null|array
     */
    protected function _auto_trigger_hook_args_map() : ?array
    {
        return null;
    }

    /**
     * Override this method if you need special input serialization!
     * For inputs which contain objects or record-arrays,
     * serialize the data, so it can be passed as string in background job
     * @return array
     */
    protected function _serialize_input_for_background() : array
    {
        return $this->get_input();
    }

    /**
     * Override this method if you need special input unserialization!
     * Once we are in background job, before triggering event, unserialize the input data
     * sent to background job
     * @return array
     */
    protected function _unserialize_input_for_background() : array
    {
        return $this->get_input();
    }

    /**
     * @param string|array $key Key for which we want to change value or full array with key/value pairs
     * @param null|mixed $val Value of provided key or null in case we receive a full array of key/value pairs
     *
     * @return bool
     */
    protected function _set_input($key, $val = null) : bool
    {
        if ($val === null) {
            if (!is_array($key)) {
                return false;
            }

            foreach ($key as $kkey => $kval) {
                if (!is_scalar($kkey)) {
                    continue;
                }

                $this->input[$kkey] = $kval;
            }

            return true;
        }

        if (!is_scalar($key)) {
            return false;
        }

        $this->input[$key] = $val;

        return true;
    }

    private function _trigger_old_hooks(array $old_hooks) : void
    {
        $hook_args = $this->_generate_hook_args_for_hook();
        $output_arr = $this->_validate_event_output($this->get_output());
        $trigger_result = [];
        foreach ($old_hooks as $hook) {
            if (!($hook_result = PHS::trigger_hooks($hook, $hook_args))
             || !is_array($hook_result)) {
                continue;
            }

            foreach ($hook_result as $key => $val) {
                if (!array_key_exists($key, $output_arr)) {
                    continue;
                }

                $trigger_result[$key] = $val;
            }

            $hook_args = $hook_result;
        }

        $this->set_output($trigger_result);
    }

    private function _generate_hook_args_for_hook() : array
    {
        if (!($io_args = $this->get_input())) {
            $io_args = [];
        }

        if (($output_arr = $this->get_output())) {
            $default_output_arr = $this->_output_parameters();
            foreach ($output_arr as $o_key => $o_val) {
                if (!array_key_exists($o_key, $default_output_arr)
                    || $o_val === $default_output_arr[$o_key]) {
                    continue;
                }

                $io_args[$o_key] = $o_val;
            }
        }

        if (!($hook_args_map = $this->_auto_trigger_hook_args_map())) {
            return $io_args;
        }

        $hook_args = [];
        foreach ($hook_args_map as $io_key => $args_key) {
            $hook_args[$args_key] = $io_args[$io_key] ?? null;
        }

        return $hook_args;
    }

    /**
     * @param null|array|callable $callback
     *
     * @return null|array
     */
    private function _get_callback_details($callback) : ?array
    {
        if (!($callback = $this->_validate_listener_callback($callback))) {
            return null;
        }

        $return_arr = [];
        $return_arr['callback'] = $callback;

        if (!($return_arr['callback_id'] = $this->_get_callback_id($callback))) {
            return null;
        }

        return $return_arr;
    }

    private function _get_callback_id($callback) : string
    {
        if ($callback instanceof Closure) {
            return Closure::class.'::__invoke('.microtime().')';
        }

        if (is_string($callback)) {
            return $callback.'()';
        }

        if (is_array($callback)
            && !empty($callback[0]) && !empty($callback[1])
            && is_string($callback[0]) && is_string($callback[1])) {
            // Call id is only used to unique identify this callable, not for triggering the callable...
            return '\\'.ltrim($callback[0], '\\').'::'.$callback[1].'()';
        }

        return '';
    }

    /**
     * Make sure callable for background events are arrays with class name (strings), not an instance,
     * so we can send it as parameter to background job
     * @param callable|array $callback
     *
     * @return null|array|callable
     */
    private function _validate_listener_callback($callback)
    {
        if (!@is_callable($callback)
         && (!is_array($callback)
             || empty($callback[0]) || empty($callback[1])
             || !is_string($callback[0]) || !is_string($callback[1])
         )) {
            return null;
        }

        if (is_string($callback)
            || $callback instanceof Closure ) {
            return $callback;
        }

        if (is_string($callback[0])) {
            if (!($listener_obj = $callback[0]::get_instance())
             || !($listener_obj instanceof PHS_Instantiable)
             || !@method_exists($listener_obj, $callback[1])) {
                $this->set_error(self::ERR_LISTEN,
                    self::_t('Listeners should be a function or a method of instances of PHS_Instatiable.'));

                return null;
            }

            if (!($plugin_obj = $listener_obj->get_plugin_instance())
                || !$plugin_obj->plugin_active()) {
                return null;
            }

            return $callback;
        }

        if (is_object($callback[0])) {
            $listener_obj = $callback[0];
            if (!($listener_obj instanceof PHS_Instantiable)
             || !@method_exists($listener_obj, $callback[1])) {
                $this->set_error(self::ERR_LISTEN,
                    self::_t('Listeners should be a function or a method of instances of PHS_Instatiable.'));

                return null;
            }

            if (!($plugin_obj = $listener_obj->get_plugin_instance())
                || !$plugin_obj->plugin_active()) {
                return null;
            }

            $callback[0] = @get_class($listener_obj);

            return $callback;
        }

        return null;
    }

    /**
     * Make sure callable for background events are arrays with class name (strings), not an instance,
     * so we can send it as parameter to background job
     * @param null|callable|array $callback
     *
     * @return null|array|callable
     */
    private function _instantiate_callback($callback)
    {
        if (!($callback = $this->_validate_listener_callback($callback))) {
            return null;
        }

        if (is_string($callback)
            || $callback instanceof Closure) {
            return $callback;
        }

        /** @var PHS_Instantiable $listener_obj */
        if (!is_string($callback[0])
         || !($listener_obj = $callback[0]::get_instance())
         || !($listener_obj instanceof PHS_Instantiable)
         || !@method_exists($listener_obj, $callback[1])) {
            $this->set_error(self::ERR_TRIGGER,
                self::_t('Listeners should be a function or a method of instances of PHS_Instatiable.'));

            return null;
        }

        if (!($plugin_obj = $listener_obj->get_plugin_instance())
            || !$plugin_obj->plugin_active()) {
            return null;
        }

        return [$listener_obj, $callback[1]];
    }
    //
    // endregion Listen methods
    //

    //
    // region Utilities
    //
    private function _validate_event_input(array $input = []) : array
    {
        if (empty($input)) {
            return $this->_input_parameters();
        }

        return self::validate_array($input, $this->_input_parameters());
    }

    private function _validate_event_output(array $output = []) : array
    {
        if (empty($output)) {
            return $this->_output_parameters();
        }

        return self::validate_array($output, $this->_output_parameters());
    }

    private function _reset_output() : void
    {
        $this->output = $this->_output_parameters();
    }

    private function _get_callback_index() : string
    {
        return static::class;
    }

    /**
     * @inheritdoc
     */
    public static function listen($callback, string $event_prefix = '', array $options = []) : ?self
    {
        $options['in_background'] = false;

        return static::_do_listen($callback, $event_prefix, $options);
    }

    /**
     * @inheritdoc
     */
    public static function listen_in_background($callback, string $event_prefix = '', array $options = []) : ?self
    {
        $options['in_background'] = true;

        return static::_do_listen($callback, $event_prefix, $options);
    }

    /**
     * @inheritdoc
     */
    public static function trigger(array $input = [], string $event_prefix = '', array $params = []) : ?self
    {
        self::st_reset_error();

        /** @var static $event_obj */
        if (!($event_obj = static::get_instance(true, static::class))) {
            self::st_set_error(self::ERR_TRIGGER, self::_t('Error instantiating event.'));

            return null;
        }

        if (null === $event_obj->do_trigger($input, $event_prefix, $params)) {
            self::st_set_error(self::ERR_TRIGGER, self::_t('Error triggering the event.'));

            return null;
        }

        return $event_obj;
    }

    //
    // region Listen methods
    //
    /**
     * @param array|callable $callback
     * @param string $event_prefix
     * @param array $options
     *
     * @return null|static::class
     */
    private static function _do_listen($callback, string $event_prefix = '', array $options = []) : ?self
    {
        self::st_reset_error();

        /** @var self $event_obj */
        if (!($event_obj = static::get_instance(true, static::class))) {
            self::st_set_error(self::ERR_LISTEN, self::_t('Error instantiating event.'));

            return null;
        }

        if (!$event_obj->add_listener($callback, $event_prefix, $options)) {
            $error_msg = '';
            if ($event_obj->has_error()) {
                $error_msg = $event_obj->get_simple_error_message();
            }

            self::st_set_error(self::ERR_LISTEN, self::_t('Error adding listener to the event.')
                                                  .($error_msg !== '' ? ' '.$error_msg : ''));

            return null;
        }

        return $event_obj;
    }

    /**
     * @param string $prefix
     *
     * @return string
     */
    private static function _prepare_event_prefix(string $prefix) : string
    {
        if (!($prefix = strtolower(trim($prefix)))) {
            return '';
        }

        return $prefix;
    }
}
