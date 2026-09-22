<?php
namespace phs\system\core\events\accounts;

use phs\libraries\PHS_Event;
use phs\libraries\PHS_Record_data;
use phs\plugins\accounts\models\PHS_Model_Accounts;

class PHS_Event_Accounts_registration_roles extends PHS_Event
{
    public const OLD_HOOK = 'phs_user_registration_roles';

    /**
     * @inheritdoc
     */
    public function supports_background_listeners() : bool
    {
        return false;
    }

    public function add_roles(array $roles_arr) : void
    {
        $this->set_output('roles_arr',
            self::array_merge_unique_values(
                $this->get_output('roles_arr') ?: [], $roles_arr)
        );
    }

    protected function _input_parameters() : array
    {
        return [
            'account_data' => null,
            'roles_arr'    => [],
        ];
    }

    protected function _output_parameters() : array
    {
        return [
            'account_data' => null,
            'roles_arr'    => [],
        ];
    }

    /**
     * Triggering method for obtaining account roles
     *
     * @param int|array|PHS_Record_data $account_data
     * @param array $roles_arr
     *
     * @return array
     */
    public static function roles_for_account(
        int | array | PHS_Record_data $account_data,
        array $roles_arr = []
    ) : array {
        if (!$account_data
            || !($accounts_model = PHS_Model_Accounts::get_instance())
            || !($account_arr = $accounts_model->data_to_array($account_data))
            || !($event_obj = self::trigger(
                ['account_data' => $account_arr, 'roles_arr' => $roles_arr, ],
                params: ['old_hooks' => [self::OLD_HOOK]])
            )) {
            return $roles_arr;
        }

        return $event_obj->get_output('roles_arr') ?: [];
    }
}
