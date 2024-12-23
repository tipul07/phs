<?php
namespace phs\libraries;

use phs\PHS;
use phs\PHS_Scope;
use phs\system\core\views\PHS_View;
use phs\system\core\libraries\PHS_Paginator_exporter_csv;

class PHS_Paginator extends PHS_Registry
{
    public const ERR_FILTERS = 1, ERR_BULK_ACTIONS = 2, ERR_COLUMNS = 3, ERR_MODEL = 4, ERR_RENDER = 5;

    public const DEFAULT_PER_PAGE = 20;

    public const CHECKBOXES_COLUMN_ALL_SUFIX = '_all';

    public const ACTION_PARAM_NAME = 'pag_act', ACTION_PARAMS_PARAM_NAME = 'pag_act_params', ACTION_RESULT_PARAM_NAME = 'pag_act_result';

    public const CELL_RENDER_HTML = 1, CELL_RENDER_TEXT = 2, CELL_RENDER_JSON = 3;

    // Bulk actions array
    private array $_bulk_actions = [];

    // Filters array
    private array $_filters = [];

    // Variables as provided in post or get
    private array $_originals = [];

    // Parsed variables extracted from request
    private array $_scope = [];

    // Action request (if any)
    private array $_action = [];

    private ?PHS_Model $_model = null;

    private array $_flow_params_arr = [];

    private array $_pagination_params_arr = [];

    private array $_columns_definition_arr = [];

    // Array with records to be displayed (limit set based on paginator parameters from model or provided array of records from external source)
    private array $_records_arr = [];

    // If exporting records, we store query result here,
    // so we can iterate results rather than obtaining a huge (maybe) array with all records
    /** @var bool|mixed */
    private $_query_id;

    /** @var string */
    private string $_base_url = '';

    public function __construct(?string $base_url = null, ?array $flow_params = null)
    {
        parent::__construct();

        $this->reset_paginator();

        if ($flow_params !== null) {
            $this->flow_params($flow_params);
        }

        if ($base_url === null) {
            $this->base_url(PHS::current_url());
        } else {
            $this->base_url($base_url);
        }
    }

    public function default_api_listing_response() : array
    {
        return [
            'offset'                => 0,
            'records_per_page'      => 0,
            'total_records'         => 0,
            'listing_records_count' => 0,
            'page'                  => 0,
            'max_pages'             => 0,
            'filters'               => [
                'sort'    => 1,
                'sort_by' => '',
            ],
            'list' => [],
        ];
    }

    public function default_others_render_call_params() : array
    {
        return [
            'columns' => [],
            'filters' => [],
        ];
    }

    public function default_cell_render_call_params() : array
    {
        return [
            'request_render_type'   => self::CELL_RENDER_HTML,
            'page_index'            => 0,
            'list_index'            => 0,
            'columns_count'         => 0,
            'record'                => false,
            'column'                => false,
            'table_field'           => false,
            'preset_content'        => '',
            'model_obj'             => null,
            'paginator_obj'         => null,
            'extra_callback_params' => false,
            'for_scope'             => false,
        ];
    }

    public function default_flow_params() : array
    {
        return [
            'form_prefix'            => '',
            'unique_id'              => str_replace('.', '_', microtime(true)),
            'term_singular'          => self::_t('record'),
            'term_plural'            => self::_t('records'),
            'listing_title'          => self::_t('Displaying results...'),
            'initial_list_arr'       => [],
            'initial_count_list_arr' => [],

            // Do not use get_count() on model when calculating number of pages (InnoDB count is very costly)
            'simulate_records_count' => false,
            // Tells if we did query database to get records already
            'did_query_database' => false,
            // If we have a model, keep model flow parameters (db_connection, table_name, etc.)
            'model_flow_params' => null,
            // Tells if current records are obtained from querying model, or they were provided
            'records_from_model' => false,

            'bulk_action'                 => '',
            'bulk_action_area'            => '',
            'display_top_bulk_actions'    => true,
            'display_bottom_bulk_actions' => true,

            // Callbacks to alter display
            'after_record_callback' => false,

            'before_filters_callback'  => null,
            'after_filters_callback'   => null,
            'before_table_callback'    => false,
            'after_table_callback'     => false,
            'after_full_list_callback' => false,

            'table_after_headers_callback' => false,
            'table_before_footer_callback' => false,
        ];
    }

    public function default_pagination_params() : array
    {
        return [
            'page_var_name'         => 'page',
            'per_page_var_name'     => 'per_page',
            'records_per_page'      => self::DEFAULT_PER_PAGE,
            'page'                  => 0,
            'offset'                => 0,
            'total_records'         => -1,
            'listing_records_count' => 0,
            'max_pages'             => 0,
            // How many pages to display left of current page before putting ... in left
            'left_pages_no' => 10,
            // How many pages to display right of current page before putting ... in right
            'right_pages_no' => 10,
            'sort'           => 0,
            'sort_by'        => '',
            'db_sort_by'     => '',
        ];
    }

    public function default_action_params() : array
    {
        return [
            'action'                     => '',
            'action_params'              => '',
            'action_result'              => '',
            'action_redirect_url_params' => false,
        ];
    }

    public function pagination_params(null | string | array $key = null, mixed $val = null) : mixed
    {
        if ($key === null && $val === null) {
            return $this->_pagination_params_arr;
        }

        if ($key !== null && $val === null) {
            if (is_array($key)) {
                $this->_pagination_params_arr = self::validate_array($key, $this->default_pagination_params());

                return $this->_pagination_params_arr;
            }

            if (is_string($key) && isset($this->_pagination_params_arr[$key])) {
                return $this->_pagination_params_arr[$key];
            }

            return null;
        }

        if (is_string($key) && isset($this->_pagination_params_arr[$key])) {
            $this->_pagination_params_arr[$key] = $val;

            return true;
        }

        return null;
    }

    public function flow_params(?array $params = null) : array
    {
        if ($params === null) {
            if (!$this->_flow_params_arr) {
                $this->_flow_params_arr = $this->default_flow_params();
            }

            return $this->_flow_params_arr;
        }

        $this->_flow_params_arr = self::validate_array_recursive($params, $this->default_flow_params());

        return $this->_flow_params_arr;
    }

    public function flow_param(string $key, $val = null) : mixed
    {
        if (empty($this->_flow_params_arr)) {
            $this->_flow_params_arr = $this->default_flow_params();
        }

        if (!isset($this->_flow_params_arr[$key])) {
            return false;
        }

        if ($val === null) {
            return $this->_flow_params_arr[$key];
        }

        $this->_flow_params_arr[$key] = $val;

        return true;
    }

    public function reset_paginator() : void
    {
        $this->_model = null;

        $this->_base_url = '';

        $this->_filters = [];
        $this->_scope = [];
        $this->_originals = [];

        $this->_flow_params_arr = $this->default_flow_params();
        $this->_pagination_params_arr = $this->default_pagination_params();
        $this->_columns_definition_arr = [];

        $this->reset_records();
    }

    /**
     * @param string $date
     * @param array $params
     *
     * @return string
     */
    public function pretty_date_independent($date, array $params = []) : string
    {
        $params['date_format'] ??= null;
        $params['request_render_type'] ??= null;

        if (empty($date)
         || !($date_time = is_db_date($date))
         || empty_db_date($date)) {
            return '';
        }

        if (!empty($params['date_format'])) {
            $date_str = @date($params['date_format'], parse_db_date($date_time));
        } else {
            $date_str = $date;
        }

        if (!empty($params['request_render_type'])) {
            switch ($params['request_render_type']) {
                case self::CELL_RENDER_JSON:
                case self::CELL_RENDER_TEXT:
                    return $date_str;
            }
        }

        // force indexes for language xgettext parser
        self::_t('in %s');
        self::_t('%s ago');

        if (($seconds_ago = seconds_passed($date_time)) < 0) {
            // date in future
            $lang_index = 'in %s';
        } else {
            // date in past
            $lang_index = '%s ago';
        }

        return '<span title="'.self::_t($lang_index, PHS_Utils::parse_period($seconds_ago, ['only_big_part' => true])).'">'.$date_str.'</span>';
    }

    public function pretty_date(array $params) : null | string
    {
        if (!($params = self::validate_array($params, $this->default_cell_render_call_params()))
         || empty($params['record']) || !is_array($params['record'])
         || empty($params['column']) || !is_array($params['column'])
         || (empty($params['column']['record_field']) && empty($params['column']['record_db_field']))) {
            return null;
        }

        $field_name = $this->get_column_name($params['column'], ($params['for_scope'] ?? PHS_Scope::SCOPE_WEB) ?: PHS_Scope::SCOPE_WEB);

        if (!$field_name
            || !array_key_exists($field_name, $params['record'])) {
            return null;
        }

        $pretty_params = [];
        $pretty_params['date_format'] = $params['column']['date_format'] ?? null;
        $pretty_params['request_render_type'] = $params['request_render_type'] ?? null;

        if (!($date_str = $this->pretty_date_independent($params['record'][$field_name], $pretty_params))) {
            return ($params['column']['invalid_value'] ?? null) ?: self::_t('N/A');
        }

        return $date_str;
    }

