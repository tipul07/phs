<?php

namespace phs\plugins\backup;

use \phs\PHS;
use \phs\libraries\PHS_Plugin;
use \phs\libraries\PHS_Hooks;
use \phs\libraries\PHS_Roles;

class PHS_Plugin_Backup extends PHS_Plugin
{
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
        return '1.0.1';
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

    public function trigger_after_left_menu_admin( $hook_args = false )
    {
        $hook_args = self::validate_array( $hook_args, PHS_Hooks::default_buffer_hook_args() );

        $data = array();

        $hook_args['buffer'] = $this->quick_render_template_for_buffer( 'left_menu_admin', $data );

        return $hook_args;
    }

}
