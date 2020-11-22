<?php

namespace phs\libraries;

class PHS_File_upload extends PHS_Registry
{
    //! /descr Class was initialised successfully
    const ERR_OK = 0;
    //! Error related to parameters sent to method
    const ERR_PARAMS = 1;
    //! Source not found
    const ERR_NO_SOURCE = 2;
    //! Error related to destination file
    const ERR_NO_DESTINATION = 3;
    //! Error with destination directory
    const ERR_DESTINATION_DIR = 4;
    //! Source is bigger than limit
    const ERR_TOO_BIG = 5;
    //! Source has wrong extension
    const ERR_NO_EXTENSION = 6;
    //! Destination file exists
    const ERR_DESTINATION_EXISTS = 7;
    //! Unknown/System error occurred
    const ERR_ERROR = 8;
    //
    const ERR_TMP_FILE = 9;
    //! Writing destination failed
    const ERR_NO_WRITE = 10;
    //! Error changing source
    const ERR_CHANGE_SOURCE = 11;
    //! Error changing destination
    const ERR_CHANGE_DESTINATION = 12;

    // source details
    /** @var array $source_config */
    private $source_config;
    // destination details
    /** @var array $destination_config */
    private $destination_config;
    //! Details related to copy result
    /** @var array $copy_result */
    private $copy_result;

    /**
     * PHS_File_upload constructor.
     *
     * @param bool|array $params
     */
    public function __construct( $params = false )
    {
        parent::__construct();

        $this->_reset_settings();

        if( !empty( $params ) )
        {
            if( !is_array( $params )
             or empty( $params['source'] ) or !is_array( $params['source'] ) )
            {
                $this->set_error( self::ERR_PARAMS, self::_t( 'Unknown parameters.' ) );
                return;
            }

            if( !$this->set_source( $params['source'] ) )
                return;
        }

        $this->reset_error();
    }

