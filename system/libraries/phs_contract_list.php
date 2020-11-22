<?php
namespace phs\libraries;

// When we are asked to display a listing of items to an external party,
// we can "chain" a normal contract with PHS_Contract_list to export lists
// in same format. All we have to provide is structure of each item in the list
// by returning item contract (eg. PHS_Contract_item::get_contract_data_definition) in
// PHS_Contract_list::get_contract_data_list_definition call
abstract class PHS_Contract_list extends PHS_Contract
{
    /**
     * Returns an array containing item node definition in the list
     * @return array|bool
     * @see \phs\libraries\PHS_Contract::_get_contract_node_definition()
     */
    abstract public function get_contract_data_list_definition();

    /**
     * Returns an array with data nodes definition
     * @return array|bool
     * @see \phs\libraries\PHS_Contract::_get_contract_node_definition()
     */
    public function get_contract_data_definition()
    {
        return [
            'total_count' => [
                'title' => 'Total items count',
                'description' => 'Total items resulted when we query database with provided filters',
                'type' => PHS_Params::T_INT,
                'default' => 0,
                'key_type' => self::FROM_INSIDE,
            ],
            'items_per_page' => [
                'title' => 'Requested items per page',
                'description' => 'How many items were we requested to put in the list. In case of pagination this is the number of items per page.',
                'type' => PHS_Params::T_INT,
                'default' => 0,
                'key_type' => self::FROM_INSIDE,
            ],
            'list' => [
                'title' => 'Properties list',
                'description' => 'An array containing properties definition',
                'recurring_key_type' => PHS_Params::T_INT,
                'recurring_node' => true,
                'key_type' => self::FROM_INSIDE,
                'nodes' => $this->get_contract_data_list_definition(),
            ],
        ];
    }
}
