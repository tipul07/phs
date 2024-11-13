<?php
namespace phs\plugins\accounts\graphql\types;

use phs\libraries\PHS_Graphql_Type;
use phs\graphql\libraries\PHS_Graphql;
use phs\plugins\accounts\models\PHS_Model_Accounts;

class PHS_Graphql_Accounts extends PHS_Graphql_Type
{
    public function get_model_flow_params() : array
    {
        return ['table_name' => 'users'];
    }

    public function get_type_fields() : array
    {
        return [
            'id'               => self::id(),
            'nick'             => self::string(),
            'email'            => self::string(),
            'email_verified'   => self::int(),
            'language'         => self::string(),
            'status'           => self::int(),
            'status_date'      => self::string(),
            'level'            => self::int(),
            'is_multitenant'   => self::int(),
            'failed_logins'    => self::int(),
            'locked_date'      => self::string(),
            'last_pass_change' => self::string(),
            'lastlog'          => self::string(),
            'lastip'           => self::string(),
            'cdate'            => self::string(),
            'details'          => [
                'type'        => PHS_Graphql::ref_by_class(PHS_Graphql_Account_details::class),
                'description' => 'Account details',
                'resolve'     => static function($account) {
                    return $account?->details()?->current() ?: null;
                },
            ],
            'roles_slugs' => [
                'type' => self::listOf(self::string()),
                'args' => [
                    'offset' => [
                        'type'         => self::int(),
                        'description'  => 'Offset of the list of role units returned',
                        'defaultValue' => 0,
                    ],
                    'limit' => [
                        'type'         => self::int(),
                        'description'  => 'Limit the number of role units returned',
                        'defaultValue' => 1000,
                    ],
                ],
                'resolve' => static function($account, array $args) {
                    return $account?->roles_slugs($args['offset'] ?? 0, $args['limit'] ?? 1000)?->cast_to_array() ?: null;
                },
            ],
            'roles_units_slugs' => [
                'type' => self::listOf(self::string()),
                'args' => [
                    'offset' => [
                        'type'         => self::int(),
                        'description'  => 'Offset of the list of role units returned',
                        'defaultValue' => 0,
                    ],
                    'limit' => [
                        'type'         => self::int(),
                        'description'  => 'Limit the number of role units returned',
                        'defaultValue' => 1000,
                    ],
                ],
                'resolve' => static function($account, array $args) {
                    return $account?->roles_units_slugs($args['offset'] ?? 0, $args['limit'] ?? 1000)?->cast_to_array() ?: null;
                },
            ],
        ];
    }

    public static function get_model_class() : ?string
    {
        return PHS_Model_Accounts::class;
    }

    public static function get_type_name() : string
    {
        return 'account';
    }

    public static function get_type_description() : string
    {
        return 'Platform account';
    }
}
