<?php

namespace phs\plugins\backup\actions;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Logger;
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
        /** @var \phs\plugins\backup\PHS_Plugin_Backup $backup_plugin */
        if( !($backup_plugin = PHS::load_plugin( 'backup' )) )
        {
            PHS_Logger::logf( '!!! Error: Couldn\'t load backup plugin.', $backup_plugin::LOG_CHANNEL );

            $this->set_error( self::ERR_DEPENDENCIES, $this->_pt( 'Couldn\'t load backup plugin.' ) );
            return false;
        }

        if( !$backup_plugin->run_backups_bg() )
        {
            if( $backup_plugin->has_error() )
                $this->copy_error( $backup_plugin );
            else
                $this->set_error( self::ERR_FUNCTIONALITY, $this->_pt( 'Checking backup rules failed.' ) );

            PHS_Logger::logf( '!!! Error running backup rules: '.$this->get_error_message(), $backup_plugin::LOG_CHANNEL );

            return false;
        }

        return PHS_Action::default_action_result();
    }
}
