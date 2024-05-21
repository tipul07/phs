<?php

namespace phs\plugins\foobar;

use phs\libraries\PHS_Params;
use phs\libraries\PHS_Plugin;

class PHS_Plugin_Foobar extends PHS_Plugin
{
    public const ERR_TEMPLATE = 40000, ERR_RENDER = 40001;

    /**
     * @inheritdoc
     */
    public function get_settings_structure() : array
    {
        return [
            'foobar_api_url' => [
                'display_name' => 'API URL',
                'display_hint' => 'URL where API requests will be sent',
                'type'         => PHS_Params::T_URL,
                'default'      => '',
            ],
            'foobar_api_key' => [
                'display_name' => 'API Key',
                'display_hint' => 'API key used in API requests',
                'type'         => PHS_Params::T_NOHTML,
                'default'      => '',
            ],
            'foobar_api_timeout' => [
                'display_name' => 'API timeout',
                'display_hint' => 'After how many seconds should API calls timeout',
                'type'         => PHS_Params::T_INT,
                'default'      => 30,
            ],
            'foobar_api_timeout2' => [
                'display_name' => 'API timeout2',
                'display_hint' => 'After how many seconds should API calls timeout2',
                'type'         => PHS_Params::T_INT,
                'default'      => 35,
            ],
            'foobar_api_timeout3' => [
                'display_name' => 'API timeout3',
                'display_hint' => 'After how many seconds should API calls timeout3',
                'type'         => PHS_Params::T_INT,
                'default'      => 35,
            ],
            'foobar_api_timeout4' => [
                'display_name' => 'API timeout3',
                'display_hint' => 'After how many seconds should API calls timeout3',
                'type'         => PHS_Params::T_INT,
                'default'      => 35,
            ],
        ];
    }
}
