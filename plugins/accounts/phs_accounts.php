<?php

namespace phs\plugins\accounts;

use \phs;
use \phs\libraries\PHS_Plugin;

class PHS_Plugin_Accounts extends PHS_Plugin
{
    /**
     * @return string Returns version of model
     */
    public function get_plugin_version()
    {
        return '1.0.0';
    }

    public function get_models()
    {
        return array( 'accounts', 'accounts_details' );
    }

    /**
     * Override this function and return an array with default settings to be saved for current plugin
     *
     * @return array
     */
    public function get_default_settings()
    {
        return array(
            'email_mandatory' => true,
            'replace_nick_with_email' => true,
            'account_requires_activation' => true,
            'min_password_length' => 6,
            'pass_salt_length' => 8,
        );
    }

}
