<?php

namespace phs\system\core\actions;

use phs\PHS_Scope;
use phs\PHS_Bg_jobs;
use phs\libraries\PHS_Event;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Logger;

class PHS_Action_Trigger_event_bg extends PHS_Action
{
    public function allowed_scopes()
    {
        return [ PHS_Scope::SCOPE_BACKGROUND ];
    }

    public function execute()
    {
        /** @var \phs\libraries\PHS_Event $event_obj */
        if( !($params = PHS_Bg_jobs::get_current_job_parameters())
         || !($event_class = ($params['event'] ?? ''))
         || !($listeners = ($params['listeners'] ?? []))
         || !is_array( $listeners )
         || !($event_obj = $event_class::get_instance( true, $event_class )) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Error initiating event %s for background job.', ($event_class ?? 'N/A') ) );
            return false;
        }

        PHS_Logger::logf( '[EVENT] Triggering event '.$event_class.'.' );

        $trigger_params = $params['params'] ?? [];
        $trigger_params['only_background_listeners'] = true;

        foreach( $listeners as $listener ) {
            $options = $listener['options'] ?? [];
            // Make sure we don't duplicate the listeners (if already defined in boostrap scripts)
            $options['unique'] = true;

            if( empty( $listener['callback'] )
             || !$event_obj->add_listener( $listener['callback'], $options )
            ) {
                if( $event_obj->has_error() && $event_obj->get_error_code() === PHS_Event::ERR_NOT_UNIQUE ) {
                    // If we got an error because the event is not unique, just ignore the error...
                    continue;
                }

                $this->set_error( self::ERR_PARAMETERS,
                    self::_t( 'Error initiating listeners for event %s in background job: %s',
                        $event_class, $event_obj->get_simple_error_message() ?? self::_t( 'N/A' ) ) );
                return false;
            }
        }

        $event_obj->do_trigger_from_background(
            $params['input'] ?? [],
            $trigger_params,
            $params['event_prefix'] ?? '' );

        PHS_Logger::logf( '[EVENT] Finished triggering event '.$event_class.'.' );

        return PHS_Action::default_action_result();
    }
}
