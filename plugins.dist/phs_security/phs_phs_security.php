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
                        'type'            => PHS_Params::T_ARRAY,
                        'default'         => [],
                    ],
                    'headers_values' => [
                        'display_name'   => $this->_pt('Security headers values'),
                        'display_hint'   => $this->_pt('Values of what should actually be sent in headers.'),
                        'skip_rendering' => true,
                        'type'           => PHS_Params::T_ARRAY,
                        'default'        => [],
                    ],
                ],
            ],
        ];
    }

    public function security_headers_are_enabled() : bool
    {
        return (bool)($this->get_plugin_settings()['headers_enabled'] ?? false);
    }

    public function get_enabled_security_headers() : array
    {
        return $this->get_plugin_settings()['headers_selected'] ?? [];
    }

    public function get_security_headers_values() : array
    {
        return $this->get_plugin_settings()['headers_values'] ?? [];
    }

    public function plugin_settings_display_security_headers($params) : string
    {
        // Load ekyc instance to make sure ekyc is bootstrapped
        if (!($headers_lib = $this->get_security_headers_instance())) {
            return 'Error rendering settings field.';
        }

        $data_arr = [];
        $data_arr['headers_definition'] = $headers_lib->get_security_headers_definition();
        $data_arr['current_settings'] = $this->get_plugin_settings();

        return $this->quick_render_template_for_buffer('plugin_headers_settings', $data_arr);
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
