<?php
namespace phs\system\core\libraries;

use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Library;

class PHS_Imap extends PHS_Library
{
    public const PORT_SSL = 993, PORT_NON_SSL = 143;

    public const STATUS_UNDEFINED = '', STATUS_OK = 'OK', STATUS_NO = 'NO', STATUS_BAD = 'BAD';

    public const DIR_INBOX = 'INBOX';

    public const COMMAND_PREFIX = 'C';

    // Default IMAP port 993 ssl, 143 non-ssl
    private array $_settings = [
        'host'    => '',
        'port'    => self::PORT_SSL,
        'ssl'     => true,
        'timeout' => 30,
    ];

    private int $line_index = 0;

    private array $last_lines = [];

    private ?string $last_line = null;

    private ?string $last_status = null;

    private ?string $last_response = null;

    private ?string $_logger = null;

    private bool $_loggedin = false;

    private $fp;

    public function fetch_all_as_string(string $uid) : ?string
    {
        if (!($lines_arr = $this->fetch($uid, 'BODY[]'))) {
            return null;
        }

        return implode("\n", $lines_arr);
    }

    public function fetch_all(string $uid) : ?array
    {
        if (!($lines_arr = $this->fetch($uid, 'BODY[]'))) {
            return null;
        }

        return $lines_arr;
    }

    public function fetch_headers(string $uid) : ?array
    {
        if (null === ($lines_arr = $this->fetch($uid, 'BODY.PEEK[HEADER]'))) {
            return null;
        }

        $headers = [];
        $h_name = null;
        $h_value = null;
        foreach ($lines_arr as $line) {
            if (trim($line) === '') {
                break;
            }

            if (@preg_match('~^([a-z][a-z0-9-_]+):~is', $line, $match)) {
                if ($h_name !== null) {
                    $this->_add_to_headers($headers, $h_name, $h_value);
                }

                $h_name = $match[1];
                $h_value = trim(substr($line, strlen($h_name) + 1));

                continue;
            }

            $h_value .= trim($line);
        }

        if ($h_name !== null) {
            $this->_add_to_headers($headers, $h_name, $h_value);
        }

        return $headers;
    }

    public function fetch(string $uid, string $what = 'ALL') : ?array
    {
        $this->reset_error();

        if (!$this->is_logged_in()) {
            $this->set_error(self::ERR_RIGHTS, self::_t('You should login to IMAP server first.'));

            return null;
        }

        if (!$this->_command('FETCH '.$uid.($what !== '' ? ' '.$what : ''))
            || !$this->is_ok_last_status()) {
            $this->set_error_if_not_set(self::ERR_FUNCTIONALITY, self::_t('Could not perform fetch.'));

            if ($this->_logger) {
                PHS_Logger::warning('Could not fetch uid '.$uid.': '.$this->get_simple_error_message(), $this->_logger);
            }

            return null;
        }

        $lines = $this->get_last_response_lines();
        $first_line = array_shift($lines);

        if (!$first_line
           || !str_starts_with($first_line, '* '.$uid.' FETCH')) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Could not fetch data for uid %s.', $uid));

            if ($this->_logger) {
                PHS_Logger::warning('Could not fetch data for uid '.$uid.': '.$this->get_simple_error_message(), $this->_logger);
            }

            return null;
        }

        if (trim($lines[count($lines) - 1] ?? '') === ')') {
            array_pop($lines);
        }

