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

class PHS_Action_Edit extends PHS_Action
{
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_WEB, PHS_Scope::SCOPE_AJAX];
    }

    /**
     * @return array|bool
     */
    public function execute()
    {
        PHS::page_settings('page_title', $this->_pt('Edit Tenant'));

        if (!PHS::user_logged_in()) {
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

        $tid = PHS_Params::_gp('tid', PHS_Params::T_INT);
        $back_page = PHS_Params::_gp('back_page', PHS_Params::T_ASIS);

        if (empty($tid)
         || !($tenant_arr = $tenants_model->get_details($tid))) {
            PHS_Notifications::add_warning_notice($this->_pt('Invalid tenant...'));

            $args = [
                'unknown_tenant' => 1,
            ];

            if (empty($back_page)) {
                $back_page = PHS::url(['p' => 'admin', 'a' => 'list', 'ad' => 'tenants']);
            } else {
                $back_page = from_safe_url($back_page);
            }

            $back_page = add_url_params($back_page, $args);

            return action_redirect($back_page);
        }

        if (PHS_Params::_g('changes_saved', PHS_Params::T_INT)) {
            PHS_Notifications::add_success_notice($this->_pt('Tenant details saved.'));
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

        if (empty($foobar)) {
            $name = $tenant_arr['name'];
            $domain = $tenant_arr['domain'];
            $directory = $tenant_arr['directory'];
            $identifier = $tenant_arr['identifier'];
            $is_default = (!empty($tenant_arr['is_default']) ? 1 : 0);

            if (($settings_arr = $tenants_model->get_tenant_settings($tenant_arr))) {
                $default_theme = $settings_arr['default_theme'] ?? '';
                $current_theme = $settings_arr['current_theme'] ?? '';
                $cascading_themes = $settings_arr['cascading_themes'] ?? [];
            }
        }

        if (!empty($do_submit)) {
            $edit_arr = [];
            $edit_arr['name'] = $name;
            $edit_arr['domain'] = $domain;
            $edit_arr['directory'] = $directory;
            $edit_arr['identifier'] = $identifier;
            $edit_arr['is_default'] = $is_default;
            $edit_arr['settings'] = [
                'default_theme'    => $default_theme ?? '',
                'current_theme'    => $current_theme ?? '',
                'cascading_themes' => $cascading_themes ?? [],
            ];

            $edit_params_arr = [];
            $edit_params_arr['fields'] = $edit_arr;

            if ($tenants_model->edit($tenant_arr, $edit_params_arr)) {
                PHS_Notifications::add_success_notice($this->_pt('Tenant details saved...'));

                $url_params = [];
                $url_params['changes_saved'] = 1;
                $url_params['tid'] = $tenant_arr['id'];
                if (!empty($back_page)) {
                    $url_params['back_page'] = $back_page;
                }

                return action_redirect(['p' => 'admin', 'a' => 'edit', 'ad' => 'tenants'], $url_params);
            }

            if ($tenants_model->has_error()) {
                PHS_Notifications::add_error_notice($tenants_model->get_simple_error_message());
            } else {
                PHS_Notifications::add_error_notice($this->_pt('Error saving details to database. Please try again.'));
            }
        }

        $data = [
            'tid'              => $tenant_arr['id'],
            'back_page'        => $back_page,
            'name'             => $name,
            'domain'           => $domain,
            'directory'        => $directory,
            'identifier'       => $identifier,
            'is_default'       => $is_default,
            'default_theme'    => $default_theme,
            'current_theme'    => $current_theme,
            'cascading_themes' => $cascading_themes,
        ];

        return $this->quick_render_template('tenants/edit', $data);
    }
}
