<?php
namespace phs\libraries;

abstract class PHS_Contract extends PHS_Instantiable
{
    // hardcoded maximum recursive calls when parsing data
    /** @see PHS_Contract::max_recursive_level_for_data_parsing() */
    const MAX_RECURSIVE_PARSING = 15;

    const FROM_OUTSIDE = 1, FROM_INSIDE = 2, FROM_BOTH = 3;
    protected static $KEYS_ARR = [
        self::FROM_OUTSIDE => [ 'title' => 'From Outside' ],
        self::FROM_INSIDE => [ 'title' => 'From Inside' ],
        self::FROM_BOTH => [ 'title' => 'From Both' ],
    ];

    /** @var array Array of data that was parsed */
    private $_source_data = [];

    /** @var array After parsing data this is the resulting array */
    private $_resulting_data = [];

    /** @var bool|int Tells if $_source_data is considered input or output */
    private $_data_type = false;

    /** @var bool Tells if any data was parsed */
    private $_data_was_parsed = false;

    /** @var array Normalized nodes definition */
    private $_definition_arr = [];

    /** @var array Data cache for data retrieved from models for inside sources */
    private $_data_cache = [];

    /** @var bool Was defintion normalized already? */
    private $_definition_initialized = false;

    /** @var int Maximum number of recursive calls when parsing contract data */
    private $_recursive_lvl = self::MAX_RECURSIVE_PARSING;

    /**
     * Returns an array with data nodes definition
     * @return array|bool
     * @see \phs\libraries\PHS_Contract::_get_contract_node_definition()
     */
    abstract public function get_contract_data_definition();

    /**
     * Override this method if you want to pre process data which will be processed from outside source
     * If this method returns null, outside source data will not be changed
     *
     * @param array $outside_data_arr
     * @param false|array $params_arr
     *
     * @return mixed
     */
    public function pre_processing_from_outside_source( $outside_data_arr, $params_arr = false )
    {
        return $outside_data_arr;
    }

    /**
     * Override this method if you want to pre process data which will be processed from inside source
     * If this method returns null, inside source data will not be changed
     *
     * @param array $inside_data_arr
     * @param false|array $params_arr
     *
     * @return mixed
     */
    public function pre_processing_from_inside_source( $inside_data_arr, $params_arr = false )
    {
        return $inside_data_arr;
    }

    /**
     * Override this method if you want to post process values for values from outside source
     * If this method returns null record will be ignored (if in a list) or will imported as null
     *
     * @param mixed $result_arr
     * @param false|array $params_arr
     *
     * @return mixed
     */
    public function post_processing_from_outside_source( $result_arr, $params_arr = false )
    {
        return $result_arr;
    }

    /**
     * Override this method if you want to post process values for values from inside source.
     * If this method returns null record will be ignored (if in a list) or will export as null
     *
     * @param mixed $result_arr
     * @param false|array $params_arr
     *
     * @return mixed
     */
    public function post_processing_from_inside_source( $result_arr, $params_arr = false )
    {
        return $result_arr;
    }

    /**
     * Override this method if data provided by this contract is linked with a model.
     * Also you will have to provide model flow parameters (if required) overriding PHS_Contract->get_parsing_data_model_flow().
     * Contract will use table_name and primary_key from the flow parameters.
     *
     * @return \phs\libraries\PHS_Model|false
     */
    public function get_parsing_data_model()
    {
        return false;
    }

    /**
     * Override this method if data provided by this contract is linked with a model.
     * This method will have to provide model flow parameters (if required).
     * Contract will use table_name and primary_key from the flow parameters.
     *
     * @return array|false
     */
    public function get_parsing_data_model_flow()
    {
        return false;
    }

    /**
     * @return string
     */
    final public function instance_type()
    {
        return self::INSTANCE_TYPE_CONTRACT;
    }

    public function max_recursive_level_for_data_parsing( $lvl = false )
    {
        if( $lvl === false )
            return $this->_recursive_lvl;

        $this->_recursive_lvl = (int)$lvl;
        return $this->_recursive_lvl;
    }

    private function _reset_data()
    {
        $this->_source_data = [];
        $this->_resulting_data = [];
        $this->_data_cache = [];
        $this->_data_type = false;
        $this->_data_was_parsed = false;
    }

