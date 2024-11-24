<?php

namespace phs\graphql\libraries;

use Closure;
use phs\PHS;
use Exception;
use Throwable;
use phs\PHS_Api;
use phs\PHS_Scope;
use GraphQL\GraphQL;
use RuntimeException;
use GraphQL\Type\Schema;
use GraphQL\Error\DebugFlag;
use GraphQL\Type\SchemaConfig;
use phs\libraries\PHS_Graphql_Type;
use phs\libraries\PHS_Instantiable;
use GraphQL\Type\Definition\ObjectType;

final class PHS_Graphql
{
    /** @var array Defined types */
    private static array $types = [];

    private static array $reverse_types = [];

    /** @var array Instantiated types */
    private static array $types_instances = [];

    /** @var array Types that should go to query root */
    private static array $query_types = [];

    public static function resolve_request(array $input) : array
    {
        $return_arr = [];
        $return_arr['has_error'] = false;
        $return_arr['error_msg'] = '';
        $return_arr['output_arr'] = [];

        try {
            include_once PHS_GRAPHQL_DIR.'bootstrap.php';

            $schema = new Schema(
                (new SchemaConfig())
                    ->setTypes(self::_get_schema_types())
                    ->setQuery(self::_get_query_object())
                    ->setMutation(self::_get_mutation_types())
            );

            $result = GraphQL::executeQuery(schema: $schema, source: $input['query'] ?? '', variableValues: $input['variables'] ?? null);

            $return_arr['output_arr'] = $result->toArray(PHS::st_debugging_mode()
                ? DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::RETHROW_INTERNAL_EXCEPTIONS | DebugFlag::RETHROW_UNSAFE_EXCEPTIONS
                : DebugFlag::NONE);
        } catch (Throwable $e) {
            $return_arr['output_arr'] = [
                'errors' => [
                    ['message' => $e->getMessage()],
                ],
            ];

            $return_arr['error_msg'] = 'Error while running query: '.$e->getMessage();
            $return_arr['has_error'] = true;
        }

        return $return_arr;
    }

    /**
     * @param class-string<PHS_Graphql_Type> $type_class
     * @param bool $is_query_type
     *
     * @return bool
     */
    public static function register_type(string $type_class, bool $is_query_type = false) : bool
    {
        if (!($instance_details = PHS_Instantiable::extract_details_from_full_namespace_name($type_class))
             || $instance_details['instance_type'] !== PHS_Instantiable::INSTANCE_TYPE_GRAPHQL
             || !($type_name = $type_class::get_type_name())
             || !empty(self::$types[$type_name])) {
            return false;
        }

        self::$types[$type_name] = $type_class;
        self::$reverse_types[$type_class] = $type_name;

        if ($is_query_type) {
            self::$query_types[$type_name] = $type_class;
        }

        return true;
    }

    public static function valid_context() : bool
    {
        return defined('PHS_PATH')
               && PHS_Scope::current_scope() === PHS_Scope::SCOPE_GRAPHQL;
    }

    public static function get_types() : array
    {
        return self::$types;
    }

    public static function get_type_by_class_name(string $type_class) : array
    {
        return self::$reverse_types[$type_class] ?? [];
    }

    public static function get_query_types() : array
    {
        return self::$query_types;
    }

    public static function ref_by_class(string $type_class) : Closure
    {
        return static fn () => self::instance_by_class($type_class);
    }

    public static function ref_by_name(string $type_name) : Closure
    {
        return static fn () => self::instance_by_name($type_name);
    }

    public static function instance_by_class(string $type_class) : ?ObjectType
    {
        if (empty(self::$reverse_types[$type_class])) {
            return null;
        }

        return self::phs_instance_by_name(self::$reverse_types[$type_class])?->graphql_type() ?: null;
    }

    public static function instance_by_name(string $type_name) : ?ObjectType
    {
        return self::phs_instance_by_name($type_name)?->graphql_type() ?: null;
    }

    public static function phs_instance_by_class(string $type_class) : ?PHS_Graphql_Type
    {
        if (empty(self::$reverse_types[$type_class])) {
            return null;
        }

        return self::phs_instance_by_name(self::$reverse_types[$type_class]);
    }

    public static function phs_instance_by_name(string $type_name) : ?PHS_Graphql_Type
    {
        if (empty(self::$types[$type_name])) {
            return null;
        }

        if (!empty(self::$types_instances[$type_name])) {
            return self::$types_instances[$type_name];
        }

        $type_class = self::$types[$type_name];
        if (!($type_instance = $type_class::get_instance())
            || !($type_instance instanceof PHS_Graphql_Type)) {
            return null;
        }

        self::$types_instances[$type_name] = $type_instance;

        return self::$types_instances[$type_name];
    }

    private static function _get_query_object() : ObjectType
    {
        return new ObjectType([
            'name'   => 'Query',
            'fields' => self::_get_query_types_as_fields(),
        ]);
    }

    private static function _get_schema_types() : callable
    {
        return static function() {
            $types = [];
            foreach (self::get_types() as $type_name => $type_class) {
                if (!($type_instance = self::phs_instance_by_name($type_name))) {
                    continue;
                }

                $types[$type_name] = $type_instance->lazy_graphql_type();
            }

            return $types;
        };
    }

    private static function _get_query_types_as_fields() : array
    {
        if (!($query_types = self::get_query_types())) {
            return [];
        }

        $query_fields = [];
        foreach ($query_types as $type_name => $type_class) {
            if (!($type_instance = self::phs_instance_by_name($type_name))) {
                continue;
            }

            if (($custom_type_name = $type_instance->get_query_definition_type_name())) {
                $type_name = $custom_type_name;
            }

            $query_fields[$type_name] = $type_instance->get_query_definition();
        }

        return $query_fields;
    }

    private static function _get_mutation_types() : ?ObjectType
    {
        return null;
        // return new ObjectType([
        //     'name'   => 'Mutation',
        //     'fields' => []
        //     ]);
    }
}
