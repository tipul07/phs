<?php

namespace phs\plugins\backup;

use \phs\PHS;
use \phs\libraries\PHS_Plugin;
use \phs\libraries\PHS_Hooks;
use \phs\libraries\PHS_params;
use \phs\libraries\PHS_Roles;

class PHS_Plugin_Backup extends PHS_Plugin
{
    const DIRNAME_IN_UPLOADS = '_backups';

    const AGENT_JOB_HANDLE = 'backup_index_bg_run_backups_ag',
          AGENT_BACKUPS_RUN_SECS = 3600;

    const LOG_CHANNEL = 'backups.log';

    const ROLE_BACKUP_MANAGER = 'phs_backup_manager', ROLE_BACKUP_OPERATOR = 'phs_backup_operator';

    const ROLEU_MANAGE_RULES = 'phs_backups_manage_rules', ROLEU_LIST_RULES = 'phs_backups_list_rules',
          ROLEU_LIST_BACKUPS = 'phs_backups_list_backups', ROLEU_DELETE_BACKUPS = 'phs_backups_delete_backups';

    /**
     * @return string Returns version of model
     */
    public function get_plugin_version()
    {
        return '1.0.2';
    }

    /**
     * @return array Returns an array with plugin details populated array returned by default_plugin_details_fields() method
     */
    public function get_plugin_details()
    {
        return array(
            'name' => 'Backup Plugin',
            'description' => 'Manages backing up framework database and files.',
        );
    }

    public function get_models()
    {
        return array( 'rules' );
    }

    /**
     * @inheritdoc
     */
    public function get_agent_jobs_definition()
    {
        return array(
            self::AGENT_JOB_HANDLE => array(
                'title' => 'Backup system according to rules',
                'route' => array(
                    'plugin' => 'backup',
                    'controller' => 'index_bg',
                    'action' => 'run_backups_ag'
                ),
                'timed_seconds' => self::AGENT_BACKUPS_RUN_SECS,
            ),
        );
    }

    /**
     * @inheritdoc
     */
    public function get_roles_definition()
    {
        $return_arr = array(
            self::ROLE_BACKUP_OPERATOR => array(
                'name' => 'Backup Operator',
                'description' => 'Allow user to view backup information',
                'role_units' => array(
                    self::ROLEU_LIST_RULES => array(
                        'name' => 'Backup list rules',
                        'description' => 'Allow user to list backup rules',
                    ),
                    self::ROLEU_LIST_BACKUPS => array(
                        'name' => 'Backup list files',
                        'description' => 'Allow user to list backup files',
                    ),
                ),
            ),
        );

        $return_arr[self::ROLE_BACKUP_MANAGER] = $return_arr[self::ROLE_BACKUP_OPERATOR];

        $return_arr[self::ROLE_BACKUP_MANAGER]['name'] = 'Backup Manager';
        $return_arr[self::ROLE_BACKUP_MANAGER]['description'] = 'User wich has full access over backup functionality';

        $return_arr[self::ROLE_BACKUP_MANAGER]['role_units'][self::ROLEU_MANAGE_RULES] = array(
            'name' => 'Backups Manage rules',
            'description' => 'Can manage backup rules',
        );
        $return_arr[self::ROLE_BACKUP_MANAGER]['role_units'][self::ROLEU_DELETE_BACKUPS] = array(
            'name' => 'Backups delete files',
            'description' => 'Can delete backup files',
        );

        return $return_arr;
    }

    /**
     * @inheritdoc
     */
    public function get_settings_structure()
    {
        return array(
            'location' => array(
                'display_name' => 'Default backups location',
                'display_hint' => 'A writable directory where backup files will be generated. If path is not absolute, it will be relative to framework uploads dir ('.PHS_UPLOADS_DIR.').',
                'type' => PHS_params::T_NOHTML,
                'default' => self::DIRNAME_IN_UPLOADS,
                'custom_renderer' => array( $this, 'plugin_settings_render_location' ),
            ),
        );
    }

