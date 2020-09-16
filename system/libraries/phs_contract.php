<?php
namespace phs\libraries;

abstract class PHS_Contract extends PHS_Instantiable
{
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

    /** @var bool Was defintion normalized already? */
    private $_definition_initialized = false;

    /**
     * Returns an array with data nodes definition
     * @return array|bool
     * @see \phs\libraries\PHS_Contract::_get_contract_node_definition()
     */
    abstract public function get_contract_data_definition();

    /**
     * @return string
     */
    final public function instance_type()
    {
        return self::INSTANCE_TYPE_CONTRACT;
    }

    private function _reset_data()
    {
        $this->_source_data = [];
        $this->_resulting_data = [];
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

        $this->_data_type = self::FROM_OUTSIDE;
        $this->_source_data = $outside_data;

        $parsing_params = [];
        $parsing_params['lvl'] = 0;
        $parsing_params['force_import_if_not_found'] = $params['force_import_if_not_found'];

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
                $rec_params['lvl']++;

                $recurring_items_no = 0;
                foreach( $outside_data[$node_arr['outside_key']] as $knti => $outside_item )
                {
                    if( false === ($result_item = $this->_parse_data_from_outside_source( $node_arr['nodes'], $outside_item, $rec_params )) )
                        continue;

                    $recurring_items_no++;

                    $inside_knti = PHS_params::set_type( $knti, $node_arr['recurring_key_type'],
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
                $rec_params['lvl']++;

                if( false === ($result_item = $this->_parse_data_from_inside_source( $node_arr['nodes'], $outside_data[$node_arr['outside_key']], $rec_params )) )
                    continue;

                $return_arr[$node_arr['inside_key']] = $result_item;

                continue;
            }

            // Scalar value...
            $return_arr[$node_arr['inside_key']] = PHS_params::set_type( $outside_data[$node_arr['outside_key']], $node_arr['type'],
                    (!empty( $node_arr['type_extra'] )?$node_arr['type_extra']:false) );
        }

        return $return_arr;
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

        if( empty( $params['force_export_if_not_found'] ) )
            $params['force_export_if_not_found'] = false;
        else
            $params['force_export_if_not_found'] = true;

        $this->_data_type = self::FROM_INSIDE;
        $this->_source_data = $inside_data;

        $parsing_params = [];
        $parsing_params['lvl'] = 0;
        $parsing_params['force_export_if_not_found'] = $params['force_export_if_not_found'];

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

        $return_arr = [];
        foreach( $definition_arr as $node_key => $node_arr )
        {
            if( $node_arr['key_type'] === self::FROM_OUTSIDE )
                continue;

            // Make sure that we have a data to process
            if( !array_key_exists( $node_arr['inside_key'], $inside_data ) )
            {
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
                $rec_params['lvl']++;

                $recurring_items_no = 0;
                foreach( $inside_data[$node_arr['inside_key']] as $knti => $input_item )
                {
                    if( false === ($result_item = $this->_parse_data_from_inside_source( $node_arr['nodes'], $input_item, $rec_params )) )
                        continue;

                    $recurring_items_no++;

                    $inside_knti = PHS_params::set_type( $knti, $node_arr['recurring_key_type'],
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
                $rec_params['lvl']++;

                if( false === ($result_item = $this->_parse_data_from_inside_source( $node_arr['nodes'], $inside_data[$node_arr['inside_key']], $rec_params )) )
                    continue;

                $return_arr[$node_arr['outside_key']] = $result_item;

                continue;
            }

            // Scalar value...
            $return_arr[$node_arr['outside_key']] = PHS_params::set_type( $inside_data[$node_arr['inside_key']], $node_arr['type'],
                    (!empty( $node_arr['type_extra'] )?$node_arr['type_extra']:false) );
        }

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
            'type' => PHS_params::T_ASIS,
            // Extra parameters used in PHS_params::set_type()
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
            // Maximumum number of items to read from source array
            // Capped to 10000 to be sure we don't run out of memory. If you need more than 10000 of records,
            // just define the right number of records you want returned in your contract definition
            'recurring_max_items' => 10000,
            // What type should be set on each key of recurring data
            'recurring_key_type' => PHS_params::T_ASIS,
            // Extra parameters used in PHS_params::set_type()
            'recurring_key_type_extra' => false,

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

            if( !empty( $node_arr['recurring_node'] )
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