    /**
     * @param bool|array $extra
     *
     * @return bool|array
     */
    public function copy_url( $extra = false )
    {
        $this->reset_error();

        $this->_reset_copy_result();

        if( !($source_arr = $this->set_source()) or !is_array( $source_arr )
         or empty( $source_arr['url_file'] ) )
        {
            $this->set_error( self::ERR_NO_SOURCE, self::_t( 'Unknown source.' ) );
            $this->_update_copy_result( array(
                    'error_no' => self::ERR_NO_SOURCE,
                    'error_msg' => self::_t( 'Unknown source.' ),
                ));
            return false;
        }

        if( !($destination_arr = $this->set_destination())
         or empty( $destination_arr['local'] ) )
        {
            $this->set_error( self::ERR_NO_DESTINATION, self::_t( 'Unknown destination.' ) );
            $this->_update_copy_result( array(
                    'error_no' => self::ERR_NO_DESTINATION,
                    'error_msg' => self::_t( 'Unknown destination.' ),
                ));
            return false;
        }

        if( !is_array( $extra ) )
            $extra = array();

        if( !isset( $extra['maxsize'] ) )
           $extra['maxsize'] = $source_arr['file_max_size'];
        if( !isset( $extra['location'] ) )
           $extra['location'] = $destination_arr['local']['path'];
        if( !isset( $extra['prefix'] ) )
           $extra['prefix'] = $destination_arr['local']['file_prefix'];
        if( !isset( $extra['custom_name'] ) )
           $extra['custom_name'] = $destination_arr['local']['file'];
        if( !isset( $extra['extensions'] ) or !is_array( $extra['extensions'] ) )
           $extra['extensions'] = $source_arr['allowed_extentions'];
        if( !isset( $extra['overwrite_destination'] ) )
           $extra['overwrite_destination'] = true;
        if( !isset( $extra['backup_filename'] ) )
           $extra['backup_filename'] = false;
        if( !isset( $extra['filename_to_lower'] ) )
           $extra['filename_to_lower'] = true;
        if( !isset( $extra['timeout'] ) )
           $extra['timeout'] = $source_arr['url_file_timeout'];
        if( empty( $extra['buffer_size'] ) or $extra['buffer_size'] < 128 )
           $extra['buffer_size'] = 1024;

        $extra['timeout'] = (int)$extra['timeout'];
        $extra['buffer_size'] = (int)$extra['buffer_size'];

        $url_info_arr = PHS_Utils::myparse_url( $source_arr['url_file'] );
        $url_file_arr = PHS_Utils::mypathinfo( $url_info_arr['path'] );

        if( empty( $url_info_arr ) or empty( $url_info_arr['scheme'] ) or empty( $url_info_arr['host'] ) )
        {
            $this->set_error( self::ERR_NO_SOURCE, self::_t( 'Unknown source file.' ) );
            $this->_update_copy_result( array(
                    'error_no' => self::ERR_NO_SOURCE,
                    'error_msg' => self::_t( 'Unknown source file.' ),
                ));
            return false;
        }

        if( !@file_exists( $extra['location'] ) )
        {
            if( !PHS_Utils::mkdir_tree( $extra['location'] ) )
            {
                $this->set_error( self::ERR_DESTINATION_DIR, self::_t( 'Cannot create directory.' ) );
                $this->_update_copy_result( array(
                        'error_no' => self::ERR_DESTINATION_DIR,
                        'error_msg' => self::_t( 'Cannot create directory.' ),
                    ));
                return false;
            }
        }

        if( !@is_writable( $extra['location'] ) )
        {
            $this->set_error( self::ERR_DESTINATION_DIR, self::_t( 'Cannot write in destination directory.' ) );
            $this->_update_copy_result( array(
                    'error_no' => self::ERR_DESTINATION_DIR,
                    'error_msg' => self::_t( 'Cannot write in destination directory.' ),
                ));
            return false;
        }

        $local_filename = $extra['custom_name'];
        if( $local_filename === '' )
            $local_filename = $url_file_arr['filename'];

        $file_name_ext = PHS_Utils::mypathinfo( $local_filename );

        if( !empty( $extra['extensions'] ) and is_array( $extra['extensions'] )
        and !in_array( strtolower( $url_file_arr['extension'] ), $extra['extensions'], true ) )
        {
            $this->set_error( self::ERR_NO_EXTENSION, self::_t( 'File extension not allowed.' ) );
            $this->_update_copy_result( array(
                    'error_no' => self::ERR_NO_EXTENSION,
                    'error_msg' => self::_t( 'File extension not allowed.' ),
                ));
            return false;
        }

        $new_name = $extra['prefix'];
        if( $file_name_ext['basename'] === '' )
            $new_name .= date( 'dmyhis' ).microtime( true );
        else
            $new_name .= $file_name_ext['basename'];

        if( $file_name_ext['extension'] !== '' )
            $file_extension = $file_name_ext['extension'];
        else
            $file_extension = $url_file_arr['extension'];

        if( !empty( $extra['filename_to_lower'] ) )
        {
            $new_name = strtolower( $new_name );
            $file_extension = strtolower( $file_extension );
        }

        $this->_update_copy_result( array(
                    'fullname' => $extra['location'].'/'.$new_name,
                    'fileextension' => $file_extension,
                    'filename' => $new_name,
                    'location' => $extra['location']
            ));

        if( !($fil_in = @fopen( $source_arr['url_file'], 'rb' )) )
        {
            $this->set_error( self::ERR_NO_SOURCE, self::_t( 'Cannot open source file.' ) );
            $this->_update_copy_result( array(
                    'error_no' => self::ERR_NO_SOURCE,
                    'error_msg' => self::_t( 'Cannot open source file.' ),
                ));
            return false;
        }

        if( @file_exists( $extra['location'].'/'.$new_name ) )
        {
            if( !empty( $extra['backup_filename'] ) )
            {
                @rename( $extra['location'].'/'.$new_name, $extra['backup_filename'] );
            } elseif( empty( $extra['overwrite_destination'] ) )
            {
                $this->set_error( self::ERR_DESTINATION_EXISTS, self::_t( 'Destination already exists.' ) );
                $this->_update_copy_result( array(
                        'error_no' => self::ERR_DESTINATION_EXISTS,
                        'error_msg' => self::_t( 'Destination already exists.' ),
                    ));
                @fclose( $fil_in );
                return false;
            }
        }

        if( !($fil_out = @fopen( $extra['location'].'/'.$new_name, 'wb' )) )
        {
            @fclose( $fil_in );

            if( !empty( $extra['backup_filename'] ) )
                @rename( $extra['backup_filename'], $extra['location'].'/'.$new_name );

            $this->set_error( self::ERR_NO_DESTINATION, self::_t( 'Cannot create destination file.' ) );
            $this->_update_copy_result( array(
                    'error_no' => self::ERR_NO_DESTINATION,
                    'error_msg' => self::_t( 'Cannot create destination file.' ),
                ));
            return false;
        }

        @stream_set_timeout( $fil_out, $extra['timeout'] );

        $got_error = false;
        while( !@feof( $fil_in ) )
        {
            if( ($buf = @fread( $fil_in, $extra['buffer_size'] )) === false
             or @fwrite( $fil_out, $buf ) === false )
            {
                $got_error = true;
                break;
            }
        }

        @fclose( $fil_in );
        @fflush( $fil_out );
        @fclose( $fil_out );

        if( $got_error )
        {
            @unlink( $extra['location'].'/'.$new_name );

            if( !empty( $extra['backup_filename'] ) )
                @rename( $extra['backup_filename'], $extra['location'].'/'.$new_name );

            $this->set_error( self::ERR_ERROR, self::_t( 'Error downloading file.' ) );
            $this->_update_copy_result( array(
                    'error_no' => self::ERR_ERROR,
                    'error_msg' => self::_t( 'Error downloading file.' ),
                ));
            return false;
        }

        if( !empty( $extra['maxsize'] )
        and @filesize( $extra['location'].'/'.$new_name ) > $extra['maxsize'] )
        {
            @unlink( $extra['location'].'/'.$new_name );

            if( !empty( $extra['backup_filename'] ) )
                @rename( $extra['backup_filename'], $extra['location'].'/'.$new_name );

            $this->set_error( self::ERR_TOO_BIG, self::_t( 'Downloaded file too big.' ) );
            $this->_update_copy_result( array(
                    'error_no' => self::ERR_TOO_BIG,
                    'error_msg' => self::_t( 'Downloaded file too big.' ),
                ));
            return false;
        }

        return $this->get_copy_result();
    }

