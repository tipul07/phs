<?php
namespace phs\plugins\admin\actions\plugins;

use phs\PHS;
use phs\PHS_Scope;
use phs\PHS_Crypt;
use phs\libraries\PHS_Roles;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_Plugin;
use phs\libraries\PHS_Instantiable;
use phs\libraries\PHS_Notifications;
use phs\libraries\PHS_Has_db_settings;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\system\core\models\PHS_Model_Tenants;
use phs\system\core\models\PHS_Model_Plugins;

class PHS_Action_Settings extends PHS_Action
{
    public function allowed_scopes(): array
    {
        return [PHS_Scope::SCOPE_WEB];
    }

    public function execute()
    {
        PHS::page_settings('page_title', $this->_pt('Plugin Settings'));

        if (!PHS::user_logged_in()) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        /** @var \phs\plugins\admin\PHS_Plugin_Admin $admin_plugin */
        /** @var \phs\system\core\models\PHS_Model_Tenants $tenants_model */
        /** @var \phs\system\core\models\PHS_Model_Plugins $plugins_model */
        if (!($admin_plugin = PHS_Plugin_Admin::get_instance())
            || !($tenants_model = PHS_Model_Tenants::get_instance())
            || !($plugins_model = PHS_Model_Plugins::get_instance())) {
            PHS_Notifications::add_error_notice($this->_pt('Error loading required resources.'));

            return self::default_action_result();
        }

        if (!$admin_plugin->can_admin_manage_plugins()) {
            PHS_Notifications::add_error_notice($this->_pt('You don\'t have rights to access this section.'));

            return self::default_action_result();
        }

        $is_multi_tenant = PHS::is_multi_tenant();

        $foobar = PHS_Params::_p('foobar', PHS_Params::T_INT);
        $pid = PHS_Params::_gp('pid', PHS_Params::T_NOHTML) ?? '';
        $model_id = PHS_Params::_gp('model_id', PHS_Params::T_NOHTML) ?? '';
        $tenant_id = PHS_Params::_gp('tenant_id', PHS_Params::T_INT) ?? 0;
        $back_page = PHS_Params::_gp('back_page', PHS_Params::T_NOHTML);

        $do_submit = PHS_Params::_gp('do_submit', PHS_Params::T_NOHTML);
        $do_cancel = PHS_Params::_p('do_cancel');

        if (empty($back_page)) {
            $back_page = PHS::url(['p' => 'admin', 'a' => 'list', 'ad' => 'plugins']);
        } else {
            $back_page = from_safe_url($back_page);
        }

        if ($do_cancel) {
            return action_redirect($back_page);
        }

        if( !$is_multi_tenant ) {
            $tenant_id = 0;
            $tenants_arr = [];
        } elseif(!($tenants_arr = $tenants_model->get_all_tenants())) {
            $tenants_arr = [];
        }

        if( !empty( $tenant_id )
            && empty( $tenants_arr[$tenant_id] ) ) {
            return action_redirect(['p' => 'admin', 'a' => 'list', 'ad' => 'plugins'], ['unknown_tenant' => 1]);
        }

        $context_arr = [];
        $context_arr['tenant_id'] = $tenant_id;
        $context_arr['plugin'] = $pid;
        $context_arr['model_id'] = $model_id;
        $context_arr['extract_submit'] = (bool)$do_submit;

        $init_has_error = false;
        if( !($context_arr = PHS_Has_db_settings::init_settings_context($context_arr))
            || !empty($context_arr['stop_executon'])
            || !empty($context_arr['redirect_to'])
            || ($init_has_error = self::arr_has_error($context_arr['errors'] ?? []))) {

            if( $init_has_error ) {
                if (!($error_msg = self::arr_get_error_message($context_arr['errors'] ?? []))) {
                    $error_msg = self::_t('Error initializing plugin settings page.');
                }
                PHS_Notifications::add_error_notice($error_msg);
            }

            if( !empty( $context_arr['redirect_to'] ) ) {
                return action_redirect($context_arr['redirect_to']);
            }

            if(!empty( $context_arr['stop_executon'] ) ) {
                return self::default_action_result();
            }
        }

        if($tenant_id !== $context_arr['tenant_id']) {
            $tenant_id = $context_arr['tenant_id'];
        }

        $context_arr = PHS_Has_db_settings::extract_settings_and_form_data_from_context( $context_arr );

        if (PHS_Params::_g('changes_saved', PHS_Params::T_INT)) {
            PHS_Notifications::add_success_notice($this->_pt('Settings saved in database.'));
        }

        if (!empty($do_submit)) {
            $context_arr = PHS_Has_db_settings::get_custom_save_fields_settings_for_save($context_arr);

            if( !empty( $context_arr['errors_arr'] ) ) {
                foreach( $context_arr['errors_arr'] as $error_msg ) {
                    PHS_Notifications::add_error_notice($error_msg);
                }
            }

            if( !empty( $context_arr['warnings_arr'] ) ) {
                foreach( $context_arr['warnings_arr'] as $warning_msg ) {
                    PHS_Notifications::add_warning_notice($warning_msg);
                }
            }

            /** @var PHS_Has_db_settings $instance_obj */
            if( !($instance_obj = $context_arr['model_instance'] ?? $context_arr['plugin_instance'] ?? null) ) {
                PHS_Notifications::add_error_notice($this->_pt('Cannot save settings. Unknown instance.'));
            }

            elseif (!PHS_Notifications::have_notifications_errors()) {
                if (null !== $instance_obj->save_db_settings($context_arr['submit_settings'], $tenant_id)) {
                    $args = [
                        'changes_saved'   => 1,
                        'pid'             => $pid,
                        'model_id' => $model_id,
                    ];

                    if($is_multi_tenant) {
                        $args['tenant_id'] = $tenant_id;
                    }

                    $args['back_page'] = $back_page;

                    return action_redirect(['p' => 'admin', 'a' => 'settings', 'ad' => 'plugins'], $args);
                }

                if ($instance_obj->has_error()) {
                    PHS_Notifications::add_error_notice($instance_obj->get_error_message());
                } else {
                    PHS_Notifications::add_error_notice($this->_pt('Error saving settings in database. Please try again.'));
                }
            }
        }

        $data = [
            'back_page' => $back_page,
            'tenant_id' => $tenant_id,
            'pid' => $pid,
            'model_id' => $model_id,
            'tenants_arr' => $tenants_arr,
            'context_arr' => $context_arr,

            'tenants_model' => $tenants_model,
            'plugins_model' => $plugins_model,
            'admin_plugin' => $admin_plugin,
        ];

        return $this->quick_render_template('plugins/settings', $data);
    }
}
