<?php
namespace phs\plugins\remote_phs\actions\domains;

use phs\PHS;
use phs\PHS_Scope;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_params;
use phs\libraries\PHS_Notifications;
use phs\system\core\models\PHS_Model_Api_keys;
use phs\plugins\remote_phs\PHS_Plugin_Remote_phs;
use phs\plugins\accounts\models\PHS_Model_Accounts;
use phs\plugins\remote_phs\models\PHS_Model_Phs_remote_domains;

class PHS_Action_Add extends PHS_Action
{
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_WEB];
    }

    public function execute()
    {
        PHS::page_settings('page_title', $this->_pt('Add Remote PHS Domain'));

        if (!($current_user = PHS::user_logged_in())) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        /** @var PHS_Plugin_Remote_phs $remote_plugin */
        /** @var PHS_Model_Api_keys $apikeys_model */
        /** @var PHS_Model_Accounts $accounts_model */
        /** @var PHS_Model_Phs_remote_domains $domains_model */
        if (!($remote_plugin = PHS_Plugin_Remote_phs::get_instance())
         || !($apikeys_model = PHS_Model_Api_keys::get_instance())
         || !($accounts_model = PHS_Model_Accounts::get_instance())
         || !($domains_model = PHS_Model_Phs_remote_domains::get_instance())) {
            PHS_Notifications::add_error_notice($this->_pt('Error loading required resources.'));

            return self::default_action_result();
        }

        if (!$remote_plugin->can_admin_manage_domains($current_user)) {
            PHS_Notifications::add_error_notice($this->_pt('You don\'t have rights to access this section.'));

            return self::default_action_result();
        }

        if (PHS_params::_g('changes_saved', PHS_params::T_INT)) {
            PHS_Notifications::add_success_notice($this->_pt('Remote domain details saved in database.'));
        }

        if (!($apikeys_arr = $apikeys_model->get_all_api_keys_as_key_val())) {
            $apikeys_arr = [];
        }

        $foobar = PHS_params::_p('foobar', PHS_params::T_INT);
        $title = PHS_params::_pg('title', PHS_params::T_NOHTML);
        $handle = PHS_params::_pg('handle', PHS_params::T_NOHTML);
        $remote_www = PHS_params::_pg('remote_www', PHS_params::T_NOHTML);
        $apikey_id = PHS_params::_p('apikey_id', PHS_params::T_INT);
        $out_apikey = PHS_params::_p('out_apikey', PHS_params::T_NOHTML);
        $out_apisecret = PHS_params::_p('out_apisecret', PHS_params::T_NOHTML);
        $out_timeout = PHS_params::_p('out_timeout', PHS_params::T_INT);
        $ips_whihtelist = PHS_params::_p('ips_whihtelist', PHS_params::T_NOHTML);

        if (PHS_params::_p('allow_incoming', PHS_params::T_INT)) {
            $allow_incoming = true;
        } else {
            $allow_incoming = false;
        }

        if (PHS_params::_p('log_requests', PHS_params::T_INT)) {
            $log_requests = true;
        } else {
            $log_requests = false;
        }

        if (PHS_params::_p('log_body', PHS_params::T_INT)) {
            $log_body = true;
        } else {
            $log_body = false;
        }

        $do_submit = PHS_params::_p('do_submit');

        if (!empty($do_submit)) {
            $insert_arr = [];
            $insert_arr['added_by_uid'] = $current_user['id'];
            $insert_arr['title'] = $title;
            $insert_arr['handle'] = $handle;
            $insert_arr['remote_www'] = $remote_www;
            $insert_arr['apikey_id'] = $apikey_id;
            $insert_arr['out_apikey'] = $out_apikey;
            $insert_arr['out_apisecret'] = $out_apisecret;
            $insert_arr['out_timeout'] = $out_timeout;
            $insert_arr['ips_whihtelist'] = $ips_whihtelist;
            $insert_arr['allow_incoming'] = $allow_incoming;
            $insert_arr['log_requests'] = $log_requests;
            $insert_arr['log_body'] = $log_body;
            $insert_arr['source'] = $domains_model::SOURCE_MANUALLY;

            $insert_params_arr = [];
            $insert_params_arr['fields'] = $insert_arr;

            if (($new_domain = $domains_model->insert($insert_params_arr))) {
                PHS_Notifications::add_success_notice($this->_pt('Remote domain details saved in database.'));

                return action_redirect(['p' => 'remote_phs', 'c' => 'admin', 'a' => 'list', 'ad' => 'domains'], ['domain_added' => 1]);
            }

            if ($domains_model->has_error()) {
                PHS_Notifications::add_error_notice($domains_model->get_error_message());
            } else {
                PHS_Notifications::add_error_notice($this->_pt('Error saving details to database. Please try again.'));
            }
        }

        $data = [
            'title'          => $title,
            'handle'         => $handle,
            'remote_www'     => $remote_www,
            'apikey_id'      => $apikey_id,
            'out_apikey'     => $out_apikey,
            'out_apisecret'  => $out_apisecret,
            'out_timeout'    => $out_timeout,
            'ips_whihtelist' => $ips_whihtelist,
            'allow_incoming' => $allow_incoming,
            'log_requests'   => $log_requests,
            'log_body'       => $log_body,

            'apikeys_arr' => $apikeys_arr,

            'accounts_model' => $accounts_model,
            'apikeys_model'  => $apikeys_model,
            'domains_model'  => $domains_model,
        ];

        return $this->quick_render_template('domains/add', $data);
    }
}
