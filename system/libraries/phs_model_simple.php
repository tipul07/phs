<?php
namespace phs\libraries;

abstract class PHS_Model_Core_generator extends PHS_Model_Core_base
{
    function get_details_fields_gen( $constrain_arr, $params = false )
    {
        return $this->get_details_fields( $constrain_arr, $params );
    }

    public function get_list_gen( $params = false )
    {
        return $this->get_list( $params );
    }
}
