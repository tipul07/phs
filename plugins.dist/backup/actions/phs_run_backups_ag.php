<?php

namespace phs\plugins\backup\actions;

use phs\PHS;
use phs\PHS_Scope;
use phs\libraries\PHS_Action;
use phs\libraries\PHS_Logger;
use phs\plugins\backup\PHS_Plugin_Backup;

class PHS_Action_Run_backups_ag extends PHS_Action
{
    public function allowed_scopes() : array
    {
        return [PHS_Scope::SCOPE_AGENT];
    }

    public function execute()
    {
        /** @var PHS_Plugin_Backup $backup_plugin */
        if (!($backup_plugin = PHS_Plugin_Backup::get_instance())) {
            PHS_Logger::error('!!! Error: Couldn\'t load backup plugin.', $backup_plugin::LOG_CHANNEL);

            $this->set_error(self::ERR_DEPENDENCIES, $this->_pt('Couldn\'t load backup plugin.'));

            return false;
        }

        if (!($check_result = $backup_plugin->run_backups_bg())) {
            if ($backup_plugin->has_error()) {
                $this->copy_error($backup_plugin);
            } else {
                $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Checking backup rules failed.'));
            }

            PHS_Logger::error('!!! Error running backup rules: '.$this->get_error_message(), $backup_plugin::LOG_CHANNEL);
        } else {
            if (empty($check_result['backup_rules'])) {
                $check_result['backup_rules'] = 0;
            }
            if (empty($check_result['failed_rules_ids']) || !is_array($check_result['failed_rules_ids'])) {
                $check_result['failed_rules_ids'] = [];
            }

            PHS_Logger::notice('Run '.$check_result['backup_rules'].' rules, '
                              .count($check_result['failed_rules_ids']).' failed: '
                              .(empty($check_result['failed_rules_ids']) ? 'N/A' : implode(', ', $check_result['failed_rules_ids'])).'.', $backup_plugin::LOG_CHANNEL);
        }

        if (!($copy_result = $backup_plugin->copy_backup_files_bg())) {
            if ($backup_plugin->has_error()) {
                $this->copy_error($backup_plugin);
            } else {
                $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Copying backup result files failed.'));
            }

            PHS_Logger::error('!!! Error copying result files: '.$this->get_error_message(), $backup_plugin::LOG_CHANNEL);
        } else {
            if (empty($copy_result['results_copied'])) {
                $copy_result['results_copied'] = 0;
            }
            if (empty($copy_result['failed_copy_result_ids']) || !is_array($copy_result['failed_copy_result_ids'])) {
                $copy_result['failed_copy_result_ids'] = [];
            }

            PHS_Logger::notice('Copied '.$copy_result['results_copied'].' results, '
                              .count($copy_result['failed_copy_result_ids']).' copy actions failed: '
                              .(empty($copy_result['failed_copy_result_ids']) ? 'N/A' : implode(', ', $copy_result['failed_copy_result_ids'])).'.', $backup_plugin::LOG_CHANNEL);
        }

        if (!($delete_result = $backup_plugin->delete_old_backups_bg())) {
            if ($backup_plugin->has_error()) {
                $this->copy_error($backup_plugin);
            } else {
                $this->set_error(self::ERR_FUNCTIONALITY, $this->_pt('Deleting old backup results failed.'));
            }

            PHS_Logger::error('!!! Error deleting old results: '.$this->get_error_message(), $backup_plugin::LOG_CHANNEL);
        } else {
            if (empty($delete_result['results_deleted'])) {
                $delete_result['results_deleted'] = 0;
            }
            if (empty($delete_result['failed_delete_result_ids']) || !is_array($delete_result['failed_delete_result_ids'])) {
                $delete_result['failed_delete_result_ids'] = [];
            }

            PHS_Logger::notice('Deleted '.$delete_result['results_deleted'].' results, '
                              .count($delete_result['failed_delete_result_ids']).' deletions failed: '
                              .(empty($delete_result['failed_delete_result_ids']) ? 'N/A' : implode(', ', $delete_result['failed_delete_result_ids'])).'.', $backup_plugin::LOG_CHANNEL);
        }

        return PHS_Action::default_action_result();
    }
}
