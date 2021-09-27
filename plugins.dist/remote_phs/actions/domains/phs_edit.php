<?php

namespace phs\plugins\remote_phs\actions\domains;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\libraries\PHS_Action;
use \phs\libraries\PHS_params;
use \phs\libraries\PHS_Notifications;
use \phs\libraries\PHS_Roles;
use \phs\plugins\s2p_libraries\libraries\S2P_Countries;

class PHS_Action_Edit extends PHS_Action
{
    /**
     * Returns an array of scopes in which action is allowed to run
     *
     * @return array If empty array, action is allowed in all scopes...
     */
    public function allowed_scopes()
    {
        return [ PHS_Scope::SCOPE_WEB ];
    }

    /**
     * @return array|bool
     */
    public function execute()
    {
        PHS::page_settings( 'page_title', $this->_pt( 'Edit Remote PHS Domain' ) );

        if( !($current_user = PHS::user_logged_in()) )
        {
            PHS_Notifications::add_warning_notice( $this->_pt( 'You should login first...' ) );

            $action_result = self::default_action_result();

            $action_result['request_login'] = true;

            return $action_result;
        }

        /** @var \phs\plugins\remote_phs\PHS_Plugin_Remote_phs $remote_plugin */
        /** @var \phs\system\core\models\PHS_Model_Api_keys $apikeys_model */
        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        /** @var \phs\plugins\remote_phs\models\PHS_Model_Phs_Remote_domains $domains_model */
        if( !($remote_plugin = PHS::load_plugin( 'remote_phs' ))
         || !($apikeys_model = PHS::load_model( 'api_keys' ))
         || !($accounts_model = PHS::load_model( 'accounts', 'accounts' ))
         || !($domains_model = PHS::load_model( 'phs_remote_domains', 'remote_phs' )) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'Error loading required resources.' ) );
            return self::default_action_result();
        }

        if( !$remote_plugin->can_admin_manage_domains( $current_user ) )
        {
            PHS_Notifications::add_error_notice( $this->_pt( 'You don\'t have rights to manage remote PHS domains.' ) );
            return self::default_action_result();
        }

        $did = PHS_params::_gp( 'did', PHS_params::T_INT );
        $back_page = PHS_params::_gp( 'back_page', PHS_params::T_ASIS );

        if( empty( $did )
         || !($domain_arr = $domains_model->get_details( $did ))
         || $domains_model->is_deleted( $domain_arr )
         || !$domains_model->can_user_edit( $domain_arr, $current_user ) )
        {
            PHS_Notifications::add_warning_notice( $this->_pt( 'Invalid remote PHS domain...' ) );

            $action_result = self::default_action_result();

            $args = [
                'unknown_domain' => 1
            ];

            if( empty( $back_page ) )
                $back_page = PHS::url( [ 'p' => 'remote_phs', 'c' => 'admin', 'a' => 'list', 'ad' => 'domains' ] );
            else
                $back_page = from_safe_url( $back_page );

            $back_page = add_url_params( $back_page, $args );

            $action_result['redirect_to_url'] = $back_page;

            return $action_result;
        }

        if( PHS_params::_g( 'changes_saved', PHS_params::T_INT ) )
            PHS_Notifications::add_success_notice( $this->_pt( 'Remote domain details saved in database.' ) );

        if( !($apikeys_arr = $apikeys_model->get_all_api_keys_as_key_val()) )
            $apikeys_arr = [];

        $foobar = PHS_params::_p( 'foobar', PHS_params::T_INT );
        $title = PHS_params::_pg( 'title', PHS_params::T_NOHTML );
        $handle = PHS_params::_pg( 'handle', PHS_params::T_NOHTML );
        $domain = PHS_params::_pg( 'domain', PHS_params::T_NOHTML );
        $apikey_id = PHS_params::_p( 'apikey_id', PHS_params::T_INT );
        $out_apikey = PHS_params::_p( 'out_apikey', PHS_params::T_NOHTML );
        $out_apisecret = PHS_params::_p( 'out_apisecret', PHS_params::T_NOHTML );
        $ips_whihtelist = PHS_params::_p( 'ips_whihtelist', PHS_params::T_NOHTML );

        if( PHS_params::_p( 'allow_incoming', PHS_params::T_INT ) )
            $allow_incoming = true;
        else
            $allow_incoming = false;

        if( PHS_params::_p( 'log_requests', PHS_params::T_INT ) )
            $log_requests = true;
        else
            $log_requests = false;

        $do_submit = PHS_params::_p( 'do_submit' );

        if( empty( $foobar ) )
        {
            $title = $domain_arr['title'];
            $handle = $domain_arr['handle'];
            $domain = $domain_arr['domain'];
            $apikey_id = $domain_arr['apikey_id'];
            $out_apikey = $domain_arr['out_apikey'];
            $out_apisecret = $domain_arr['out_apisecret'];
            $ips_whihtelist = $domain_arr['ips_whihtelist'];
            $allow_incoming = $domain_arr['allow_incoming'];
            $log_requests = $domain_arr['log_requests'];
        }

        if( !empty( $do_submit ) )
        {
            $edit_arr = [];
            $edit_arr['title'] = $title;
            if( (int)$domain_arr['source'] === $domains_model::SOURCE_MANUALLY )
                $edit_arr['handle'] = $handle;
            $edit_arr['domain'] = $domain;
            $edit_arr['apikey_id'] = $apikey_id;
            $edit_arr['out_apikey'] = $out_apikey;
            $edit_arr['out_apisecret'] = $out_apisecret;
            $edit_arr['ips_whihtelist'] = $ips_whihtelist;
            $edit_arr['allow_incoming'] = $allow_incoming;
            $edit_arr['log_requests'] = $log_requests;

            $edit_params_arr = [];
            $edit_params_arr['fields'] = $edit_arr;

            if( ($new_domain = $payment_categories_model->edit( $domain_arr, $edit_params_arr )) )
            {
                PHS_Notifications::add_success_notice( $this->_pt( 'Remote PHS domain details saved in database.' ) );

                $action_result = self::default_action_result();

                $action_result['redirect_to_url'] = PHS::url( [ 'p' => 'remote_phs', 'c' => 'admin', 'a' => 'edit', 'ad' => 'domains' ],
                                                              [ 'did' => $domain_arr['id'], 'changes_saved' => 1 ] );

                return $action_result;
            }

            if( $payment_categories_model->has_error() )
                PHS_Notifications::add_error_notice( $payment_categories_model->get_error_message() );
            else
                PHS_Notifications::add_error_notice( $this->_pt( 'Error saving details to database. Please try again.' ) );
        }

        $data = [
            'did' => $domain_arr['id'],
            'back_page' => $back_page,
            'domain_arr' => $domain_arr,

            'title' => $title,
            'handle' => $handle,
            'domain' => $domain,
            'apikey_id' => $apikey_id,
            'out_apikey' => $out_apikey,
            'out_apisecret' => $out_apisecret,
            'ips_whihtelist' => $ips_whihtelist,
            'allow_incoming' => $allow_incoming,
            'log_requests' => $log_requests,

            'apikeys_arr' => $apikeys_arr,

            'accounts_model' => $accounts_model,
            'apikeys_model' => $apikeys_model,
            'domains_model' => $domains_model,
        ];

        return $this->quick_render_template( 'domains/edit', $data );
    }
}
