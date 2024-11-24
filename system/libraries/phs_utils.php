<?php

namespace phs\libraries;

/*! \file phs_utils.php
 *  \brief Contains PHS_Utils class (different utility functions...)
 *  \version 1.5
 */

class PHS_Utils extends PHS_Language
{
    // ! Error related to directories
    public const ERR_DIRECTORY = 1;

    public const PERIOD_FULL = 0, PERIOD_SECONDS = 1, PERIOD_MINUTES = 2, PERIOD_HOURS = 3, PERIOD_DAYS = 4, PERIOD_WEEKS = 5, PERIOD_MONTHS = 6, PERIOD_YEARS = 7;

    public const MAX_COUNT_FILESIZE = 2097152; // 2 Mb

    /**
     * Compares two numeric strings and says which one is bigger. This method simulates bccomp behaviour.
     * It doesn't take in consideration float representations of numbers (eg. 9.2233720368548E+18), only "plain" numbers
     * (eg. 3523466345634573567657456.33223, -34534534534534543543534.2244555)
     * On error, method returns false
     *
     * @param string|int|float $num1
     * @param string|int|float $num2
     * @return int
     */
    public static function numeric_string_compare($num1, $num2) : int
    {
        if (!is_string($num1)) {
            $num1 = (string)$num1;
        }
        if (!is_string($num2)) {
            $num2 = (string)$num2;
        }

        if ($num1 === '') {
            $num1 = '0';
        }
        if ($num2 === '') {
            $num2 = '0';
        }

        if (str_contains($num1, '.')
            && ($num1_arr = @explode('.', $num1))) {
            $num1_int = ($num1_arr[0] === '' ? '0' : $num1_arr[0]);
            if ('0' === ($num1_digits = ($num1_arr[1] === '' ? '0' : rtrim($num1_arr[1], '0')))) {
                $num1_digits = '0';
            }
        } else {
            $num1_int = $num1;
            $num1_digits = '0';
        }

        if (str_contains($num2, '.')
            && ($num2_arr = @explode('.', $num2))) {
            $num2_int = ($num2_arr[0] === '' ? '0' : $num2_arr[0]);
            if ('' === ($num2_digits = ($num2_arr[1] === '' ? '0' : rtrim($num2_arr[1], '0')))) {
                $num2_digits = '0';
            }
        } else {
            $num2_int = $num2;
            $num2_digits = '0';
        }

        $num1_positive = true;
        $num2_positive = true;

        if ($num1_int[0] === '-') {
            $num1_int = substr($num1_int, 1);
            $num1_positive = false;
        }

        if ($num2_int[0] === '-') {
            $num2_int = substr($num2_int, 1);
            $num2_positive = false;
        }

        if ($num1_positive xor $num2_positive) {
            if ($num1_positive) {
                return 1;
            }

            return -1;
        }

        $num1_int_len = strlen($num1_int);
        $num2_int_len = strlen($num2_int);

        if ($num1_int_len > $num2_int_len) {
            if ($num1_positive) {
                return 1;
            }

            return -1;
        }

        if ($num1_int_len < $num2_int_len) {
            if ($num1_positive) {
                return -1;
            }

            return 1;
        }

        for ($i = 0; $i < $num1_int_len; $i++) {
            $num1_digit = (int)$num1_int[$i];
            $num2_digit = (int)$num2_int[$i];
            if ($num1_digit === $num2_digit) {
                continue;
            }

            // If digits are not equal...
            if ($num1_digit > $num2_digit) {
                if ($num1_positive) {
                    return 1;
                }

                return -1;
            }
            if ($num1_positive) {
                return -1;
            }

            return 1;
        }

        $num1_digits_len = strlen($num1_digits);
        $num2_digits_len = strlen($num2_digits);

        $digits_len = min($num1_digits_len, $num2_digits_len);

        for ($i = 0; $i < $digits_len; $i++) {
            $num1_digit = (int)$num1_digits[$i];
            $num2_digit = (int)$num2_digits[$i];
            if ($num1_digit === $num2_digit) {
                continue;
            }

            // If digits are not equal...
            if ($num1_digit > $num2_digit) {
                if ($num1_positive) {
                    return 1;
                }

                return -1;
            }
            if ($num1_positive) {
                return -1;
            }

            return 1;
        }

        if ($num1_digits_len > $num2_digits_len) {
            if ($num1_positive) {
                return 1;
            }

            return -1;
        }
        if ($num1_digits_len < $num2_digits_len) {
            if ($num1_positive) {
                return -1;
            }

            return 1;
        }

        return 0;
    }

    /**
     * Returns details about running process with provided PID
     * @param int $pid Process id
     * @return array|false
     */
    public static function get_process_details(int $pid) : ?array
    {
        if (empty($pid)
         || !@is_dir('/proc')
         || !@is_readable('/proc')
         || !@is_dir('/proc/'.$pid)
         || !@is_readable('/proc/'.$pid)
         || !@file_exists('/proc/'.$pid.'/status')) {
            return null;
        }

        $cmd_line = '';
        if (@file_exists('/proc/'.$pid.'/cmdline')) {
            $cmd_line = trim(@file_get_contents('/proc/'.$pid.'/cmdline'));
        }

        $return_arr = [];
        $return_arr['pid'] = $pid;
        $return_arr['cmd_line'] = $cmd_line;
        $return_arr['parent_pid'] = 0;
        $return_arr['name'] = '';
        $return_arr['state'] = '';
        $return_arr['is_running'] = false;
        $return_arr['is_sleeping'] = false;
        $return_arr['is_idle'] = false;

        if (($status_file_str = @file_get_contents('/proc/'.$pid.'/status'))) {
            if (preg_match('@State:\s*(.*)@i', $status_file_str, $matches_arr)
             && is_array($matches_arr) && !empty($matches_arr[1])) {
                $return_arr['state'] = $matches_arr[1];
            }
            if (preg_match('@Name:\s*(.*)@i', $status_file_str, $matches_arr)
             && is_array($matches_arr) && !empty($matches_arr[1])) {
                $return_arr['name'] = $matches_arr[1];
            }
            if (preg_match('@PPid:\s*(.*)@i', $status_file_str, $matches_arr)
             && is_array($matches_arr) && !empty($matches_arr[1])) {
                $return_arr['parent_pid'] = (int)$matches_arr[1];
            }
        }

        if (!empty($return_arr['state'])) {
            $state_letter = strtoupper(substr($return_arr['state'], 0, 1));
            if ($state_letter === 'R') {
                $return_arr['is_running'] = true;
            } elseif ($state_letter === 'S') {
                $return_arr['is_sleeping'] = true;
            } elseif ($state_letter === 'I') {
                $return_arr['is_idle'] = true;
            }
        }

        return $return_arr;
    }