    public function get_checkbox_name_format() : string
    {
        if (!($flow_params_arr = $this->flow_params())) {
            return '';
        }

        return $flow_params_arr['form_prefix'].'%s_chck';
    }

    public function get_all_checkbox_name_format() : string
    {
        if (!($flow_params_arr = $this->flow_params())) {
            return '';
        }

        return $flow_params_arr['form_prefix'].'%s_chck'.self::CHECKBOXES_COLUMN_ALL_SUFIX;
    }

    public function get_listing_form_name() : string
    {
        if (!($flow_params_arr = $this->flow_params())) {
            return '';
        }

        return $flow_params_arr['form_prefix'].'paginator_list_form';
    }

    public function get_filters_form_name() : string
    {
        if (!($flow_params_arr = $this->flow_params())) {
            return '';
        }

        return $flow_params_arr['form_prefix'].'paginator_filters_form';
    }

    public function get_checkbox_name_for_column(array $column_arr) : string
    {
        if (empty($column_arr)
            || empty($column_arr['checkbox_record_index_key']['key'])
            || !$this->flow_params()) {
            return '';
        }

        if (empty($column_arr['checkbox_record_index_key']['checkbox_name'])) {
            $column_arr['checkbox_record_index_key']['checkbox_name'] = $column_arr['checkbox_record_index_key']['key'];
        }

        return @sprintf($this->get_checkbox_name_format(), $column_arr['checkbox_record_index_key']['checkbox_name']);
    }

    public function display_checkbox_column(array $params)
    {
        if (!($params = self::validate_array($params, $this->default_cell_render_call_params()))
         || empty($params['record']) || !is_array($params['record'])
         || empty($params['column']) || !is_array($params['column'])
         || !($checkbox_name = $this->get_checkbox_name_for_column($params['column']))
         || empty($params['column']['checkbox_record_index_key']['key'])
         || !isset($params['record'][$params['column']['checkbox_record_index_key']['key']])) {
            return false;
        }

        $params['preset_content'] ??= '';

        if (!empty($params['request_render_type'])) {
            switch ($params['request_render_type']) {
                case self::CELL_RENDER_JSON:
                case self::CELL_RENDER_TEXT:
                    return $params['preset_content'];
            }
        }

        $scope_arr = $this->get_scope() ?: [];

        $checkbox_value = $params['record'][$params['column']['checkbox_record_index_key']['key']];
        $checkbox_name_all = $checkbox_name.self::CHECKBOXES_COLUMN_ALL_SUFIX;

        $checkbox_checked = false;
        if ($scope_arr
            && (!empty($scope_arr[$checkbox_name_all])
                || (!empty($scope_arr[$checkbox_name])
                    && is_array($scope_arr[$checkbox_name])
                    && in_array($checkbox_value, $scope_arr[$checkbox_name])
                ))
        ) {
            $checkbox_checked = true;
        }

        ob_start();
        ?>
        <label for="<?php echo $checkbox_name; ?>" style="width:100%">
        <span style="float:left;">
            <input type="checkbox" value="<?php echo $checkbox_value; ?>" name="<?php echo $checkbox_name; ?>[]" id="<?php echo $checkbox_name.'_'.$checkbox_value; ?>"
                   rel="skin_checkbox" <?php echo $checkbox_checked ? 'checked="checked"' : ''; ?>
                   onchange="phs_paginator_update_list_all_checkbox( '<?php echo $checkbox_name.'_'.$checkbox_value; ?>', '<?php echo $checkbox_name_all; ?>' )" />
        </span>
        <?php echo $params['preset_content']; ?>
        </label>
        <?php

        return ob_get_clean();
    }

    public function base_url(?string $url = null) : string
    {
        if ($url === null) {
            return $this->_base_url;
        }

        $this->_base_url = $url;

        return $this->_base_url;
    }

    public function get_action_parameter_names() : ?array
    {
        if (!($flow_params = $this->flow_params())) {
            return null;
        }

        $action_key = $flow_params['form_prefix'].self::ACTION_PARAM_NAME;
        $action_params_key = $flow_params['form_prefix'].self::ACTION_PARAMS_PARAM_NAME;
        $action_result_key = $flow_params['form_prefix'].self::ACTION_RESULT_PARAM_NAME;

        return [
            'action'        => $action_key,
            'action_params' => $action_params_key,
            'action_result' => $action_result_key,
        ];
    }

    /**
     * @param null|string|array $action array with 'action' key saying what action should be taken and 'action_params' key action parameters
     *
     * @return null|array Array with parameters to be passed in get for action or false if no action
     */
    public function parse_action_parameter(null | string | array $action) : ?array
    {
        if (empty($action)
            || !($action_parameter_names = $this->get_action_parameter_names())) {
            return null;
        }

        $action_key = $action_parameter_names['action'];
        $action_params_key = $action_parameter_names['action_params'];
        $action_result_key = $action_parameter_names['action_result'];

        $action_args = [];
        $action_args[$action_key] = '';
        $action_args[$action_params_key] = null;
        $action_args[$action_result_key] = null;

        if (is_string($action)) {
            $action_args[$action_key] = $action;
        } elseif (is_array($action)) {
            $action_args[$action_key] = ($action['action'] ?? '') ?: '';
            if (!empty($action['action_params'])) {
                // try sending arrays as parameters (although not recommended)
                if (!is_string($action['action_params'])) {
                    $action['action_params'] = urlencode(@json_encode($action['action_params']));
                }

                $action_args[$action_params_key] = $action['action_params'];
            }
        }

        if (!empty($action['action_result'])) {
            $action_args[$action_result_key] = $action['action_result'];
        }

        if (empty($action_args[$action_key])) {
            return null;
        }

        return $action_args;
    }

    /**
     * @param null|array $params
     *
     * @return string
     */
    public function get_full_url(?array $params = null) : string
    {
        if (empty($this->_originals)) {
            $this->extract_filters_scope();
        }

        $params ??= [];
        $params['include_pagination_params'] = !isset($params['include_pagination_params']) || !empty($params['include_pagination_params']);
        $params['include_action_params'] = !isset($params['include_action_params']) || !empty($params['include_action_params']);
        $params['include_filters'] = !isset($params['include_filters']) || !empty($params['include_filters']);

        if (empty($params['extra_params']) || !is_array($params['extra_params'])) {
            $params['extra_params'] = [];
        }

        if (empty($params['action'])) {
            $params['action'] = null;
        }

        if (!isset($params['force_scope']) || !is_array($params['force_scope'])) {
            $params['force_scope'] = $this->_scope;
        }

        if (!$params['include_action_params']
            || !($action_params = $this->parse_action_parameter($params['action']))) {
            $action_params = null;
        }

        if (isset($params['sort'])) {
            $params['sort'] = (!empty($params['sort']) ? 1 : 0);
        }
        if (isset($params['sort_by'])) {
            if (is_string($params['sort_by'])) {
                $params['sort_by'] = trim($params['sort_by']);
            } else {
                unset($params['sort_by']);
            }
        }

        $query_arr = [];
        if ($params['include_filters']
            && !empty($params['force_scope'])) {
            $query_arr = array_merge($query_arr, $params['force_scope']);
        }

        if ($params['include_pagination_params']
         && ($flow_params = $this->flow_params())
         && ($pagination_params = $this->pagination_params())) {
            $add_args = [];
            $add_args[$flow_params['form_prefix'].$pagination_params['page_var_name']] = $pagination_params['page'];
            $add_args[$flow_params['form_prefix'].$pagination_params['per_page_var_name']] = $pagination_params['records_per_page'];
            $add_args[$flow_params['form_prefix'].'sort_by'] = ($params['sort_by'] ?? $pagination_params['sort_by']);
            $add_args[$flow_params['form_prefix'].'sort'] = ($params['sort'] ?? $pagination_params['sort']);

            $query_arr = array_merge($query_arr, $add_args);
        }

        if (!empty($params['extra_params'])) {
            $query_arr = array_merge($query_arr, $params['extra_params']);
        }

        $url = $this->_base_url;

        if (!str_contains($url, '?')) {
            $url .= '?';
        }

        $query_string = '';

        // Don't run $action_params through http_build_query as values will be rawurlencoded,
        // and we might add javascript code in parameters
        // e.g. action_params might be an id passed as javascript function parameter
        if ($params['include_action_params']
            && $action_params && is_array($action_params)) {
            foreach ($action_params as $key => $val) {
                if ($val === null) {
                    continue;
                }

                if (isset($query_arr[$key])) {
                    unset($query_arr[$key]);
                }

                $query_string .= ($query_string !== '' ? '&' : '').$key.'='.$val;
            }
        }

        $query_string .= ($query_string !== '' ? '&' : '').array_to_query_string($query_arr);

        $url .= '&'.$query_string;

        return $url;
    }

    public function get_records() : array
    {
        return $this->_records_arr;
    }