    /**
     * @param array $outside_data Source array received from outside which should be converted into inside data
     * @param array|bool $params Functionality parameters
     *
     * @return array|bool
     */
    public function parse_data_from_outside_source( $outside_data, $params = false )
    {
        $this->reset_error();
        $this->_reset_data();

        if( !$this->_make_sure_we_have_definition() )
            return false;

        if( empty( $outside_data ) || !is_array( $outside_data ) )
            $outside_data = [];
        if( empty( $params ) || !is_array( $params ) )
            $params = [];

        if( empty( $params['force_import_if_not_found'] ) )
            $params['force_import_if_not_found'] = false;
        else
            $params['force_import_if_not_found'] = true;

        if( empty( $params['pre_processing_params'] ) || !is_array( $params['pre_processing_params'] ) )
            $params['pre_processing_params'] = false;
        if( empty( $params['post_processing_params'] ) || !is_array( $params['post_processing_params'] ) )
            $params['post_processing_params'] = false;

        $this->_data_type = self::FROM_OUTSIDE;
        $this->_source_data = $outside_data;

        $parsing_params = [];
        $parsing_params['lvl'] = 0;
        $parsing_params['lvl_contract'] = $this;
        $parsing_params['force_import_if_not_found'] = $params['force_import_if_not_found'];
        $parsing_params['pre_processing_params'] = $params['pre_processing_params'];
        $parsing_params['post_processing_params'] = $params['post_processing_params'];

        if( null === ($this->_resulting_data = $this->_parse_data_from_outside_source( $this->_definition_arr, $outside_data, $parsing_params )) )
        {
            $this->_resulting_data = [];

            if( !$this->has_error() )
                $this->set_error( self::ERR_PARAMETERS, self::_t( 'Error while parsing data from outside source.' ) );

            return false;
        }

        $this->_data_was_parsed = true;

        return $this->_resulting_data;
    }

    /**
     * @param array $definition_arr
     * @param array $outside_data
     * @param array $params
     *
     * @return array|bool|null array with result, false if we reached a leaf or null on error...
     */
    private function _parse_data_from_outside_source( $definition_arr, $outside_data, $params )
    {
        if( empty( $definition_arr ) || !is_array( $definition_arr )
         || empty( $outside_data ) || !is_array( $outside_data ) )
        {
            if( 0 === $params['lvl'] )
                return [];

            return false;
        }

        // Check if we reached maximum number of recursive calls
        if( $params['lvl'] > $this->max_recursive_level_for_data_parsing() )
            return null;

        // What contract did provide these nodes?
        if( empty( $params['lvl_contract'] ) )
            $params['lvl_contract'] = null;

        if( empty( $params['pre_processing_params'] ) || !is_array( $params['pre_processing_params'] ) )
            $params['pre_processing_params'] = false;
        if( empty( $params['post_processing_params'] ) || !is_array( $params['post_processing_params'] ) )
            $params['post_processing_params'] = false;

        /** @var \phs\libraries\PHS_Contract $lvl_contract */
        if( ($lvl_contract = $params['lvl_contract'])
         && null !== ($new_outside_data = $lvl_contract->pre_processing_from_outside_source( $outside_data, $params['pre_processing_params'] )) )
            $outside_data = $new_outside_data;

        $return_arr = [];
        foreach( $definition_arr as $node_key => $node_arr )
        {
            if( $node_arr['key_type'] === self::FROM_INSIDE )
                continue;

            // Make sure that we have a data to process
           if( !array_key_exists( $node_arr['outside_key'], $outside_data ) )
            {
                if( !empty( $node_arr['import_if_not_found'] )
                 || !empty( $params['force_import_if_not_found'] ) )
                    $return_arr[$node_arr['inside_key']] = $node_arr['default_inside'];

                continue;
            }

            // Check if we have recurring node...
            if( !empty( $node_arr['recurring_node'] ) )
            {
                $return_arr[$node_arr['inside_key']] = [];

                if( !is_array( $outside_data[$node_arr['outside_key']] ) )
                    continue;

                $rec_params = $params;
                $rec_params['lvl_contract'] = (!empty( $node_arr['nodes_from_contract'] )?$node_arr['nodes_from_contract']:null);
                $rec_params['lvl']++;

                $recurring_items_no = 0;
                foreach( $outside_data[$node_arr['outside_key']] as $knti => $outside_item )
                {
                    if( !empty( $node_arr['recurring_scalar_node'] ) )
                    {
                        // Recurring scalar value...
                        if( null === ($result_item = PHS_Params::set_type( $outside_item, $node_arr['type'], (!empty( $node_arr['type_extra'] )?$node_arr['type_extra']:false) )) )
                            continue;
                    } elseif( false === ($result_item = $this->_parse_data_from_outside_source( $node_arr['nodes'], $outside_item, $rec_params )) )
                        continue;

                    // Check if we have data post processing to do
                    /** @var \phs\libraries\PHS_Contract $contract_obj */
                    if( $result_item !== null
                     && ($contract_obj = $node_arr['nodes_from_contract']) )
                    {
                        // If post processing returns null, we ignore this record
                        if( null === ($result_item = $contract_obj->post_processing_from_outside_source( $result_item, $params['post_processing_params'] )) )
                            continue;
                    }

                    $recurring_items_no++;

                    $inside_knti = PHS_Params::set_type( $knti, $node_arr['recurring_key_type'],
                        (!empty( $node_arr['recurring_key_type_extra'] )?$node_arr['recurring_key_type_extra']:false) );

                    $return_arr[$node_arr['inside_key']][$inside_knti] = $result_item;

                    if( !empty( $node_arr['recurring_max_items'] )
                     && $recurring_items_no >= $node_arr['recurring_max_items'] )
                        break;
                }

                continue;
            }

            // This is not a recurring node, but it is an "object" (has nodes definition inside)
            if( !empty( $node_arr['nodes'] ) && is_array( $node_arr['nodes'] ) )
            {
                if( !is_array( $outside_data[$node_arr['outside_key']] ) )
                    continue;

                $return_arr[$node_arr['inside_key']] = [];

                $rec_params = $params;
                $rec_params['lvl_contract'] = (!empty( $node_arr['nodes_from_contract'] )?$node_arr['nodes_from_contract']:null);
                $rec_params['lvl']++;

                if( false === ($result_item = $this->_parse_data_from_outside_source( $node_arr['nodes'], $outside_data[$node_arr['outside_key']], $rec_params )) )
                    continue;

                // Check if we have data post processing to do
                /** @var \phs\libraries\PHS_Contract $contract_obj */
                if( $result_item !== null
                 && ($contract_obj = $node_arr['nodes_from_contract']) )
                {
                    // If post processing returns null, we ignore this record
                    if( null === ($result_item = $contract_obj->post_processing_from_outside_source( $result_item, $params['post_processing_params'] )) )
                        continue;
                }

                $return_arr[$node_arr['inside_key']] = $result_item;

                continue;
            }

            // Scalar value...
            $return_arr[$node_arr['inside_key']] = PHS_Params::set_type( $outside_data[$node_arr['outside_key']], $node_arr['type'],
                    (!empty( $node_arr['type_extra'] )?$node_arr['type_extra']:false) );
        }

        // Post-process for "root" object
        if( null === ($return_arr = $this->post_processing_from_outside_source( $return_arr, $params['post_processing_params'] )) )
            return false;

        return $return_arr;
    }

