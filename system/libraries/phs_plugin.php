<?php
namespace phs\libraries;

use phs\PHS;

abstract class PHS_Plugin extends PHS_Signal_and_slot
{
    const ERR_MODEL = 30000, ERR_INSTANCE = 30001;

    // Cached plugin settings
    private static $plugin_settings = array();

    function __construct( $instance_details )
    {
        parent::__construct( $instance_details );

        $this->_reset_plugin_settings_cache();
    }

    private function _reset_plugin_settings_cache()
    {
        self::$plugin_settings = array();
    }

    protected function instance_type()
    {
        return self::INSTANCE_TYPE_PLUGIN;
    }

    public function get_db_details( $force = false )
    {
        $this->reset_error();

        if( !($instance_id = $this->instance_id()) )
        {
            $this->set_error( self::ERR_INSTANCE, self::_t( 'Unknown instance ID.' ) );
            return false;
        }

        if( !empty( $force )
        and !empty( self::$plugin_settings[$instance_id] ) )
            unset( self::$plugin_settings[$instance_id] );

        if( !empty( self::$plugin_settings[$instance_id] ) )
            return self::$plugin_settings[$instance_id];

        if( !($plugins_model = PHS::load_model( 'plugins' )) )
        {
            if( self::st_has_error() )
                $this->copy_static_error();
            else
                $this->set_error( self::ERR_MODEL, self::_t( 'Couldn\'t initiate plugins model.' ) );
            return false;
        }

        $check_arr = array();
        $check_arr['instance_id'] = $instance_id;

        if( !($db_details = $plugins_model->get_details_fields( $check_arr )) )
        {
            if( $plugins_model->has_error() )
                $this->copy_error( $plugins_model );
            else
                $this->set_error( self::ERR_MODEL, self::_t( 'Couldn\'t find plugin settings in database. Try re-installing plugin.' ) );

            return false;
        }

        self::$plugin_settings[$instance_id] = $db_details;

        return $db_details;
    }
}
