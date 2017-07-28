<?php

namespace phs\libraries;

use \phs\PHS;

abstract class PHS_Has_db_registry extends PHS_Has_db_settings
{
    // Database record
    protected $_db_registry_details = false;
    // Database registry field parsed as array
    protected $_db_registry = false;

    /**
     * @param bool $force Forces reading details from database (ignoring cached value)
     *
     * @return array|bool
     */
    public function get_db_registry_details( $force = false )
    {
        if( empty( $force )
        and !empty( $this->_db_registry_details ) )
            return $this->_db_registry_details;

        if( !$this->_load_plugins_instance()
         or !($db_details = $this->_plugins_instance->get_db_registry( $this->instance_id(), $force ))
         or !is_array( $db_details ) )
            return false;

        $this->_db_registry_details = $db_details;

        return $this->_db_registry_details;
    }

    /**
     * @param bool $force Forces reading details from database (ignoring cached value)
     *
     * @return array|bool Settings saved in database for current instance
     */
    public function get_db_registry( $force = false )
    {
        if( empty( $force )
        and $this->_db_registry !== false )
            return $this->_db_registry;

        if( !$this->_load_plugins_instance() )
            return false;

        if( !($db_registry = $this->_plugins_instance->get_plugins_db_registry( $this->instance_id(), $force ))
         or !is_array( $db_registry ) )
            $db_registry = array();

        $this->_db_registry = $db_registry;

        return $this->_db_registry;
    }

    public function save_db_registry( $registry_arr )
    {
        if( !$this->_load_plugins_instance() )
            return false;

        if( !($db_registry = $this->_plugins_instance->save_plugins_db_registry( $registry_arr, $this->instance_id() ))
         or !is_array( $db_registry ) )
            $db_registry = array();

        $this->_db_registry = $db_registry;

        // invalidate cached data...
        $this->_db_registry_details = false;

        return $this->_db_registry;
    }

    public function update_db_registry( $registry_part_arr )
    {
        if( empty( $registry_part_arr ) or !is_array( $registry_part_arr ) )
            return false;

        return $this->save_db_registry( self::merge_array_assoc( $this->get_db_registry(), $registry_part_arr ) );

    }

    public function clean_db_registry()
    {
        return $this->save_db_registry( array() );
    }

    public function delete_db_registry()
    {
        $this->reset_error();

        if( !$this->_load_plugins_instance() )
            return false;

        if( !$this->_plugins_instance->delete_db_registry( $this->instance_id() ) )
        {
            if( $this->_plugins_instance->has_error() )
                $this->copy_error( $this->_plugins_instance );
            else
                $this->set_error( self::ERR_PLUGINS_MODEL, self::_t( 'Couldn\'t delete registry database record.' ) );

            return false;
        }

        return true;
    }

}