    public function get_query_id()
    {
        return $this->_query_id;
    }

    public function set_query_id($qid) : bool
    {
        $this->reset_records();

        if (!$qid) {
            $qid = null;
        }

        $model_flow_params = $this->flow_param('model_flow_params');

        $this->_query_id = $qid;

        $records_count = 0;
        if ($qid
            && (!$this->flow_param('simulate_records_count')
                || !($records_count = db_num_rows($qid, $model_flow_params['db_connection'] ?? false)))
        ) {
            $records_count = 0;
        }

        $this->pagination_params('listing_records_count', $records_count);

        return true;
    }

    public function set_records(array $records_arr) : void
    {
        $this->reset_records();

        $this->pagination_params('listing_records_count', count($records_arr));

        $this->_records_arr = $records_arr;
    }

    public function format_api_export(mixed $value, array $column_arr, ?int $for_scope = null) : ?array
    {
        if (empty($column_arr['api_export']) || !is_array($column_arr['api_export'])) {
            return null;
        }

        $api_export = self::validate_array($column_arr['api_export'], $this->default_api_export_fields());

        if (($new_value = PHS_Params::set_type($value, $api_export['type'], $api_export['type_extra'])) !== null) {
            $value = $new_value;
        } elseif ($api_export['invalid_value'] !== null) {
            $value = $api_export['invalid_value'];
        }

        if (!empty($api_export['field_name'])) {
            $field_name = $api_export['field_name'];
        } else {
            $field_name = $this->get_column_name($column_arr, $for_scope);
        }

        return [
            'key'   => $field_name,
            'value' => $value,
        ];
    }

    public function default_api_export_fields() : array
    {
        return [
            // if left empty, resulting field name will be used
            'field_name' => '',
            // if left empty, resulting field name will be used
            'invalid_value' => null,
            // to what should be the value formatted
            'type'       => PHS_Params::T_ASIS,
            'type_extra' => false,
        ];
    }

    public function default_column_fields() : array
    {
        return [
            'column_title' => '',
            // Extra text to add after column_title (outsize sort link)
            'column_title_extra' => '',
            // 'record_db_field' is record array key (SELECT field AS my_alias...) => 'record_db_field' = 'my_alias'
            'record_db_field' => '',
            // 'record_field' is always what we send to database...
            'record_field' => '',
            // Special for API scope. Check if we have a key defined in record and use directly that value as output
            'record_api_field' => '',

            // Tells how to export record field to api reponse (if required)
            'api_export' => false,

            // 'key': should contain key in record fields that should be put as value in checkbox (it also defined checkbox name)
            // 'checkbox_name': string used to form input name, if empty 'key' will be used as 'checkbox_name' ({form_prefix}{checkbox_name}_chck)
            // 'type': is a PHS_Params::T_* which will be used to validate input value
            'checkbox_record_index_key' => [
                'key'           => '',
                'checkbox_name' => '',
                'type'          => PHS_Params::T_ASIS,
            ],
            'sortable' => true,
            // 0 or 1 if default sorting 0 - ascending or 1 - descending
            'default_sort' => false,
            // In case we have an array of key-values and key is found in field of current record
            'display_key_value' => false,
            // in case we want a special display for this cell
            'display_callback' => false,
            // Extra parameters to be sent to display callback (if display_callback is present)
            'extra_callback_params' => false,
            // in case field is a date what format should the date be displayed in?
            'date_format' => '',
            // What should be displayed if value in column is not something valid
            'invalid_value' => null,
            // For which scopes to hide the column (array of scopes)
            'hide_for_scopes' => false,
            // Show this column only for provided scopes (array of scopes)
            'show_for_scopes' => false,
            // Header columns styling
            'extra_style'   => '',
            'extra_classes' => '',
            // Raw attributes to be added to header td
            'raw_attrs' => '',
            // If column will be custom rendered and will span over more columns, provide the number of columns span here
            'column_colspan' => 1,
            // Record lines styling
            'extra_records_style'   => '',
            'extra_records_classes' => '',
            // Raw attributes to be added to record td
            'raw_records_attrs' => '',
        ];
    }

    public function set_columns(array $columns_arr) : ?array
    {
        $this->reset_columns();

        if (empty($columns_arr)) {
            $this->set_error(self::ERR_COLUMNS, self::_t('Bad columns format.'));

            return null;
        }

        $new_columns = [];
        $default_column_fields = $this->default_column_fields();
        foreach ($columns_arr as $column) {
            if (empty($column)
             || !is_array($column)
             || !($new_column = self::validate_array_to_new_array_recursive($column, $default_column_fields))) {
                continue;
            }

            if (empty($new_column['column_title'])) {
                $this->set_error(self::ERR_COLUMNS, self::_t('Please provide a column_title for all columns.'));

                return null;
            }

            $new_columns[] = $new_column;
        }

        $this->_columns_definition_arr = $new_columns;

        return $this->_columns_definition_arr;
    }

    public function reset_columns() : void
    {
        $this->_columns_definition_arr = [];
    }

    public function get_columns() : array
    {
        return $this->_columns_definition_arr;
    }

    /**
     * @param null|int $scope
     *
     * @return array
     */
    public function get_columns_for_scope(?int $scope = null) : array
    {
        $columns_arr = $this->get_columns();

        if ($scope === null) {
            $scope = PHS_Scope::current_scope();
        }

        if (!PHS_Scope::valid_scope($scope)) {
            return $columns_arr;
        }

        $scope_columns_arr = $columns_arr;
        if (!empty($columns_arr)) {
            $scope_columns_arr = [];
            foreach ($columns_arr as $column_arr) {
                if ((!empty($column_arr['hide_for_scopes']) && is_array($column_arr['hide_for_scopes'])
                        && in_array($scope, $column_arr['hide_for_scopes'], true))
                    || (!empty($column_arr['show_for_scopes']) && is_array($column_arr['show_for_scopes'])
                        && !in_array($scope, $column_arr['show_for_scopes'], true))
                ) {
                    continue;
                }

                $scope_columns_arr[] = $column_arr;
            }
        }

        return $scope_columns_arr;
    }

    /**
     * @param array $filters_arr
     *
     * @return null|array
     */
    public function set_filters(array $filters_arr) : ?array
    {
        $this->reset_filters();

        if (empty($filters_arr)) {
            $this->set_error(self::ERR_FILTERS, self::_t('Bad filters format.'));

            return null;
        }

        $new_filters = [];
        $default_filters_fields = self::default_filter_fields();
        foreach ($filters_arr as $filter) {
            if (!($new_filter = self::validate_array_to_new_array($filter, $default_filters_fields))) {
                continue;
            }

            if (empty($new_filter['var_name'])
             || (empty($new_filter['record_field'])
                 && empty($new_filter['switch_filter'])
                 && empty($new_filter['raw_query'])
                 && (empty($new_filter['check_callback']) || !@is_callable($new_filter['check_callback']))
             )
            ) {
                $this->set_error(self::ERR_FILTERS, self::_t('var_name or (record_field, raw_query, switch_filter, check_callback) not provided for %s filter.',
                    (!empty($new_filter['display_name']) ? $new_filter['display_name'] : '(???)')));

                return null;
            }

            if (!empty($new_filter['autocomplete'])
             && (!is_array($new_filter['autocomplete'])
                 || empty($new_filter['autocomplete']['action'])
                 || !($new_filter['autocomplete']['action'] instanceof PHS_Action_Autocomplete)
             )) {
                $this->set_error(self::ERR_FILTERS, self::_t('Filter %s doesn\'t have a valid autocomplete action.',
                    (!empty($new_filter['display_name']) ? $new_filter['display_name'] : '(???)')));

                return null;
            }

            $new_filters[] = $new_filter;
        }

        $this->_filters = $new_filters;

        return $this->_filters;
    }

    public function reset_filters() : void
    {
        $this->_filters = [];
    }

    public function get_filters() : array
    {
        return $this->_filters;
    }

    /**
     * @param array $actions_arr
     *
     * @return null|array
     */
    public function set_bulk_actions(array $actions_arr) : ?array
    {
        $this->reset_bulk_actions();

        if (empty($actions_arr)) {
            $this->set_error(self::ERR_BULK_ACTIONS, self::_t('Bad bulk actions format.'));

            return null;
        }

        $new_actions = [];
        $default_actions_fields = self::default_bulk_actions_fields();
        foreach ($actions_arr as $action) {
            if (!($new_action = self::validate_array_to_new_array($action, $default_actions_fields))) {
                continue;
            }

            if (empty($new_action['action']) || empty($new_action['js_callback'])) {
                $this->set_error(self::ERR_FILTERS, self::_t('No action or js_callback provided for bulk action %s.', (!empty($new_action['display_name']) ? $new_action['display_name'] : '(???)')));

                return null;
            }

            $new_actions[] = $new_action;
        }

        $this->_bulk_actions = $new_actions;

        return $this->_bulk_actions;
    }

