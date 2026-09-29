<?php
namespace phs\system\core\contracts;

use phs\libraries\PHS_Contract;
use phs\system\core\attributes\PHS_Dependency;

class PHS_Contract_Ractive_autocomplete extends PHS_Contract
{
    #[PHS_Dependency]
    private ?PHS_Contract_Autocomplete $_autocomplete_contract = null;

    /**
     * @inheritdoc
     */
    public function get_contract_data_definition() : ?array
    {
        return $this->_autocomplete_contract->get_contract_data_definition();
    }
}
