<?php

namespace phs\plugins\mobileapi\actions;

use \phs\PHS_Scope;
use \phs\libraries\PHS_Action;

class PHS_Action_Check_api_sessions_ag extends PHS_Action
{
    public function allowed_scopes()
    {
        return array( PHS_Scope::SCOPE_AGENT );
    }

    public function execute()
    {
        // /** @var \phs\plugins\sui_devices\models\PHS_Model_Devices $devices_model */
        // /** @var \phs\plugins\sui_devices\PHS_Plugin_Sui_devices $devices_plugin */
        // if( !($devices_plugin = PHS::load_plugin( 'sui_devices' ))
        //  or !($devices_model = PHS::load_model( 'devices', 'sui_devices' )) )
        // {
        //     $this->set_error( self::ERR_DEPENDENCIES, $this->_pt( 'Couldn\'t load devices model.' ) );
        //     return false;
        // }
        //
        // PHS_Logger::logf( ' ----- Started devices check...', $devices_plugin::LOG_CHANNEL );
        //
        // if( !$devices_model->check_devices_ag() )
        // {
        //     if( $devices_model->has_error() )
        //         $this->copy_error( $devices_model );
        //     else
        //         $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Error checking devices.' ) );
        //
        //     return false;
        // }
        //
        // PHS_Logger::logf( ' ----- END devices check...', $devices_plugin::LOG_CHANNEL );

        return self::default_action_result();
    }
}
