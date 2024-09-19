<?php

namespace phs\plugins\messages\actions;

use phs\PHS_Scope;
use phs\PHS_Bg_jobs;
use phs\libraries\PHS_Action;
use phs\plugins\messages\models\PHS_Model_Messages;

class PHS_Action_Write_message_bg extends PHS_Action
{
    public const ERR_UNKNOWN_MESSAGE = 40000, ERR_FINISH_ERROR = 40001;

    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_BACKGROUND];
    }

    public function execute()
    {
        /** @var PHS_Model_Messages $messages_model */
        if (!($params = PHS_Bg_jobs::get_current_job_parameters())
         || empty($params['mid'])
         || !($messages_model = PHS_Model_Messages::get_instance())
         || !($m_flow_params = $messages_model->fetch_default_flow_params(['table_name' => 'messages']))
         || !($message_arr = $messages_model->get_details($params['mid'], $m_flow_params))) {
            $this->set_error(self::ERR_UNKNOWN_MESSAGE, $this->_pt('Message doesn\'t require additional work.'));

            return false;
        }

        if (!$messages_model->need_write_finish($message_arr)) {
            return self::default_action_result();
        }

        if (!$messages_model->write_message_finish_bg($message_arr, $params)) {
            $this->copy_or_set_error(
                $messages_model,
                self::ERR_FINISH_ERROR,
                $this->_pt('Error finishing additional work required for message #%s.', $message_arr['id'])
            );

            return false;
        }

        return self::default_action_result();
    }
}
