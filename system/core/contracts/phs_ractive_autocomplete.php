<?php

namespace phs\system\core\contracts;

use \phs\PHS;
use \phs\libraries\PHS_Contract;

class PHS_Contract_Ractive_autocomplete extends PHS_Contract
{
    /**
     * Returns an array with data nodes definition
     * @return array|bool
     * @see \phs\libraries\PHS_Contract::_get_contract_node_definition()
     */
    public function get_contract_data_definition()
    {
        $this->reset_error();

        /** @var \phs\plugins\amv_products\contracts\assets\PHS_Contract_Model_basic $model_contract */
        if( !($autocomplete_contract = PHS::load_contract( 'autocomplete' )) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error loading autocomplete contract.' ) );
            return false;
        }

        return $autocomplete_contract->get_contract_data_definition();
    }
}
