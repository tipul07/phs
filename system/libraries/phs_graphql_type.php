<?php

namespace phs\libraries;

use Closure;
use GraphQL\Type\Definition\Type;
use GraphQL\Error\InvariantViolation;
use phs\graphql\libraries\PHS_Graphql;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;

abstract class PHS_Graphql_Type extends PHS_Instantiable
{
    private ?PHS_Model $model_obj = null;

    private ?ObjectType $graphql_type = null;

    abstract public static function get_model_class() : ?string;

    abstract public static function get_type_name() : string;

    abstract public static function get_type_description() : string;

    public function instance_type() : string
    {
        return self::INSTANCE_TYPE_GRAPHQL;
    }

    public function get_model_flow_params() : array
    {
        return $this->get_model_instance()?->fetch_default_flow_params() ?: [];
    }

    public function get_type_fields() : array
    {
        return [];
    }

    /**
     * Override this method when using default extract_fields_from_model_definition() method
     * to extract GraphQL type fields from model fields, but you don't want to expose all database fields to GraphQL
     *
     * @return array
     */
    public function do_not_extract_model_fields_for_graphql_type() : array
    {
        return [];
    }

    public function get_query_definition() : array
    {
        return [
            'type'    => PHS_Graphql::ref_by_class(static::class),
            'args'    => $this->_get_query_resolver_args(),
            'resolve' => $this->_get_query_resolver(),
        ];
    }

    public function get_type_definition() : array
    {
        return [
            'name'        => static::get_type_name(),
            'description' => static::get_type_description(),
            'fields'      => $this->get_type_fields() ?: $this->extract_fields_from_model_definition(),
        ];
    }

    public function graphql_type() : ObjectType
    {
        if (!$this->graphql_type) {
            $this->graphql_type = new ObjectType($this->get_type_definition());
        }

        return $this->graphql_type;
    }

    public function lazy_graphql_type() : Closure
    {
        return fn () => $this->graphql_type();
    }

    public function get_model_instance() : ?PHS_Model
    {
        if ($this->model_obj) {
            return $this->model_obj;
        }

        if (!($model_class = static::get_model_class())) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Model class not set.'));

            return null;
        }

        if ( !($model_obj = $model_class::get_instance())
            || !($model_obj instanceof PHS_Model) ) {
            $this->set_error(self::ERR_FUNCTIONALITY,
                self::_t('Error instantiating model class.'));

            return null;
        }

        $this->model_obj = $model_obj;

        return $this->model_obj;
    }

    public function extract_fields_from_model_definition() : array
    {
        if (!($model_obj = $this->get_model_instance())) {
            return [];
        }

        $exclude_fields = $this->do_not_extract_model_fields_for_graphql_type() ?: [];

        $fields_arr = [];
        foreach ($model_obj->all_fields_definition($this->get_model_flow_params()) ?: [] as $field_name => $field_definition) {
            if (!empty($exclude_fields)
               && in_array($field_name, $exclude_fields, true)) {
                continue;
            }

            $fields_arr[$field_name] = self::_model_field_type_to_graphql_type($field_definition);
        }

        // TODO: Add relations as well. Find a way to link relations with GraphQL types
        // foreach ($model_obj->relations() as $rel_name => $rel_definition) {
        //     if(!empty($exclude_fields)
        //        && in_array($field_name, $exclude_fields, true)) {
        //         continue;
        //     }
        //
        //     $fields_arr[$rel_name] = self::_convert_relation_definition_to_graphql_type_dedfinition($field_definition);
        // }

        return $fields_arr;
    }

    protected function _get_query_resolver_args() : array
    {
        if (!($model_obj = $this->get_model_instance())
            || !($primary_key = $model_obj->get_primary_key($this->get_model_flow_params()))) {
            return [];
        }

        return [
            $primary_key => ['type' => self::id()],
        ];
    }

    protected function _get_query_resolver() : Closure
    {
        return function($root, array $args) {
            if (($model_obj = $this->get_model_instance())
                && ($primary_key = $model_obj->get_primary_key($this->get_model_flow_params()))
                && !empty($args[$primary_key])) {
                return $model_obj->get_details_to_record_data($args[$primary_key], $this->get_model_flow_params());
            }

            return null;
        };
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

    public static function listOf($type) : ListOfType
    {
        return Type::listOf($type);
    }

    protected static function _model_field_type_to_graphql_type(array $field_definition) : ScalarType
    {
        if ( !empty($field_definition['primary'])) {
            return self::id();
        }

        if (empty($field_definition['type'])) {
            return self::string();
        }

        return match ($field_definition['type']) {
            PHS_Model_Mysqli::FTYPE_TINYINT, PHS_Model_Mysqli::FTYPE_SMALLINT, PHS_Model_Mysqli::FTYPE_MEDIUMINT, PHS_Model_Mysqli::FTYPE_INT, PHS_Model_Mysqli::FTYPE_BIGINT => self::int(),
            PHS_Model_Mysqli::FTYPE_DECIMAL, PHS_Model_Mysqli::FTYPE_FLOAT, PHS_Model_Mysqli::FTYPE_DOUBLE, PHS_Model_Mysqli::FTYPE_REAL => self::float(),
            default => self::string(),
        };
    }
}