    /**
     * In order to reduce number of database queries, we will cache any records obtained as result of matching
     * primary keys in current contract recurring level. This method caches any records obtained outside contract
     * scope by providing node key - records arrays pairs. (eg. 'user' => $user_arr, where user node refers to user_id key in
     * current contract nodes definition)
     *
     * @param array $data_arr Data to be cached
     *
     * @return bool
     */
    protected function _set_initial_cache_data( $data_arr )
    {
        if( empty( $data_arr ) | !is_array( $data_arr ) )
            return false;

        foreach( $this->_definition_arr as $node_key => $node_arr )
        {
            /** @var \phs\libraries\PHS_Model $model_obj */
            if( empty( $data_arr[$node_key] )
             || empty( $node_arr['nodes'] )  || !is_array( $node_arr['nodes'] )
             || empty( $node_arr['data_model_obj'] )
             || !(($model_obj = $node_arr['data_model_obj']) instanceof PHS_Model)
             || !($flow_arr = $model_obj->fetch_default_flow_params( (!empty( $node_arr['data_flow_arr'] )?$node_arr['data_flow_arr']:false) ))
             || empty( $data_arr[$node_key][$flow_arr['table_index']] )
             || !($model_id = $model_obj->instance_id()) )
                continue;

            if( empty( $this->_data_cache[$model_id] ) )
                $this->_data_cache[$model_id] = [];
            if( empty( $this->_data_cache[$model_id][$flow_arr['table_name']] ) )
                $this->_data_cache[$model_id][$flow_arr['table_name']] = [];

            $this->_data_cache[$model_id][$flow_arr['table_name']][(int)$data_arr[$node_key][$flow_arr['table_index']]] = $data_arr[$node_key];
        }

        return true;
    }

    private function _non_nodes_related_cache_data_keys()
    {
        return [
            // Instance of model to be used to obtain data
            'data_model_obj' => false,
            // flow to be passed to PHS_Model::get_details() method (obtained with PHS_Model::fetch_default_flow_params() method)
            'data_flow_arr' => false,
            // Data to be added to cache as an array of records (key is ignored, but record array should contain a model flow 'table_index' key)
            'data_cache' => false,
        ];
    }

    /**
     * If sub-nodes are known to use data which can be provided in an array with finite values (eg. categories which can be up to 100, etc)
     * and which are not related necessary to a node in current contract definition, you can use this method to add data to cache in order
     * to limit number of queries to database
     *
     * @param \phs\libraries\PHS_Model $model_obj
     * @param array $data_arr
     * @param bool|array $flow_arr
     *
     * @return bool
     */
    public function add_data_to_cache( $model_obj, $data_arr, $flow_arr = false )
    {
        /** @var \phs\libraries\PHS_Model $model_obj */
        if( empty( $model_obj )
         || empty( $data_arr ) || !is_array( $data_arr )
         || !($model_obj instanceof PHS_Model)
         || !($flow_arr = $model_obj->fetch_default_flow_params( $flow_arr ))
         || !($table_name = $flow_arr['table_name'])
         || !($primary_key = $flow_arr['table_index'])
         || !($model_id = $model_obj->instance_id())
          )
            return false;

        foreach( $data_arr as $cache_data_arr )
        {
            if( empty( $cache_data_arr ) || !is_array( $cache_data_arr )
             || empty( $cache_data_arr[$primary_key] ) )
                continue;

            $this->_data_cache[$model_id][$table_name][$cache_data_arr[$primary_key]] = $cache_data_arr;
        }

        return true;
    }

