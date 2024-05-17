<?php

namespace phs\plugins\mailchimp;

use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Plugin;

class PHS_Plugin_Mailchimp extends PHS_Plugin
{
    public const LOG_CHANNEL = 'phs_mailchimp.log';

    /**
     * Returns an instance of Mailchimp class
     *
     * @return bool|libraries\Mailchimp
     */
    public function get_mailchimp_instance()
    {
        static $mailchimp_library = null;

        if ($mailchimp_library !== null) {
            return $mailchimp_library;
        }

        $library_params = [];
        $library_params['full_class_name'] = '\\phs\\plugins\\mailchimp\\libraries\\Mailchimp';
        $library_params['as_singleton'] = true;

        /** @var libraries\Mailchimp $loaded_library */
        if (!($loaded_library = $this->load_library('phs_mailchimp', $library_params))) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_LIBRARY, $this->_pt('Error loading MailChimp library.'));
            }

            return false;
        }

        if ($loaded_library->has_error()) {
            $this->copy_error($loaded_library, self::ERR_LIBRARY);

            return false;
        }

        $mailchimp_library = $loaded_library;

        return $mailchimp_library;
    }
}
