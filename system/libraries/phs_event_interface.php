<?php
namespace phs\libraries;

interface PHS_Event_interface
{
    /**
     * Listen for event triggers
     *
     * @param callable|array $callback
     * @param array $options
     *
     * @return null|static
     */
    public static function listen($callback, array $options = []) : ?self;

    /**
     * Listen for event triggers. The listener will be launched in a background job.
     * Listener should be a method in a PHS_Instantiable class
     *
     * @param callable|array $callback
     * @param array $options
     *
     * @return null|static
     */
    public static function listen_in_background($callback, array $options = []) : ?self;

    /**
     * Trigger the event.
     *
     * @param array $input
     * @param array $params
     * @param string $event_prefix
     *
     * @return null|static
     */
    public static function trigger(array $input = [], array $params = [], string $event_prefix = '') : ?self;
}