    /**
     * Given a node definition and data to be parsed for current contract recurrence level, check if we have cached any data
     *
     * @param array $node_arr Node definition for which we check cache
     * @param array $inside_data Data provided for current contract recurrence level
     *
     * @return false|array
     */
    protected function _get_cache_data_for_node( $node_arr, $inside_data )
    {
        /** @var \phs\libraries\PHS_Model $model_obj */
        if( empty( $node_arr ) || !is_array( $node_arr )
         || empty( $node_arr['nodes'] ) || !is_array( $node_arr['nodes'] )
         || empty( $node_arr['data_primary_key'] )
         || empty( $node_arr['data_model_obj'] )
         || !isset( $inside_data[$node_arr['data_primary_key']] )
         || !($primary_key = (int)$inside_data[$node_arr['data_primary_key']])
         || !(($model_obj = $node_arr['data_model_obj']) instanceof PHS_Model)
         || !($flow_arr = $model_obj->fetch_default_flow_params( (!empty( $node_arr['data_flow_arr'] )?$node_arr['data_flow_arr']:false) ))
         || !($model_id = $model_obj->instance_id())
         || empty( $this->_data_cache[$model_id] )
         || !is_array( $this->_data_cache[$model_id] )
         || empty( $this->_data_cache[$model_id][$flow_arr['table_name']] )
         || !is_array( $this->_data_cache[$model_id][$flow_arr['table_name']] )
         || empty( $this->_data_cache[$model_id][$flow_arr['table_name']][$primary_key] )
        )
            return false;

        return $this->_data_cache[$model_id][$flow_arr['table_name']][$primary_key];
    }

    /**
     * Given a node definition and data to be parsed for current contract recurrence level, check if we have cached any data
     *
     * @param array $node_arr Node definition for which we check cache
     * @param array $data_arr Data to be cached
     *
     * @return bool
     */
    protected function _set_cache_data_for_node( $node_arr, $data_arr )
    {
        /** @var \phs\libraries\PHS_Model $model_obj */
        if( empty( $node_arr ) | !is_array( $node_arr )
         || empty( $node_arr['nodes'] )  || !is_array( $node_arr['nodes'] )
         || empty( $node_arr['data_primary_key'] )
         || empty( $node_arr['data_model_obj'] )
         || !(($model_obj = $node_arr['data_model_obj']) instanceof PHS_Model)
         || !($flow_arr = $model_obj->fetch_default_flow_params( (!empty( $node_arr['data_flow_arr'] )?$node_arr['data_flow_arr']:false) ))
         || !isset( $data_arr[$flow_arr['table_index']] )
         || !($primary_key = (int)$data_arr[$flow_arr['table_index']])
         || !($model_id = $model_obj->instance_id())
        )
            return false;

        if( empty( $this->_data_cache[$model_id] ) )
            $this->_data_cache[$model_id] = [];
        if( empty( $this->_data_cache[$model_id][$flow_arr['table_name']] ) )
            $this->_data_cache[$model_id][$flow_arr['table_name']] = [];

        $this->_data_cache[$model_id][$flow_arr['table_name']][$primary_key] = $data_arr;

        return true;
    }

    /**
     * @param array $inside_data Source array received from inside which should be converted into outside data
     * @param array|bool $params Functionality parameters
     *
     * @return array|bool
     */
    public function parse_data_from_inside_source( $inside_data, $params = false )
    {
        $this->reset_error();
        $this->_reset_data();

        if( !$this->_make_sure_we_have_definition() )
            return false;

        if( empty( $inside_data ) || !is_array( $inside_data ) )
            $inside_data = [];
        if( empty( $params ) || !is_array( $params ) )
            $params = [];

        if( empty( $params['data_cache'] ) || !is_array( $params['data_cache'] ) )
            $params['data_cache'] = false;

        if( empty( $params['force_export_if_not_found'] ) )
            $params['force_export_if_not_found'] = false;
        else
            $params['force_export_if_not_found'] = true;

        if( empty( $params['pre_processing_params'] ) || !is_array( $params['pre_processing_params'] ) )
            $params['pre_processing_params'] = false;
        if( empty( $params['post_processing_params'] ) || !is_array( $params['post_processing_params'] ) )
            $params['post_processing_params'] = false;

        if( empty( $params['max_data_recursive_lvl'] ) )
            $params['max_data_recursive_lvl'] = 0;
        else
            $params['max_data_recursive_lvl'] = (int)$params['max_data_recursive_lvl'];

        $this->_data_type = self::FROM_INSIDE;
        $this->_source_data = $inside_data;

        if( !empty( $params['data_cache'] ) )
            $this->_set_initial_cache_data( $params['data_cache'] );

        $parsing_params = [];
        $parsing_params['lvl'] = 0;
        $parsing_params['lvl_contract'] = $this;
        $parsing_params['force_export_if_not_found'] = $params['force_export_if_not_found'];
        $parsing_params['max_data_recursive_lvl'] = $params['max_data_recursive_lvl'];
        $parsing_params['pre_processing_params'] = $params['pre_processing_params'];
        $parsing_params['post_processing_params'] = $params['post_processing_params'];

        if( null === ($this->_resulting_data = $this->_parse_data_from_inside_source( $this->_definition_arr, $inside_data, $parsing_params )) )
        {
            $this->_resulting_data = [];

            if( !$this->has_error() )
                $this->set_error( self::ERR_PARAMETERS, self::_t( 'Error while parsing data from inside source.' ) );

            return false;
        }

        $this->_data_was_parsed = true;

        return $this->_resulting_data;
    }

