<?php
namespace phs\plugins\admin\actions\translations;

use phs\PHS;
use phs\PHS_Ajax;
use phs\PHS_Crypt;
use phs\PHS_Scope;
use phs\PHS_Api_base;
use phs\libraries\PHS_Utils;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Po_format;
use phs\libraries\PHS_Api_action;
use phs\libraries\PHS_Notifications;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\system\core\libraries\PHS_Ui_translations;
use phs\plugins\accounts\models\PHS_Model_Accounts;

class PHS_Action_Ui_translations extends PHS_Api_action
{
    // How many secods should a download token be valid
    public const TOKEN_LIFETIME_SECS = 60;

    private ?PHS_Plugin_Admin $_admin_plugin = null;

    private ?PHS_Ui_translations $_ui_translations = null;

    private ?PHS_Model_Accounts $_accounts_model = null;

    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX];
    }

    public function execute()
    {
        PHS::page_settings('page_title', $this->_pt('UI Translations'));

        if (!PHS::user_logged_in()) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        if (!$this->_load_dependencies()) {
            PHS_Notifications::add_error_notice($this->_pt('Error loading required resources.'));

            return self::default_action_result();
        }

        if (!$this->_accounts_model->acc_is_developer()) {
            PHS_Notifications::add_error_notice($this->_pt('You don\'t have rights to access this section.'));

            return self::default_action_result();
        }

        if (PHS_Scope::current_scope() === PHS_Scope::SCOPE_AJAX) {
            if (!($result = $this->_execute_api_call())) {
                $error_arr = $this->get_error();
                $error_code = self::arr_get_error_code($error_arr, self::ERR_FUNCTIONALITY);
                $error_msg = self::arr_get_simple_error_message($error_arr, $this->_pt('Unknown error occured.'));

                return $this->send_api_error(
                    PHS_Api_base::framework_error_code_to_http_code($error_code),
                    $error_code,
                    $error_msg
                );
            }

            return $result;
        }

        return $this->quick_render_template('translations/ui_translations', [
            'admin_plugin'      => $this->_admin_plugin,
            'ui_translations'   => $this->_ui_translations,
            'translation_files' => $this->_ui_translations->get_po_instance()?->get_translation_existing_files() ?: [],
            'excluding_paths'   => $this->_admin_plugin->get_ui_translation_excluding_paths(),
        ]);
    }

    private function _execute_api_call() : null | bool | array
    {
        return match ($this->request_var('action', PHS_Params::T_NOHTML)) {
            'do_regenerate_pot'    => $this->_do_regenerate_pot(),
            'do_po_info'           => $this->_do_po_info(),
            'do_regenerate_po'     => $this->_do_regenerate_po_file(),
            'do_translate_po_file' => $this->_do_translate_po_file(),
            'do_stop_translation'  => $this->_do_stop_translation(),
            'do_download'          => $this->_do_download(),
            'download_file'        => $this->_do_download_file(),
            default                => $this->send_api_error(PHS_Api_base::H_CODE_BAD_REQUEST,
                self::ERR_PARAMETERS,
                $this->_pt('Invalid action provided.')),
        };
    }

    private function _do_download() : ?array
    {
        if (!(['basename' => $basename, 'extension' => $extension]
                = PHS_Utils::mypathinfo($this->request_var('file', PHS_Params::T_NOHTML)))
           || !$extension
           || !($basename = $this->_ui_translations->get_po_instance()?->validate_filename($basename))
           || !($file_path = $this->_get_file_path_by_basename_and_extension($basename, $extension))) {
            return $this->send_api_error(PHS_Api_base::H_CODE_BAD_REQUEST,
                self::ERR_PARAMETERS,
                $this->_pt('Error validating request.'));
        }

        $payload_arr = [];
        $payload_arr['download_url'] = PHS_Ajax::url(
            ['a' => 'ui_translations', 'ad' => 'translations', 'c' => 'api', 'p' => 'admin'],
            [
                'action'         => 'download_file',
                'download_token' => $this->_generate_download_token(PHS::current_user()['id'], $basename, $extension),
            ]);
        $payload_arr['file_size'] = @filesize($file_path);

        $payload_arr['server_time'] = time();

        return $this->send_api_success($payload_arr);
    }

    private function _do_regenerate_po_file() : ?array
    {
        if (!($lang = $this->request_var('lang', PHS_Params::T_NOHTML) ?: '')
           || !self::valid_language($lang)) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Please provide a language for PO file.'));

            return null;
        }

        if (!($po_obj = $this->_generate_po_file_from_pot($lang))) {
            $this->set_error_if_not_set(self::ERR_FUNCTIONALITY,
                $this->_pt('Error generating PO file.'));

            return null;
        }

        $payload_arr = [];
        $payload_arr['translation_files'] = $po_obj->get_translation_existing_files();
        $payload_arr['server_time'] = time();

        return $this->send_api_success($payload_arr);
    }

    private function _do_translate_po_file() : ?array
    {
        if (!($lang = $this->request_var('lang', PHS_Params::T_NOHTML) ?: '')
           || !self::valid_language($lang)) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Please provide a language for PO file.'));

            return null;
        }

        $force = $this->request_var('force', PHS_Params::T_BOOL) ?: false;

        if (!($status_arr = $this->_ui_translations->start_ui_translations($lang, $force))) {
            $this->copy_or_set_error($this->_ui_translations,
                self::ERR_FUNCTIONALITY, $this->_pt('Error starting UI translations background job.'));

            return null;
        }

        $payload_arr = [];
        $payload_arr['ui_translation_status'] = $status_arr;
        $payload_arr['server_time'] = time();

        return $this->send_api_success($payload_arr);
    }

    private function _do_stop_translation() : ?array
    {
        if (!($lang = $this->request_var('lang', PHS_Params::T_NOHTML) ?: '')
           || !self::valid_language($lang)) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Please provide a language for PO file.'));

            return null;
        }

        if (!($status_arr = $this->_ui_translations->force_stop_ui_translation($lang))) {
            $this->copy_or_set_error($this->_ui_translations,
                self::ERR_FUNCTIONALITY, $this->_pt('Error sending STOP request to UI translations background job.'));

            return null;
        }

        $payload_arr = [];
        $payload_arr['ui_translation_status'] = $status_arr;
        $payload_arr['server_time'] = time();

        return $this->send_api_success($payload_arr);
    }

    private function _generate_po_file_from_pot(string $lang) : ?PHS_Po_format
    {
        if (!($po_obj = $this->_ui_translations->get_po_instance())) {
            $this->copy_or_set_error($this->_ui_translations,
                self::ERR_FUNCTIONALITY, $this->_pt('Error obtaining PO format instance.'));

            return null;
        }

        $po_obj::add_to_ignored_directories_for_pot_list($this->_admin_plugin->get_ui_translation_excluding_paths());

        if (!$po_obj->refresh_po_file_from_pot($lang)) {
            $this->copy_or_set_error($po_obj,
                self::ERR_FUNCTIONALITY, $this->_pt('Error generating PO file.'));

            return null;
        }

        return $po_obj;
    }

    private function _do_regenerate_pot() : ?array
    {
        if (!($po_obj = $this->_ui_translations->get_po_instance())) {
            $this->copy_or_set_error($this->_ui_translations,
                self::ERR_FUNCTIONALITY, $this->_pt('Error obtaining PO format instance.'));

            return null;
        }

        if (($excluding_paths = $this->request_var('excluding_paths', PHS_Params::T_ARRAY, type_extra: ['type' => PHS_Params::T_NOHTML]) ?: [])) {
            $po_obj::add_to_ignored_directories_for_pot_list($excluding_paths);
        }

        $this->_admin_plugin->save_ui_translation_excluding_paths($excluding_paths);

        if (!$po_obj->generate_pot_file()) {
            $this->copy_or_set_error($po_obj,
                self::ERR_FUNCTIONALITY, $this->_pt('Error generating POT file.'));

            return null;
        }

        $payload_arr = [];
        $payload_arr['translation_files'] = $po_obj->get_translation_existing_files();
        $payload_arr['server_time'] = time();

        return $this->send_api_success($payload_arr);
    }

    private function _do_po_info() : ?array
    {
        if (!($po_obj = $this->_ui_translations->get_po_instance())) {
            $this->copy_or_set_error($this->_ui_translations,
                self::ERR_FUNCTIONALITY, $this->_pt('Error obtaining PO format instance.'));

            return null;
        }

        if (!($file = $this->request_var('file', PHS_Params::T_NOHTML) ?: '')
           || !@file_exists(LANG_PO_DIR.$file)) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Please provide a PO file.'));

            return null;
        }

        $file_info = PHS_Utils::mypathinfo($file);
        if (strtolower($file_info['extension'] ?? '') === 'pot') {
            $language = LANG_EN;
        } elseif (!($language = (strtolower($file_info['basename']) ?? ''))
                 || !self::valid_language($language)) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Invalid language for provided PO file.'));

            return null;
        }

        if (!$po_obj->parse_details_from_po_file(LANG_PO_DIR.$file, ['language' => $language])) {
            $this->copy_or_set_error($po_obj,
                self::ERR_FUNCTIONALITY, $this->_pt('Error parsing PO file.'));

            return null;
        }

        if (!($ui_translation_status = $this->_ui_translations->get_status($language))
            || !$ui_translation_status['status'] ?? 0) {
            $ui_translation_status = null;
        }

        $payload_arr = [];
        $payload_arr['po_info'] = $po_obj->get_parsed_indexes();
        $payload_arr['ui_translation_status'] = $ui_translation_status;
        $payload_arr['server_time'] = time();

        return $this->send_api_success($payload_arr);
    }

    private function _do_download_file() : ?array
    {
        if (!(['basename' => $basename, 'extension' => $extension]
            = $this->_decode_download_token($this->request_var('download_token', PHS_Params::T_NOHTML)))
           || !($file_path = $this->_get_file_path_by_basename_and_extension($basename, $extension))) {
            return $this->send_api_error(PHS_Api_base::H_CODE_BAD_REQUEST,
                self::ERR_PARAMETERS,
                $this->_pt('Error validating request.'));
        }

        $filename = $basename.'.'.$extension;
        @header('Content-Description: '.$filename);
        @header('Content-Transfer-Encoding: binary');
        @header('Content-Disposition: attachment; filename="'.$filename.'"');

        @header('Expires: 0');
        @header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        @header('Pragma: public');
        @header('Content-Length: '.@filesize($file_path));
        @header('Content-Type: text/plain');

        @readfile($file_path);

        exit;
    }

    private function _get_file_path_by_basename_and_extension(string $basename, string $extension) : ?string
    {
        $file = LANG_PO_DIR.$basename.'.'.$extension;
        if (!@file_exists($file)) {
            return null;
        }

        return $file;
    }

    private function _generate_download_token(int $account_id, string $basename, string $extension) : string
    {
        return PHS_Crypt::quick_encode($account_id.'::'.$basename.'::'.$extension.'::'.time());
    }

    private function _decode_download_token(string $token) : ?array
    {
        if (!$token
            || !($decoded_token = PHS_Crypt::quick_decode($token))
            || !($token_arr = explode('::', $decoded_token, 4))
            || empty($token_arr[0]) || empty($token_arr[1]) || empty($token_arr[2]) || empty($token_arr[3])
            || (int)$token_arr[0] !== (int)PHS::current_user()['id']
            || $token_arr[3] + self::TOKEN_LIFETIME_SECS < time()
            || !$this->_get_file_path_by_basename_and_extension($token_arr[1], $token_arr[2])) {
            return null;
        }

        return [
            'basename'  => $token_arr[1],
            'extension' => $token_arr[2],
        ];
    }

    private function _load_dependencies() : bool
    {
        $this->reset_error();

        if (
            (!$this->_admin_plugin && !($this->_admin_plugin = PHS_Plugin_Admin::get_instance()))
            || (!$this->_ui_translations && !($this->_ui_translations = PHS_Ui_translations::get_instance()))
            || (!$this->_accounts_model && !($this->_accounts_model = PHS_Model_Accounts::get_instance()))
        ) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Error loading required resources.'));

            return false;
        }

        return true;
    }
}
