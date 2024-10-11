<?php

namespace phs\plugins\phs_security;

use phs\libraries\PHS_Params;
use phs\libraries\PHS_Plugin;
use phs\plugins\phs_security\libraries\Phs_security_headers;

class PHS_Plugin_Phs_security extends PHS_Plugin
{
    /**
     * @inheritdoc
     */
    public function get_settings_structure() : array
    {
        return [
            'headers_settings_group' => [
                'display_name' => $this->_pt('Security Headers Settings'),
                'display_hint' => $this->_pt('This affects if platform will add in web and ajax requests specified security headers.'),
                'group_fields' => [
                    'headers_enabled' => [
                        'display_name' => $this->_pt('Enable security headers'),
                        'display_hint' => $this->_pt('Should framework send security headers selected in this section?'),
                        'type'         => PHS_Params::T_BOOL,
                        'default'      => false,
                    ],
                    'headers_selected' => [
                        'display_name'    => $this->_pt('Security headers'),
                        'display_hint'    => $this->_pt('Which security headers are enabled to be sent?'),
                        'custom_renderer' => [$this, 'plugin_settings_display_security_headers'],
                        // 'custom_save'     => [$this, 'plugin_settings_save_security_headers'],
                        'type'    => PHS_Params::T_ARRAY,
                        'default' => [],
                    ],
                    'headers_values' => [
                        'display_name'   => $this->_pt('Security headers values'),
                        'display_hint'   => $this->_pt('Values of what should actually be sent in headers.'),
                        'skip_rendering' => true,
                        // 'custom_save'    => [$this, 'plugin_settings_save_security_headers'],
                        'type'    => PHS_Params::T_ARRAY,
                        'default' => [],
                    ],
                ],
            ],
        ];
    }

    public function plugin_settings_display_security_headers($params) : string
    {
        // Load ekyc instance to make sure ekyc is bootstrapped
        if (!($headers_lib = $this->get_security_headers_instance())) {
            return 'Error rendering settings field.';
        }

        $params = self::validate_array($params, self::default_custom_renderer_params());

        $data_arr = [];
        $data_arr['headers_definition'] = $headers_lib->get_security_headers_definition();
        $data_arr['current_settings'] = $this->get_security_headers_settings();

        return $this->quick_render_template_for_buffer('plugin_headers_settings', $data_arr);
    }

    public function plugin_settings_save_security_headers($params) : ?string
    {
        $params = self::validate_array($params, self::st_default_custom_save_params());

        if (empty($params['field_name'])
            || empty($params['form_data']) || !is_array($params['form_data'])
            || ($params['field_name'] !== 'ekyc_providers' && $params['field_name'] !== 'ekyc_providers_priority')) {
            return null;
        }

        if ($params['field_name'] === 'ekyc_providers') {
            if (empty($params['form_data']['ekyc_providers']) || !is_array($params['form_data']['ekyc_providers'])) {
                return '[]';
            }

            $settings_arr = [];
            foreach ($params['form_data']['ekyc_providers'] as $check_type => $check_details) {
                if (empty($check_details) || !is_array($check_details)) {
                    continue;
                }
                foreach ($check_details as $provider_slug => $enabled) {
                    $settings_arr[$check_type][$provider_slug] = !empty($enabled);
                }
            }

            return @json_encode($settings_arr);
        }

        if ($params['field_name'] === 'ekyc_providers_priority') {
            if (empty($params['form_data']['ekyc_providers_priority']) || !is_array($params['form_data']['ekyc_providers_priority'])) {
                return '[]';
            }

            $settings_arr = [];
            foreach ($params['form_data']['ekyc_providers_priority'] as $check_type => $check_details) {
                if (empty($check_details) || !is_array($check_details)) {
                    continue;
                }
                foreach ($check_details as $provider_slug => $order) {
                    $settings_arr[$check_type][$provider_slug] = (int)$order;
                }
            }

            return @json_encode($settings_arr);
        }

        return null;
    }

    public function get_security_headers_settings() : array
    {
        $plugin_settings = $this->get_plugin_settings();

        return [
            'headers_selected' => $plugin_settings['headers_selected'],
            'headers_values'   => $plugin_settings['headers_values'],
        ];
    }

    public function get_security_headers_instance() : ?Phs_security_headers
    {
        static $security_headers_lib = null;

        if ($security_headers_lib !== null) {
            return $security_headers_lib;
        }

        $this->reset_error();

        $library_params = [];
        $library_params['full_class_name'] = Phs_security_headers::class;
        $library_params['as_singleton'] = true;

        /** @var Phs_security_headers $loaded_library */
        if (!($loaded_library = $this->load_library('phs_security_headers', $library_params))) {
            $this->set_error_if_not_set(self::ERR_LIBRARY, $this->_pt('Error loading security headers library.'));

            return null;
        }

        $security_headers_lib = $loaded_library;

        return $loaded_library;
    }
}
