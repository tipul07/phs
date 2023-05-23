<?php
namespace phs\plugins\emails\libraries;

use phs\libraries\PHS_Library;

/*! \file phs_smtp.php
 *  \brief Contains PHS_Smtp class (send emails trough smtp)
 *  \version 1.10
 */

class PHS_Smtp extends PHS_Library
{
    public const CLASS_VERSION = '1.20';

    // ! /descr Class was initialised succesfully
    public const ERR_CONNECT = 1, ERR_AUTHENTICATION = 2, ERR_EMAIL_DETAILS = 3, ERR_NOT_EXPECTED = 4,
    ERR_FROM = 5, ERR_TO = 6, ERR_DATA = 7, ERR_BODY = 8, ERR_NOOP = 9;

    public const EOL = "\r\n";

    public const AUTH_AUTO_DETECT = 'AUTO', AUTH_CRAM_SHA1 = 'CRAM-SHA1', AUTH_CRAM_MD5 = 'CRAM-MD5', AUTH_PLAIN = 'PLAIN', AUTH_LOGIN = 'LOGIN';

    public const ENCRYPTION_NONE = 'tcp', ENCRYPTION_SSL = 'ssl', ENCRYPTION_TLS = 'tls';

    /** @var int|resource */
    private $fd = 0;

    private int $buffer_size = 8192;

    private string $helo_word = '';

    private array $email_settings = [
        'headers'       => false,
        'to_name'       => '',
        'to_email'      => '',
        'reply_to'      => '',
        'reply_name'    => '',
        'from_name'     => '',
        'from_email'    => '',
        'subject'       => '',
        'mime_boundary' => '',
        'body_html'     => '',
        'body_txt'      => '',
        'body_full'     => '',
    ];

    private array $smtp_settings = [
        'localhost'           => '',
        'smtp_host'           => '',
        'smtp_port'           => 25,
        'smtp_user'           => '',
        'smtp_pass'           => '',
        'smtp_timeout'        => 30,
        'smtp_encryption'     => self::ENCRYPTION_NONE,
        'smtp_authentication' => self::AUTH_AUTO_DETECT,
        'smtp_resend_hello'   => false,
    ];

    private array $debug_log = [];

    private static array $AUTHENTICATION_METHODS_ARR =
        [self::AUTH_AUTO_DETECT, self::AUTH_CRAM_SHA1, self::AUTH_CRAM_MD5, self::AUTH_LOGIN, self::AUTH_PLAIN];

    private static array $ENCRYPTIONS_ARR =
        [self::ENCRYPTION_NONE, self::ENCRYPTION_SSL, self::ENCRYPTION_TLS];

    /**
     * @param false|array $params
     */
    public function __construct($params = false)
    {
        parent::__construct();

        $this->smtp_settings['localhost'] = ($_SERVER['LOCAL_ADDR'] ?? '127.0.0.1');

        if (!empty($params) && is_array($params)) {
            $this->settings($params);
        }

        $this->reset_error();
    }

    public function get_authentication_methods(): array
    {
        return self::$AUTHENTICATION_METHODS_ARR;
    }

    public function valid_authentication($method): bool
    {
        $method = strtoupper(trim($method));

        return in_array($method, self::$AUTHENTICATION_METHODS_ARR, true);
    }

    public function get_encryption_types(): array
    {
        return self::$ENCRYPTIONS_ARR;
    }

    public function valid_encryption($item): bool
    {
        $item = strtolower(trim($item));

        return in_array($item, self::$ENCRYPTIONS_ARR, true);
    }

    public function debug_log(): array
    {
        return $this->debug_log;
    }

    /**
     * @param array|null $params
     *
     * @return array|null
     */
    public function settings(?array $params = null): ?array
    {
        if ($params === null) {
            return $this->smtp_settings;
        }

        if (empty($params) || !is_array($params)) {
            return null;
        }

        foreach ($params as $key => $val) {
            if (!array_key_exists($key, $this->smtp_settings)
             || ($key === 'smtp_encryption' && !$this->valid_encryption($val))
             || ($key === 'smtp_authentication' && !$this->valid_authentication($val))) {
                continue;
            }

            $this->smtp_settings[$key] = $val;
        }

        return $this->smtp_settings;
    }

    /**
     * @param array|null $params
     *
     * @return array|null
     */
    public function email_details(?array $params = null): ?array
    {
        if ($params === null) {
            return $this->email_settings;
        }

        if (empty($params) || !is_array($params)) {
            return null;
        }

        foreach ($params as $key => $val) {
            if (!array_key_exists($key, $this->email_settings)
             || ($key === 'headers' && $val !== false && !is_array($val))) {
                continue;
            }

            $this->email_settings[$key] = $val;
        }

        return $this->email_settings;
    }