    /**
     * @param bool|array $extra
     *
     * @return array|bool
     */
    public function copy_local( $extra = false )
    {
        $this->reset_error();

        $this->_reset_copy_result();

        if( !($source_arr = $this->set_source()) or !is_array( $source_arr )
         or empty( $source_arr['local_file'] ) )
        {
            $this->set_error( self::ERR_NO_SOURCE, self::_t( 'Unknown source.' ) );
            $this->_update_copy_result( array(
                    'error_no' => self::ERR_NO_SOURCE,
                    'error_msg' => self::_t( 'Unknown source.' ),
                ));
            return false;
        }

        if( !($destination_arr = $this->set_destination())
         or empty( $destination_arr['local'] ) )
        {
            $this->set_error( self::ERR_NO_DESTINATION, self::_t( 'Unknown destination.' ) );
            $this->_update_copy_result( array(
                    'error_no' => self::ERR_NO_DESTINATION,
                    'error_msg' => self::_t( 'Unknown destination.' ),
                ));
            return false;
        }

        if( !is_array( $extra ) )
            $extra = array();

        if( !isset( $extra['maxsize'] ) )
           $extra['maxsize'] = $source_arr['file_max_size'];
        if( !isset( $extra['location'] ) )
           $extra['location'] = $destination_arr['local']['path'];
        if( !isset( $extra['prefix'] ) )
           $extra['prefix'] = $destination_arr['local']['file_prefix'];
        if( !isset( $extra['custom_name'] ) )
           $extra['custom_name'] = $destination_arr['local']['file'];
        if( !isset( $extra['extensions'] ) or !is_array( $extra['extensions'] ) )
           $extra['extensions'] = $source_arr['allowed_extentions'];
        if( !isset( $extra['overwrite_destination'] ) )
           $extra['overwrite_destination'] = true;
        if( !isset( $extra['backup_filename'] ) )
           $extra['backup_filename'] = false;
        if( !isset( $extra['filename_to_lower'] ) )
           $extra['filename_to_lower'] = true;

        if( !empty( $extra['maxsize'] )
            and @filesize( $source_arr['local_file'] ) > $extra['maxsize'] )
        {
            $this->set_error( self::ERR_TOO_BIG, self::_t( 'Source file too big.' ) );
            $this->_update_copy_result( array(
                                        'error_no' => self::ERR_TOO_BIG,
                                        'error_msg' => self::_t( 'Source file too big.' ),
                                        ));
            return false;
        }

        $path_file_arr = PHS_Utils::mypathinfo( $source_arr['local_file'] );

        if( !@file_exists( $extra['location'] ) )
        {
            if( !PHS_Utils::mkdir_tree( $extra['location'] ) )
            {
                $this->set_error( self::ERR_DESTINATION_DIR, self::_t( 'Cannot create directory.' ) );
                $this->_update_copy_result( array(
                        'error_no' => self::ERR_DESTINATION_DIR,
                        'error_msg' => self::_t( 'Cannot create directory.' ),
                    ));
                return false;
            }
        }

        if( !@is_dir( $extra['location'] ) or !@is_writable( $extra['location'] ) )
        {
            $this->set_error( self::ERR_DESTINATION_DIR, self::_t( 'Cannot write in destination directory.' ) );
            $this->_update_copy_result( array(
                    'error_no' => self::ERR_DESTINATION_DIR,
                    'error_msg' => self::_t( 'Cannot write in destination directory.' ),
                ));
            return false;
        }

        $local_filename = $extra['custom_name'];
        if( $local_filename === '' )
            $local_filename = $path_file_arr['filename'];

        $file_name_ext = PHS_Utils::mypathinfo( $local_filename );

        if( !empty( $extra['extensions'] ) and is_array( $extra['extensions'] ) and !in_array( strtolower( $path_file_arr['extension'] ), $extra['extensions'] ) )
        {
            $this->set_error( self::ERR_NO_EXTENSION, self::_t( 'File extension not allowed.' ) );
            $this->_update_copy_result( array(
                    'error_no' => self::ERR_NO_EXTENSION,
                    'error_msg' => self::_t( 'File extension not allowed.' ),
                ));
            return false;
        }

        $new_name = $extra['prefix'];
        if( $file_name_ext['basename'] === '' )
            $new_name .= date( 'dmyhis' ).microtime( true );
        else
            $new_name .= $file_name_ext['basename'];

        if( $file_name_ext['extension'] !== '' )
            $file_extension = $file_name_ext['extension'];
        else
            $file_extension = $path_file_arr['extension'];

        $new_name .= '.'.$file_extension;

        if( !empty( $extra['filename_to_lower'] ) )
        {
            $new_name = strtolower( $new_name );
            $file_extension = strtolower( $file_extension );
        }

        $this->_update_copy_result( array(
                    'fullname' => $extra['location'].'/'.$new_name,
                    'fileextension' => $file_extension,
                    'filename' => $new_name,
                    'location' => $extra['location']
            ));

        if( @file_exists( $extra['location'].'/'.$new_name ) )
        {
            if( !empty( $extra['backup_filename'] ) )
            {
                @rename( $extra['location'].'/'.$new_name, $extra['backup_filename'] );
            } elseif( empty( $extra['overwrite_destination'] ) )
            {
                $this->set_error( self::ERR_DESTINATION_EXISTS, self::_t( 'Destination already exists.' ) );
                $this->_update_copy_result( array(
                        'error_no' => self::ERR_DESTINATION_EXISTS,
                        'error_msg' => self::_t( 'Destination already exists.' ),
                    ));
                return false;
            }
        }

        if( !@copy( $source_arr['local_file'], $extra['location'].'/'.$new_name ) )
        {
            @unlink( $extra['location'].'/'.$new_name );

            if( !empty( $extra['backup_filename'] ) )
                @rename( $extra['backup_filename'], $extra['location'].'/'.$new_name );

            $this->set_error( self::ERR_ERROR, self::_t( 'Error downloading file.' ) );
            $this->_update_copy_result( array(
                    'error_no' => self::ERR_ERROR,
                    'error_msg' => self::_t( 'Error downloading file.' ),
                ));
            return false;
        }

        return $this->get_copy_result();
    }

