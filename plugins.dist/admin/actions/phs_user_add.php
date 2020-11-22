<?php

namespace phs\plugins\admin\actions;

use \phs\PHS;
use \phs\PHS_Bg_jobs;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Params;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Roles;

class PHS_Action_User_add extends PHS_Action
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
        PHS::page_settings( 'page_title', $this->_pt( 'Add User' ) );

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

        if( !($roles_by_slug = $roles_model->get_all_roles_by_slug()) )
            $roles_by_slug = array();

        $foobar = PHS_Params::_p( 'foobar', PHS_Params::T_INT );
        $nick = PHS_Params::_p( 'nick', PHS_Params::T_NOHTML );
        $pass = PHS_Params::_p( 'pass', PHS_Params::T_ASIS );
        $email = PHS_Params::_p( 'email', PHS_Params::T_EMAIL );
        $level = PHS_Params::_p( 'level', PHS_Params::T_INT );
        if( !($account_roles_slugs = PHS_Params::_p( 'account_roles_slugs', PHS_Params::T_ARRAY, array( 'type' => PHS_Params::T_NOHTML ) )) )
            $account_roles_slugs = array();
        $title = PHS_Params::_p( 'title', PHS_Params::T_NOHTML );
        $fname = PHS_Params::_p( 'fname', PHS_Params::T_NOHTML );
        $lname = PHS_Params::_p( 'lname', PHS_Params::T_NOHTML );
        $phone = PHS_Params::_p( 'phone', PHS_Params::T_NOHTML );
        $company = PHS_Params::_p( 'company', PHS_Params::T_NOHTML );

        $do_submit = PHS_Params::_p( 'do_submit' );

        if( empty( $foobar ) )
        {
            $level = $accounts_model::LVL_MEMBER;
        }

        if( !empty( $do_submit ) )
        {
            $insert_arr = array();
            $insert_arr['nick'] = $nick;
            $insert_arr['pass'] = $pass;
            $insert_arr['email'] = $email;
            $insert_arr['level'] = $level;
            $insert_arr['status'] = $accounts_model::STATUS_ACTIVE;
            $insert_arr['added_by'] = $current_user['id'];

            $insert_details_arr = array();
            $insert_details_arr['title'] = $title;
            $insert_details_arr['fname'] = $fname;
            $insert_details_arr['lname'] = $lname;
            $insert_details_arr['phone'] = $phone;
            $insert_details_arr['company'] = $company;

            $insert_params_arr = array();
            $insert_params_arr['fields'] = $insert_arr;
            $insert_params_arr['{users_details}'] = $insert_details_arr;
            $insert_params_arr['{account_roles}'] = $account_roles_slugs;
            $insert_params_arr['{send_confirmation_email}'] = true;

            if( ($new_account = $accounts_model->insert( $insert_params_arr )) )
            {
                PHS_Notifications::add_success_notice( $this->_pt( 'User account created...' ) );

                $action_result = self::default_action_result();

                $action_result['redirect_to_url'] = PHS::url( array( 'p' => 'admin', 'a' => 'users_list' ), array( 'account_created' => 1 ) );

                return $action_result;
            } else
            {
                if( $accounts_model->has_error() )
                    PHS_Notifications::add_error_notice( $accounts_model->get_error_message() );
                else
                    PHS_Notifications::add_error_notice( $this->_pt( 'Error saving details to database. Please try again.' ) );
            }
        }

        $data = array(
            'nick' => $nick,
            'pass' => $pass,
            'level' => $level,
            'email' => $email,
            'title' => $title,
            'fname' => $fname,
            'lname' => $lname,
            'phone' => $phone,
            'company' => $company,

            'accounts_plugin_settings' => $accounts_plugin_settings,
            'user_levels' => $accounts_model->get_levels(),
            'min_password_length' => $accounts_plugin_settings['min_password_length'],
            'password_regexp' => $accounts_plugin_settings['password_regexp'],

            'roles_by_slug' => $roles_by_slug,

            'roles_model' => $roles_model,
            'plugins_model' => $plugins_model,
        );

        return $this->quick_render_template( 'user_add', $data );
    }
}
