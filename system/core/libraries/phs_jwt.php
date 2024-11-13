<?php
namespace phs\system\core\libraries;

use phs\PHS;
use phs\libraries\PHS_Library;

class PHS_Jwt extends PHS_Library
{
    /**
     * When checking nbf, iat or expiration times,
     * we want to provide some extra leeway time to
     * account for clock skew.
     */
    private $leeway = 0;

    /**
     * Allow the current timestamp to be specified.
     * Useful for fixing a value within unit testing.
     *
     * Will default to PHP time() value if null.
     */
    private $timestamp;

    public static $supported_algs = [
        'HS256' => ['hash_hmac', 'SHA256'],
        'HS512' => ['hash_hmac', 'SHA512'],
        'HS384' => ['hash_hmac', 'SHA384'],
        'RS256' => ['openssl', 'SHA256'],
        'RS384' => ['openssl', 'SHA384'],
        'RS512' => ['openssl', 'SHA512'],
    ];

    /**
     * PHS_Jwt constructor.
     *
     * @param bool|array $params
     */
    public function __construct($params = false)
    {
        parent::__construct();

        if (!empty($params) && is_array($params)) {
            if (isset($params['timestamp'])) {
                $this->timestamp($params['timestamp']);
            }
            if (isset($params['leeway'])) {
                $this->leeway($params['leeway']);
            }
        }

        $this->reset_error();
    }

    public function timestamp($ts = false)
    {
        if ($ts !== false) {
            $this->timestamp = (int)$ts;

            return $this->timestamp;
        }

        if ($this->timestamp === null) {
            $this->timestamp = time();
        }

        return $this->timestamp;
    }

    public function leeway($lw = false)
    {
        if ($lw !== false) {
            $this->leeway = (int)$lw;

            return $this->leeway;
        }

        return $this->leeway;
    }

    /**
     * @param string $jwt JWT string
     * @param bool|array $params Action parameters
     *
     * @return bool|array Returns payload from JWT
     */
    public function decode($jwt, $params = false)
    {
        $this->reset_error();

        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        // !!!Public key used in JWT validation. If you don't provide it here be sure to manually do verify_payload() call later!!!
        if (empty($params['verification_key'])) {
            $params['verification_key'] = false;
        }
        if (empty($params['allowed_algs']) || !is_array($params['allowed_algs'])) {
            $params['allowed_algs'] = [];
        }
        if (!isset($params['do_verification'])) {
            $params['do_verification'] = true;
        } else {
            $params['do_verification'] = (!empty($params['do_verification']) ? true : false);
        }

        if (empty($params['issuer'])) {
            $params['issuer'] = '';
        }

        if (empty($params['audience'])) {
            $params['audience'] = [];
        }
        if (!is_array($params['audience'])) {
            $params['audience'] = [$params['audience']];
        }

        $key = $params['verification_key'];

        if (!empty($params['do_verification'])
        && empty($key)) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('JWT key not provided.'));

