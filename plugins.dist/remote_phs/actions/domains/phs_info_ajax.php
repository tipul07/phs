<?php
namespace phs\plugins\remote_phs\actions\domains;

use phs\PHS;
use phs\PHS_Scope;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_params;
use phs\libraries\PHS_Notifications;
use phs\plugins\remote_phs\PHS_Plugin_Remote_phs;
use phs\plugins\remote_phs\models\PHS_Model_Phs_remote_domains;

class PHS_Action_Info_ajax extends PHS_Action
{
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_AJAX];
    }

    public function execute()
    {
        PHS::page_settings('page_title', $this->_pt('Ping Remote Domain'));

        if (!($current_user = PHS::user_logged_in())) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        /** @var PHS_Plugin_Remote_phs $remote_plugin */
        /** @var PHS_Model_Phs_remote_domains $domains_model */
        if (!($remote_plugin = PHS_Plugin_Remote_phs::get_instance())
            || !($domains_model = PHS_Model_Phs_remote_domains::get_instance())) {
            PHS_Notifications::add_error_notice($this->_pt('Couldn\'t load required resources.'));

            return self::default_action_result();
        }

        if (!$remote_plugin->can_admin_list_domains()) {
            PHS_Notifications::add_error_notice($this->_pt('You don\'t have rights to access this section.'));

            return self::default_action_result();
        }

        $domain_id = PHS_params::_gp('domain_id', PHS_params::T_INT);
        $do_ping = PHS_params::_gp('do_ping', PHS_params::T_INT);

        if (empty($domain_id)
         || !($domain_arr = $domains_model->get_details($domain_id, ['table_name' => 'phs_remote_domains']))
         || $domains_model->is_deleted($domain_arr)) {
            PHS_Notifications::add_warning_notice($this->_pt('Remote domain is not connected.'));

            return self::default_action_result();
        }

        $ping_result = false;
        if (!empty($do_ping)) {
            if (!$remote_plugin->can_admin_ping_domains($current_user)) {
                PHS_Notifications::add_error_notice($this->_pt('You don\'t have rights to access this section.'));

                return self::default_action_result();
            }

            $ping_result = [
                'has_error' => false,
                'error_msg' => false,
                'timezone'  => false,
                'result'    => [],
            ];

            $msg = [
                'route' => ['p' => 'remote_phs', 'c' => 'remote', 'a' => 'ping', 'ad' => 'remote'],
            ];

            if (!($response_arr = $domains_model->send_request_to_domain($domain_arr, $msg))
             || !is_array($response_arr)) {
                // Communication error...
                $error_msg = $this->_pt('Error sending ping request to remote domain.').' ';
                if (!$domains_model->has_error()) {
                    $error_msg .= $this->_pt('Unknown error.');
                } else {
                    $error_msg .= $domains_model->get_simple_error_message();
                }

                $ping_result['has_error'] = true;
                $ping_result['error_msg'] = $error_msg;
            } else {
                // Logical error in remote action
                $ping_result['has_error'] = (!empty($response_arr['has_error']));

                if (!empty($response_arr['has_error'])) {
                    $ping_result['error_msg'] = $this->_pt('Error received from remote action: %s',
                        (isset($response_arr['error_code']) ? '['.$response_arr['error_code'].'] ' : '')
                        .($response_arr['error_msg'] ?? $this->_pt('N/A')));
                } elseif (!empty($response_arr['json_arr']) && is_array($response_arr['json_arr'])) {
                    if (isset($response_arr['json_arr']['timezone'])) {
                        $ping_result['timezone'] = (int)$response_arr['json_arr']['timezone'];
                    }

                    $ping_result['result'] = $response_arr['json_arr']['response'];
                }
            }
        }

        $data = [
            'domain_arr' => $domain_arr,

            'do_ping'     => $do_ping,
            'ping_result' => $ping_result,

            'domains_model' => $domains_model,
        ];

        return $this->quick_render_template('domains/info', $data);
    }
}
