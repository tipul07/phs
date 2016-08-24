<?php

namespace phs\libraries;

/*! \file phs_utils.php
 *  \brief Contains PHS_utils class (different utility functions...)
 *  \version 1.30
 */

class PHS_utils extends PHS_Language
{
    //! Error related to directories
    const ERR_DIRECTORY = 1;

    const PERIOD_FULL = 0, PERIOD_SECONDS = 1, PERIOD_MINUTES = 2, PERIOD_HOURS = 3, PERIOD_DAYS = 4, PERIOD_WEEKS = 5, PERIOD_MONTHS = 6, PERIOD_YEARS = 7;

    function __construct()
    {
        parent::__construct();
    }

    static function parse_period( $seconds_span, $params = false )
    {
        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['only_big_part'] ) )
            $params['only_big_part'] = false;
        if( empty( $params['show_period'] ) or $params['show_period'] > self::PERIOD_YEARS )
            $params['show_period'] = self::PERIOD_FULL;
        if( empty( $params['start_timestamp'] ) )
            $params['start_timestamp'] = time();
        else
            $params['start_timestamp'] = intval( $params['start_timestamp'] );

        $seconds_span = intval( $seconds_span );
        $nowtime = $params['start_timestamp'];
        $pasttime = $nowtime - $seconds_span;

        $nowdate_obj = new \DateTime( '@'.$nowtime );
        $pastdate_obj = new \DateTime( '@'.$pasttime );
        $interval = $nowdate_obj->diff( $pastdate_obj );

        $years = $interval->y;
        $months = $interval->m;
        $days = $interval->d;
        $hours = $interval->h;
        $minutes = $interval->i;
        $seconds = $interval->s;

        switch( $params['show_period'] )
        {
            default:
            case self::PERIOD_FULL:
                if( !empty( $params['only_big_part'] ) )
                {
                    $return_arr = array();
                    if( !empty( $years ) )
                        $return_arr[] = $years.' '.($years>1?self::_t( 'years' ):self::_t( 'year' ));
                    elseif( !empty( $months ) )
                        $return_arr[] = $months.' '.($months>1?self::_t( 'months' ):self::_t( 'month' ));
                    elseif( !empty( $days ) )
                        $return_arr[] = $days.' '.($days>1?self::_t( 'days' ):self::_t( 'day' ));
                    elseif( !empty( $hours ) )
                        $return_arr[] = $hours.' '.($hours>1?self::_t( 'hours' ):self::_t( 'hour' ));
                    elseif( !empty( $minutes ) )
                        $return_arr[] = $minutes.' '.($minutes>1?self::_t( 'minutes' ):self::_t( 'minute' ));
                    elseif( !empty( $seconds ) )
                        $return_arr[] = $seconds.' '.($seconds>1?self::_t( 'seconds' ):self::_t( 'second' ));

                    return implode( ', ', $return_arr );
                } else
                {
                    $return_arr = array();
                    if( !empty( $years ) )
                        $return_arr[] = $years.' '.($years>1?self::_t( 'years' ):self::_t( 'year' ));
                    if( !empty( $months ) )
                        $return_arr[] = $months.' '.($months>1?self::_t( 'months' ):self::_t( 'month' ));
                    if( !empty( $days ) )
                        $return_arr[] = $days.' '.($days>1?self::_t( 'days' ):self::_t( 'day' ));
                    if( !empty( $hours ) )
                        $return_arr[] = $hours.' '.($hours>1?self::_t( 'hours' ):self::_t( 'hour' ));
                    if( !empty( $minutes ) )
                        $return_arr[] = $minutes.' '.($minutes>1?self::_t( 'minutes' ):self::_t( 'minute' ));
                    if( !empty( $seconds ) )
                        $return_arr[] = $seconds.' '.($seconds>1?self::_t( 'seconds' ):self::_t( 'second' ));

                    return implode( ', ', $return_arr );
                }
            break;

            case self::PERIOD_SECONDS:
                return $seconds_span.' '.($seconds_span>1?self::_t( 'seconds' ):self::_t( 'second' ));
            break;

            case self::PERIOD_MINUTES:
                $minutes_diff = floor( $seconds_span/60 );
                return $minutes_diff.' '.($minutes_diff>1?self::_t( 'minutes' ):self::_t( 'minute' ));
            break;

            case self::PERIOD_HOURS:
                $hours_diff = floor( $seconds_span/3600 );
                return $hours_diff.' '.($hours_diff>1?self::_t( 'hours' ):self::_t( 'hour' ));
            break;

            case self::PERIOD_DAYS:
                $days_diff = floor( $seconds_span/86400 );
                return $days_diff.' '.($days_diff>1?self::_t( 'days' ):self::_t( 'day' ));
            break;

            case self::PERIOD_WEEKS:
                $weeks_diff = floor( $seconds_span/604800 );
                return $weeks_diff.' '.($weeks_diff>1?self::_t( 'weeks' ):self::_t( 'week' ));
            break;

            case self::PERIOD_MONTHS:
                return $months.' '.($months>1?self::_t( 'months' ):self::_t( 'month' ));
            break;

            case self::PERIOD_YEARS:
                return $years.' '.($years>1?self::_t( 'years' ):self::_t( 'year' ));
            break;
        }
    }

    static function check_crawler_request( $params = false )
    {
        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['user_agent'] )
        and !empty( $_SERVER ) and isset( $_SERVER['HTTP_USER_AGENT'] ) )
            $params['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        else
            $params['user_agent'] = '';

        $return_arr = array();
        $return_arr['is_bot'] = false;
        $return_arr['bot_name'] = '';

        if( stristr( $params['user_agent'], 'googlebot' ) !== false )
        {
            $return_arr['is_bot'] = true;
            $return_arr['bot_name'] = 'Google';
        } elseif( stristr( $params['user_agent'], 'msnbot' ) !== false or stristr( $params['user_agent'], 'msrbot' ) !== false )
        {
            $return_arr['is_bot'] = true;
            $return_arr['bot_name'] = 'MSN';
        } elseif( stristr( $params['user_agent'], 'bingbot' ) !== false or stristr( $params['user_agent'], 'bingpreview' ) !== false )
        {
            $return_arr['is_bot'] = true;
            $return_arr['bot_name'] = 'Bing';
        }

        return $return_arr;
    }

    public static function mkdir_tree( $segments, $params = false )
    {
        self::st_reset_error();

        if( !isset( $segments ) or $segments === '' )
        {
            self::st_set_error( self::ERR_DIRECTORY, self::_t( 'Cannot create empty directory.' ) );
            return false;
        }

        if( empty( $params ) or !is_array( $params ) )
            $params = array();
        if( empty( $params['root'] ) )
            $params['root'] = '';
        if( !isset( $params['dir_mode'] ) )
            $params['dir_mode'] = 0775;

        $segments_arr = $segments;
        if( !is_array( $segments ) )
            $segments_arr = explode( '/', $segments );

        $segments_quick = implode( '/', $segments_arr );
        if( @file_exists( $segments_quick ) and @is_dir( $segments_quick ) )
            return true;

        $segments_path = (string)$params['root'];
        if( substr( $segments_path, -1 ) == '/' )
            $segments_path = substr( $segments_path, 0, -1 );

        foreach( $segments_arr as $dir_segment )
        {
            if( empty( $dir_segment ) )
            {
                if( $segments_path == '' )
                    $segments_path .= '/';
                continue;
            }

            $segments_path .= '/'.$dir_segment;

            if( @file_exists( $segments_path ) )
            {
                if( !@is_dir( $segments_path ) )
                {
                    self::st_set_error( self::ERR_DIRECTORY, self::_t( '[%s] is not a directory.', $segments_path ) );
                    return false;
                }

                continue;
            }

            if( !@mkdir( $segments_path ) )
            {
                self::st_set_error( self::ERR_DIRECTORY, self::_t( 'Cannot create directory [%s]', $segments_path ) );
                return false;
            }

            if( !empty( $params['dir_mode'] ) )
                @chmod( $segments_path, $params['dir_mode'] );
        }

        return true;
    }

    public static function get_files_recursive( $directory, $params = false )
    {
        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( substr( $directory, -1 ) == '/' )
            $directory = substr( $directory, 0, -1 );

        if( !@file_exists( $directory ) or !@is_dir( $directory ) )
            return array();

        if( empty( $params['accept_symlinks'] ) )
            $params['accept_symlinks'] = false;

        if( empty( $params['extensions_arr'] ) or !is_array( $params['extensions_arr'] ) )
            $params['extensions_arr'] = array();

        // you don't have to pas <level> as it is used internally
        if( empty( $params['{level}'] ) )
            $params['{level}'] = 0;

        if( !empty( $params['extensions_arr'] ) and is_array( $params['extensions_arr'] ) )
        {
            $new_extensions_arr = array();
            foreach( $params['extensions_arr'] as $ext )
            {
                $new_extensions_arr[] = strtolower( $ext );
            }

            $params['extensions_arr'] = $new_extensions_arr;
        }

        $found_files = array();
        if( ($directory_content = @glob( $directory.'/*' )) )
        {
            foreach( $directory_content as $filename )
            {
                if( $filename == '.' or $filename == '..' )
                    continue;

                if( @is_file( $filename )
                 or (@is_link( $filename ) and !empty( $params['accept_symlinks'] )) )
                {
                    $file_ext = '';
                    if( ($file_arr = explode( '.', $filename ))
                    and count( $file_arr ) > 1 )
                        $file_ext = array_pop( $file_arr );

                    if( empty( $params['extensions_arr'] )
                     or empty( $file_ext )
                     or in_array( strtolower( $file_ext ), $params['extensions_arr'] ) )
                        $found_files[$filename] = 1;

                    continue;
                }

                if( @is_dir( $filename ) )
                {
                    $new_params = $params;
                    $new_params['{level}']++;

                    if( ($dir_found_files = self::get_files_recursive( $filename, $new_params ))
                    and is_array( $dir_found_files ) )
                        $found_files = array_merge( $found_files, $dir_found_files );
                }
            }
        }

        // top level...
        if( empty( $params['{level}'] ) and !empty( $found_files ) )
            $found_files = array_keys( $found_files );

        return $found_files;
    }

    public static function rmdir_tree( $directory, $params = false )
    {
        if( empty( $params ) or !is_array( $params ) )
            $params = array();
        if( !isset( $params['recursive'] ) )
            $params['recursive'] = true;

        if( substr( $directory, -1 ) == '/' )
            $directory = substr( $directory, 0, -1 );

        if( !@file_exists( $directory ) or !@is_dir( $directory ) )
            return true;

        $got_errors = false;
        if( ($directory_content = @glob( $directory.'/*' )) )
        {
            foreach( $directory_content as $filename )
            {
                if( $filename == '.' or $filename == '..' )
                    continue;

                if( @is_file( $filename ) or @is_link( $filename ) )
                {
                    @unlink( $filename );
                    continue;
                }

                if( @is_dir( $filename ) and !empty( $params['recursive'] ) )
                {
                    if( !self::rmdir_tree( $filename, $params ) )
                        $got_errors = true;

                    @rmdir( $filename );
                }
            }
        }

        $return_val = @rmdir( $directory );

        if( empty( $return_val ) and !empty( $got_errors ) )
            return false;

        return $return_val;
    }

    public static function mimetype( $file, $params = false )
    {
        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['virtual_file'] ) )
            $params['virtual_file'] = false;

        $file = (string)$file;
        if( $file == ''
         or (empty( $params['virtual_file'] ) and (!@file_exists( $file ) or !@is_readable( $file ))) )
            return '';

        $file_mime_type = '';
        if( empty( $params['virtual_file'] )
        and @function_exists( 'finfo_open' ) )
        {
            if( !($flags = constant( 'FILEINFO_MIME' )) )
                $flags = 0;

            if( defined( 'FILEINFO_PRESERVE_ATIME' ) )
                $flags |= constant( 'FILEINFO_PRESERVE_ATIME' );

            if( !empty( $flags )
            and ($finfo = @finfo_open( $flags )) )
            {
                $file_mime_type = @finfo_file( $finfo, $file );
                @finfo_close( $finfo );
            }
        }

        if( empty( $params['virtual_file'] )
        and empty( $file_mime_type ) )
        {
            if( ($cmd_buf = @exec( 'file -bi ' . @escapeshellarg( $file ) )) )
                $file_mime_type = trim( $cmd_buf );
        }

        if( empty( $file_mime_type ) )
        {
            $file_ext = '';
            if( ($file_dots_arr = explode( '.', $file )) and is_array( $file_dots_arr ) and count( $file_dots_arr ) > 1 )
                $file_ext = array_pop( $file_dots_arr );

            $file_ext = strtolower( $file_ext );
            switch( $file_ext )
            {
                case 'js' :
                   $file_mime_type = 'application/x-javascript';
                break;

                case 'json' :
                    $file_mime_type = 'application/json';
                break;

                case 'jpg' :
                case 'jpeg' :
                case 'jpe' :
                    $file_mime_type = 'image/jpeg';
                break;

                case 'png' :
                case 'gif' :
                case 'bmp' :
                case 'tiff' :
                    $file_mime_type = 'image/'.$file_ext;
                break;

                case 'css' :
                    $file_mime_type = 'text/css';
                break;

                case 'xml' :
                    $file_mime_type = 'application/xml';
                break;

                case 'doc' :
                case 'docx' :
                    $file_mime_type = 'application/msword';
                break;

                case 'xls' :
                case 'xlt' :
                case 'xlm' :
                case 'xld' :
                case 'xla' :
                case 'xlc' :
                case 'xlw' :
                case 'xll' :
                    $file_mime_type = 'application/vnd.ms-excel';
                break;

                case 'ppt' :
                case 'pps' :
                    $file_mime_type = 'application/vnd.ms-powerpoint';
                break;

                case 'rtf' :
                    $file_mime_type = 'application/rtf';
                break;

                case 'pdf' :
                    $file_mime_type = 'application/pdf';
                break;

                case 'html' :
                case 'htm' :
                case 'php' :
                    $file_mime_type = 'text/html';
                break;

                case 'txt' :
                    $file_mime_type = 'text/plain';
                break;

                case 'mpeg' :
                case 'mpg' :
                case 'mpe' :
                    $file_mime_type = 'video/mpeg';
                break;

                case 'mp3' :
                    $file_mime_type = 'audio/mpeg3';
                break;

                case 'wav' :
                    $file_mime_type = 'audio/wav';
                break;

                case 'aiff' :
                case 'aif' :
                    $file_mime_type = 'audio/aiff';
                break;

                case 'avi' :
                    $file_mime_type = 'video/avi';
                break;

                case 'wmv' :
                    $file_mime_type = 'video/x-ms-wmv';
                break;

                case 'mov' :
                    $file_mime_type = 'video/quicktime';
                break;

                case 'mp4' :
                    $file_mime_type = 'video/mp4';
                break;

                case 'webm' :
                    $file_mime_type = 'video/webm';
                break;

                case 'zip' :
                    $file_mime_type = 'application/zip';
                break;

                case 'tar' :
                    $file_mime_type = 'application/x-tar';
                break;

                case 'swf' :
                    $file_mime_type = 'application/x-shockwave-flash';
                break;
            }
        }

        return $file_mime_type;
    }

    public static function mypathinfo( $str )
    {
        $ret = array();
        $ret['dirname'] = '';
        $ret['filename'] = '';
        $ret['basename'] = '';
        $ret['extension'] = '';

        $dir_file = explode( '/', $str );
        $knt = count( $dir_file );

        if( $knt <= 1 )
        {
            $ret['dirname'] = '';
            $file = explode( '.', $str );
        } elseif( $dir_file[$knt-1] == '' )
        {
            $ret['dirname'] = implode( '/', array_slice( $dir_file, 0, -1 ) );
            $file = false;
        } else
        {
            $ret['dirname'] = implode( '/', array_slice( $dir_file, 0, -1 ) );
            $file = explode( '.', $dir_file[$knt-1] );
        }

        if( $file !== false )
        {
            if( ($dot_count = count( $file )) <= 1 )
            {
                $ret['basename'] = implode( '.', $file );
                $ret['extension'] = '';
                $ret['filename'] = $ret['basename'];
            } else
            {
                $ret['basename'] = implode( '.', array_slice( $file, 0, -1 ) );
                $ret['extension'] = $file[$dot_count-1];
                $ret['filename'] = $ret['basename'].'.'.$ret['extension'];
            }
        }

        return $ret;
    }

    public static function myparse_url( $str )
    {
        $ret = array();
        $ret['scheme'] = '';
        $ret['user'] = '';
        $ret['pass'] = '';
        $ret['host'] = '';
        $ret['port'] = '';
        $ret['path'] = '';
        $ret['query'] = '';
        $ret['anchor'] = '';

        $mystr = $str;

        $res = explode( '#', $mystr, 2 );
        if( isset( $res[1] ) )
            $ret['anchor'] = $res[1];
        else
            $ret['anchor'] = '';
        $mystr = $res[0];

        $res = explode( '?', $mystr, 2 );
        if( isset( $res[1] ) )
            $ret['query'] = $res[1];
        else
            $ret['query'] = '';
        $mystr = $res[0];

        $res = explode( '://', $mystr, 2 );
        if( isset( $res[1] ) )
        {
            $ret['scheme'] = $res[0];
            $mystr = $res[1];
        } else
        {
            $mystr = $res[0];

            if( substr( $mystr, 0, 2 ) == '//' )
            {
                $ret['scheme'] = '//';
                $mystr = substr( $mystr, 2 );
            } else
                $ret['scheme'] = '';
        }

        $path_present = true;
        $host_present = true;
        // host is not present - only the path might be present
        if( ($dotpos = strpos( $mystr , '.' )) === false
        and $ret['scheme'] == '' )
            $host_present = false;

        // no path is present or only a directory name is present
        if( ($slashpos = strpos( $mystr , '/' )) === false
        and $ret['scheme'] == '' )
        {
            $host_present = true;
            $path_present = false;
        }

        if( $host_present and $dotpos !== false  )
        {
            if( $slashpos !== false )
            {
                if( $dotpos > $slashpos )
                {
                    // dot is after / so it must be a path
                    $host_present = false;
                    $path_present = true;
                } elseif( $ret['scheme'] == '' )
                {
                    // no scheme given, might be a server path...
                    $host_present = false;
                    $path_present = true;
                }
            } elseif( $ret['scheme'] == '' )
            {
                // we don't have any slashes... might be a filename/dir name in current folder
                $host_present = false;
                $path_present = true;
            }
        }

        if( $path_present )
        {
            if( !$host_present )
            {
                $ret['path'] = $mystr;
            } else
            {
                $res = explode( '/', $mystr, 2 );
                if( isset( $res[1] ) and $res[1] != '' )
                    $ret['path'] = $res[1];
                else
                    $ret['path'] = '';
                $mystr = $res[0];
            }
        }

        $host_port = '';
        $user_pass = '';
        if( $host_present )
        {
            if( strstr( $mystr, '@' ) )
            {
                $res = explode( '@', $mystr, 2 );
                $user_pass = $res[0];
                $host_port = $res[1];
            } else
            {
                $host_port = $mystr;
                $user_pass = '';
            }
        }

        if( strstr( $host_port, ':' ) )
        {
            $res = explode( ':', $host_port, 2 );
            $ret['host'] = $res[0];
            $ret['port'] = $res[1];
        } else
        {
            $ret['host'] = $host_port;
            $ret['port'] = '';
        }

        if( $user_pass != '' )
        {
            $res = explode( ':', $user_pass, 2 );
            if( isset( $res[1] ) and $res[1] != '' )
                $ret['pass'] = $res[1];
            else
                $ret['pass'] = '';
            $ret['user'] = $res[0];
        } else
        {
            $ret['user'] = '';
            $ret['pass'] = '';
        }

        return $ret;
    }

    public static function rebuild_url( $url_parts )
    {
        if( empty( $url_parts ) or !is_array( $url_parts ) )
            return '';

        $parts_arr = array( 'scheme', 'user', 'pass', 'host', 'port', 'path', 'query', 'anchor' );
        foreach( $parts_arr as $part_field )
        {
            if( !isset( $url_parts[$part_field] ) )
                $url_parts[$part_field] = '';
        }

        $final_url = $url_parts['scheme'];

        if( $url_parts['scheme'] != '//' )
        {
            $final_url .= (!empty( $url_parts['scheme'] )?':':'').'//';
        }

        $final_url .= $url_parts['user'];
        $final_url .= (!empty( $url_parts['pass'] )?':':'').$url_parts['pass'].((!empty( $url_parts['user'] ) or !empty( $url_parts['pass'] ))?'@':'');
        $final_url .= $url_parts['host'];
        $final_url .= (!empty( $url_parts['port'] )?':':'').$url_parts['port'];
        $final_url .= $url_parts['path'];
        $final_url .= (!empty( $url_parts['query'] )?'?':'').$url_parts['query'];
        $final_url .= (!empty( $url_parts['anchor'] )?'#':'').$url_parts['anchor'];

        return $final_url;
    }

    public static function quick_watermark( $source, $destination, $watermark, $params = false )
    {
        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $source ) or empty( $watermark )
         or !@file_exists( $source ) or !@file_exists( $watermark ) )
            return false;

        if( !isset( $params['output_details'] ) )
            $params['output_details'] = true;
        if( empty( $params['overwrite_destination'] ) )
            $params['overwrite_destination'] = false;
        if( empty( $params['composite_bin'] ) )
            $params['composite_bin'] = 'composite';

        if( empty( $params['watermark'] ) )
            $params['watermark'] = '20%';
        else
            $params['watermark'] = trim( $params['watermark'] );
        if( empty( $params['gravity'] ) )
            $params['gravity'] = 'SouthEast';
        else
            $params['gravity'] = trim( $params['gravity'] );

        if( @file_exists( $destination ) )
        {
            if( @is_dir( $destination )
             or empty( $params['overwrite_destination'] )
             or !@unlink( $destination ) )
                return false;
        }

        @exec( $params['composite_bin'].' -watermark '.escapeshellarg( $params['watermark'] ).' '.
               ' -gravity '.escapeshellarg( $params['gravity'] ).' '.
               ' '.escapeshellarg( $watermark ).' '.escapeshellarg( $source ).' '.escapeshellarg( $destination ) );

        if( !@file_exists( $destination ) )
            return false;

        $return_val = true;
        if( !empty( $params['output_details'] )
        and ($output_details = @getimagesize( $destination )) )
        {
            $return_val = array();
            $return_val['width'] = $output_details[0];
            $return_val['height'] = $output_details[1];
        }

        return $return_val;
    }

    public static function quick_convert( $source, $destination, $params )
    {
        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( (empty( $params['width'] ) and empty( $params['height'] ))
         or !@file_exists( $source ) )
            return false;

        if( empty( $params['width'] ) )
            $params['width'] = 0;
        else
            $params['width'] = intval( $params['width'] );

        if( empty( $params['height'] ) )
            $params['height'] = 0;
        else
            $params['height'] = intval( $params['height'] );

        if( !isset( $params['output_details'] ) )
            $params['output_details'] = true;
        if( empty( $params['overwrite_destination'] ) )
            $params['overwrite_destination'] = false;
        if( empty( $params['convert_bin'] ) )
            $params['convert_bin'] = 'convert';
        if( empty( $params['background'] ) )
            $params['background'] = 'black';
        else
            $params['background'] = trim( $params['background'] );

        if( @file_exists( $destination ) )
        {
            if( @is_dir( $destination )
             or empty( $params['overwrite_destination'] )
             or !@unlink( $destination ) )
                return false;
        }

        $size_str = '';
        if( !empty( $params['width'] ) )
            $size_str .= $params['width'];
        $size_str .= 'x';
        if( !empty( $params['height'] ) )
            $size_str .= $params['height'];

        @exec( escapeshellarg( $params['convert_bin'] ).' '.escapeshellarg( $source ).' -resize '.$size_str.'\> -background \''.escapeshellarg( $params['background'] ).'\' '.
               ' -gravity center -extent \''.$size_str.'\' '.escapeshellarg( $destination ) );

        if( !@file_exists( $destination ) )
            return false;

        $return_val = true;
        if( !empty( $params['output_details'] )
        and ($output_details = @getimagesize( $destination )) )
        {
            $return_val = array();
            $return_val['width'] = $output_details[0];
            $return_val['height'] = $output_details[1];
        }

        return $return_val;
    }

    public static function quick_curl( $url, $params = false )
    {
        if( !($ch = @curl_init()) )
            return false;

        if( !is_array( $params ) )
            $params = array();

        // Default CURL params...
        if( empty( $params['userpass'] ) or !is_array( $params['userpass'] ) or !isset( $params['userpass']['user'] ) or !isset( $params['userpass']['pass'] ) )
            $params['userpass'] = false;

        if( empty( $params['follow_location'] ) )
            $params['follow_location'] = false;
        else
            $params['follow_location'] = (!empty( $params['follow_location'] )?true:false);

        if( empty( $params['timeout'] ) )
            $params['timeout'] = 30;
        else
            $params['timeout'] = intval( $params['timeout'] );
        if( empty( $params['user_agent'] ) )
            $params['user_agent'] = 'PHS/PHS_utils v'.PHS_VERSION;
        if( empty( $params['extra_get_params'] ) or !is_array( $params['extra_get_params'] ) )
            $params['extra_get_params'] = array();
        // END Default CURL params...

        if( !isset( $params['raw_post_str'] ) )
            $params['raw_post_str'] = '';
        if( empty( $params['header_keys_arr'] ) or !is_array( $params['header_keys_arr'] ) )
            $params['header_keys_arr'] = array();
        if( empty( $params['header_arr'] ) or !is_array( $params['header_arr'] ) )
            $params['header_arr'] = array();

        // Convert old format to new format...
        if( !empty( $params['header_arr'] ) )
        {
            foreach( $params['header_arr'] as $knti => $header_txt )
            {
                $header_value_arr = explode( ':', $header_txt );
                $key = trim( $header_value_arr[0] );
                $val = '';
                if( isset( $header_value_arr[1] ) )
                    $val = ltrim( $header_value_arr[1] );

                $params['header_keys_arr'][$key] = $val;
            }

            // Reset raw headers array as we moved them to key => value pairs...
            $params['header_arr'] = array();
        }

        $post_string = '';
        if( !empty( $params['post_arr'] ) and is_array( $params['post_arr'] ) )
        {
            foreach( $params['post_arr'] as $key => $val )
            {
                // workaround for '@/local/file' fields...
                if( substr( $val, 0, 1 ) == '@' )
                {
                    $post_string = $params['post_arr'];
                    break;
                }

                $post_string .= $key.'='.utf8_encode( rawurlencode( $val ) ).'&';
            }

            if( is_string( $post_string ) and $post_string != '' )
                $post_string = substr( $post_string, 0, -1 );

            if( !isset( $params['header_keys_arr']['Content-Type'] ) )
                $params['header_keys_arr']['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        if( $params['raw_post_str'] != '' )
            $post_string .= $params['raw_post_str'];

        if( $post_string != '' )
        {
            @curl_setopt( $ch, CURLOPT_POST, true );
            @curl_setopt( $ch, CURLOPT_POSTFIELDS, $post_string );
        }

        if( count( $params['extra_get_params'] ) )
        {
            if( strstr( $url, '?' ) === false )
                $url .= '?';

            foreach( $params['extra_get_params'] as $key => $val )
            {
                $url .= '&'.$key.'='.rawurlencode( $val );
            }
        }

        if( !empty( $params['header_keys_arr'] ) and is_array( $params['header_keys_arr'] ) )
        {
            foreach( $params['header_keys_arr'] as $key => $val )
                $params['header_arr'][] = $key.': '.$val;
        }

        if( !empty( $params['header_arr'] ) and is_array( $params['header_arr'] ) )
           @curl_setopt( $ch, CURLOPT_HTTPHEADER, $params['header_arr'] );

        if( !empty( $params['user_agent'] ) )
            curl_setopt( $ch, CURLOPT_USERAGENT, $params['user_agent'] );

        @curl_setopt( $ch, CURLOPT_URL, $url );
        @curl_setopt( $ch, CURLOPT_HEADER, 0 );
        if( defined( 'CURLINFO_HEADER_OUT' ) )
            @curl_setopt( $ch, constant( 'CURLINFO_HEADER_OUT' ), true );
        @curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        @curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
        @curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
        @curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, $params['follow_location'] );
        @curl_setopt( $ch, CURLOPT_TIMEOUT, $params['timeout'] );

        if( !empty( $params['userpass'] ) )
            @curl_setopt( $ch, CURLOPT_USERPWD, $params['userpass']['user'].':'.$params['userpass']['pass'] );

        $response = @curl_exec( $ch );

        $return_params = $params;
        if( isset( $return_params['userpass']['pass'] ) )
            $return_params['userpass']['pass'] = '(undisclosed_pass)';

        $response = array(
            'response' => $response,
            'request_details' => @curl_getinfo( $ch ),
            'request_error_msg' => @curl_error( $ch ),
            'request_error_no' => @curl_errno( $ch ),
            'request_params' => $return_params,
        );

        @curl_close( $ch );

        return $response;
    }

    static function xml_to_array( $buf, $params = false )
    {
        $reg_exp = "/<(\w+)[^>]*>(.*?)<\/\\1>/s";
        preg_match_all( $reg_exp, $buf, $match );

        if( !is_array( $params ) )
            $params = array();
        if( !isset( $params['keys_to_lowercase'] ) )
            $params['keys_to_lowercase'] = true;

        $array = array();
        foreach( $match[1] as $key => $val )
        {
            if( !empty( $params['keys_to_lowercase'] ) )
                $val = strtolower( $val );

            if( preg_match( $reg_exp, $match[2][$key] ) )
            {
                if( !isset( $array[$val] ) )
                    $array[$val] = self::xml_to_array( $match[2][$key], $params );

                else
                {
                    if( !isset( $array[$val][0] ) or !is_array( $array[$val][0] ) )
                    {
                        $tmp_array = $array[$val];
                        unset( $array[$val] );
                        $array[$val] = array( 0 => $tmp_array );
                    }

                    $array[$val][] = self::xml_to_array( $match[2][$key], $params );
                }
            } else
            {
                if( !isset( $array[$val] ) )
                    $array[$val] = $match[2][$key];

                else
                {
                    if( !isset( $array[$val][0] ) or !is_array( $array[$val][0] ) )
                        $array[$val] = array( 0 => $array[$val] );

                    $array[$val][] = $match[2][$key];
                }
            }
        }

        return $array;
    }

    //! Parses an array and returns XML string
    /*
     * Example:
     *
     * $xml_arr = array(
     *   'gigi' => array( '@attr1' => 1, '@attr2' => 'attr2', '#' => 'bubu' ),
     *   'list' => array( 'item' => array( 0 => array( 'name' => 'vasile1', 'age' => 12 ), 1 => array( 'name' => 'vasile2', 'age' => 12 ), 2 => array( 'name' => 'vasile3', 'age' => 12 ), ) ),
     *   'gigi2' => array( '@attr1' => 1, '@attr2' => 'attr2', '#' => array( 'key1' => 1, 'key2' => 2 ) ),
     *   );
     *
     * PHS_utils::array_to_xml( $xml_arr, array( 'root_tag' => 'root' ) );
     *
     **/
    static function array_to_xml( $arr, $params = false )
    {
        if( empty( $arr ) or !is_array( $arr ) )
            return '';

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['root'] ) )
            $params['root'] = '';
        if( empty( $params['root_tag'] ) )
            $params['root_tag'] = '';
        if( empty( $params['add_xml_signature'] ) )
            $params['add_xml_signature'] = true;
        if( empty( $params['xml_encoding'] ) )
            $params['xml_encoding'] = 'UTF-8';
        if( empty( $params['xml_version'] ) )
            $params['xml_version'] = '1.0';
        if( empty( $params['format_string'] ) )
            $params['format_string'] = false;
        if( empty( $params['line_indent'] ) )
            $params['line_indent'] = '';

        $return_str = '';
        if( empty( $params['root'] ) )
        {
            // Create root tag
            if( !empty( $params['add_xml_signature'] ) )
            {
                $return_str .= '<'.'?xml version="'.$params['xml_version'].'" encoding="'.$params['xml_encoding'].'" ?'.'>';

                if( !empty( $params['format_string'] ) )
                    $return_str .= "\n";
            }
            
            if( !empty( $params['root_tag'] ) )
            {
                $return_str .= '<'.$params['root_tag'].'>';
            }
        }

        foreach( $arr as $root_tag => $tag_arr )
        {
            $new_params = $params;
            $new_params['root'] = $root_tag;
            $new_params['line_indent'] .= "\t";

            $attrs_str = '';
            $content_str = '';
            $only_content = false;
            if( is_array( $tag_arr ) )
            {
                foreach( $tag_arr as $attr_key => $attr_val )
                {
                    if( $attr_key === '' )
                        continue;

                    if( is_numeric( $attr_key ) and is_array( $attr_val ) )
                    {
                        $content_str .= self::array_to_xml( array( $root_tag => $attr_val ), $new_params );
                        $only_content = true;
                        continue;
                    }

                    if( $attr_key === '#' )
                    {
                        if( is_array( $attr_val ) )
                            $content_str = self::array_to_xml( $attr_val, $new_params );
                        else
                            $content_str = self::xml_encode( $attr_val, array( 'xml_encoding' => $params['xml_encoding'] ) );
                    } elseif( substr( $attr_key, 0, 1 ) == '@' )
                    {
                        $attr_key = substr( $attr_key, 1 );
                        if( $attr_key === '' )
                            continue;

                        // we have an attribute
                        $attrs_str .= ' '.self::xml_encode( $attr_key, array( 'xml_encoding' => $params['xml_encoding'] ) ).'="'.self::xml_encode( $attr_val, array( 'xml_encoding' => $params['xml_encoding'] ) ).'"';
                    } elseif( is_array( $attr_val ) )
                    {
                        $content_str .= self::array_to_xml( array( $attr_key => $attr_val ), $new_params );
                    } else
                    {
                        $content_str .= (!empty( $params['format_string'] )?$params['line_indent']:'').'<'.$attr_key.'>'.
                        self::xml_encode( $attr_val, array( 'xml_encoding' => $params['xml_encoding'] ) ).
                                        '</'.$attr_key.'>'.(!empty( $params['format_string'] )?"\n":'');
                    }
                }
            } else
                $content_str = self::xml_encode( $tag_arr, array( 'xml_encoding' => $params['xml_encoding'] ) );

            if( empty( $only_content ) )
                $return_str .= (!empty( $params['format_string'] )?$params['line_indent']:'').'<'.$root_tag.$attrs_str.'>';

            $return_str .= $content_str;

            if( empty( $only_content ) )
                $return_str .= '</'.$root_tag.'>'.(!empty( $params['format_string'] )?"\n":'');
        }
        
        if( empty( $params['root'] ) and !empty( $params['root_tag'] ) )
            $return_str .= '</'.$params['root_tag'].'>';
        
        return $return_str;
    }

    static function xml_encode( $string, $params = false )
    {
        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['xml_encoding'] ) )
            $params['xml_encoding'] = 'UTF-8';
        if( empty( $params['convert_flags'] ) )
            $params['convert_flags'] = false;

        if( !is_string( $string ) or $string == '' )
            return '';

        if( $params['convert_flags'] == false )
        {
            $params['convert_flags'] = constant( 'ENT_QUOTES' );
            $params['convert_flags'] |= constant( 'ENT_SUBSTITUTE' );
            $params['convert_flags'] |= constant( 'ENT_DISALLOWED' );
            if( defined( 'ENT_XML1' ) )
                $params['convert_flags'] |= constant( 'ENT_XML1' );
        }

        return @htmlspecialchars( $string, $params['convert_flags'], $params['xml_encoding'] );
    }
}
