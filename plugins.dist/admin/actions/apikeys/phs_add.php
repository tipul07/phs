<?php
namespace phs\plugins\admin\actions\apikeys;

use phs\PHS;
use phs\PHS_Api;
use phs\PHS_Scope;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\system\core\models\PHS_Model_Tenants;
use phs\system\core\attributes\PHS_Dependency;
use phs\system\core\models\PHS_Model_Api_keys;
use phs\plugins\admin\actions\PHS_Action_Users_autocomplete;

class PHS_Action_Add extends PHS_Action
{
    #[PHS_Dependency]
    private ?PHS_Plugin_Admin $_admin_plugin = null;

    #[PHS_Dependency]
    private ?PHS_Model_Api_keys $_apikeys_model = null;

    #[PHS_Dependency]
    private ?PHS_Action_Users_autocomplete $_users_autocomplete_action = null;

    #[PHS_Dependency]
    private ?PHS_Model_Tenants $_tenants_model = null;

    /**
     * @inheritdoc
     */
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX];
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        PHS::page_settings('page_title', $this->_pt('Add API Key'));

        if (!($current_user = PHS::user_logged_in())) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        $is_multi_tenant = PHS::is_multi_tenant();

        if (!$this->_admin_plugin->can_admin_manage_api_keys()) {
            PHS_Notifications::add_error_notice($this->_pt('You don\'t have rights to access this section.'));

            return self::default_action_result();
        }

        if (!($api_obj = PHS_Api::api_factory())) {
            if (PHS_Api::st_has_error()) {
                $error_msg = $this->_pt('Error creating API instance: %s', PHS_Api::st_get_simple_error_message());
            } else {
                $error_msg = $this->_pt('Couldn\'t obtain an API instance.');
            }

            PHS_Notifications::add_error_notice($error_msg);

            return self::default_action_result();
        }

        $api_methods_arr = $api_obj->allowed_http_methods() ?: [];

        $all_tenants_arr = [];
        if ($is_multi_tenant
            && !($all_tenants_arr = $this->_tenants_model->get_all_tenants())) {
            $all_tenants_arr = [];
        }

        $foobar = PHS_Params::_p('foobar', PHS_Params::T_INT);
        $uid = PHS_Params::_p('uid', PHS_Params::T_INT);
        $autocomplete_uid = PHS_Params::_p('autocomplete_uid', PHS_Params::T_NOHTML);
        $tenant_id = PHS_Params::_p('tenant_id', PHS_Params::T_INT) ?? 0;
        $title = PHS_Params::_p('title', PHS_Params::T_NOHTML);
        $api_key = PHS_Params::_p('api_key', PHS_Params::T_NOHTML);
        $api_secret = PHS_Params::_p('api_secret', PHS_Params::T_NOHTML);
        $allow_sw = PHS_Params::_p('allow_sw', PHS_Params::T_NUMERIC_BOOL);
        $allow_graphql = PHS_Params::_p('allow_graphql', PHS_Params::T_NUMERIC_BOOL);
        $allowed_methods = PHS_Params::_p('allowed_methods', PHS_Params::T_ARRAY,
            ['type' => PHS_Params::T_NOHTML, 'trim_before' => true]) ?: [];
        $denied_methods = PHS_Params::_p('denied_methods', PHS_Params::T_ARRAY,
            ['type' => PHS_Params::T_NOHTML, 'trim_before' => true]) ?: [];

        $do_submit = PHS_Params::_p('do_submit');

        $this->_users_autocomplete_action->autocomplete_params([
            'id_id'     => 'uid',
            'text_id'   => 'autocomplete_uid',
            'id_name'   => 'uid',
            'text_name' => 'autocomplete_uid',

            // styling
            'text_css_classes' => 'form-control',
            'text_css_style'   => 'display: inline-block;',

            'id_value'   => $uid ?: 0,
            'text_value' => $autocomplete_uid ?: '',

            'min_text_length' => 1,
        ]);

        if (empty($allowed_methods) || !is_array($allowed_methods)) {
            $allowed_methods = [];
        } else {
            $new_allowed_methods = [];
            foreach ($allowed_methods as $method) {
                if (!in_array($method, $api_methods_arr, true)) {
                    continue;
                }

                $new_allowed_methods[] = $method;
            }

            $allowed_methods = $new_allowed_methods;
        }

        if (empty($denied_methods) || !is_array($denied_methods)) {
            $denied_methods = [];
        } else {
            $new_denied_methods = [];
            foreach ($denied_methods as $method) {
                if (!in_array($method, $api_methods_arr, true)) {
                    continue;
                }

                $new_denied_methods[] = $method;
            }

            $denied_methods = $new_denied_methods;
        }

        if ($do_submit) {
            $insert_arr = [];
            $insert_arr['added_by_uid'] = $current_user['id'];
            $insert_arr['uid'] = $uid;
            if ($is_multi_tenant) {
                $insert_arr['tenant_id'] = $tenant_id;
            }
            $insert_arr['title'] = $title;
            $insert_arr['api_key'] = $api_key;
            $insert_arr['api_secret'] = $api_secret;
            $insert_arr['allow_sw'] = $allow_sw;
            $insert_arr['allow_graphql'] = $allow_graphql;
            $insert_arr['allowed_methods'] = (!empty($allowed_methods) ? implode(',', $allowed_methods) : null);
            $insert_arr['denied_methods'] = (!empty($denied_methods) ? implode(',', $denied_methods) : null);

            $insert_params_arr = [];
            $insert_params_arr['fields'] = $insert_arr;

            if ($this->_apikeys_model->insert($insert_params_arr)) {
                PHS_Notifications::add_success_notice($this->_pt('API key details saved...'));

                return action_redirect(['p' => 'admin', 'a' => 'list', 'ad' => 'apikeys'], ['api_key_added' => 1]);
            }

            PHS_Notifications::add_error_notice(
                $this->_apikeys_model->get_simple_error_message(
                    $this->_pt('Error saving details to database. Please try again.'))
            );
        }

        $data = [
            'uid'              => $uid,
            'tenant_id'        => $tenant_id,
            'autocomplete_uid' => $autocomplete_uid,
            'title'            => $title,
            'api_key'          => $api_key,
            'api_secret'       => $api_secret,
            'allow_sw'         => $allow_sw,
            'allow_graphql'    => $allow_graphql,
            'allowed_methods'  => $allowed_methods,
            'denied_methods'   => $denied_methods,

            'api_methods_arr' => $api_methods_arr,
            'all_tenants_arr' => $all_tenants_arr,

            'api_obj'                   => $api_obj,
            'apikeys_model'             => $this->_apikeys_model,
            'users_autocomplete_action' => $this->_users_autocomplete_action,
        ];

        return $this->quick_render_template('apikeys/add', $data);
    }
}
