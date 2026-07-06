<?php
namespace phs\system\core\libraries;

use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Library;

class PHS_Imap extends PHS_Library
{
    public const PORT_SSL = 993, PORT_NON_SSL = 143;

    public const STATUS_UNDEFINED = '', STATUS_OK = 'OK', STATUS_NO = 'NO', STATUS_BAD = 'BAD';

    public const COMMAND_PREFIX = 'C';

    // Default IMAP port 993 ssl, 143 non-ssl
    private array $_settings = [
        'host' => '',
        'port' => self::PORT_SSL,
        'ssl' => true,
        'timeout' => 30,
    ];

    private int $line_index = 0;

    private array $last_lines = [];
    private ?string $last_line = null;
    private ?string $last_status = null;
    private ?string $last_response = null;

    private ?string $_logger = null;

    private bool $_loggedin = false;

    private $fp = null;

    /**
     * @inheritdoc
     */
    public static function instances_as_singletons() : bool
    {
        return false;
    }

    public function select_folder(string $folder): bool
    {
        $this->reset_error();

        if(!$this->is_logged_in()) {
            $this->set_error(self::ERR_RIGHTS, self::_t('You should login to IMAP server first.'));
            return false;
        }

        if(!$this->_command('SELECT '.$folder)
           || !$this->is_ok_last_status()) {
            $this->set_error_if_not_set(self::ERR_FUNCTIONALITY, self::_t('Could not select folder on IMAP host.'));

            if($this->_logger) {
                PHS_Logger::warning('Could not select folder on IMAP host: '.$this->get_simple_error_message(), $this->_logger);
            }

            return false;
        }

        return true;
    }

    public function login(string $login, string $pwd): bool
    {
        $this->reset_error();

        if(!$this->_command('LOGIN '.$login.' '.$pwd)
           || !$this->is_ok_last_status()) {
            $this->set_error_if_not_set(self::ERR_FUNCTIONALITY, self::_t('Could not login to IMAP host.'));

            if($this->_logger) {
                PHS_Logger::warning('Login error: '.$this->get_simple_error_message()
                                    .' Server response: '.$this->get_last_response(), $this->_logger);
            }

            return false;
        }

        $this->_loggedin = true;

        return true;
    }

    public function logout(): void
    {
        $this->close();
        $this->_loggedin = false;
    }

    public function is_logged_in(): bool
    {
        return $this->_loggedin;
    }

    public function connect(): bool
    {
        $this->reset_error();

        if(!($host = $this->_get_imap_host())
           || !($port = $this->_get_imap_port())) {
            $this->set_error_if_not_set(self::ERR_SETTINGS, self::_t('Could not obtain IMAP host.'));
            return false;
        }

        if($this->fp) {
            $this->close();
        }

        $timeout = $this->_settings['timeout'] ?? 30;

        if (!($this->fp = @fsockopen($host, $port, $errno, $errstr, $timeout))) {
            if($this->_logger) {
                PHS_Logger::warning('Could not connect to IMAP host ['.$host.':'.$port.']: '.
                                    '('.$errno.') '.$errstr, $this->_logger);
            }

            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Could not connect to IMAP host'));
            return false;
        }

        @stream_set_timeout($this->fp, $timeout);

        // Discard first line from response
        @fgets($this->fp);

        return true;
    }

    public function close(): void
    {
        if($this->fp) {
            @fclose($this->fp);
            $this->fp = null;
        }
    }

    private function _command(string $command): ?string
    {
        if(!$this->fp
           && !$this->connect()) {
            return null;
        }

        $this->_reset_last_response();

        $index = $this->_get_next_command_index();

        @fwrite($this->fp, "$index $command\r\n");

        PHS_Logger::notice("Sending [$index $command\r\n]", $this->_logger);

        while (($line = @fgets($this->fp))) {
            PHS_Logger::notice("Line [$line]", $this->_logger);

            $line = trim($line);
            if (($line_arr = @preg_split('/\s+/', $line, 0, PREG_SPLIT_NO_EMPTY))
                && strtoupper($line_arr[0]) === $index) {
                array_shift($line_arr);
                $status = array_shift($line_arr) ?: null;
                $response = $line_arr ? implode(' ', $line_arr) : null;

                $this->last_line = implode(' ', $line_arr);
                $this->last_status = $status ? strtoupper($status) : self::STATUS_UNDEFINED;
                $this->last_response = $response;
                break;
            }

            $this->last_lines[] = $line;
        }

        return $this->last_status;
    }

    public function is_ok_last_status(): bool
    {
        return $this->last_status === self::STATUS_OK;
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

    private function _reset_last_response(): void
    {
        $this->last_line = null;
        $this->last_lines = [];
        $this->last_status = null;
        $this->last_response = null;
    }

    private function _get_next_command_index(): string
    {
        $this->line_index++;
        return $this->_get_command_index();
    }

    private function _get_command_index(): string
    {
        return self::COMMAND_PREFIX.$this->line_index;
    }

    public function imap_settings(
        ?string $host = null,
        ?int $port = null,
        ?bool $ssl = null,
        ?int $timeout = null
    ): bool
    {
        if(!$this->_validate_settings($host, $port)){
            return false;
        }

        if($host !== null){
            $this->_settings['host'] = $host;
        }
        if($port !== null && $port > 0){
            $this->_settings['port'] = $port;
        }
        if($ssl !== null){
            $this->_settings['ssl'] = $ssl;
        }
        if($timeout !== null && $timeout > 0){
            $this->_settings['timeout'] = $timeout;
        }

        return true;
    }

    public function logger(?string $logger = null): ?string
    {
        if($logger === null){
            return $this->_logger;
        }

        $this->_logger = $logger;

        return $this->_logger;
    }

    public function reset_logger(): void
    {
        $this->_logger = null;
    }

    private function _get_imap_port(): int
    {
        return (int)(($this->_settings['port'] ?? 0) ?: 0);
    }

    private function _get_imap_host(): ?string
    {
        $this->reset_error();

        if(!$this->_validate_settings()) {
            $this->set_error(self::ERR_SETTINGS, self::_t('Invalid settings for IMAP host.'));
            return null;
        }

        return ($this->_settings['ssl'] ? 'ssl://' : '')
               .$this->_settings['host'];

    }

    private function _validate_settings(?string $host = null, ?int $port = null): bool
    {
        $host ??= $this->_settings['host'] ?? null;
        $port ??= $this->_settings['port'] ?? 0;

        $port = (int)$port;

        return $host && $port > 0 && $port <= 65535;
    }
}
