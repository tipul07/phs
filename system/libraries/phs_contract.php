<?php
namespace phs\libraries;

abstract class PHS_Contract extends PHS_Instantiable
{
    // hardcoded maximum recursive calls when parsing data
    /** @see PHS_Contract::max_recursive_level_for_data_parsing() */
    public const MAX_RECURSIVE_PARSING = 15;

    public const FROM_OUTSIDE = 1, FROM_INSIDE = 2, FROM_BOTH = 3;

    /** @var array Array of data that was parsed */
    private array $_source_data = [];

    /** @var array After parsing data this is the resulting array */
    private array $_resulting_data = [];

    private ?int $_data_type = null;

    private bool $_data_was_parsed = false;

    /** @var array Normalized nodes definition */
    private array $_definition_arr = [];

    /** @var array Data cache for data retrieved from models for inside sources */
    private array $_data_cache = [];

    /** @var null|array Data being processed now (from inside or from outside) */
    private ?array $_processing_data = null;

    /** @var bool Was defintion normalized already? */
    private bool $_definition_initialized = false;

    /** @var int Maximum number of recursive calls when parsing contract data */
    private int $_recursive_lvl = self::MAX_RECURSIVE_PARSING;

    protected static array $KEYS_ARR = [
        self::FROM_OUTSIDE => ['title' => 'From Outside'],
        self::FROM_INSIDE  => ['title' => 'From Inside'],
        self::FROM_BOTH    => ['title' => 'From Both'],
    ];

    /**
     * Returns an array with data nodes definition
     * @return null|array
     * @see \phs\libraries\PHS_Contract::_get_contract_node_definition()
     */
    abstract public function get_contract_data_definition() : ?array;

    /**
     * Override this method if you want to pre process data which will be processed from outside source
     * If this method returns null, outside source data will not be changed
     *
     * @param array $outside_data_arr
     * @param array $params_arr
     * @param array $extra_arr
     *
     * @return null|bool|array array means new data to be used
     *                         array means new data to be used
     *                         false means we ignore the node from result
     *                         null with error means we have an error and should propagate it
     *                         null without error means we will put null on this node
     */
    public function pre_processing_from_outside_source(array $outside_data_arr, array $params_arr = [], array $extra_arr = []) : null | bool | array
    {
        return $outside_data_arr;
    }

    /**
     * Override this method if you want to pre process data which will be processed from inside source
     * If this method returns null, inside source data will not be changed
     *
     * @param array $inside_data_arr
     * @param array $params_arr
     * @param array $extra_arr
     *
     * @return null|array|false
     *                          array means new data to be used
     *                          false means we ignore the node from result
     *                          null with error means we have an error and should propagate it
     *                          null without error means we will put null on this node
     */
    public function pre_processing_from_inside_source(array $inside_data_arr, array $params_arr = [], array $extra_arr = []) : null | bool | array
    {
        return $inside_data_arr;
    }

    /**
     * Override this method if you want to post process values for values from outside source
     * If this method returns null record will be ignored (if in a list) or will be imported as null
     *
     * @param mixed $result_arr
     * @param array $params_arr
     * @param array $extra_arr
     *
     * @return mixed
     *               array means new data to be used
     *               null with error means we have an error and should propagate it
     *               null without error means we will put null or default value on this node
     */
    public function post_processing_from_outside_source(mixed $result_arr, array $params_arr = [], array $extra_arr = []) : mixed
    {
        return $result_arr;
    }

    /**
     * Override this method if you want to post process values for values from inside source.
     * If this method returns null record will be ignored (if in a list) or will export as null
     *
     * @param mixed $result_arr
     * @param array $params_arr
     * @param array $extra_arr
     *
     * @return mixed
     *               array means new data to be used
     *               null with error means we have an error and should propagate it
     *               null without error means we will put null or default value on this node
     */
    public function post_processing_from_inside_source(mixed $result_arr, array $params_arr = [], array $extra_arr = []) : mixed
    {
        return $result_arr;
    }

    /**
     * Override this method if data provided by this contract is linked with a model.
     * Also, you will have to provide model flow parameters (if required) overriding PHS_Contract->get_parsing_data_model_flow().
     * Contract will use table_name and primary_key from the flow parameters.
     *
     * @return null|PHS_Model
     */
    public function get_parsing_data_model() : ?PHS_Model
    {
        return null;
    }

    /**
     * Override this method if data provided by this contract is linked with a model.
     * This method will have to provide model flow parameters (if required).
     * Contract will use table_name and primary_key from the flow parameters.
     *
     * @return null|array
     */
    public function get_parsing_data_model_flow() : ?array
    {
        return null;
    }

    final public function instance_type() : string
    {
        return self::INSTANCE_TYPE_CONTRACT;
    }

    public function max_recursive_level_for_data_parsing(?int $lvl = null) : int
    {
        if ($lvl === null) {
            return $this->_recursive_lvl;
        }

        $this->_recursive_lvl = $lvl;

        return $this->_recursive_lvl;
    }

    /**
     * Set an error inside _pre or _post methods and stop processing.
     *
     * @param int $error_no
     * @param string $error_msg
     *
     * @return null|array This is actually returning only null, but we want to keep PHP 8.1 compatibility
     */
    public function set_processing_error(int $error_no, string $error_msg) : ?array
    {
        $this->set_error($error_no, $error_msg);

        return null;
    }

    /**
     * Get source data (array) as provided from inside or from outside.
     * self::$_source_data is not changed by _pre and _post methods, so it is the "original data"
     * @return null|array
     */
    public function get_source_data() : ?array
    {
        return $this->_source_data;
    }

    /**
     * Get current processing data as array with details at current level in contract.
     * This will also be changed by _pre and _post method calls
     * @return null|array
     */
    public function get_processing_data() : ?array
    {
        return $this->_processing_data;
    }

