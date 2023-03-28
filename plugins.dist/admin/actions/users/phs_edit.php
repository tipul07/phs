<?php
namespace phs\plugins\admin\actions\users;

use phs\PHS;
use phs\PHS_Scope;
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

class PHS_Action_Edit extends PHS_Action
{
    /**
     * Returns an array of scopes in which action is allowed to run
     *
     * @return array If empty array, action is allowed in all scopes...
     */
    public function allowed_scopes()
    {
        return [PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX];
    }

    /**
     * @return array|bool
     */
    public function execute()
    {
        PHS::page_settings('page_title', $this->_pt('Edit User'));

        if (!($current_user = PHS::user_logged_in())) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        $is_multi_tenant = PHS::is_multi_tenant();

        /** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
        /** @var \phs\plugins\admin\PHS_Plugin_Admin $admin_plugin */
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        /** @var \phs\system\core\models\PHS_Model_Roles $roles_model */
        /** @var \phs\system\core\models\PHS_Model_Plugins $plugins_model */
        /** @var \phs\system\core\models\PHS_Model_Tenants $tenants_model */
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts_tenants $accounts_tenants_model */
        if (!($accounts_plugin = PHS_Plugin_Accounts::get_instance())
         || !($admin_plugin = PHS_Plugin_Admin::get_instance())
         || !($accounts_plugin_settings = $accounts_plugin->get_plugin_settings())
         || !($accounts_model = PHS_Model_Accounts::get_instance())
         || !($roles_model = PHS_Model_Roles::get_instance())
         || !($plugins_model = PHS_Model_Plugins::get_instance())
         || ($is_multi_tenant
             && (!($tenants_model = PHS_Model_Tenants::get_instance())
                 || !($accounts_tenants_model = PHS_Model_Accounts_tenants::get_instance())))
        ) {
            PHS_Notifications::add_error_notice($this->_pt('Error loading required resources.'));

            return self::default_action_result();
        }

        if (!$admin_plugin->can_admin_manage_accounts()) {
            PHS_Notifications::add_error_notice($this->_pt('You don\'t have rights to manage accounts.'));

            return self::default_action_result();
        }

        $uid = PHS_Params::_gp('uid', PHS_Params::T_INT);
        $back_page = PHS_Params::_gp('back_page', PHS_Params::T_ASIS);

        if (empty($uid)
         || !($account_arr = $accounts_model->get_details($uid))
         || $accounts_model->is_deleted($account_arr)) {
            PHS_Notifications::add_warning_notice($this->_pt('Invalid account...'));

            $action_result = self::default_action_result();

            $args = ['unknown_account' => 1];

            if (empty($back_page)) {
                $back_page = PHS::url(['p' => 'admin', 'a' => 'list', 'ad' => 'users']);
            } else {
                $back_page = from_safe_url($back_page);
            }

            $back_page = add_url_params($back_page, $args);

            $action_result['redirect_to_url'] = $back_page;

            return $action_result;
        }

        if (!$accounts_model->can_manage_account($current_user, $account_arr)) {
            PHS_Notifications::add_warning_notice($this->_pt('Invalid account...'));

            $action_result = self::default_action_result();

            $args = ['cannot_edit_account' => 1];

            if (empty($back_page)) {
                $back_page = PHS::url(['p' => 'admin', 'a' => 'list', 'ad' => 'users']);
            } else {
                $back_page = from_safe_url($back_page);
            }

            $back_page = add_url_params($back_page, $args);

            $action_result['redirect_to_url'] = $back_page;

            return $action_result;
        }

        if (!($roles_by_slug = $roles_model->get_all_roles_by_slug())) {
            $roles_by_slug = [];
        }
        if (!($account_roles = $roles_model->get_user_roles_slugs($account_arr))) {
            $account_roles = [];
        }

        $all_tenants_arr = [];
        $db_account_tenants = [];
        if( $is_multi_tenant ) {
            if (!($all_tenants_arr = $tenants_model->get_all_tenants())) {
                $all_tenants_arr = [];
            }
            if (!($db_account_tenants = $accounts_tenants_model->get_account_tenants_as_ids_array($account_arr['id']))) {
                $db_account_tenants = [];
            }
        }

        if (!($account_details_arr = $accounts_model->get_account_details($account_arr, ['populate_with_empty_data' => true]))) {
            $account_details_arr = [];
        }

        if (PHS_Params::_g('changes_saved', PHS_Params::T_INT)) {
            PHS_Notifications::add_success_notice($this->_pt('Account details saved in database.'));
        }

        $foobar = PHS_Params::_p('foobar', PHS_Params::T_INT);
        $nick = PHS_Params::_p('nick', PHS_Params::T_NOHTML);
        $pass = PHS_Params::_p('pass', PHS_Params::T_ASIS);
        $pass2 = PHS_Params::_p('pass2', PHS_Params::T_ASIS);
        $email = PHS_Params::_p('email', PHS_Params::T_EMAIL);
        $level = PHS_Params::_p('level', PHS_Params::T_INT);
        $title = PHS_Params::_p('title', PHS_Params::T_NOHTML);
        $fname = PHS_Params::_p('fname', PHS_Params::T_NOHTML);
        $lname = PHS_Params::_p('lname', PHS_Params::T_NOHTML);
        $phone = PHS_Params::_p('phone', PHS_Params::T_NOHTML);
        $company = PHS_Params::_p('company', PHS_Params::T_NOHTML);
        $account_roles_slugs = PHS_Params::_p('account_roles_slugs', PHS_Params::T_ARRAY, ['type' => PHS_Params::T_NOHTML]);
        if( !($account_tenants = PHS_Params::_p('account_tenants', PHS_Params::T_ARRAY, ['type' => PHS_Params::T_INT])) ) {
            $account_tenants = [];
        }

        $do_submit = PHS_Params::_p('do_submit');

        if (empty($foobar)) {
            $nick = $account_arr['nick'];
            $email = $account_arr['email'];
            $level = $account_arr['level'];

            $title = $account_details_arr['title'];
            $fname = $account_details_arr['fname'];
            $lname = $account_details_arr['lname'];
            $phone = $account_details_arr['phone'];
            $company = $account_details_arr['company'];
        }

        if (!empty($do_submit)) {
            if ((!empty($pass) || !empty($pass2))
             && $pass !== $pass2) {
                PHS_Notifications::add_error_notice($this->_pt('Password fields don\'t match.'));
            } else {
                $edit_arr = [];
                $edit_arr['nick'] = $nick;
                if (!empty($pass)) {
                    $edit_arr['pass'] = $pass;
                }
                $edit_arr['email'] = $email;
                if ($account_arr['level'] < $current_user['level']) {
                    $edit_arr['level'] = $level;
                }

                $edit_details_arr = [];
                $edit_details_arr['title'] = $title;
                $edit_details_arr['fname'] = $fname;
                $edit_details_arr['lname'] = $lname;
                $edit_details_arr['phone'] = $phone;
                $edit_details_arr['company'] = $company;

                $edit_params_arr = [];
                $edit_params_arr['fields'] = $edit_arr;
                $edit_params_arr['{users_details}'] = $edit_details_arr;
                $edit_params_arr['{account_roles}'] = $account_roles_slugs;
                $edit_params_arr['{account_tenants}'] = $account_tenants;
                $edit_params_arr['{send_confirmation_email}'] = true;

                if ($accounts_model->edit($account_arr, $edit_params_arr)) {
                    PHS_Notifications::add_success_notice($this->_pt('Account details saved in database.'));

                    $args = ['uid' => $account_arr['id'], 'changes_saved' => 1];

                    if (!empty($back_page)) {
                        $args['back_page'] = $back_page;
                    }

                    return action_redirect(['p' => 'admin', 'a' => 'edit', 'ad' => 'users'], $args);
                }

                if ($accounts_model->has_error()) {
                    PHS_Notifications::add_error_notice($accounts_model->get_error_message());
                } else {
                    PHS_Notifications::add_error_notice($this->_pt('Error saving details to database. Please try again.'));
                }
            }
        }

        $data = [
            'uid'          => $account_arr['id'],
            'back_page'    => $back_page,
            'account_data' => $account_arr,

            'nick'          => $nick,
            'pass'          => $pass,
            'pass2'         => $pass2,
            'level'         => $level,
            'email'         => $email,
            'title'         => $title,
            'fname'         => $fname,
            'lname'         => $lname,
            'phone'         => $phone,
            'company'       => $company,
            'account_roles' => $account_roles,
            'db_account_tenants' => $db_account_tenants,

            'accounts_plugin_settings' => $accounts_plugin_settings,
            'user_levels'              => $accounts_model->get_levels(),
            'min_password_length'      => $accounts_plugin_settings['min_password_length'],
            'password_regexp'          => $accounts_plugin_settings['password_regexp'],

            'roles_by_slug' => $roles_by_slug,
            'all_tenants_arr' => $all_tenants_arr,

            'tenants_model'   => $tenants_model,
            'roles_model'   => $roles_model,
            'plugins_model' => $plugins_model,
        ];

        return $this->quick_render_template('users/edit', $data);
    }
}
