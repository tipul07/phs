<?php

namespace phs\plugins\accounts_3rd\libraries;

use phs\PHS;
use phs\libraries\PHS_utils;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Library;

class Google extends PHS_Library
{
    public const REWRITE_RULE_LOGIN = 'google/oauth/login', REWRITE_RULE_REGISTER = 'google/oauth/register';

    public const ACTION_LOGIN = 'login', ACTION_REGISTER = 'register';

    public const AUTH_PLATFORM_ANDROID = 'android', AUTH_PLATFORM_IOS = 'ios';

    public const SDK_DIR = 'google';

    /** @var bool|\phs\plugins\accounts_3rd\PHS_Plugin_Accounts_3rd */
    private $_accounts_3rd_plugin = false;

    public function get_auth_platforms()
    {
        return [self::AUTH_PLATFORM_ANDROID, self::AUTH_PLATFORM_IOS];
    }

    public function valid_auth_platform($platform)
    {
        $platform = trim($platform);
        if (!in_array($platform, $this->get_auth_platforms(), true)) {
            return false;
        }

        return $platform;
    }

    public function get_google_dir_paths()
    {
        $this->reset_error();

        if (!($library_paths = $this->get_library_location_paths())) {
            $this->set_error(self::ERR_SETTINGS, $this->_pt('Error obtaining Google 3rd party library directory paths.'));

            return false;
        }

        $return_arr = [];
        $return_arr['www'] = $library_paths['library_www'].self::SDK_DIR.'/';
        $return_arr['path'] = $library_paths['library_path'].self::SDK_DIR.'/';

        return $return_arr;
    }

    /**
     * @param false|array $params
     *
     * @return false|\Google\Client
     */
    public function get_web_instance_for_login($params = false)
    {
        return $this->_get_web_instance(self::ACTION_LOGIN, $params);
    }

    /**
     * @param false|array $params
     *
     * @return false|\Google\Client
     */
    public function get_web_instance_for_register($params = false)
    {
        return $this->_get_web_instance(self::ACTION_REGISTER, $params);
    }

    /**
     * @param string $google_code
     * @param string $action
     * @param false|array $params
     *
     * @return false|array
     */
    public function get_web_account_details_by_code($google_code, $action, $params = false)
    {
        if (!$this->_load_dependencies()) {
            return false;
        }

        $accounts_3rd_plugin = $this->_accounts_3rd_plugin;

        if (empty($google_code) || !is_string($google_code)) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Please provide a Google verification code.'));

            return false;
        }

