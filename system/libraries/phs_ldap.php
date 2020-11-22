<?php

namespace phs\libraries;

class PHS_Ldap extends PHS_Registry
{
    //! Error related to LDAP repository
    const ERR_LDAP = 2;
    //! Error related to file outside LDAP structure
    const ERR_SOURCE = 3;
    //! Error related to file outside LDAP structure
    const ERR_DESTINATION = 4;
    //! Error related to LDAP directory
    const ERR_LDAP_DIR = 5;
    //! Error related to LDAP meta data
    const ERR_LDAP_META = 6;
    //! Error related to LDAP config file
    const ERR_LDAP_CONFIG = 7;

    // input variables
    /** @var array */
    private $server_config;

    //! (bool) true if class was provided a valid directory (not necessary writeable)
    /** @var bool */
    private $server_ready;
    //! (bool) true if there are no rights to write files in the directory, false if class can write files in directory
    /** @var bool */
    private $server_readonly;

    /**
     * PHS_Ldap constructor.
     *
     * @param bool|array $params
     */
    public function __construct( $params = false )
    {
        parent::__construct();

        $this->_reset_server_settings();

        if( !empty( $params ) )
        {
            if( !is_array( $params )
             or empty( $params['server'] ) or !is_array( $params['server'] ) )
            {
                $this->set_error( self::ERR_PARAMETERS, self::_t( 'Unkown parameters for LDAP class.' ) );
                return;
            }

            if( !$this->server_settings( $params['server'] ) )
                return;
        }

        $this->reset_error();
    }

    /**
     * @param bool|array $settings
     *
     * @return bool|array
     */
    public function server_settings( $settings = false )
    {
        if( $settings === false )
            return $this->server_config;

        $this->reset_error();

        if( !isset( $settings['ignore_config_file'] ) )
            $settings['ignore_config_file'] = false;

        if( !($settings = self::settings_valid( $settings )) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Invalid LDAP server settings.' ) );
            return false;
        }

        if( substr( $settings['root'], -1 ) !== '/' )
            $settings['root'] .= '/';

        // Read server settings from file if available
        if( empty( $settings['ignore_config_file'] )
        and @file_exists( $settings['root'].self::_ldap_config_file() ) )
        {
            if( ($file_settings_arr = self::load_ldap_settings( $settings['root'] )) !== false
            and ($file_settings_arr = self::settings_valid( $file_settings_arr )) )
            {
                $settings = $file_settings_arr;
            }
        }

        if( ($server_settings = self::validate_settings( $settings )) === false )
        {
            $this->set_error( self::ERR_LDAP_CONFIG, self::_t( 'Failed validating LDAP config.' ) );
            return false;
        }

        $this->_reset_server_settings();

        $this->server_config = $server_settings;

        if( !@is_writable( $this->server_config['root'] ) )
            $this->server_readonly = true;
        else
            $this->server_readonly = false;

        $this->server_ready = true;

        if( !$this->server_readonly )
            $this->_save_ldap_settings();

        return true;
    }

    /**
     * @param array $settings
     *
     * @return array|bool
     */
    public static function settings_valid( $settings )
    {
        if( empty( $settings ) or !is_array( $settings )
         or empty( $settings['root'] )
         or !($settings['root'] = @realpath( $settings['root'] ))
         or !@is_dir( $settings['root'] ) )
            return false;

        return $settings;
    }

    /**
     * @return array
     */
    public static function default_settings()
    {
        $default_config = array();
        $default_config['version'] = 1;
        $default_config['name'] = 'LDAP Repository';
        $default_config['root'] = '';
        $default_config['dir_length'] = 2;
        $default_config['depth'] = 4;
        $default_config['file_prefix'] = '';
        $default_config['dir_mode'] = 0775; // 0775 is octal value, not decimal
        $default_config['file_mode'] = 0775; // 0775 is octal value, not decimal
        $default_config['allowed_extentions'] = array();
        $default_config['denied_extentions'] = array();
        $default_config['config_last_save'] = 0;
        $default_config['checksum'] = '';

        return $default_config;
    }