    /**
     * @param array $definition_arr
     * @param array $inside_data
     * @param array $params
     *
     * @return array|bool|null array with result, false if we reached a leaf or null on error...
     */
    private function _parse_data_from_inside_source( $definition_arr, $inside_data, $params )
    {
        if( empty( $definition_arr ) || !is_array( $definition_arr )
         || empty( $inside_data ) || !is_array( $inside_data ) )
        {
            if( 0 === $params['lvl'] )
                return [];

            return false;
        }

        // Check if we reached maximum number of recursive calls
        if( $params['lvl'] > $this->max_recursive_level_for_data_parsing() )
            return null;

        // What contract did provide these nodes?
        if( empty( $params['lvl_contract'] ) )
            $params['lvl_contract'] = null;

        if( empty( $params['pre_processing_params'] ) || !is_array( $params['pre_processing_params'] ) )
            $params['pre_processing_params'] = false;
        if( empty( $params['post_processing_params'] ) || !is_array( $params['post_processing_params'] ) )
            $params['post_processing_params'] = false;

        /** @var \phs\libraries\PHS_Contract $lvl_contract */
        if( ($lvl_contract = $params['lvl_contract'])
         && null !== ($new_inside_data = $lvl_contract->pre_processing_from_inside_source( $inside_data, $params['pre_processing_params'] )) )
            $inside_data = $new_inside_data;

        $return_arr = [];
        foreach( $definition_arr as $node_key => $node_arr )
        {
            if( $node_arr['key_type'] === self::FROM_OUTSIDE )
                continue;

            // Check if we have data to process for current node
            if( !array_key_exists( $node_arr['inside_key'], $inside_data ) )
            {
                if( empty( $node_arr['max_data_recursive_lvl'] ) )
                    $max_data_recursive_lvl = $params['max_data_recursive_lvl'];
                elseif( empty( $params['max_data_recursive_lvl'] ) )
                    $max_data_recursive_lvl = $node_arr['max_data_recursive_lvl'];
                else
                    // Node recurrence is calculated from level of node...
                    $max_data_recursive_lvl = min( $params['max_data_recursive_lvl'], $node_arr['max_data_recursive_lvl'] + $params['lvl'] );

                // This should be an "object", but we are provided no data for it,
                // check if we have an associated model from where we can take the data
                /** @var \phs\libraries\PHS_Model $model_obj */
                if( !empty( $node_arr['nodes'] ) && is_array( $node_arr['nodes'] )
                 && !empty( $node_arr['data_model_obj'] )
                 && !empty( $node_arr['data_primary_key'] )
                 && !empty( $inside_data[$node_arr['data_primary_key']] )
                 && (empty( $max_data_recursive_lvl ) || $max_data_recursive_lvl > $params['lvl']) )
                {
                    $db_record_arr = false;
                    // It seems we can get some data from model...
                    if( !($db_record_arr = $this->_get_cache_data_for_node( $node_arr, $inside_data )) )
                        $db_record_arr = false;

                    if( empty( $db_record_arr )
                     && (($model_obj = $node_arr['data_model_obj']) instanceof PHS_Model)
                     && ($flow_arr = $model_obj->fetch_default_flow_params( (!empty( $node_arr['data_flow_arr'] )?$node_arr['data_flow_arr']:false) ))
                     && ($db_record_arr = $model_obj->get_details( $inside_data[$node_arr['data_primary_key']], $flow_arr )) )
                        $this->_set_cache_data_for_node( $node_arr, $db_record_arr );

                    if( !empty( $db_record_arr ) )
                    {
                        $rec_params = $params;
                        $rec_params['max_data_recursive_lvl'] = $max_data_recursive_lvl;
                        $rec_params['lvl_contract'] = (!empty( $node_arr['nodes_from_contract'] )?$node_arr['nodes_from_contract']:null);
                        $rec_params['lvl']++;

                        if( false !== ($result_item = $this->_parse_data_from_inside_source( $node_arr['nodes'], $db_record_arr, $rec_params )) )
                        {
                            // Check if we have data post processing to do
                            /** @var \phs\libraries\PHS_Contract $contract_obj */
                            if( $result_item !== null
                             && ($contract_obj = $node_arr['nodes_from_contract']) )
                            {
                                // If post processing returns null, we ignore this record
                                if( null === ($result_item = $contract_obj->post_processing_from_inside_source( $result_item, $params['post_processing_params'] )) )
                                    continue;
                            }

                            $return_arr[$node_arr['outside_key']] = $result_item;
                            continue;
                        }
                    }
                }

                if( !empty( $node_arr['export_if_not_found'] )
                 || !empty( $params['force_export_if_not_found'] ) )
                    $return_arr[$node_arr['outside_key']] = $node_arr['default_outside'];

                continue;
            }

            // Check if we have recurring node...
            if( !empty( $node_arr['recurring_node'] ) )
            {
                $return_arr[$node_arr['outside_key']] = [];

                if( !is_array( $inside_data[$node_arr['inside_key']] ) )
                    continue;

                $rec_params = $params;
                $rec_params['lvl_contract'] = (!empty( $node_arr['nodes_from_contract'] )?$node_arr['nodes_from_contract']:null);
                $rec_params['lvl']++;

                $recurring_items_no = 0;
                foreach( $inside_data[$node_arr['inside_key']] as $knti => $input_item )
                {
                    if( !empty( $node_arr['recurring_scalar_node'] ) )
                    {
                        // Recurring scalar value...
                        if( null === ($result_item = PHS_Params::set_type( $input_item, $node_arr['type'], (!empty( $node_arr['type_extra'] )?$node_arr['type_extra']:false) )) )
                            continue;
                    } elseif( false === ($result_item = $this->_parse_data_from_inside_source( $node_arr['nodes'], $input_item, $rec_params )) )
                        continue;

                    // Check if we have data post processing to do
                    /** @var \phs\libraries\PHS_Contract $contract_obj */
                    if( $result_item !== null
                     && ($contract_obj = $node_arr['nodes_from_contract']) )
                    {
                        // If post processing returns null, we ignore this record
                        if( null === ($result_item = $contract_obj->post_processing_from_inside_source( $result_item, $params['post_processing_params'] )) )
                            continue;
                    }

                    $recurring_items_no++;

                    $inside_knti = PHS_Params::set_type( $knti, $node_arr['recurring_key_type'],
                        (!empty( $node_arr['recurring_key_type_extra'] )?$node_arr['recurring_key_type_extra']:false) );

                    $return_arr[$node_arr['outside_key']][$inside_knti] = $result_item;

                    if( !empty( $node_arr['recurring_max_items'] )
                     && $recurring_items_no >= $node_arr['recurring_max_items'] )
                        break;
                }

                continue;
            }

            // This is not a recurring node, but it is an "object" (has nodes definition inside)
            if( !empty( $node_arr['nodes'] ) && is_array( $node_arr['nodes'] ) )
            {
                if( !is_array( $inside_data[$node_arr['inside_key']] ) )
                    continue;

                $return_arr[$node_arr['outside_key']] = [];

                $rec_params = $params;
                $rec_params['lvl_contract'] = (!empty( $node_arr['nodes_from_contract'] )?$node_arr['nodes_from_contract']:null);
                $rec_params['lvl']++;

                if( false === ($result_item = $this->_parse_data_from_inside_source( $node_arr['nodes'], $inside_data[$node_arr['inside_key']], $rec_params )) )
                    continue;

                // Check if we have data post processing to do
                /** @var \phs\libraries\PHS_Contract $contract_obj */
                if( $result_item !== null
                 && ($contract_obj = $node_arr['nodes_from_contract']) )
                {
                    // If post processing returns null, we ignore this record
                    if( null === ($result_item = $contract_obj->post_processing_from_inside_source( $result_item, $params['post_processing_params'] )) )
                        continue;
                }

                $return_arr[$node_arr['outside_key']] = $result_item;

                continue;
            }

            // Scalar value...
            $return_arr[$node_arr['outside_key']] = PHS_Params::set_type( $inside_data[$node_arr['inside_key']], $node_arr['type'],
                    (!empty( $node_arr['type_extra'] )?$node_arr['type_extra']:false) );
        }

        // Post-process for "root" object
        if( null === ($return_arr = $this->post_processing_from_inside_source( $return_arr, $params['post_processing_params'] )) )
            return false;

        return $return_arr;
    }

