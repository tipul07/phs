<?php
namespace phs\system\core\libraries;

use phs\libraries\PHS_Library;

class PHS_Mime_parser extends PHS_Library
{
    public const ERR_INPUT_BUFFER = 1;

    private array $_lines_arr = [];

    private bool $_lines_parsed = false;

    private ?string $_prev_line = null;

    private int $_full_read_filsezise_limit = 512000;

    private ?string $_filename = null;

    private mixed $_fh = null;

    private int $_li = 0;

    private array $_headers_arr = [];

    public function set_buffer(string $buffer) : void
    {
        $this->_parse_lines_from_buffer($buffer);
    }

    public function set_input_file(string $filename) : bool
    {
        $this->reset_error();
        $this->_reset_lines_arr();

        if(!@is_file($filename)
           || !@is_readable($filename)
           || !($fsize = @filesize($filename))) {
            $this->set_error(self::ERR_INPUT_BUFFER, self::_t('Provided file is not readable.'));
            return false;
        }

        $this->_filename = $filename;

        if(($limit = $this->full_read_filsezise_limit())
           && $fsize > $limit) {
            return true;
        }

        if(!($buffer = @file_get_contents($filename))) {
            $this->set_error(self::ERR_INPUT_BUFFER, self::_t('Error reading provided file.'));
            return false;
        }

        $this->_parse_lines_from_buffer($buffer);

        return true;
    }

    public function get_headers() : array
    {
        return $this->_headers_arr;
    }

    public function full_read_filsezise_limit(?int $limit = null) : int
    {
        if($limit !== null) {
            $this->_full_read_filsezise_limit = $limit;
        }

        return $this->_full_read_filsezise_limit;
    }

    public function _parse_email(): bool
    {
        $this->reset_error();

        $this->_read_headers();

        $this->_close_fh();

        return true;
    }

    private function _read_headers(): void
    {
        $this->_headers_arr = [];
        $h_key = '';
        $h_val = '';

        while(null !== ($line = $this->_get_next_line())
              && trim($line) !== '') {

            if(in_array($line[0], [' ', "\t"], true)) {
                $h_val .= ' ' . trim($line);
                continue;
            }

            if($h_key !== '') {
                $this->_headers_arr[$h_key] = trim($h_val);
                $h_key = '';
                $h_val = '';
            }

            if($h_key === '') {
                $line_parts = explode(':', $line, 2);
                $h_key = trim($line_parts[0]);
                $h_val = trim($line_parts[1] ?? '');
            }
        }

        if($h_key !== '') {
            $this->_headers_arr[$h_key] = $h_val;
        }
    }

    private function _get_next_line(): ?string
    {
        if($this->_lines_parsed) {
            if(($this->_lines_arr[$this->_li] ?? null) === null) {
                return null;
            }

            $this->_li++;

            return $this->_lines_arr[$this->_li-1];
        }

        $this->_li++;

        return $this->_get_line_from_file();
    }

    private function _get_line_from_file(): ?string
    {
        if ($this->_filename === null) {
            return null;
        }

        if (!($fh = $this->_get_fh())) {
            return null;
        }

        if(($line = @fgets($fh)) === false) {
            $this->_close_fh();
            return null;
        }

        return $line;
    }

    private function _close_fh(): void
    {
        if ($this->_fh !== null) {
            @fclose($this->_fh);
            $this->_fh = null;
        }
    }

    private function _get_fh()
    {
        if ($this->_fh !== null) {
            return $this->_fh;
        }

        if(!$this->_filename
           || !($handle = @fopen($this->_filename, 'rb'))) {
            return null;
        }

        $this->_fh = $handle;

        return $this->_fh;
    }

    private function _reset_lines_arr() : void
    {
        $this->_lines_arr = [];
        $this->_li = 0;
        $this->_prev_line = null;

        $this->_headers_arr = [];
    }

    private function _parse_lines_from_buffer(string $buffer) : void
    {
        $this->_reset_lines_arr();

        $this->_lines_arr = explode("\n", $buffer) ?: [];

        foreach ($this->_lines_arr as $knti => $line) {
            $this->_lines_arr[$knti] = trim($line, "\r");
        }

        $this->_lines_parsed = true;
    }

    public static function instances_as_singletons() : bool
    {
        return false;
    }
}
