<?php

namespace phs\plugins\mailchimp;

use \phs\libraries\PHS_Plugin;
use \phs\libraries\PHS_Logger;

class PHS_Plugin_Mailchimp extends PHS_Plugin
{
    const LOG_CHANNEL = 'phs_mailchimp.log';

    /**
     * @return string Returns version of model
     */
    public function get_plugin_version()
    {
        return '1.0.0';
    }

    /**
     * @return array Returns an array with plugin details populated array returned by default_plugin_details_fields() method
     */
    public function get_plugin_details()
    {
        return array(
            'vendor_id' => 'phs',
            'vendor_name' => 'PHS',
            'name' => 'MailChimp integration',
            'description' => 'This plugin implements a library to be used when connecting to a MailChimp account. Plugin implements API communication with MailChimp servers.',
        );
    }

    public function get_models()
    {
        return array();
    }

    /**
     * Returns an instance of Mailchimp class
     *
     * @return bool|\phs\plugins\mailchimp\libraries\Mailchimp
     */
    public function get_mailchimp_instance()
    {
        static $mailchimp_library = null;

        if( $mailchimp_library !== null )
            return $mailchimp_library;

        $library_params = array();
        $library_params['full_class_name'] = '\\phs\\plugins\\mailchimp\\libraries\\Mailchimp';
        $library_params['as_singleton'] = true;

        /** @var \phs\plugins\mailchimp\libraries\Mailchimp $loaded_library */
        if( !($loaded_library = $this->load_library( 'phs_mailchimp', $library_params )) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_LIBRARY, $this->_pt( 'Error loading MailChimp library.' ) );

            return false;
        }

        if( $loaded_library->has_error() )
        {
            $this->copy_error( $loaded_library, self::ERR_LIBRARY );
            return false;
        }

        $mailchimp_library = $loaded_library;

        return $mailchimp_library;
    }

}
