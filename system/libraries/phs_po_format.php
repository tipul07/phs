<?php

namespace phs\libraries;

class PHS_po_format extends PHS_Registry
{
    const ERR_PO_FILE = 1, ERR_INPUT_BUFFER = 2;

    private $can_use_generator = false;

    private $filename = '';

    private $lines_arr = array();
    private $_li = 0;

    private $header_lines = 0;
    private $header_arr = array();

    public function set_filename( $f )
    {
        $this->reset_error();

        if( empty( $f )
         or !@file_exists( $f )
         or !@is_readable( $f ) )
        {
            $this->set_error( self::ERR_PO_FILE, self::_t( 'PO file not found or not readable.' ) );
            return false;
        }

        if( version_compare( PHP_VERSION, '5.5.0', '>' ) )
            $this->can_use_generator = true;
        else
            $this->can_use_generator = false;

        if( !($buf = @file_get_contents( $f )) )
        {
            $this->set_error( self::ERR_PO_FILE, self::_t( 'Couldn\'t read PO file or it is empty.' ) );
            return false;
        }

        $this->filename = $f;

        return $this->set_buffer( $buf );
    }

    public function set_buffer( $b )
    {
        $this->reset_lines_arr();

        if( !$this->get_lines( $b ) )
            return false;

        return true;
    }

    public function extract_po_translation()
    {
        if( empty( $this->header_arr ) )
            $this->extract_header();

        return $this->extract_po_unit();
    }

    public function get_po_header()
    {
        if( empty( $this->header_arr ) )
            $this->extract_header();

        return array(
            'header_lines' => $this->header_lines,
            'header_arr' => $this->header_arr,
        );
    }

    private function extract_po_unit()
    {
        // read empty lines till first translation unit
        do
        {
            if( ($line_str = $this->get_line( $this->_li )) === false )
                break;

            $this->_li++;

        } while( $line_str === '' );

        if( $line_str === false )
            return false;

        $unit_arr = array();
        $unit_arr['comment'] = '';
        $unit_arr['files'] = array();
        $unit_arr['index'] = '';
        $unit_arr['translation'] = '';

        do
        {
            if( substr( $line_str, 0, 2 ) == '# ' )
            {
                // comment
                $unit_arr['comment'] = substr( $line_str, 2 );
            } elseif( substr( $line_str, 0, 3 ) == '#: ' )
            {
                // file...
                // POEdit (or PO format) has errors if file name contains spaces or :
                if( ($file_str = substr( $line_str, 3 ))
                and ($parts_arr = explode( ':', $file_str )) )
                {
                    $file = null;
                    $line = null;
                    foreach( $parts_arr as $file_data )
                    {
                        $file_line = explode( ' ', $file_data );

                        if( $file === null )
                        {
                            $file = trim( $file_line[0] );
                            continue;
                        }

                        $line = intval( $file_line[0] );

                        if( $file !== false and $line !== false )
                        {
                            $unit_arr['files'][] = array(
                                'file' => $file,
                                'line' => $line,
                            );

                            $file = false;
                            $line = false;
                        }

                        if( !empty( $file_line[1] ) )
                            $file = trim( $file_line[1] );
                    }
                }
            } elseif( substr( $line_str, 0, 6 ) == 'msgid ' )
            {
                $msgid = trim( substr( $line_str, 6 ), '"' );
                while( ($next_line_str = $this->get_line( $this->_li )) )
                {
                    if( substr( $next_line_str, 0, 1 ) != '"' )
                        break;

                    $msgid .= trim( $next_line_str, '"' );
                    $this->_li++;
                }

                $unit_arr['index'] = $msgid;
            } elseif( substr( $line_str, 0, 7 ) == 'msgstr ' )
            {
                $msgstr = trim( substr( $line_str, 7 ), '"' );
                while( ($next_line_str = $this->get_line( $this->_li )) )
                {
                    if( substr( $next_line_str, 0, 1 ) != '"' )
                        break;

                    $msgstr .= trim( $next_line_str, '"' );
                    $this->_li++;
                }

                $unit_arr['translation'] = $msgstr;
            }

            $line_str = $this->get_line( $this->_li );
            $this->_li++;
        } while( $line_str );

        return $unit_arr;
    }

    private function reset_lines_arr()
    {
        $this->lines_arr = array();
        $this->_li = 0;

        $this->header_arr = array();
        $this->header_lines = 0;
    }

    private function get_lines( $buffer = false )
    {
        if( $buffer === false )
            return $this->lines_arr;

        $this->reset_error();

        if( !is_string( $buffer ) )
        {
            $this->set_error( self::ERR_INPUT_BUFFER, self::_t( 'Invalid input buffer.' ) );
            return false;
        }

        $this->reset_lines_arr();
        if( !($this->lines_arr = explode( "\n", $buffer )) )
        {
            $this->lines_arr = array();
            return false;
        }

        return true;
    }

    /**
     * @param int $index Line index if we use full buffer of po file. If we use generators function will yield next line in file
     * @return bool|string
     */
    private function get_line( $index )
    {
        if( !isset( $this->lines_arr[$index] ) )
            return false;

        return $this->lines_arr[$index];
    }

    private function extract_header()
    {
        $this->_li = 0;
        $this->header_lines = 0;

        while( ($line_str = $this->get_line( $this->_li )) )
        {
            $line_str = trim( $line_str );

            $this->_li++;

            if( substr( $line_str, 0, 5 ) == 'msgid'
             or substr( $line_str, 0, 6 ) == 'msgstr' )
                continue;

            if( $line_str === ''
             or substr( $line_str, 0, 2 ) == '#:'
             or substr( $line_str, 0, 1 ) != '"' )
                break;

            $line_str = trim( $line_str, '"' );

            while( substr( $line_str, -2 ) != '\\n' )
            {
                if( ($next_line = $this->get_line( $this->_li )) === false
                 or $next_line == '' )
                    break 2;

                $this->_li++;

                $next_line = trim( $next_line, '"' );

                $line_str .= $next_line;
            }

            if( !($header_vals = explode( ':', $line_str, 2 ))
             or empty( $header_vals[0] ) or empty( $header_vals[1] ) )
                continue;

            $header_val = trim( $header_vals[1] );
                $header_val = substr( $header_val, 0, -2 );

            $this->header_arr[trim($header_vals[0])] = $header_val;
        }

        $this->header_lines = $this->_li;
    }
}
