<?php
namespace phs\plugins\admin\actions\users\api;

use phs\PHS;
use phs\PHS_Scope;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\system\core\models\PHS_Model_Tenants;
use phs\plugins\accounts\models\PHS_Model_Accounts;
use phs\plugins\accounts\models\PHS_Model_Accounts_tenants;
use phs\system\core\events\accounts\PHS_Event_Accounts_info_data;

class PHS_Action_Info extends PHS_Action
{
    private ?PHS_Plugin_Admin $admin_plugin = null;

    private ?PHS_Model_Accounts $accounts_model = null;

    private ?PHS_Model_Tenants $tenants_model = null;

    private ?PHS_Model_Accounts_tenants $accounts_tenants_model = null;

    /**
     * @inheritdoc
     */
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_AJAX];
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        PHS::page_settings('page_title', $this->_pt('Account details'));

        if (!($current_user = PHS::user_logged_in())) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        if (!$this->_load_dependencies()) {
            PHS_Notifications::add_error_notice($this->get_simple_error_message());

            return self::default_action_result();
        }

        if (!($account_id = PHS_Params::_gp('account_id', PHS_Params::T_INT))
            || !($account_arr = $this->accounts_model->get_details($account_id, ['table_name' => 'users']))
            || $this->accounts_model->is_deleted($account_arr)
        ) {
            PHS_Notifications::add_error_notice($this->_pt('Invalid account.'));

            return self::default_action_result();
        }

        $account_details = $this->accounts_model->get_account_details($account_arr) ?: [];
        $all_tenants_arr = $this->tenants_model->get_all_tenants(true) ?: [];
        $db_account_tenants = $this->accounts_tenants_model->get_account_tenants_as_ids_array($account_arr['id']) ?: [];

        $event_data = [];
        $event_data['account_data'] = $account_arr;
        $event_data['account_details_data'] = $account_details;

        $data = [];
        $data['account_data'] = $account_arr;
        $data['account_details_data'] = $account_details;
        $data['all_tenants_arr'] = $all_tenants_arr;
        $data['db_account_tenants'] = $db_account_tenants;

        if (($event_obj = PHS_Event_Accounts_info_data::trigger($event_data)) !== false
            && ($template_data = $event_obj->get_output('template_data'))) {
            $data = array_merge($data, $template_data);
        }

        $data['account_levels'] = $this->accounts_model->get_levels_as_key_val();
        $data['account_statuses'] = $this->accounts_model->get_statuses_as_key_val();

        $data['accounts_model'] = $this->accounts_model;

        return $this->quick_render_template('users/api/info', $data);
    }

    private function _load_dependencies() : bool
    {
        $this->reset_error();

        if (
            (!$this->admin_plugin && !($this->admin_plugin = PHS_Plugin_Admin::get_instance()))
            || (!$this->accounts_model && !($this->accounts_model = PHS_Model_Accounts::get_instance()))
            || (!$this->tenants_model && !($this->tenants_model = PHS_Model_Tenants::get_instance()))
            || (!$this->accounts_tenants_model && !($this->accounts_tenants_model = PHS_Model_Accounts_tenants::get_instance()))
        ) {
            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Error loading required resources.'));

            return false;
        }

        return true;
    }
}
