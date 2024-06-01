<?php

namespace phs\plugins\__PLUGIN_NAME__\migrations;

use phs\libraries\PHS_Migration;

class PHS___ITEM_NAME__ extends PHS_Migration
{
    // ! Change progress step at which migration will do `PHS_Maintenance::output()` calls
    // protected int $_progress_step = 1;

    protected function bootstrap(bool $forced = false) : bool
    {
        /**
         * Register methods which will be called on required install or update events
         * E.g.
         * $this->plugin_install(
         * // Method to be called
         * [$this, 'migration_plugin_install'],
         * // Plugin which triggers the event
         * PHS_Plugin_Accounts::class
         * );
         * $this->after_update_table(
         * // Method to be called
         * [$this, 'after_update_table_users_details'],
         * // Model which triggers the event
         * PHS_Model_Accounts_details::class,
         * // What table from the model are we interested in
         * 'users_details'
         * );
         *
         * Remember to call $this->refresh_migration_record($total_count, $current_count); within a reasonable time frame
         * so framework won't consider your migration script as stalling or for CI/CD pipeline to fail.
         *
         * @see PHS_Migration::plugin_install()
         * @see PHS_Migration::plugin_start()
         * @see PHS_Migration::plugin_after_roles()
         * @see PHS_Migration::plugin_after_jobs()
         * @see PHS_Migration::before_missing_table()
         * @see PHS_Migration::after_missing_table()
         * @see PHS_Migration::before_update_table()
         * @see PHS_Migration::after_update_table()
         * @see PHS_Migration::plugin_finish()
         * @see PHS_Migration::refresh_migration_record()
         * /**/

        return true;
    }
}
