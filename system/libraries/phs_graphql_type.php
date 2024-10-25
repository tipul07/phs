<?php

namespace phs\libraries;

abstract class PHS_Graphql_Type extends PHS_Instantiable
{
    private ?PHS_Model $model_obj = null;

    abstract public function get_model_class() : ?string;

    abstract public function get_type_name() : string;

    abstract public function get_type_description() : string;

    public function instance_type() : string
    {
        return self::INSTANCE_TYPE_GRAPHQL;
    }

    public function get_type_fields() : array
    {
        return [];
    }

    public function get_model_instance() : ?PHS_Model
    {
        if ($this->model_obj) {
            return $this->model_obj;
        }

        if (!($model_class = $this->get_model_class())) {
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

        $fields_arr = [];
        $model_definition = $model_obj->fields_definition();
        foreach ($model_definition as $field_name => $field_definition) {
            $fields_arr[$field_name] = $field_definition;
        }

        return $fields_arr;
    }
}
