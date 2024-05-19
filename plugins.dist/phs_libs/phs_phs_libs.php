<?php
namespace phs\plugins\phs_libs;

use phs\PHS;
use phs\PHS_Api;
use phs\PHS_Crypt;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Roles;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Plugin;
use phs\system\core\models\PHS_Model_Plugins;
use phs\plugins\phs_libs\libraries\Phs_qr_code;
use phs\plugins\accounts\models\PHS_Model_Accounts;
use phs\system\core\events\layout\PHS_Event_Template;

class PHS_Plugin_Phs_libs extends PHS_Plugin
{
    public const LOG_QR_CODE = 'phs_qr_codes.log';

    private const QR_PARAM_NAME = '_t';

    private const QR_DIR = 'phs_qrcodes';

    /**
     * @inheritdoc
     */
    public function get_settings_structure()
    {
        return [
            'qrcodes_settings_group' => [
                'display_name' => $this->_pt('QR Codes settings'),
                'display_hint' => $this->_pt('Settings related to QR codes functionality.'),
                'group_fields' => [
                    'default_theme_in_admin' => [
                        'display_name' => $this->_pt('Default theme in admin'),
                        'display_hint' => $this->_pt('Should framework use default theme in admin section?'),
                        'type'         => PHS_Params::T_BOOL,
                        'default'      => false,
                    ],
                    'current_theme_as_default_in_admin' => [
                        'display_name' => $this->_pt('Current theme as default'),
                        'display_hint' => $this->_pt('If using default theme in admin section, should we set current theme as default (helps with loading resources from current theme if needed in admin interface)'),
                        'type'         => PHS_Params::T_BOOL,
                        'default'      => false,
                    ],
                ],
            ],
        ];
    }

