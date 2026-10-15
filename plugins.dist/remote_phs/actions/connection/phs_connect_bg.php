<?php
namespace phs\plugins\remote_phs\actions\connection;

use phs\PHS_Scope;
use phs\PHS_bg_jobs;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Logger;
use phs\system\core\attributes\PHS_Dependency;
use phs\plugins\remote_phs\models\PHS_Model_Phs_remote_domains;

class PHS_Action_Connect_bg extends PHS_Action
{
    #[PHS_Dependency]
    private ?PHS_Model_Phs_remote_domains $_domains_model = null;

    public function allowed_scopes(): array
    {
        return [PHS_Scope::SCOPE_BACKGROUND];
    }

    public function execute()
    {
        if (!($params = PHS_bg_jobs::get_current_job_parameters())
            || empty($params['rdid'])
            || !($domain_arr = $this->_domains_model->get_details($params['rdid']))) {
            $this->set_error(self::ERR_PARAMETERS, $this->_pt('PHS remote domain not found in database.'));

            return false;
        }

        if (!$this->_domains_model->act_connect_bg($domain_arr)) {
            PHS_Logger::error('Error connecting to PHS remote domain: '
                              .$this->_domains_model->get_simple_error_message(
                                  'Unknown error connecting to PHS remote domain ['.$domain_arr['title'].'] #'.$domain_arr['id']),
                PHS_Logger::TYPE_REMOTE);
        }

        return PHS_Action::default_action_result();
    }
}