    /**
     * @param bool|array $extra
     *
     * @return bool|array
     */
    public function copy_uploaded( $extra = false )
    {
        $this->reset_error();

        $this->_reset_copy_result();

        if( !($source_arr = $this->set_source()) or !is_array( $source_arr )
         or empty( $source_arr['upload_file'] ) )
        {
            $this->set_error( self::ERR_NO_SOURCE, self::_t( 'Unknown source.' ) );
            $this->_update_copy_result( array(
                    'error_no' => self::ERR_NO_SOURCE,
                    'error_msg' => self::_t( 'Unknown source.' ),
                ));
            return false;
        }

        if( !($destination_arr = $this->set_destination())
         or empty( $destination_arr['local'] ) )
        {
            $this->set_error( self::ERR_NO_DESTINATION, self::_t( 'Unknown destination.' ) );
            $this->_update_copy_result( array(
                    'error_no' => self::ERR_NO_DESTINATION,
                    'error_msg' => self::_t( 'Unknown destination.' ),
                ));
            return false;
        }

        if( !is_array( $extra ) )
            $extra = array();

        if( !isset( $extra['maxsize'] ) )
           $extra['maxsize'] = $source_arr['file_max_size'];
        if( !isset( $extra['location'] ) )
           $extra['location'] = $destination_arr['local']['path'];
        if( !isset( $extra['prefix'] ) )
           $extra['prefix'] = $destination_arr['local']['file_prefix'];
        if( !isset( $extra['custom_name'] ) )
           $extra['custom_name'] = $destination_arr['local']['file'];
        if( !isset( $extra['extensions'] ) or !is_array( $extra['extensions'] ) )
           $extra['extensions'] = $source_arr['allowed_extentions'];
        if( !isset( $extra['overwrite_destination'] ) )
           $extra['overwrite_destination'] = true;
        if( !isset( $extra['backup_filename'] ) )
           $extra['backup_filename'] = false;
        if( !isset( $extra['filename_to_lower'] ) )
           $extra['filename_to_lower'] = true;
        if( !isset( $extra['timeout'] ) )
           $extra['timeout'] = $source_arr['url_file_timeout'];
        if( empty( $extra['buffer_size'] ) or $extra['buffer_size'] < 128 )
           $extra['buffer_size'] = 1024;

        $extra['timeout'] = (int)$extra['timeout'];
        $extra['buffer_size'] = (int)$extra['buffer_size'];

        $finfo = $source_arr['upload_file'];

        if( !is_array( $finfo ) or empty( $finfo['name'] ) or !isset( $finfo['size'] ) )
        {
            $this->set_error( self::ERR_NO_SOURCE, self::_t( 'Invalid source file provided for upload.' ) );
            $this->_update_copy_result( array(
                    'error_no' => self::ERR_NO_SOURCE,
                    'error_msg' => self::_t( 'Unknown source file.' ),
                ));
            return false;
        }

        if( empty( $finfo['size'] ) or $finfo['size'] <= 0 )
        {
            $this->set_error( self::ERR_NO_SOURCE, self::_t( 'PHP file uploading size limit might be reached.' ) );
            $this->_update_copy_result( array(
                    'error_no' => self::ERR_NO_SOURCE,
                    'error_msg' => self::_t( 'Unknown source file.' ),
                ));
            return false;
        }

        if( !@file_exists( $extra['location'] ) )
        {
            if( !PHS_Utils::mkdir_tree( $extra['location'] ) )
            {
                $this->set_error( self::ERR_DESTINATION_DIR, self::_t( 'Cannot create directory.' ) );
                $this->_update_copy_result( array(
                        'error_no' => self::ERR_DESTINATION_DIR,
                        'error_msg' => self::_t( 'Cannot create directory.' ),
                    ));
                return false;
            }
        }

        if( !@is_writable( $extra['location'] ) )
        {
            $this->set_error( self::ERR_DESTINATION_DIR, self::_t( 'Cannot write in destination directory.' ) );
            $this->_update_copy_result( array(
                    'error_no' => self::ERR_DESTINATION_DIR,
                    'error_msg' => self::_t( 'Cannot write in destination directory.' ),
                ));
            return false;
        }

        $local_filename = $extra['custom_name'];
        if( !is_string( $local_filename )
         or $local_filename === '' )
            $local_filename = $finfo['name'];

        $file_name_ext = PHS_Utils::mypathinfo( $local_filename );
        $finfo_arr = PHS_Utils::mypathinfo( $finfo['name'] );

        if( !empty( $extra['extensions'] ) and is_array( $extra['extensions'] )
        and !in_array( strtolower( $finfo_arr['extension'] ), $extra['extensions'], true ) )
        {
            $this->set_error( self::ERR_NO_EXTENSION, self::_t( 'File extension not allowed.' ) );
            $this->_update_copy_result( array(
                    'error_no' => self::ERR_NO_EXTENSION,
                    'error_msg' => self::_t( 'File extension not allowed.' ),
                ));
            return false;
        }

        if( !empty( $extra['maxsize'] )
        and $finfo['size'] > $extra['maxsize'] )
        {
            $this->set_error( self::ERR_TOO_BIG, self::_t( 'Downloaded file too big.' ) );
            $this->_update_copy_result( array(
                    'error_no' => self::ERR_TOO_BIG,
                    'error_msg' => self::_t( 'Downloaded file too big.' ),
                ));
            return false;
        }

        $new_name = $extra['prefix'];
        if( $file_name_ext['basename'] === '' )
            $new_name .= date( 'dmyhis' ).microtime( true );
        else
            $new_name .= $file_name_ext['basename'];

        if( $file_name_ext['extension'] !== '' )
            $file_extension = $file_name_ext['extension'];
        else
            $file_extension = $finfo_arr['extension'];

        $new_name .= '.'.$file_extension;

        if( !empty( $extra['filename_to_lower'] ) )
        {
            $new_name = strtolower( $new_name );
            $file_extension = strtolower( $file_extension );
        }

        $this->_update_copy_result( array(
                    'fullname' => $extra['location'].'/'.$new_name,
                    'fileextension' => $file_extension,
                    'filename' => $new_name,
                    'location' => $extra['location']
            ));

        if( @file_exists( $extra['location'].'/'.$new_name ) )
        {
            if( !empty( $extra['backup_filename'] ) )
            {
                @rename( $extra['location'].'/'.$new_name, $extra['backup_filename'] );
            } elseif( empty( $extra['overwrite_destination'] ) )
            {
                $this->set_error( self::ERR_DESTINATION_EXISTS, self::_t( 'Destination already exists.' ) );
                $this->_update_copy_result( array(
                        'error_no' => self::ERR_DESTINATION_EXISTS,
                        'error_msg' => self::_t( 'Destination already exists.' ),
                    ));
                return false;
            }
        }

        if( !@move_uploaded_file( $finfo['tmp_name'], $extra['location'].'/'.$new_name ) )
        {
            $this->set_error( self::ERR_ERROR, self::_t( 'Error downloading file.' ) );
            $this->_update_copy_result( array(
                    'error_no' => self::ERR_ERROR,
                    'error_msg' => self::_t( 'Error downloading file.' ),
                ));
            return false;
        }

        return $this->get_copy_result();
    }

