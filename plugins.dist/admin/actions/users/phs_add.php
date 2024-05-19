<?php
namespace phs\plugins\admin\actions\users;

use phs\PHS;
use phs\PHS_Scope;
use phs\libraries\PHS_Roles;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\system\core\models\PHS_Model_Roles;
use phs\plugins\accounts\PHS_Plugin_Accounts;
use phs\system\core\models\PHS_Model_Plugins;
use phs\system\core\models\PHS_Model_Tenants;
use phs\plugins\accounts\models\PHS_Model_Accounts;
use phs\plugins\accounts\models\PHS_Model_Accounts_tenants;

class PHS_Action_Add extends PHS_Action
{
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX];
    }

    public function execute()
    {
        PHS::page_settings('page_title', $this->_pt('Add User'));

        if (!($current_user = PHS::user_logged_in())) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        $is_multi_tenant = PHS::is_multi_tenant();

        $tenants_model = null;
        /** @var PHS_Plugin_Accounts $accounts_plugin */
        /** @var PHS_Plugin_Admin $admin_plugin */
        /** @var PHS_Model_Accounts $accounts_model */
        /** @var PHS_Model_Roles $roles_model */
        /** @var PHS_Model_Plugins $plugins_model */
        /** @var PHS_Model_Tenants $tenants_model */
        if (!($accounts_plugin = PHS_Plugin_Accounts::get_instance())
         || !($admin_plugin = PHS_Plugin_Admin::get_instance())
         || !($accounts_plugin_settings = $accounts_plugin->get_plugin_settings())
         || !($accounts_model = PHS_Model_Accounts::get_instance())
         || !($roles_model = PHS_Model_Roles::get_instance())
         || !($plugins_model = PHS_Model_Plugins::get_instance())
         || ($is_multi_tenant && !($tenants_model = PHS_Model_Tenants::get_instance()))
        ) {
            PHS_Notifications::add_error_notice($this->_pt('Error loading required resources.'));

            return self::default_action_result();
        }

        if (!$admin_plugin->can_admin_manage_accounts()) {
            PHS_Notifications::add_error_notice($this->_pt('You don\'t have rights to access this section.'));

            return self::default_action_result();
        }

        if (!($roles_by_slug = $roles_model->get_all_roles_by_slug())) {
            $roles_by_slug = [];
        }

        $all_tenants_arr = [];
        if ($is_multi_tenant
        && !($all_tenants_arr = $tenants_model->get_all_tenants())) {
            $all_tenants_arr = [];
        }

        $foobar = PHS_Params::_p('foobar', PHS_Params::T_INT);
        $nick = PHS_Params::_p('nick', PHS_Params::T_NOHTML);
        $pass = PHS_Params::_p('pass', PHS_Params::T_ASIS);
        $email = PHS_Params::_p('email', PHS_Params::T_EMAIL);
        $level = PHS_Params::_p('level', PHS_Params::T_INT);
        $title = PHS_Params::_p('title', PHS_Params::T_NOHTML);
        $fname = PHS_Params::_p('fname', PHS_Params::T_NOHTML);
        $lname = PHS_Params::_p('lname', PHS_Params::T_NOHTML);
        $phone = PHS_Params::_p('phone', PHS_Params::T_NOHTML);
        $company = PHS_Params::_p('company', PHS_Params::T_NOHTML);
        if (!($account_roles_slugs = PHS_Params::_p('account_roles_slugs', PHS_Params::T_ARRAY, ['type' => PHS_Params::T_NOHTML]))) {
            $account_roles_slugs = [];
        }
        if (!($account_tenants = PHS_Params::_p('account_tenants', PHS_Params::T_ARRAY, ['type' => PHS_Params::T_INT]))) {
            $account_tenants = [];
        }

        $do_submit = PHS_Params::_p('do_submit');

        if (empty($foobar)) {
            $level = $accounts_model::LVL_MEMBER;
        }

        if (!empty($do_submit)) {
            $insert_arr = [];
            $insert_arr['nick'] = $nick;
            $insert_arr['pass'] = $pass;
            $insert_arr['email'] = $email;
            $insert_arr['level'] = $level;
            $insert_arr['status'] = $accounts_model::STATUS_ACTIVE;
            $insert_arr['added_by'] = $current_user['id'];

            $insert_details_arr = [];
            $insert_details_arr['title'] = $title;
            $insert_details_arr['fname'] = $fname;
            $insert_details_arr['lname'] = $lname;
            $insert_details_arr['phone'] = $phone;
            $insert_details_arr['company'] = $company;

            $insert_params_arr = [];
            $insert_params_arr['fields'] = $insert_arr;
            $insert_params_arr['{users_details}'] = $insert_details_arr;
            $insert_params_arr['{account_roles}'] = $account_roles_slugs;
            $insert_params_arr['{account_tenants}'] = $account_tenants;
            $insert_params_arr['{send_confirmation_email}'] = true;

            if ($accounts_model->insert($insert_params_arr)) {
                PHS_Notifications::add_success_notice($this->_pt('User account created...'));

                return action_redirect(['p' => 'admin', 'a' => 'list', 'ad' => 'users'], ['account_created' => 1]);
            }

            if ($accounts_model->has_error()) {
                PHS_Notifications::add_error_notice($accounts_model->get_error_message());
            } else {
                PHS_Notifications::add_error_notice($this->_pt('Error saving details to database. Please try again.'));
            }
        }

        $data = [
            'nick'    => $nick,
            'pass'    => $pass,
            'level'   => $level,
            'email'   => $email,
            'title'   => $title,
            'fname'   => $fname,
            'lname'   => $lname,
            'phone'   => $phone,
            'company' => $company,

            'accounts_plugin_settings' => $accounts_plugin_settings,
            'user_levels'              => $accounts_model->get_levels(),
            'min_password_length'      => $accounts_plugin_settings['min_password_length'],
            'password_regexp'          => $accounts_plugin_settings['password_regexp'],

            'roles_by_slug'   => $roles_by_slug,
            'all_tenants_arr' => $all_tenants_arr,

            'tenants_model'   => $tenants_model,
            'roles_model'     => $roles_model,
            'plugins_model'   => $plugins_model,
            'accounts_plugin' => $accounts_plugin,
        ];

        return $this->quick_render_template('users/add', $data);
    }
}
