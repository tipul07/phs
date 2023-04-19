<?php
namespace phs\libraries;

interface PHS_Event_interface
{
    /**
     * Listen for event triggers
     *
     * @param callable|array $callback
     * @param string $event_prefix
     * @param array $options
     *
     * @return null|static
     */
    public static function listen($callback, string $event_prefix = '', array $options = []) : ?self;

    /**
     * Listen for event triggers. The listener will be launched in a background job.
     * Listener should be a method in a PHS_Instantiable class
     *
     * @param callable|array $callback
     * @param string $event_prefix
     * @param array $options
     *
     * @return null|static
     */
    public static function listen_in_background($callback, string $event_prefix = '', array $options = []) : ?self;

    /**
     * This is the function which should be used when triggering any event
     *
     * @param array $input
     * @param string $event_prefix
     * @param array $params
     *
     * @return null|static
     */
    public static function trigger(array $input = [], string $event_prefix = '', array $params = []) : ?self;
}