    /**
     * @return bool
     */
    public function data_is_from_outside()
    {
        return ($this->_data_type === self::FROM_OUTSIDE);
    }

    /**
     * @return bool
     */
    public function data_is_from_inside()
    {
        return ($this->_data_type === self::FROM_INSIDE);
    }

    /**
     * @return bool
     */
    public function data_was_parsed()
    {
        return $this->_data_was_parsed;
    }

    /**
     * Returns an array of parsed data
     * @return array
     */
    public function get_resulting_data()
    {
        return $this->_resulting_data;
    }

    /**
     * Standard definition of a data node to be exported as response to a 3rd party request
     * @return array
     */
    protected static function _get_contract_node_definition()
    {
        return [
            // Exporting internal data to outside data (input -> output) $internal_source[$node['inside_key']] -> $export[$node['outside_key']]
            // Importing outside data to internal data (output -> input) $outside_source[$node['outside_key']] -> $import[$node['inside_key']]

            // When parsing data as input, what key should we check in source array
            // If not set, key which defined this node will be used
            'inside_key' => '',
            // When parsing data as output, what key should we check in source array
            // If not set, key which defined this node will be used
            'outside_key' => '',
            // Type of data to be exported (useful when exporting to type-oriented languages)
            'type' => PHS_Params::T_ASIS,
            // Extra parameters used in PHS_Params::set_type()
            'type_extra' => false,
            // If this is defined will be used for both default_inside and default_outside
            'default' => null,
            // Default value when transforming outside data to inside data
            'default_inside' => null,
            // Default value when transforming inside data to outside data
            'default_outside' => null,
            // false - don't import missing keys/indexes from input data array
            // true - import missing keys from input data array with default value
            'import_if_not_found' => true,
            // false - don't export missing keys/indexes from output data array
            // true - export missing keys from output data array with default value
            'export_if_not_found' => true,
            // Tells if current node should be accepted only as input, output or both
            'key_type' => self::FROM_BOTH,
            // Tells if this node definition repeats (used for lists)
            // If this is true, key from source will be used and structure that repeats is defined in nodes
            'recurring_node' => false,
            // If this is a recurring node, it might be an array of scalar values. If so, put this to true
            'recurring_scalar_node' => false,
            // Maximumum number of items to read from source array
            // Capped to 10000 to be sure we don't run out of memory. If you need more than 10000 of records,
            // just define the right number of records you want returned in your contract definition
            'recurring_max_items' => 10000,
            // What type should be set on each key of recurring data
            'recurring_key_type' => PHS_Params::T_ASIS,
            // Extra parameters used in PHS_Params::set_type()
            'recurring_key_type_extra' => false,

            // Try obtaining data from a model if we are provided no data from inside source
            // NOTE: This works only when parsing data from inside sources
            // Instance of model to be used to obtain data
            'data_model_obj' => false,
            // flow to be passed to PHS_Model::get_details() method (obtained with PHS_Model::fetch_default_flow_params() method)
            'data_flow_arr' => false,
            // Which key in inside data array contains primary index value to be used with PHS_Model::get_details() method
            // when retrieving data from model
            'data_primary_key' => false,

            // If a contract class is provided here 'nodes' will be taken from ${'nodes_from_contract'}->get_contract_data_definition()
            // Also contract can provide 'data_model_obj' and 'data_flow_arr' from abstract methods
            'nodes_from_contract' => false,

            // Maximum number of recursive calls from this node forward 0 - no limit
            'max_data_recursive_lvl' => 0,

            // (OPTIONAL) Descriptive info about this node (can generate documentation based on this
            'title' => '',
            'description' => '',

            // If this node has children (subnodes), add their definition here...
            'nodes' => false,
        ];
    }

