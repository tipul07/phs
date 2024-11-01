<?php

namespace phs\plugins\accounts\graphql\types;

use Closure;
use phs\libraries\PHS_Graphql_Type;
use phs\plugins\accounts\models\PHS_Model_Accounts_details;

class PHS_Graphql_Account_details extends PHS_Graphql_Type
{
    public function get_type_name() : string
    {
        return 'accountDetails';
    }

    public function get_type_description() : string
    {
        return 'Account details type';
    }

    public function get_model_class() : ?string
    {
        return PHS_Model_Accounts_details::class;
    }

    public function get_model_flow_params() : array
    {
        return ['table_name' => 'users_details'];
    }

    protected function _get_query_resolver_args() : array
    {
        return [...parent::_get_query_resolver_args(), ...[
            'account_id' => ['type' => self::int()],
        ]];
    }

    protected function _get_query_resolver() : Closure
    {
        return function($root, array $args) {
            /** @var PHS_Model_Accounts_details $accounts_details_model */
            if (!($accounts_details_model = $this->get_model_instance())) {
                return null;
            }

            if (!empty($args['id'])) {
                return $accounts_details_model->data_to_record_data($args['id'], $this->get_model_flow_params()) ?: null;
            }

            if (!empty($args['account_id'])) {
                return $accounts_details_model->data_to_record_data($args['account_id'], $this->get_model_flow_params()) ?: null;
            }

            return null;
        };
    }
}
