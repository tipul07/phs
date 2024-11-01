<?php

namespace phs\plugins\accounts\graphql\types;

use GraphQL\Type\Definition\Type;
use phs\libraries\PHS_Graphql_Type;
use phs\graphql\libraries\PHS_Graphql;
use phs\plugins\accounts\models\PHS_Model_Accounts;

class PHS_Graphql_Accounts extends PHS_Graphql_Type
{
    public function get_type_name() : string
    {
        return 'account';
    }

    public function get_type_description() : string
    {
        return 'Account type';
    }

    public function get_model_class() : ?string
    {
        return PHS_Model_Accounts::class;
    }

    public function get_model_flow_params() : array
    {
        return ['table_name' => 'users'];
    }

    public function get_type_fields() : array
    {
        return [
            'id'               => Type::id(),
            'nick'             => Type::string(),
            'email'            => Type::string(),
            'email_verified'   => Type::int(),
            'language'         => Type::string(),
            'status'           => Type::int(),
            'status_date'      => Type::string(),
            'level'            => Type::int(),
            'is_multitenant'   => Type::int(),
            'failed_logins'    => Type::int(),
            'locked_date'      => Type::string(),
            'last_pass_change' => Type::string(),
            'lastlog'          => Type::string(),
            'lastip'           => Type::string(),
            'cdate'            => Type::string(),
            'details'          => [
                'type'        => PHS_Graphql::ref_by_class(PHS_Graphql_Account_details::class),
                'description' => 'Account details',
                'resolve'     => static function($account) {
                    return $account?->details()?->current() ?: null;
                },
            ],
            'roles_slugs' => [
                'type' => Type::listOf(Type::string()),
                'args' => [
                    'offset' => [
                        'type'         => Type::int(),
                        'description'  => 'Offset of the list of role units returned',
                        'defaultValue' => 0,
                    ],
                    'limit' => [
                        'type'         => Type::int(),
                        'description'  => 'Limit the number of role units returned',
                        'defaultValue' => 1000,
                    ],
                ],
                'resolve' => static function($account) {
                    return $account->roles_slugs($args['offset'] ?? 0, $args['limit'] ?? 1000)?->cast_to_array() ?: null;
                },
            ],
            'roles_units_slugs' => [
                'type' => Type::listOf(Type::string()),
                'args' => [
                    'offsett' => [
                        'type'         => Type::int(),
                        'description'  => 'Offset of the list of role units returned',
                        'defaultValue' => 0,
                    ],
                    'limit' => [
                        'type'         => Type::int(),
                        'description'  => 'Limit the number of role units returned',
                        'defaultValue' => 1000,
                    ],
                ],
                'resolve' => static function($account, array $args) {
                    return $account->roles_units_slugs($args['offset'] ?? 0, $args['limit'] ?? 1000)?->cast_to_array() ?: null;
                },
            ],
        ];
    }
}