    protected function _make_sure_we_have_definition()
    {
        if( $this->_definition_initialized )
            return true;

        if( !($definition_arr = $this->_normalize_definition_of_nodes()) )
            return false;

        $this->_definition_arr = $definition_arr;
        $this->_definition_initialized = true;

        return true;
    }

    /**
     * Normalize an array with definitions of data nodes to be used in this contract
     * @param array|bool $definition_arr Definition of nodes of current node or false to normalize all definition
     * @return bool|array Normalized definition array, false if we had errors
     */
    protected function _normalize_definition_of_nodes( $definition_arr = false )
    {
        if( $definition_arr === false )
        {
            if( !($definition_arr = $this->get_contract_data_definition())
             || !is_array( $definition_arr ) )
            {
                if( !$this->has_error() )
                    $this->set_error( self::ERR_PARAMETERS, self::_t( 'get_contract_data_definition() method should return an array.' ) );

                return false;
            }
        }

        if( empty( $definition_arr ) || !is_array( $definition_arr ) )
        {
            $this->set_error( self::ERR_PARAMETERS, self::_t( 'Provided node definition is not an array.' ) );
            return false;
        }

        $node_definition = self::_get_contract_node_definition();
        $return_arr = [];
        foreach( $definition_arr as $int_key => $node_arr )
        {
            if( empty( $node_arr ) || !is_array( $node_arr ) )
                continue;

            if( !isset( $node_arr['inside_key'] )
             || (string)$node_arr['inside_key'] === ''
             || !is_scalar( $node_arr['inside_key'] ) )
                $node_arr['inside_key'] = $int_key;

            if( !isset( $node_arr['outside_key'] )
             || (string)$node_arr['outside_key'] === ''
             || !is_scalar( $node_arr['outside_key'] ) )
                $node_arr['outside_key'] = $int_key;

            if( array_key_exists( 'default', $node_arr ) )
            {
                $node_arr['default_inside'] = $node_arr['default'];
                $node_arr['default_outside'] = $node_arr['default'];
            }

            if( !isset( $node_arr['key_type'] )
             || !$this->valid_data_key( $node_arr['key_type'] ) )
                $node_arr['key_type'] = self::FROM_BOTH;

            $node_arr = self::validate_array( $node_arr, $node_definition );

            /** @var \phs\libraries\PHS_Contract $contract_obj */
            if( ($contract_obj = $node_arr['nodes_from_contract'])
             && !($contract_obj instanceof PHS_Contract) )
            {
                $this->set_error( self::ERR_PARAMETERS, self::_t( 'Node %s in contract definition doesn\'t provide a valid contract.', $int_key ) );
                return false;
            }

            if( !empty( $contract_obj )
             && !($node_arr['nodes'] = $contract_obj->get_contract_data_definition()) )
                $node_arr['nodes'] = false;

            /** @var \phs\libraries\PHS_Model $model_obj */
            $model_obj = null;
            $model_flow_arr = false;
            if( !empty( $node_arr['data_model_obj'] ) )
            {
                $model_obj = $node_arr['data_model_obj'];
                if( !($model_obj instanceof PHS_Model) )
                {
                    $this->set_error( self::ERR_PARAMETERS, self::_t( 'Node %s in contract definition doesn\'t provide a valid data parsing model.', $int_key ) );
                    return false;
                }
            }

            if( !empty( $node_arr['data_flow_arr'] )
             && is_array( $node_arr['data_flow_arr'] ) )
                $model_flow_arr = $node_arr['data_flow_arr'];

            if( !empty( $contract_obj ) )
            {
                if( $model_obj === null
                 && ($model_obj = $contract_obj->get_parsing_data_model())
                 && !($model_obj instanceof PHS_Model) )
                {
                    $this->set_error( self::ERR_PARAMETERS, self::_t( 'Node %s in contract definition doesn\'t provide a valid data parsing model.', $int_key ) );
                    return false;
                }

                if( empty( $model_flow_arr )
                 && (!($model_flow_arr = $contract_obj->get_parsing_data_model_flow())
                      || !is_array( $model_flow_arr )
                    ) )
                    $model_flow_arr = false;

                $node_arr['data_model_obj'] = $model_obj;
                $node_arr['data_flow_arr'] = $model_flow_arr;
            }

            if( !empty( $node_arr['recurring_node'] )
             && empty( $node_arr['recurring_scalar_node'] )
             && (empty( $node_arr['nodes'] ) || !is_array( $node_arr['nodes'] )) )
            {
                $this->set_error( self::ERR_PARAMETERS, self::_t( 'Node %s in contract definition is set as recurring, '.
                                                                  'but has no nodes defined as array.', $int_key ) );
                return false;
            }

            if( !empty( $node_arr['nodes'] ) && is_array( $node_arr['nodes'] )
             && false === ($node_arr['nodes'] = $this->_normalize_definition_of_nodes( $node_arr['nodes'] )) )
                return false;

            $return_arr[$int_key] = $node_arr;
        }

        return $return_arr;
    }