    public function get_bulk_action_select_name() : string
    {
        if (!($flow_params_arr = $this->flow_params())) {
            return '';
        }

        return $flow_params_arr['form_prefix'].'bulk_action';
    }

    public function reset_bulk_actions() : void
    {
        $this->_bulk_actions = [];
    }

    public function get_bulk_actions() : array
    {
        return $this->_bulk_actions;
    }

    public function get_scope() : array
    {
        if (empty($this->_originals)) {
            $this->extract_filters_scope();
        }

        return $this->_scope;
    }

    public function get_originals() : array
    {
        if (empty($this->_originals)) {
            $this->extract_filters_scope();
        }

        return $this->_originals;
    }

    public function reset_model() : void
    {
        $this->_model = null;
    }

    public function get_model() : ?PHS_Model
    {
        return $this->_model;
    }

    /**
     * @param PHS_Model $model Model object which should provide records for listing
     */
    public function set_model(PHS_Model $model) : bool
    {
        $this->reset_error();

        $this->_model = $model;

        return true;
    }

    public function get_current_action() : array
    {
        if (empty($this->_action)) {
            $this->extract_action_from_request();
        }

        return $this->_action;
    }

    public function get_filters_result() : string | array
    {
        if (empty($this->_originals)) {
            $this->extract_filters_scope();
        }

        if (!($filters_buffer = $this->render_template('paginator_filters'))) {
            if (PHS_Scope::current_scope() === PHS_Scope::SCOPE_API) {
                $filters_buffer = [];
            } else {
                $filters_buffer = self::_t('Error obtaining filters buffer.').' - '.$this->get_error_message();
            }
        }

        return $filters_buffer;
    }

    public function set_records_count(int $count) : void
    {
        $page = $this->pagination_params('page');
        $records_per_page = $this->_get_records_per_page();

        $max_pages = ceil($count / $records_per_page);

        $offset = ($page * $records_per_page);

        $this->pagination_params('records_per_page', $records_per_page);
        $this->pagination_params('page', $page);
        $this->pagination_params('total_records', $count);
        $this->pagination_params('max_pages', $max_pages);
        $this->pagination_params('offset', $offset);
    }

    public function reset_record_data(array $record_data) : array
    {
        if (!$record_data) {
            return $this->default_export_record_data();
        }

        $record_data['record_arr'] = [];
        $record_data['record_buffer'] = '';

        return $record_data;
    }

    public function default_export_record_data() : array
    {
        return [
            // Tells if current "record" to be parsed is the actual header of export
            'is_header' => false,
            // Index of record in records array
            'record_index' => 0,
            // Counter of current record in export list
            'record_count' => 0,
            // Actual record as array after "rendering" contents
            'record_arr' => [],
            // Record after parsing its content for output
            'record_buffer' => '',
        ];
    }

    public function export_result_array() : array
    {
        return [
            'export_file_dir'  => '',
            'export_file_name' => '',
            // Full location to export file
            'export_full_file_path' => '',
            // How many successful exports
            'exports_successful' => 0,
            'exports_failed'     => 0,
        ];
    }

    public function do_export_records(array $params = []) : ?array
    {
        $this->reset_error();

        $export_action_scope = PHS_Scope::SCOPE_WEB;

        if (!($columns_arr = $this->get_columns_for_scope($export_action_scope))) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('No columns defined for paginator. Export failed.'));

