<?php

class PHS_Model_Plugins extends PHS_Model
{
    const MODEL_VERSION = '1.0.0';

    const HOOK_STATUSES = 'phs_plugins_statuses';

    const STATUS_COPIED = 1, STATUS_INSTALLED = 2, STATUS_ACTIVE = 3, STATUS_INACTIVE = 4;

    protected static $STATUSES_ARR = array(
        self::STATUS_COPIED => array( 'title' => 'Copied' ),
        self::STATUS_INSTALLED => array( 'title' => 'Installed' ),
        self::STATUS_ACTIVE => array( 'title' => 'Active' ),
        self::STATUS_INACTIVE => array( 'title' => 'Inactive' ),
    );

    /**
     * @return array of string Returns an array of strings containing tables that model will handle
     */
    public function get_table_names()
    {
        return array( 'plugins' );
    }

    /**
     * @return string Returns main table name used when calling insert with no table name
     */
    function get_main_table_name()
    {
        return 'plugins';
    }

    final public function get_statuses()
    {
        static $statuses_arr = array();

        if( !empty( $statuses_arr ) )
            return $statuses_arr;

        $new_statuses_arr = self::$STATUSES_ARR;
        if( ($extra_statuses_arr = PHS::trigger_hooks( self::HOOK_STATUSES, array( 'statuses_arr' => self::$STATUSES_ARR ) ))
        and is_array( $extra_statuses_arr ) and !empty( $extra_statuses_arr['statuses_arr'] ) )
            $new_statuses_arr = array_merge( $extra_statuses_arr['statuses_arr'], $new_statuses_arr );

        $statuses_arr = array();
        // Translate and validate statuses...
        if( !empty( $new_statuses_arr ) and is_array( $new_statuses_arr ) )
        {
            foreach( $new_statuses_arr as $status_id => $status_arr )
            {
                $status_id = intval( $status_id );
                if( empty( $status_id ) )
                    continue;

                if( empty( $status_arr['title'] ) )
                    $status_arr['title'] = self::_t( 'Status %s', $status_id );
                else
                    $status_arr['title'] = self::_t( $status_arr['title'] );

                $statuses_arr[$status_id] = array(
                    'title' => $status_arr['title']
                );
            }
        }

        return $statuses_arr;
    }

    public function valid_status( $status )
    {
        $all_statuses = $this->get_statuses();
        if( empty( $status )
         or empty( $all_statuses[$status] ) )
            return false;

        return $all_statuses[$status];
    }

    /**
     * Called first in insert flow.
     * Parses flow parameters if anything special should be done.
     * This should do checks on raw parameters received by insert method.
     *
     * @param array|false $params Parameters in the flow
     *
     * @return array Flow parameters array
     */
    protected function get_insert_prepare_params( $params )
    {
        if( empty( $params ) or !is_array( $params ) )
            return false;

        if( empty( $params['fields']['status'] ) )
            $params['fields']['status'] = self::STATUS_COPIED;

        if( !$this->valid_status( $params['fields']['status'] ) )
        {
            $this->set_error( self::ERR_INSERT, self::_t( 'Please provide a valid plugin status.' ) );
            return false;
        }

        $now_date = date( self::DATETIME_DB );

        $params['fields']['status_date'] = $now_date;

        if( empty( $params['fields']['cdate'] ) )
            $params['fields']['cdate'] = date( self::DATETIME_DB );
        else
            $params['fields']['cdate'] = date( self::DATETIME_DB, parse_db_date( $params['fields']['cdate'] ) );

        return $params;
    }

    /**
     * @param array|false $params Parameters in the flow
     *
     * @return array Returns an array with table fields
     */
    final protected function fields_definition( $params = false )
    {
        // $params should be flow parameters...
        if( empty( $params ) or !is_array( $params )
         or empty( $params['table_name'] ) )
            return false;

        $return_arr = array();
        switch( $params['table_name'] )
        {
            case 'plugins':
                $return_arr = array(
                    'id' => array(
                        'type' => self::FTYPE_INT,
                        'primary' => true,
                        'auto_increment' => true,
                    ),
                    'instance_id' => array(
                        'type' => self::FTYPE_VARCHAR,
                        'length' => '255',
                        'nullable' => true,
                        'index' => true,
                    ),
                    'added_by' => array(
                        'type' => self::FTYPE_INT,
                    ),
                    'is_core' => array(
                        'type' => self::FTYPE_TINYINT,
                        'length' => '2',
                        'index' => true,
                    ),
                    'status' => array(
                        'type' => self::FTYPE_TINYINT,
                        'length' => '2',
                        'index' => true,
                    ),
                    'status_date' => array(
                        'type' => self::FTYPE_DATETIME,
                        'index' => false,
                    ),
                    'cdate' => array(
                        'type' => self::FTYPE_DATETIME,
                    ),
                );
            break;
        }

        return $return_arr;
    }

}