    /**
     * @param string $destination_file
     * @param bool|array $extra
     *
     * @return array|bool
     */
    public function set_destination_file( $destination_file, $extra = false )
    {
        if( empty( $extra ) or !is_array( $extra ) )
            $extra = array();
        if( empty( $extra['file_prefix'] ) )
            $extra['file_prefix'] = '';
        if( empty( $extra['dir_mode'] ) )
            $extra['dir_mode'] = 0775;
        if( empty( $extra['file_mode'] ) )
            $extra['file_mode'] = 0775;

        $destination_details = PHS_Utils::mypathinfo( $destination_file );

        $destination_arr = array();
        $destination_arr['local'] = array();
        $destination_arr['local']['file'] = $destination_details['filename'];
        $destination_arr['local']['path'] = $destination_details['dirname'];
        $destination_arr['local']['file_prefix'] = $extra['file_prefix'];
        $destination_arr['local']['dir_mode'] = $extra['dir_mode'];
        $destination_arr['local']['file_mode'] = $extra['file_mode'];

        return $this->set_destination( $destination_arr );
    }

    /**
     * @param string $source_file
     * @param bool|array $extra
     *
     * @return array|bool
     */
    public function set_source_file( $source_file, $extra = false )
    {
        if( empty( $extra ) or !is_array( $extra ) )
            $extra = array();
        if( empty( $extra['local_file'] ) )
            $extra['local_file'] = '';
        if( empty( $extra['url_file'] ) )
            $extra['url_file'] = '';
        if( empty( $extra['upload_file'] ) )
            $extra['upload_file'] = '';
        if( empty( $extra['file_max_size'] ) )
            $extra['file_max_size'] = 0;
        if( empty( $extra['allowed_extentions'] ) )
            $extra['allowed_extentions'] = array();
        if( empty( $extra['denied_extentions'] ) )
            $extra['denied_extentions'] = array();

        if( !empty( $source_file ) and @file_exists( $source_file ) )
            $extra['local_file'] = $source_file;

        $source_arr = array();
        $source_arr['local_file'] = $extra['local_file'];
        $source_arr['url_file'] = $extra['url_file'];
        $source_arr['upload_file'] = $extra['upload_file'];
        $source_arr['allowed_extentions'] = $extra['allowed_extentions'];
        $source_arr['denied_extentions'] = $extra['denied_extentions'];

        return $this->set_source( $source_arr );
    }

