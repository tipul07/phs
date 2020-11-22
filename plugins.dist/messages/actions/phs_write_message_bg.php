<?php

namespace phs\plugins\messages\actions;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\PHS_Bg_jobs;
use \phs\libraries\PHS_Logger;
use \phs\libraries\PHS_Action;

class PHS_Action_Write_message_bg extends PHS_Action
{
    const ERR_UNKNOWN_MESSAGE = 40000, ERR_FINISH_ERROR = 40001;

    public function allowed_scopes()
    {
        return array( PHS_Scope::SCOPE_BACKGROUND );
    }

    public function execute()
    {
        /** @var \phs\plugins\messages\models\PHS_Model_Messages $messages_model */
        if( !($params = PHS_Bg_jobs::get_current_job_parameters())
         or !is_array( $params )
         or empty( $params['mid'] )
         or !($messages_model = PHS::load_model( 'messages', 'messages' ))
         or !($m_flow_params = $messages_model->fetch_default_flow_params( array( 'table_name' => 'messages' ) ))
         or !($message_arr = $messages_model->get_details( $params['mid'], $m_flow_params ))
         or !$messages_model->need_write_finish( $message_arr ) )
        {
            $this->set_error( self::ERR_UNKNOWN_MESSAGE, $this->_pt( 'Message doesn\'t require additional work.' ) );
            return false;
        }

        if( !$messages_model->write_message_finish_bg( $message_arr, $params ) )
        {
            if( $messages_model->has_error() )
                $this->copy_error( $messages_model, self::ERR_FINISH_ERROR );
            else
                $this->set_error( self::ERR_FINISH_ERROR, $this->_pt( 'Error finishing additional work required for message #%s.', $message_arr['id'] ) );

            return false;
        }

        return PHS_Action::default_action_result();
    }
}