    public static function parse_period(int $seconds_span, array $params = []) : string
    {
        $params['only_big_part'] = !empty($params['only_big_part']);
        $params['big_part_if_zero'] = !empty($params['big_part_if_zero']);
        $params['start_timestamp'] = (int)($params['start_timestamp'] ?? time());

        if (empty($params['show_period']) || $params['show_period'] > self::PERIOD_YEARS) {
            $params['show_period'] = self::PERIOD_FULL;
        }

        $nowtime = $params['start_timestamp'];
        $pasttime = $nowtime - $seconds_span;

        try {
            $nowdate_obj = new \DateTime('@'.$nowtime);
            $pastdate_obj = new \DateTime('@'.$pasttime);
        } catch (\Exception $e) {
            return '#Cannot_parse_period#';
        }

        $interval = $nowdate_obj->diff($pastdate_obj);

        $years = $interval->y;
        $months = $interval->m;
        $days = $interval->d;
        $hours = $interval->h;
        $minutes = $interval->i;
        $seconds = $interval->s;

        switch ($params['show_period']) {
            default:
            case self::PERIOD_FULL:
                if (!empty($params['only_big_part'])) {
                    $return_arr = [];
                    if (!empty($years)) {
                        $return_arr[] = $years.' '.($years > 1 ? self::_t('years') : self::_t('year'));
                    } elseif (!empty($months)) {
                        $return_arr[] = $months.' '.($months > 1 ? self::_t('months') : self::_t('month'));
                    } elseif (!empty($days)) {
                        $return_arr[] = $days.' '.($days > 1 ? self::_t('days') : self::_t('day'));
                    } elseif (!empty($hours)) {
                        $return_arr[] = $hours.' '.($hours > 1 ? self::_t('hours') : self::_t('hour'));
                    } elseif (!empty($minutes)) {
                        $return_arr[] = $minutes.' '.($minutes > 1 ? self::_t('minutes') : self::_t('minute'));
                    } elseif (!empty($seconds)) {
                        $return_arr[] = $seconds.' '.($seconds > 1 ? self::_t('seconds') : self::_t('second'));
                    }
                } else {
                    $return_arr = [];
                    if (!empty($years)) {
                        $return_arr[] = $years.' '.($years > 1 ? self::_t('years') : self::_t('year'));
                    }
                    if (!empty($months)) {
                        $return_arr[] = $months.' '.($months > 1 ? self::_t('months') : self::_t('month'));
                    }
                    if (!empty($days)) {
                        $return_arr[] = $days.' '.($days > 1 ? self::_t('days') : self::_t('day'));
                    }
                    if (!empty($hours)) {
                        $return_arr[] = $hours.' '.($hours > 1 ? self::_t('hours') : self::_t('hour'));
                    }
                    if (!empty($minutes)) {
                        $return_arr[] = $minutes.' '.($minutes > 1 ? self::_t('minutes') : self::_t('minute'));
                    }
                    if (!empty($seconds)) {
                        $return_arr[] = $seconds.' '.($seconds > 1 ? self::_t('seconds') : self::_t('second'));
                    }
                }

                if (empty($return_arr)) {
                    $return_arr[] = '0 '.self::_t('seconds');
                }

                return implode(', ', $return_arr);
            case self::PERIOD_SECONDS:
                return $seconds_span.' '.($seconds_span > 1 ? self::_t('seconds') : self::_t('second'));
            case self::PERIOD_MINUTES:
                $minutes_diff = floor($seconds_span / 60);
                if ($minutes_diff === 0.0
                 && !empty($params['big_part_if_zero'])) {
                    $params['show_period'] = self::PERIOD_FULL;
                    $params['only_big_part'] = true;

                    return self::parse_period($seconds_span, $params);
                }

                return $minutes_diff.' '.($minutes_diff > 1 ? self::_t('minutes') : self::_t('minute'));
            case self::PERIOD_HOURS:
                $hours_diff = floor($seconds_span / 3600);
                if ($hours_diff === 0.0
                 && !empty($params['big_part_if_zero'])) {
                    $params['show_period'] = self::PERIOD_FULL;
                    $params['only_big_part'] = true;

                    return self::parse_period($seconds_span, $params);
                }

                return $hours_diff.' '.($hours_diff > 1 ? self::_t('hours') : self::_t('hour'));
            case self::PERIOD_DAYS:
                $days_diff = floor($seconds_span / 86400);
                if ($days_diff === 0.0
                 && !empty($params['big_part_if_zero'])) {
                    $params['show_period'] = self::PERIOD_FULL;
                    $params['only_big_part'] = true;

                    return self::parse_period($seconds_span, $params);
                }

                return $days_diff.' '.($days_diff > 1 ? self::_t('days') : self::_t('day'));
            case self::PERIOD_WEEKS:
                $weeks_diff = floor($seconds_span / 604800);
                if ($weeks_diff === 0.0
                 && !empty($params['big_part_if_zero'])) {
                    $params['show_period'] = self::PERIOD_FULL;
                    $params['only_big_part'] = true;

                    return self::parse_period($seconds_span, $params);
                }

                return $weeks_diff.' '.($weeks_diff > 1 ? self::_t('weeks') : self::_t('week'));
            case self::PERIOD_MONTHS:
                if ($months === 0
                 && !empty($params['big_part_if_zero'])) {
                    $params['show_period'] = self::PERIOD_FULL;
                    $params['only_big_part'] = true;

                    return self::parse_period($seconds_span, $params);
                }

                return $months.' '.($months > 1 ? self::_t('months') : self::_t('month'));
            case self::PERIOD_YEARS:
                if ($years === 0
                 && !empty($params['big_part_if_zero'])) {
                    $params['show_period'] = self::PERIOD_FULL;
                    $params['only_big_part'] = true;

                    return self::parse_period($seconds_span, $params);
                }

                return $years.' '.($years > 1 ? self::_t('years') : self::_t('year'));
        }
    }

