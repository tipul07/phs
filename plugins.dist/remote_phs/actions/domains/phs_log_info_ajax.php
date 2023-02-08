<?php
namespace phs\plugins\remote_phs\actions\domains;

use phs\PHS;
use phs\PHS_Scope;
use phs\PHS_bg_jobs;
use phs\libraries\PHS_Hooks;
use phs\libraries\PHS_Roles;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_params;
use phs\libraries\PHS_line_params;
use phs\libraries\PHS_Notifications;

class PHS_Action_Log_info_ajax extends PHS_Action
{
    public function allowed_scopes()
    {
        return [PHS_Scope::SCOPE_AJAX];
    }

    /**
     * @return array|bool
     */
    public function execute()
    {
        PHS::page_settings('page_title', $this->_pt('Remote Domain Log'));

        if (!($current_user = PHS::user_logged_in())) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            $action_result = self::default_action_result();

            $action_result['request_login'] = true;

            return $action_result;
        }

        /** @var \phs\plugins\remote_phs\PHS_Plugin_Remote_phs $remote_plugin */
        /** @var \phs\plugins\remote_phs\models\PHS_Model_Phs_remote_domains $domains_model */
        if (!($remote_plugin = PHS::load_plugin('remote_phs'))
         || !($domains_model = PHS::load_model('phs_remote_domains', 'remote_phs'))) {
            PHS_Notifications::add_error_notice($this->_pt('Couldn\'t load required resources.'));

            return self::default_action_result();
        }

        if (!$remote_plugin->can_admin_list_logs($current_user)) {
            PHS_Notifications::add_error_notice($this->_pt('You don\'t have rights to list remote domain logs.'));

            return self::default_action_result();
        }

        $log_id = PHS_params::_gp('log_id', PHS_params::T_INT);
        if (empty($log_id)
         || !($log_arr = $domains_model->get_details($log_id, ['table_name' => 'phs_remote_logs']))) {
            PHS_Notifications::add_warning_notice($this->_pt('Remote domain log not found.'));

            return self::default_action_result();
        }

        if (empty($log_arr['domain_id'])
         || !($domain_arr = $domains_model->get_details($log_arr['domain_id'], ['table_name' => 'phs_remote_domains']))) {
            $domain_arr = false;
        }

        $data = [
            'log_arr'    => $log_arr,
            'domain_arr' => $domain_arr,

            'domains_model' => $domains_model,
        ];

        return $this->quick_render_template('domains/log_info', $data);
    }
}
