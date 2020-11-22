<?php

namespace phs\plugins\admin\actions;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_Params;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Roles;
use \phs\PHS_Api;

class PHS_Action_Api_key_edit extends PHS_Action
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
        PHS::page_settings( 'page_title', $this->_pt( 'Edit API Key' ) );

        if( !($current_user = PHS::user_logged_in()) )
        {
            PHS_Notifications::add_warning_notice( $this->_pt( 'You should login first...' ) );

            $action_result = self::default_action_result();

            $action_result['request_login'] = true;

            return $action_result;
        }

        if( !PHS_Roles::user_has_role_units( $current_user, PHS_Roles::ROLEU_MANAGE_API_KEYS ) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'You don\'t have rights to manage API keys.' ) );
            return self::default_action_result();
        }

        if( !($api_obj = PHS_Api::api_factory()) )
        {
            if( !PHS_Api::st_has_error() )
                $error_msg = $this->_pt( 'Error creating API instance: %s', PHS_Api::st_get_error_message() );
            else
                $error_msg = $this->_pt( 'Couldn\'t obtain an API instance.' );

            PHS_Notifications::add_error_notice( $error_msg );
            return self::default_action_result();
        }

        /** @var \phs\system\core\models\PHS_Model_Api_keys $apikeys_model */
        if( !($apikeys_model = PHS::load_model( 'api_keys' )) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load API keys model.' ) );
            return self::default_action_result();
        }

        /** @var \phs\plugins\admin\actions\PHS_Action_Users_autocomplete $users_autocomplete_action */
        if( !($users_autocomplete_action = PHS::load_action( 'users_autocomplete', 'admin' )) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Couldn\'t load users autocomplete action.' ) );
            return self::default_action_result();
        }

        $aid = PHS_Params::_gp( 'aid', PHS_Params::T_INT );
        $back_page = PHS_Params::_gp( 'back_page', PHS_Params::T_ASIS );

        if( empty( $aid )
         or !($apikey_arr = $apikeys_model->get_details( $aid )) )
        {
            PHS_Notifications::add_warning_notice( $this->_pt( 'Invalid API key...' ) );

            $action_result = self::default_action_result();

            $args = array(
                'unknown_api_key' => 1
            );

            if( empty( $back_page ) )
                $back_page = PHS::url( array( 'p' => 'admin', 'a' => 'api_keys_list' ) );
            else
                $back_page = from_safe_url( $back_page );

            $back_page = add_url_params( $back_page, $args );

            $action_result['redirect_to_url'] = $back_page;

            return $action_result;
        }

        if( PHS_Params::_g( 'changes_saved', PHS_Params::T_INT ) )
            PHS_Notifications::add_success_notice( $this->_pt( 'API key details saved.' ) );

        if( !($api_methods_arr = $api_obj->allowed_http_methods()) )
            $api_methods_arr = array();

        $foobar = PHS_Params::_p( 'foobar', PHS_Params::T_INT );
        $uid = PHS_Params::_p( 'uid', PHS_Params::T_INT );
        $autocomplete_uid = PHS_Params::_p( 'autocomplete_uid', PHS_Params::T_NOHTML );
        $title = PHS_Params::_p( 'title', PHS_Params::T_NOHTML );
        $api_key = PHS_Params::_p( 'api_key', PHS_Params::T_NOHTML );
        $api_secret = PHS_Params::_p( 'api_secret', PHS_Params::T_NOHTML );
        $allow_sw = PHS_Params::_p( 'allow_sw', PHS_Params::T_NUMERIC_BOOL );
        if( !($allowed_methods = PHS_Params::_p( 'allowed_methods', PHS_Params::T_ARRAY, array( 'type' => PHS_Params::T_NOHTML, 'trim_before' => true ) )) )
            $allowed_methods = array();
        if( !($denied_methods = PHS_Params::_p( 'denied_methods', PHS_Params::T_ARRAY, array( 'type' => PHS_Params::T_NOHTML, 'trim_before' => true ) )) )
            $denied_methods = array();

        $do_submit = PHS_Params::_p( 'do_submit' );

        if( empty( $foobar ) )
        {
            if( empty( $apikey_arr['uid'] )
             or !($account_data = $users_autocomplete_action->account_data( $apikey_arr['uid'] )) )
                $account_data = array();

            $uid = $apikey_arr['uid'];
            $autocomplete_uid = $users_autocomplete_action->format_data( false, false );
            $title = $apikey_arr['title'];
            $api_key = $apikey_arr['api_key'];
            $api_secret = $apikey_arr['api_secret'];
            $allow_sw = (!empty( $apikey_arr['allow_sw'] )?1:0);

            if( empty( $apikey_arr['allowed_methods'] ) )
                $allowed_methods = array();
            else
                $allowed_methods = self::extract_strings_from_comma_separated( $apikey_arr['allowed_methods'] );

            if( empty( $apikey_arr['denied_methods'] ) )
                $denied_methods = array();
            else
                $denied_methods = self::extract_strings_from_comma_separated( $apikey_arr['denied_methods'] );
        }

        $users_autocomplete_action->autocomplete_params( array(
            'id_id' => 'uid',
            'text_id' => 'autocomplete_uid',
            'id_name' => 'uid',
            'text_name' => 'autocomplete_uid',

            // styling
            'text_css_classes' => 'form-control',
            'text_css_style' => '',

            'id_value' => (empty( $uid )?0:$uid),
            'text_value' => (empty( $autocomplete_uid )?'':$autocomplete_uid),

            'min_text_length' => 1,
        ) );

        if( empty( $allowed_methods ) or !is_array( $allowed_methods ) )
            $allowed_methods = array();

        else
        {
            $new_allowed_methods = array();
            foreach( $allowed_methods as $method )
            {
                if( !in_array( $method, $api_methods_arr ) )
                    continue;

                $new_allowed_methods[] = $method;
            }

            $allowed_methods = $new_allowed_methods;
        }

        if( empty( $denied_methods ) or !is_array( $denied_methods ) )
            $denied_methods = array();

        else
        {
            $new_denied_methods = array();
            foreach( $denied_methods as $method )
            {
                if( !in_array( $method, $api_methods_arr ) )
                    continue;

                $new_denied_methods[] = $method;
            }

            $denied_methods = $new_denied_methods;
        }

        if( !empty( $do_submit ) )
        {
            $edit_arr = array();
            $edit_arr['added_by_uid'] = $current_user['id'];
            $edit_arr['uid'] = $uid;
            $edit_arr['title'] = $title;
            $edit_arr['api_key'] = $api_key;
            $edit_arr['api_secret'] = $api_secret;
            $edit_arr['allow_sw'] = $allow_sw;
            $edit_arr['allowed_methods'] = (!empty( $allowed_methods )?implode( ',', $allowed_methods ):'');
            $edit_arr['denied_methods'] = (!empty( $denied_methods )?implode( ',', $denied_methods ):'');

            $edit_params_arr = array();
            $edit_params_arr['fields'] = $edit_arr;

            if( ($new_role = $apikeys_model->edit( $apikey_arr, $edit_params_arr )) )
            {
                PHS_Notifications::add_success_notice( $this->_pt( 'API key details saved...' ) );

                $action_result = self::default_action_result();

                $url_params = array();
                $url_params['changes_saved'] = 1;
                $url_params['aid'] = $apikey_arr['id'];
                if( !empty( $back_page ) )
                    $url_params['back_page'] = $back_page;

                $action_result['redirect_to_url'] = PHS::url( array( 'p' => 'admin', 'a' => 'api_key_edit' ), $url_params );

                return $action_result;
            } else
            {
                if( $apikeys_model->has_error() )
                    PHS_Notifications::add_error_notice( $apikeys_model->get_error_message() );
                else
                    PHS_Notifications::add_error_notice( $this->_pt( 'Error saving details to database. Please try again.' ) );
            }
        }

        $data = array(
            'aid' => $apikey_arr['id'],
            'back_page' => $back_page,
            'uid' => $uid,
            'autocomplete_uid' => $autocomplete_uid,
            'title' => $title,
            'api_key' => $api_key,
            'api_secret' => $api_secret,
            'allow_sw' => $allow_sw,
            'allowed_methods' => $allowed_methods,
            'denied_methods' => $denied_methods,

            'api_methods_arr' => $api_methods_arr,
            'api_obj' => $api_obj,
            'apikeys_model' => $apikeys_model,
            'users_autocomplete_action' => $users_autocomplete_action,
        );

        return $this->quick_render_template( 'api_key_edit', $data );
    }
}