    /**
     * @param null|array $outside_data Source array received from outside which should be converted into inside data
     * @param array $params Functionality parameters
     *
     * @return array|bool
     */
    public function parse_data_from_outside_source(?array $outside_data = null, array $params = []) : ?array
    {
        $this->reset_error();
        $this->_reset_data();

        if (!$this->_make_sure_we_have_definition()) {
            return false;
        }

        $outside_data ??= [];

        $params['force_import_if_not_found'] = !empty($params['force_import_if_not_found']);

        if (empty($params['pre_processing_params']) || !is_array($params['pre_processing_params'])) {
            $params['pre_processing_params'] = [];
        }
        if (empty($params['post_processing_params']) || !is_array($params['post_processing_params'])) {
            $params['post_processing_params'] = [];
        }

        $this->_data_type = self::FROM_OUTSIDE;
        $this->_source_data = $this->_processing_data = $outside_data;

        $parsing_params = [];
        $parsing_params['lvl'] = 0;
        $parsing_params['lvl_contract'] = $this;
        $parsing_params['force_import_if_not_found'] = $params['force_import_if_not_found'];
        $parsing_params['pre_processing_params'] = $params['pre_processing_params'];
        $parsing_params['post_processing_params'] = $params['post_processing_params'];

        $this->_resulting_data = [];
        if (null === ($_resulting_data = $this->_parse_data_from_outside_source($this->_definition_arr, $outside_data, $parsing_params))) {
            $this->_data_was_parsed = true;

            $this->set_error_if_not_set(self::ERR_PARAMETERS, self::_t('Error while parsing data from outside source.'));

            return null;
        }

        $this->_resulting_data = $_resulting_data ?: [];

        $this->_data_was_parsed = true;

        return $this->_resulting_data;
    }

    /**
     * @param null|array $inside_data Source array received from inside which should be converted into outside data
     * @param array $params Functionality parameters
     *
     * @return null|array
     *                    array with parsed data
     *                    null with error if data validation failed
     *                    null with no error if expected value should indeed be null
     */
    public function parse_data_from_inside_source(?array $inside_data = null, array $params = []) : ?array
    {
        $this->reset_error();
        $this->_reset_data();

        if (!$this->_make_sure_we_have_definition()) {
            return null;
        }

        $inside_data ??= [];

        $params['data_cache'] ??= null;
        $params['pre_processing_params'] ??= [];
        $params['post_processing_params'] ??= [];
        $params['force_export_if_not_found'] = !empty($params['force_export_if_not_found']);
        $params['max_data_recursive_lvl'] = (int)($params['max_data_recursive_lvl'] ?? 0);

        if (!$params['data_cache'] || !is_array($params['data_cache'])) {
            $params['data_cache'] = null;
        }
        if (!$params['pre_processing_params'] || !is_array($params['pre_processing_params'])) {
            $params['pre_processing_params'] = [];
        }
        if (!$params['post_processing_params'] || !is_array($params['post_processing_params'])) {
            $params['post_processing_params'] = [];
        }

        $this->_data_type = self::FROM_INSIDE;
        $this->_source_data = $this->_processing_data = $inside_data;

        if ($params['data_cache']) {
            $this->_set_initial_cache_data($params['data_cache']);
        }

        $parsing_params = [];
        $parsing_params['lvl'] = 0;
        $parsing_params['lvl_contract'] = $this;
        $parsing_params['force_export_if_not_found'] = $params['force_export_if_not_found'];
        $parsing_params['max_data_recursive_lvl'] = $params['max_data_recursive_lvl'];
        $parsing_params['pre_processing_params'] = $params['pre_processing_params'];
        $parsing_params['post_processing_params'] = $params['post_processing_params'];

        $this->_resulting_data = [];

        if ((null === ($_resulting_data
                    = $this->_parse_data_from_inside_source($this->_definition_arr, $inside_data, $parsing_params)))
            && $this->has_error()) {
            $this->_data_was_parsed = true;

            return null;
        }

        $this->_resulting_data = $_resulting_data ?: [];

        $this->_data_was_parsed = true;

        return $this->_resulting_data;
    }

    /**
     * If sub-nodes are known to use data which can be provided in an array with finite values (eg. categories which can be up to 100, etc)
     * and which are not related necessary to a node in current contract definition, you can use this method to add data to cache in order
     * to limit number of queries to database
     *
     * @param PHS_Model $model_obj
     * @param array $data_arr
     * @param null|bool|array $flow_arr
     *
     * @return bool
     */
    public function add_data_to_cache(PHS_Model $model_obj, array $data_arr, null | bool | array $flow_arr = null) : bool
    {
        if (!$data_arr
            || !($flow_arr = $model_obj->fetch_default_flow_params($flow_arr))
            || !($table_name = $flow_arr['table_name'])
            || !($primary_key = $flow_arr['table_index'])
            || !($model_id = $model_obj->instance_id())
        ) {
            return false;
        }

        foreach ($data_arr as $cache_data_arr) {
            if (empty($cache_data_arr) || !is_array($cache_data_arr)
                || empty($cache_data_arr[$primary_key])) {
                continue;
            }

            $this->_data_cache[$model_id][$table_name][$cache_data_arr[$primary_key]] = $cache_data_arr;
        }

        return true;
    }

    public function data_is_from_outside() : bool
    {
        return $this->_data_type === self::FROM_OUTSIDE;
    }

    public function data_is_from_inside() : bool
    {
        return $this->_data_type === self::FROM_INSIDE;
    }

    public function data_was_parsed() : bool
    {
        return $this->_data_was_parsed;
    }

    /**
     * Returns an array of parsed data
     * @return array
     */
    public function get_resulting_data() : array
    {
        return $this->_resulting_data;
    }