    /**
     * @param bool|array $source
     *
     * @return array|bool
     */
    public function set_source( $source = false )
    {
        if( $source === false )
            return $this->source_config;

        $this->reset_error();

        if( !($source = self::source_valid( $source )) )
        {
            $this->set_error( self::ERR_PARAMS, self::_t( 'Invalid source settings.' ) );
            return false;
        }

        if( ($source_settings = self::validate_source( $source )) === false )
            return false;

        $this->_reset_source_settings();

        $this->source_config = $source_settings;

        return true;
    }

    /**
     * @param bool|array $destination
     *
     * @return array|bool
     */
    public function set_destination( $destination = false )
    {
        if( $destination === false )
            return $this->destination_config;

        $this->reset_error();

        if( !($destination = self::destination_valid( $destination )) )
        {
            $this->set_error( self::ERR_PARAMS, self::_t( 'Invalid destination settings.' ) );
            return false;
        }

        if( ($destination_settings = self::validate_destination( $destination )) === false )
            return false;

        $this->_reset_destination_settings();

        $this->destination_config = $destination_settings;

        return true;
    }

    public static function source_valid( $source )
    {
        if( empty( $source ) or !is_array( $source )
         or (empty( $source['local_file'] ) and empty( $source['upload_file'] ) and empty( $source['url_file'] ))

         // valiate local files...
         //or (!empty( $source['local_file'] ) and (!@is_file( $source['local_file'] ) or !@is_readable( $source['local_file'] )))

          )
            return false;

        return $source;
    }

    public static function destination_valid( $destination )
    {
        if( empty( $destination ) or !is_array( $destination )
         or empty( $destination['local'] ) or !is_array( $destination['local'] )

         or empty( $destination['local']['file'] )

          )
            return false;

        return $destination;
    }

    public static function default_copy_result()
    {
        $default_result = array();
        $default_result['version'] = 1;
        $default_result['fullname'] = '';
        $default_result['filename'] = '';
        $default_result['fileextension'] = '';
        $default_result['location'] = '';
        $default_result['error_no'] = self::ERR_OK;
        $default_result['error_msg'] = '';

        return $default_result;
    }