            return false;
        }

        if (empty($jwt)
         || !($segments_arr = @explode('.', $jwt))
         || !is_array($segments_arr)
         || count($segments_arr) != 3) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Invalid JWT segments.'));

            return false;
        }

        $header_enc = $segments_arr[0];
        $payload_enc = $segments_arr[1];

        if (!($header_str = $this->_safe_base64_decode($header_enc))
         || !($header_arr = @json_decode($header_str, true))
         || !is_array($header_arr)) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Couldn\'t decode JWT header.'));

            return false;
        }

        if (!($payload_str = $this->_safe_base64_decode($payload_enc))
         || null === ($payload_arr = @json_decode($payload_str, true))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Couldn\'t decode JWT payload.'));

            return false;
        }

        if (!($signature_str = $this->_safe_base64_decode($segments_arr[2]))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Couldn\'t decode JWT signature.'));

            return false;
        }

        if (empty($payload_arr)) {
            $payload_arr = [];
        }

        if (!empty($header_arr['alg'])) {
            $header_arr['alg'] = strtoupper($header_arr['alg']);
        }

        if (empty($header_arr['alg'])
         || empty(self::$supported_algs[$header_arr['alg']])) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('JWT algorithm not supported.'));

            return false;
        }

        if (!empty($params['allowed_algs'])
        && !in_array($header_arr['alg'], $params['allowed_algs'])) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('JWT algorithm not allowed.'));

            return false;
        }

        // Check signature...
        if (!empty($params['do_verification'])) {
            if (is_array($key)) {
                if (!isset($header_arr['kid'])) {
                    $this->set_error(self::ERR_PARAMETERS, $this->_pt('JWT key not provided.'));

                    return false;
                }

                if (empty($key[$header_arr['kid']])) {
                    $this->set_error(self::ERR_PARAMETERS, $this->_pt('JWT key is invalid.'));

                    return false;
                }

                $key = $key[$header_arr['kid']];
            }

            if (!$this->verify_payload($header_enc.'.'.$payload_enc, $signature_str, $key, $header_arr['alg'])) {
                if (!$this->has_error()) {
                    $this->set_error(self::ERR_PARAMETERS, $this->_pt('JWT signature verification failed.'));
                }

                return false;
            }
        }

        $timestamp = $this->timestamp();
        $leeway = $this->leeway();

        // Check if the nbf if it is defined. This is the time that the
        // token can actually be used. If it's not yet that time, abort.
        if (isset($payload_arr['nbf']) && $payload_arr['nbf'] > ($timestamp + $leeway)) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('JWT cannot be used now.'));

            return false;
        }

        // Check that this token has been created before 'now'. This prevents
        // using tokens that have been created for later use (and haven't
        // correctly used the nbf claim).
        if (isset($payload_arr['iat']) && $payload_arr['iat'] > ($timestamp + $leeway)) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('JWT cannot be used now.'));

            return false;
        }

        // Check if this token has expired.
        if (isset($payload_arr['exp']) && ($timestamp - $leeway) > $payload_arr['exp']) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('JWT token expired.'));

            return false;
        }

        // Check audience (if present)
        if ((empty($payload_arr['aud']) && !empty($params['audience']))
         || (!empty($payload_arr['aud'])
                && (empty($params['audience']) || !in_array($payload_arr['aud'], $params['audience'], true))
         )) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('JWT audience is invalid.'));

            return false;
        }

        // Check issuer (if present)
        if (!empty($params['issuer'])
        && (empty($payload_arr['iss']) || $payload_arr['iss'] !== $params['issuer'])) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('JWT issuer is invalid.'));

            return false;
        }

        return [
            'header'    => $header_arr,
            'payload'   => $payload_arr,
            'signature' => $signature_str,
            'body'      => $header_enc.'.'.$payload_enc,
        ];
    }

    /**
     * @param string $msg Message to be checked
     * @param string $signature Signature of the message
     * @param string $key Key to be used in verification
     * @param string $alg Algorithm used in verification
     *
     * @return bool True is payload passed validation check, false otherwise
     */
    public function verify_payload($msg, $signature, $key, $alg)
    {
        $this->reset_error();

        $alg = strtoupper($alg);
        if (empty($alg)
         || empty(self::$supported_algs[$alg])) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('JWT algorithm not supported.'));

            return false;
        }

        $hash_func = self::$supported_algs[$alg][0];
        $hash_alg = self::$supported_algs[$alg][1];

        switch ($hash_func) {
            case 'hash_hmac':
                if (!function_exists('hash_hmac')) {
                    $this->set_error(self::ERR_PARAMETERS, $this->_pt('JWT algorithm is not supported by PHP.'));

                    return false;
                }

                $hash = hash_hmac($hash_alg, $msg, $key, true);
                if (function_exists('hash_equals')) {
                    return @hash_equals($signature, $hash);
                }

                $len = min(self::_safe_strlen($signature), self::_safe_strlen($hash));
                $status = 0;
                for ($i = 0; $i < $len; $i++) {
                    $status |= (ord($signature[$i]) ^ ord($hash[$i]));
                }

                $status |= (self::_safe_strlen($signature) ^ self::_safe_strlen($hash));

                return $status === 0;
                break;

            case 'openssl':
                if (!function_exists('openssl_verify')) {
                    $this->set_error(self::ERR_PARAMETERS, $this->_pt('JWT algorithm is not supported by PHP.'));

                    return false;
                }

                $success = openssl_verify($msg, $signature, $key, $hash_alg);
                if ($success === 1) {
                    return true;
                }

                if ($success === 0) {
                    return false;
                }

                $this->set_error(self::ERR_PARAMETERS, $this->_pt('Error while validating payload.'));

                return false;
                break;
        }

        $this->set_error(self::ERR_PARAMETERS, $this->_pt('JWT unknown algorithm when verifying data.'));

        return false;
    }

    /**
     * @param array $payload Payload to be encoded
     * @param string $key Public key
     * @param bool|array $params
     *
     * @return string|bool Encoded JWT
     */
    public function encode($payload, $key, $params = false)
    {
        $this->reset_error();

        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        if (empty($params['alg'])) {
            $params['alg'] = 'RS256';
        }
        if (empty($params['key_id'])) {
            $params['key_id'] = false;
        }
        if (empty($params['header_arr']) || !is_array($params['header_arr'])) {
            $params['header_arr'] = [];
        }

        if (empty($params['alg'])
         || empty(self::$supported_algs[$params['alg']])) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('JWT algorithm not supported.'));

            return false;
        }

        $header_arr = $params['header_arr'];
        $header_arr['typ'] = 'JWT';
        $header_arr['alg'] = $params['alg'];
        if (!empty($params['key_id'])) {
            $header_arr['kid'] = $params['key_id'];
        }

        $segments = [];
        $segments[] = $this->_safe_base64_encode(@json_encode($header_arr));
        $segments[] = $this->_safe_base64_encode(@json_encode($payload));

        $signing_input = implode('.', $segments);
        if (!($signature = $this->sign_payload($signing_input, $key, $params['alg']))) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('JWT error signing data.'));
            }

            return false;
        }

        $segments[] = $this->_safe_base64_encode($signature);

        return implode('.', $segments);
    }

    public function sign_payload($msg, $key, $alg = 'RS256')
    {
        $this->reset_error();

        if (empty($alg)
         || empty(self::$supported_algs[$alg])) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('JWT algorithm not supported.'));

            return false;
        }

        $hash_func = self::$supported_algs[$alg][0];
        $hash_alg = self::$supported_algs[$alg][1];

        switch ($hash_func) {
            case 'hash_hmac':
                if (!function_exists('hash_hmac')) {
                    $this->set_error(self::ERR_PARAMETERS, $this->_pt('JWT signing algorithm is not supported by PHP.'));

                    return false;
                }

                return @hash_hmac($hash_alg, $msg, $key, true);
            case 'openssl':
                if (!function_exists('openssl_sign')) {
                    $this->set_error(self::ERR_PARAMETERS, $this->_pt('JWT signing algorithm is not supported by PHP.'));

                    return false;
                }

                $signature = '';
                if (!@openssl_sign($msg, $signature, $key, $hash_alg)) {
                    $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('JWT unable to sign data using OpenSSL.'));

                    return false;
                }

                return $signature;
        }

        $this->set_error(self::ERR_PARAMETERS, $this->_pt('JWT unknown algorithm when signing data.'));

        return false;
    }

    private function _safe_base64_decode($str)
    {
        if (empty($str)) {
            return '';
        }

        if (($remainder = strlen($str) % 4)) {
            $padlen = 4 - $remainder;
            $str .= str_repeat('=', $padlen);
        }

        $decoded_str = @base64_decode(strtr($str, '-_', '+/'));

        return $decoded_str ? $decoded_str : '';
    }

    private function _safe_base64_encode($str)
    {
        if (!is_string($str)) {
            return '';
        }

        return @str_replace('=', '', @strtr(@base64_encode($str), '+/', '-_'));
    }

    private static function _safe_strlen($str)
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($str, '8bit');
        }

        return strlen($str);
    }
}
