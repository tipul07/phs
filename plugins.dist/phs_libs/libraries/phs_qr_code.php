<?php

namespace phs\plugins\phs_libs\libraries;

use Exception;
use phs\PHS_Crypt;
use chillerlan\QRCode\QRCode;
use phs\libraries\PHS_Library;
use chillerlan\QRCode\QROptions;

class Phs_qr_code extends PHS_Library
{
    public const LIB_DIR = 'qrcode';

    protected const QR_VERSION = -1;

    public function get_extension_by_output_type(string $output_type) : ?string
    {
        switch ($output_type) {
            default:
                return null;
            case QRCode::OUTPUT_IMAGE_PNG:
                return 'png';
            case QRCode::OUTPUT_IMAGE_JPG:
                return 'jpg';
            case QRCode::OUTPUT_IMAGE_GIF:
                return 'gif';
        }
    }

    public function get_mimetype_by_output_type(string $output_type) : ?string
    {
        switch ($output_type) {
            default:
                return null;
            case QRCode::OUTPUT_IMAGE_PNG:
                return 'image/png';
            case QRCode::OUTPUT_IMAGE_JPG:
                return 'image/jpeg';
            case QRCode::OUTPUT_IMAGE_GIF:
                return 'image/gif';
        }
    }

    public function get_output_type_from_extension(string $extension) : ?string
    {
        switch ($extension) {
            default:
                return null;
            case 'png':
                return QRCode::OUTPUT_IMAGE_PNG;
            case 'jpg':
                return QRCode::OUTPUT_IMAGE_JPG;
            case 'gif':
                return QRCode::OUTPUT_IMAGE_GIF;
        }
    }

    public function extract_token_details_from_qr_filename(string $filename) : ?array
    {
        if (($filename = basename($filename))
            || !($name_parts = explode('.', $filename, 2))
            || empty($name_parts[0])
            || empty($name_parts[1])
            || !($output_type = $this->get_output_type_from_extension($name_parts[1]))
            || !($token_parts = explode('_', $name_parts[0], 3))
            || empty($token_parts[0]) || empty($token_parts[1]) || empty($token_parts[2])
            || $token_parts[0] !== 'qr'
            || $token_parts[2] > ($now_time = time())
            || !($decrypted_data = PHS_Crypt::quick_decode($token_parts[1]))
            || !($data_parts = explode(':', $decrypted_data, 3))
            || empty($data_parts[2])
            || (int)$data_parts[2] !== (int)$token_parts[2]
        ) {
            return null;
        }

        return [
            'extension'      => $name_parts[1],
            'output_type'    => $output_type,
            'for_account_id' => $data_parts[0] ?? 0,
            'is_expired'     => (!empty($data_parts[1]) && $data_parts[1] < $now_time),
            'expiration'     => $data_parts[1] ?? 0,
        ];
    }

    public function render_url_to_output(string $url, ?array $options = null) : ?array
    {
        $options ??= [];

        $options['return_buffer'] = true;
        $options['to_file'] = false;

        if (!($result = $this->_render_url($url, $options))) {
            return null;
        }

        return $result;
    }

    public function render_url_to_file(string $url, ?array $options = null) : ?string
    {
        $options ??= [];

        $options['return_buffer'] = false;
        $options['to_file'] = true;

        if (!($result = $this->_render_url($url, $options))) {
            return null;
        }

        return $result['result'];
    }

    private function _get_qrcode_dir_paths() : ?array
    {
        $this->reset_error();

        if (!($library_paths = $this->get_library_location_paths())) {
            $this->set_error(self::ERR_SETTINGS, $this->_pt('Error obtaining PHS libraries directory paths.'));

            return null;
        }

        $return_arr = [];
        $return_arr['www'] = $library_paths['library_www'].self::LIB_DIR.'/';
        $return_arr['path'] = $library_paths['library_path'].self::LIB_DIR.'/';

        return $return_arr;
    }

    private function _bootstrap_qr_codes() : bool
    {
        static $_booted = false;

        if ($_booted) {
            return true;
        }

        $this->reset_error();

        if (!($paths = $this->_get_qrcode_dir_paths())
         || !@file_exists($paths['path'].'autoload.php')) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error bootstraping QR codes functionality.'));
            }

            return false;
        }

        @ob_start();
        include_once $paths['path'].'autoload.php';
        @ob_end_clean();

        $_booted = true;

        return true;
    }

    private function _qr_code_options() : array
    {
        return [
            // In case this QR code is for a specific account
            'for_account_id' => 0,
            // After how many hours should created file/link expire
            'expiration_hours' => 0,

            // Rendering options
            // Render and return result buffer
            'return_buffer' => true,
            // Render to a file and return file name
            'to_file' => false,
            // Options sent to QROptions class
            'qr_options' => [
                'version'          => self::QR_VERSION,
                'eccLevel'         => QRCode::ECC_L,
                'outputType'       => QRCode::OUTPUT_IMAGE_PNG,
                'imageBase64'      => false,
                'quietzoneSize'    => 2,
                'scale'            => 5,
                'imageTransparent' => false,
            ],
        ];
    }

    private function _get_resulting_filename(array $options) : ?array
    {
        $this->reset_error();

        if (empty($options['to_file'])) {
            return null;
        }

        /** @var \phs\plugins\phs_libs\PHS_Plugin_Phs_libs $plugin_obj */
        if (!($plugin_obj = $this->get_plugin_instance())) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Error loading required resources.'));

            return null;
        }

        if (empty($options['output_type'])
            || !($extension = $this->get_extension_by_output_type($options['output_type']))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Invalid QR code image output type.'));

            return null;
        }

        $now_time = time();

        $expiration = 0;
        if (!empty($options['expiration_hours'])) {
            $expiration = $now_time + $options['expiration_hours'] * 3600;
        }

        if (!($name_token = PHS_Crypt::quick_encode(((int)($options['for_account_id'] ?? 0)).':'.$expiration.':'.$now_time))) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error obtaining file name token.'));

            return null;
        }

        $return_arr = [];
        $return_arr['name_token'] = $name_token;
        $return_arr['timestamp'] = $now_time;
        $return_arr['extension'] = $extension;
        $return_arr['filename'] = 'qr_'.$name_token.'_'.$now_time.'.'.$extension;
        $return_arr['path'] = $plugin_obj->get_qr_code_path();
        $return_arr['www'] = $plugin_obj->get_qr_code_www();
        $return_arr['full_path'] = $return_arr['path'].$return_arr['filename'];
        $return_arr['full_www'] = $return_arr['www'].$return_arr['filename'];

        return $return_arr;
    }

    private function _render_url(string $url, ?array $options = null) : ?array
    {
        $this->reset_error();

        if (!$this->_bootstrap_qr_codes()) {
            return null;
        }

        $qr_options = self::validate_array_recursive($options, $this->_qr_code_options());
        $qr_options['qr_options']['output_type'] ??= QRCode::OUTPUT_IMAGE_PNG;

        if (null === ($filedetails = $this->_get_resulting_filename($qr_options))
            && $this->has_error()) {
            return null;
        }

        $filename = $filedetails['full_path'] ?? null;

        try {
            $result = (new QRCode(new QROptions($qr_options['qr_options'] ?? [])))->render($url, $filename);
        } catch (Exception $e) {
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error rendering QR code: %s.', $e->getMessage()));

            return null;
        }

        return [
            'result'  => $filename ?? $result,
            'options' => $qr_options,
        ];
    }
}