            return null;
        }

        if (empty($params['filter_records_fields']) || !is_array($params['filter_records_fields'])) {
            $params['filter_records_fields'] = false;
        }

        $params['ignore_headers'] = !empty($params['ignore_headers']);

        if (empty($params['model_query_params'])) {
            $params['model_query_params'] = false;
        }

        if (empty($params['request_render_type'])
            || !self::valid_render_type($params['request_render_type'])) {
            $params['request_render_type'] = self::CELL_RENDER_TEXT;
        }

        if (empty($params['exporter_library_params']) || !is_array($params['exporter_library_params'])) {
            $params['exporter_library_params'] = false;
        }

        if (empty($params['exporter_library'])) {
            $exporter_library_params = [];
            $exporter_library_params['full_class_name'] = PHS_Paginator_exporter_csv::class;
            $exporter_library_params['init_params'] = $params['exporter_library_params'];

            if (!($params['exporter_library'] = PHS::load_core_library('phs_paginator_exporter_csv', $exporter_library_params))) {
                $this->copy_or_set_static_error(self::ERR_FUNCTIONALITY, self::_t('Error loading default CSV export library.'));

                return null;
            }
        }

        /** @var PHS_Paginator_exporter_library $exporter_library_obj */
        if (!($exporter_library_obj = $params['exporter_library'])
            || !($exporter_library_obj instanceof PHS_Paginator_exporter_library)) {
            $this->set_error(self::ERR_FUNCTIONALITY, self::_t('Provided library is not a paginator export library.'));

            return null;
        }

        $exporter_library_obj->paginator_obj($this);

        if (!$this->query_records_for_export($params['model_query_params'])) {
            $this->set_error_if_not_set(self::ERR_FUNCTIONALITY, self::_t('Error querying model for records to export.'));

            return null;
        }

        if (!$exporter_library_obj->start_output()) {
            $this->copy_or_set_error($exporter_library_obj,
                self::ERR_FUNCTIONALITY, self::_t('Error calling exporting library output start method.'));

            return null;
        }

        $record_data = $this->default_export_record_data();

        if (empty($params['ignore_headers'])
            && ($header_arr = $this->get_columns_header_as_array($export_action_scope))) {
            $record_data['is_header'] = true;
            $record_data['record_arr'] = $header_arr;

            if (!($record_data['record_buffer'] = $exporter_library_obj->record_to_buffer($record_data))) {
                $record_data['record_buffer'] = '';
            }

            if (!$exporter_library_obj->record_to_output($record_data)) {
                $exporter_library_obj->finish_output();

                $this->copy_or_set_error($exporter_library_obj,
                    self::ERR_FUNCTIONALITY, self::_t('Error outputing header for export.'));

                return null;
            }
        }

        $records_arr = $this->get_records();
        $query_id = $this->get_query_id();

        $return_arr = $this->export_result_array();
        $return_arr['export_file_name'] = $exporter_library_obj->export_registry('export_file_name');
        $return_arr['export_file_dir'] = $exporter_library_obj->export_registry('export_file_dir');
        $return_arr['export_full_file_path'] = $exporter_library_obj->export_registry('export_full_file_path');

        // sanity check
        if (!$query_id) {
            $query_id = null;
        }

        if (!$records_arr && !$query_id) {
            $exporter_library_obj->finish_output();

            return $return_arr;
        }

        // Records have query fields in keys (usually unique ids, but not necessary consecutive)
        $records_keys_arr = false;
        $records_keys_index = 0;
        if ($records_arr) {
            $records_keys_arr = array_keys($records_arr);
        }

        $record_index = '';
        // Record count starts from 1 (header is 0)
        $record_count = 0;

        //
        // !!! Force Web scope for exporting
        //

        $cell_render_params = $this->default_cell_render_call_params();
        $cell_render_params['request_render_type'] = $params['request_render_type'];
        $cell_render_params['page_index'] = 0;
        $cell_render_params['list_index'] = 0;
        $cell_render_params['columns_count'] = count($columns_arr);
        $cell_render_params['record'] = false;
        $cell_render_params['column'] = false;
        $cell_render_params['table_field'] = false;
        $cell_render_params['preset_content'] = '';
        $cell_render_params['model_obj'] = $this->get_model();
        $cell_render_params['paginator_obj'] = $this;
        $cell_render_params['for_scope'] = $export_action_scope;

        $fields_filters = false;

        $model_flow_params = $this->flow_param('model_flow_params');
        $db_connection = $model_flow_params['db_connection'] ?? false;

        $record_data['is_header'] = false;
        while (true) {
            $record_data = $this->reset_record_data($record_data);
            $record_count++;

            if (!empty($records_keys_arr)) {
                // get record from $records_arr array
                if (!isset($records_keys_arr[$records_keys_index])
                 || !($db_record_arr = $records_arr[$records_keys_arr[$records_keys_index]])) {
                    $db_record_arr = false;
                } else {
                    $record_index = $records_keys_arr[$records_keys_index];
                    $records_keys_index++;
                }
            } elseif (!($db_record_arr = @db_fetch_assoc($query_id, $db_connection))) {
                $db_record_arr = false;
            }

            if (empty($db_record_arr)) {
                break;
            }

            $cell_render_params['page_index'] = $record_count;
            $cell_render_params['list_index'] = $record_count;
            $cell_render_params['record'] = $db_record_arr;
            $cell_render_params['table_field'] = false;
            $cell_render_params['preset_content'] = '';

            $record_arr = [];
            foreach ($columns_arr as $column_arr) {
                if (!$column_arr || !is_array($column_arr)) {
                    continue;
                }

                $cell_render_params['column'] = $column_arr;

                if (!($field_name = $this->get_column_name($column_arr, PHS_Scope::SCOPE_WEB))) {
                    $field_name = false;
                }

                if (($cell_content = $this->render_column_for_record($cell_render_params)) === null) {
                    $cell_content = '!'.$this::_t('Failed rendering cell').'!';
                }

                if ($field_name !== false) {
                    $record_arr[$field_name] = $cell_content;
                } else {
                    $record_arr[] = $cell_content;
                }
            }

            // Validate selection filters based on record field keys
            if ($fields_filters === false
            && !empty($params['filter_records_fields'])) {
                $fields_filters = [];
                foreach ($params['filter_records_fields'] as $field_key => $filter_values) {
                    if (!isset($record_arr[$field_key])
                        || empty($filter_values) || !is_array($filter_values)) {
                        continue;
                    }

                    $fields_filters[$field_key] = $filter_values;
                }
            }

            if (!empty($fields_filters)) {
                $should_continue = false;
                foreach ($fields_filters as $field_key => $filter_values) {
                    if (isset($record_arr[$field_key])
                        && !in_array($record_arr[$field_key], $filter_values)) {
                        $should_continue = true;
                        break;
                    }
                }

                if ($should_continue) {
                    continue;
                }
            }

            $record_data['record_count'] = $record_count;
            $record_data['record_index'] = $record_index;
            $record_data['record_arr'] = $record_arr;

            if (!($record_data['record_buffer'] = $exporter_library_obj->record_to_buffer($record_data))) {
                $return_arr['exports_failed']++;
                continue;
            }

            if (!$exporter_library_obj->record_to_output($record_data)) {
                $return_arr['exports_failed']++;
                continue;
            }
        }

        if (!$exporter_library_obj->finish_output()) {
            $this->copy_or_set_error($exporter_library_obj,
                self::ERR_FUNCTIONALITY, self::_t('Error calling exporting library output finish method.'));

            return null;
        }

        return $return_arr;
    }

    public function get_columns_header_as_array(?int $scope = null) : array
    {
        if ($scope === null) {
            $scope = PHS_Scope::current_scope();
        }

        $columns_arr = $this->get_columns_for_scope($scope) ?: [];

        $return_arr = [];
        foreach ($columns_arr as $column_array_index => $column_arr) {
            if (empty($column_arr) || !is_array($column_arr)) {
                continue;
            }

            $return_arr[$column_array_index] = ($column_arr['column_title'] ?? '') ?: '';
        }

        return $return_arr;
    }

    public function query_model_for_records(array $params = []) : bool
    {
        $this->reset_error();

        $params['force'] = !empty($params['force']);
        $params['store_query_id'] = !empty($params['store_query_id']);

        if ($params['store_query_id']) {
            $params['ignore_query_limit'] = !empty($params['ignore_query_limit']);
        }

        $current_records = $this->get_records();

        if (empty($params['force'])
            && (!empty($current_records)
                || $this->flow_param('did_query_database'))) {
            return true;
        }

        if (!empty($params['force'])
            && !empty($current_records)
            && !$this->flow_param('records_from_model')) {
            return true;
        }

        $this->reset_records();

        if (!($model_obj = $this->get_model())) {
            $this->set_error(self::ERR_MODEL, self::_t('Model not set. Nothing to query.'));

            return false;
        }

        $scope_arr = $this->get_scope() ?: [];
        $filters_arr = $this->get_filters() ?: [];

        $initial_fields = [];
        if (!($list_arr = $this->flow_param('initial_list_arr'))
            || !is_array($list_arr)) {
            $list_arr = [];
        } elseif (!empty($list_arr['fields'])
                  && is_array($list_arr['fields'])) {
            foreach ($list_arr['fields'] as $field_name => $field_val) {
                $initial_fields[$field_name] = true;
            }
        }

        $linkage_func = 'AND';
        if (!empty($list_arr['fields']['{linkage_func}'])
            && in_array(strtolower($list_arr['fields']['{linkage_func}']), ['and', 'or'], true)) {
            $linkage_func = strtoupper($list_arr['fields']['{linkage_func}']);
        }

        if (!($count_list_arr = $this->flow_param('initial_count_list_arr'))
            || !is_array($count_list_arr)) {
            $count_list_arr = $list_arr;
        }

        $list_arr['extra_sql'] = '';
        $count_list_arr['extra_sql'] = '';
        $fields_to_be_removed = [];

        foreach ($filters_arr as $filter_arr) {
            if (empty($filter_arr['var_name'])) {
                continue;
            }

            if (($filter_callback = $filter_arr['check_callback'] ?? null)) {
                if (!@is_callable($filter_callback)
                    || !($filter_callback_result = $filter_callback($filter_arr, $scope_arr[$filter_arr['var_name']] ?? null))) {
                    continue;
                }

                if (!empty($filter_callback_result['filter'])
                   && is_array($filter_callback_result['filter'])) {
                    $filter_arr = $filter_callback_result['filter'];
                }

                if (is_array($filter_callback_result)
                   && array_key_exists('scope', $filter_callback_result)) {
                    $scope_arr[$filter_arr['var_name']] = $filter_callback_result['scope'];
                }
            }

            // Accept empty $filter_arr['record_field'], but this means it will be a raw query or switch filter...
            if ((empty($filter_arr['record_field']) && empty($filter_arr['switch_filter']) && empty($filter_arr['raw_query']))
             || !isset($scope_arr[$filter_arr['var_name']])
             || ($filter_arr['default'] !== false && $scope_arr[$filter_arr['var_name']] == $filter_arr['default'])) {
                continue;
            }

            // If we have a "switch" filter, check value for provided filter and merge any options for specific case
            if (!empty($filter_arr['switch_filter']) && is_array($filter_arr['switch_filter'])) {
                if (empty($filter_arr['switch_filter'][$scope_arr[$filter_arr['var_name']]])
                 || !is_array($filter_arr['switch_filter'][$scope_arr[$filter_arr['var_name']]])) {
                    continue;
                }

                $new_filter = $filter_arr['switch_filter'][$scope_arr[$filter_arr['var_name']]];
                foreach (['var_name', 'default'] as $key) {
                    if (isset($new_filter[$key])) {
                        unset($new_filter[$key]);
                    }
                }

                $filter_arr = self::merge_array_assoc($filter_arr, $new_filter);
            }

            if (!empty($filter_arr['raw_record_check']) && is_array($filter_arr['raw_record_check'])) {
                $check_value = $filter_arr['raw_record_check'];
            } elseif (!empty($filter_arr['record_check']) && is_array($filter_arr['record_check'])) {
                if (isset($filter_arr['record_check']['value'])
                    && str_contains($filter_arr['record_check']['value'], '%s')) {
                    if (is_array($scope_arr[$filter_arr['var_name']])) {
                        $final_value = implode(',', $scope_arr[$filter_arr['var_name']]);
                    } else {
                        $final_value = $scope_arr[$filter_arr['var_name']];
                    }

                    $check_value = $filter_arr['record_check'];

                    // Convert all %s into filter value... Also make sure %% won't be also replaced
                    $check_value['value'] = self::sprintf_all($filter_arr['record_check']['value'], $final_value);
                }

                // more complex linkage...
                elseif (isset($filter_arr['record_check']['fields'])) {
                    $check_model_obj = $model_obj;
                    if (!empty($filter_arr['record_check_model'])
                        && ($filter_arr['record_check_model'] instanceof PHS_Model)) {
                        $check_model_obj = $filter_arr['record_check_model'];
                    }

                    if (($linkage_params = $check_model_obj->get_query_fields($filter_arr['record_check']))
                     && !empty($linkage_params['extra_sql'])) {
                        if (is_array($scope_arr[$filter_arr['var_name']])) {
                            $final_value = implode(',', $scope_arr[$filter_arr['var_name']]);
                        } else {
                            $final_value = $scope_arr[$filter_arr['var_name']];
                        }

                        $linkage_params['extra_sql'] = self::sprintf_all($linkage_params['extra_sql'], $final_value);

                        // In case we have complex linkages...
                        $list_arr['extra_sql'] .= (!empty($list_arr['extra_sql']) ? ' '.$linkage_func.' ' : '').' ('.$linkage_params['extra_sql'].')';
                        $count_list_arr['extra_sql'] .= (!empty($count_list_arr['extra_sql']) ? ' '.$linkage_func.' ' : '').' ('.$linkage_params['extra_sql'].')';

                        continue;
                    }
                }
            }

            if (empty($check_value)
             || (empty($filter_arr['record_check']) && empty($filter_arr['raw_record_check']))) {
                $check_value = $scope_arr[$filter_arr['var_name']];
            }

            // Accept empty $filter_arr['record_field'], but this means it will be a raw query...
            if (empty($filter_arr['record_field'])) {
                if (!empty($filter_arr['raw_query'])) {
                    if (str_contains($filter_arr['raw_query'], '%s')) {
                        $filter_arr['raw_query'] = self::sprintf_all($filter_arr['raw_query'], $scope_arr[$filter_arr['var_name']]);
                    }

                    $list_arr['fields'][] = ['raw' => $filter_arr['raw_query']];
                    $count_list_arr['fields'][] = ['raw' => $filter_arr['raw_query']];
                }

                if (!empty($filter_arr['remove_fields']) && is_array($filter_arr['remove_fields'])) {
                    foreach ($filter_arr['remove_fields'] as $rem_field) {
                        $fields_to_be_removed[$rem_field] = true;
                    }
                }
            } else {
                // If in initial list we were passed predefined filters and now we have an end-user filter,
                // discard predefined filter and use what end-user passed us
                if (!empty($initial_fields)
                 && !empty($initial_fields[$filter_arr['record_field']])
                 && isset($list_arr['fields'][$filter_arr['record_field']])) {
                    unset($list_arr['fields'][$filter_arr['record_field']]);
                    if (isset($count_list_arr['fields'][$filter_arr['record_field']])) {
                        unset($count_list_arr['fields'][$filter_arr['record_field']]);
                    }
                    unset($initial_fields[$filter_arr['record_field']]);

                    if (empty($initial_fields)) {
                        $initial_fields = [];
                    }
                }

                // 'record_field' is always what we send to database...
                if (isset($list_arr['fields'][$filter_arr['record_field']])) {
                    if (!is_array($list_arr['fields'][$filter_arr['record_field']])
                     || empty($list_arr['fields'][$filter_arr['record_field']][0])) {
                        $list_arr['fields'][$filter_arr['record_field']] = [$list_arr['fields'][$filter_arr['record_field']]];
                        $count_list_arr['fields'][$filter_arr['record_field']] = [$count_list_arr['fields'][$filter_arr['record_field']]];
                    }

                    $list_arr['fields'][$filter_arr['record_field']][] = $check_value;
                    $count_list_arr['fields'][$filter_arr['record_field']][] = $check_value;

                    if (!empty($filter_arr['linkage_func'])) {
                        $list_arr['fields'][$filter_arr['record_field']]['linkage_func'] = $filter_arr['linkage_func'];
                        $count_list_arr['fields'][$filter_arr['record_field']]['linkage_func'] = $filter_arr['linkage_func'];
                    }
                } else {
                    $list_arr['fields'][$filter_arr['record_field']] = $check_value;
                    $count_list_arr['fields'][$filter_arr['record_field']] = $check_value;
                }
            }
        }

        if (!empty($fields_to_be_removed)) {
            foreach ($fields_to_be_removed as $rem_field => $junk) {
                if (array_key_exists($rem_field, $list_arr['fields'])) {
                    unset($list_arr['fields'][$rem_field]);
                }
                if (array_key_exists($rem_field, $count_list_arr['fields'])) {
                    unset($count_list_arr['fields'][$rem_field]);
                }
            }
        }

        $model_flow_params = $model_obj->fetch_default_flow_params($list_arr);

        $this->flow_param('model_flow_params', $model_flow_params);
        $this->flow_param('did_query_database', true);
        $this->flow_param('records_from_model', true);

        if ($this->flow_param('simulate_records_count')) {
            $records_count = $this->_simulate_records_count_based_on_page();
        } elseif (!($records_count = $model_obj->get_count($count_list_arr))) {
            // Set count of total records to 0
            $this->set_records_count(0);

            return true;
        }

        $this->set_records_count($records_count);

        if (!empty($params['ignore_query_limit'])) {
            $list_arr['offset'] = 0;
            $list_arr['enregs_no'] = 10000000000;
        } else {
            $list_arr['offset'] = $this->pagination_params('offset');
            $list_arr['enregs_no'] = $this->pagination_params('records_per_page');
        }

        $sort = $this->pagination_params('sort');

        $sort_type_added = false;
        if (($db_sort_by = $this->pagination_params('db_sort_by'))
        && is_string($db_sort_by)) {
            if (str_contains($db_sort_by, '%s')) {
                $db_sort_by = str_replace('%s', (empty($sort) ? 'ASC' : 'DESC'), $db_sort_by);
                $sort_type_added = true;
            }

            $list_arr['order_by'] = $db_sort_by;
        } elseif (($sort_by = $this->pagination_params('sort_by'))
        && is_string($sort_by)) {
            $list_arr['order_by'] = (!str_contains($sort_by, '.')
                    ? '`'.$model_obj->get_flow_table_name($model_flow_params).'`.' : '').$sort_by;
        }

        if (!empty($list_arr['order_by'])
            && empty($sort_type_added)) {
            $list_arr['order_by'] .= ' '.(empty($sort) ? 'ASC' : 'DESC');
        }

        if (!empty($params['store_query_id'])) {
            $list_arr['get_query_id'] = true;
        }

        $query_result = $model_obj->get_list($list_arr) ?: null;

        if (!empty($params['store_query_id'])) {
            $this->set_query_id($query_result);
        } else {
            if (!$query_result) {
                $query_result = [];
            }

            $this->set_records($query_result);
        }

        return true;
    }

    public function get_column_name(array $column_arr, ?int $for_scope = null) : ?string
    {
        if (!$column_arr) {
            return null;
        }

        if ($for_scope === null) {
            $for_scope = PHS_Scope::current_scope();
        }

        $column_name = null;
        if ($for_scope === PHS_Scope::SCOPE_API
            && !empty($column_arr['record_api_field'])) {
            $column_name = $column_arr['record_api_field'];
        } elseif (!empty($column_arr['record_db_field']) || !empty($column_arr['record_field'])) {
            $column_name = ($column_arr['record_db_field'] ?? null) ?: $column_arr['record_field'];
        }

        return $column_name;
    }

    public function render_column_for_record(array $render_params)
    {
        $render_params ??= [];
        $render_params['for_scope'] = (int)($render_params['for_scope'] ?? 0);

        if (empty($render_params['request_render_type'])
            || !self::valid_render_type($render_params['request_render_type'])) {
            $render_params['request_render_type'] = self::CELL_RENDER_HTML;
        }

        if (empty($render_params['record']) || !is_array($render_params['record'])
            || empty($render_params['column']) || !is_array($render_params['column'])) {
            return '!'.self::_t('Unkown column or invalid record').'!';
        }

        $column_arr = $render_params['column'];
        $record_arr = $render_params['record'];

        if (!($model_obj = $this->get_model())) {
            $model_obj = null;
        }

        if (!($field_name = $this->get_column_name($column_arr, $render_params['for_scope']))) {
            $field_name = false;
        }

        $field_exists_in_record = false;
        if (!empty($field_name)
        && array_key_exists($field_name, $record_arr)) {
            $field_exists_in_record = true;
        }

        $cell_content = null;
        if (empty($column_arr['record_field'])
        && empty($column_arr['record_db_field'])
        && empty($column_arr['record_api_field'])
        && empty($column_arr['display_callback'])) {
            $cell_content = '!'.self::_t('Bad column setup').'!';
        } elseif ($render_params['for_scope'] !== PHS_Scope::SCOPE_API
             || empty($field_exists_in_record)) {
            if (!empty($column_arr['display_key_value'])
            && is_array($column_arr['display_key_value'])
            && !empty($field_name)
            && isset($record_arr[$field_name])) {
                if (isset($column_arr['display_key_value'][$record_arr[$field_name]])) {
                    $cell_content = $column_arr['display_key_value'][$record_arr[$field_name]];
                } elseif ($column_arr['invalid_value'] !== null) {
                    $cell_content = $column_arr['invalid_value'];
                }
            } elseif (!empty($model_obj)
                && !empty($field_name)
                && $field_exists_in_record
                && ($field_details = $model_obj->table_field_details($field_name))
                && is_array($field_details)) {
                switch ($field_details['type']) {
                    case $model_obj::FTYPE_DATETIME:
                    case $model_obj::FTYPE_DATE:
                        if (empty_db_date($record_arr[$field_name])) {
                            $cell_content = null;
                            if (empty($column_arr['invalid_value'])) {
                                $column_arr['invalid_value'] = self::_t('N/A');
                            }
                        } elseif (!empty($column_arr['date_format'])) {
                            $cell_content = @date($column_arr['date_format'], parse_db_date($record_arr[$field_name]));
                        }
                        break;
                }
            }
        }

        if ($cell_content === null
        && !empty($field_name)
        && $field_exists_in_record) {
            $cell_content = $record_arr[$field_name] ?? $column_arr['invalid_value'] ?? '';
        }

        if (($cell_content === null || $render_params['for_scope'] !== PHS_Scope::SCOPE_API)
        && !empty($column_arr['display_callback'])) {
            if (!@is_callable($column_arr['display_callback'])) {
                $cell_content = '!'.self::_t('Cell callback failed.').'!';
            } else {
                if (empty($field_name)
                 || !$field_exists_in_record
                 || !($field_details = $model_obj->table_field_details($field_name))
                 || !is_array($field_details)) {
                    $field_details = false;
                }

                $cell_callback_params = $render_params;
                $cell_callback_params['table_field'] = $field_details;
                $cell_callback_params['preset_content'] = ($cell_content ?? '');
                $cell_callback_params['extra_callback_params'] = (!empty($column_arr['extra_callback_params']) ? $column_arr['extra_callback_params'] : false);

                if (($cell_content = @call_user_func($column_arr['display_callback'], $cell_callback_params)) === false
                    || $cell_content === null) {
                    $cell_content = $column_arr['invalid_value'] ?? '!'.$this::_t('Render cell call failed.').'!';
                }
            }
        }

        // Allow display_callback parameter on checkbox fields...
        if ($render_params['for_scope'] !== PHS_Scope::SCOPE_API
            && $this->get_checkbox_name_for_column($column_arr)) {
            if (empty($field_name)
                || !isset($record_arr[$field_name])
                || !($field_details = $model_obj->table_field_details($field_name))
                || !is_array($field_details)) {
                $field_details = false;
            }

            $cell_callback_params = $render_params;
            $cell_callback_params['table_field'] = $field_details;
            $cell_callback_params['preset_content'] = $cell_content ?? '';

            if (($checkbox_content = $this->display_checkbox_column($cell_callback_params)) !== false
                && $checkbox_content !== null
                && is_string($checkbox_content)) {
                $cell_content = $checkbox_content;
            }
        }

        if (($cell_content === null)
            && $column_arr['invalid_value'] !== null) {
            $cell_content = $column_arr['invalid_value'];
        }

        return $cell_content;
    }

    public function get_listing_result() : string | array
    {
        if (empty($this->_originals)) {
            $this->extract_filters_scope();
        }

        $records_arr = $this->get_records();

        if (!$records_arr
            && $this->get_model()
            && !$this->flow_param('did_query_database')
            && !$this->query_model_for_records()) {
            return $this->get_error_message(self::_t('Error obtaining listing buffer.'));
        }

        // If records were provided from outside paginator class and no records count was provided just assume these are all records...
        if ($records_arr
            && $this->pagination_params('total_records') === -1) {
            $this->set_records_count(count($records_arr));
        }

        if (!($listing_buffer = $this->render_template('paginator_list'))) {
            // Allow empty buffer for listing (for scopes which don't need an output buffer)
            if (PHS_Scope::current_scope() === PHS_Scope::SCOPE_API) {
                $listing_buffer = [];
            } else {
                $listing_buffer = self::_t('Error obtaining listing buffer.').' - '.$this->get_error_message();
            }
        }

        return $listing_buffer;
    }

    public function get_full_buffer() : string
    {
        if (!($listing_buffer = $this->get_listing_result())
            || !is_string($listing_buffer)) {
            $listing_buffer = '';
        }

        if (!($filters_buffer = $this->get_filters_result())
            || !is_string($filters_buffer)) {
            $filters_buffer = '';
        }

        return $filters_buffer.$listing_buffer;
    }

    /**
     * @param $template
     * @param false|array $template_data
     *
     * @return bool|string
     */
    final public function render_template($template, $template_data = false)
    {
        $this->reset_error();

        if (empty($template_data) || !is_array($template_data)) {
            $template_data = ['paginator' => $this];
        }

        $view_params = [];
        $view_params['action_obj'] = false;
        $view_params['controller_obj'] = false;
        $view_params['plugin'] = false;
        $view_params['template_data'] = $template_data;

        if (!($view_obj = PHS_View::init_view($template, $view_params))) {
            if (self::st_has_error()) {
                $this->copy_static_error();
            }

            return false;
        }

        $rendering_params = null;
        if (PHS_Scope::current_scope() === PHS_Scope::SCOPE_API) {
            $rendering_params = ['only_string_result' => false];
        }

        // in API scope return could be an array to place in response
        if (($buffer = $view_obj->render(null, null, $rendering_params)) === null) {
            if ($view_obj->has_error()) {
                $this->copy_error($view_obj);
            } else {
                $this->set_error(self::ERR_RENDER, self::_t('Error rendering template [%s].', $view_obj->get_template()));
            }

            return false;
        }

        if (empty($buffer)) {
            $buffer = '';
        }

        return $buffer;
    }

    /**
     * @param false|array $params
     *
     * @return bool
     */
    protected function query_records_for_export($params = false)
    {
        $this->reset_error();

        if (empty($params) || !is_array($params)) {
            $params = [];
        }

        // In case we want to export all records or only records from current page...
        if (empty($params['export_all_records'])) {
            $params['export_all_records'] = false;
        } else {
            $params['export_all_records'] = true;
        }

        $records_params = [];
        if (!empty($params['export_all_records'])) {
            $records_params['store_query_id'] = true;
            $records_params['ignore_query_limit'] = true;
        } else {
            $records_params['store_query_id'] = false;
            $records_params['ignore_query_limit'] = false;
        }

        return $this->query_model_for_records($records_params);
    }

    private function _simulate_records_count_based_on_page() : int
    {
        $page = $this->pagination_params('page');
        $right_pages_no = $this->pagination_params('right_pages_no');
        $records_per_page = $this->_get_records_per_page();

        $max_pages = $page * $records_per_page + ($right_pages_no + 2) * $records_per_page;
        $count = $max_pages * $records_per_page;
        $offset = ($page * $records_per_page);

        $this->pagination_params('records_per_page', $records_per_page);
        $this->pagination_params('page', $page);
        $this->pagination_params('total_records', $count);
        $this->pagination_params('max_pages', $max_pages);
        $this->pagination_params('offset', $offset);

        return $count;
    }

    private function _get_records_per_page() : int
    {
        return max(2, $this->pagination_params('records_per_page'));
    }

    private function reset_records() : void
    {
        $this->_records_arr = [];
        $this->_query_id = false;
    }

    private function extract_action_from_request() : void
    {
        $this->_action = $this->default_action_params();

        if (!($flow_params = $this->flow_params())) {
            return;
        }

        if (!($bulk_select_name = $this->get_bulk_action_select_name())) {
            $bulk_select_name = '';
        }

        $action_key = $flow_params['form_prefix'].self::ACTION_PARAM_NAME;
        $action_params_key = $flow_params['form_prefix'].self::ACTION_PARAMS_PARAM_NAME;
        $action_result_key = $flow_params['form_prefix'].self::ACTION_RESULT_PARAM_NAME;

        if (!($action = PHS_Params::_gp($action_key, PHS_Params::T_NOHTML))) {
            if (($bulk_action = PHS_Params::_gp($bulk_select_name.'top', PHS_Params::T_NOHTML))) {
                $this->flow_param('bulk_action', $bulk_action);
                $this->flow_param('bulk_action_area', 'top');
                $action = $bulk_action;
            } elseif (($bulk_action = PHS_Params::_gp($bulk_select_name.'bottom', PHS_Params::T_NOHTML))) {
                $this->flow_param('bulk_action', $bulk_action);
                $this->flow_param('bulk_action_area', 'bottom');
                $action = $bulk_action;
            } else {
                return;
            }
        }

        $this->_action['action'] = $action;

        if (!($action_params = PHS_Params::_gp($action_params_key, PHS_Params::T_ASIS))) {
            $action_params = '';
        }
        if (!($action_result = PHS_Params::_gp($action_result_key, PHS_Params::T_ASIS))) {
            $action_result = '';
        }

        $this->_action['action_params'] = $action_params;
        $this->_action['action_result'] = $action_result;
    }

    private function extract_filters_scope()
    {
        $this->_scope = [];
        $this->_originals = [];

        if (!($filters_arr = $this->get_filters())) {
            return true;
        }

        if (!($flow_params_arr = $this->flow_params())) {
            $flow_params_arr = $this->default_flow_params();
        }

        // Allow filters even on hidden columns
        if (!($columns_arr = $this->get_columns())
         || !is_array($columns_arr)) {
            $columns_arr = [];
        }

        foreach ($filters_arr as $filter_details) {
            if (empty($filter_details['var_name'])
             || (empty($filter_details['record_field']) && empty($filter_details['switch_filter']) && empty($filter_details['raw_query']))) {
                continue;
            }

            $this->_originals[$filter_details['var_name']] = PHS_Params::_pg($flow_params_arr['form_prefix'].$filter_details['var_name'],
                PHS_Params::T_ASIS,
                ['trim_before' => (!empty($filter_details['trim_before']))]);

            if (!empty($new_filter['autocomplete'])) {
                $this->_originals[$filter_details['var_name'].'_phs_ac_name'] = PHS_Params::_pg($flow_params_arr['form_prefix'].$filter_details['var_name'].'_phs_ac_name', PHS_Params::T_NOHTML);
            }

            if ($this->_originals[$filter_details['var_name']] !== null) {
                // Accept arrays to be passed as comma separated values...
                if ($filter_details['type'] === PHS_Params::T_ARRAY
                 && is_string($this->_originals[$filter_details['var_name']])
                 && $this->_originals[$filter_details['var_name']] !== '') {
                    $value_type = PHS_Params::T_ASIS;
                    if (!empty($filter_details['extra_type']) && is_array($filter_details['extra_type'])
                    && !empty($filter_details['extra_type']['type'])
                    && PHS_Params::valid_type($filter_details['extra_type']['type'])) {
                        $value_type = $filter_details['extra_type']['type'];
                    }

                    $scope_val = [];
                    if (($parts_arr = explode(',', $this->_originals[$filter_details['var_name']]))
                    && is_array($parts_arr)) {
                        foreach ($parts_arr as $part) {
                            $scope_val[] = PHS_Params::set_type($part, $value_type);
                        }
                    }
                } else {
                    $scope_val = PHS_Params::set_type($this->_originals[$filter_details['var_name']],
                        $filter_details['type'],
                        $filter_details['extra_type']);
                }

                if ($filter_details['default'] !== false
                    && $scope_val !== $filter_details['default']) {
                    $this->_scope[$filter_details['var_name']] = $scope_val;
                    $ac_var_name = $filter_details['var_name'].'_phs_ac_name';
                    if (!empty($new_filter['autocomplete'])
                        && isset($this->_originals[$ac_var_name])) {
                        $this->_scope[$ac_var_name] = $this->_originals[$ac_var_name];
                    }
                }
            }
        }

        // Extract any checkboxes...
        if (!empty($columns_arr)) {
            foreach ($columns_arr as $column_arr) {
                if (empty($column_arr) || !is_array($column_arr)
                    || !($checkbox_name = $this->get_checkbox_name_for_column($column_arr))) {
                    continue;
                }

                $checkbox_name_all = $checkbox_name.self::CHECKBOXES_COLUMN_ALL_SUFIX;
                if (($checkbox_all_values = PHS_Params::_pg($checkbox_name_all, PHS_Params::T_INT))) {
                    $this->_scope[$checkbox_name_all] = 1;
                }

                // accept checkboxes to be passed as comma separated values...
                if (($checkbox_asis_value = PHS_Params::_pg($checkbox_name, PHS_Params::T_ASIS)) !== null) {
                    if (is_string($checkbox_asis_value)) {
                        $scope_val = [];
                        if (($parts_arr = explode(',', $checkbox_asis_value))
                            && is_array($parts_arr)) {
                            foreach ($parts_arr as $part) {
                                $scope_val[] = PHS_Params::set_type($part, $column_arr['checkbox_record_index_key']['type']);
                            }
                        }

                        $this->_scope[$checkbox_name] = $scope_val;
                    } elseif (($checkbox_array_value = PHS_Params::set_type($checkbox_asis_value, PHS_Params::T_ARRAY, ['type' => $column_arr['checkbox_record_index_key']['type']]))) {
                        $this->_scope[$checkbox_name] = $checkbox_array_value;
                    }
                }
            }
        }

        // Extract pagination vars...
        if (($pagination_params = $this->pagination_params())
        && is_array($pagination_params)) {
            if (!empty($pagination_params['page_var_name'])) {
                // Reset page number if filters were submitted...
                if (PHS_Params::_p($flow_params_arr['form_prefix'].'filters_submit')) {
                    $page = 0;
                } else {
                    $page = PHS_Params::_pg($flow_params_arr['form_prefix'].$pagination_params['page_var_name'],
                        PHS_Params::T_INT);
                }

                $this->pagination_params('page', $page);
            }

            if (!empty($pagination_params['per_page_var_name'])) {
                if (null !== ($per_page = PHS_Params::_pg($flow_params_arr['form_prefix'].$pagination_params['per_page_var_name'].'top', PHS_Params::T_INT))
                    || null !== ($per_page = PHS_Params::_pg($flow_params_arr['form_prefix'].$pagination_params['per_page_var_name'].'bottom', PHS_Params::T_INT))
                    || null !== ($per_page = PHS_Params::_pg($flow_params_arr['form_prefix'].$pagination_params['per_page_var_name'], PHS_Params::T_INT))) {
                    $this->pagination_params('records_per_page', $per_page);
                }
            }

            $sort = PHS_Params::_pg($flow_params_arr['form_prefix'].'sort', PHS_Params::T_INT);
            $sort_by = PHS_Params::_pg($flow_params_arr['form_prefix'].'sort_by', PHS_Params::T_NOHTML);
            $db_sort_by = '';

            if (!empty($columns_arr)) {
                $default_sort_by = false;
                $default_db_sort_by = false;
                $default_sort = false;
                $sort_by_valid = false;
                foreach ($columns_arr as $column_arr) {
                    if (empty($column_arr['record_field'])) {
                        continue;
                    }

                    if (!empty($column_arr['record_db_field'])) {
                        $field_name = $column_arr['record_db_field'];
                    } else {
                        $field_name = $column_arr['record_field'];
                    }

                    if ($column_arr['default_sort'] !== false) {
                        $default_sort_by = $field_name;
                        $default_db_sort_by = $column_arr['record_field'];
                        $default_sort = (!empty($column_arr['default_sort']) ? 1 : 0);

                        if ($sort_by === null) {
                            $sort_by = $default_sort_by;
                            $db_sort_by = $default_db_sort_by;
                            if ($sort === null) {
                                $sort = $default_sort;
                            }

                            break;
                        }
                    }

                    if ($sort_by === $field_name) {
                        $sort_by_valid = true;
                        $db_sort_by = $column_arr['record_field'];
                        break;
                    }
                }

                if (!$sort_by_valid && $default_sort_by !== false) {
                    $sort_by = $default_sort_by;
                    $db_sort_by = $default_db_sort_by;
                }
                if ($sort === null && $default_sort !== false) {
                    $sort = $default_sort;
                }
            }

            if (empty($sort) || strtolower($sort) === 'asc') {
                $sort = 0;
            } else {
                $sort = 1;
            }

            if (empty($sort_by)) {
                $sort_by = '';
                $db_sort_by = '';
            }

            $this->pagination_params('sort', $sort);
            $this->pagination_params('sort_by', $sort_by);
            $this->pagination_params('db_sort_by', $db_sort_by);
        }

        return true;
    }

    public static function valid_render_type($render_type) : bool
    {
        $render_type = (int)$render_type;

        return !(empty($render_type)
         || !in_array($render_type, [self::CELL_RENDER_HTML, self::CELL_RENDER_TEXT, self::CELL_RENDER_JSON], true));
    }

    public static function default_filter_fields() : array
    {
        return [
            'hidden_filter' => false,
            // Trim value before using it
            'trim_before' => false,
            // Variable name of the filter. This is mandatory, and it should be present in GET or POST in order for this filter to be taken in consideration
            'var_name' => '',
            // Name of field in database model that will have to check this value
            'record_field' => '',
            // A callable method which receives filter definition and filter value as parameters
            // Return should be an array with 'filter' and 'scope' keys.
            // If 'filter' key is present, it will replace current filter definition
            // If 'scope' key is present, it will replace current filter value in the scope
            // e.g. using 'raw_query', 'switch_filter' or 'record_check' depending on filter value
            'check_callback' => null,
            // If this filter doesn't target a specific field in the query, we just pass here a raw query
            // Filter value will be added in the raw query string as %s
            // Used for queries like EXISTS (SELECT 1 FROM ...)
            'raw_query' => '',
            // In case we want to select different filter functionality based on value provided for that filter
            // Usually this is used for filters that involve more fields in database based on value of the filter
            // e.g. Filter option 1: 'Logged-in users' -> status = active and last_log !== null,
            // Filter Options 2: 'Never logged-in users' -> status = active and last_log === null
            'switch_filter' => false, // Array
            // Database function passed to model to check value for this field (if false will be same as default field check eg. '=', [ 'check' => 'LIKE' ] )
            // If this is an array and 'value' key is not provided, script will create a 'value' key with value which coresponds from _scope array.
            // If 'value' key is passed it should contain a %s placeholder which will be replaced with value from _scope array.
            'record_check' => false,
            // If this is a raw_query filter, tell paginator to exclude specific fields (if set) - array of fields to be removed
            'remove_fields' => [],
            // This is similar with 'record_check', but if an array is provided at this key it will be used "as-it-is" for the field
            'raw_record_check' => false,
            // In case record_check parameter refers to other model, provide model to be used
            'record_check_model'        => false,
            'display_name'              => '',
            'display_hint'              => '',
            'display_placeholder'       => '',
            'type'                      => PHS_Params::T_ASIS,
            'extra_type'                => false,
            'default'                   => null,
            'display_default_as_filter' => false,
            'values_arr'                => false,
            'extra_style'               => '',
            'extra_classes'             => '',
            // In case there are more filters using a single field how should these be linked logically in sql
            // last filter will overwrite linkage_func of previous filters
            'linkage_func' => '',
            // In case this filter should display an autocomplete field, provide here all autocomplete action details...
            'autocomplete' => false,
        ];
    }

    public static function default_bulk_actions_fields() : array
    {
        return [
            'display_in_top'    => true,
            'display_in_bottom' => true,
            'display_name'      => '',
            'action'            => '',
            'js_callback'       => '',
            // name of column which holds the checkboxes that matter for this bulk action ('record_field'/'record_db_field' key in columns array)
            'checkbox_column' => '',
        ];
    }
}
