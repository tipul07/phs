<?php
namespace phs\plugins\admin\actions\users;

use phs\PHS;
use phs\PHS_Scope;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Params;
use phs\libraries\PHS_File_upload;
use phs\libraries\PHS_Notifications;
use phs\plugins\admin\PHS_Plugin_Admin;
use phs\system\core\models\PHS_Model_Roles;
use phs\plugins\accounts\PHS_Plugin_Accounts;
use phs\system\core\attributes\PHS_Dependency;
use phs\plugins\accounts\models\PHS_Model_Accounts;

class PHS_Action_Import extends PHS_Action
{
    #[PHS_Dependency]
    private ?PHS_Plugin_Accounts $_accounts_plugin = null;

    #[PHS_Dependency]
    private ?PHS_Plugin_Admin $_admin_plugin = null;

    #[PHS_Dependency]
    private ?PHS_Model_Accounts $_accounts_model = null;

    #[PHS_Dependency]
    private ?PHS_Model_Roles $_roles_model = null;

    public function allowed_scopes(): array
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

        if (!$this->_admin_plugin->can_admin_import_accounts()) {
            PHS_Notifications::add_error_notice($this->_pt('You don\'t have rights to access this section.'));

            return self::default_action_result();
        }

        $roles_by_slug = $this->_roles_model->get_all_roles_by_slug();

        $foobar = PHS_Params::_p('foobar', PHS_Params::T_INT);
        $insert_not_found = PHS_Params::_pg('insert_not_found', PHS_Params::T_INT) ?: 0;
        $override_level = PHS_Params::_pg('override_level', PHS_Params::T_INT) ?: 0;
        $reset_roles = PHS_Params::_pg('reset_roles', PHS_Params::T_INT) ?: 0;
        $update_roles = PHS_Params::_pg('update_roles', PHS_Params::T_INT) ?: 0;
        $update_details = PHS_Params::_pg('update_details', PHS_Params::T_INT) ?: 0;
        $import_level = PHS_Params::_pg('import_level', PHS_Params::T_INT) ?: 0;
        $import_file = PHS_Params::_f('import_file');

        $do_submit = PHS_Params::_p('do_submit');

        $import_finished = PHS_Params::_pg('import_finished', PHS_Params::T_INT) ?: 0;

        if ($import_finished) {
            $total_accounts = PHS_Params::_pg('total_accounts', PHS_Params::T_INT) ?: 0;
            $processed_accounts = PHS_Params::_pg('processed_accounts', PHS_Params::T_INT) ?: 0;
            $not_found = PHS_Params::_pg('not_found', PHS_Params::T_INT) ?: 0;
            $inserts = PHS_Params::_pg('inserts', PHS_Params::T_INT) ?: 0;
            $edits = PHS_Params::_pg('edits', PHS_Params::T_INT) ?: 0;
            $errors = PHS_Params::_pg('errors', PHS_Params::T_INT) ?: 0;

            PHS_Notifications::add_success_notice(
                $this->_pt('Import finished. %s accounts in import file, %s accounts processed, %s new accounts (%s inserted in database), %s updated accounts, %s errors.',
                    $total_accounts, $processed_accounts, $not_found, $inserts, $edits, $errors).' '
                .$this->_pt('For more details check import log file.'));
        }

        if ($do_submit) {
            $uploader_obj = new PHS_File_upload();
            if ($uploader_obj->has_error()) {
                PHS_Notifications::add_error_notice($this->_pt('Error initiating upload procedure.'));
            } else {
                $source_arr = [];
                $source_arr['file_max_size'] = 0; // as big as possible :)
                $source_arr['allowed_extentions'] = ['json'];
                $source_arr['upload_file'] = $import_file;

                $import_dir = $this->_accounts_plugin->get_accounts_import_dir();

                // OLD files cleanup (if any)...
                if (($old_files = @glob($import_dir.'_ai'.$current_user['id'].'_*.*'))) {
                    foreach ($old_files as $del_file) {
                        @unlink($del_file);
                    }
                }

                $upload_file_name = '_ai'.$current_user['id'].'_'.md5(microtime(true));
                $tmp_file = $this->_accounts_plugin->get_accounts_import_dir().$upload_file_name;

                $uploader_obj->set_source($source_arr);
                $uploader_obj->set_destination_file($tmp_file);

                if (!($download_result = $uploader_obj->copy(['overwrite_destination' => true]))
                    || empty($download_result['fullname'])) {
                    PHS_Notifications::add_error_notice(
                        $this->_pt('Error uploading accounts import file: %s',
                            $uploader_obj->get_simple_error_message($this->_pt('Unknown error. Please try again.'))),
                    );
                } else {
                    $uploaded_file = $download_result['fullname'];

                    $import_params = [];
                    $import_params['insert_not_found'] = (bool)$insert_not_found;
                    $import_params['override_level'] = (bool)$override_level;
                    $import_params['reset_roles'] = (bool)$reset_roles;
                    $import_params['update_roles'] = (bool)$update_roles;
                    $import_params['update_details'] = (bool)$update_details;
                    $import_params['import_level'] = $import_level;

                    if (($import_results = $this->_accounts_plugin->import_accounts_from_json_file($uploaded_file, $import_params))) {
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

                        return action_redirect(self::class, $url_args);
                    }

                    @unlink($uploaded_file);

                    PHS_Notifications::add_error_notice(
                        $this->_pt('Error processing import: %s',
                            $this->_accounts_plugin->get_simple_error_message($this->_pt('Unknown error. Please try again.'))),
                    );
                }
            }
        }

        if (!$foobar && !$import_finished) {
            $insert_not_found = true;
            $override_level = false;
            $update_roles = false;
            $reset_roles = false;
            $update_details = false;
        }

        return $this->quick_render_template('users/import', [
            'foobar' => $foobar,

            'import_file'      => $import_file,
            'insert_not_found' => $insert_not_found,
            'override_level'   => $override_level,
            'update_roles'     => $update_roles,
            'reset_roles'      => $reset_roles,
            'update_details'   => $update_details,
            'import_level'     => $import_level,

            'user_levels' => $this->_accounts_model->get_levels(),

            'roles_by_slug' => $roles_by_slug,
        ]);
    }
}
