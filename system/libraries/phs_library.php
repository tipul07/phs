<?php

namespace phs\libraries;

//! All plugin libraries should extend this class
abstract class PHS_Library extends PHS_Language
{
    /** @var PHS_Plugin|bool $_parent_plugin */
    private $_parent_plugin = false;

    final public function parent_plugin( $plugin_obj = false )
    {
        if( $plugin_obj === false )
            return $this->_parent_plugin;

        if( !($plugin_obj instanceof PHS_Plugin) )
            return false;

        $this->_parent_plugin = $plugin_obj;

        return $this->_parent_plugin;
    }

    /**
     * Gets plugin instance where current instance is running
     *
     * @return bool|false|PHS_Plugin
     */
    final public function get_plugin_instance()
    {
        if( empty( $this->_parent_plugin ) )
            return false;

        return $this->_parent_plugin;
    }

    /**
     * @return array Array with settings of plugin of current model
     */
    public function get_plugin_settings()
    {
        if( !($plugin_obj = $this->get_plugin_instance()) )
            return array();

        if( ($plugins_settings = $plugin_obj->get_db_settings()) === false
         or empty( $plugins_settings ) or !is_array( $plugins_settings ) )
            $plugins_settings = $plugin_obj->get_default_settings();

        return $plugins_settings;
    }

}
