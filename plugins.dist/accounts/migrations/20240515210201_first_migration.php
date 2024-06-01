<?php

namespace phs\plugins\accounts\migrations;

use phs\PHS_Maintenance;
use phs\libraries\PHS_Migration;
use phs\plugins\accounts\PHS_Plugin_Accounts;
use phs\system\core\models\PHS_Model_Migrations;
use phs\plugins\accounts\models\PHS_Model_Accounts_details;
use phs\system\core\events\migrations\PHS_Event_Migration_models;
use phs\system\core\events\migrations\PHS_Event_Migration_plugins;

class PHS_First_migration extends PHS_Migration
{
    private const TOTAL_COUNT = 9;

    // ! Change progress step at which migration will do `PHS_Maintenance::output()` calls
    protected int $_progress_step = 1;

    public function migration_plugin_install(PHS_Event_Migration_plugins $event_obj) : bool
    {
        // Update last_action and progress fields of migration record, so it won't be considered as stalling
        $this->refresh_migration_record(self::TOTAL_COUNT, 1);

        PHS_Maintenance::output("\t".'Migration plugin install '
                                .($event_obj->get_input('plugin_instance_id') ?? 'N/A')
                                .', class '
                                .($event_obj->get_input('plugin_class') ?? 'N/A')
                                .'.');

        PHS_Maintenance::output(
            "\t".'Forced: '.($event_obj->is_forced() ? 'Yes' : 'No')
            .', Dry Update: '.($event_obj->is_dry_update() ? 'Yes' : 'No').'.'
        );

        return true;
    }

    public function migration_plugin_start(PHS_Event_Migration_plugins $event_obj) : bool
    {
        // Update last_action and progress fields of migration record, so it won't be considered as stalling
        $this->refresh_migration_record(self::TOTAL_COUNT, 2);

        PHS_Maintenance::output("\t".'Migration plugin start '
                                .($event_obj->get_input('plugin_instance_id') ?? 'N/A')
                                .', class '
                                .($event_obj->get_input('plugin_class') ?? 'N/A')
                                .'.');

        PHS_Maintenance::output(
            "\t".'Forced: '.($event_obj->is_forced() ? 'Yes' : 'No')
            .', Dry Update: '.($event_obj->is_dry_update() ? 'Yes' : 'No').'.'
        );

        return true;
    }

    public function migration_plugin_after_roles(PHS_Event_Migration_plugins $event_obj) : bool
    {
        // Update last_action and progress fields of migration record, so it won't be considered as stalling
        $this->refresh_migration_record(self::TOTAL_COUNT, 3);

        PHS_Maintenance::output("\t".'Migration plugin after roles '
                                .($event_obj->get_input('plugin_instance_id') ?? 'N/A')
                                .', class '
                                .($event_obj->get_input('plugin_class') ?? 'N/A')
                                .'.');

        PHS_Maintenance::output(
            "\t".'Forced: '.($event_obj->is_forced() ? 'Yes' : 'No')
            .', Dry Update: '.($event_obj->is_dry_update() ? 'Yes' : 'No').'.'
        );

        return true;
    }

    public function migration_plugin_after_jobs(PHS_Event_Migration_plugins $event_obj) : bool
    {
        // Update last_action and progress fields of migration record, so it won't be considered as stalling
        $this->refresh_migration_record(self::TOTAL_COUNT, 4);

        PHS_Maintenance::output("\t".'Migration plugin after jobs '
                                .($event_obj->get_input('plugin_instance_id') ?? 'N/A')
                                .', class '
                                .($event_obj->get_input('plugin_class') ?? 'N/A')
                                .'.');

        PHS_Maintenance::output(
            "\t".'Forced: '.($event_obj->is_forced() ? 'Yes' : 'No')
            .', Dry Update: '.($event_obj->is_dry_update() ? 'Yes' : 'No').'.'
        );

        return true;
    }

    public function before_missing_table_phs_migrations(PHS_Event_Migration_models $event_obj) : bool
    {
        // Update last_action and progress fields of migration record, so it won't be considered as stalling
        $this->refresh_migration_record(self::TOTAL_COUNT, 5);

        PHS_Maintenance::output("\t".'Before missing table trigger on model '
                                .($event_obj->get_input('model_instance_id') ?? 'N/A')
                                .', table '
                                .($event_obj->get_input('table_name') ?? 'N/A')
                                .'.');

        PHS_Maintenance::output(
            "\t".'Forced: '.($event_obj->is_forced() ? 'Yes' : 'No')
            .', Dry Update: '.($event_obj->is_dry_update() ? 'Yes' : 'No').'.'
        );

        return true;
    }

