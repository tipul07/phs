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

class PHS_Action_Edit extends PHS_Action
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
        PHS::page_settings('page_title', $this->_pt('Edit API Key'));

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

        $aid = PHS_Params::_gp('aid', PHS_Params::T_INT);
        $back_page = PHS_Params::_gp('back_page', PHS_Params::T_ASIS);

        if (!$aid
            || !($apikey_arr = $this->_apikeys_model->get_details($aid))) {
            PHS_Notifications::add_warning_notice($this->_pt('Invalid API key...'));

            $back_page = !$back_page
                ? PHS::url(['p' => 'admin', 'a' => 'list', 'ad' => 'apikeys'])
                : from_safe_url($back_page);

            return action_redirect(add_url_params($back_page, ['unknown_api_key' => 1, ]));
        }

        if (PHS_Params::_g('changes_saved', PHS_Params::T_INT)) {
            PHS_Notifications::add_success_notice($this->_pt('API key details saved.'));
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

        if (empty($foobar)) {
            if (!empty($apikey_arr['uid'])) {
                $this->_users_autocomplete_action->set_account_data($apikey_arr['uid']);
            }

            $uid = (int)$apikey_arr['uid'];
            $tenant_id = (int)$apikey_arr['tenant_id'];
            $autocomplete_uid = $this->_users_autocomplete_action->format_data(false);
            $title = $apikey_arr['title'];
            $api_key = $apikey_arr['api_key'];
            $api_secret = $apikey_arr['api_secret'];
            $allow_sw = (!empty($apikey_arr['allow_sw']) ? 1 : 0);
            $allow_graphql = (!empty($apikey_arr['allow_graphql']) ? 1 : 0);

            if (empty($apikey_arr['allowed_methods'])) {
                $allowed_methods = [];
            } else {
                $allowed_methods = self::extract_strings_from_comma_separated($apikey_arr['allowed_methods']);
            }

            if (empty($apikey_arr['denied_methods'])) {
                $denied_methods = [];
            } else {
                $denied_methods = self::extract_strings_from_comma_separated($apikey_arr['denied_methods']);
            }
        }

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

        if (!empty($do_submit)) {
            $edit_arr = [];
            $edit_arr['added_by_uid'] = $current_user['id'];
            $edit_arr['uid'] = $uid;
            if ($is_multi_tenant) {
                $edit_arr['tenant_id'] = $tenant_id;
            }
            $edit_arr['title'] = $title;
            $edit_arr['api_key'] = $api_key;
            $edit_arr['api_secret'] = $api_secret;
            $edit_arr['allow_sw'] = $allow_sw;
            $edit_arr['allow_graphql'] = $allow_graphql;
            $edit_arr['allowed_methods'] = (!empty($allowed_methods) ? implode(',', $allowed_methods) : null);
            $edit_arr['denied_methods'] = (!empty($denied_methods) ? implode(',', $denied_methods) : null);

            if ($this->_apikeys_model->edit($apikey_arr, ['fields' => $edit_arr])) {
                PHS_Notifications::add_success_notice($this->_pt('API key details saved...'));

                $url_params = [];
                $url_params['changes_saved'] = 1;
                $url_params['aid'] = $apikey_arr['id'];
                if (!empty($back_page)) {
                    $url_params['back_page'] = $back_page;
                }

                return action_redirect(['p' => 'admin', 'a' => 'edit', 'ad' => 'apikeys'], $url_params);
            }

            PHS_Notifications::add_error_notice(
                $this->_apikeys_model->get_simple_error_message(
                    $this->_pt('Error saving details to database. Please try again.')));
        }

        $data = [
            'aid'              => $apikey_arr['id'],
            'back_page'        => $back_page,
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

        return $this->quick_render_template('apikeys/edit', $data);
    }
}
