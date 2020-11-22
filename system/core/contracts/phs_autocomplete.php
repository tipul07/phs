<?php

namespace phs\system\core\contracts;

use phs\libraries\PHS_Params;
use \phs\libraries\PHS_Contract;

class PHS_Contract_Autocomplete extends PHS_Contract
{
    /**
     * Returns an array with data nodes definition
     * @return array
     * @see \phs\libraries\PHS_Contract::_get_contract_node_definition()
     */
    public function get_contract_data_definition()
    {
        return [
            'items' => [
                'title' => 'Items to be presented',
                'description' => 'An array containing all items to be presented in autocomplete interface',
                'recurring_key_type' => PHS_Params::T_INT,
                'recurring_node' => true,
                'nodes' => [
                    'id' => [
                        'title' => 'Item identifier',
                        'description' => 'This field will be passed in hidden field as value',
                        'type' => PHS_Params::T_INT,
                        'default' => 0,
                    ],
                    'listing_title' => [
                        'title' => 'Text to be put in the list',
                        'description' => 'Plain text to be presented as option to be selected in items list',
                        'type' => PHS_Params::T_NOHTML,
                        'default' => '',
                    ],
                    'listing_title_html' => [
                        'title' => 'Text as HTML to be put in the list',
                        'description' => 'HTML text to be presented as option to be selected in items list',
                        'type' => PHS_Params::T_ASIS,
                        'default' => '',
                    ],
                    'input_title' => [
                        'title' => 'Text to be put in the input',
                        'description' => 'This text will be put in autocomplete input after user selected an item',
                        'type' => PHS_Params::T_NOHTML,
                        'default' => '',
                    ],
                    'extra' => [
                        'title' => 'Item extra data',
                        'description' => 'Any extra information you want to return for this item',
                        'type' => PHS_Params::T_ASIS,
                        'default' => null,
                    ],
                ]
            ],
            'total_items' => [
                'title' => 'Total number of items',
                'description' => 'Total number of items that matched search criteria',
                'recurring_key_type' => PHS_Params::T_INT,
            ],
        ];
    }
}