    public static function default_source_settings()
    {
        $default_source = array();
        $default_source['version'] = 1;
        $default_source['upload_file'] = '';
        $default_source['local_file'] = '';
        $default_source['url_file'] = '';
        $default_source['url_file_timeout'] = 30;
        $default_source['file_max_size'] = 0;
        $default_source['allowed_extentions'] = array();
        $default_source['denied_extentions'] = array();

        return $default_source;
    }

    public static function default_destination_settings()
    {
        $default_destination = array();
        $default_destination['version'] = 1;
        $default_destination['local'] = array();
        $default_destination['local']['file'] = '';
        $default_destination['local']['path'] = '';
        $default_destination['local']['path_arr'] = array();
        $default_destination['local']['full_file'] = '';
        $default_destination['local']['file_prefix'] = '';
        $default_destination['local']['dir_mode'] = 0775; // 0775 is octal value, not decimal
        $default_destination['local']['file_mode'] = 0775; // 0775 is octal value, not decimal

        return $default_destination;
    }

    /**
     * @param array $params
     *
     * @return array
     */
    public static function validate_source( $params )
    {
        $def_source = self::default_source_settings();
        if( empty( $params ) or !is_array( $params ) )
            return $def_source;

        if( empty( $params['version'] ) )
            $params['version'] = $def_source['version'];
        else
            $params['version'] = (int)$params['version'];

        if( empty( $params['upload_file'] ) )
            $params['upload_file'] = $def_source['upload_file'];

        if( empty( $params['local_file'] ) )
            $params['local_file'] = $def_source['local_file'];

        if( empty( $params['url_file'] ) )
            $params['url_file'] = $def_source['url_file'];

        if( empty( $params['url_file_timeout'] ) )
            $params['url_file_timeout'] = $def_source['url_file_timeout'];
        else
            $params['url_file_timeout'] = (int)$params['url_file_timeout'];

        if( empty( $params['file_max_size'] ) )
            $params['file_max_size'] = $def_source['file_max_size'];
        else
            $params['file_max_size'] = (int)$params['file_max_size'];

        if( empty( $params['allowed_extentions'] ) or !is_array( $params['allowed_extentions'] ) )
            $params['allowed_extentions'] = $def_source['allowed_extentions'];

        if( empty( $params['denied_extentions'] ) or !is_array( $params['denied_extentions'] ) )
            $params['denied_extentions'] = $def_source['denied_extentions'];

        if( isset( $params['allowed_extentions'] ) )
        {
            $valid_exts_arr = array();
            if( !empty( $params['allowed_extentions'] ) and is_array( $params['allowed_extentions'] ) )
            {
                foreach( $params['allowed_extentions'] as $ext )
                {
                    $ext = strtolower( trim( str_replace( array( '/', '.', '\\', ':' ), '', $ext ) ) );
                    if( $ext === '' )
                        continue;

                    $valid_exts_arr[] = $ext;
                }
            }

            $params['allowed_extentions'] = $valid_exts_arr;
        }

        if( isset( $params['denied_extentions'] ) )
        {
            $valid_exts_arr = array();
            if( !empty( $params['denied_extentions'] ) and is_array( $params['denied_extentions'] ) )
            {
                foreach( $params['denied_extentions'] as $ext )
                {
                    $ext = strtolower( trim( str_replace( array( '/', '.', '\\', ':' ), '', $ext ) ) );
                    if( $ext === '' )
                        continue;

                    $valid_exts_arr[] = $ext;
                }
            }

            $params['denied_extentions'] = $valid_exts_arr;
        }

        return $params;
    }

    /**
     * @param array $params
     *
     * @return array
     */
    public static function validate_destination( $params )
    {
        $def_params = self::default_destination_settings();
        if( empty( $params ) or !is_array( $params ) )
            return $def_params;

        if( empty( $params['version'] ) )
            $params['version'] = $def_params['version'];
        else
            $params['version'] = (int)$params['version'];

        if( empty( $params['local'] ) )
            $params['local'] = array();

        if( empty( $params['local']['file'] ) )
            $params['local']['file'] = $def_params['local']['file'];
        else
            $params['local']['file'] = trim( $params['local']['file'] );

        if( empty( $params['local']['path'] ) )
            $params['local']['path'] = $def_params['local']['path'];
        else
            $params['local']['path'] = trim( $params['local']['path'] );

        if( substr( $params['local']['path'], -1 ) === '/' )
            $params['local']['path'] = substr( $params['local']['path'], 0, -1 );

        $params['local']['full_file'] = $params['local']['path'].'/'.$params['local']['file'];
        $params['local']['path_arr'] = PHS_Utils::mypathinfo( $params['local']['full_file'] );

        if( empty( $params['local']['file_prefix'] ) )
            $params['local']['file_prefix'] = $def_params['local']['file_prefix'];
        else
            $params['local']['file_prefix'] = trim( str_replace( array( '.', '/', '\\', "\t", "\r", "\n", ':' ), '', $params['local']['file_prefix'] ) );

        if( empty( $params['local']['dir_mode'] ) )
            $params['local']['dir_mode'] = $def_params['local']['dir_mode'];
        else
            $params['local']['dir_mode'] = (int)$params['local']['dir_mode'];

        if( empty( $params['local']['file_mode'] ) )
            $params['local']['file_mode'] = $def_params['file_mode'];
        else
            $params['local']['file_mode'] = (int)$params['local']['file_mode'];

        return $params;
    }

