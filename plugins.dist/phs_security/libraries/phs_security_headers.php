<?php

namespace phs\plugins\phs_security\libraries;

use phs\libraries\PHS_Library;
use phs\plugins\phs_security\PHS_Plugin_Phs_security;

class Phs_security_headers extends PHS_Library
{
    public const CONTENT_TYPE_OPTIONS = 'content_type_options', STRICT_TRANSPORT_SECURITY = 'strict_transport_security',
        REFERRER_POLICY = 'referrer_policy', CONTENT_SECURITY_POLICY = 'content_security_policy', PERMISSIONS_POLICY = 'permissions_policy',
        CORS_EMBEDDER_POLICY = 'cors_embedder_policy', CORS_OPENER_POLICY = 'cors_opener_policy', CORS_RESOURCE_POLICY = 'cors_resource_policy';

    private ?PHS_Plugin_Phs_security $_security_plugin = null;

    public function get_security_headers_definition() : array
    {
        return [
            self::CONTENT_TYPE_OPTIONS => [
                'header'      => 'X-Content-Type-Options',
                'default'     => 'nosniff',
                'details_url' => 'https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/X-Content-Type-Options',
            ],
            self::STRICT_TRANSPORT_SECURITY => [
                'header'      => 'Strict-Transport-Security',
                'default'     => 'max-age=315360000; includeSubDomains',
                'details_url' => 'https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Strict-Transport-Security',
            ],
            self::REFERRER_POLICY => [
                'header'     => 'Referrer-Policy',
                'default'    => 'strict-origin-when-cross-origin',
                'values_arr' => [
                    'no-referrer',
                    'no-referrer-when-downgrade',
                    'origin',
                    'origin-when-cross-origin',
                    'same-origin',
                    'strict-origin',
                    'strict-origin-when-cross-origin',
                    'unsafe-url',
                ],
                'details_url' => 'https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Referrer-Policy',
            ],
            self::CONTENT_SECURITY_POLICY => [
                'header'      => 'Content-Security-Policy',
                'default'     => 'default-src \'self\'',
                'details_url' => 'https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Content-Security-Policy',
                'hint'        => '{directive} {value}; {directive} {value}',
            ],
            self::PERMISSIONS_POLICY => [
                'header'      => 'Permissions-Policy',
                'default'     => '',
                'details_url' => 'https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Permissions-Policy',
                'hint'        => '{directive}={value}; {directive}={value}',
            ],
            self::CORS_EMBEDDER_POLICY => [
                'header'     => 'Cross-Origin-Embedder-Policy',
                'default'    => 'unsafe-none',
                'values_arr' => [
                    'unsafe-none',
                    'require-corp',
                    'credentialless',
                ],
                'details_url' => 'https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Cross-Origin-Embedder-Policy',
            ],
            self::CORS_OPENER_POLICY => [
                'header'     => 'Cross-Origin-Opener-Policy',
                'default'    => 'unsafe-none',
                'values_arr' => [
                    'unsafe-none',
                    'same-origin-allow-popups',
                    'same-origin',
                ],
                'details_url' => 'https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Cross-Origin-Opener-Policy',
            ],
            self::CORS_RESOURCE_POLICY => [
                'header'     => 'Cross-Origin-Resource-Policy',
                'default'    => 'same-site',
                'values_arr' => [
                    'same-origin',
                    'same-site',
                    'cross-origin',
                ],
                'details_url' => 'https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Cross-Origin-Resource-Policy',
            ],
        ];
    }

    public function get_security_headers_for_response() : ?array
    {
        if ( !$this->_load_dependencies() ) {
            return null;
        }

        if ( !$this->_security_plugin->security_headers_are_enabled() ) {
            return [];
        }

        $enabled_headers = $this->_security_plugin->get_enabled_security_headers();
        $headers_values = $this->_security_plugin->get_security_headers_values();

        $headers_arr = [];
        foreach ( $this->get_security_headers_definition() as $h_id => $h_arr ) {
            if ( empty( $enabled_headers[$h_id] ) ) {
                continue;
            }

            $headers_arr[$h_arr['header']] = $headers_values[$h_id] ?? $h_arr['default'] ?? null;
        }

        return $headers_arr;
    }

    private function _load_dependencies() : bool
    {
        $this->reset_error();

        if (!$this->_security_plugin
            && !($this->_security_plugin = PHS_Plugin_Phs_security::get_instance())) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Error loading required resources.'));

            return false;
        }

        return true;
    }
}