    /**
     * @param array|null $params
     *
     * @return bool
     */
    public function send(?array $params = null): bool
    {
        $this->reset_error();

        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        $this->debug_log = [];

        $params['close_after_send'] = (!isset($params['close_after_send']) || !empty($params['close_after_send']));

        if (!($email_details = $this->email_details())
         || empty($email_details['to_email']) || empty($email_details['from_email'])
         || (empty($email_details['body_html']) && empty($email_details['body_txt']) && empty($email_details['body_full']))) {
            $this->set_error(self::ERR_EMAIL_DETAILS, 'Please provide email details.');

            return false;
        }

        if (!$this->_connect()) {
            return false;
        }

        if (null === $this->_exec('MAIL FROM: <'.$email_details['from_email'].'>', '250')) {
            $this->_exec('RSET');

            if (!empty($params['close_after_send'])) {
                $this->_disconnect();
            }

            $this->set_error(self::ERR_FROM, 'Error sending FROM to server.');

            return false;
        }

        if (null === $this->_exec('RCPT TO: <'.$email_details['to_email'].'>', '250')) {
            $this->_exec('RSET');

            if (!empty($params['close_after_send'])) {
                $this->_disconnect();
            }

            $this->set_error(self::ERR_TO, 'Error sending TO to server.');

            return false;
        }

        if (null === $this->_exec('DATA', '354')) {
            $this->_exec('RSET');

            if (!empty($params['close_after_send'])) {
                $this->_disconnect();
            }

            $this->set_error(self::ERR_DATA, 'Error sending DATA to server.');

            return false;
        }

        if (empty($email_details['headers']) || !is_array($email_details['headers'])) {
            $email_details['headers'] = [];
        }

        if (empty($email_details['headers']['To'])) {
            $email_details['headers']['To'] = (!empty($email_details['to_name']) ? '"'.$email_details['to_name'].'" ' : '')
                .'<'.$email_details['to_email'].'>';
        }
        if (empty($email_details['headers']['From'])) {
            $email_details['headers']['From'] = (!empty($email_details['from_name']) ? '"'.$email_details['from_name'].'"' : '').' <'.$email_details['from_email'].'>';
        }
        if (empty($email_details['headers']['Return-Path'])) {
            $email_details['headers']['Return-Path'] = '<'.$email_details['from_email'].'>';
        }
        if (empty($email_details['headers']['Reply-To'])) {
            $email_details['headers']['Reply-To'] = (!empty($email_details['reply_name']) ? '"'.$email_details['reply_name'].'"' : '').' <'.(!empty($email_details['reply_to']) ? $email_details['reply_to'] : $email_details['from_email']).'>';
        }
        if (empty($email_details['headers']['X-Sender'])) {
            $email_details['headers']['X-Sender'] = '<'.$email_details['from_email'].'>';
        }
        if (empty($email_details['headers']['X-Mailer'])) {
            $email_details['headers']['X-Mailer'] = 'PHP (PHS_SMTP-'.self::CLASS_VERSION.')';
        }
        if (empty($email_details['headers']['MIME-Version'])) {
            $email_details['headers']['MIME-Version'] = '1.0';
        }
        if (empty($email_details['headers']['Content-Transfer-Encoding'])) {
            $email_details['headers']['Content-Transfer-Encoding'] = '7bit';
        }
        if (empty($email_details['headers']['Date'])) {
            $email_details['headers']['Date'] = date('r');
        }
        if (empty($email_details['headers']['Subject'])) {
            $email_details['headers']['Subject'] = (!empty($email_details['subject']) ? $email_details['subject'] : '(no subject)');
        }

        if (!empty($email_details['body_full'])) {
            $raw_message = $email_details['body_full'];
        } else {
            if (empty($email_details['mime_boundary'])) {
                $hash = md5(time());
                $email_details['mime_boundary'] = '==MULTIPART_BOUNDARY_'.$hash;

                $email_details['headers']['Content-Type'] = 'multipart/alternative; boundary='.chr(34).$email_details['mime_boundary'].chr(34);
            }

            if (empty($email_details['headers']['Content-Type'])) {
                $email_details['headers']['Content-Type'] = 'multipart/alternative; boundary='.chr(34).$email_details['mime_boundary'].chr(34);
            }

            if (empty($email_details['body_txt'])) {
                $email_details['body_txt'] = 'This email doesn\'t contain a plain text version. Please check HTML version.';
            }
            if (empty($email_details['body_html'])) {
                $email_details['body_html'] = 'This email doesn\'t contain a HTML version. Please check plain text version.';
            }

            $raw_message = 'This is a multi-part message in MIME format.'.self::EOL.self::EOL
                .'--'.$email_details['mime_boundary'].self::EOL
                .'Content-Type: text/plain; charset=UTF-8'.self::EOL
                .'Content-Transfer-Encoding: 7bit'.self::EOL.self::EOL
                .preg_replace('/^\.$/imsSU', '..', $email_details['body_txt']).self::EOL.self::EOL
                .'--'.$email_details['mime_boundary'].self::EOL
                .'Content-Type: text/html; charset=UTF-8'.self::EOL
                .'Content-Transfer-Encoding: 7bit'.self::EOL.self::EOL
                .$email_details['body_html'].self::EOL.self::EOL
                .'--'.$email_details['mime_boundary'].'--'.self::EOL;
        }

        $headers_str = '';
        if (!empty($email_details['headers']) && is_array($email_details['headers'])) {
            foreach ($email_details['headers'] as $key => $value) {
                $headers_str .= $key.': '.$value.self::EOL;
            }
            $headers_str .= self::EOL;
        }

        $raw_message = $headers_str.$raw_message.self::EOL.'.';
        if (null === $this->_exec($raw_message, '250')) {
            $this->_exec('RSET');

            if (!empty($params['close_after_send'])) {
                $this->_disconnect();
            }

            $this->set_error(self::ERR_BODY, 'Error sending BODY to server.');

            return false;
        }

        if (null === $this->_exec('NOOP', '250')) {
            if (!empty($params['close_after_send'])) {
                $this->_disconnect();
            }

            $this->set_error(self::ERR_NOOP, 'Error sending NOOP to server.');

            return false;
        }

        if (!empty($params['close_after_send'])) {
            $this->_disconnect();
        }

        return true;
    }