    public function after_missing_table_phs_migrations(PHS_Event_Migration_models $event_obj) : bool
    {
        // Update last_action and progress fields of migration record, so it won't be considered as stalling
        $this->refresh_migration_record(self::TOTAL_COUNT, 6);

        PHS_Maintenance::output("\t".'After missing table trigger on model '
                                .($event_obj->get_input('model_instance_id') ?? 'N/A')
                                .', table '
                                .($event_obj->get_input('table_name') ?? 'N/A')
                                .'.');

        PHS_Maintenance::output(
            "\t".'Forced: '.($event_obj->is_forced() ? 'Yes' : 'No')
            .', Dry Update: '.($event_obj->is_dry_update() ? 'Yes' : 'No').'.'
        );

        return true;
    }

    public function before_update_table_users_details(PHS_Event_Migration_models $event_obj) : bool
    {
        // Update last_action and progress fields of migration record, so it won't be considered as stalling
        $this->refresh_migration_record(self::TOTAL_COUNT, 7);

        PHS_Maintenance::output("\t".'Before update table trigger on model '
                                .($event_obj->get_input('model_instance_id') ?? 'N/A')
                                .', table '
                                .($event_obj->get_input('table_name') ?? 'N/A')
                                .'.');

        PHS_Maintenance::output(
            "\t".'Forced: '.($event_obj->is_forced() ? 'Yes' : 'No')
            .', Dry Update: '.($event_obj->is_dry_update() ? 'Yes' : 'No').'.'
        );

        return true;
    }

    public function after_update_table_users_details(PHS_Event_Migration_models $event_obj) : bool
    {
        // Update last_action and progress fields of migration record, so it won't be considered as stalling
        $this->refresh_migration_record(self::TOTAL_COUNT, 8);

        PHS_Maintenance::output("\t".'After update table trigger on model '
                                .($event_obj->get_input('model_instance_id') ?? 'N/A')
                                .', table '
                                .($event_obj->get_input('table_name') ?? 'N/A')
                                .'.');

        PHS_Maintenance::output(
            "\t".'Forced: '.($event_obj->is_forced() ? 'Yes' : 'No')
            .', Dry Update: '.($event_obj->is_dry_update() ? 'Yes' : 'No').'.'
        );

        return true;
    }

    public function migration_plugin_finish(PHS_Event_Migration_plugins $event_obj) : bool
    {
        // Update last_action and progress fields of migration record, so it won't be considered as stalling
        $this->refresh_migration_record(self::TOTAL_COUNT, 9);

        PHS_Maintenance::output("\t".'Migration plugin finish '
                                .($event_obj->get_input('plugin_instance_id') ?? 'N/A')
                                .', class '
                                .($event_obj->get_input('plugin_class') ?? 'N/A')
                                .'.');

        PHS_Maintenance::output(
            "\t".'Forced: '.($event_obj->is_forced() ? 'Yes' : 'No')
            .', Dry Update: '.($event_obj->is_dry_update() ? 'Yes' : 'No').'.'
        );

        return true;
    }

    protected function bootstrap(bool $forced = false) : bool
    {
        $this->plugin_install(
            [$this, 'migration_plugin_install'],
            PHS_Plugin_Accounts::class
        );

        $this->plugin_start(
            [$this, 'migration_plugin_start'],
            PHS_Plugin_Accounts::class
        );

        $this->plugin_after_roles(
            [$this, 'migration_plugin_after_roles'],
            PHS_Plugin_Accounts::class
        );

        $this->plugin_after_jobs(
            [$this, 'migration_plugin_after_jobs'],
            PHS_Plugin_Accounts::class
        );

        $this->before_missing_table(
            [$this, 'before_missing_table_phs_migrations'],
            PHS_Model_Migrations::class,
            'phs_migrations'
        );

        $this->after_missing_table(
            [$this, 'after_missing_table_phs_migrations'],
            PHS_Model_Migrations::class,
            'phs_migrations'
        );

        $this->before_update_table(
            [$this, 'before_update_table_users_details'],
            PHS_Model_Accounts_details::class,
            'users_details'
        );

        $this->after_update_table(
            [$this, 'after_update_table_users_details'],
            PHS_Model_Accounts_details::class,
            'users_details'
        );

        $this->plugin_finish(
            [$this, 'migration_plugin_finish'],
            PHS_Plugin_Accounts::class
        );

        return true;
    }
}
