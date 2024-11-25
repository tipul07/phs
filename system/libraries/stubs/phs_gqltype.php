<?php
namespace phs\plugins\__PLUGIN_NAME__\graphql\types__CLASS_NAMESPACE__;

use phs\libraries\PHS_Graphql_Type;

class PHS_Graphql___CLASS_NAME__ extends PHS_Graphql_Type
{
    // TODO: Use this method to provide working table if this is not the main model table
    // public function get_model_flow_params() : array
    // {
    //     return ['table_name' => 'not_the_main_table'];
    // }

    public static function get_model_class() : ?string
    {
        // TODO: Change this to your model class name (e.g. PHS_Model_Mymodel::class)
        return null;
    }

    public static function get_type_name() : string
    {
        // TODO: Change this to your type name
        return '';
    }

    public static function get_type_description() : string
    {
        // TODO: Change this to your type description
        return '';
    }
}