    /**
     * @param bool|string $lang
     *
     * @return array
     */
    public function get_data_keys( $lang = false )
    {
        static $keys_arr = [];

        if( $lang === false
         && !empty( $keys_arr ) )
            return $keys_arr;

        $result_arr = $this->translate_array_keys( self::$KEYS_ARR, [ 'title' ], $lang );

        if( $lang === false )
            $keys_arr = $result_arr;

        return $result_arr;
    }

    /**
     * @param bool|string $lang
     *
     * @return array
     */
    public function get_data_keys_as_key_val( $lang = false )
    {
        static $data_keys_key_val_arr = false;

        if( $lang === false
         && $data_keys_key_val_arr !== false )
            return $data_keys_key_val_arr;

        $key_val_arr = [];
        if( ($data_keys = $this->get_data_keys( $lang )) )
        {
            foreach( $data_keys as $key => $val )
            {
                if( !is_array( $val ) )
                    continue;

                $key_val_arr[$key] = $val['title'];
            }
        }

        if( $lang === false )
            $data_keys_key_val_arr = $key_val_arr;

        return $key_val_arr;
    }

    /**
     * @param int $data_key
     * @param bool|string $lang
     *
     * @return bool|array
     */
    public function valid_data_key( $data_key, $lang = false )
    {
        $all_data_keys = $this->get_data_keys( $lang );
        if( empty( $data_key )
         || !isset( $all_data_keys[$data_key] ) )
            return false;

        return $all_data_keys[$data_key];
    }
}
