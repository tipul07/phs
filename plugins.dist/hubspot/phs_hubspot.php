<?php
namespace phs\plugins\hubspot;

use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Plugin;

class PHS_Plugin_Hubspot extends PHS_Plugin
{
    public const LOG_CHANNEL = 'phs_hubspot.log';

    public function get_settings_keys_to_obfuscate()
    {
        return ['hubspot_api_key'];
    }

    /**
     * @inheritdoc
     */
    public function get_settings_structure() : array
    {
        return [
            'default_settings_group' => [
                'display_name' => $this->_pt('HubSpot Default Settings'),
                'display_hint' => $this->_pt('If no settings are passed in a HubSpot API call system will use these ones.'),
                'group_fields' => [
                    'hubspot_api_url' => [
                        'display_name' => 'HubSpot API URL',
                        'display_hint' => 'HubSpot URL where API requests will be placed (eg. https://api.hubapi.com/)',
                        'type'         => PHS_Params::T_URL,
                        'default'      => 'https://api.hubapi.com/',
                    ],
                    'hubspot_api_key' => [
                        'display_name' => 'HubSpot API Key',
                        'display_hint' => 'HubSpot API key used in API requests',
                        'type'         => PHS_Params::T_NOHTML,
                        'default'      => '',
                    ],
                    'hubspot_api_timeout' => [
                        'display_name' => 'HubSpot API timeout',
                        'display_hint' => 'After how many seconds should HubSpot API calls timeout',
                        'type'         => PHS_Params::T_INT,
                        'default'      => 30,
                    ],
                ],
            ],
        ];
    }

    /**
     * Returns an instance of Hubspot class
     *
     * @return bool|libraries\PHS_Hubspot
     */
    public function get_hubspot_instance()
    {
        static $hubspot_library = null;

        if ($hubspot_library !== null) {
            return $hubspot_library;
        }

        $library_params = [];
        $library_params['full_class_name'] = '\\phs\\plugins\\hubspot\\libraries\\PHS_Hubspot';
        $library_params['as_singleton'] = true;

        /** @var libraries\PHS_Hubspot $loaded_library */
        if (!($loaded_library = $this->load_library('phs_hubspot', $library_params))) {
            if (!$this->has_error()) {
                $this->set_error(self::ERR_LIBRARY, $this->_pt('Error loading HubSpot library.'));
            }

            return false;
        }

        if ($loaded_library->has_error()) {
            $this->copy_error($loaded_library, self::ERR_LIBRARY);

            return false;
        }

        $hubspot_library = $loaded_library;

        return $hubspot_library;
    }
}
