<?php

namespace phs\libraries;

abstract class PHS_Paginator_exporter_library extends PHS_Library
{
    const EXPORT_TO_FILE = 1, EXPORT_TO_OUTPUT = 2, EXPORT_TO_BROWSER = 3;

    // Registry used when exporting data
    private $_export_registry = array();

    // Paginator object which started export
    /** @var bool|\phs\libraries\PHS_Paginator $_paginator_obj */
    private $_paginator_obj = false;

    /**
     * Transforms record array sent from PHS_Paginator to a string which will be outputed using record_to_output method
     *
     * @param array $record_data Record data sent from PHS_Paginator to be transformed in string
     * @param array|bool $record_data Parameters (if any)
     *
     * @return mixed
     */
    abstract public function record_to_buffer( $record_data, $params = false );

    function __construct( $init_params = false )
    {
        parent::__construct();

        if( !empty( $init_params ) and is_array( $init_params ) )
            $this->export_registry( self::validate_array( $init_params, $this->default_export_registry() ) );
        else
            $this->export_registry( $this->default_export_registry() );
    }

    /**
     *  This method is called when starting export flow.
     *  Make sure export can be done, prepare files to export to, set headers if exporting to browser, etc
     *  You can override this method in child class in case you want to export to other sources or you want to change way this exports to output...
     */
    public function start_output()
    {
        if( !($export_registry = $this->export_registry()) )
            $export_registry = $this->default_export_registry();

        if( empty( $export_registry['export_to'] )
         or !self::valid_export_to( $export_registry['export_to'] ) )
            $export_registry['export_to'] = self::EXPORT_TO_BROWSER;

        if( empty( $export_registry['export_file_name'] ) )
        {
            $export_registry['export_file_name'] = 'export_file_'.date( 'Y_m_d_H_i' );
            $this->export_registry( 'export_file_name', $export_registry['export_file_name'] );
        }

        switch( $export_registry['export_to'] )
        {
            case self::EXPORT_TO_FILE:
                if( empty( $export_registry['export_file_dir'] )
                 or !($export_file_dir = rtrim( $export_registry['export_file_dir'], '/\\' ))
                 or !@is_dir( $export_file_dir )
                 or !@is_writable( $export_file_dir ) )
                {
                    $this->set_error( self::ERR_PARAMETERS, self::_t( 'No directory provided to save export data to or no rights to write in that directory.' ) );
                    $this->record_error( false, $this->get_error_message() );
                    return false;
                }

                $full_file_path = $export_file_dir.'/'.$export_registry['export_file_name'];
                if( !($fd = @fopen( $full_file_path, 'w' )) )
                {
                    $this->set_error( self::ERR_PARAMETERS, self::_t( 'Couldn\'t create export file.' ) );
                    $this->record_error( false, $this->get_error_message() );
                    return false;
                }

                $this->export_registry( array(
                    'export_full_file_path' => $full_file_path,
                    'export_fd' => $fd,
                ) );
            break;

            case self::EXPORT_TO_BROWSER:
                if( @headers_sent() )
                {
                    $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Headers already sent. Cannot send export file to browser.' ) );
                    $this->record_error( false, $this->get_error_message() );
                    return false;
                }

                if( empty( $export_registry['export_mime_type'] ) )
                    $export_registry['export_mime_type'] = '';

                @header( 'Content-Transfer-Encoding: binary' );
                @header( 'Content-Disposition: attachment; filename="' . $export_registry['export_file_name'] . '"' );
                @header( 'Expires: 0' );
                @header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
                @header( 'Pragma: public' );

                if( !empty( $export_registry['export_mime_type'] ) )
                    @header( 'Content-Type: '.$export_registry['export_mime_type'].(!empty( $export_registry['export_encoding'] )?';charset='.$export_registry['export_encoding']:'') );
            break;
        }

        if( false and $export_registry['export_encoding']
        and @function_exists( 'mb_internal_encoding' ) )
        {
            if( ($export_original_encoding = @mb_internal_encoding()) )
            {
                $this->export_registry( array(
                    'export_original_encoding' => $export_original_encoding,
                ) );
            }

            @mb_internal_encoding( $export_registry['export_encoding'] );
        }

        return true;
    }