    /**
     * Checks if current request is made by a well known web bot
     * @param bool|array $params
     *
     * @return array
     */
    public static function check_crawler_request($params = false)
    {
        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        if (empty($params['user_agent'])
         && !empty($_SERVER) && isset($_SERVER['HTTP_USER_AGENT'])) {
            $params['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        } else {
            $params['user_agent'] = '';
        }

        $return_arr = [];
        $return_arr['is_bot'] = false;
        $return_arr['bot_name'] = '';

        if (false !== stripos($params['user_agent'], 'googlebot')) {
            $return_arr['is_bot'] = true;
            $return_arr['bot_name'] = 'Google';
        } elseif (false !== stripos($params['user_agent'], 'msnbot') || false !== stripos($params['user_agent'], 'msrbot')) {
            $return_arr['is_bot'] = true;
            $return_arr['bot_name'] = 'MSN';
        } elseif (false !== stripos($params['user_agent'], 'bingbot') || false !== stripos($params['user_agent'], 'bingpreview')) {
            $return_arr['is_bot'] = true;
            $return_arr['bot_name'] = 'Bing';
        }

        return $return_arr;
    }

    public static function mkdir_tree(string | array $segments, array $params = []) : bool
    {
        self::st_reset_error();

        if (!isset($segments) || $segments === '') {
            self::st_set_error(self::ERR_DIRECTORY, self::_t('Cannot create empty directory.'));

            return false;
        }

        $params['root'] ??= '';
        $params['dir_mode'] ??= 0775;

        $segments_arr = $segments;
        if (!is_array($segments)) {
            $segments_arr = explode('/', $segments);
        }

        $segments_quick = implode('/', $segments_arr);
        if (@file_exists($segments_quick) && @is_dir($segments_quick)) {
            return true;
        }

        $segments_path = rtrim((string)$params['root'], '/\\');

        foreach ($segments_arr as $dir_segment) {
            if (empty($dir_segment)) {
                if ($segments_path === '') {
                    $segments_path .= '/';
                }
                continue;
            }

            $segments_path .= ($segments_path === '/' ? '' : '/').$dir_segment;

            if (@file_exists($segments_path)) {
                if (!@is_dir($segments_path)) {
                    self::st_set_error(self::ERR_DIRECTORY, self::_t('[%s] is not a directory.', $segments_path));

                    return false;
                }

                continue;
            }

            if (!@mkdir($segments_path) && !@is_dir($segments_path)) {
                self::st_set_error(self::ERR_DIRECTORY, self::_t('Cannot create directory [%s]', $segments_path));

                return false;
            }

            if (!empty($params['dir_mode'])) {
                @chmod($segments_path, $params['dir_mode']);
            }
        }

        return true;
    }

    public static function get_files_recursive(string $directory, array $params = []) : array
    {
        if (str_ends_with($directory, '/')) {
            $directory = substr($directory, 0, -1);
        }

        if (!@file_exists($directory) || !@is_dir($directory)) {
            return [];
        }

        $params['basename_regex'] ??= '';
        $params['accept_symlinks'] = !empty($params['accept_symlinks']);

        if (empty($params['extensions_arr']) || !is_array($params['extensions_arr'])) {
            $params['extensions_arr'] = [];
        }

        // you don't have to pass {level} as it is used internally
        $params['{level}'] ??= 0;

        if (!empty($params['extensions_arr'])) {
            $new_extensions_arr = [];
            foreach ($params['extensions_arr'] as $ext) {
                $new_extensions_arr[] = strtolower($ext);
            }

            $params['extensions_arr'] = $new_extensions_arr;
        }

        $found_files = [];
        if (($directory_content = @glob($directory.'/*'))) {
            foreach ($directory_content as $filename) {
                if ($filename === '.' || $filename === '..') {
                    continue;
                }

                if (@is_file($filename)
                    || (!empty($params['accept_symlinks']) && @is_link($filename))) {
                    if (!empty($params['basename_regex'])
                        && ($base_name = @basename($filename))
                        && !@preg_match($params['basename_regex'], $base_name)) {
                        continue;
                    }

                    $file_ext = '';
                    if (($file_arr = explode('.', $filename))
                        && count($file_arr) > 1) {
                        $file_ext = array_pop($file_arr);
                    }

                    if (empty($params['extensions_arr'])
                        || empty($file_ext)
                        || in_array(strtolower($file_ext), $params['extensions_arr'], true)) {
                        $found_files[] = [$filename => 1];
                    }

                    continue;
                }

                if (@is_dir($filename)) {
                    $new_params = $params;
                    $new_params['{level}']++;

                    if (($dir_found_files = self::get_files_recursive($filename, $new_params))) {
                        $found_files[] = $dir_found_files;
                    }
                }
            }

            $found_files = array_merge(...$found_files);
        }

        // top level...
        if (empty($params['{level}']) && !empty($found_files)) {
            $found_files = array_keys($found_files);
        }

        return $found_files;
    }

    /**
     * @param string $directory
     * @param bool|array $params
     *
     * @return bool
     */
    public static function rmdir_tree($directory, $params = false)
    {
        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        if (!isset($params['recursive'])) {
            $params['recursive'] = true;
        }

        // Delete directory only if there are no files, symlinks or directories in it
        if (!isset($params['only_if_empty'])) {
            $params['only_if_empty'] = false;
        } else {
            $params['only_if_empty'] = (!empty($params['only_if_empty']));
        }

        // Delete directory only if there are no files or symlinks
        // !!! NOTE: If glob() returns empty directories before any files or symlinks those empty directories will be deleted
        // util we find a file or symlink. This functionality will not check for file existence first in dir tree!!!
        if (!isset($params['only_if_no_files'])) {
            $params['only_if_no_files'] = false;
        } else {
            $params['only_if_no_files'] = (!empty($params['only_if_no_files']));
        }

        $directory = rtrim($directory, '/\\');

        if (!@file_exists($directory) || !@is_dir($directory)) {
            return true;
        }

        $got_errors = false;
        if (($directory_content = @glob($directory.'/*'))) {
            foreach ($directory_content as $filename) {
                if ($filename === '.' || $filename === '..') {
                    continue;
                }

                if (!empty($params['only_if_empty'])) {
                    return false;
                }

                if (@is_file($filename) || @is_link($filename)) {
                    if (!empty($params['only_if_no_files'])) {
                        return false;
                    }

                    @unlink($filename);
                    continue;
                }

                if (!empty($params['recursive']) && @is_dir($filename)) {
                    if (!self::rmdir_tree($filename, $params)) {
                        $got_errors = true;
                    }

                    @rmdir($filename);
                }
            }
        }

        $return_val = @rmdir($directory);

        if (empty($return_val) && !empty($got_errors)) {
            return false;
        }

        return $return_val;
    }

    public static function mimetype(string $file, array $params = []) : ?string
    {
        $params['virtual_file'] = !empty($params['virtual_file']);

        if ($file === ''
         || (!$params['virtual_file']
             && (!@file_exists($file) || !@is_readable($file)))) {
            return null;
        }

        $file_mime_type = '';
        if (empty($params['virtual_file'])
            && @function_exists('finfo_open')) {
            if (!($flags = constant('FILEINFO_MIME'))) {
                $flags = 0;
            }

            if (defined('FILEINFO_PRESERVE_ATIME')) {
                $flags |= constant('FILEINFO_PRESERVE_ATIME');
            }

            if (!empty($flags)
                && ($finfo = @finfo_open($flags))) {
                $file_mime_type = @finfo_file($finfo, $file);
                @finfo_close($finfo);
            }
        }

        if (empty($params['virtual_file'])
            && empty($file_mime_type)
            && ($cmd_buf = @exec('file -bi '.@escapeshellarg($file)))) {
            $file_mime_type = trim($cmd_buf);
        }

        if (empty($file_mime_type)) {
            $file_ext = '';
            if (($file_dots_arr = explode('.', $file)) && is_array($file_dots_arr) && count($file_dots_arr) > 1) {
                $file_ext = array_pop($file_dots_arr);
            }

            $file_ext = strtolower($file_ext);

            switch ($file_ext) {
                default:
                    $file_mime_type = null;
                    break;

                case 'js':
                    $file_mime_type = 'application/x-javascript';
                    break;

                case 'json':
                    $file_mime_type = 'application/json';
                    break;

                case 'jpg':
                case 'jpeg':
                case 'jpe':
                    $file_mime_type = 'image/jpeg';
                    break;

                case 'png':
                case 'gif':
                case 'bmp':
                case 'tiff':
                    $file_mime_type = 'image/'.$file_ext;
                    break;

                case 'css':
                    $file_mime_type = 'text/css';
                    break;

                case 'xml':
                    $file_mime_type = 'application/xml';
                    break;

                case 'doc':
                case 'docx':
                    $file_mime_type = 'application/msword';
                    break;

                case 'xls':
                case 'xlt':
                case 'xlm':
                case 'xld':
                case 'xla':
                case 'xlc':
                case 'xlw':
                case 'xll':
                    $file_mime_type = 'application/vnd.ms-excel';
                    break;

                case 'ppt':
                case 'pps':
                    $file_mime_type = 'application/vnd.ms-powerpoint';
                    break;

                case 'rtf':
                    $file_mime_type = 'application/rtf';
                    break;

                case 'pdf':
                    $file_mime_type = 'application/pdf';
                    break;

                case 'html':
                case 'htm':
                case 'php':
                    $file_mime_type = 'text/html';
                    break;

                case 'txt':
                    $file_mime_type = 'text/plain';
                    break;

                case 'csv':
                    $file_mime_type = 'text/csv';
                    break;

                case 'mpeg':
                case 'mpg':
                case 'mpe':
                    $file_mime_type = 'video/mpeg';
                    break;

                case 'mp3':
                    $file_mime_type = 'audio/mpeg3';
                    break;

                case 'wav':
                    $file_mime_type = 'audio/wav';
                    break;

                case 'aiff':
                case 'aif':
                    $file_mime_type = 'audio/aiff';
                    break;

                case 'avi':
                    $file_mime_type = 'video/avi';
                    break;

                case 'wmv':
                    $file_mime_type = 'video/x-ms-wmv';
                    break;

                case 'mov':
                    $file_mime_type = 'video/quicktime';
                    break;

                case 'mp4':
                    $file_mime_type = 'video/mp4';
                    break;

                case 'webm':
                    $file_mime_type = 'video/webm';
                    break;

                case 'zip':
                    $file_mime_type = 'application/zip';
                    break;

                case 'tar':
                    $file_mime_type = 'application/x-tar';
                    break;

                case 'swf':
                    $file_mime_type = 'application/x-shockwave-flash';
                    break;
            }
        }

        return $file_mime_type;
    }

    public static function mypathinfo($str) : array
    {
        $ret = [];
        $ret['dirname'] = '';
        $ret['filename'] = '';
        $ret['basename'] = '';
        $ret['extension'] = '';

        $dir_file = explode('/', $str);
        $knt = count($dir_file);

        if ($knt <= 1) {
            $ret['dirname'] = '';
            $file = explode('.', $str);
        } elseif ($dir_file[$knt - 1] === '') {
            $ret['dirname'] = implode('/', array_slice($dir_file, 0, -1));
            $file = null;
        } else {
            $ret['dirname'] = implode('/', array_slice($dir_file, 0, -1));
            $file = explode('.', $dir_file[$knt - 1]);
        }

        if ($file !== null) {
            if (($dot_count = count($file)) <= 1) {
                $ret['basename'] = implode('.', $file);
                $ret['extension'] = '';
                $ret['filename'] = $ret['basename'];
            } else {
                $ret['basename'] = implode('.', array_slice($file, 0, -1));
                $ret['extension'] = $file[$dot_count - 1];
                $ret['filename'] = $ret['basename'].'.'.$ret['extension'];
            }
        }

        return $ret;
    }

    /**
     * @param string $str URL to be parsed
     *
     * @return array Array with parts of parsed URL
     */
    public static function myparse_url(string $str) : array
    {
        $ret = [];
        $ret['user'] = '';
        $ret['pass'] = '';
        $ret['host'] = '';
        $ret['port'] = '';
        $ret['path'] = '';

        $mystr = $str;
        $res = explode('#', $mystr, 2);
        $ret['anchor'] = $res[1] ?? '';
        $mystr = $res[0];

        $res = explode('?', $mystr, 2);
        $ret['query'] = $res[1] ?? '';
        $mystr = $res[0];

        $res = explode('://', $mystr, 2);
        if (isset($res[1])) {
            $ret['scheme'] = $res[0];
            $mystr = $res[1];
        } else {
            $mystr = $res[0];

            if (strpos($mystr, '//') === 0) {
                $ret['scheme'] = '//';
                $mystr = substr($mystr, 2);
            } else {
                $ret['scheme'] = '';
            }
        }

        $dotpos = strpos($mystr, '.');
        $slashpos = strpos($mystr, '/');

        $path_present = true;
        $host_present = true;
        // host is not present - only the path might be present
        if ($ret['scheme'] === '' && $dotpos === false) {
            $host_present = false;
        }

        // no path is present or only a directory name is present
        if ($ret['scheme'] === '' && $slashpos === false) {
            $host_present = true;
            $path_present = false;
        }

        if ($host_present && $dotpos !== false) {
            if ($slashpos !== false) {
                if ($dotpos > $slashpos) {
                    // dot is after / so it must be a path
                    $host_present = false;
                    $path_present = true;
                } elseif ($ret['scheme'] === '') {
                    // no scheme given, might be a server path...
                    $host_present = false;
                    $path_present = true;
                }
            } elseif ($ret['scheme'] === '') {
                // we don't have any slashes... might be a filename/dir name in current folder
                $host_present = false;
                $path_present = true;
            }
        }

        if ($path_present) {
            if (!$host_present) {
                $ret['path'] = $mystr;
            } else {
                $res = explode('/', $mystr, 2);
                if (isset($res[1]) && $res[1] !== '') {
                    $ret['path'] = $res[1];
                } else {
                    $ret['path'] = '';
                }
                $mystr = $res[0];
            }
        }

        $host_port = '';
        $user_pass = '';
        if ($host_present) {
            if (str_contains($mystr, '@')) {
                $res = explode('@', $mystr, 2);
                $user_pass = $res[0];
                $host_port = $res[1] ?? '';
            } else {
                $host_port = $mystr;
            }
        }

        if (str_contains($host_port, ':')) {
            $res = explode(':', $host_port, 2);
            $ret['host'] = $res[0];
            $ret['port'] = $res[1];
        } else {
            $ret['host'] = $host_port;
            $ret['port'] = '';
        }

        if ($user_pass !== '') {
            $res = explode(':', $user_pass, 2);
            $ret['user'] = $res[0];
            $ret['pass'] = $res[1] ?? '';
        } else {
            $ret['user'] = '';
            $ret['pass'] = '';
        }

        return $ret;
    }

    public static function rebuild_url(array $url_parts) : string
    {
        if (!$url_parts) {
            return '';
        }

        $parts_arr = ['scheme', 'user', 'pass', 'host', 'port', 'path', 'query', 'anchor'];
        foreach ($parts_arr as $part_field) {
            if (!isset($url_parts[$part_field])) {
                $url_parts[$part_field] = '';
            }
        }

        $final_url = $url_parts['scheme'];

        if ($url_parts['scheme'] !== '//') {
            $final_url .= (!empty($url_parts['scheme']) ? ':' : '').'//';
        }

        $final_url .= $url_parts['user'];
        $final_url .= (!empty($url_parts['pass']) ? ':' : '').$url_parts['pass'].((!empty($url_parts['user']) || !empty($url_parts['pass'])) ? '@' : '');
        $final_url .= $url_parts['host'];
        $final_url .= (!empty($url_parts['port']) ? ':' : '').$url_parts['port'];
        $final_url .= $url_parts['path'];
        $final_url .= (!empty($url_parts['query']) ? '?' : '').$url_parts['query'];
        $final_url .= (!empty($url_parts['anchor']) ? '#' : '').$url_parts['anchor'];

        return $final_url;
    }

    /**
     * @param string $source Source image file
     * @param string $destination Destination image file
     * @param string $watermark Watermark image file
     * @param bool|array $params
     *
     * @return array|bool
     */
    public static function quick_watermark($source, $destination, $watermark, $params = false)
    {
        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        if (empty($source) || empty($watermark)
         || !@file_exists($source) || !@file_exists($watermark)) {
            return false;
        }

        if (!isset($params['output_details'])) {
            $params['output_details'] = true;
        }
        if (empty($params['overwrite_destination'])) {
            $params['overwrite_destination'] = false;
        }
        if (empty($params['composite_bin'])) {
            $params['composite_bin'] = 'composite';
        }

        if (empty($params['watermark'])) {
            $params['watermark'] = '20%';
        } else {
            $params['watermark'] = trim($params['watermark']);
        }
        if (empty($params['gravity'])) {
            $params['gravity'] = 'SouthEast';
        } else {
            $params['gravity'] = trim($params['gravity']);
        }

        if (@file_exists($destination)) {
            if (@is_dir($destination)
             || empty($params['overwrite_destination'])
             || !@unlink($destination)) {
                return false;
            }
        }

        @exec($params['composite_bin'].' -watermark '.escapeshellarg($params['watermark']).' '
               .' -gravity '.escapeshellarg($params['gravity']).' '
               .' '.escapeshellarg($watermark).' '.escapeshellarg($source).' '.escapeshellarg($destination));

        if (!@file_exists($destination)) {
            return false;
        }

        $return_val = true;
        if (!empty($params['output_details'])
         && ($output_details = @getimagesize($destination))) {
            $return_val = [];
            $return_val['width'] = $output_details[0];
            $return_val['height'] = $output_details[1];
        }

        return $return_val;
    }

    public static function quick_convert($source, $destination, $params)
    {
        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        if ((empty($params['width']) && empty($params['height']))
         || !@file_exists($source)) {
            return false;
        }

        if (empty($params['width'])) {
            $params['width'] = 0;
        } else {
            $params['width'] = (int)$params['width'];
        }

        if (empty($params['height'])) {
            $params['height'] = 0;
        } else {
            $params['height'] = (int)$params['height'];
        }

        if (!isset($params['output_details'])) {
            $params['output_details'] = true;
        }
        if (empty($params['overwrite_destination'])) {
            $params['overwrite_destination'] = false;
        }
        if (empty($params['convert_bin'])) {
            $params['convert_bin'] = 'convert';
        }
        if (empty($params['background'])) {
            $params['background'] = 'black';
        } else {
            $params['background'] = trim($params['background']);
        }

        if (@file_exists($destination)) {
            if (@is_dir($destination)
             || empty($params['overwrite_destination'])
             || !@unlink($destination)) {
                return false;
            }
        }

        $size_str = '';
        if (!empty($params['width'])) {
            $size_str .= $params['width'];
        }
        $size_str .= 'x';
        if (!empty($params['height'])) {
            $size_str .= $params['height'];
        }

        @exec(escapeshellarg($params['convert_bin']).' '.escapeshellarg($source).' -resize '.$size_str.'\> -background \''.escapeshellarg($params['background']).'\' '
               .' -gravity center -extent \''.$size_str.'\' '.escapeshellarg($destination));

        if (!@file_exists($destination)) {
            return false;
        }

        $return_val = true;
        if (!empty($params['output_details'])
         && ($output_details = @getimagesize($destination))) {
            $return_val = [];
            $return_val['width'] = $output_details[0];
            $return_val['height'] = $output_details[1];
        }

        return $return_val;
    }

    /**
     * @param string $url URL where we send the request
     * @param bool|array $params {
     *                           cURL parameters
     * @type array userpass {
     *             'user' @type string User used in Basic Authentication
     *             'pass' @type string Pass used in Basic Authentication
     *             } Basic Authentication
     * @type bool follow_location Should request follow location if redirected
     * @type int timeout Timeout in seconds
     * @type string user_agent User-agent to be used for this request
     * @type string[string] extra_get_params Extra parameters to be sent in GET (variable name as key, variable value as value)
     * @type string raw_post_str Raw POST string
     * @type string[string] header_keys_arr Headers to be sent. Header name as key and header value as value
     * @type string[int] header_arr Header lines to be sent in this request
     * @type string[string] post_arr POST to be passed in the request (variable name as key, variable value as value)
     * @type string http_method Method to be sent in this request (eg. GET, POST, PUT, PATCH, DELETE, etc)
     *              }
     * @return array|bool {
     *                    'response' @type string
     *                    'http_code' @type int HTTP code returned by request
     *                    'request_details' @type array result of curl_getinfo() on cURL resource
     *                    'request_error_msg' @type string result of curl_error() on cURL resource
     *                    'request_error_no' @type int result of curl_errno() on cURL resource
     *                    'request_params' @type array Request parameters ($params array populated with default values as used in the request)
     *                    } cURL result array or false on error
     */
    public static function quick_curl($url, $params = false)
    {
        if (!($ch = @curl_init())) {
            return false;
        }

        if (!is_array($params)) {
            $params = [];
        }

        $params['obfuscate_request_details_header']
            = !isset($params['obfuscate_request_details_header']) || !empty($params['obfuscate_request_details_header']);
        $params['ssl_verification'] = !empty($params['ssl_verification']);
        $params['follow_location'] = !empty($params['follow_location']);
        $params['inhibit_100_continue'] = (bool)($params['inhibit_100_continue'] ?? true);
        $params['timeout'] = (int)($params['timeout'] ?? 30);

        if (empty($params['userpass']) || !is_array($params['userpass'])
            || !isset($params['userpass']['user']) || !isset($params['userpass']['pass'])) {
            $params['userpass'] = null;
        }

        if (empty($params['user_agent'])) {
            $params['user_agent'] = 'PHS/PHS_Utils v'.PHS_VERSION;
        }
        if (empty($params['extra_get_params']) || !is_array($params['extra_get_params'])) {
            $params['extra_get_params'] = [];
        }

        if (!isset($params['raw_post_str'])) {
            $params['raw_post_str'] = '';
        }
        if (empty($params['header_keys_arr']) || !is_array($params['header_keys_arr'])) {
            $params['header_keys_arr'] = [];
        }
        if (empty($params['header_arr']) || !is_array($params['header_arr'])) {
            $params['header_arr'] = [];
        }

        // Convert old format to new format...
        if (!empty($params['header_arr'])) {
            foreach ($params['header_arr'] as $header_txt) {
                $header_value_arr = explode(':', $header_txt);
                $key = trim($header_value_arr[0]);
                $val = '';
                if (isset($header_value_arr[1])) {
                    $val = ltrim($header_value_arr[1]);
                }

                $params['header_keys_arr'][$key] = $val;
            }

            // Reset raw headers array as we moved them to key => value pairs...
            $params['header_arr'] = [];
        }

        $post_string = '';
        if (!empty($params['post_arr']) && is_array($params['post_arr'])) {
            foreach ($params['post_arr'] as $key => $val) {
                // workaround for '@/local/file' fields...
                if (!is_scalar($val)
                    || str_starts_with($val, '@')) {
                    $post_string = $params['post_arr'];
                    break;
                }

                $post_string .= $key.'='.utf8_encode(rawurlencode($val)).'&';
            }

            if (is_string($post_string) && $post_string !== '') {
                $post_string = substr($post_string, 0, -1);
            }

            if (!isset($params['header_keys_arr']['Content-Type'])) {
                $params['header_keys_arr']['Content-Type'] = 'application/x-www-form-urlencoded';
            }
        }

        if ($params['raw_post_str'] !== '') {
            $post_string .= $params['raw_post_str'];
        }

        if ($post_string !== '') {
            @curl_setopt($ch, CURLOPT_POST, true);
            @curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
        }

        if (count($params['extra_get_params'])) {
            if (!str_contains($url, '?')) {
                $url .= '?';
            }

            $ends_in_qmark = (str_ends_with($url, '?'));
            $first_and = true;
            foreach ($params['extra_get_params'] as $key => $val) {
                $url .= ((!$first_and || !$ends_in_qmark) ? '&' : '').$key.'='.rawurlencode($val);
                $first_and = false;
            }
        }

        $has_expect_header = false;
        if (!empty($params['header_keys_arr']) && is_array($params['header_keys_arr'])) {
            foreach ($params['header_keys_arr'] as $key => $val) {
                if (strtolower($key) === 'expect') {
                    $has_expect_header = true;
                }
                $params['header_arr'][] = $key.': '.$val;
            }
        }

        if (!$has_expect_header && $params['inhibit_100_continue']) {
            $params['header_arr'][] = 'Expect:';
        }

        if (!empty($params['header_arr']) && is_array($params['header_arr'])) {
            @curl_setopt($ch, CURLOPT_HTTPHEADER, $params['header_arr']);
        }

        if (!empty($params['user_agent'])) {
            @curl_setopt($ch, CURLOPT_USERAGENT, $params['user_agent']);
        }

        if (!empty($params['http_method']) && is_string($params['http_method'])) {
            @curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper(trim($params['http_method'])));
        }

        @curl_setopt($ch, CURLOPT_URL, $url);
        @curl_setopt($ch, CURLOPT_HEADER, 0);
        if (defined('CURLINFO_HEADER_OUT')) {
            @curl_setopt($ch, constant('CURLINFO_HEADER_OUT'), true);
        }
        @curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if (empty($params['ssl_verification'])) {
            @curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            @curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }
        @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $params['follow_location']);
        @curl_setopt($ch, CURLOPT_TIMEOUT, $params['timeout']);