    /**
     * @return array
     */
    public function get_copy_result()
    {
        return $this->copy_result;
    }

    private function _reset_copy_result()
    {
        $this->copy_result = self::default_copy_result();
    }

    /**
     * @param array $details
     *
     * @return bool
     */
    private function _update_copy_result( $details )
    {
        if( !is_array( $details ) )
            return false;

        foreach( $details as $key => $val )
        {
            if( array_key_exists( $key, $this->copy_result ) )
                $this->copy_result[$key] = $val;
        }

        return true;
    }

    private function _reset_settings()
    {
        $this->_reset_source_settings();
        $this->_reset_destination_settings();
    }

    private function _reset_source_settings()
    {
        $this->source_config = self::default_source_settings();
    }

    private function _reset_destination_settings()
    {
        $this->destination_config = self::default_destination_settings();
    }

    /**
     * @param bool|array $params
     *
     * @return bool|array
     */
    public function copy( $params = false )
    {
        $this->reset_error();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !isset( $params['overwrite_destination'] ) )
            $params['overwrite_destination'] = false;
        if( !isset( $params['destination_to_source'] ) )
            $params['destination_to_source'] = true;

        if( !($source_arr = $this->set_source()) or !is_array( $source_arr ) )
        {
            $this->set_error( self::ERR_NO_SOURCE, self::_t( 'Unknown copy method.' ) );
            return false;
        }

        if( !empty( $source_arr['local_file'] ) )
            return $this->copy_local( $params );

        if( !empty( $source_arr['upload_file'] ) )
            return $this->copy_uploaded( $params );

        if( !empty( $source_arr['url_file'] ) )
            return $this->copy_url( $params );

        $this->set_error( self::ERR_NO_SOURCE, self::_t( 'Unknown copy method.' ) );
        return false;
    }

    /**
     * @param array $params
     *
     * @return bool
     */
    public function rename( $params )
    {
        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !isset( $params['overwrite_destination'] ) )
            $params['overwrite_destination'] = false;
        if( !isset( $params['destination_to_source'] ) )
            $params['destination_to_source'] = true;

        $source_arr = $this->set_source();
        $destination_arr = $this->set_destination();

        if( empty( $source_arr ) or !isset( $source_arr['local_file'] )
         or !@file_exists( $source_arr['local_file'] ) )
        {
            $this->set_error( self::ERR_NO_SOURCE, self::_t( 'Source not found' ) );
            return false;
        }
        if( empty( $destination_arr ) or empty( $destination_arr['local'] ) or $destination_arr['local']['full_file'] === '' )
        {
            $this->set_error( self::ERR_NO_DESTINATION, self::_t( 'Unknown destination' ) );
            return false;
        }

        if( empty( $params['overwrite_destination'] ) and @file_exists( $destination_arr['local']['full_file'] ) )
        {
            $this->set_error( self::ERR_DESTINATION_EXISTS, self::_t( 'Destination file already exists' ) );
            return false;
        }

        if( !@file_exists( $destination_arr['local']['path'] )
        and !PHS_Utils::mkdir_tree( $destination_arr['local']['path'], array( 'dir_mode' => $destination_arr['local']['dir_mode'] ) ) )
        {
            if( !self::st_has_error() )
                $this->set_error( self::ERR_DESTINATION_DIR, self::_t( 'Cannot create destination directory.' ) );
            else
                $this->copy_static_error( self::ERR_DESTINATION_DIR );

            return false;
        }

        if( !@rename( $source_arr['file'], $destination_arr['local']['full_file'] ) )
        {
            $this->set_error( self::ERR_ERROR, self::_t( 'Failed renaming source file.' ) );
            return false;
        }

        if( !empty( $params['destination_to_source'] ) )
        {
            if( !$this->set_source_file( $destination_arr['local']['full_file'] ) )
            {
                $this->set_error( self::ERR_CHANGE_SOURCE, self::_t( 'Failed changing source to [%s].', $destination_arr['local']['full_file'] ) );
                return false;
            }
        }

        return true;
    }
}
