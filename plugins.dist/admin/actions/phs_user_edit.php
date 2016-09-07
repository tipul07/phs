<?php

namespace phs\plugins\admin\actions;

use \phs\PHS;
use \phs\PHS_bg_jobs;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_params;
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

            $args = array(
                'back_page' => PHS::current_url()
            );

            $action_result['redirect_to_url'] = PHS::url( array( 'p' => 'accounts', 'a' => 'login' ), $args );

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

        if( !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_MANAGE_ACCOUNTS ) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'You don\'t have rights to manage accounts.' ) );
            return self::default_action_result();
        }

        $uid = PHS_params::_gp( 'uid', PHS_params::T_INT );
        $back_page = PHS_params::_gp( 'back_page', PHS_params::T_ASIS );

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

        if( PHS_params::_g( 'changes_saved', PHS_params::T_INT ) )
            PHS_Notifications::add_success_notice( $this->_pt( 'Account details saved in database.' ) );

        $foobar = PHS_params::_p( 'foobar', PHS_params::T_INT );
        $nick = PHS_params::_p( 'nick', PHS_params::T_NOHTML );
        $pass = PHS_params::_p( 'pass', PHS_params::T_ASIS );
        $pass2 = PHS_params::_p( 'pass2', PHS_params::T_ASIS );
        $email = PHS_params::_p( 'email', PHS_params::T_EMAIL );
        $level = PHS_params::_p( 'level', PHS_params::T_INT );
        $account_roles_slugs = PHS_params::_p( 'account_roles_slugs', PHS_params::T_ARRAY, array( 'type' => PHS_params::T_NOHTML ) );
        $title = PHS_params::_p( 'title', PHS_params::T_NOHTML );
        $fname = PHS_params::_p( 'fname', PHS_params::T_NOHTML );
        $lname = PHS_params::_p( 'lname', PHS_params::T_NOHTML );
        $phone = PHS_params::_p( 'phone', PHS_params::T_NOHTML );
        $company = PHS_params::_p( 'company', PHS_params::T_NOHTML );

        $do_submit = PHS_params::_p( 'do_submit' );

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
        );

        return $this->quick_render_template( 'user_edit', $data );
    }
}
