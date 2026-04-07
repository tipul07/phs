<?php
namespace phs\plugins\mailchimp;

use phs\libraries\PHS_Plugin;
use phs\plugins\mailchimp\libraries\Mailchimp;

class PHS_Plugin_Mailchimp extends PHS_Plugin
{
    public const LOG_CHANNEL = 'phs_mailchimp.log';

    /**
     * Returns an instance of Mailchimp class
     *
     * @return null|libraries\Mailchimp
     */
    public function get_mailchimp_instance(): ?libraries\Mailchimp
    {
        static $mailchimp_library = null;

        if ($mailchimp_library !== null) {
            return $mailchimp_library;
        }

        $library_params = [];
        $library_params['full_class_name'] = Mailchimp::class;
        $library_params['as_singleton'] = true;

        /** @var libraries\Mailchimp $loaded_library */
        if (!($loaded_library = $this->load_library('phs_mailchimp', $library_params))) {
            $this->set_error_if_not_set(self::ERR_LIBRARY, $this->_pt('Error loading MailChimp library.'));

            return null;
        }

        if ($loaded_library->has_error()) {
            $this->copy_error($loaded_library, self::ERR_LIBRARY);

            return null;
        }

        $mailchimp_library = $loaded_library;

        return $mailchimp_library;
    }
}