        return $lines;
    }

    public function search_new_emails() : ?array
    {
        return $this->search('UNSEEN');
    }

    public function search_since_timestamp(int $timestamp) : ?array
    {
        return $this->search('SINCE '.date('j-M-Y', $timestamp));
    }

    public function search(string $criteria) : ?array
    {
        $this->reset_error();

        if (!$this->is_logged_in()) {
            $this->set_error(self::ERR_RIGHTS, self::_t('You should login to IMAP server first.'));

            return null;
        }

        if (!$this->_command('SEARCH '.$criteria)
           || !$this->is_ok_last_status()) {
            $this->set_error_if_not_set(self::ERR_FUNCTIONALITY, self::_t('Could not perform search.'));

            if ($this->_logger) {
                PHS_Logger::warning('Could not perform search: '.$this->get_simple_error_message(), $this->_logger);
            }

            return null;
        }

        $search_results = [];
        if (($lines = $this->get_last_response_lines())
           && !empty($lines[0])
           && str_starts_with($lines[0], '* SEARCH ')) {
            $search_results = explode(' ', trim(substr($lines[0], 9)));
        }

        if ($this->_logger) {
            PHS_Logger::notice('Found '.count($search_results).' results for criteria '.$criteria.'.', $this->_logger);
        }

        return $search_results;
    }

    public function select_folder_inbox() : bool
    {
        return $this->select_folder(self::DIR_INBOX);
    }

    public function select_folder(string $folder) : bool
    {
        $this->reset_error();

        if (!$this->is_logged_in()) {
            $this->set_error(self::ERR_RIGHTS, self::_t('You should login to IMAP server first.'));

            return false;
        }

        if (!$this->_command('SELECT '.$folder)
           || !$this->is_ok_last_status()) {
            $this->set_error_if_not_set(self::ERR_FUNCTIONALITY, self::_t('Could not select folder on IMAP host.'));

            if ($this->_logger) {
                PHS_Logger::warning('Select folder error: '.$this->get_simple_error_message(), $this->_logger);
            }

            return false;
        }

        return true;
    }

    public function login(string $login, string $pwd) : bool
    {
        $this->reset_error();

        $this->_loggedin = false;

        if (!$this->_command('LOGIN '.$login.' '.$pwd)
           || !$this->is_ok_last_status()) {
            $this->set_error_if_not_set(self::ERR_FUNCTIONALITY, self::_t('Could not login to IMAP host.'));

            if ($this->_logger) {
                PHS_Logger::error('Login error: '.$this->get_simple_error_message()
                                    .' Server response: '.$this->get_last_response(), $this->_logger);
            }

            return false;
        }

        $this->_loggedin = true;

        return true;
    }

    public function logout() : bool
    {
        $this->reset_error();

        if (!$this->is_logged_in()) {
            return true;
        }

        if (!$this->_command('LOGOUT')
            || !$this->is_ok_last_status()) {
            $this->set_error_if_not_set(self::ERR_FUNCTIONALITY, self::_t('Could not perform logout.'));

            if ($this->_logger) {
                PHS_Logger::warning('Could not logout: '.$this->get_simple_error_message(), $this->_logger);
            }

            return false;
        }

        $this->_close_fp();
        $this->_loggedin = false;

        return true;
    }

    public function connect() : bool
    {
        $this->reset_error();

        if (!($host = $this->_get_settings_imap_host())
           || !($port = $this->_get_settings_imap_port())) {
            $this->set_error_if_not_set(self::ERR_SETTINGS, self::_t('Could not obtain IMAP host or port.'));

            return false;
        }

        if ($this->fp) {
            $this->_close_fp();
        }

        $timeout = $this->_get_settings_imap_timeout();

        if (!($this->fp = @fsockopen($host, $port, $errno, $errstr, $timeout))) {
            if ($this->_logger) {
                PHS_Logger::warning('Could not connect to IMAP host ['.$host.':'.$port.']: '
                                    .'('.$errno.') '.$errstr, $this->_logger);
            }

            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Could not connect to IMAP host.'));

            return false;
        }

        @stream_set_timeout($this->fp, $timeout);

        // Discard first line from response
        @fgets($this->fp);

        return true;
    }

    public function is_logged_in() : bool
    {
        return $this->_loggedin;
    }

    public function is_ok_last_status() : bool
    {
        return $this->last_status === self::STATUS_OK;
    }

    public function is_no_last_status() : bool
    {
        return $this->last_status === self::STATUS_NO;
    }

    public function is_bad_last_status() : bool
    {
        return $this->last_status === self::STATUS_BAD;
    }

    public function get_last_status() : ?string
    {
        return $this->last_status;
    }

    public function get_last_response() : ?string
    {
        return $this->last_response;
    }

    public function get_last_line() : ?string
    {
        return $this->last_line;
    }

    public function get_last_response_lines() : array
    {
        return $this->last_lines;
    }

    public function imap_settings(
        ?string $host = null,
        ?int $port = null,
        ?bool $ssl = null,
        ?int $timeout = null
    ) : bool {
        if (!$this->_validate_settings($host, $port)) {
            return false;
        }

        if ($host !== null) {
            $this->_settings['host'] = $host;
        }
        if ($port !== null && $port > 0) {
            $this->_settings['port'] = $port;
        }
        if ($ssl !== null) {
            $this->_settings['ssl'] = $ssl;
        }
        if ($timeout !== null && $timeout > 0) {
            $this->_settings['timeout'] = $timeout;
        }

        return true;
    }

    public function logger(?string $logger = null) : ?string
    {
        if ($logger === null) {
            return $this->_logger;
        }

        $this->_logger = $logger;

        return $this->_logger;
    }

    public function reset_logger() : void
    {
        $this->_logger = null;
    }

    private function _close_fp() : void
    {
        if ($this->fp) {
            @fclose($this->fp);
            $this->fp = null;
        }
    }

    private function _command(string $command) : ?string
    {
        if (!$this->fp
           && !$this->connect()) {
            return null;
        }

        $this->_reset_last_response();

        $index = $this->_get_next_command_index();

        @fwrite($this->fp, "{$index} {$command}\r\n");

        while (($line = @fgets($this->fp))) {
            if (($line_arr = @preg_split('/\s+/', $line, 0, PREG_SPLIT_NO_EMPTY))
                && strtoupper($line_arr[0]) === $index) {
                array_shift($line_arr);
                $status = array_shift($line_arr) ?: null;
                $response = $line_arr ? implode(' ', $line_arr) : null;

                $this->last_line = $line;
                $this->last_status = $status ? strtoupper($status) : self::STATUS_UNDEFINED;
                $this->last_response = $response;
                break;
            }

            $this->last_lines[] = rtrim($line, "\r\n");
        }

        return $this->last_status;
    }

    private function _reset_last_response() : void
    {
        $this->last_line = null;
        $this->last_lines = [];
        $this->last_status = self::STATUS_UNDEFINED;
        $this->last_response = null;
    }

    private function _add_to_headers(array &$headers, string $h_name, string $h_value) : void
    {
        if (isset($headers[$h_name])) {
            if (!is_array($headers[$h_name])) {
                $headers[$h_name] = [$headers[$h_name]];
            }

            $headers[$h_name][] = $h_value;

            return;
        }

        $headers[$h_name] = $h_value;
    }

    private function _get_next_command_index() : string
    {
        $this->line_index++;

        return $this->_get_command_index();
    }

    private function _get_command_index() : string
    {
        return self::COMMAND_PREFIX.$this->line_index;
    }

    private function _get_settings_imap_timeout() : int
    {
        return (int)(($this->_settings['timeout'] ?? 30) ?: 30);
    }

    private function _get_settings_imap_port() : int
    {
        return (int)(($this->_settings['port'] ?? 0) ?: 0);
    }

    private function _get_settings_imap_ssl() : bool
    {
        return (bool)($this->_settings['ssl'] ?? true);
    }

    private function _get_settings_imap_host() : ?string
    {
        $this->reset_error();

        if (!$this->_validate_settings()) {
            $this->set_error(self::ERR_SETTINGS, self::_t('Invalid settings for IMAP host.'));

            return null;
        }

        return ($this->_get_settings_imap_ssl() ? 'ssl://' : '')
               .$this->_settings['host'];
    }

    private function _validate_settings(?string $host = null, ?int $port = null) : bool
    {
        $host ??= $this->_settings['host'] ?? null;
        $port ??= $this->_settings['port'] ?? 0;

        $port = (int)$port;

        return $host && $port > 0 && $port <= 65535;
    }

    /**
     * @inheritdoc
     */
    public static function instances_as_singletons() : bool
    {
        return false;
    }
}