    public function is_connected(): bool
    {
        return !empty($this->fd);
    }

    protected function _read(): string
    {
        if (!$this->is_connected()) {
            return '';
        }

        $response = '';
        while (($chunk = @fread($this->fd, $this->buffer_size))) {
            $response .= $chunk;
            if (@feof($this->fd) || preg_match('/^\d{3}[^-]/mSU', trim($chunk))) {
                break;
            }
        }

        return trim($response);
    }

    protected function _write($cmd): bool
    {
        if (!$this->is_connected()) {
            return false;
        }

        return (bool)@fwrite($this->fd, $cmd.self::EOL);
    }

    protected function _add_debug_log( $cmd, $response ): void
    {
        $this->debug_log[] = [
            'cmd' => $cmd,
            'response' => $response,
        ];
    }

    /**
     * @param string $cmd
     * @param string|null $expected
     *
     * @return null|string
     */
    protected function _exec(string $cmd, ?string $expected = null): ?string
    {
        if (!$this->_write($cmd)) {
            return null;
        }

        $response = $this->_read();

        if ($expected !== null
            && (!$response || !preg_match('/^'.$expected.'/S', $response))) {
            $this->_add_debug_log( $cmd, 'Expected ['.$expected.'], got ['.($response ?? 'N/A').']' );
            $this->set_error(self::ERR_NOT_EXPECTED, 'Expected ['.$expected.'], got ['.($response ?? 'N/A').']');

            return null;
        }

        $this->_add_debug_log( $cmd, $response );

        return $response;
    }