    /**
     * @param array $settings
     *
     * @return array
     */
    public static function validate_settings( $settings )
    {
        $def_settings = self::default_settings();
        if( empty( $settings ) or !is_array( $settings ) )
            return $def_settings;

        $settings = self::validate_array( $settings, self::default_settings() );

        $settings['version'] = (int)$settings['version'];
        $settings['name'] = trim( $settings['name'] );
        $settings['root'] = trim( $settings['root'] );
        $settings['dir_length'] = (int)$settings['dir_length'];
        $settings['depth'] = (int)$settings['depth'];
        $settings['file_prefix'] = trim( $settings['file_prefix'] );
        $settings['dir_mode'] = (int)$settings['dir_mode'];
        $settings['file_mode'] = (int)$settings['file_mode'];

        if( empty( $settings['allowed_extentions'] ) or !is_array( $settings['allowed_extentions'] ) )
            $settings['allowed_extentions'] = $def_settings['allowed_extentions'];

        if( empty( $settings['denied_extentions'] ) or !is_array( $settings['denied_extentions'] ) )
            $settings['denied_extentions'] = $def_settings['denied_extentions'];

        if( substr( $settings['root'], -1 ) !== '/' )
            $settings['root'] .= '/';

        if( !empty( $settings['name'] ) )
            $settings['name'] = trim( str_replace( array( '.', '/', '\\', "\r", "\n" ), '', $settings['name'] ) );
        if( !empty( $settings['file_prefix'] ) )
            $settings['file_prefix'] = trim( str_replace(
                array( '.', '/', '\\', "\r", "\n", '~', '`', '@', '#', '$', '%', '^', '&', '*', '(', ')', '+', '=', '{', '}', '[', ']', '|', '>', '<', ';', ':', '\'', '"', '?', ',' ),
                '', $settings['file_prefix'] ) );

        if( isset( $settings['allowed_extentions'] ) )
        {
            $valid_exts_arr = array();
            if( !empty( $settings['allowed_extentions'] ) and is_array( $settings['allowed_extentions'] ) )
            {
                foreach( $settings['allowed_extentions'] as $ext )
                {
                    $ext = strtolower( trim( str_replace( array( '/', '.', '\\' ), '', $ext ) ) );
                    if( $ext === '' )
                        continue;

                    $valid_exts_arr[] = $ext;
                }
            }

            $settings['allowed_extentions'] = $valid_exts_arr;
        }

        if( isset( $settings['denied_extentions'] ) )
        {
            $valid_exts_arr = array();
            if( !empty( $settings['denied_extentions'] ) and is_array( $settings['denied_extentions'] ) )
            {
                foreach( $settings['denied_extentions'] as $ext )
                {
                    $ext = strtolower( trim( str_replace( array( '/', '.', '\\' ), '', $ext ) ) );
                    if( $ext === '' )
                        continue;

                    $valid_exts_arr[] = $ext;
                }
            }

            $settings['denied_extentions'] = $valid_exts_arr;
        }

        return $settings;
    }

    private function _reset_server_settings()
    {
        $this->server_config = self::default_settings();

        $this->server_readonly = true;
        $this->server_ready = false;
    }

    /**
     * @return bool
     */
    public function is_ready()
    {
        return (!empty( $this->server_ready )?true:false);
    }

    /**
     * @return bool
     */
    public function unlink_all()
    {
        $this->reset_error();

        if( !$this->is_ready()
         or !($settings = $this->server_settings()) or !isset( $settings['root'] )
         or !@file_exists( $settings['root'] ) or !@is_dir( $settings['root'] ) )
        {
            $this->set_error( self::ERR_LDAP, self::_t( 'LDAP repository not setup.' ) );
            return false;
        }

        return $this->_unlink_ldap_dir( $settings['root'] );
    }

    /**
     * @param string $dir
     * @param int $level
     *
     * @return bool
     */
    private function _unlink_ldap_dir( $dir, $level = 0 )
    {
        if( empty( $dir ) or !@is_dir( $dir ) )
            return false;

        if( ($files_arr = glob( $dir.'*' )) and is_array( $files_arr ) )
        {
            foreach( $files_arr as $file )
            {
                if( @is_dir( $file ) )
                    $this->_unlink_ldap_dir( $file.'/', $level+1 );
                else
                    @unlink( $file );
            }
        }

        if( $level > 0 )
            @rmdir( $dir );

        return true;
    }

