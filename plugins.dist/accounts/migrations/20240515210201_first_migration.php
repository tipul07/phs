<?php
namespace phs\plugins\accounts\migrations;

use phs\PHS_Maintenance;
use phs\libraries\PHS_Migration;
use phs\system\core\models\PHS_Model_Migrations;
use phs\plugins\accounts\models\PHS_Model_Accounts_details;
use phs\system\core\events\migrations\PHS_Event_Migration_models;

class PHS_First_migration extends PHS_Migration
{
    public function bootstrap(): bool
    {
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

        return true;
    }

    public function before_missing_table_phs_migrations(PHS_Event_Migration_models $event_obj) : bool
    {
        PHS_Maintenance::output("\t".'Before missing table trigger on model '.
                                ($event_obj->get_input('model_instance_id') ?? 'N/A').
                                ', table '.
                                ($event_obj->get_input('table_name') ?? 'N/A').
                                '.');
    }

    public function after_missing_table_phs_migrations(PHS_Event_Migration_models $event_obj) : bool
    {
        PHS_Maintenance::output("\t".'After missing table trigger on model '.
                                ($event_obj->get_input('model_instance_id') ?? 'N/A').
                                ', table '.
                                ($event_obj->get_input('table_name') ?? 'N/A').
                                '.');
    }

    public function before_update_table_users_details(PHS_Event_Migration_models $event_obj) : bool
    {
        PHS_Maintenance::output("\t".'Before update table trigger on model '.
                                ($event_obj->get_input('model_instance_id') ?? 'N/A').
                                ', table '.
                                ($event_obj->get_input('table_name') ?? 'N/A').
                                '.');
    }

    public function after_update_table_users_details(PHS_Event_Migration_models $event_obj) : bool
    {
        PHS_Maintenance::output("\t".'After update table trigger on model '.
                                ($event_obj->get_input('model_instance_id') ?? 'N/A').
                                ', table '.
                                ($event_obj->get_input('table_name') ?? 'N/A').
                                '.');
    }

    private function _load_dependencies(): bool
    {

    }
}
