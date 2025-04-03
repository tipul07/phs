<?php
namespace phs\system\core\contracts;

use phs\libraries\PHS_Contract;

class PHS_Contract_Ractive_autocomplete extends PHS_Contract
{
    /**
     * @inheritdoc
     */
    public function get_contract_data_definition() : ?array
    {
        $this->reset_error();

        if (!($autocomplete_contract = PHS_Contract_Autocomplete::get_instance())) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Error loading required resources.'));

            return null;
        }

        return $autocomplete_contract->get_contract_data_definition();
    }
}
