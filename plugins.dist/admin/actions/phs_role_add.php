<?php
namespace phs\plugins\admin\actions;

use phs\PHS;
use phs\PHS_Scope;
use phs\PHS_Bg_jobs;
use phs\libraries\PHS_Roles;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\system\core\models\PHS_Model_Roles;
use phs\system\core\models\PHS_Model_Plugins;

class PHS_Action_Role_add extends PHS_Action
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
        PHS::page_settings('page_title', $this->_pt('Add Role'));

        if (!PHS::user_logged_in()) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        /** @var \phs\plugins\admin\PHS_Plugin_Admin $admin_plugin */
        /** @var \phs\system\core\models\PHS_Model_Roles $roles_model */
        /** @var \phs\system\core\models\PHS_Model_Plugins $plugins_model */
        if (!($admin_plugin = PHS_Plugin_Admin::get_instance())
         || !($roles_model = PHS_Model_Roles::get_instance())
         || !($plugins_model = PHS_Model_Plugins::get_instance())) {
            PHS_Notifications::add_error_notice($this->_pt('Error loading required resources.'));

            return self::default_action_result();
        }

        if (!$admin_plugin->can_admin_manage_roles()) {
            PHS_Notifications::add_error_notice($this->_pt('You don\'t have rights to access this section.'));

            return self::default_action_result();
        }

        $foobar = PHS_Params::_p('foobar', PHS_Params::T_INT);
        $name = PHS_Params::_p('name', PHS_Params::T_NOHTML);
        $slug = PHS_Params::_p('slug', PHS_Params::T_NOHTML);
        $description = PHS_Params::_p('description', PHS_Params::T_NOHTML);
        $ru_slugs = PHS_Params::_p('ru_slugs', PHS_Params::T_ARRAY,
            ['type' => PHS_Params::T_NOHTML, 'trim_before' => true]);

        $do_submit = PHS_Params::_p('do_submit');

        if (empty($ru_slugs) || !is_array($ru_slugs)) {
            $ru_slugs = [];
        }

        if (!empty($do_submit)) {
            $insert_arr = [];
            $insert_arr['name'] = $name;
            $insert_arr['description'] = $description;
            $insert_arr['slug'] = $slug;
            $insert_arr['predefined'] = 0;

            $insert_params_arr = [];
            $insert_params_arr['fields'] = $insert_arr;
            $insert_params_arr['{role_units}'] = $ru_slugs;
            $insert_params_arr['{role_units_params}'] = ['append_role_units' => false];

            if ($roles_model->insert($insert_params_arr)) {
                PHS_Notifications::add_success_notice($this->_pt('Role details saved...'));

                return action_redirect(['p' => 'admin', 'a' => 'roles_list'], ['role_added' => 1]);
            }

            if ($roles_model->has_error()) {
                PHS_Notifications::add_error_notice($roles_model->get_error_message());
            } else {
                PHS_Notifications::add_error_notice($this->_pt('Error saving details to database. Please try again.'));
            }
        }

        $data = [
            'name'        => $name,
            'description' => $description,

            'ru_slugs'           => $ru_slugs,
            'role_units_by_slug' => $roles_model->get_all_role_units_by_slug(),
            'plugins_model'      => $plugins_model,
        ];

        return $this->quick_render_template('roles/add', $data);
    }
}