    /**
     * @param string $response
     * @param array|null $smtp_settings
     *
     * @return bool
     */
    protected function _authenticate(string $response, ?array $smtp_settings = null): bool
    {
        if (!$this->is_connected()) {
            return false;
        }

        if ($smtp_settings === false) {
            $smtp_settings = $this->settings();
        }

        if (empty($smtp_settings['smtp_user'])) {
            return true;
        }

        if ($smtp_settings['smtp_authentication'] === self::AUTH_AUTO_DETECT) {
            $detected_auth_method = false;
            if (preg_match('/^250\-?AUTH.*\b('.self::AUTH_CRAM_SHA1.')(?=\b|$)/mSU', $response)) {
                $detected_auth_method = self::AUTH_CRAM_SHA1;
            } elseif (preg_match('/^250\-?AUTH.*\b('.self::AUTH_CRAM_MD5.')(?=\b|$)/mSU', $response)) {
                $detected_auth_method = self::AUTH_CRAM_MD5;
            } elseif (preg_match('/^250\-?AUTH.*\b('.self::AUTH_LOGIN.')(?=\b|$)/mSU', $response)) {
                $detected_auth_method = self::AUTH_LOGIN;
            } elseif (preg_match('/^250\-?AUTH.*\b('.self::AUTH_PLAIN.')(?=\b|$)/mSU', $response)) {
                $detected_auth_method = self::AUTH_PLAIN;
            }

            if (!empty($detected_auth_method)) {
                $this->settings(['smtp_authentication' => $detected_auth_method]);
                $smtp_settings['smtp_authentication'] = $detected_auth_method;
            }
        }

        switch ($smtp_settings['smtp_authentication']) {
            case self::AUTH_CRAM_SHA1:
            case self::AUTH_CRAM_MD5:
                if (null === ($auth_request = $this->_exec('AUTH '.$smtp_settings['smtp_authentication'], '334'))) {
                    return false;
                }

                $short_auth_string = preg_replace('/^cram\-/', '', strtolower($smtp_settings['smtp_authentication']));
                if (null === $this->_exec(
                    base64_encode($smtp_settings['smtp_user'].' '.hash_hmac($short_auth_string, base64_decode(preg_replace('/^334 /', '', trim($auth_request))), $smtp_settings['smtp_pass'])),
                    235
                    )) {
                    return false;
                }
                break;

            case self::AUTH_LOGIN:
                if (null === $this->_exec('AUTH '.self::AUTH_LOGIN, '334')
                 || null === $this->_exec(base64_encode($smtp_settings['smtp_user']), '334')
                 || null === $this->_exec(base64_encode($smtp_settings['smtp_pass']), '235')) {
                    return false;
                }
                break;

            case self::AUTH_PLAIN:
                if (null === $this->_exec('AUTH '.self::AUTH_PLAIN.' '.base64_encode("\0".$smtp_settings['smtp_user']."\0".$smtp_settings['smtp_pass']), '235')) {
                    return false;
                }
                break;

            case self::AUTH_AUTO_DETECT:
                $auth_success = false;
                foreach (self::$AUTHENTICATION_METHODS_ARR as $auth) {
                    if ($auth === self::AUTH_AUTO_DETECT) {
                        continue;
                    }

                    $old_auth = $smtp_settings['smtp_authentication'];
                    $smtp_settings['smtp_authentication'] = $auth;
                    if ($this->_authenticate($response, $smtp_settings)) {
                        $smtp_settings['smtp_authentication'] = $old_auth;
                        $auth_success = true;
                        break;
                    }
                    $smtp_settings['smtp_authentication'] = $old_auth;
                }

                return $auth_success;

            default:
                return false;
        }

        return true;
    }

    protected function _connect(): bool
    {
        if ($this->is_connected()) {
            return true;
        }

        $smtp_settings = $this->settings();
        if (empty($smtp_settings['smtp_host']) || empty($smtp_settings['smtp_port'])) {
            $this->set_error(self::ERR_CONNECT, 'Check SMTP settings');

            return false;
        }

        $stream_url = '';
        if ($smtp_settings['smtp_encryption'] === self::ENCRYPTION_SSL) {
            $stream_url .= 'ssl';
        } else {
            $stream_url .= 'tcp';
        }

        $stream_url .= '://'.$smtp_settings['smtp_host'].':'.$smtp_settings['smtp_port'];

        if (!($this->fd = @stream_socket_client($stream_url, $errno, $errstr, $smtp_settings['smtp_timeout'], STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT))) {
            $this->fd = 0;
            $this->set_error(self::ERR_CONNECT, 'Failed connecting to SMTP server');

            return false;
        }

        @stream_set_blocking($this->fd, true);

        $response = trim($this->_read());

        $this->_add_debug_log( '', $response );

        $this->helo_word = ((stripos($response, 'ESMTP') !== false) ? 'EHLO' : 'HELO');
        if (!($response = $this->_exec($this->helo_word.' '.$smtp_settings['localhost'], '250'))) {
            $this->_disconnect();
            $this->set_error(self::ERR_CONNECT, 'Failed connecting to SMTP server');

            return false;
        }

        if ($smtp_settings['smtp_encryption'] === self::ENCRYPTION_TLS) {
            $this->_exec('STARTTLS', '220');
            if (!defined('STREAM_CRYPTO_METHOD_TLS_CLIENT')
             || !@stream_socket_enable_crypto($this->fd, true, constant('STREAM_CRYPTO_METHOD_TLS_CLIENT'))) {
                $this->_disconnect();
                $this->set_error(self::ERR_CONNECT, 'Unexpected TLS encryption error!');

                return false;
            }
        }

        if (!empty($smtp_settings['smtp_resend_hello'])
            && !$this->_exec($this->helo_word.' '.$smtp_settings['localhost'], '250')) {
            $this->_disconnect();
            $this->set_error(self::ERR_CONNECT, 'Failed connecting to SMTP server');

            return false;
        }

        $response = trim($response);

        return $this->_authenticate($response);
    }

    private function _disconnect(): void
    {
        if ($this->is_connected()) {
            $this->_exec('QUIT');
            if( $this->fd ) {
                @fclose($this->fd);
            }
            $this->fd = 0;
            $this->helo_word = '';
        }
    }
}
