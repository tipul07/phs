<?php

namespace phs\plugins\accounts_3rd\libraries;

use phs\PHS;
use phs\libraries\PHS_utils;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Library;

class Apple extends PHS_Library
{
    public const REWRITE_RULE_LOGIN = 'apple/oauth/login', REWRITE_RULE_REGISTER = 'apple/oauth/register';

    public const ACTION_LOGIN = 'login', ACTION_REGISTER = 'register';

    public const ERR_API_INIT = 1;

    /** @var bool|\phs\plugins\accounts_3rd\PHS_Plugin_Accounts_3rd */
    private $_accounts_3rd_plugin = false;

    private $_settings_arr = [
        'client_id'          => '',
        'team_id'            => '',
        'key_id'             => '',
        'authentication_key' => '',
        'return_url'         => '',
    ];

    /**
     * @param false|array $params
     *
     * @return bool
     */
    public function prepare_instance_for_login($params = false)
    {
        return $this->_prepare_instance(self::ACTION_LOGIN, $params);
    }

    /**
     * @param false|array $params
     *
     * @return false|\Google\Client
     */
    public function prepare_instance_for_register($params = false)
    {
        return $this->_prepare_instance(self::ACTION_REGISTER, $params);
    }

    public function get_url($action, $state = '', $scope = 'name email')
    {
        if (!$this->_prepare_instance($action)) {
            return false;
        }

        $args = [
            'response_type' => 'code',
            'response_mode' => 'form_post',
            'client_id'     => $this->_settings_arr['client_id'],
            'redirect_uri'  => $this->_settings_arr['return_url'],
            'state'         => $state,
            'scope'         => $scope,
        ];

        return 'https://appleid.apple.com/auth/authorize?'.http_build_query($args);
    }

