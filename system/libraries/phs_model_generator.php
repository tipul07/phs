<?php

namespace phs\libraries;

abstract class PHS_Model_Core_Generator extends PHS_Model_Core_Base
{
    /**
     * @param array $constrain_arr Conditional db fields
     * @param array|false $params Parameters in the flow
     *
     * @return Generator|null array of records matching conditions acting as generator
     */
    function get_details_fields_gen( $constrain_arr, $params = false )
    {
        if( !($common_arr = $this->get_details_common( $constrain_arr, $params ))
         or !is_array( $common_arr ) or empty( $common_arr['qid'] ) )
            return;

        if( $params['result_type'] == 'single' )
            yield db_fetch_assoc( $common_arr['qid'], $params['db_connection'] );

        else
        {
            while( ($row_arr = db_fetch_assoc( $common_arr['qid'], $params['db_connection'] )) )
            {
                yield $row_arr;
            }
        }
    }

    public function get_list_gen( $params = false )
    {
        if( !($common_arr = $this->get_list_common( $params ))
         or !is_array( $common_arr ) or empty( $common_arr['qid'] ) )
            return;

        while( ($item_arr = db_fetch_assoc( $common_arr['qid'], $params['db_connection'] )) )
        {
            yield $item_arr;
        }
    }
}
