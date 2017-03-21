<?php

namespace phs\plugins\backup\actions;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Action;

class PHS_Action_Run_backups_ag extends PHS_Action
{
    const ERR_DEPENDENCIES = 1;

    public function allowed_scopes()
    {
        return array( PHS_Scope::SCOPE_AGENT );
    }

    public function execute()
    {
        /** @var \phs\plugins\s2p_companies\models\PHS_Model_Companies $companies_model */
        if( !($companies_model = PHS::load_model( 'companies', 's2p_companies' )) )
        {
            $this->set_error( self::ERR_DEPENDENCIES, $this->_pt( 'Couldn\'t load company model.' ) );
            return false;
        }

        if( !$companies_model->check_companies_setup_ag() )
        {
            if( $companies_model->has_error() )
                $this->copy_error( $companies_model );
            else
                $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error checking companies setups.' ) );

            return false;
        }

        return PHS_Action::default_action_result();
    }
}