    /**
     * This method is used in web "Login with Apple" functionality
     * @param string $apple_code
     * @param string $action
     * @param false|array $params
     *
     * @return false|array
     */
    public function get_account_details_by_code($apple_code, $action, $params = false)
    {
        if (!$this->_load_dependencies()) {
            return false;
        }

        if (!($response = $this->_check_code_with_apple($apple_code, $action, $params))) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_API_INIT, $this->_pt('Error verifying token with Apple. Please try again.'));
            }

            return false;
        }

        return $this->_get_account_details_from_token_id($response['id_token'], $action, $params);
    }

    /**
     * If authentication passed, and we already have a token id, ask Apple account details based on that token id.
     * This method is used when logging in with Apple in a mobile application
     * @param string $token_id
     * @param string $action
     * @param false|array $params
     *
     * @return false|array
     */
    public function get_account_details_by_token_id($token_id, $action, $params = false)
    {
        if (!$this->_load_dependencies()) {
            return false;
        }

        return $this->_get_account_details_from_token_id($token_id, $action, $params);
    }

    /**
     * @param string $full_url
     * @param array $post_arr
     * @param false|array $params
     *
     * @return array|bool
     */
    protected function do_api_call($full_url, $post_arr, $params = false)
    {
        if (empty($full_url)) {
            $this->set_error(self::ERR_API_INIT, $this->_pt('Please provide full URL for Apple 3rd party service.'));

            return false;
        }

        $plugin_obj = $this->_accounts_3rd_plugin;

        if (empty($post_arr)) {
            $post_arr = false;
        }

        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        if (!isset($params['expect_http_codes']) || !is_array($params['expect_http_codes'])) {
            $params['expect_http_codes'] = [200, 201, 204];
        }

        if (!isset($params['expect_json'])) {
            $params['expect_json'] = true;
        }
        if (empty($params['timeout'])) {
            $params['timeout'] = 30;
        }

        $curl_params = [];
        $curl_params['timeout'] = $params['timeout'];
        $curl_params['header_keys_arr'] = [
            'Accept' => 'application/json',
        ];

        $payload_str = false;
        if (!empty($post_arr) && is_array($post_arr)) {
            $curl_params['post_arr'] = $post_arr;
            $payload_str = @json_encode($post_arr);
        }

        if (!($api_response = PHS_utils::quick_curl($full_url, $curl_params))
         || !is_array($api_response)) {
            PHS_Logger::error('[APPLE] Error initiating call to Apple service API.', $plugin_obj::LOG_ERR_CHANNEL);

            ob_start();
            var_dump($curl_params);
            $request_params = @ob_get_clean();

            PHS_Logger::error('[APPLE] Apple service API URL: '.$full_url."\n"
                            .'Params: '.$request_params."\n"
                            .'Payload: '.(!empty($payload_str) ? $payload_str : 'N/A'), $plugin_obj::LOG_ERR_CHANNEL);

            $this->set_error(self::ERR_API_INIT, $this->_pt('Error initiating call to Apple service API.'));

            return false;
        }

        if (empty($api_response['request_details']) || !is_array($api_response['request_details'])) {
            PHS_Logger::error('[APPLE] Error retrieving Apple service API request details.', $plugin_obj::LOG_ERR_CHANNEL);

            ob_start();
            var_dump($curl_params);
            $request_params = @ob_get_clean();

            PHS_Logger::error('[APPLE] Apple service API URL: '.$full_url."\n"
                            .'Params: '.$request_params."\n"
                            .'Payload: '.(!empty($payload_str) ? $payload_str : 'N/A'), $plugin_obj::LOG_ERR_CHANNEL);

            $this->set_error(self::ERR_API_INIT, $this->_pt('Error retrieving Apple service API request details.'));

            return false;
        }

        if (empty($api_response['http_code'])
         || !in_array((int)$api_response['http_code'], $params['expect_http_codes'], true)) {
            PHS_Logger::error('[APPLE] Apple service API responded with HTTP code: '.$api_response['http_code'], $plugin_obj::LOG_ERR_CHANNEL);

            $request_headers = (!empty($api_response['request_details']['request_header']) ? $api_response['request_details']['request_header'] : 'N/A');
            if (!empty($api_response['request_details']['request_params'])) {
                ob_start();
                var_dump($api_response['request_details']['request_params']);
                $request_params = @ob_get_clean();
            } else {
                $request_params = 'N/A';
            }

            PHS_Logger::error('[APPLE] Apple service API URL: '.$full_url."\n"
                            .'Request headers: '.$request_headers."\n"
                            .'Params: '.$request_params."\n"
                            .'Payload: '.(!empty($payload_str) ? $payload_str : 'N/A')."\n"
                            .'Response: '.(!empty($api_response['response']) ? $api_response['response'] : 'N/A'), $plugin_obj::LOG_ERR_CHANNEL);

            $this->set_error(self::ERR_API_INIT,
                $this->_pt('Apple service API responded with HTTP code: %s.', $api_response['http_code']));

            return false;
        }

        $http_code = (int)$api_response['http_code'];

        if (!empty($http_code)) {
            PHS_Logger::notice('[APPLE] Apple service API URL: '.$full_url."\n"
                            .'Payload: '.(!empty($payload_str) ? $payload_str : 'N/A')."\n"
                            .'Apple service API responded with HTTP code: '.$api_response['request_details']['http_code'], $plugin_obj::LOG_ERR_CHANNEL);
        }

        $api_response['response_json'] = false;

        // If we received something different than 204
        if ($http_code !== 204
        && !empty($params['expect_json'])
        && empty($api_response['response'])) {
            PHS_Logger::error('[APPLE] Apple service API response body is empty.', $plugin_obj::LOG_ERR_CHANNEL);

            $request_headers = (!empty($api_response['request_details']['request_header']) ? $api_response['request_details']['request_header'] : 'N/A');
            if (!empty($api_response['request_details']['request_params'])) {
                ob_start();
                var_dump($api_response['request_details']['request_params']);
                $request_params = @ob_get_clean();
            } else {
                $request_params = 'N/A';
            }

            PHS_Logger::error('[APPLE] Apple service API URL: '.$full_url."\n"
                            .'Request headers: '.$request_headers."\n"
                            .'Params: '.$request_params."\n"
                            .'Payload: '.(!empty($payload_str) ? $payload_str : 'N/A'), $plugin_obj::LOG_ERR_CHANNEL);
        } elseif (!empty($params['expect_json'])
            && !($api_response['response_json'] = @json_decode($api_response['response'], true))) {
            PHS_Logger::error('[APPLE] Couldn\'t decode API response.', $plugin_obj::LOG_ERR_CHANNEL);

            $request_headers = (!empty($api_response['request_details']['request_header']) ? $api_response['request_details']['request_header'] : 'N/A');
            if (!empty($api_response['request_details']['request_params'])) {
                ob_start();
                var_dump($api_response['request_details']['request_params']);
                $request_params = @ob_get_clean();
            } else {
                $request_params = 'N/A';
            }

            PHS_Logger::error('[APPLE] Apple service API URL: '.$full_url."\n"
                            .'Request headers: '.$request_headers."\n"
                            .'Params: '.$request_params."\n"
                            .'Payload: '.(!empty($payload_str) ? $payload_str : 'N/A'), $plugin_obj::LOG_ERR_CHANNEL);
        }

        return $api_response;
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
         || empty($settings_arr['enable_apple'])) {
            $this->set_error(self::ERR_SETTINGS, $this->_pt('3rd party Apple services are not enabled.'));

            return false;
        }

        return true;
    }

    /**
     * Prepare Apple instance for a call
     * @param string $action Prepares instance for login or register (values: login or register)
     * @param false|array{as_static:bool} $params
     *
     * @return bool
     */
    private function _prepare_instance($action, $params = false)
    {
        if (!$this->_load_dependencies()) {
            return false;
        }

        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        if (!in_array($action, [self::ACTION_LOGIN, self::ACTION_REGISTER], true)) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Please provide an action for Apple 3rd party service.'));

            return false;
        }

        $accounts_3rd_plugin = $this->_accounts_3rd_plugin;

        if (!($settings_arr = $accounts_3rd_plugin->get_plugin_settings())
         || empty($settings_arr['apple_client_id']) || empty($settings_arr['apple_team_id'])
         || empty($settings_arr['apple_key_id']) || empty($settings_arr['apple_authentication_key'])) {
            $this->set_error(self::ERR_SETTINGS, $this->_pt('Error obtaining Apple 3rd party service settings.'));

            return false;
        }

        $return_url = '';
        if ($action === self::ACTION_LOGIN) {
            if (!empty($settings_arr['apple_login_return_url'])) {
                $return_url = PHS::get_base_url(true).trim($settings_arr['apple_login_return_url'], '/');
            } else {
                $return_url = PHS::get_base_url(true).self::REWRITE_RULE_LOGIN;
            }
        } elseif ($action === self::ACTION_REGISTER) {
            if (!empty($settings_arr['apple_register_return_url'])) {
                $return_url = PHS::get_base_url(true).trim($settings_arr['apple_register_return_url'], '/');
            } else {
                $return_url = PHS::get_base_url(true).self::REWRITE_RULE_REGISTER;
            }
        }

        if (empty($return_url)) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Error obtaining a return URL for Apple 3rd party service.'));

            return false;
        }

        $this->_settings_arr['client_id'] = $settings_arr['apple_client_id'];
        $this->_settings_arr['team_id'] = $settings_arr['apple_team_id'];
        $this->_settings_arr['key_id'] = $settings_arr['apple_key_id'];
        $this->_settings_arr['authentication_key'] = $settings_arr['apple_authentication_key'];
        $this->_settings_arr['return_url'] = $return_url;

        return true;
    }

    /**
     * @param string $apple_code
     * @param string $action
     * @param false|array $params
     *
     * @return false|array
     */
    private function _check_code_with_apple($apple_code, $action, $params = false)
    {
        if (!$this->_load_dependencies()) {
            return false;
        }

        $accounts_3rd_plugin = $this->_accounts_3rd_plugin;

        if (empty($apple_code) || !is_string($apple_code)) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Please provide an Apple verification code.'));

            return false;
        }

        if (!$this->_prepare_instance($action, $params)) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error preparing Apple services for action %s.', $action));
            }

            return false;
        }

        if (!($jwt_token = $this->_get_jwt_token())
         || !($response = $this->_get_response($apple_code, $jwt_token))
         || !is_array($response)
         || empty($response['id_token'])) {
            $error_msg = '';
            if (!empty($response) && is_array($response)) {
                if (!empty($response['error'])) {
                    $error_msg .= '('.$response['error'].')';
                }
                if (!empty($response['error_description'])) {
                    $error_msg .= ($error_msg !== '' ? ' ' : '').$response['error_description'];
                }
            }

            PHS_Logger::error('[APPLE] Error fetching access token: '.(!empty($error_msg) ? $error_msg : 'N/A'), $accounts_3rd_plugin::LOG_ERR_CHANNEL);

            if (!$this->has_error()) {
                $this->set_error(self::ERR_FUNCTIONALITY,
                    $this->_pt('Error sending request to Apple services for action %s.', $action));
            }

            return false;
        }

        if (self::st_debugging_mode()) {
            ob_start();
            var_dump($response);
            $buf = ob_get_clean();

            PHS_Logger::debug('[APPLE] Response: '.$buf, $accounts_3rd_plugin::LOG_CHANNEL);
        }

        return $response;
    }

    /**
     * @param string $token_id
     * @param string $action
     * @param false|array $params
     *
     * @return false|array
     */
    private function _get_account_details_from_token_id($token_id, $action, $params = false)
    {
        if (!$this->_load_dependencies()) {
            return false;
        }

        $accounts_3rd_plugin = $this->_accounts_3rd_plugin;

        if (empty($token_id) || !is_string($token_id)) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Please provide an Apple verification code.'));

            return false;
        }

        if (!$this->_prepare_instance($action, $params)) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error preparing Apple services for action %s.', $action));
            }

            return false;
        }

        if (empty($token_id)
         || !($payload_arr = $this->_decode_id_token($token_id))
         || !is_array($payload_arr)
         || empty($payload_arr['payload']) || !is_array($payload_arr['payload'])) {
            $error_msg = '';
            if ($this->has_error()) {
                $error_msg = $this->get_simple_error_message();
            }

            ob_start();
            var_dump($payload_arr);
            $buf = ob_get_clean();

            PHS_Logger::error('[APPLE] Error decoding token: '.(!empty($error_msg) ? $error_msg : 'N/A')."\n"
                              .'Payload: '.$buf, $accounts_3rd_plugin::LOG_ERR_CHANNEL);

            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Couldn\'t decode Apple token ID.'));

            return false;
        }

        $return_arr = [
            'email'            => (!empty($payload_arr['payload']['email']) ? $payload_arr['payload']['email'] : ''),
            'email_verified'   => (!empty($payload_arr['payload']['email_verified']) ? $payload_arr['payload']['email_verified'] : ''),
            'is_private_email' => (!empty($payload_arr['payload']['is_private_email']) ? $payload_arr['payload']['is_private_email'] : ''),
            'fname'            => '',
            'lname'            => '',
        ];

        if (!empty($payload_arr['payload']['name']) && is_array($payload_arr['payload']['name'])) {
            if (!empty($payload_arr['payload']['name']['firstName'])) {
                $return_arr['fname'] = $payload_arr['payload']['name']['firstName'];
            }
            if (!empty($payload_arr['payload']['name']['lastName'])) {
                $return_arr['lname'] = $payload_arr['payload']['name']['lastName'];
            }
        }

        $bool_string_keys = ['email_verified', 'is_private_email'];
        foreach ($bool_string_keys as $field) {
            if ($return_arr[$field] === 'true') {
                $return_arr[$field] = true;
            } elseif ($return_arr[$field] === 'false') {
                $return_arr[$field] = false;
            }
        }

        return $return_arr;
    }

    private function _get_jwt_token()
    {
        $datetime = new \DateTime();
        $time = $datetime->getTimestamp();
        $time_end = $time + 3600;

        $claims = [
            'iss' => $this->_settings_arr['team_id'],
            'sub' => $this->_settings_arr['client_id'],
            'aud' => 'https://appleid.apple.com',
            'iat' => $time,
            'exp' => $time_end,
        ];

        $headers = [
            'kid' => $this->_settings_arr['key_id'],
            'alg' => 'ES256',
        ];

        return $this->_jwt_encode($claims, $headers, $this->_settings_arr['authentication_key']);
    }

    private function _get_response($code, $jwt_token)
    {
        $post_arr = [
            'client_id'     => $this->_settings_arr['client_id'],
            'client_secret' => $jwt_token,
            'code'          => $code,
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $this->_settings_arr['return_url'],
        ];

        if (!($api_response = $this->do_api_call('https://appleid.apple.com/auth/token', $post_arr))
         || empty($api_response['response_json'])) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_API_INIT, $this->_pt('Error sending request to Apple services.'));
            }

            return false;
        }

        return $api_response['response_json'];
    }

    private function _decode_id_token($id_token)
    {
        return $this->_jwt_decode($id_token, $this->_settings_arr['authentication_key']);
    }

    private function _jwt_encode($body, $head, $private_key)
    {
        $this->reset_error();

        if (!@function_exists('openssl_get_md_methods')
         || !@in_array('sha256', @openssl_get_md_methods(), true)) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Apple 3rd party service requires openssl with sha256 support.'));

            return false;
        }

        $msg = $this->_base64url_encode(json_encode($head)).'.'.$this->_base64url_encode(json_encode($body));

        if (!($privateKeyRes = @openssl_pkey_get_private($private_key, null))
         || !@openssl_sign($msg, $der, $privateKeyRes, 'sha256')) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error encoding data for Apple 3rd party service.'));

            return false;
        }

        // DER unpacking from https://github.com/firebase/php-jwt
        $components = [];
        $pos = 0;
        $size = strlen($der);
        while ($pos < $size) {
            $constructed = (ord($der[$pos]) >> 5) & 0x01;
            $type = ord($der[$pos++]) & 0x1F;
            $len = ord($der[$pos++]);
            if ($len & 0x80) {
                $n = $len & 0x1F;
                $len = 0;
                while ($n-- && $pos < $size) {
                    $len = ($len << 8) | ord($der[$pos++]);
                }
            }

            if ($type === 0x03) {
                $pos++;
                $components[] = substr($der, $pos, $len - 1);
                $pos += $len - 1;
            } elseif (!$constructed) {
                $components[] = substr($der, $pos, $len);
                $pos += $len;
            }
        }

        foreach ($components as &$c) {
            $c = str_pad(ltrim($c, "\x00"), 32, "\x00", STR_PAD_LEFT);
        }

        return $msg.'.'.$this->_base64url_encode(implode('', $components));
    }

    private function _jwt_decode($jwt_token, $private_key)
    {
        $this->reset_error();

        if (!@function_exists('openssl_get_md_methods')
         || !@in_array('sha256', @openssl_get_md_methods(), true)) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Apple 3rd party service requires openssl with sha256 support.'));

            return false;
        }

        if (!($jwt_arr = explode('.', $jwt_token))
         || !is_array($jwt_arr)
         || count($jwt_arr) !== 3) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Invalid JWT token when decoding it.'));

            return false;
        }

        $jwt_arr = array_combine(['header', 'payload', 'signature'], $jwt_arr);

        return [
            'header'  => json_decode(base64_decode($jwt_arr['header']), true),
            'payload' => json_decode(base64_decode($jwt_arr['payload']), true),
            'hash'    => base64_encode(hash_hmac(
                'sha256',
                $jwt_arr['header'].'.'.$jwt_arr['payload'],
                $private_key,
                true)),
        ];
    }

    private function _base64url_encode($binary_data)
    {
        return strtr(rtrim(base64_encode($binary_data), '='), '+/', '-_');
    }
}
