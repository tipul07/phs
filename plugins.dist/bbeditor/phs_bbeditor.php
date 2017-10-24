<?php

namespace phs\plugins\bbeditor;

use \phs\libraries\PHS_Plugin;

class PHS_Plugin_Bbeditor extends PHS_Plugin
{
    /**
     * @return string Returns version of model
     */
    public function get_plugin_version()
    {
        return '1.0.2';
    }

    /**
     * @return array Returns an array with plugin details populated array returned by default_plugin_details_fields() method
     */
    public function get_plugin_details()
    {
        return array(
            'vendor_id' => 'phs',
            'vendor_name' => 'PHS',
            'name' => 'Simple BB code editor',
            'description' => 'If you need a simple text editor which only changes text formatting this is the one to use.',
        );
    }

    public function get_models()
    {
        return array();
    }

    /**
     * @inheritdoc
     */
    public function get_settings_structure()
    {
        return array(
        );
    }

    /**
     * Returns an instance of Bbcode class
     *
     * @return bool|\phs\plugins\bbeditor\libraries\Bbcode
     */
    public function get_bbcode_instance()
    {
        static $bbcode_library = null;

        if( $bbcode_library !== null )
            return $bbcode_library;

        $library_params = array();
        $library_params['full_class_name'] = '\\phs\\plugins\\bbeditor\\libraries\\Bbcode';
        $library_params['as_singleton'] = true;

        /** @var \phs\plugins\bbeditor\libraries\Bbcode $loaded_library */
        if( !($loaded_library = $this->load_library( 'phs_bbcode', $library_params )) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_LIBRARY, $this->_pt( 'Error loading BB code library.' ) );

            return false;
        }

        if( $loaded_library->has_error() )
        {
            $this->copy_error( $loaded_library, self::ERR_LIBRARY );
            return false;
        }

        $bbcode_library = $loaded_library;

        return $bbcode_library;
    }

}