    public function plugin_settings_render_location( $params )
    {
        $params = self::validate_array( $params, $this->default_custom_renderer_params() );

        if( isset( $params['field_details']['default'] ) )
            $default_value = $params['field_details']['default'];
        else
            $default_value = self::DIRNAME_IN_UPLOADS;

        if( isset( $params['field_value'] ) )
            $field_value = $params['field_value'];
        else
            $field_value = $default_value;

        if( isset( $params['field_id'] ) )
            $field_id = $params['field_id'];
        else
            $field_id = $params['field_name'];

        $error_msg = '';
        $stats_str = '';
        if( !($location_details = $this->resolve_directory_location( $field_value )) )
            $error_msg = $this->_pt( 'Couldn\'t obtain current location details.' );

        elseif( empty( $location_details['location_exists'] ) )
            $error_msg = $this->_pt( 'At the moment directory doesn\'t exist. System will try creating it at first run.' );

        elseif( empty( $location_details['full_path'] )
                    or !is_writeable( $location_details['full_path'] ) )
            $error_msg = $this->_pt( 'Resolved directory is not writeable.' );

        elseif( !($stats_arr = $this->get_directory_stats( $location_details['full_path'] )) )
            $error_msg = $this->_pt( 'Couldn\'t obtain directory stats.' );

        else
            $stats_str = $this->_pt( 'Total space: %s, Free space: %s', format_filesize( $stats_arr['total_space'] ), format_filesize( $stats_arr['free_space'] ) );

        ob_start();
        ?><input type="text" id="<?php echo $field_id ?>" name="<?php echo $params['field_name']?>"
                 class="form-control <?php echo $params['field_details']['extra_classes'] ?>"
                 style="<?php echo $params['field_details']['extra_style'] ?>"
                 <?php echo (empty( $params['field_details']['editable'] )?'disabled="disabled" readonly="readonly"' : '')?>
                 value="<?php echo self::_e( $field_value )?>" /><?php

        if( !empty( $error_msg ) )
        {
            ?><div style="color:red;"><?php echo $error_msg?></div><?php
        } elseif( !empty( $stats_str ) )
        {
            ?><small><strong><?php echo $stats_str?></strong></small><br/><?php
        }

        $render_result = ob_get_clean();

        return $render_result;
    }

    public function resolve_directory_location( $location_path )
    {
        if( empty( $location_path ) )
        {
            $location_path = self::DIRNAME_IN_UPLOADS;
            $location_root = PHS_UPLOADS_DIR;
        } else
        {
            // Make sure we work only with /
            $location_path = str_replace( '\\', '/', $location_path );

            if( substr( $location_path, 0, 1 ) == '/'
             or substr( $location_path, 1, 2 ) == ':/' )
                $location_root = '';
            else
                $location_root = PHS_UPLOADS_DIR;

        }

        $location_path = rtrim( $location_path, '/' );

        $full_path = $location_root.$location_path;

        $return_arr = array(
            'location_exists' => true,
            'location_root' => $location_root,
            'location_path' => $location_path,
            'full_path' => $full_path,
        );

        if( empty( $full_path )
         or !@is_dir( $full_path ) )
            $return_arr['location_exists'] = false;

        return $return_arr;
    }

    public function get_directory_stats( $dir )
    {
        $this->reset_error();

        if( !@is_dir( $dir ) )
        {
            $this->set_error( self::ERR_PARAMETERS, $this->_pt( 'Invalid directory. Cannot obtain stats.' ) );
            return false;
        }

        if( !($free_space = @disk_free_space( $dir )) )
            $free_space = 0;
        if( !($total_space = @disk_total_space( $dir )) )
            $total_space = 0;

        return array(
            'free_space' => $free_space,
            'total_space' => $total_space,
        );
    }

    public function run_backups_bg()
    {

    }

    public function trigger_after_left_menu_admin( $hook_args = false )
    {
        $hook_args = self::validate_array( $hook_args, PHS_Hooks::default_buffer_hook_args() );

        $data = array();

        $hook_args['buffer'] = $this->quick_render_template_for_buffer( 'left_menu_admin', $data );

        return $hook_args;
    }

    public function trigger_assign_registration_roles( $hook_args = false )
    {
        $hook_args = self::validate_array( $hook_args, PHS_Hooks::default_user_registration_roles_hook_args() );

        /** @var \phs\plugins\accounts\models\PHS_Model_Accounts $accounts_model */
        if( !($accounts_model = PHS::load_model( 'accounts', 'accounts' ))
         or empty( $hook_args['account_data'] )
         or !($account_arr = $accounts_model->data_to_array( $hook_args['account_data'] )) )
            return $hook_args;

        if( empty( $hook_args['roles_arr'] ) )
            $hook_args['roles_arr'] = array();

        if( $accounts_model->acc_is_operator( $account_arr ) )
            $hook_args['roles_arr'][] = self::ROLE_ADMIN_COMPANY;
        else
            $hook_args['roles_arr'][] = self::ROLE_COMPANY_OWNER;

        return $hook_args;
    }

}