    public function get_data_keys(null | bool | string $lang = null) : array
    {
        static $keys_arr = [];

        if (!$lang
            && $keys_arr) {
            return $keys_arr;
        }

        $result_arr = $this->translate_array_keys(self::$KEYS_ARR, ['title'], $lang);

        if (!$lang) {
            $keys_arr = $result_arr;
        }

        return $result_arr;
    }

    public function get_data_keys_as_key_val(null | bool | string $lang = null) : array
    {
        static $data_keys_key_val_arr = null;

        if (!$lang
            && $data_keys_key_val_arr !== null) {
            return $data_keys_key_val_arr;
        }

        $key_val_arr = [];
        if (($data_keys = $this->get_data_keys($lang))) {
            foreach ($data_keys as $key => $val) {
                if (!is_array($val)) {
                    continue;
                }

                $key_val_arr[$key] = $val['title'];
            }
        }

        if (!$lang) {
            $data_keys_key_val_arr = $key_val_arr;
        }

        return $key_val_arr;
    }

    public function valid_data_key(int $data_key, null | bool | string $lang = null) : ?array
    {
        return $this->get_data_keys($lang)[$data_key] ?? null;
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
    protected function _set_initial_cache_data(array $data_arr) : bool
    {
        if (!$data_arr) {
            return false;
        }

        foreach ($this->_definition_arr as $node_key => $node_arr) {
            /** @var PHS_Model $model_obj */
            if (empty($data_arr[$node_key])
             || empty($node_arr['nodes']) || !is_array($node_arr['nodes'])
             || !(($model_obj = ($node_arr['data_model_obj'] ?? null)) instanceof PHS_Model)
             || !($flow_arr = $model_obj->fetch_default_flow_params($node_arr['data_flow_arr'] ?: []))
             || empty($data_arr[$node_key][$flow_arr['table_index']])
             || !($model_id = $model_obj->instance_id())) {
                continue;
            }

            $this->_data_cache[$model_id] ??= [];
            $this->_data_cache[$model_id][$flow_arr['table_name']] ??= [];

            $this->_data_cache[$model_id][$flow_arr['table_name']][(int)$data_arr[$node_key][$flow_arr['table_index']]] = $data_arr[$node_key];
        }

        return true;
    }

    /**
     * Given a node definition and data to be parsed for current contract recurrence level, check if we have cached any data
     *
     * @param array $node_arr Node definition for which we check cache
     * @param array $inside_data Data provided for current contract recurrence level
     *
     * @return null|array|PHS_Record_data
     */
    protected function _get_cache_data_for_node(array $node_arr, array $inside_data) : null | array | PHS_Record_data
    {
        /** @var PHS_Model $model_obj */
        if (!$node_arr
         || empty($node_arr['nodes']) || !is_array($node_arr['nodes'])
         || empty($node_arr['data_primary_key'])
         || !isset($inside_data[$node_arr['data_primary_key']])
         || !($primary_key = (int)$inside_data[$node_arr['data_primary_key']])
         || !(($model_obj = ($node_arr['data_model_obj'] ?? null)) instanceof PHS_Model)
         || !($flow_arr = $model_obj->fetch_default_flow_params($node_arr['data_flow_arr'] ?: []))
         || !($model_id = $model_obj->instance_id())
         || empty($this->_data_cache[$model_id][$flow_arr['table_name']][$primary_key])
        ) {
            return null;
        }

        return $this->_data_cache[$model_id][$flow_arr['table_name']][$primary_key];
    }

    /**
     * Given a node definition and data to be parsed for current contract recurrence level, check if we have cached any data
     *
     * @param array $node_arr Node definition for which we check cache
     * @param null|array|PHS_Record_data $data_arr Data to be cached
     *
     * @return bool
     */
    protected function _set_cache_data_for_node(array $node_arr, null | array | PHS_Record_data $data_arr) : bool
    {
        /** @var PHS_Model $model_obj */
        if (empty($node_arr) | !is_array($node_arr)
         || empty($node_arr['nodes']) || !is_array($node_arr['nodes'])
         || empty($node_arr['data_primary_key'])
         || !(($model_obj = ($node_arr['data_model_obj'] ?? null)) instanceof PHS_Model)
         || !($flow_arr = $model_obj->fetch_default_flow_params($node_arr['data_flow_arr'] ?: []))
         || !isset($data_arr[$flow_arr['table_index']])
         || !($primary_key = (int)$data_arr[$flow_arr['table_index']])
         || !($model_id = $model_obj->instance_id())
        ) {
            return false;
        }

        if (empty($this->_data_cache[$model_id])) {
            $this->_data_cache[$model_id] = [];
        }
        if (empty($this->_data_cache[$model_id][$flow_arr['table_name']])) {
            $this->_data_cache[$model_id][$flow_arr['table_name']] = [];
        }

        $this->_data_cache[$model_id][$flow_arr['table_name']][$primary_key] = $data_arr;

        return true;
    }

    protected function _make_sure_we_have_definition() : bool
    {
        if ($this->_definition_initialized) {
            return true;
        }

        if (!($definition_arr = $this->_normalize_definition_of_nodes())) {
            return false;
        }

        $this->_definition_arr = $definition_arr;
        $this->_definition_initialized = true;

        return true;
    }

    /**
     * Normalize an array with definitions of data nodes to be used in this contract
     * @param null|array $definition_arr Definition of nodes of current node or false to normalize all definition
     * @param array $params_arr Parameters sent to normalization method
     *
     * @return null|array Normalized definition array, false if we had errors
     */
    protected function _normalize_definition_of_nodes(?array $definition_arr = null, array $params_arr = []) : ?array
    {
        if (empty($params_arr['parent_contracts']) || !is_array($params_arr['parent_contracts'])) {
            $params_arr['parent_contracts'] = [];
        }

        if ($definition_arr === null
            && !($definition_arr = $this->get_contract_data_definition())) {
            $this->set_error_if_not_set(
                self::ERR_PARAMETERS,
                self::_t('get_contract_data_definition() method should return an array.')
            );

            return null;
        }

        if (!$definition_arr) {
            $this->set_error(self::ERR_PARAMETERS, self::_t('Provided node definition is not an array.'));

            return null;
        }

        $node_definition = self::_get_contract_node_definition();
        $return_arr = [];
        foreach ($definition_arr as $int_key => $node_arr) {
            if (empty($node_arr) || !is_array($node_arr)) {
                continue;
            }

            if (!isset($node_arr['inside_key'])
             || (string)$node_arr['inside_key'] === ''
             || !is_scalar($node_arr['inside_key'])) {
                $node_arr['inside_key'] = $int_key;
            }

            if (!isset($node_arr['outside_key'])
             || (string)$node_arr['outside_key'] === ''
             || !is_scalar($node_arr['outside_key'])) {
                $node_arr['outside_key'] = $int_key;
            }

            if (array_key_exists('default', $node_arr)) {
                $node_arr['default_inside'] = $node_arr['default'];
                $node_arr['default_outside'] = $node_arr['default'];
            }

            if (!isset($node_arr['key_type'])
                || !$this->valid_data_key($node_arr['key_type'])) {
                $node_arr['key_type'] = self::FROM_BOTH;
            }

            $node_arr = self::validate_array($node_arr, $node_definition);

            /** @var PHS_Contract $contract_obj */
            if (($contract_obj = $node_arr['nodes_from_contract'])
                && !($contract_obj instanceof self)) {
                $this->set_error(self::ERR_PARAMETERS,
                    self::_t('Node %s in contract definition doesn\'t provide a valid contract.', $int_key));

                return null;
            }

            $contract_instance_id = null;
            if ($contract_obj) {
                $contract_instance_id = $contract_obj->instance_id();
            }

            // Check recurring loop in contracts definition
            if (!empty($params_arr['parent_contracts'])
                && in_array($contract_instance_id, $params_arr['parent_contracts'], true)) {
                continue;
            }

            if ($contract_obj
                && !($node_arr['nodes'] = $contract_obj->get_contract_data_definition())) {
                $node_arr['nodes'] = [];
            }

            /** @var PHS_Model $model_obj */
            $model_obj = null;
            $model_flow_arr = [];
            if (!empty($node_arr['data_model_obj'])) {
                $model_obj = $node_arr['data_model_obj'];
                if (!($model_obj instanceof PHS_Model)) {
                    $this->set_error(self::ERR_PARAMETERS,
                        self::_t('Node %s in contract definition doesn\'t provide a valid data parsing model.', $int_key));

                    return null;
                }
            }

            if (!empty($node_arr['data_flow_arr'])
             && is_array($node_arr['data_flow_arr'])) {
                $model_flow_arr = $node_arr['data_flow_arr'];
            }

            if ($contract_obj) {
                if ($model_obj === null
                 && ($model_obj = $contract_obj->get_parsing_data_model())
                 && !($model_obj instanceof PHS_Model)) {
                    $this->set_error(self::ERR_PARAMETERS, self::_t('Node %s in contract definition doesn\'t provide a valid data parsing model.', $int_key));

                    return null;
                }

                if (empty($model_flow_arr)
                 && (!($model_flow_arr = $contract_obj->get_parsing_data_model_flow())
                      || !is_array($model_flow_arr)
                 )) {
                    $model_flow_arr = [];
                }

                $node_arr['data_model_obj'] = $model_obj;
                $node_arr['data_flow_arr'] = $model_flow_arr;
            }

            if (!empty($node_arr['recurring_node'])
             && empty($node_arr['recurring_scalar_node'])
             && (empty($node_arr['nodes']) || !is_array($node_arr['nodes']))) {
                $this->set_error(self::ERR_PARAMETERS, self::_t('Node %s in contract definition is set as recurring, '
                                                                  .'but has no nodes defined as array.', $int_key));

                return null;
            }

            $rec_params_arr = $params_arr;
            if ($contract_instance_id) {
                $rec_params_arr['parent_contracts'][] = $contract_instance_id;
            }

            if (!empty($node_arr['nodes']) && is_array($node_arr['nodes'])
                && null === ($node_arr['nodes'] = $this->_normalize_definition_of_nodes($node_arr['nodes'], $rec_params_arr))) {
                return null;
            }

            $return_arr[$int_key] = $node_arr;
        }

        return $return_arr;
    }

    private function _reset_data() : void
    {
        $this->_source_data = [];
        $this->_resulting_data = [];
        $this->_data_cache = [];
        $this->_data_type = false;
        $this->_data_was_parsed = false;
    }

    /**
     * @param array $definition_arr
     * @param array $outside_data
     * @param array $params
     *
     * @return null|array|false array with result, false if we reached a leaf or null (with error set) on error...
     */
    private function _parse_data_from_outside_source(array $definition_arr, array $outside_data, array $params) : null | bool | array
    {
        if (!$definition_arr || !$outside_data) {
            if (0 === ($params['lvl'] ?? 0)) {
                return null;
            }

            return false;
        }

        // Check if we reached maximum number of recursive calls
        // return false to ignore this node...
        if ($params['lvl'] > $this->max_recursive_level_for_data_parsing()) {
            return false;
        }

        // What contract did provide these nodes?
        if (empty($params['lvl_contract'])) {
            $params['lvl_contract'] = null;
        }

        if (empty($params['pre_processing_params']) || !is_array($params['pre_processing_params'])) {
            $params['pre_processing_params'] = [];
        }
        if (empty($params['post_processing_params']) || !is_array($params['post_processing_params'])) {
            $params['post_processing_params'] = [];
        }

        if (empty($params['ignore_nodes']) || !is_array($params['ignore_nodes'])) {
            $params['ignore_nodes'] = [];
        }
        if (empty($params['ignore_outside_nodes']) || !is_array($params['ignore_outside_nodes'])) {
            $params['ignore_outside_nodes'] = [];
        }

        if (!($ignore_nodes = self::array_merge_unique_values($params['ignore_nodes'], $params['ignore_outside_nodes']))) {
            $ignore_nodes = [];
        }

        $processing_params = [];
        $processing_params['lvl'] = $params['lvl'];
        $processing_params['max_lvl'] = $this->max_recursive_level_for_data_parsing();

        $this->_processing_data = $outside_data;

        /** @var PHS_Contract $lvl_contract */
        if (($lvl_contract = $params['lvl_contract'])) {
            if (null === ($new_outside_data = $lvl_contract->pre_processing_from_outside_source($outside_data, $params['pre_processing_params'], $processing_params))) {
                // in case validation fails, and we have an error set, copy the error and propagate it...
                if ($lvl_contract->has_error()) {
                    $this->copy_error($lvl_contract);
                }

                return null;
            }

            // Ignore the node...
            if ($new_outside_data === false
                || !is_array($new_outside_data)) {
                return false;
            }

            $outside_data = $new_outside_data;

            if (empty($outside_data) || !is_array($outside_data)) {
                $outside_data = [];
            }

            $this->_processing_data = $outside_data;
        }

        $return_arr = [];
        foreach ($definition_arr as $node_key => $node_arr) {
            if ($node_arr['key_type'] === self::FROM_INSIDE
                || ($ignore_nodes && in_array($node_key, $ignore_nodes, true))) {
                continue;
            }

            // Make sure that we have a data to process
            if (!array_key_exists($node_arr['outside_key'], $outside_data)) {
                if (!empty($node_arr['import_if_not_found'])
                    || !empty($params['force_import_if_not_found'])) {
                    $return_arr[$node_arr['inside_key']] = $node_arr['default_inside'];
                }

                continue;
            }

            // Check if we have recurring node...
            if (!empty($node_arr['recurring_node'])) {
                if (!is_array($outside_data[$node_arr['outside_key']])) {
                    continue;
                }

                $return_arr[$node_arr['inside_key']] = [];

                $rec_params = $params;
                $rec_lvl = $rec_params['lvl'];

                if (!empty($node_arr['outside_parsing_params']) && is_array($node_arr['outside_parsing_params'])) {
                    $rec_params = self::merge_array_assoc($rec_params, $node_arr['outside_parsing_params']);
                }

                $rec_params['lvl_contract'] = (!empty($node_arr['nodes_from_contract']) ? $node_arr['nodes_from_contract'] : null);
                // Make sure lvl doesn't get overwritten
                $rec_params['lvl'] = $rec_lvl + 1;

                $recurring_items_no = 0;
                foreach ($outside_data[$node_arr['outside_key']] as $outside_item) {
                    if (!empty($node_arr['recurring_scalar_node'])) {
                        // Recurring scalar value...
                        if (null === ($result_item = PHS_Params::set_type($outside_item, $node_arr['type'], (!empty($node_arr['type_extra']) ? $node_arr['type_extra'] : false)))) {
                            continue;
                        }
                    } elseif (!is_array($outside_item)
                              || false === ($result_item = $this->_parse_data_from_outside_source($node_arr['nodes'], $outside_item, $rec_params))) {
                        continue;
                    }

                    if (null === $result_item) {
                        if ($this->has_error()) {
                            return null;
                        }

                        // This is an array of items... if result is null we cannot add null in array
                        continue;
                    }

                    // Check if we have data post-processing to do
                    /** @var PHS_Contract $contract_obj */
                    if (($contract_obj = $node_arr['nodes_from_contract'])) {
                        // If post-processing returns null, we ignore this record
                        if (null === ($result_item = $contract_obj->post_processing_from_outside_source($result_item, $params['post_processing_params'], $processing_params))) {
                            if ($contract_obj->has_error()) {
                                $this->copy_error($contract_obj);

                                return null;
                            }

                            // This is an array of items... if result is null we cannot add null in array
                            continue;
                        }
                    }

                    $inside_knti = PHS_Params::set_type($recurring_items_no, $node_arr['recurring_key_type'],
                        (!empty($node_arr['recurring_key_type_extra']) ? $node_arr['recurring_key_type_extra'] : []));

                    $recurring_items_no++;

                    $return_arr[$node_arr['inside_key']][$inside_knti] = $result_item;

                    if (!empty($node_arr['recurring_max_items'])
                     && $recurring_items_no >= $node_arr['recurring_max_items']) {
                        break;
                    }
                }

                // No nodes were parsed... check if we put the default value...
                if (empty($return_arr[$node_arr['inside_key']])) {
                    if (!empty($node_arr['import_if_not_found'])
                     || !empty($params['force_import_if_not_found'])) {
                        $return_arr[$node_arr['inside_key']] = $node_arr['default_inside'];
                    } else {
                        unset($return_arr[$node_arr['inside_key']]);
                    }
                }

                continue;
            }

            // This is not a recurring node, but it is an "object" (has nodes definition inside)
            if (!empty($node_arr['nodes']) && is_array($node_arr['nodes'])) {
                if (!is_array($outside_data[$node_arr['outside_key']])) {
                    continue;
                }

                $return_arr[$node_arr['inside_key']] = [];

                $rec_params = $params;
                $rec_lvl = $rec_params['lvl'];

                if (!empty($node_arr['outside_parsing_params']) && is_array($node_arr['outside_parsing_params'])) {
                    $rec_params = self::merge_array_assoc($rec_params, $node_arr['outside_parsing_params']);
                }

                $rec_params['lvl_contract'] = (!empty($node_arr['nodes_from_contract']) ? $node_arr['nodes_from_contract'] : null);
                // Make sure lvl doesn't get overwritten
                $rec_params['lvl'] = $rec_lvl + 1;

                if (false === ($result_item = $this->_parse_data_from_outside_source($node_arr['nodes'], $outside_data[$node_arr['outside_key']], $rec_params))) {
                    continue;
                }

                // Contract data validation failed...
                if (null === $result_item
                    && $this->has_error()) {
                    return null;
                }

                // Check if we have data post-processing to do
                /** @var PHS_Contract $contract_obj */
                if ($result_item !== null
                 && ($contract_obj = $node_arr['nodes_from_contract'])) {
                    // If post-processing returns null, we ignore this record
                    if (null === ($result_item = $contract_obj->post_processing_from_outside_source($result_item, $params['post_processing_params'], $processing_params))) {
                        if ($contract_obj->has_error()) {
                            $this->copy_error($contract_obj);

                            return null;
                        }

                        if (!empty($node_arr['import_if_not_found'])
                            || !empty($params['force_import_if_not_found'])) {
                            $return_arr[$node_arr['inside_key']] = $node_arr['default_inside'];
                        }

                        continue;
                    }
                }

                if ($result_item === null) {
                    if (!empty($node_arr['import_if_not_found'])
                        || !empty($params['force_import_if_not_found'])) {
                        $return_arr[$node_arr['inside_key']] = $node_arr['default_inside'];
                    }
                } else {
                    $return_arr[$node_arr['inside_key']] = $result_item;
                }

                continue;
            }

            // Scalar value...
            $return_arr[$node_arr['inside_key']] = PHS_Params::set_type($outside_data[$node_arr['outside_key']], $node_arr['type'],
                (!empty($node_arr['type_extra']) ? $node_arr['type_extra'] : false));
        }

        // Post-process for "root" object
        return $this->post_processing_from_outside_source($return_arr, $params['post_processing_params'], $processing_params);
    }

    private function _non_nodes_related_cache_data_keys() : array
    {
        return [
            // Instance of model to be used to obtain data
            'data_model_obj' => null,
            // flow to be passed to PHS_Model::get_details() method (obtained with PHS_Model::fetch_default_flow_params() method)
            'data_flow_arr' => null,
            // Data to be added to cache as an array of records (key is ignored, but record array should contain a model flow 'table_index' key)
            'data_cache' => null,
        ];
    }

    /**
     * @param array $definition_arr
     * @param array $inside_data
     * @param array $params
     *
     * @return null|array|bool
     *                         array with result,
     *                         false if we should ignore node
     *                         null with error set on error,
     *                         null without error set means that we should put null in node...
     */
    private function _parse_data_from_inside_source(array $definition_arr, array $inside_data, array $params) : null | bool | array
    {
        if (!$definition_arr || !$inside_data) {
            if (0 === $params['lvl']) {
                return null;
            }

            return false;
        }

        // Check if we reached maximum number of recursive calls
        // return false to ignore this node...
        if ($params['lvl'] > $this->max_recursive_level_for_data_parsing()) {
            return false;
        }

        // What contract did provide these nodes?
        if (empty($params['lvl_contract'])) {
            $params['lvl_contract'] = null;
        }

        if (empty($params['pre_processing_params']) || !is_array($params['pre_processing_params'])) {
            $params['pre_processing_params'] = [];
        }
        if (empty($params['post_processing_params']) || !is_array($params['post_processing_params'])) {
            $params['post_processing_params'] = [];
        }

        if (empty($params['ignore_nodes']) || !is_array($params['ignore_nodes'])) {
            $params['ignore_nodes'] = [];
        }
        if (empty($params['ignore_inside_nodes']) || !is_array($params['ignore_inside_nodes'])) {
            $params['ignore_inside_nodes'] = [];
        }

        if (!($ignore_nodes = self::array_merge_unique_values($params['ignore_nodes'], $params['ignore_inside_nodes']))) {
            $ignore_nodes = [];
        }

        $processing_params = [];
        $processing_params['lvl'] = $params['lvl'];
        $processing_params['max_lvl'] = $this->max_recursive_level_for_data_parsing();

        $this->_processing_data = $inside_data;

        /** @var PHS_Contract $lvl_contract */
        if (($lvl_contract = $params['lvl_contract'])) {
            if (null === ($new_inside_data = $lvl_contract->pre_processing_from_inside_source($inside_data, $params['pre_processing_params'], $processing_params))) {
                // in case validation fails, and we have an error set, copy the error and propagate it...
                if ($lvl_contract->has_error()) {
                    $this->copy_error($lvl_contract);
                }

                return null;
            }

            // Ignore the node...
            if ($new_inside_data === false
                || !is_array($new_inside_data)) {
                return false;
            }

            $inside_data = $new_inside_data ?: [];

            $this->_processing_data = $inside_data;
        }

        $return_arr = [];
        foreach ($definition_arr as $node_key => $node_arr) {
            if ($node_arr['key_type'] === self::FROM_OUTSIDE
                || ($ignore_nodes && in_array($node_key, $ignore_nodes, true))) {
                continue;
            }

            // Check if we have data to process for current node
            if (!array_key_exists($node_arr['inside_key'], $inside_data)) {
                if (empty($node_arr['max_data_recursive_lvl'])) {
                    $max_data_recursive_lvl = $params['max_data_recursive_lvl'];
                } elseif (empty($params['max_data_recursive_lvl'])) {
                    $max_data_recursive_lvl = $node_arr['max_data_recursive_lvl'];
                } else { // Node recurrence is calculated from level of node...
                    $max_data_recursive_lvl = min($params['max_data_recursive_lvl'], $node_arr['max_data_recursive_lvl'] + $params['lvl']);
                }

                // This should be an "object", but we are provided no data for it,
                // check if we have an associated model from where we can take the data
                /** @var PHS_Model $model_obj */
                if (!empty($node_arr['nodes']) && is_array($node_arr['nodes'])
                 && !empty($node_arr['data_model_obj'])
                 && !empty($node_arr['data_primary_key'])
                 && !empty($inside_data[$node_arr['data_primary_key']])
                 && (empty($max_data_recursive_lvl) || $max_data_recursive_lvl > $params['lvl'])) {
                    // It seems we can get some data from model...
                    $db_record_arr = $this->_get_cache_data_for_node($node_arr, $inside_data) ?: null;

                    if (!$db_record_arr
                     && (($model_obj = $node_arr['data_model_obj']) instanceof PHS_Model)
                     && ($flow_arr = $model_obj->fetch_default_flow_params($node_arr['data_flow_arr'] ?: []))
                     && ($db_record_arr = $model_obj->get_details($inside_data[$node_arr['data_primary_key']], $flow_arr))) {
                        $this->_set_cache_data_for_node($node_arr, $db_record_arr);
                    }

                    if ($db_record_arr) {
                        $rec_params = $params;
                        $rec_params['max_data_recursive_lvl'] = $max_data_recursive_lvl;
                        $rec_lvl = $rec_params['lvl'];

                        if (!empty($node_arr['inside_parsing_params']) && is_array($node_arr['inside_parsing_params'])) {
                            $rec_params = self::merge_array_assoc($rec_params, $node_arr['inside_parsing_params']);
                        }

                        $rec_params['lvl_contract'] = (!empty($node_arr['nodes_from_contract']) ? $node_arr['nodes_from_contract'] : null);
                        // Make sure lvl doesn't get overwritten
                        $rec_params['lvl'] = $rec_lvl + 1;

                        if (false !== ($result_item = $this->_parse_data_from_inside_source($node_arr['nodes'], $db_record_arr, $rec_params))) {
                            // Contract data validation failed...
                            if (null === $result_item
                                && $this->has_error()) {
                                return null;
                            }

                            // Check if we have data post-processing to do
                            /** @var PHS_Contract $contract_obj */
                            if ($result_item !== null
                                && ($contract_obj = $node_arr['nodes_from_contract'])
                                && null === ($result_item = $contract_obj->post_processing_from_inside_source($result_item, $params['post_processing_params'], $processing_params))
                            ) {
                                if ($contract_obj->has_error()) {
                                    // If post-processing returns null, and we have an error, propagate the error
                                    $this->copy_error($contract_obj);

                                    return null;
                                }

                                if (!empty($node_arr['export_if_not_found'])
                                    || !empty($params['force_export_if_not_found'])) {
                                    $return_arr[$node_arr['outside_key']] = $node_arr['default_outside'];
                                }

                                continue;
                            }

                            $return_arr[$node_arr['outside_key']] = $result_item;
                            continue;
                        }
                    }
                }

                if (!empty($node_arr['export_if_not_found'])
                 || !empty($params['force_export_if_not_found'])) {
                    $return_arr[$node_arr['outside_key']] = $node_arr['default_outside'];
                }

                continue;
            }

            // Check if we have recurring node...
            if (!empty($node_arr['recurring_node'])) {
                if (!is_array($inside_data[$node_arr['inside_key']])) {
                    continue;
                }

                $return_arr[$node_arr['outside_key']] = [];

                $rec_params = $params;
                $rec_lvl = $rec_params['lvl'];

                if (!empty($node_arr['inside_parsing_params']) && is_array($node_arr['inside_parsing_params'])) {
                    $rec_params = self::merge_array_assoc($rec_params, $node_arr['inside_parsing_params']);
                }

                $rec_params['lvl_contract'] = (!empty($node_arr['nodes_from_contract']) ? $node_arr['nodes_from_contract'] : null);
                // Make sure lvl doesn't get overwritten
                $rec_params['lvl'] = $rec_lvl + 1;

                $recurring_items_no = 0;
                foreach ($inside_data[$node_arr['inside_key']] as $input_item) {
                    if (!empty($node_arr['recurring_scalar_node'])) {
                        // Recurring scalar value...
                        if (null === ($result_item = PHS_Params::set_type($input_item, $node_arr['type'], (!empty($node_arr['type_extra']) ? $node_arr['type_extra'] : false)))) {
                            continue;
                        }
                    } elseif (!is_array($input_item)
                              || false === ($result_item = $this->_parse_data_from_inside_source($node_arr['nodes'], $input_item, $rec_params))) {
                        continue;
                    }

                    if (null === $result_item) {
                        if ($this->has_error()) {
                            return null;
                        }

                        // This is an array of items... if result is null we cannot add null in array
                        continue;
                    }

                    // Check if we have data post-processing to do
                    /** @var PHS_Contract $contract_obj */
                    if (($contract_obj = $node_arr['nodes_from_contract'])) {
                        // If post-processing returns null, we ignore this record
                        if (null === ($result_item = $contract_obj->post_processing_from_inside_source($result_item, $params['post_processing_params'], $processing_params))) {
                            if ($contract_obj->has_error()) {
                                $this->copy_error($contract_obj);

                                return null;
                            }

                            // This is an array of items... if result is null we cannot add null in array
                            continue;
                        }
                    }

                    $inside_knti = PHS_Params::set_type($recurring_items_no, $node_arr['recurring_key_type'],
                        (!empty($node_arr['recurring_key_type_extra']) ? $node_arr['recurring_key_type_extra'] : []));

                    $recurring_items_no++;

                    $return_arr[$node_arr['outside_key']][$inside_knti] = $result_item;

                    if (!empty($node_arr['recurring_max_items'])
                     && $recurring_items_no >= $node_arr['recurring_max_items']) {
                        break;
                    }
                }

                // No nodes were parsed... check if we put the default value...
                if (empty($return_arr[$node_arr['outside_key']])) {
                    if (!empty($node_arr['export_if_not_found'])
                     || !empty($params['force_export_if_not_found'])) {
                        $return_arr[$node_arr['outside_key']] = $node_arr['default_outside'];
                    } else {
                        unset($return_arr[$node_arr['outside_key']]);
                    }
                }

                continue;
            }

            // This is not a recurring node, but it is an "object" (has nodes definition inside)
            if (!empty($node_arr['nodes']) && is_array($node_arr['nodes'])) {
                if (!is_array($inside_data[$node_arr['inside_key']])) {
                    continue;
                }

                $return_arr[$node_arr['outside_key']] = [];

                $rec_params = $params;
                $rec_lvl = $rec_params['lvl'];

                if (!empty($node_arr['inside_parsing_params']) && is_array($node_arr['inside_parsing_params'])) {
                    $rec_params = self::merge_array_assoc($rec_params, $node_arr['inside_parsing_params']);
                }

                $rec_params['lvl_contract'] = (!empty($node_arr['nodes_from_contract']) ? $node_arr['nodes_from_contract'] : null);
                // Make sure lvl doesn't get overwritten
                $rec_params['lvl'] = $rec_lvl + 1;

                if (false === ($result_item = $this->_parse_data_from_inside_source($node_arr['nodes'], $inside_data[$node_arr['inside_key']], $rec_params))) {
                    continue;
                }

                // Contract data validation failed...
                if (null === $result_item
                 && $this->has_error()) {
                    return null;
                }

                // Check if we have data post-processing to do
                /** @var PHS_Contract $contract_obj */
                if ($result_item !== null
                 && ($contract_obj = $node_arr['nodes_from_contract'])) {
                    if (null === ($result_item = $contract_obj->post_processing_from_inside_source($result_item, $params['post_processing_params'], $processing_params))) {
                        if ($contract_obj->has_error()) {
                            // If post-processing returns null, and we have an error, propagate the error
                            $this->copy_error($contract_obj);

                            return null;
                        }

                        if (!empty($node_arr['export_if_not_found'])
                         || !empty($params['force_export_if_not_found'])) {
                            $return_arr[$node_arr['outside_key']] = $node_arr['default_outside'];
                        }

                        continue;
                    }
                }

                if ($result_item === null) {
                    if (!empty($node_arr['export_if_not_found'])
                     || !empty($params['force_export_if_not_found'])) {
                        $return_arr[$node_arr['outside_key']] = $node_arr['default_outside'];
                    }
                } else {
                    $return_arr[$node_arr['outside_key']] = $result_item;
                }

                continue;
            }

            // Scalar value...
            $return_arr[$node_arr['outside_key']] = PHS_Params::set_type($inside_data[$node_arr['inside_key']], $node_arr['type'],
                (!empty($node_arr['type_extra']) ? $node_arr['type_extra'] : false));
        }

        // Post-process for "root" object
        return $this->post_processing_from_inside_source($return_arr, $params['post_processing_params'], $processing_params);
    }

    /**
     * Standard definition of a data node to be exported as response to a 3rd party request
     * @return array
     */
    protected static function _get_contract_node_definition() : array
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
            'type_extra' => [],
            // If this is defined will be used for both default_inside and default_outside
            'default' => null,
            // Default value when transforming outside data to inside data
            'default_inside' => null,
            // Default value when transforming inside data to outside data
            'default_outside' => null,

            // array of strings which represents nodes keys to be ignored when parsing contract
            // ignore_inside_nodes ignore inside source node keys, ignore_outside_nodes ignore outside source node keys, ignore_nodes (for both)
            'ignore_nodes'         => [],
            'ignore_inside_nodes'  => [],
            'ignore_outside_nodes' => [],

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
            'recurring_key_type_extra' => [],

            // Try obtaining data from a model if we are provided no data from inside source
            // NOTE: This works only when parsing data from inside sources
            // Instance of model to be used to obtain data
            'data_model_obj' => null,
            // flow to be passed to PHS_Model::get_details() method (obtained with PHS_Model::fetch_default_flow_params() method)
            'data_flow_arr' => null,
            // Which key in inside data array contains primary index value to be used with PHS_Model::get_details() method
            // when retrieving data from model
            'data_primary_key' => false,

            // If a contract class is provided here 'nodes' will be taken from ${'nodes_from_contract'}->get_contract_data_definition()
            // Also contract can provide 'data_model_obj' and 'data_flow_arr' from abstract methods
            'nodes_from_contract' => null,

            // Maximum number of recursive calls from this node forward 0 - no limit
            'max_data_recursive_lvl' => 0,

            // In case we want to overwrite parameters sent to _parse_data_from_inside_source only for this node and its children
            // we set this as array of parameters (eg. maybe change max_data_recursive_lvl, ignore_nodes, etc)
            'inside_parsing_params' => [],

            // In case we want to overwrite parameters sent to _parse_data_from_outside_source only for this node and its children
            // we set this as array of parameters (eg. maybe change max_data_recursive_lvl, ignore_nodes, etc)
            'outside_parsing_params' => [],

            // (OPTIONAL) Descriptive info about this node (can generate documentation based on this
            'title'       => '',
            'description' => '',

            // If this node has children (subnodes), add their definition here...
            'nodes' => [],
        ];
    }
}
