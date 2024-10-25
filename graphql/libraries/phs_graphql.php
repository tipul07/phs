<?php

namespace phs\graphql\libraries;

use phs\PHS;
use Exception;
use Throwable;
use phs\PHS_Api;
use GraphQL\GraphQL;
use RuntimeException;
use GraphQL\Type\Schema;
use GraphQL\Error\DebugFlag;
use GraphQL\Type\SchemaConfig;
use GraphQL\Type\Definition\Type;
use phs\libraries\PHS_Instantiable;
use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;

final class PHS_Graphql
{
    /** @var array Defined types */
    private static array $types = [];

    private static array $reverse_types = [];

    /** @var array Instantiated types */
    private static array $types_instances = [];

    /** @var array Types that should go to query root */
    private static array $query_types = [];

    public static function resolve_request() : bool
    {
        $has_error = false;

        try {
            include_once PHS_GRAPHQL_DIR.'bootstrap.php';

            $schema = new Schema(
                (new SchemaConfig())
                    ->setTypes(self::get_types())
                    ->setQuery(self::_get_query_object())
                    ->setMutation(self::_get_mutation_types())
            );

            if (!($input = PHS_Api::get_request_body_as_json_array())
                || empty($input['query'])) {
                throw new RuntimeException('Malformed JSON string');
            }

            $result = GraphQL::executeQuery(schema: $schema, source: $input['query'], variableValues: $input['variables'] ?? null);

            $output = $result->toArray(PHS::st_debugging_mode()
                ? DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::RETHROW_INTERNAL_EXCEPTIONS | DebugFlag::RETHROW_UNSAFE_EXCEPTIONS
                : DebugFlag::NONE);
        } catch (Throwable $e) {
            $output = [
                'errors' => [
                    ['message' => $e->getMessage()],
                ],
            ];
            $has_error = true;
        }

        @header('Content-Type: application/json; charset=UTF-8');

        try {
            echo @json_encode($output, JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            echo '{"error":{"message":"Internal error.'
                 .(PHS::st_debugging_mode() ? ' ('.$e->getMessage().')' : '').'"}}';
            $has_error = true;
        }

        return !$has_error;
    }

    public static function register_type(string $type_name, string $type_class, bool $is_query_type = false) : bool
    {
        if (!empty(self::$types[$type_name])) {
            return false;
        }

        if ( !($instance_details = PHS_Instantiable::extract_details_from_full_namespace_name($type_class))
            || $instance_details['instance_type'] !== PHS_Instantiable::INSTANCE_TYPE_GRAPHQL ) {
            return false;
        }

        self::$types[$type_name] = $type_class;
        self::$reverse_types[$type_class] = $type_name;

        if ($is_query_type) {
            self::$query_types[$type_name] = $type_class;
        }

        return true;
    }

    public static function get_types() : array
    {
        return self::$types;
    }

    public static function get_type_by_typpe_class(string $type_class) : array
    {
        return self::$reverse_types[$type_class] ?? [];
    }

    public static function get_query_types() : array
    {
        return self::$query_types;
    }

    /**
     * @throws InvariantViolation
     */
    public static function boolean() : ScalarType
    {
        return Type::boolean();
    }

    /**
     * @throws InvariantViolation
     */
    public static function float() : ScalarType
    {
        return Type::float();
    }

    /**
     * @throws InvariantViolation
     */
    public static function id() : ScalarType
    {
        return Type::id();
    }

    /**
     * @throws InvariantViolation
     */
    public static function int() : ScalarType
    {
        return Type::int();
    }

    /**
     * @throws InvariantViolation
     */
    public static function string() : ScalarType
    {
        return Type::string();
    }

    private static function _get_query_object() : ObjectType
    {
        return new ObjectType([
            'name'   => 'Query',
            'fields' => [],
        ]);
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