    /**
     * Returns an instance of QR code library
     *
     * @return null|Phs_qr_code
     */
    public function get_qr_code_instance() : ?Phs_qr_code
    {
        static $qr_code_library = null;

        if ($qr_code_library !== null) {
            return $qr_code_library;
        }

        $library_params = [];
        $library_params['full_class_name'] = Phs_qr_code::class;
        $library_params['as_singleton'] = true;

        /** @var Phs_qr_code $loaded_library */
        if (!($loaded_library = $this->load_library('phs_qr_code', $library_params))) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_LIBRARY, $this->_pt('Error loading QR code library.'));
            }

            return null;
        }

        if ($loaded_library->has_error()) {
            $this->copy_error($loaded_library, self::ERR_LIBRARY);

            return null;
        }

        $qr_code_library = $loaded_library;

        return $qr_code_library;
    }

    public function get_qr_code_path(bool $slash_ended = true) : string
    {
        return rtrim(PHS_UPLOADS_DIR, '/').'/'.self::QR_DIR.(!empty($slash_ended) ? '/' : '');
    }

    public function get_qr_code_www(bool $slash_ended = true) : string
    {
        return rtrim(PHS_UPLOADS_WWW, '/').'/'.self::QR_DIR.(!empty($slash_ended) ? '/' : '');
    }

    public function extract_qr_code_url_details(string $from = 'g', ?string $crypted_data = null) : ?array
    {
        $this->reset_error();

        if (empty($crypted_data)) {
            if (empty($from) || !is_string($from)) {
                $from = 'g';
            }

            $from = strtolower($from);

            switch ($from) {
                case 'gp':
                    $crypted_data = PHS_Params::_gp(self::QR_PARAM_NAME, PHS_Params::T_NOHTML);
                    break;
                case 'pg':
                    $crypted_data = PHS_Params::_pg(self::QR_PARAM_NAME, PHS_Params::T_NOHTML);
                    break;
                case 'p':
                    $crypted_data = PHS_Params::_p(self::QR_PARAM_NAME, PHS_Params::T_NOHTML);
                    break;
                default:
                case 'g':
                    $crypted_data = PHS_Params::_g(self::QR_PARAM_NAME, PHS_Params::T_NOHTML);
                    break;
            }
        }

        if (empty($crypted_data)
            || !($decrypted_param = PHS_Crypt::quick_decode($crypted_data))
            || !($parts_arr = @json_decode($decrypted_param, true))
            || count($parts_arr) !== 5
        ) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Error extracting QR code token details.'));

            return null;
        }

        for ($i = 0; $i < 3; $i++) {
            if (!isset($parts_arr[$i])) {
                $this->set_error(self::ERR_PARAMETERS, $this->_pt('Error extracting QR code token details.'));

                return null;
            }

            $parts_arr[$i] = (int)$parts_arr[$i];
        }

        if (empty($parts_arr[4])) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Invalid QR code token.'));

            return null;
        }

        if (!empty($parts_arr[0])
            && $parts_arr[0] < time()) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('QR code token expired.'));

            return null;
        }

        $return_arr = [];
        $return_arr['link_expiration'] = $parts_arr[0];
        $return_arr['for_account_id'] = $parts_arr[1];
        $return_arr['expiration_hours'] = $parts_arr[2];
        $return_arr['qr_options'] = (!empty($parts_arr[3]) && is_array($parts_arr[3])) ? $parts_arr[3] : [];
        $return_arr['url'] = $parts_arr[4];

        return $return_arr;
    }

    public function generate_qr_code_img_url(string $url, ?array $options = null) : ?array
    {
        $this->reset_error();

        if (!($token = $this->_generate_qr_code_img_url_token($url, $options))) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error obtaining QR code image token.'));
            }

            return null;
        }

        $return_arr = [];
        $return_arr['token'] = $token;
        $return_arr['route'] = ['p' => 'phs_libs', 'a' => 'qr'];
        $return_arr['args'] = [self::QR_PARAM_NAME => $token];
        $return_arr['url'] = PHS::url($return_arr['route']);
        $return_arr['full_url'] = PHS::url($return_arr['route'], $return_arr['args']);

        return $return_arr;
    }

    public function clean_qr_code_directory_bg() : bool
    {
        $this->reset_error();

        if (!($libs_obj = $this->get_qr_code_instance())) {
            PHS_Logger::error('Error loading required resources while trying to clean QR codes directory.',
                self::LOG_QR_CODE);

            return true;
        }

        $qr_code_path = $this->get_qr_code_path();
        if (!($files = @scandir($qr_code_path))) {
            PHS_Logger::notice('No files found in QR code folder.',
                self::LOG_QR_CODE);

            return true;
        }

        foreach ($files as $filename) {
            $file_path = $qr_code_path.$filename;

            if (!@is_file($file_path)) {
                PHS_Logger::warning('['.$file_path.'] is not a file. Skipping...',
                    self::LOG_QR_CODE);
                continue;
            }

            if (!($file_details = $libs_obj->extract_token_details_from_qr_filename($filename))) {
                PHS_Logger::warning('File ['.$file_path.'] doesn\'t look like a QR codes file. Trying to delete it.',
                    self::LOG_QR_CODE);

                if (@unlink($file_path)) {
                    PHS_Logger::warning('File ['.$file_path.'] deleted.',
                        self::LOG_QR_CODE);
                }

                continue;
            }

            if (!empty($file_details['is_expired'])) {
                if (@unlink($file_path)) {
                    PHS_Logger::notice('File '.$file_path.' deleted.', self::LOG_QR_CODE);
                } else {
                    PHS_Logger::error('Error deleting '.$file_path.' file.', self::LOG_QR_CODE);
                }
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    protected function custom_activate($plugin_arr)
    {
        $this->reset_error();

        if (!$this->_create_qr_code_folder()) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_ACTIVATE, $this->_pt('Error creating QR code folder.'));
            }

            return false;
        }

        return true;
    }

    private function _generate_qr_code_img_url_token(string $url, ?array $options = null) : ?string
    {
        if (empty($url)) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Please provide an URL for QR code.'));

            return null;
        }

        $options ??= [];
        $options['link_expiration_seconds'] = (int)($options['link_expiration_seconds'] ?? 0);
        $options['for_account_id'] = (int)($options['for_account_id'] ?? 0);
        // Image expiration (images will be deleted by agent job)
        // This doesn't affect QR code validity
        $options['expiration_hours'] = (int)($options['expiration_hours'] ?? 0);
        $options['qr_options'] ??= null;

        if (!is_array($options['qr_options'])) {
            $options['qr_options'] = null;
        }

        $link_expiration = 0;
        if (!empty($options['link_expiration_seconds'])) {
            $link_expiration = time() + $options['link_expiration_seconds'];
        }

        $json_arr = [];
        $json_arr[] = $link_expiration;
        $json_arr[] = $options['for_account_id'];
        $json_arr[] = $options['expiration_hours'];
        $json_arr[] = $options['qr_options'];
        $json_arr[] = $url;

        if (!($token_json = @json_encode($json_arr))
         || !($token = PHS_Crypt::quick_encode($token_json))) {
            return null;
        }

        return $token;
    }

    private function _create_qr_code_folder() : bool
    {
        $this->reset_error();

        if (!($qr_code_dir = $this->get_qr_code_path(false))) {
            $this->set_error(self::ERR_ACTIVATE, $this->_pt('Error obtaining temporary upload directory path.'));

            return false;
        }

        if (@file_exists($qr_code_dir)) {
            if (!@is_dir($qr_code_dir)
                || !@is_writable($qr_code_dir)) {
                $this->set_error(self::ERR_ACTIVATE,
                    $this->_pt('QR code directory is not a directory or is not writeable.'));

                return false;
            }

            return true;
        }

        if (!@mkdir($qr_code_dir, 0775)
            && !@is_dir($qr_code_dir)
        ) {
            $this->set_error(self::ERR_ACTIVATE, $this->_pt('Error creating QR code directory.'));

            return false;
        }

        return true;
    }
}