        if (!($google_obj = $this->_get_web_instance($action, $params))) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error obtaining Google 3rd party WEB services instance.'));
            }

            return false;
        }

        $return_arr = [];
        try {
            if (!($token = $google_obj->fetchAccessTokenWithAuthCode($google_code))
             || !is_array($token)
             || empty($token['access_token'])) {
                if (!empty($token) && is_array($token)
                 && !empty($token['error'])) {
                    PHS_Logger::error('Error fetching WEB access token: '
                                      .$token['error'].(!empty($token['error_description']) ? ' ('.$token['error_description'].')' : ''), $accounts_3rd_plugin::LOG_ERR_CHANNEL);
                }

                $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error obtaining access token for Google 3rd party WEB services.'));

                return false;
            }

            $google_obj->setAccessToken($token['access_token']);

            $google_oauth = new \Google\Service\Oauth2($google_obj);
            if (($google_account_info = $google_oauth->userinfo->get())) {
                $return_arr = [
                    'email'          => $google_account_info->getEmail(),
                    'given_name'     => $google_account_info->getGivenName(),
                    'family_name'    => $google_account_info->getFamilyName(),
                    'gender'         => $google_account_info->getGender(),
                    'hd'             => $google_account_info->getHd(),
                    'id'             => $google_account_info->getId(),
                    'link'           => $google_account_info->getLink(),
                    'locale'         => $google_account_info->getLocale(),
                    'name'           => $google_account_info->getName(),
                    'picture'        => $google_account_info->getPicture(),
                    'verified_email' => $google_account_info->getVerifiedEmail(),
                ];
            }
        } catch (\Exception $e) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error obtaining Google account details for WEB services.'));

            return false;
        }

        return $return_arr;
    }

    /**
     * @param string $google_code
     * @param string $platform
     * @param false|array $params
     *
     * @return false|array
     */
    public function get_mobile_account_details_by_code($google_code, $platform, $params = false)
    {
        if (!$this->_load_dependencies()) {
            return false;
        }

        if (empty($google_code) || !is_string($google_code)) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Please provide a Google verification code.'));

            return false;
        }

        // When verifying tokens, action doesn't really matter
        if (!($google_obj = $this->_get_mobile_instance($platform, $params))) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error obtaining Google 3rd party MOBILE services instance.'));
            }

            return false;
        }

        try {
            if (!($google_account_info = $google_obj->verifyIdToken($google_code))
             || !is_array($google_account_info)) {
                // if( !empty( $token ) && is_array( $token )
                //  && !empty( $token['error'] ) )
                // {
                //     PHS_Logger::error( 'Error fetching WEB access token: '.
                //                       $token['error'].(!empty( $token['error_description'] )?' ('.$token['error_description'].')':''), $accounts_3rd_plugin::LOG_ERR_CHANNEL );
                // }

                $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error obtaining account details for Google 3rd party MOBILE services.'));

                return false;
            }
        } catch (\Exception $e) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error obtaining Google account details for MOBILE services.'));

            return false;
        }

        return $google_account_info;
    }

    private function _load_dependencies()
    {
        $this->reset_error();

        if (empty($this->_accounts_3rd_plugin)
         && !($this->_accounts_3rd_plugin = PHS::load_plugin('accounts_3rd'))) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Couldn\'t load accounts 3rd party plugin instance.'));

            return false;
        }

        if (!($settings_arr = $this->_accounts_3rd_plugin->get_plugin_settings())
         || !is_array($settings_arr)
         || empty($settings_arr['enable_3rd_party'])
         || empty($settings_arr['enable_google'])) {
            $this->set_error(self::ERR_SETTINGS, $this->_pt('3rd party Google services are not enabled.'));

            return false;
        }

        if (!($googlelib_paths = $this->get_google_dir_paths())) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_SETTINGS, $this->_pt('Error obtaining phpseclib library directory paths.'));
            }

            return false;
        }

        $autoload_file = $googlelib_paths['path'].'vendor/autoload.php';

        if (!@file_exists($autoload_file)) {
            $this->set_error(self::ERR_SETTINGS, $this->_pt('Autoload for Google 3rd party functionality not found.'));

            return false;
        }

        include_once $autoload_file;

        return true;
    }

    /**
     * Instantiate a generic Google client object (can be used on web or mobile)
     * @param false|array{as_static:bool,return_url_params:false|array} $params
     *
     * @return bool|\Google\Client
     */
    private function _get_google_instance($params = false)
    {
        /** @var \Google\Client $client_obj */
        static $client_obj = null;

        if (!$this->_load_dependencies()) {
            return false;
        }

        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        $params['as_static'] = (!empty($params['as_static']));

        if (!$params['as_static']) {
            try {
                $return_obj = new \Google\Client();
            } catch (\Exception $e) {
                $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error obtaining an instance of Google 3rd party client.'));

                return false;
            }
        } elseif ($client_obj !== null) {
            $return_obj = $client_obj;
        }

        if (empty($return_obj)) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error obtaining an instance of Google 3rd party client.'));

            return false;
        }

        $return_obj->addScope(['email', 'profile']);

        if ($params['as_static']) {
            $client_obj = $return_obj;
        }

        return $return_obj;
    }

    /**
     * Instantiate a WEB Google client object
     * @param string $action Prepares a WEB instance for login or register (values: login or register)
     * @param false|array{as_static:bool} $params
     *
     * @return false|\Google\Client
     */
    private function _get_web_instance($action, $params = false)
    {
        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        if (!($google_obj = $this->_get_google_instance($params))) {
            return false;
        }

        if (!in_array($action, [self::ACTION_LOGIN, self::ACTION_REGISTER], true)) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Please provide an action for Google 3rd party WEB services.'));

            return false;
        }

        $accounts_3rd_plugin = $this->_accounts_3rd_plugin;

        if (!($settings_arr = $accounts_3rd_plugin->get_plugin_settings())
         || empty($settings_arr['google_client_id']) || empty($settings_arr['google_client_secret'])) {
            $this->set_error(self::ERR_SETTINGS, $this->_pt('Error obtaining Google 3rd party WEB services settings.'));

            return false;
        }

        $return_url = '';
        if ($action === self::ACTION_LOGIN) {
            if (!empty($settings_arr['google_web_login_return_url'])) {
                $return_url = PHS::get_base_url(true).trim($settings_arr['google_web_login_return_url'], '/');
            } else {
                $return_url = PHS::get_base_url(true).self::REWRITE_RULE_LOGIN;
            }
        } elseif ($action === self::ACTION_REGISTER) {
            if (!empty($settings_arr['google_web_register_return_url'])) {
                $return_url = PHS::get_base_url(true).trim($settings_arr['google_web_register_return_url'], '/');
            } else {
                $return_url = PHS::get_base_url(true).self::REWRITE_RULE_REGISTER;
            }
        }

        if (empty($return_url)) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Please provide an action for Google 3rd party WEB services.'));

            return false;
        }

        $google_obj->setAccessType('offline');
        $google_obj->setClientId($settings_arr['google_client_id']);
        $google_obj->setClientSecret($settings_arr['google_client_secret']);
        $google_obj->setRedirectUri($return_url);

        return $google_obj;
    }

    /**
     * Instantiate a MOBILE Google client object
     * @param string $platform
     * @param false|array{as_static:bool} $params
     *
     * @return false|\Google\Client
     */
    private function _get_mobile_instance($platform, $params = false)
    {
        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        $accounts_3rd_plugin = $this->_accounts_3rd_plugin;

        if (!($settings_arr = $accounts_3rd_plugin->get_plugin_settings())
         || empty($settings_arr['google_mobile_android_client_id'])
         || empty($settings_arr['google_mobile_ios_client_id'])) {
            $this->set_error(self::ERR_SETTINGS, $this->_pt('Error obtaining Google 3rd party MOBILE services settings.'));

            return false;
        }

        if (!($platform = $this->valid_auth_platform($platform))) {
            $this->set_error(self::ERR_SETTINGS, $this->_pt('Invalid authentication platform for Google 3rd party MOBILE services.'));

            return false;
        }

        if (!($google_obj = $this->_get_google_instance($params))) {
            return false;
        }

        $google_obj->setAccessType('offline');

        if ($platform === self::AUTH_PLATFORM_ANDROID) {
            $google_obj->setClientId($settings_arr['google_mobile_android_client_id']);
        } elseif ($platform === self::AUTH_PLATFORM_IOS) {
            $google_obj->setClientId($settings_arr['google_mobile_ios_client_id']);
        }

        return $google_obj;
    }
}
