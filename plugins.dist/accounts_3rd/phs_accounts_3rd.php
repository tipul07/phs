<?php
namespace phs\plugins\accounts_3rd;

use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Plugin;

class PHS_Plugin_Accounts_3rd extends PHS_Plugin
{
    public const ERR_REGISTER = 40000, ERR_LOGIN = 40001, ERR_CONFIRMATION = 40002;

    public const LOG_CHANNEL = 'phs_accounts_3rd_party.log', LOG_ERR_CHANNEL = 'phs_accounts_3rd_party_err.log';

    public const H_ACCOUNTS_3RD_REGISTER_BUFFER = 'phs_accounts_3rd_register_buffer',
    H_ACCOUNTS_3RD_LOGIN_BUFFER = 'phs_accounts_3rd_login_buffer';

    public function get_settings_keys_to_obfuscate()
    {
        return ['google_client_id', 'google_client_secret', 'apple_client_id', 'google_mobile_android_client_id', 'google_mobile_ios_client_id'];
    }

    /**
     * @inheritdoc
     */
    public function get_settings_structure()
    {
        if (!($google_lib = $this->get_google_instance())) {
            $this->reset_error();
            $google_lib = false;
        }

        if (!($apple_lib = $this->get_apple_instance())) {
            $this->reset_error();
            $apple_lib = false;
        }

        return [
            'enable_3rd_party' => [
                'display_name' => $this->_pt('Enable 3rd Party'),
                'display_hint' => $this->_pt('Enable 3rd party login or register in this platform?'),
                'type'         => PHS_Params::T_BOOL,
                'default'      => false,
            ],
            'register_login_non_existing' => [
                'display_name' => $this->_pt('Ask Register at Login'),
                'display_hint' => $this->_pt('If user tries to login with a non-existing email, should we offer register option?'),
                'type'         => PHS_Params::T_BOOL,
                'default'      => false,
            ],
            'register_login_forced' => [
                'display_name' => $this->_pt('Force Registration at Login'),
                'display_hint' => $this->_pt('If user tries to login with a non-existing email, force registration with provided details.'),
                'type'         => PHS_Params::T_BOOL,
                'default'      => false,
            ],
            'google_settings_group' => [
                'display_name' => $this->_pt('Google 3rd Party Settings'),
                'display_hint' => $this->_pt('Login or register with Google settings.'),
                'group_fields' => [
                    'enable_google' => [
                        'display_name' => $this->_pt('Enable Google Service'),
                        'display_hint' => $this->_pt('Enable login or register with Google 3rd party?'),
                        'type'         => PHS_Params::T_BOOL,
                        'default'      => false,
                    ],
                    'google_client_id' => [
                        'display_name' => $this->_pt('WEB Client ID'),
                        'display_hint' => $this->_pt('WEB Client ID to be used with OAuth when checking accounts in WEB interface'),
                        'type'         => PHS_Params::T_ASIS,
                        'default'      => '',
                    ],
                    'google_client_secret' => [
                        'display_name' => $this->_pt('WEB Client Secret'),
                        'display_hint' => $this->_pt('WEB Client Secret to be used with OAuth when checking accounts in WEB interface'),
                        'type'         => PHS_Params::T_ASIS,
                        'default'      => '',
                    ],
                    'google_web_login_return_url' => [
                        'display_name' => $this->_pt('WEB Login Return URL'),
                        'display_hint' => $this->_pt('When user wants to login using Google on WEB, what\'s the rewrite rule that redirects to Google WEB login page?'),
                        'type'         => PHS_Params::T_ASIS,
                        'default'      => ($google_lib ? $google_lib::REWRITE_RULE_LOGIN : ''),
                    ],
                    'google_web_register_return_url' => [
                        'display_name' => $this->_pt('WEB Register Return URL'),
                        'display_hint' => $this->_pt('When user wants to register using Google on WEB, what\'s the rewrite rule that redirects to Google WEB register page?'),
                        'type'         => PHS_Params::T_ASIS,
                        'default'      => ($google_lib ? $google_lib::REWRITE_RULE_REGISTER : ''),
                    ],
                    'google_mobile_android_client_id' => [
                        'display_name' => $this->_pt('MOBILE Client ID (Android)'),
                        'display_hint' => $this->_pt('MOBILE Client ID to be used with OAuth when checking accounts from an Android MOBILE application'),
                        'type'         => PHS_Params::T_ASIS,
                        'default'      => '',
                    ],
                    'google_mobile_ios_client_id' => [
                        'display_name' => $this->_pt('MOBILE Client ID (iOS)'),
                        'display_hint' => $this->_pt('MOBILE Client ID to be used with OAuth when checking accounts from an iOS MOBILE application'),
                        'type'         => PHS_Params::T_ASIS,
                        'default'      => '',
                    ],
                ],
            ],
            'apple_settings_group' => [
                'display_name' => $this->_pt('Apple 3rd Party Settings'),
                'display_hint' => $this->_pt('Login or register with Apple settings.'),
                'group_fields' => [
                    'enable_apple' => [
                        'display_name' => $this->_pt('Enable Apple Service'),
                        'display_hint' => $this->_pt('Enable login or register with Apple 3rd party?'),
                        'type'         => PHS_Params::T_BOOL,
                        'default'      => false,
                    ],
                    'apple_client_id' => [
                        'display_name' => $this->_pt('Client ID'),
                        'display_hint' => $this->_pt('Apple Client ID to be used when registering or logging in with Apple'),
                        'type'         => PHS_Params::T_ASIS,
                        'default'      => '',
                    ],
                    'apple_team_id' => [
                        'display_name' => $this->_pt('Team ID'),
                        'display_hint' => $this->_pt('Apple Team ID to be used when registering or logging in with Apple'),
                        'type'         => PHS_Params::T_ASIS,
                        'default'      => '',
                    ],
                    'apple_key_id' => [
                        'display_name' => $this->_pt('Key ID'),
                        'display_hint' => $this->_pt('Apple Key ID to be used when registering or logging in with Apple'),
                        'type'         => PHS_Params::T_ASIS,
                        'default'      => '',
                    ],
                    'apple_authentication_key' => [
                        'display_name' => $this->_pt('Authentication Key'),
                        'display_hint' => $this->_pt('Apple Key ID to be used when registering or logging in with Apple'),
                        'type'         => PHS_Params::T_ASIS,
                        'input_type'   => self::INPUT_TYPE_TEXTAREA,
                        'default'      => '',
                    ],
                    'apple_login_return_url' => [
                        'display_name' => $this->_pt('Apple Login Return URL'),
                        'display_hint' => $this->_pt('When user wants to login using Apple, what\'s the rewrite rule that redirects to Apple login page?'),
                        'type'         => PHS_Params::T_ASIS,
                        'default'      => ($apple_lib ? $apple_lib::REWRITE_RULE_LOGIN : ''),
                    ],
                    'apple_register_return_url' => [
                        'display_name' => $this->_pt('Apple Register Return URL'),
                        'display_hint' => $this->_pt('When user wants to register using Apple, what\'s the rewrite rule that redirects to Apple register page?'),
                        'type'         => PHS_Params::T_ASIS,
                        'default'      => ($apple_lib ? $apple_lib::REWRITE_RULE_REGISTER : ''),
                    ],
                ],
            ],
        ];
    }