    /**
     * @param array $params
     *
     * @return array|bool
     */
    public function rename( $params )
    {
        $this->reset_error();

        if( empty( $params ) or !is_array( $params )
         or empty( $params['ldap_from'] ) or empty( $params['ldap_to'] ) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Unkown parameters sent to LDAP rename.' ) );
            return false;
        }

        if( !is_array( $params['ldap_from'] ) )
            $ldap_from = $this->identifier2ldap( $params['ldap_from'] );
        else
            $ldap_from = self::validate_array( $params['ldap_from'], self::default_ldap_data() );

        if( !is_array( $ldap_from ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_SOURCE, self::_t( 'Cannot get source LDAP info.' ) );
            return false;
        }

        if( !@file_exists( $ldap_from['ldap_full_file_path'] ) )
        {
            $this->set_error( self::ERR_SOURCE, self::_t( 'LDAP resource not found.' ) );
            return false;
        }

        if( !is_array( $params['ldap_to'] ) )
        {
            $ldap_to_meta = $ldap_from['ldap_meta_arr'];
            $ldap_to_meta['ldap_id'] = $params['ldap_to'];

            $ldap_to = $this->identifier2ldap( $params['ldap_to'], false, $ldap_to_meta );
        } else
        {
            $ldap_to = self::validate_array( $params['ldap_to'], self::default_ldap_data() );

            $ldap_to['ldap_meta_arr'] = $ldap_from['ldap_meta_arr'];
            $ldap_to['ldap_meta_arr']['ldap_id'] = $ldap_to['ldap_id'];
        }

        if( !is_array( $ldap_to ) )
        {
            $this->set_error( self::ERR_DESTINATION, self::_t( 'Cannot get destination LDAP info.' ) );
            return false;
        }

        $ldap_to['ldap_meta_arr']['renamed'] = date( 'd-m-Y H:i:s' );

        if( !$this->_mkdir_tree( $ldap_to['ldap_path_segments'] ) )
            return false;

        // make sure we can save ldap_to meta file
        if( $this->update_meta( $ldap_to, array( 'ignore_read_errors' => true ) ) === false )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_LDAP_META, self::_t( 'Failed updating destination meta data.' ) );
            return false;
        }

        if( !@rename( $ldap_from['ldap_full_file_path'], $ldap_to['ldap_full_file_path'] ) )
        {
            // Delete destination meta file
            @unlink( $ldap_to['ldap_full_meta_file_path'] );

            $this->set_error( self::ERR_DESTINATION, self::_t( 'Failed renaming LDAP resource.' ) );
            return false;
        }

        // Delete old meta data
        if( @file_exists( $ldap_from['ldap_full_meta_file_path'] ) )
            @unlink( $ldap_from['ldap_full_meta_file_path'] );

        return array(
            'ldap_from' => $ldap_from,
            'ldap_to' => $ldap_to,
        );
    }

    /**
     * @param string|array $ldap_id
     *
     * @return array|bool
     */
    public function identifier_details( $ldap_id )
    {
        $this->reset_error();

        if( empty( $ldap_id ) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Unkown parameters sent to LDAP file_details.' ) );
            return false;
        }

        if( !is_array( $ldap_id ) )
            $ldap_data = $this->identifier2ldap( $ldap_id );
        else
            $ldap_data = $ldap_id;

        if( !is_array( $ldap_data ) )
        {
            $this->set_error( self::ERR_LDAP, self::_t( 'Cannot generate LDAP info.' ) );
            return false;
        }

        return $ldap_data;
    }

    /**
     * @param array $params
     *
     * @return bool
     */
    public function unlink( $params )
    {
        if( empty( $params ) or !is_array( $params )
         or empty( $params['ldap_data'] ) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Unkown parameters sent to LDAP unlink.' ) );
            return false;
        }

        if( !is_array( $params['ldap_data'] ) )
            $ldap_data = $this->identifier2ldap( $params['ldap_data'] );
        else
            $ldap_data = $params['ldap_data'];

        if( !is_array( $ldap_data )
         or empty( $ldap_data['ldap_full_file_path'] ) or empty( $ldap_data['ldap_full_meta_file_path'] ) )
        {
            $this->set_error( self::ERR_DESTINATION, self::_t( 'Cannot generate LDAP info.' ) );
            return false;
        }

        // Delete meta data
        if( @file_exists( $ldap_data['ldap_full_meta_file_path'] ) )
            @unlink( $ldap_data['ldap_full_meta_file_path'] );

        // We deleted meta data to be sure no related file is left
        if( !@file_exists( $ldap_data['ldap_full_file_path'] ) )
            return true;

        @unlink( $ldap_data['ldap_full_file_path'] );

        return true;
    }

    /**
     * @param array $params
     *
     * @return array|bool
     */
    public function add( $params )
    {
        $this->reset_error();

        if( !$this->is_ready() )
        {
            $this->set_error( self::ERR_LDAP, self::_t( 'LDAP repository not ready.' ) );
            return false;
        }

        if( empty( $params ) or !is_array( $params ) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Bad parameters sent to LDAP add.' ) );
            return false;
        }

        if( empty( $params['file'] )
         or !@file_exists( $params['file'] ) or !@is_readable( $params['file'] )
         or (!@is_file( $params['file'] ) and !@is_link( $params['file'] )) )
        {
            $this->set_error( self::ERR_SOURCE, self::_t( 'Cannot read source file [%s]', $params['file'] ) );
            return false;
        }

        $settings = $this->server_settings();

        if( !isset( $params['overwrite'] ) )
            $params['overwrite'] = false;
        if( !isset( $params['move_source'] ) )
            $params['move_source'] = false;
        if( empty( $params['ldap_data'] ) )
            $params['ldap_data'] = @basename( $params['file'] );
        if( empty( $params['extra_meta'] ) )
            $params['extra_meta'] = array();

        if( empty( $params['ldap_data'] )
         or !(is_string( $params['ldap_data'] ) or !is_array( $params['ldap_data'] )) )
        {
            $this->set_error( self::ERR_DESTINATION, self::_t( 'Cannot generate LDAP info.' ) );
            return false;
        }

        if( is_string( $params['ldap_data'] ) )
            $ldap_details = $this->identifier2ldap( $params['ldap_data'], $params['file'] );
        else
            $ldap_details = $params['ldap_data'];

        if( empty( $ldap_details ) or !is_array( $ldap_details )
         or !isset( $ldap_details['ldap_meta_arr']['file_extension'] ) )
        {
            $this->set_error( self::ERR_DESTINATION, self::_t( 'Cannot generate LDAP info.' ) );
            return false;
        }

        if( !empty( $settings['allowed_extentions'] ) and is_array( $settings['allowed_extentions'] )
        and !in_array( $ldap_details['ldap_meta_arr']['file_extension'], $settings['allowed_extentions'], true ) )
        {
            $this->set_error( self::ERR_SOURCE, self::_t( 'Extension [%s] not allowed. [Only: %s]', $ldap_details['ldap_meta_arr']['file_extension'], implode( ', ', $settings['allowed_extentions'] ) ) );
            return false;
        }

        if( !empty( $settings['denied_extentions'] ) and is_array( $settings['denied_extentions'] )
        and in_array( $ldap_details['ldap_meta_arr']['file_extension'], $settings['denied_extentions'], true ) )
        {
            $this->set_error( self::ERR_SOURCE, self::_t( 'Extension [%s] not allowed. [Denied: %s]', $ldap_details['ldap_meta_arr']['file_extension'], implode( ', ', $settings['denied_extentions'] ) ) );
            return false;
        }

        if( !$this->_mkdir_tree( $ldap_details['ldap_path_segments'] ) )
            return false;

        if( @file_exists( $ldap_details['ldap_full_file_path'] ) )
        {
            if( empty( $params['overwrite'] ) )
            {
                $this->set_error( self::ERR_DESTINATION, self::_t( 'LDAP file already exists. [%s]', $ldap_details['ldap_file_name'] ) );
                return false;
            }

            @unlink( $ldap_details['ldap_full_file_path'] );
        }

        if( !empty( $params['move_source'] ) )
            $result = @rename( $params['file'], $ldap_details['ldap_full_file_path'] );
        else
            $result = @copy( $params['file'], $ldap_details['ldap_full_file_path'] );

        if( $result === false )
        {
            $this->set_error( self::ERR_SOURCE, self::_t( 'Error copying resource file to LDAP structure.' ) );
            return false;
        }

        if( empty( $ldap_details ) or !is_array( $ldap_details ) )
            $ldap_details = array();

        if( empty( $ldap_details['ldap_meta_arr'] ) or !is_array( $ldap_details['ldap_meta_arr'] ) )
            $ldap_details['ldap_meta_arr'] = array();

        if( !empty( $params['extra_meta'] ) and is_array( $params['extra_meta'] ) )
        {
            foreach( $params['extra_meta'] as $key => $val )
            {
                if( $key !== ''
                and !isset( $ldap_details['ldap_meta_arr'][$key] ) )
                    $ldap_details['ldap_meta_arr'][$key] = $val;
            }
        }

        $ldap_details['ldap_meta_arr']['added'] = date( 'd-m-Y H:i:s' );

        if( ($ldap_details['ldap_meta_arr'] = $this->update_meta( $ldap_details )) === false )
        {
            @unlink( $ldap_details['ldap_full_file_path'] );
            return false;
        }

        return $ldap_details;
    }

    /**
     * @return array
     */
    public static function default_meta_data()
    {
        return array(
            'version' => 1,
            'ldap_id' => '',
            'file_name' => '',
            'file_extension' => '',
            'size' => 0,
        );
    }

    /**
     * @param string $file_name
     * @param string $ldap_id
     *
     * @return array|bool
     */
    private function _extract_meta_data( $file_name, $ldap_id )
    {
        $file_name = @realpath( $file_name );
        if( empty( $file_name ) or !@file_exists( $file_name )
         or (!@is_file( $file_name ) and !@is_link( $file_name )) )
            return false;

        $file_ext = '';
        if( ($file_dots_arr = explode( '.', $file_name ))
        and is_array( $file_dots_arr )
        and count( $file_dots_arr ) > 1 )
            $file_ext = strtolower( array_pop( $file_dots_arr ) );

        $return_arr = self::default_meta_data();
        $return_arr['ldap_id'] = $ldap_id;
        $return_arr['file_name'] = @basename( $file_name );
        $return_arr['file_extension'] = $file_ext;
        $return_arr['size'] = @filesize( $file_name );

        return $return_arr;
    }

    /**
     * @param string $meta_file
     *
     * @return array|bool
     */
    private static function _read_meta_data( $meta_file )
    {
        if( !@file_exists( $meta_file ) or !@is_readable( $meta_file )
         // refuse to read files bigger than 2Mb - might be an error regarding meta file name
         or @filesize( $meta_file ) > 2097152
         or ($meta_buffer = @file_get_contents( $meta_file )) === false )
            return false;

        $return_arr = array();

        if( !empty( $meta_buffer ) )
            $return_arr = self::validate_array( PHS_Line_params::parse_string( $meta_buffer ), self::default_meta_data() );

        return $return_arr;
    }

    /**
     * @param array|string $ldap_data
     * @param bool|array $params
     *
     * @return array|bool
     */
    public function get_meta( $ldap_data, $params = false )
    {
        $this->reset_error();

        $return_arr = self::default_meta_data();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !isset( $params['ignore_read_errors'] ) )
            $params['ignore_read_errors'] = false;

        if( !is_array( $ldap_data ) and is_string( $ldap_data ) )
            $ldap_data = $this->identifier2ldap( $ldap_data );

        if( empty( $ldap_data )
         or !is_array( $ldap_data )
         or empty( $ldap_data['ldap_full_meta_file_path'] ) )
        {
            $this->set_error( self::ERR_LDAP_META, self::_t( 'Unkown LDAP meta file.' ) );
            return false;
        }

        if( !@file_exists( $ldap_data['ldap_full_meta_file_path'] ) )
            return $return_arr;

        if( ($return_arr = self::_read_meta_data( $ldap_data['ldap_full_meta_file_path'] )) === false
        and empty( $params['ignore_read_errors'] ) )
        {
            $this->set_error( self::ERR_LDAP_META, self::_t( 'Couldn\'t read LDAP meta file.' ) );
            return false;
        }

        if( empty( $return_arr ) )
            $return_arr = self::default_meta_data();

        return $return_arr;
    }

    /**
     * @param string|array $ldap_data
     * @param bool|array $params
     *
     * @return array|bool
     */
    public function update_meta( $ldap_data, $params = false )
    {
        $this->reset_error();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();
        if( !isset( $params ) )
            $params['ignore_read_errors'] = false;

        if( !is_array( $ldap_data ) and is_string( $ldap_data ) )
            $ldap_data = $this->identifier2ldap( $ldap_data );

        if( empty( $ldap_data ) or !is_array( $ldap_data )
         or empty( $ldap_data['ldap_full_meta_file_path'] ) )
        {
            $this->set_error( self::ERR_LDAP_META, self::_t( 'Unkown LDAP meta file.' ) );
            return false;
        }

        if( ($existing_meta = $this->get_meta( $ldap_data, $params )) === false )
        {
            if( empty( $params['ignore_read_errors'] ) )
            {
                if( !$this->has_error() )
                    $this->set_error( self::ERR_LDAP_META, self::_t( 'Error reading LDAP meta file.' ) );
                return false;
            }

            $existing_meta = array();
        }

        $new_meta = PHS_Line_params::update_line_params( $existing_meta, $ldap_data['ldap_meta_arr'] );

        $new_meta['last_save'] = date( 'd-m-Y H:i:s' );

        $new_meta_str = PHS_Line_params::to_string( $new_meta );

        $retries = 5;
        while( !($fil = @fopen( $ldap_data['ldap_full_meta_file_path'], 'wt' )) and $retries > 0 )
            $retries--;

        if( empty( $fil ) )
        {
            $this->set_error( self::ERR_LDAP_META, self::_t( 'Cannot open meta file for write.' ) );
            return false;
        }

        @fwrite( $fil, $new_meta_str );
        @fflush( $fil );
        @fclose( $fil );

        return $new_meta;
    }

    /**
     * @return array
     */
    public static function default_ldap_data()
    {
        return array(
            'ldap_id' => '',
            'ldap_root' => '',
            'ldap_path' => '',
            'ldap_path_segments' => array(),
            'ldap_full_path' => '',
            'ldap_file_name' => '',
            'ldap_full_file_path' => '',
            'ldap_meta_file' => '',
            'ldap_full_meta_file_path' => '',
            'ldap_meta_arr' => self::default_meta_data(),
        );
    }

    /**
     * @param string $identifier
     * @param bool|string $source_file
     * @param bool|array $meta_arr
     *
     * @return array|bool
     */
    public function identifier2ldap( $identifier, $source_file = false, $meta_arr = false )
    {
        $settings = $this->server_settings();

        if( empty( $identifier ) or !is_string( $identifier ) )
            return false;

        if( empty( $settings ) or !is_array( $settings ) )
            $settings = self::default_settings();
        else
            $settings = self::validate_array( $settings, self::default_settings() );

        $file_hash = md5( $identifier );
        $segments_arr = str_split( $file_hash, $settings['dir_length'] );
        if( empty( $segments_arr ) or !is_array( $segments_arr ) )
            return false;

        $return_arr = self::default_ldap_data();
        $return_arr['ldap_id'] = $identifier;
        $return_arr['ldap_root'] = $settings['root'];
        $return_arr['ldap_path'] = '';
        $return_arr['ldap_path_segments'] = array();
        $return_arr['ldap_full_path'] = '';
        $return_arr['ldap_file_name'] = '';
        $return_arr['ldap_full_file_path'] = '';
        $return_arr['ldap_meta_file'] = $settings['file_prefix'].$file_hash.'_.meta'; // added _ at the end in case file we put in LDAP has 'meta' extension
        $return_arr['ldap_full_meta_file_path'] = '';
        $return_arr['ldap_meta_arr'] = self::default_meta_data();

        for( $i = 0; isset( $segments_arr[$i] ) and $i < $settings['depth']; $i++ )
        {
            $return_arr['ldap_path'] .= $segments_arr[$i].'/';
            $return_arr['ldap_path_segments'][] = $segments_arr[$i];
        }

        $return_arr['ldap_full_path'] = $return_arr['ldap_root'].$return_arr['ldap_path'];
        $return_arr['ldap_full_meta_file_path'] = $return_arr['ldap_full_path'].$return_arr['ldap_meta_file'];

        // If we have a source file, populate LDAP identification with what we can extract from file...
        if( $source_file !== false )
        {
            // If we don't have a valid file as source, we cannot extract LDAP info correctly
            if( !@file_exists( $source_file ) or !@is_readable( $source_file )
             or (!@is_file( $source_file ) and !@is_link( $source_file ))
             or !($return_arr['ldap_meta_arr'] = $this->_extract_meta_data( $source_file, $identifier )) )
                return false;
        } else
        {
            if( !empty( $meta_arr ) and is_array( $meta_arr ) )
                $return_arr['ldap_meta_arr'] = self::validate_array( $meta_arr, self::default_meta_data() );

            elseif( !@file_exists( $return_arr['ldap_full_meta_file_path'] )
             or ($return_arr['ldap_meta_arr'] = self::_read_meta_data( $return_arr['ldap_full_meta_file_path'] )) === false )
                return false;
        }

        $return_arr['ldap_file_name'] = $settings['file_prefix'].$file_hash.'.'.$return_arr['ldap_meta_arr']['file_extension'];
        $return_arr['ldap_full_file_path'] = $return_arr['ldap_full_path'].$return_arr['ldap_file_name'];

        return $return_arr;
    }

    /**
     * @param array $segments_arr
     *
     * @return bool
     */
    private function _mkdir_tree( $segments_arr )
    {
        if( empty( $segments_arr ) or !is_array( $segments_arr )
         or !$this->is_ready()
         or !($settings = $this->server_settings())
         or empty( $settings['root'] ) )
            return false;

        $this->reset_error();

        $settings['root'] = rtrim( $settings['root'], '/\\' );

        $segments_path = '';
        foreach( $segments_arr as $dir_segment )
        {
            if( empty( $dir_segment ) )
                continue;

            $segments_path .= '/'.$dir_segment;

            $current_path = $settings['root'].$segments_path;

            if( @file_exists( $current_path ) )
            {
                if( !@is_dir( $current_path ) )
                {
                    $this->set_error( self::ERR_LDAP_DIR, self::_t( '[%s] is not a directory.', $current_path ) );
                    return false;
                }

                continue;
            }

            if( !@mkdir( $current_path ) and !@is_dir( $current_path ) )
            {
                $this->set_error( self::ERR_LDAP_DIR, 'Cannot create directory ['.$current_path.']' );
                return false;
            }

            @chmod( $current_path, $settings['dir_mode'] );
        }

        return true;
    }

    /**
     * @param string $root
     *
     * @return array|bool
     */
    public static function load_ldap_settings( $root )
    {
        if( empty( $root )
         or !($root = @realpath( $root ))
         or !@is_dir( $root ) )
            return false;

        if( substr( $root, -1 ) !== '/' )
            $root .= '/';

        $return_arr = array();

        $config_file = self::_ldap_config_file();
        if( !@file_exists( $root.$config_file ) )
            return $return_arr;

        if( ($existing_config = @file_get_contents( $root.$config_file )) === false
         or empty( $existing_config ) )
            return false;

        if( !empty( $existing_config ) )
            $return_arr = PHS_Line_params::parse_string( $existing_config );

        $return_arr = self::validate_array( $return_arr, self::default_settings() );

        return $return_arr;
    }

    /**
     * @param array $settings_arr
     *
     * @return string
     */
    private function get_settings_checksum( $settings_arr )
    {
        $settings_arr = self::validate_array( $settings_arr, self::default_settings() );

        $settings_arr['checksum'] = '';
        $settings_arr['config_last_save'] = '';

        return @md5( @json_encode( $settings_arr ) );
    }

    /**
     * @return bool
     */
    private function _save_ldap_settings()
    {
        if( !$this->is_ready() )
            return false;

        $settings = self::validate_array( $this->server_settings(), self::default_settings() );

        $settings_checksum = $this->get_settings_checksum( $settings );

        if( empty( $settings['checksum'] ) )
            $settings['checksum'] = $settings_checksum;

        elseif( $settings['checksum'] === $settings_checksum )
            return true;

        $config_file = self::_ldap_config_file();

        $retries = 5;
        while( !($fil = @fopen( $settings['root'].$config_file, 'wb' )) and $retries > 0 )
            $retries--;

        if( empty( $fil ) )
        {
            $this->set_error( self::ERR_LDAP_META, self::_t( 'Cannot open LDAP config file for write.' ) );
            return false;
        }

        $settings['config_last_save'] = date( 'd-m-Y H:i:s' );

        @fwrite( $fil, PHS_Line_params::to_string( $settings ) );
        @fflush( $fil );
        @fclose( $fil );

        return true;
    }

    /**
     * @return string
     */
    private static function _ldap_config_file()
    {
        return '__ldap.config';
    }
}