    /**
     *  This method is called last in export flow.
     *  Flush file handler and close it, put closing tags for XML, enclose all records as an object for JSON, etc
     *  You can override this method in child class in case you want to export to other sources or you want to change way this exports to output...
     *
     * @param array $record_data Record array to be sent to output
     * @return bool
     */
    public function record_to_output( $record_data )
    {
        if( empty( $record_data ) or !is_array( $record_data )
         or !isset( $record_data['record_buffer'] ) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Bad record data to export.' ) );
            $this->record_error( $record_data, $this->get_error_message() );
            return false;
        }

        if( !($paginator_obj = $this->paginator_obj())
         or !($export_registry = $this->export_registry()) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Exporter setup failure.' ) );
            $this->record_error( $record_data, $this->get_error_message() );
            return false;
        }

        if( empty( $export_registry['export_to'] )
         or !self::valid_export_to( $export_registry['export_to'] ) )
            return true;

        switch( $export_registry['export_to'] )
        {
            case self::EXPORT_TO_FILE:
                if( empty( $export_registry['export_fd'] )
                 or !@is_resource( $export_registry['export_fd'] ) )
                {
                    $this->set_error( self::ERR_PARAMETERS, self::_t( 'Invalid file descriptor for export.' ) );
                    $this->record_error( $record_data, $this->get_error_message() );
                    return false;
                }

                @fputs( $export_registry['export_fd'], $record_data['record_buffer'] );
            break;

            case self::EXPORT_TO_OUTPUT:
            case self::EXPORT_TO_BROWSER:
                echo $record_data['record_buffer'];
            break;
        }

        return true;
    }

    /**
     *  This method is called last in export flow.
     *  Flush file handler and close it, put closing tags for XML, enclose all records as an object for JSON, etc
     *  You can override this method in child class in case you want to export to other sources or you want to change way this exports to output...
     */
    public function finish_output()
    {
        if( !($export_registry = $this->export_registry())
         or empty( $export_registry['export_to'] )
         or !self::valid_export_to( $export_registry['export_to'] ) )
            return true;

        if( false and $export_registry['export_original_encoding']
        and @function_exists( 'mb_internal_encoding' ) )
        {
            @mb_internal_encoding( $export_registry['export_original_encoding'] );
        }

        switch( $export_registry['export_to'] )
        {
            case self::EXPORT_TO_FILE:
                if( empty( $export_registry['export_fd'] )
                 or !@is_resource( $export_registry['export_fd'] ) )
                    return true;

                @fclose( $export_registry['export_fd'] );
                @fflush( $export_registry['export_fd'] );

                $this->export_registry( 'export_fd', false );
            break;

            case self::EXPORT_TO_OUTPUT:
            case self::EXPORT_TO_BROWSER:
                exit;
            break;
        }

        return true;
    }

    /**
     * By default class won't do anything on error. Override this method to log in a file or whatever is required on an error.
     *
     * @param bool|array $record_data Record which triggered the error
     * @param string $error_buf Error message
     *
     * @return bool
     */
    public function record_error( $record_data, $error_buf )
    {
        return true;
    }

    public function paginator_obj( $paginator_obj = null )
    {
        if( $paginator_obj === null )
            return $this->_paginator_obj;

        if( empty( $paginator_obj ) )
        {
            $this->_paginator_obj = false;
            return true;
        }

        if( !($paginator_obj instanceof PHS_Paginator) )
            return false;

        $this->_paginator_obj = $paginator_obj;
        return true;
    }

    public static function valid_export_to( $export_to )
    {
        if( empty( $export_to )
         or !in_array( $export_to, array( self::EXPORT_TO_FILE, self::EXPORT_TO_OUTPUT, self::EXPORT_TO_BROWSER ) ) )
            return false;

        return true;
    }

    /**
     * This defines a default export settings array. Using export_registry( $key, $val ) you can set whatever custom variables in this array.
     *
     * @return array Returns an array with default export settings...
     */
    public function default_export_registry()
    {
        return array(
            // To what encoding should we export (if false it will not do any encodings)
            'export_encoding' => false,
            // Where to export the data
            'export_to' => self::EXPORT_TO_BROWSER,
            'export_file_dir' => '',
            'export_file_name' => '',
            'export_mime_type' => '',

            // Save what encoding was originally used in script before export
            'export_original_encoding' => false,
            // File descriptor
            'export_fd' => false,
            // Full file path
            'export_full_file_path' => '',

            'start_output_params' => false,
            'finish_output_params' => false,
            'record_to_output_params' => false,
            'record_to_buffer_params' => false,
        );
    }

    public function reset_export_registry()
    {
        $this->_export_registry = $this->default_export_registry();
    }

    /**
     * Set or retrieve values from export settings array
     *
     * @param null|string $key Null to return full array or a string which is the key to set a value or array key of value to be returned
     * @param null|mixed $val Null or a value to be set for specified key
     *
     * @return array|bool|null Set or retrieve values from export settings array
     */
    public function export_registry( $key = null, $val = null )
    {
        if( $key === null and $val === null )
            return $this->_export_registry;

        if( $key !== null and $val === null )
        {
            if( is_array( $key ) )
            {
                if( empty( $key ) )
                    return $this->_export_registry;

                $this->_export_registry = self::merge_array_assoc( $this->_export_registry, $key );
                return $this->_export_registry;
            }

            if( is_string( $key ) and isset( $this->_export_registry[$key] ) )
                return $this->_export_registry[$key];

            return null;
        }

        if( is_string( $key ) and isset( $this->_export_registry[$key] ) )
        {
            $this->_export_registry[$key] = $val;
            return true;
        }

        return null;
    }
}
