<?php

namespace phs\plugins\phs_security\libraries;

use phs\libraries\PHS_Library;

class Phs_security_headers extends PHS_Library
{
    public const CONTENT_TYPE_OPTIONS = 1, STRICT_TRANSPORT_SECURITY = 2, REFERRER_POLICY = 3, CONTENT_SECURITY_POLICY = 4;

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
            ],
        ];
    }

    public function get_security_headers_for_response(array $headers) : array
    {
        $definition_arr = $this->get_security_headers_definition();
    }
}