        if (!empty($params['userpass'])) {
            @curl_setopt($ch, CURLOPT_USERPWD, $params['userpass']['user'].':'.$params['userpass']['pass']);
        }

        $curl_response = @curl_exec($ch);

        $return_params = $params;
        if (isset($return_params['userpass']['pass'])) {
            $return_params['userpass']['pass'] = '(undisclosed_pass)';
        }

        $response = [
            'response'          => $curl_response,
            'http_code'         => 0,
            'request_details'   => @curl_getinfo($ch),
            'request_error_msg' => @curl_error($ch),
            'request_error_no'  => @curl_errno($ch),
            'request_params'    => $return_params,
        ];

        if (isset($response['request_details']['http_code'])) {
            $response['http_code'] = (int)$response['request_details']['http_code'];
        }

        if ($params['obfuscate_request_details_header']
           && !empty($response['request_details']['request_header'])) {
            $response['request_details']['request_header']
                = self::obfuscate_authorization_header_from_string($response['request_details']['request_header']);
        }

        @curl_close($ch);

        return $response;
    }

    public static function obfuscate_authorization_header_from_string(string $headers) : string
    {
        if (preg_match('/^(Authorization)\s*:\s*?(.*)\s*$/miU', $headers, $matches)) {
            $auth_str = explode(' ', $matches[2] ?? '', 2);
            $headers = str_replace(
                $matches[0],
                ($matches[1] ?? 'Authorization').': '
                .(!empty($auth_str[1]) ? $auth_str[0].' ' : '')
                .'(Obfuscated_authorization)',
                $headers
            );
        }

        return $headers;
    }

    /**
     * @param string $attr_str String in node to be checked for attributes
     * @param bool|array $params Parameters to be used
     *
     * @return array|bool array of attributes or false on error
     */
    public static function xml_parse_node_attributes($attr_str, $params = false)
    {
        if (empty($attr_str) || !is_string($attr_str)) {
            return false;
        }

        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        if (!isset($params['attributes_to_lowercase'])) {
            $params['attributes_to_lowercase'] = false;
        }

        $reg_exp = "/(\S+)=(\"[^\"]*\"|'[^']*'|[^\s])/Um";
        preg_match_all($reg_exp, $attr_str, $matches, PREG_SET_ORDER);

        $attrs_arr = [];
        foreach ($matches as $match_arr) {
            if (empty($match_arr[1]) || !isset($match_arr[2])) {
                continue;
            }

            if (!empty($params['keys_to_lowercase'])) {
                $key = strtolower($match_arr[1]);
            } else {
                $key = $match_arr[1];
            }

            $attrs_arr[$key] = trim($match_arr[2], '\'"');
        }

        return $attrs_arr;
    }

    /**
     * @param string $buf Buffer to be parsed for XML
     * @param bool|array $params Parameters to be used
     *
     * @return bool|array Array with nodes
     */
    public static function xml_to_array($buf, $params = false)
    {
        if (empty($buf)) {
            return [];
        }

        // $reg_exp = "/<(\w+)[^>]*>(.*?)<\/\\1>/s";
        $reg_exp = "/<([a-zA-Z0-9_\-]+)(\s+[^>]*|)>(.*)<\/\\1>/Usmi";
        preg_match_all($reg_exp, $buf, $matches, PREG_SET_ORDER);

        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        if (!isset($params['keys_to_lowercase'])) {
            $params['keys_to_lowercase'] = false;
        }
        if (!isset($params['attributes_to_lowercase'])) {
            $params['attributes_to_lowercase'] = $params['keys_to_lowercase'];
        }

        $return_arr = false;
        foreach ($matches as $key => $match_arr) {
            if (empty($match_arr) || !is_array($match_arr)
             || empty($match_arr[1])) {
                continue;
            }

            if (!empty($params['keys_to_lowercase'])) {
                $key = strtolower($match_arr[1]);
            } else {
                $key = $match_arr[1];
            }

            $node_arr = [];
            // check content
            if (isset($match_arr[3])
             && ($node_content = trim($match_arr[3])) !== '') {
                if (!($node_arr = self::xml_to_array(trim($match_arr[3])))) {
                    $node_arr = ['#' => $match_arr[3]];
                } else {
                    if (!is_array($node_arr)) {
                        $node_arr = ['#' => $node_arr];
                    } elseif (!isset($node_arr['#'])) {
                        $node_arr['#'] = '';
                    }
                }
            }

            // check attributes
            if (!empty($match_arr[2])
             && ($attributes_arr = self::xml_parse_node_attributes($match_arr[2]))) {
                if (!is_array($node_arr)) {
                    $node_arr = ['#' => $node_arr];
                }

                foreach ($attributes_arr as $attr_key => $attr_val) {
                    if (!empty($params['attributes_to_lowercase'])) {
                        $attr_key = strtolower($attr_key);
                    }

                    $node_arr['@'.$attr_key] = $attr_val;
                }
            }

            if (isset($return_arr[$key])) {
                if (empty($return_arr[$key][0]) || !is_array($return_arr[$key][0])) {
                    $return_arr[$key] = [0 => $return_arr[$key]];
                }

                $return_arr[$key][] = $node_arr;
            } else {
                $return_arr[$key] = $node_arr;
            }
        }

        if ($return_arr === false
         && $buf !== '') {
            return $buf;
        }

        return $return_arr;
    }

    // ! Parses an array and returns XML string
    /*
     * Example:
     *
     * $xml_arr = [
     *   'gigi' => [ '@attr1' => 1, '@attr2' => 'attr2', '#' => 'bubu' ],
     *   'list' => [ 'item' => [ 0 => [ 'name' => 'vasile1', 'age' => 12 ], 1 => [ 'name' => 'vasile2', 'age' => 12 ], 2 => [ 'name' => 'vasile3', 'age' => 12 ] ] ],
     *   'gigi2' => [ '@attr1' => 1, '@attr2' => 'attr2', '#' => [ 'key1' => 1, 'key2' => 2 ] ],
     *   ];
     *
     * PHS_Utils::array_to_xml( $xml_arr, [ 'root_tag' => 'root' ] );
     *
     **/
    /**
     * @param array $arr Array of nodes
     * @param bool|array $params Parameters to be used
     *
     * @return string Representation of XML as string
     */
    public static function array_to_xml($arr, $params = false)
    {
        if (empty($arr) || !is_array($arr)) {
            return '';
        }

        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        if (empty($params['root'])) {
            $params['root'] = '';
        }
        if (empty($params['root_tag'])) {
            $params['root_tag'] = '';
        }
        if (empty($params['add_xml_signature'])) {
            $params['add_xml_signature'] = true;
        }
        if (empty($params['xml_encoding'])) {
            $params['xml_encoding'] = 'UTF-8';
        }
        if (empty($params['xml_version'])) {
            $params['xml_version'] = '1.0';
        }
        if (empty($params['format_string'])) {
            $params['format_string'] = false;
        }
        if (empty($params['line_indent'])) {
            $params['line_indent'] = '';
        }

        $return_str = '';
        if (empty($params['root'])) {
            // Create root tag
            if (!empty($params['add_xml_signature'])) {
                $return_str .= '<'.'?xml version="'.$params['xml_version'].'" encoding="'.$params['xml_encoding'].'" ?'.'>';

                if (!empty($params['format_string'])) {
                    $return_str .= "\n";
                }
            }

            if (!empty($params['root_tag'])) {
                $return_str .= '<'.$params['root_tag'].'>';
            }
        }

        foreach ($arr as $root_tag => $tag_arr) {
            $new_params = $params;
            $new_params['root'] = $root_tag;
            $new_params['line_indent'] .= "\t";

            $attrs_str = '';
            $content_str = '';
            $only_content = false;
            if (is_array($tag_arr)) {
                foreach ($tag_arr as $attr_key => $attr_val) {
                    if ($attr_key === '') {
                        continue;
                    }

                    if (is_numeric($attr_key) && is_array($attr_val)) {
                        $content_str .= self::array_to_xml([$root_tag => $attr_val], $new_params);
                        $only_content = true;
                        continue;
                    }

                    if ($attr_key === '#') {
                        if (is_array($attr_val)) {
                            $content_str = self::array_to_xml($attr_val, $new_params);
                        } else {
                            $content_str = self::xml_encode($attr_val, ['xml_encoding' => $params['xml_encoding']]);
                        }
                    } elseif (substr($attr_key, 0, 1) === '@') {
                        $attr_key = substr($attr_key, 1);
                        if ($attr_key === '') {
                            continue;
                        }

                        // we have an attribute
                        $attrs_str .= ' '.self::xml_encode($attr_key, ['xml_encoding' => $params['xml_encoding']])
                                      .'="'.self::xml_encode($attr_val, ['xml_encoding' => $params['xml_encoding']]).'"';
                    } elseif (is_array($attr_val)) {
                        $content_str .= self::array_to_xml([$attr_key => $attr_val], $new_params);
                    } else {
                        $content_str .= (!empty($params['format_string']) ? $params['line_indent'] : '').'<'.$attr_key.'>'
                        .self::xml_encode($attr_val, ['xml_encoding' => $params['xml_encoding']])
                                        .'</'.$attr_key.'>'.(!empty($params['format_string']) ? "\n" : '');
                    }
                }
            } else {
                $content_str = self::xml_encode($tag_arr, ['xml_encoding' => $params['xml_encoding']]);
            }

            if (empty($only_content)) {
                $return_str .= (!empty($params['format_string']) ? $params['line_indent'] : '').'<'.$root_tag.$attrs_str.'>';
            }

            $return_str .= $content_str;

            if (empty($only_content)) {
                $return_str .= '</'.$root_tag.'>'.(!empty($params['format_string']) ? "\n" : '');
            }
        }

        if (empty($params['root']) && !empty($params['root_tag'])) {
            $return_str .= '</'.$params['root_tag'].'>';
        }

        return $return_str;
    }

    /**
     * Convert a node content to XML compatible string
     * @param string $string
     * @param bool|array $params Parameters to be used
     *
     * @return string Converted node content
     */
    public static function xml_encode($string, $params = false)
    {
        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        if (empty($params['xml_encoding'])) {
            $params['xml_encoding'] = 'UTF-8';
        }
        if (empty($params['convert_flags'])) {
            $params['convert_flags'] = false;
        }

        if (!is_string($string) || $string === '') {
            return '';
        }

        if ($params['convert_flags'] === false) {
            $params['convert_flags'] = constant('ENT_QUOTES');
            $params['convert_flags'] |= constant('ENT_SUBSTITUTE');
            $params['convert_flags'] |= constant('ENT_DISALLOWED');
            if (defined('ENT_XML1')) {
                $params['convert_flags'] |= constant('ENT_XML1');
            }
        }

        return @htmlspecialchars($string, $params['convert_flags'], $params['xml_encoding']);
    }

    /**
     * Convert a CSV column content to a CSV compatible string
     * @param string $str Content of CSV column to be converted
     * @param string $delimiter
     * @param string $enclosure
     * @param string $escape
     *
     * @return string Converted string
     */
    public static function csv_column(string $str, string $delimiter = ',', string $enclosure = '"', string $escape = '"') : string
    {
        if (str_contains($str, $enclosure)
            || str_contains($str, $delimiter)) {
            $str = $enclosure.str_replace($enclosure, $escape.$enclosure, $str).$enclosure;
        }

        return $str;
    }

    /**
     * Convert an array of CSV columns to a CSV string line
     * @param array $line_arr Columns array to be converted to CSV
     * @param string $line_delimiter
     * @param string $delimiter
     * @param string $enclosure
     * @param string $escape
     *
     * @return string Returns a CSV string line based on provided columns array
     */
    public static function csv_line(array $line_arr, string $line_delimiter = "\n", string $delimiter = ',', string $enclosure = '"', string $escape = '"') : string
    {
        if (empty($line_arr)) {
            return '';
        }

        $result_arr = [];
        foreach ($line_arr as $line_str) {
            if (!is_scalar($line_str)) {
                continue;
            }

            $result_arr[] = self::csv_column($line_str, $delimiter, $enclosure, $escape);
        }

        return implode($delimiter, $result_arr).$line_delimiter;
    }

    /**
     * @param string $file
     * @param string $str
     * @param array|false $params
     *
     * @return false|int
     */
    public static function count_string_in_file($file, $str, $params = false)
    {
        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        if (!isset($params['case_sensitive'])) {
            $params['case_sensitive'] = true;
        } else {
            $params['case_sensitive'] = (!empty($params['case_sensitive']));
        }

        if (!isset($params['ignore_size_limit'])) {
            $params['ignore_size_limit'] = false;
        } else {
            $params['ignore_size_limit'] = (!empty($params['ignore_size_limit']));
        }

        if (empty($params['size_limit']) || !is_numeric($params['size_limit'])) {
            $params['size_limit'] = self::MAX_COUNT_FILESIZE;
        }

        if (!($str_len = strlen($str))) {
            return 0;
        }

        if (!$params['case_sensitive']) {
            $str = strtolower($str);
        }

        if (empty($file)
         || !@file_exists($file)
         || (!$params['ignore_size_limit'] && @filesize($file) > $params['size_limit'])
         || !@is_readable($file)
         || !($fil = @fopen($file, 'rb'))) {
            return false;
        }

        $count = 0;
        while (($buf = @fread($fil, 1024))) {
            if (!$params['case_sensitive']) {
                $buf = strtolower($buf);
            }

            $count += substr_count($buf, $str);
        }

        return $count;
    }

    public static function arrays_are_same(array $arr1, array $arr2, bool $skip_nulls = false) : bool
    {
        return !self::array_diff_assoc_recursive($arr1, $arr2, $skip_nulls)
               && !self::array_diff_assoc_recursive($arr2, $arr1, $skip_nulls);
    }

    public static function array_diff_assoc_recursive(array $arr1, array $arr2, bool $skip_nulls = false) : ?array
    {
        $diff_arr = [];
        foreach ($arr2 as $key => $val) {
            if (!array_key_exists($key, $arr1)) {
                if ($skip_nulls
                    && $val === null) {
                    continue;
                }

                $diff_arr[$key] = $val;
                continue;
            }

            if (is_array($val)) {
                if (!is_array($arr1[$key])) {
                    $diff_arr[$key] = $val;
                    continue;
                }

                if (!($recursive_diff = self::array_diff_assoc_recursive($arr1[$key], $val, $skip_nulls))) {
                    continue;
                }

                $diff_arr[$key] = $recursive_diff;

                continue;
            }

            if ((string)$val !== (string)$arr1[$key]) {
                $diff_arr[$key] = $val;
            }
        }

        return $diff_arr;
    }
}
