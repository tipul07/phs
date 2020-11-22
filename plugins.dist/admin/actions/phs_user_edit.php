<?php

namespace phs\plugins\admin\actions;

use \phs\PHS;
use \phs\PHS_Bg_jobs;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Params;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Roles;

class PHS_Action_User_edit extends PHS_Action
{
    /**
     * Returns an array of scopes in which action is allowed to run
     *
     * @return array If empty array, action is allowed in all scopes...
     */
    public function allowed_scopes()
    {
        return array( PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX );
    }

    /**
     * @return array|bool
     */
    public function execute()
    {
        PHS::page_settings( 'page_title', $this->_pt( 'Edit User' ) );

        if( !($current_user = PHS::user_logged_in()) )
        {
            PHS_Notifications::add_warning_notice( $this->_pt( 'You should login first...' ) );

            $action_result = self::default_action_result();

            $action_result['request_login'] = true;

            return $action_result;
        }

        /** @var \phs\plugins\accounts\PHS_Plugin_Accounts $accounts_plugin */
        if( !($accounts_plugin = PHS::load_plugin( 'accounts' ))
         or !($accounts_plugin_settings = $accounts_plugin->get_plugin_settings()) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load accounts plugin.' ) );
            return self::default_action_result();
        }

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($accounts_model = PHS::load_model( 'accounts', 'accounts' )) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load accounts model.' ) );
            return self::default_action_result();
        }

        /** @var \phs\system\core\models\PHS_Model_Roles $roles_model */
        if( !($roles_model = PHS::load_model( 'roles' )) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load roles model.' ) );
            return self::default_action_result();
        }

        /** @var \phs\system\core\models\PHS_Model_Plugins $plugins_model */
        if( !($plugins_model = PHS::load_model( 'plugins' )) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load plugins model.' ) );
            return self::default_action_result();
        }

        if( !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_MANAGE_ACCOUNTS ) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'You don\'t have rights to manage accounts.' ) );
            return self::default_action_result();
        }

        $uid = PHS_Params::_gp( 'uid', PHS_Params::T_INT );
        $back_page = PHS_Params::_gp( 'back_page', PHS_Params::T_ASIS );

        if( empty( $uid )
         or !($account_arr = $accounts_model->get_details( $uid ))
         or $accounts_model->is_deleted( $account_arr ) )
        {
            PHS_Notifications::add_warning_notice( $this->_pt( 'Invalid account...' ) );

            $action_result = self::default_action_result();

            $args = array(
                'unknown_account' => 1
            );

            if( empty( $back_page ) )
                $back_page = PHS::url( array( 'p' => 'admin', 'a' => 'users_list' ) );
            else
                $back_page = from_safe_url( $back_page );

            $back_page = add_url_params( $back_page, $args );

            $action_result['redirect_to_url'] = $back_page;

            return $action_result;
        }

        if( !($roles_by_slug = $roles_model->get_all_roles_by_slug()) )
            $roles_by_slug = array();
        if( !($account_roles = $roles_model->get_user_roles_slugs( $account_arr )) )
            $account_roles = array();

        if( !($account_details_arr = $accounts_model->get_account_details( $account_arr, array( 'populate_with_empty_data' => true ) )) )
            $account_details_arr = array();

        if( PHS_Params::_g( 'changes_saved', PHS_Params::T_INT ) )
            PHS_Notifications::add_success_notice( $this->_pt( 'Account details saved in database.' ) );

        $foobar = PHS_Params::_p( 'foobar', PHS_Params::T_INT );
        $nick = PHS_Params::_p( 'nick', PHS_Params::T_NOHTML );
        $pass = PHS_Params::_p( 'pass', PHS_Params::T_ASIS );
        $pass2 = PHS_Params::_p( 'pass2', PHS_Params::T_ASIS );
        $email = PHS_Params::_p( 'email', PHS_Params::T_EMAIL );
        $level = PHS_Params::_p( 'level', PHS_Params::T_INT );
        $account_roles_slugs = PHS_Params::_p( 'account_roles_slugs', PHS_Params::T_ARRAY, array( 'type' => PHS_Params::T_NOHTML ) );
        $title = PHS_Params::_p( 'title', PHS_Params::T_NOHTML );
        $fname = PHS_Params::_p( 'fname', PHS_Params::T_NOHTML );
        $lname = PHS_Params::_p( 'lname', PHS_Params::T_NOHTML );
        $phone = PHS_Params::_p( 'phone', PHS_Params::T_NOHTML );
        $company = PHS_Params::_p( 'company', PHS_Params::T_NOHTML );

        $do_submit = PHS_Params::_p( 'do_submit' );

        if( empty( $foobar ) )
        {
            $nick = $account_arr['nick'];
            $email = $account_arr['email'];
            $level = $account_arr['level'];

            $title = $account_details_arr['title'];
            $fname = $account_details_arr['fname'];
            $lname = $account_details_arr['lname'];
            $phone = $account_details_arr['phone'];
            $company = $account_details_arr['company'];
        }

        if( !empty( $do_submit ) )
        {
            if( (!empty( $pass ) or !empty( $pass2 ))
            and $pass != $pass2 )
                PHS_Notifications::add_error_notice( $this->_pt( 'Password fields don\'t match.' ) );

            else
            {
                $edit_arr = array();
                $edit_arr['nick'] = $nick;
                if( !empty( $pass ) )
                    $edit_arr['pass'] = $pass;
                $edit_arr['email'] = $email;
                $edit_arr['level'] = $level;

                $edit_details_arr = array();
                $edit_details_arr['title'] = $title;
                $edit_details_arr['fname'] = $fname;
                $edit_details_arr['lname'] = $lname;
                $edit_details_arr['phone'] = $phone;
                $edit_details_arr['company'] = $company;

                $edit_params_arr = array();
                $edit_params_arr['fields'] = $edit_arr;
                $edit_params_arr['{users_details}'] = $edit_details_arr;
                $edit_params_arr['{account_roles}'] = $account_roles_slugs;
                $edit_params_arr['{send_confirmation_email}'] = true;

                if( ($new_account = $accounts_model->edit( $account_arr, $edit_params_arr )) )
                {
                    PHS_Notifications::add_success_notice( $this->_pt( 'Account details saved in database.' ) );

                    $action_result = self::default_action_result();

                    $action_result['redirect_to_url'] = PHS::url( array( 'p' => 'admin', 'a' => 'user_edit' ), array( 'uid' => $account_arr['id'], 'changes_saved' => 1 ) );

                    return $action_result;
                } else
                {
                    if( $accounts_model->has_error() )
                        PHS_Notifications::add_error_notice( $accounts_model->get_error_message() );
                    else
                        PHS_Notifications::add_error_notice( $this->_pt( 'Error saving details to database. Please try again.' ) );
                }
            }
        }

        $data = array(
            'uid' => $account_arr['id'],
            'back_page' => $back_page,

            'nick' => $nick,
            'pass' => $pass,
            'pass2' => $pass2,
            'level' => $level,
            'email' => $email,
            'title' => $title,
            'fname' => $fname,
            'lname' => $lname,
            'phone' => $phone,
            'company' => $company,
            'account_roles' => $account_roles,

            'accounts_plugin_settings' => $accounts_plugin_settings,
            'user_levels' => $accounts_model->get_levels(),
            'min_password_length' => $accounts_plugin_settings['min_password_length'],
            'password_regexp' => $accounts_plugin_settings['password_regexp'],

            'roles_by_slug' => $roles_by_slug,

            'roles_model' => $roles_model,
            'plugins_model' => $plugins_model,
        );

        return $this->quick_render_template( 'user_edit', $data );
    }
}
