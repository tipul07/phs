<?php
namespace phs\plugins\admin\actions;

use phs\PHS;
use phs\PHS_Scope;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Notifications;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\system\core\models\PHS_Model_Roles;
use phs\system\core\models\PHS_Model_Plugins;

class PHS_Action_Role_edit extends PHS_Action
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
        PHS::page_settings('page_title', $this->_pt('Edit Role'));

        if (!PHS::user_logged_in()) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        /** @var PHS_Plugin_Admin $admin_plugin */
        /** @var PHS_Model_Roles $roles_model */
        /** @var PHS_Model_Plugins $plugins_model */
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

        $rid = PHS_Params::_gp('rid', PHS_Params::T_INT);
        $back_page = PHS_Params::_gp('back_page', PHS_Params::T_ASIS);

        if (empty($rid)
         || !($role_arr = $roles_model->get_details($rid))
         || $roles_model->is_deleted($role_arr)) {
            PHS_Notifications::add_warning_notice($this->_pt('Invalid role...'));

            if (empty($back_page)) {
                $back_page = PHS::url(['p' => 'admin', 'a' => 'roles_list']);
            } else {
                $back_page = from_safe_url($back_page);
            }

            $back_page = add_url_params($back_page, ['unknown_role' => 1]);

            return action_redirect($back_page);
        }

        if (PHS_Params::_g('changes_saved', PHS_Params::T_INT)) {
            PHS_Notifications::add_success_notice($this->_pt('Role details saved.'));
        }

        $foobar = PHS_Params::_p('foobar', PHS_Params::T_INT);
        $name = PHS_Params::_p('name', PHS_Params::T_NOHTML);
        $description = PHS_Params::_p('description', PHS_Params::T_NOHTML);
        $ru_slugs = PHS_Params::_p('ru_slugs', PHS_Params::T_ARRAY,
            ['type' => PHS_Params::T_NOHTML, 'trim_before' => true]);

        $do_submit = PHS_Params::_p('do_submit');

        if (empty($foobar)) {
            $name = $role_arr['name'];
            $description = $role_arr['description'];
            $ru_slugs = $roles_model->get_role_role_units_slugs($role_arr);
        }

        if (empty($ru_slugs) || !is_array($ru_slugs)) {
            $ru_slugs = [];
        }

        if (!empty($do_submit)) {
            $edit_arr = [];
            $edit_arr['name'] = $name;
            $edit_arr['description'] = $description;

            $edit_params_arr = [];
            $edit_params_arr['fields'] = $edit_arr;
            $edit_params_arr['{role_units}'] = $ru_slugs;
            $edit_params_arr['{role_units_params}'] = ['append_role_units' => false];

            if ($roles_model->edit($role_arr, $edit_params_arr)) {
                PHS_Notifications::add_success_notice($this->_pt('Role details saved...'));

                return action_redirect(['p' => 'admin', 'a' => 'role_edit'], ['rid' => $rid, 'changes_saved' => 1]);
            }

            if ($roles_model->has_error()) {
                PHS_Notifications::add_error_notice($roles_model->get_error_message());
            } else {
                PHS_Notifications::add_error_notice($this->_pt('Error saving details to database. Please try again.'));
            }
        }

        $data = [
            'back_page'   => $back_page,
            'rid'         => $role_arr['id'],
            'name'        => $name,
            'slug'        => $role_arr['slug'],
            'description' => $description,

            'ru_slugs'           => $ru_slugs,
            'role_units_by_slug' => $roles_model->get_all_role_units_by_slug(),
            'plugins_model'      => $plugins_model,
        ];

        return $this->quick_render_template('roles/edit', $data);
    }
}
