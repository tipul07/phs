<?php

namespace phs\plugins\admin\actions\users;

use phs\PHS;
use phs\PHS_Scope;
use phs\libraries\PHS_Roles;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_File_upload;
use phs\libraries\PHS_Notifications;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\system\core\models\PHS_Model_Roles;
use phs\plugins\accounts\PHS_Plugin_Accounts;
use phs\system\core\models\PHS_Model_Plugins;
use phs\plugins\accounts\models\PHS_Model_Accounts;

class PHS_Action_Import extends PHS_Action
{
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_WEB];
    }

    public function execute()
    {
        PHS::page_settings('page_title', $this->_pt('Import Users'));

        if (!($current_user = PHS::user_logged_in())) {
            PHS_Notifications::add_warning_notice($this->_pt('You should login first...'));

            return action_request_login();
        }

        /** @var PHS_Plugin_Accounts $accounts_plugin */
        /** @var PHS_Plugin_Admin $admin_plugin */
        /** @var PHS_Model_Accounts $accounts_model */
        /** @var PHS_Model_Roles $roles_model */
        /** @var PHS_Model_Plugins $plugins_model */
        if (!($accounts_plugin = PHS_Plugin_Accounts::get_instance())
         || !($admin_plugin = PHS_Plugin_Admin::get_instance())
         || !($accounts_plugin_settings = $accounts_plugin->get_plugin_settings())
         || !($accounts_model = PHS_Model_Accounts::get_instance())
         || !($roles_model = PHS_Model_Roles::get_instance())
         || !($plugins_model = PHS_Model_Plugins::get_instance())) {
            PHS_Notifications::add_error_notice($this->_pt('Error loading required resources.'));

            return self::default_action_result();
        }

        if (!$admin_plugin->can_admin_import_accounts()) {
            PHS_Notifications::add_error_notice($this->_pt('You don\'t have rights to access this section.'));

            return self::default_action_result();
        }

        if (!($roles_by_slug = $roles_model->get_all_roles_by_slug())) {
            $roles_by_slug = [];
        }

        $foobar = PHS_Params::_p('foobar', PHS_Params::T_INT);
        if (!($insert_not_found = PHS_Params::_pg('insert_not_found', PHS_Params::T_INT))) {
            $insert_not_found = 0;
        }
        if (!($override_level = PHS_Params::_pg('override_level', PHS_Params::T_INT))) {
            $override_level = 0;
        }
        if (!($reset_roles = PHS_Params::_pg('reset_roles', PHS_Params::T_INT))) {
            $reset_roles = 0;
        }
        if (!($update_roles = PHS_Params::_pg('update_roles', PHS_Params::T_INT))) {
            $update_roles = 0;
        }
        if (!($update_details = PHS_Params::_pg('update_details', PHS_Params::T_INT))) {
            $update_details = 0;
        }
        if (!($import_level = PHS_Params::_pg('import_level', PHS_Params::T_INT))) {
            $import_level = 0;
        }
        $import_file = PHS_Params::_f('import_file');

        $do_submit = PHS_Params::_p('do_submit');

        if (!($import_finished = PHS_Params::_pg('import_finished', PHS_Params::T_INT))) {
            $import_finished = 0;
        }

        if ($import_finished) {
            if (!($total_accounts = PHS_Params::_pg('total_accounts', PHS_Params::T_INT))) {
                $total_accounts = 0;
            }
            if (!($processed_accounts = PHS_Params::_pg('processed_accounts', PHS_Params::T_INT))) {
                $processed_accounts = 0;
            }
            if (!($not_found = PHS_Params::_pg('not_found', PHS_Params::T_INT))) {
                $not_found = 0;
            }
            if (!($inserts = PHS_Params::_pg('inserts', PHS_Params::T_INT))) {
                $inserts = 0;
            }
            if (!($edits = PHS_Params::_pg('edits', PHS_Params::T_INT))) {
                $edits = 0;
            }
            if (!($errors = PHS_Params::_pg('errors', PHS_Params::T_INT))) {
                $errors = 0;
            }

            PHS_Notifications::add_success_notice(
                $this->_pt('Import finished. %s accounts in import file, %s accounts processed, %s new accounts (%s inserted in database), %s updated accounts, %s errors.',
                    $total_accounts, $processed_accounts, $not_found, $inserts, $edits, $errors).' '
                .$this->_pt('For more details check import log file.'));
        }

        if (!empty($do_submit)) {
            $uploader_obj = new PHS_File_upload();
            if ($uploader_obj->has_error()) {
                PHS_Notifications::add_error_notice($this->_pt('Error initiating upload procedure.'));
            } else {
                $source_arr = [];
                $source_arr['file_max_size'] = 0; // as big as possible :)
                $source_arr['allowed_extentions'] = ['json'];
                $source_arr['upload_file'] = $import_file;

                $import_dir = $accounts_plugin->get_accounts_import_dir();

                // OLD files cleanup (if any)...
                if (($old_files = @glob($import_dir.'_ai'.$current_user['id'].'_*.*'))) {
                    foreach ($old_files as $del_file) {
                        @unlink($del_file);
                    }
                }

                $upload_file_name = '_ai'.$current_user['id'].'_'.md5(microtime(true));
                $tmp_file = $accounts_plugin->get_accounts_import_dir().$upload_file_name;

                $uploader_obj->set_source($source_arr);
                $uploader_obj->set_destination_file($tmp_file);

                if (!($download_result = $uploader_obj->copy(['overwrite_destination' => true]))
                 || empty($download_result['fullname'])) {
                    if ($uploader_obj->has_error()) {
                        $error_msg = $this->_pt('Unknown error. Please try again.');
                    } else {
                        $error_msg = $uploader_obj->get_simple_error_message();
                    }

                    PHS_Notifications::add_error_notice($this->_pt('Error uploading accounts import file: %s', $error_msg));
                } else {
                    $uploaded_file = $download_result['fullname'];

                    $import_params = [];
                    $import_params['insert_not_found'] = (bool)$insert_not_found;
                    $import_params['override_level'] = (bool)$override_level;
                    $import_params['reset_roles'] = (bool)$reset_roles;
                    $import_params['update_roles'] = (bool)$update_roles;
                    $import_params['update_details'] = (bool)$update_details;
                    $import_params['import_level'] = $import_level;

                    if (!($import_results = $accounts_plugin->import_accounts_from_json_file($uploaded_file, $import_params))) {
                        @unlink($uploaded_file);
                        if (!$accounts_plugin->has_error()) {
                            $error_msg = $this->_pt('Unknown error. Please try again.');
                        } else {
                            $error_msg = $accounts_plugin->get_simple_error_message();
                        }

                        PHS_Notifications::add_error_notice($this->_pt('Error processing import: %s', $error_msg));
                    } else {
                        @unlink($uploaded_file);

                        PHS_Notifications::add_success_notice($this->_pt('Import finished...'));

                        $url_args = [];
                        $url_args['import_finished'] = 1;
                        $url_args['total_accounts'] = (!empty($import_results['total_accounts']) ? $import_results['total_accounts'] : 0);
                        $url_args['processed_accounts'] = (!empty($import_results['processed_accounts']) ? $import_results['processed_accounts'] : 0);
                        $url_args['not_found'] = (!empty($import_results['not_found']) ? $import_results['not_found'] : 0);
                        $url_args['inserts'] = (!empty($import_results['inserts']) ? $import_results['inserts'] : 0);
                        $url_args['edits'] = (!empty($import_results['edits']) ? $import_results['edits'] : 0);
                        $url_args['errors'] = (!empty($import_results['errors']) ? $import_results['errors'] : 0);
                        $url_args['insert_not_found'] = ($insert_not_found ? 1 : 0);
                        $url_args['override_level'] = ($override_level ? 1 : 0);
                        $url_args['update_roles'] = ($update_roles ? 1 : 0);
                        $url_args['reset_roles'] = ($reset_roles ? 1 : 0);
                        $url_args['update_details'] = ($update_details ? 1 : 0);
                        $url_args['import_level'] = $import_level;

                        return action_redirect(['p' => 'admin', 'a' => 'import', 'ad' => 'users'], $url_args);
                    }
                }
            }
        }

        if (empty($foobar) && empty($import_finished)) {
            $insert_not_found = true;
            $override_level = false;
            $update_roles = false;
            $reset_roles = false;
            $update_details = false;
        }

        $data = [
            'foobar' => $foobar,

            'import_file'      => $import_file,
            'insert_not_found' => $insert_not_found,
            'override_level'   => $override_level,
            'update_roles'     => $update_roles,
            'reset_roles'      => $reset_roles,
            'update_details'   => $update_details,
            'import_level'     => $import_level,

            'accounts_plugin_settings' => $accounts_plugin_settings,
            'user_levels'              => $accounts_model->get_levels(),

            'roles_by_slug' => $roles_by_slug,

            'roles_model'   => $roles_model,
            'plugins_model' => $plugins_model,
        ];

        return $this->quick_render_template('users/import', $data);
    }
}