    /**
     * Returns an instance of Google 3rd party services class
     *
     * @return bool|\phs\plugins\accounts_3rd\libraries\Google
     */
    public function get_google_instance()
    {
        static $google_library = null;

        $this->reset_error();

        if ($google_library !== null) {
            return $google_library;
        }

        $library_params = [];
        $library_params['full_class_name'] = '\\phs\\plugins\\accounts_3rd\\libraries\\Google';
        $library_params['as_singleton'] = true;

        /** @var \phs\plugins\accounts_3rd\libraries\Google $loaded_library */
        if (!($loaded_library = $this->load_library('phs_google', $library_params))) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_LIBRARY, $this->_pt('Error loading Google 3rd party library.'));
            }

            return false;
        }

        $google_library = $loaded_library;

        return $google_library;
    }

    /**
     * Returns an instance of Apple 3rd party services class
     *
     * @return bool|\phs\plugins\accounts_3rd\libraries\Apple
     */
    public function get_apple_instance()
    {
        static $apple_library = null;

        $this->reset_error();

        if ($apple_library !== null) {
            return $apple_library;
        }

        $library_params = [];
        $library_params['full_class_name'] = '\\phs\\plugins\\accounts_3rd\\libraries\\Apple';
        $library_params['as_singleton'] = true;

        /** @var \phs\plugins\accounts_3rd\libraries\Apple $loaded_library */
        if (!($loaded_library = $this->load_library('phs_apple', $library_params))) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_LIBRARY, $this->_pt('Error loading Apple 3rd party library.'));
            }

            return false;
        }

        $apple_library = $loaded_library;

        return $apple_library;
    }

    /**
     * @param bool|array $hook_args
     *
     * @return array
     */
    public function trigger_trd_party_login_buffer($hook_args = false)
    {
        $hook_args = self::validate_array($hook_args, PHS_Hooks::default_buffer_hook_args());

        $data = ((!empty($hook_args['buffer_data']) && is_array($hook_args['buffer_data'])) ? $hook_args['buffer_data'] : []);

        $hook_args['buffer'] = $this->quick_render_template_for_buffer('login_buffer', $data);

        return $hook_args;
    }

    /**
     * @param bool|array $hook_args
     *
     * @return array
     */
    public function trigger_trd_party_register_buffer($hook_args = false)
    {
        $hook_args = self::validate_array($hook_args, PHS_Hooks::default_buffer_hook_args());

        $data = ((!empty($hook_args['buffer_data']) && is_array($hook_args['buffer_data'])) ? $hook_args['buffer_data'] : []);

        $hook_args['buffer'] = $this->quick_render_template_for_buffer('register_buffer', $data);

        return $hook_args;
    }
}
