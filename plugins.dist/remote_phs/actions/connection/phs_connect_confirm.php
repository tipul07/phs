<?php

namespace phs\plugins\remote_phs\actions\connection;

use phs\PHS;
use phs\PHS_Api;
use phs\PHS_Scope;
use phs\PHS_Api_base;
use phs\libraries\PHS_Logger;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Api_action;
use phs\libraries\PHS_Notifications;

class PHS_Action_Connect_confirm extends PHS_Api_action
{
    /**
     * @return array|bool
     */
    public function execute()
    {
        if (!($api_obj = $this->get_action_api_instance())
         || !($apikey_arr = $api_obj->get_request_apikey())
         || empty($apikey_arr['id'])) {
            return $this->send_api_error(PHS_Api_base::H_CODE_BAD_REQUEST, self::ERR_PARAMETERS,
                $this->_pt('Error obtaining API key details.'));
        }

        /** @var \phs\plugins\remote_phs\models\PHS_Model_Phs_remote_domains $domains_model */
        if (!($domains_model = PHS::load_model('phs_remote_domains', 'remote_phs'))) {
            return $this->send_api_error(PHS_Api_base::H_CODE_INTERNAL_SERVER_ERROR, self::ERR_FUNCTIONALITY,
                $this->_pt('Error loading required resources.'));
        }

        $remote_id = $this->request_var('remote_id', PHS_Params::T_INT, 0);
        $remote_www = $this->request_var('remote_www', PHS_Params::T_NOHTML, '');

        if (empty($remote_www) || empty($remote_id)
         || !($domain_arr = $domains_model->get_details_fields(['remote_id' => $remote_id],
             ['table_name' => 'phs_remote_domains']))
         || $domains_model->is_deleted($domain_arr)
         || $domain_arr['remote_www'] !== $remote_www) {
            return $this->send_api_error(PHS_Api_base::H_CODE_BAD_REQUEST, self::ERR_PARAMETERS,
                $this->_pt('Error obtaining remote domain details.'));
        }

        $edit_arr = [];
        $edit_arr['status'] = $domains_model::STATUS_CONNECTED;
        $edit_arr['error_log'] = null;

        $edit_params = [];
        $edit_params['fields'] = $edit_arr;

        if (!($new_domain_arr = $domains_model->edit($domain_arr, $edit_params))) {
            PHS_Logger::error('Error updating incoming remote domain connection for '.$domain_arr['title'].' #'.$domain_arr['id'].'.', PHS_Logger::TYPE_REMOTE);

            return $this->send_api_error(PHS_Api_base::H_CODE_INTERNAL_SERVER_ERROR, self::ERR_FUNCTIONALITY,
                $this->_pt('Error updating remote domain details.'));
        }
        $domain_arr = $new_domain_arr;

        $payload_arr = [
            'remote_id' => (int)$domain_arr['id'],
        ];

        return $this->send_api_success($payload_arr);
    }
}
