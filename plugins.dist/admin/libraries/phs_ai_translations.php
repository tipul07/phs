<?php
namespace phs\plugins\admin\libraries;

use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Library;
use phs\plugins\admin\PHS_Plugin_Admin;

class PHS_Ai_translations extends PHS_Library
{
    private ?PHS_Plugin_Admin $_admin_plugin = null;

    private array $_settings = [];

    private static array $_injected_settings = [];

    public function translate(array $payload, string $from_language, string $to_language) : ?array
    {
        if (!$this->_extract_ai_settings()) {
            return null;
        }

        if (!$payload) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Please provide a valid payload for translation.'));

            return null;
        }

        if (!$from_language || !$to_language
            || !($from_lang = self::get_defined_language($from_language))
            || empty($from_lang['title'])
            || !($to_lang = self::get_defined_language($to_language))
            || empty($to_lang['title'])
        ) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('Please valid languages for translation.'));

            return null;
        }

        if (!($prompt_arr = $this->_get_ai_prompt($payload, $from_lang['title'], $to_lang['title']))) {
            $this->set_error_if_not_set(
                self::ERR_PARAMETERS, $this->_pt('Error obtaining prompt for OpenAI request.')
            );

            return null;
        }

        $call_settings = [
            'log_file'             => $this->_admin_plugin::LOG_AI_TRANSLATIONS,
            'expect_json_response' => true,
            'auth_bearer'          => [
                'token' => $this->_settings['openai_token'],
            ],
        ];

        if (!($response = http_call($this->_settings['openai_url'], 'POST', $prompt_arr, settings: $call_settings))) {
            $this->copy_or_set_static_error(self::ERR_FUNCTIONALITY, $this->_pt('Error sending request to OpenAI API.'));

            return null;
        }

        if (empty($response['response_json']['output'][0]['content'])
            || !is_array($response['response_json']['output'][0]['content'])) {
            PHS_Logger::error('Invalid OpenAI response (no output.0.content node): '.$response['response_buf'], $this->_admin_plugin::LOG_AI_TRANSLATIONS);
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Invalid OpenAI response.'));

            return null;
        }

        $response_arr = [];
        foreach ($response['response_json']['output'][0]['content'] as $node_arr) {
            if (!empty($node_arr['type']) && !empty($node_arr['text'])
                && $node_arr['type'] === 'output_text'
                && ($response_arr = @json_decode($node_arr['text'], true))) {
                break;
            }
        }

        if (empty($response_arr)) {
            PHS_Logger::error('Invalid OpenAI response (no output.0.content.[type=output_text] with valid JSON node): '.$response['response_buf'], $this->_admin_plugin::LOG_AI_TRANSLATIONS);
            $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Error extracting OpenAI response.'));

            return null;
        }

        return $response_arr;
    }

    public function inject_settings(array $settings) : bool
    {
        $settings = !$settings ? [] : self::validate_array_to_new_array($settings, self::_get_settings_structure());

        if(!$settings) {
            $this->_extract_ai_settings();

            // Reset error if pluin settings are not set
            $this->reset_error();

            return true;
        }

        if(!$this->_validate_settings($settings)) {
            $this->set_error_if_not_set(self::ERR_SETTINGS,
                $this->_pt('Error validating injected OpenAI settings.'));

            return false;
        }

        self::$_injected_settings = $settings;

        $this->_settings = [];

        return $this->_extract_ai_settings();
    }

    private static function _get_settings_structure() : array
    {
        return [
            'openai_url'        => '',
            'openai_token'      => '',
            'openai_model'      => '',
            'openai_temperature'=> 0.01,
        ];
    }

    private function _get_ai_prompt(array $payload, string $from_lang_title, string $to_lang_title) : array
    {
        if (!$payload
            || !($payload_keys = array_keys($payload))
            || !($user_content = @json_encode($payload))
            || !$this->_extract_ai_settings()) {
            return [];
        }

        $system_content = 'You are a framework technical translator and will try to translate all terminology as technical as possible. '
                          .'The content is a JSON string with nodes '.implode(', ', $payload_keys).'. '
                          .'Translate them from '.$from_lang_title.' to '.$to_lang_title.'.';

        $prompt_arr = [];
        $prompt_arr['model'] = $this->_settings['openai_model'];
        $prompt_arr['input'] = [
            [
                'role'    => 'system',
                'content' => $system_content,
            ],
            [
                'role'    => 'user',
                'content' => $user_content,
            ],
        ];
        $prompt_arr['temperature'] = $this->_settings['openai_temperature'];

        return $prompt_arr;
    }

    private function _extract_ai_settings() : bool
    {
        if ($this->_settings) {
            return true;
        }

        if (!$this->_load_dependencies()) {
            return false;
        }

        $this->_settings['openai_url'] = self::$_injected_settings['openai_url'] ?? $this->_admin_plugin->get_ai_openai_url();
        $this->_settings['openai_token'] = self::$_injected_settings['openai_token'] ?? $this->_admin_plugin->get_ai_openai_token();
        $this->_settings['openai_model'] = self::$_injected_settings['openai_model'] ?? $this->_admin_plugin->get_ai_openai_model();
        $this->_settings['openai_temperature'] = self::$_injected_settings['openai_temperature'] ?? $this->_admin_plugin->get_ai_openai_temperature() ?: 0.01;

        if (!$this->_validate_settings($this->_settings)) {
            $this->_settings = [];
            $this->set_error_if_not_set(self::ERR_SETTINGS, $this->_pt('Error loading OpenAI settings.'));

            return false;
        }

        if (!is_numeric($this->_settings['openai_temperature']) || $this->_settings['openai_temperature'] < 0) {
            $this->_settings['openai_temperature'] = 0.01;
        }

        return true;
    }

    private function _validate_settings(array $settings) : bool
    {
        if (empty($settings['openai_url'])
            || empty($settings['openai_token'])
            || empty($settings['openai_model'])) {
            $this->set_error(self::ERR_SETTINGS, $this->_pt('Please provide valid OpenAI settings.'));

            return false;
        }

        return true;
    }

    private function _load_dependencies() : bool
    {
        $this->reset_error();

        if (
            (!$this->_admin_plugin && !($this->_admin_plugin = PHS_Plugin_Admin::get_instance()))
        ) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Error loading required resources.'));

            return false;
        }

        return true;
    }
}
