<?php
namespace phs\plugins\admin\actions\tenants;

use phs\PHS;
use phs\PHS_Api;
use phs\PHS_Scope;
use phs\libraries\PHS_Roles;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\system\core\models\PHS_Model_Tenants;

class PHS_Action_Add extends PHS_Action
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
        PHS::page_settings('page_title', $this->_pt('Add Tenant'));

        if (!($current_user = PHS::user_logged_in())) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        /** @var \phs\plugins\admin\PHS_Plugin_Admin $admin_plugin */
        /** @var \phs\system\core\models\PHS_Model_Tenants $tenants_model */
        if (!($admin_plugin = PHS_Plugin_Admin::get_instance())
         || !($tenants_model = PHS_Model_Tenants::get_instance())) {
            PHS_Notifications::add_error_notice($this->_pt('Error loading required resources.'));

            return self::default_action_result();
        }

        if (!$admin_plugin->can_admin_manage_tenants()) {
            PHS_Notifications::add_error_notice($this->_pt('You don\'t have rights to access this section.'));

            return self::default_action_result();
        }

        $foobar = PHS_Params::_p('foobar', PHS_Params::T_INT);
        $name = PHS_Params::_p('name', PHS_Params::T_NOHTML, ['trim_before' => true]);
        $domain = PHS_Params::_p('domain', PHS_Params::T_NOHTML, ['trim_before' => true]);
        $directory = PHS_Params::_p('directory', PHS_Params::T_NOHTML, ['trim_before' => true]);
        $identifier = PHS_Params::_p('identifier', PHS_Params::T_NOHTML, ['trim_before' => true]);
        $is_default = PHS_Params::_p('is_default', PHS_Params::T_NUMERIC_BOOL);
        $default_theme = PHS_Params::_p('default_theme', PHS_Params::T_NOHTML, ['trim_before' => true]);
        $current_theme = PHS_Params::_p('current_theme', PHS_Params::T_NOHTML, ['trim_before' => true]);
        $cascading_themes = PHS_Params::_p('cascading_themes', PHS_Params::T_ARRAY, ['type' => PHS_Params::T_NOHTML, 'trim_before' => true]);

        $do_submit = PHS_Params::_p('do_submit');

        if (!empty($do_submit)) {
            $insert_arr = [];
            $insert_arr['added_by_uid'] = $current_user['id'];
            $insert_arr['name'] = $name;
            $insert_arr['domain'] = $domain;
            $insert_arr['directory'] = $directory;
            $insert_arr['identifier'] = $identifier;
            $insert_arr['is_default'] = $is_default;
            $insert_arr['settings'] = [
                'default_theme'    => $default_theme ?? '',
                'current_theme'    => $current_theme ?? '',
                'cascading_themes' => $cascading_themes ?? [],
            ];

            $insert_params_arr = [];
            $insert_params_arr['fields'] = $insert_arr;

            if ($tenants_model->insert($insert_params_arr)) {
                PHS_Notifications::add_success_notice($this->_pt('Tenant details saved...'));

                return action_redirect(['p' => 'admin', 'a' => 'list', 'ad' => 'tenants'], ['tenant_added' => 1]);
            }

            if ($tenants_model->has_error()) {
                PHS_Notifications::add_error_notice($tenants_model->get_error_message());
            } else {
                PHS_Notifications::add_error_notice($this->_pt('Error saving details to database. Please try again.'));
            }
        }

        if (empty($foobar)) {
            $identifier = $tenants_model->generate_identifier();
        }

        $data = [
            'name'             => $name,
            'domain'           => $domain,
            'directory'        => $directory,
            'identifier'       => $identifier,
            'is_default'       => $is_default,
            'default_theme'    => $default_theme,
            'current_theme'    => $current_theme,
            'cascading_themes' => $cascading_themes,
        ];

        return $this->quick_render_template('tenants/add', $data);
    }
}
