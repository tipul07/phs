<?php
namespace phs\libraries;

use \phs\PHS;
use \phs\system\core\views\PHS_View;

class PHS_Paginator extends PHS_Registry
{
    const ERR_FILTERS = 1, ERR_BULK_ACTIONS = 2, ERR_COLUMNS = 3, ERR_MODEL = 4, ERR_RENDER = 5;

    const DEFAULT_PER_PAGE = 20;

    const CHECKBOXES_COLUMN_ALL_SUFIX = '_all';
    const ACTION_PARAM_NAME = 'pag_act', ACTION_PARAMS_PARAM_NAME = 'pag_act_params', ACTION_RESULT_PARAM_NAME = 'pag_act_result';

    // Bulk actions array
    private $_bulk_actions = array();
    // Filters array
    private $_filters = array();
    // Variables as provided in post or get
    private $_originals = array();
    // Parsed variables extracted from request
    private $_scope = array();
    // Action request (if any)
    private $_action = false;

    /** @var bool|\phs\libraries\PHS_Model  */
    private $_model = false;

    private $_flow_params_arr = array();
    private $_pagination_params_arr = false;
    private $_columns_definition_arr = array();

    private $_records_arr = array();

    /** @var string */
    private $_base_url = '';

    public function __construct( $base_url = false, $flow_params = false )
    {
        parent::__construct();

        $this->reset_paginator();

        if( $flow_params !== false )
            $this->flow_params( $flow_params );

        if( $base_url === false )
            $this->base_url( PHS::current_url() );
        else
            $this->base_url( $base_url );
    }

    public function default_others_render_call_params()
    {
        return array(
            'columns' => array(),
            'filters' => array(),
        );
    }

    public function default_cell_render_call_params()
    {
        return array(
            'page_index' => 0,
            'list_index' => 0,
            'columns_count' => 0,
            'record' => false,
            'column' => false,
            'table_field' => false,
            'preset_content' => '',
        );
    }

    public function default_flow_params()
    {
        return array(
            'form_prefix' => '',
            'unique_id' => str_replace( '.', '_', microtime( true ) ),
            'term_singular' => self::_t( 'record' ),
            'term_plural' => self::_t( 'records' ),
            'listing_title' => self::_t( 'Displaying results...' ),
            'did_query_database' => false,

            'bulk_action' => '',
            'bulk_action_area' => '',
            'display_top_bulk_actions' => true,
            'display_bottom_bulk_actions' => true,

            // Callbacks to alter display
            'after_record_callback' => false,

            'before_filters_callback' => false,
            'after_filters_callback' => false,
            'before_table_callback' => false,
            'after_table_callback' => false,

            'table_after_headers_callback' => false,
            'table_before_footer_callback' => false,
        );
    }

    public function default_pagination_params()
    {
        return array(
            'page_var_name' => 'page',
            'per_page_var_name' => 'per_page',
            'records_per_page' => self::DEFAULT_PER_PAGE,
            'page' => 0,
            'offset' => 0,
            'total_records' => -1,
            'listing_records_count' => 0,
            'max_pages' => 0,
            // How many pages to display left of current page before putting ... in left
            'left_pages_no' => 10,
            // How many pages to display right of current page before putting ... in right
            'right_pages_no' => 10,
            'sort' => 0,
            'sort_by' => '',
        );
    }

    public function default_action_params()
    {
        return array(
            'action' => '',
            'action_params' => '',
            'action_result' => '',
            'action_redirect_url_params' => false,
        );
    }

    /**
     * @param null|string $key Null to return full array or a string which is the key to set a value or array key of value to be returned
     * @param null|mixed $val Null or a value to be set for specified key
     *
     * @return array|bool|null
     */
    public function pagination_params( $key = null, $val = null )
    {
        if( $key === null and $val === null )
            return $this->_pagination_params_arr;

        if( $key !== null and $val === null )
        {
            if( is_array( $key ) )
            {
                $this->_pagination_params_arr = self::validate_array( $key, $this->default_pagination_params() );
                return $this->_pagination_params_arr;
            }

            if( is_string( $key ) and isset( $this->_pagination_params_arr[$key] ) )
                return $this->_pagination_params_arr[$key];

            return null;
        }

        if( is_string( $key ) and isset( $this->_pagination_params_arr[$key] ) )
        {
            $this->_pagination_params_arr[$key] = $val;
            return true;
        }

        return null;
    }

    public function flow_params( $params = false )
    {
        if( $params === false )
        {
            if( empty( $this->_flow_params_arr ) )
                $this->_flow_params_arr = $this->default_flow_params();

            return $this->_flow_params_arr;
        }

        $this->_flow_params_arr = self::validate_array_recursive( $params, $this->default_flow_params() );

        return $this->_flow_params_arr;
    }

    public function flow_param( $key, $val = null )
    {
        if( empty( $this->_flow_params_arr ) )
            $this->_flow_params_arr = $this->default_flow_params();

        if( !is_string( $key ) or !isset( $this->_flow_params_arr[$key] ) )
            return false;

        if( $val === null )
            return $this->_flow_params_arr[$key];

        $this->_flow_params_arr[$key] = $val;
        return true;
    }

    public function reset_paginator()
    {
        $this->_model = false;

        $this->_base_url = '';

        $this->_filters = array();
        $this->_scope = array();
        $this->_originals = array();

        $this->_flow_params_arr = $this->default_flow_params();
        $this->_pagination_params_arr = $this->default_pagination_params();
        $this->_columns_definition_arr = array();

        $this->reset_records();
    }

    public function pretty_date( $params )
    {
        if( !($params = self::validate_array( $params, $this->default_cell_render_call_params() ))
         or !is_array( $params )
         or empty( $params['record'] ) or !is_array( $params['record'] )
         or empty( $params['column'] ) or !is_array( $params['column'] )
         or empty( $params['column']['record_field'] )
         or empty( $params['record'][$params['column']['record_field']] ) )
            return false;

        if( !($date_time = is_db_date( $params['record'][$params['column']['record_field']] ))
         or empty_db_date( $params['record'][$params['column']['record_field']] ) )
            return (!empty( $params['column']['invalid_value'] )?$params['column']['invalid_value']:self::_t( 'N/A' ));

        if( !empty( $params['column']['date_format'] ) )
            $date_str = @date( $params['column']['date_format'], parse_db_date( $date_time ) );
        else
            $date_str = $params['record'][$params['column']['record_field']];

        $seconds_ago = seconds_passed( $date_time );

        return '<span title="'.self::_t( '%s ago', PHS_utils::parse_period( $seconds_ago, array( 'only_big_part' => true ) ) ).'">'.$date_str.'</span>';
    }

    public function get_checkbox_name_format()
    {
        if( !($flow_params_arr = $this->flow_params()) )
            return '';

        return $flow_params_arr['form_prefix'].'%s_chck';
    }

    public function get_all_checkbox_name_format()
    {
        if( !($flow_params_arr = $this->flow_params()) )
            return '';

        return $flow_params_arr['form_prefix'].'%s_chck'.self::CHECKBOXES_COLUMN_ALL_SUFIX;
    }

    public function get_listing_form_name()
    {
        if( !($flow_params_arr = $this->flow_params()) )
            return '';

        return $flow_params_arr['form_prefix'].'paginator_list_form';
    }

    public function get_filters_form_name()
    {
        if( !($flow_params_arr = $this->flow_params()) )
            return '';

        return $flow_params_arr['form_prefix'].'paginator_list_form';
    }

    public function get_checkbox_name_for_column( $column_arr )
    {
        if( empty( $column_arr ) or !is_array( $column_arr )
         or empty( $column_arr['checkbox_record_index_key'] ) or !is_array( $column_arr['checkbox_record_index_key'] )
         or empty( $column_arr['checkbox_record_index_key']['key'] )
         or !($flow_params_arr = $this->flow_params()) )
            return '';

        if( empty( $column_arr['checkbox_record_index_key']['checkbox_name'] ) )
            $column_arr['checkbox_record_index_key']['checkbox_name'] = $column_arr['checkbox_record_index_key']['key'];

        return @sprintf( $this->get_checkbox_name_format(), $column_arr['checkbox_record_index_key']['checkbox_name'] );
    }

    public function display_checkbox_column( $params )
    {
        if( !($params = self::validate_array( $params, $this->default_cell_render_call_params() ))
         or !is_array( $params )
         or empty( $params['record'] ) or !is_array( $params['record'] )
         or empty( $params['column'] ) or !is_array( $params['column'] )
         or !($checkbox_name = $this->get_checkbox_name_for_column( $params['column'] ))
         or empty( $params['column']['checkbox_record_index_key'] )
         or !is_array( $params['column']['checkbox_record_index_key'] )
         or empty( $params['column']['checkbox_record_index_key']['key'] )
         or !isset( $params['record'][$params['column']['checkbox_record_index_key']['key']] ) )
            return false;

        if( !($scope_arr = $this->get_scope()) )
            $scope_arr = array();

        if( empty( $params['preset_content'] ) )
            $params['preset_content'] = '';

        $checkbox_value = $params['record'][$params['column']['checkbox_record_index_key']['key']];
        $checkbox_name_all = $checkbox_name.self::CHECKBOXES_COLUMN_ALL_SUFIX;

        $checkbox_checked = false;
        if( !empty( $scope_arr ) and is_array( $scope_arr ) )
        {
            if( !empty( $scope_arr[$checkbox_name_all] )
             or (!empty( $scope_arr[$checkbox_name] )
                and is_array( $scope_arr[$checkbox_name] )
                and in_array( $checkbox_value, $scope_arr[$checkbox_name] )
                ) )
                $checkbox_checked = true;
        }

        ob_start();
        ?>
        <label for="<?php echo $checkbox_name?>" style="width:100%">
        <span style="float:left;">
            <input type="checkbox" value="<?php echo $checkbox_value?>" name="<?php echo $checkbox_name?>[]" id="<?php echo $checkbox_name.'_'.$checkbox_value?>"
                   class="wpcf7-text" rel="skin_checkbox" <?php echo ($checkbox_checked?'checked="checked"':'')?>
                   onchange="phs_paginator_update_list_all_checkbox( '<?php echo $checkbox_name.'_'.$checkbox_value?>', '<?php echo $checkbox_name_all?>' )" />
        </span>
        <?php echo $params['preset_content']?>
        </label>
        <?php

        return ob_get_clean();
    }

    public function base_url( $url = false )
    {
        if( $url === false )
            return $this->_base_url;

        $this->_base_url = $url;

        return $this->_base_url;
    }

    /**
     * @param array $action array with 'action' key saying what action should be taken and 'action_params' key action parameters
     *
     * @return array|bool Array with parameters to be passed in get for action or false if no action
     */
    public function parse_action_parameter( $action )
    {
        if( empty( $action )
         or !($flow_params = $this->flow_params()) )
            return false;

        $action_key = $flow_params['form_prefix'].self::ACTION_PARAM_NAME;
        $action_params_key = $flow_params['form_prefix'].self::ACTION_PARAMS_PARAM_NAME;
        $action_result_key = $flow_params['form_prefix'].self::ACTION_RESULT_PARAM_NAME;

        $action_args = array();
        $action_args[$action_key] = '';
        $action_args[$action_params_key] = '';
        $action_args[$action_result_key] = '';

        if( is_string( $action ) )
            $action_args[$action_key] = $action;

        elseif( is_array( $action ) )
        {
            $action_args[$action_key] = (!empty( $action['action'] )?$action['action']:'');
            if( !empty( $action['action_params'] ) )
            {
                // try sending arrays as parameters (although not recommended)
                if( !is_string( $action['action_params'] ) )
                    $action['action_params'] = rawurlencode( @json_encode( $action['action_params'] ) );

                $action_args[$action_params_key] = $action['action_params'];
            }
        }

        if( !empty( $action['action_result'] ) )
            $action_args[$action_result_key] = $action['action_result'];

        if( empty( $action_args[$action_key] ) )
            return false;

        return $action_args;
    }

    public function get_full_url( $params = false )
    {
        if( empty( $this->_originals ) )
            $this->extract_filters_scope();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( !isset( $params['include_pagination_params'] ) )
            $params['include_pagination_params'] = true;
        if( !isset( $params['include_filters'] ) )
            $params['include_filters'] = true;

        if( empty( $params['extra_params'] ) or !is_array( $params['extra_params'] ) )
            $params['extra_params'] = array();

        if( empty( $params['action'] ) )
            $params['action'] = false;

        if( !isset( $params['force_scope'] ) or !is_array( $params['force_scope'] ) )
            $params['force_scope'] = $this->_scope;

        if( !($action_params = $this->parse_action_parameter( $params['action'] ))
         or !is_array( $action_params ) )
            $action_params = false;

        if( isset( $params['sort'] ) )
            $params['sort'] = (!empty( $params['sort'] )?1:0);
        if( isset( $params['sort_by'] ) )
        {
            if( is_string( $params['sort_by'] ) )
                $params['sort_by'] = trim( $params['sort_by'] );
            else
                unset( $params['sort_by'] );
        }

        $query_arr = array();
        if( !empty( $params['include_filters'] )
        and !empty( $params['force_scope'] ) )
            $query_arr = array_merge( $query_arr, $params['force_scope'] );

        if( !empty( $params['include_pagination_params'] )
        and ($flow_params = $this->flow_params())
        and ($pagination_params = $this->pagination_params()) )
        {
            $add_args = array();
            $add_args[$flow_params['form_prefix'].$pagination_params['page_var_name']] = $pagination_params['page'];
            $add_args[$flow_params['form_prefix'].$pagination_params['per_page_var_name']] = $pagination_params['records_per_page'];
            $add_args[$flow_params['form_prefix'].'sort_by'] = (isset( $params['sort_by'] )?$params['sort_by']:$pagination_params['sort_by']);
            $add_args[$flow_params['form_prefix'].'sort'] = (isset( $params['sort'] )?$params['sort']:$pagination_params['sort']);

            $query_arr = array_merge( $query_arr, $add_args );
        }

        if( !empty( $params['extra_params'] ) )
            $query_arr = array_merge( $query_arr, $params['extra_params'] );

        $url = $this->_base_url;

        if( strstr( $url, '?' ) === false )
            $url .= '?';

        $query_string = array_to_query_string( $query_arr );


        // Don't run $action_params through http_build_query as values will be rawurlencoded and we might add javascript code in parameters
        // eg. action_params might be an id passed as javascript function parameter
        if( !empty( $action_params ) and is_array( $action_params ) )
        {
            foreach( $action_params as $key => $val )
            {
                $query_string .= '&'.$key.'='.$val;
            }
        }

        $url .= (substr( $query_string, 0, 1 )!='&'?'&':'').$query_string;

        return $url;
    }

    private function reset_records()
    {
        $this->_records_arr = array();
    }

    public function get_records()
    {
        return $this->_records_arr;
    }

    public function set_records( $records_arr )
    {
        $this->reset_records();

        if( empty( $records_arr ) or !is_array( $records_arr ) )
            return;

        $this->pagination_params( 'listing_records_count', count( $records_arr ) );

        $this->_records_arr = $records_arr;
    }

    public function default_column_fields()
    {
        return array(
            'column_title' => '',
            'record_field' => '',

            // 'key': should contain key in record fields that should be put as value in checkbox (it also defined checkbox name)
            // 'checkbox_name': string used to form input name, if empty 'key' will be used as 'checkbox_name' ({form_prefix}{checkbox_name}_chck)
            // 'type': is a PHS_params::T_* which will be used to validate input value
            'checkbox_record_index_key' => array(
                'key' => '',
                'checkbox_name' => '',
                'type' => PHS_params::T_ASIS,
            ),
            'sortable' => true,
            // 0 or 1 if default sorting 0 - ascending or 1 - descending
            'default_sort' => false,
            // In case we have an array of key-values and key is found in field of current record
            'display_key_value' => false,
            // in case we want a special display for this cell
            'display_callback' => false,
            // in case field is a date what format should the date be displayed in?
            'date_format' => '',
            // What should be displayed if value in column is not something valid
            'invalid_value' => '',
            'extra_style' => '',
            'extra_classes' => '',
            'extra_records_style' => '',
            'extra_records_classes' => '',
        );
    }

    public function set_columns( $columns_arr )
    {
        $this->reset_columns();

        if( empty( $columns_arr ) or !is_array( $columns_arr ) )
        {
            $this->set_error( self::ERR_COLUMNS, self::_t( 'Bad columns format.' ) );
            return false;
        }

        $new_columns = array();
        $default_column_fields = self::default_column_fields();
        foreach( $columns_arr as $column )
        {
            if( !($new_column = self::validate_array_to_new_array_recursive( $column, $default_column_fields )) )
                continue;

            if( empty( $new_column['column_title'] ) )
            {
                $this->set_error( self::ERR_COLUMNS, self::_t( 'Please provide a column_title for all columns.' ) );
                return false;
            }

            $new_columns[] = $new_column;
        }

        $this->_columns_definition_arr = $new_columns;

        return $this->_columns_definition_arr;
    }

    public function reset_columns()
    {
        $this->_columns_definition_arr = array();
    }

    public function get_columns()
    {
        return $this->_columns_definition_arr;
    }

    public static function default_filter_fields()
    {
        return array(
            'hidden_filter' => false,
            'var_name' => '',
            // Name of field in database model that will have to check this value
            'record_field' => '',
            // Database function passed to model to check value for this field (if false will be same as default field check eg. '=', array( 'check' => 'LIKE' ) )
            // If this is an array and 'value' key is not provided, script will create a 'value' key with value which coresponds from _scope array.
            // If 'value' key is passed it should contain a %s placeholder which will be replaced with value from _scope array.
            'record_check' => false,
            'display_name' => '',
            'display_hint' => '',
            'display_placeholder' => '',
            'type' => PHS_params::T_ASIS,
            'extra_type' => false,
            'default' => null,
            'display_default_as_filter' => false,
            'values_arr' => false,
            'extra_style' => '',
            'extra_classes' => '',
        );
    }

    public function set_filters( $filters_arr )
    {
        $this->reset_filters();

        if( empty( $filters_arr ) or !is_array( $filters_arr ) )
        {
            $this->set_error( self::ERR_FILTERS, self::_t( 'Bad filters format.' ) );
            return false;
        }

        $new_filters = array();
        $default_filters_fields = self::default_filter_fields();
        foreach( $filters_arr as $filter )
        {
            if( !($new_filter = self::validate_array_to_new_array( $filter, $default_filters_fields )) )
                continue;

            if( empty( $new_filter['var_name'] ) or empty( $new_filter['record_field'] ) )
            {
                $this->set_error( self::ERR_FILTERS, self::_t( 'var_name or record_field not provided for %s filter.', (!empty( $new_filter['display_name'] )?$new_filter['display_name']:'(???)') ) );
                return false;
            }

            $new_filters[] = $new_filter;
        }

        $this->_filters = $new_filters;

        return $this->_filters;
    }

    public function reset_filters()
    {
        $this->_filters = array();
    }

    public function get_filters()
    {
        return $this->_filters;
    }

    public static function default_bulk_actions_fields()
    {
        return array(
            'display_name' => '',
            'action' => '',
            'js_callback' => '',
            // name of column which holds the checkboxes that matter for this bulk action ('record_field' key in columns array)
            'checkbox_column' => '',
        );
    }

    public function set_bulk_actions( $actions_arr )
    {
        $this->reset_bulk_actions();

        if( empty( $actions_arr ) or !is_array( $actions_arr ) )
        {
            $this->set_error( self::ERR_BULK_ACTIONS, self::_t( 'Bad bulk actions format.' ) );
            return false;
        }

        $new_actions = array();
        $default_actions_fields = self::default_bulk_actions_fields();
        foreach( $actions_arr as $action )
        {
            if( !($new_action = self::validate_array_to_new_array( $action, $default_actions_fields )) )
                continue;

            if( empty( $new_action['action'] ) or empty( $new_action['js_callback'] ) )
            {
                $this->set_error( self::ERR_FILTERS, self::_t( 'No action or js_callback provided for bulk action %s.', (!empty( $new_action['display_name'] )?$new_action['display_name']:'(???)') ) );
                return false;
            }

            $new_actions[] = $new_action;
        }

        $this->_bulk_actions = $new_actions;

        return $this->_bulk_actions;
    }

    public function get_bulk_action_select_name()
    {
        if( !($flow_params_arr = $this->flow_params()) )
            return '';

        return $flow_params_arr['form_prefix'].'bulk_action';
    }

    public function reset_bulk_actions()
    {
        $this->_bulk_actions = array();
    }

    public function get_bulk_actions()
    {
        return $this->_bulk_actions;
    }

    public function get_scope()
    {
        if( empty( $this->_originals ) )
            $this->extract_filters_scope();

        return $this->_scope;
    }

    public function get_originals()
    {
        if( empty( $this->_originals ) )
            $this->extract_filters_scope();

        return $this->_originals;
    }

    public function reset_model()
    {
        $this->_model = false;
    }

    public function get_model()
    {
        return $this->_model;
    }

    /**
     * @param \phs\libraries\PHS_Model $model Model object which should provide records for listing
     *
     * @return bool True if everything went ok or false on error when setting model
     */
    public function set_model( $model )
    {
        $this->reset_error();

        if( empty( $model ) or !($model instanceof PHS_Model) )
        {
            $this->set_error( self::ERR_MODEL, self::_t( 'Model is invalid.' ) );
            return false;
        }

        $this->_model = $model;

        return true;
    }

    private function extract_action_from_request()
    {
        $this->_action = $this->default_action_params();

        if( !($flow_params = $this->flow_params()) )
            return false;

        if( !($bulk_select_name = $this->get_bulk_action_select_name()) )
            $bulk_select_name = '';

        $action_key = $flow_params['form_prefix'].self::ACTION_PARAM_NAME;
        $action_params_key = $flow_params['form_prefix'].self::ACTION_PARAMS_PARAM_NAME;
        $action_result_key = $flow_params['form_prefix'].self::ACTION_RESULT_PARAM_NAME;

        if( !($action = PHS_params::_gp( $action_key, PHS_params::T_NOHTML )) )
        {
            if( ($bulk_action = PHS_params::_gp( $bulk_select_name.'top', PHS_params::T_NOHTML )) )
            {
                $this->flow_param( 'bulk_action', $bulk_action );
                $this->flow_param( 'bulk_action_area', 'top' );
                $action = $bulk_action;
            } elseif( ($bulk_action = PHS_params::_gp( $bulk_select_name.'bottom', PHS_params::T_NOHTML )) )
            {
                $this->flow_param( 'bulk_action', $bulk_action );
                $this->flow_param( 'bulk_action_area', 'bottom' );
                $action = $bulk_action;
            } else
                return true;
        }

        $this->_action['action'] = $action;

        if( !($action_params = PHS_params::_gp( $action_params_key, PHS_params::T_ASIS )) )
            $action_params = '';
        if( !($action_result = PHS_params::_gp( $action_result_key, PHS_params::T_ASIS )) )
            $action_result = '';

        $this->_action['action_params'] = $action_params;
        $this->_action['action_result'] = $action_result;

        return true;
    }

    public function get_current_action()
    {
        if( empty( $this->_action ) )
            $this->extract_action_from_request();

        return $this->_action;
    }

    private function extract_filters_scope()
    {
        $this->_scope = array();
        $this->_originals = array();

        if( !($filters_arr = $this->get_filters()) )
            return true;

        if( !($flow_params_arr = $this->flow_params()) )
            $flow_params_arr = $this->default_flow_params();

        if( !($columns_arr = $this->get_columns())
         or !is_array( $columns_arr ) )
            $columns_arr = array();

        foreach( $filters_arr as $filter_details )
        {
            if( empty( $filter_details['var_name'] ) or empty( $filter_details['record_field'] ) )
                continue;

            $this->_originals[$filter_details['var_name']] = PHS_params::_pg( $flow_params_arr['form_prefix'].$filter_details['var_name'], PHS_params::T_ASIS );

            if( $this->_originals[$filter_details['var_name']] !== null )
            {
                // Accept arrays to be passed as comma separated values...
                if( $filter_details['type'] == PHS_params::T_ARRAY
                and is_string( $this->_originals[$filter_details['var_name']] )
                and $this->_originals[$filter_details['var_name']] != '' )
                {
                    $value_type = PHS_params::T_ASIS;
                    if( !empty( $filter_details['extra_type'] ) and is_array( $filter_details['extra_type'] )
                    and !empty( $filter_details['extra_type']['type'] )
                    and PHS_params::valid_type( $filter_details['extra_type']['type'] ) )
                        $value_type = $filter_details['extra_type']['type'];

                    $scope_val = array();
                    if( ($parts_arr = explode( ',', $this->_originals[$filter_details['var_name']] ))
                    and is_array( $parts_arr ) )
                    {
                        foreach( $parts_arr as $part )
                        {
                            $scope_val[] = PHS_params::set_type( $part, $value_type );
                        }
                    }
                } else
                    $scope_val = PHS_params::set_type( $this->_originals[$filter_details['var_name']],
                                                       $filter_details['type'],
                                                       $filter_details['extra_type'] );

                if( $filter_details['default'] !== false
                and $scope_val != $filter_details['default'] )
                    $this->_scope[$filter_details['var_name']] = $scope_val;
            }
        }

        // Extract any checkboxes...
        if( !empty( $columns_arr ) )
        {
            foreach( $columns_arr as $column_arr )
            {
                if( !($checkbox_name = $this->get_checkbox_name_for_column( $column_arr )) )
                    continue;

                $checkbox_name_all = $checkbox_name.self::CHECKBOXES_COLUMN_ALL_SUFIX;
                if( ($checkbox_all_values = PHS_params::_pg( $checkbox_name_all, PHS_params::T_INT )) )
                    $this->_scope[$checkbox_name_all] = 1;

                // accept checkboxes to be passed as comma separated values...
                if( ($checkbox_asis_value = PHS_params::_pg( $checkbox_name, PHS_params::T_ASIS )) !== null )
                {
                    if( is_string( $checkbox_asis_value ) )
                    {
                        $scope_val = array();
                        if( ($parts_arr = explode( ',', $checkbox_asis_value ))
                        and is_array( $parts_arr ) )
                        {
                            foreach( $parts_arr as $part )
                            {
                                $scope_val[] = PHS_params::set_type( $part, $column_arr['checkbox_record_index_key']['type'] );
                            }
                        }

                        $this->_scope[$checkbox_name] = $scope_val;
                    } elseif( ($checkbox_array_value = PHS_params::set_type( $checkbox_asis_value, PHS_params::T_ARRAY, array( 'type' => $column_arr['checkbox_record_index_key']['type'] ) )) )
                        $this->_scope[$checkbox_name] = $checkbox_array_value;
                }

            }
        }

        // Extract pagination vars...
        if( ($pagination_params = $this->pagination_params())
        and is_array( $pagination_params ) )
        {
            if( !empty( $pagination_params['page_var_name'] ) )
            {
                $page = PHS_params::_pg( $flow_params_arr['form_prefix'] . $pagination_params['page_var_name'], PHS_params::T_INT );
                $this->pagination_params( 'page', $page );
            }
            if( !empty( $pagination_params['per_page_var_name'] ) )
            {
                if( ($per_page = PHS_params::_pg( $flow_params_arr['form_prefix'] . $pagination_params['per_page_var_name'].'top', PHS_params::T_INT )) !== null )
                    $this->pagination_params( 'records_per_page', $per_page );
                elseif( ($per_page = PHS_params::_pg( $flow_params_arr['form_prefix'] . $pagination_params['per_page_var_name'].'bottom', PHS_params::T_INT )) !== null )
                    $this->pagination_params( 'records_per_page', $per_page );
                elseif( ($per_page = PHS_params::_pg( $flow_params_arr['form_prefix'] . $pagination_params['per_page_var_name'], PHS_params::T_INT )) !== null )
                    $this->pagination_params( 'records_per_page', $per_page );
            }

            $sort = PHS_params::_pg( $flow_params_arr['form_prefix'] . 'sort', PHS_params::T_INT );
            $sort_by = PHS_params::_pg( $flow_params_arr['form_prefix'] . 'sort_by', PHS_params::T_NOHTML );

            if( !empty( $columns_arr ) )
            {
                $default_sort_by = false;
                $default_sort = false;
                $sort_by_valid = false;
                foreach( $columns_arr as $column_arr )
                {
                    if( empty( $column_arr['record_field'] ) )
                        continue;

                    if( $column_arr['default_sort'] !== false )
                    {
                        $default_sort_by = $column_arr['record_field'];
                        $default_sort = (!empty($column_arr['default_sort']) ? 1 : 0);

                        if( $sort_by === null )
                        {
                            $sort_by = $default_sort_by;
                            if( $sort === null )
                                $sort = $default_sort;

                            break;
                        }
                    }

                    if( $sort_by == $column_arr['record_field'] )
                    {
                        $sort_by_valid = true;
                        break;
                    }
                }

                if( !$sort_by_valid and $default_sort_by !== false )
                    $sort_by = $default_sort_by;
                if( $sort === null and $default_sort !== false )
                    $sort = $default_sort;
            }

            if( empty( $sort ) or strtolower( $sort ) == 'asc' )
                $sort = 0;
            else
                $sort = 1;

            if( empty( $sort_by ) )
                $sort_by = '';

            $this->pagination_params( 'sort', $sort );
            $this->pagination_params( 'sort_by', $sort_by );
        }

        return true;
    }

    public function get_filters_buffer()
    {
        if( empty( $this->_originals ) )
            $this->extract_filters_scope();

        if( !($filters_buffer = $this->render_template( 'paginator_filters' )) )
        {
            if( $this->has_error() )
                $filters_buffer = $this->get_error_message();
            else
                $filters_buffer = self::_t( 'Error obtaining filters buffer.' );
        }

        return $filters_buffer;
    }

    public function set_records_count( $count )
    {
        $count = intval( $count );

        $page = $this->pagination_params( 'page' );
        $records_per_page = max( 2, $this->pagination_params( 'records_per_page' ) );

        $max_pages = ceil( $count / $records_per_page );

        if( $page > $max_pages-1 )
            $page = $max_pages - 1;
        if( $page < 0 )
            $page = 0;

        $offset = ($page * $records_per_page);

        $this->pagination_params( 'records_per_page', $records_per_page );
        $this->pagination_params( 'page', $page );
        $this->pagination_params( 'total_records', $count );
        $this->pagination_params( 'max_pages', $max_pages );
        $this->pagination_params( 'offset', $offset );
    }

    public function query_model_for_records( $params = false )
    {
        $this->reset_error();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['force'] ) )
            $params['force'] = false;

        if( empty( $params['force'] )
        and ($this->get_records()
                or $this->flow_param( 'did_query_database' )) )
            return true;

        $this->reset_records();

        if( !($model_obj = $this->get_model()) )
        {
            $this->set_error( self::ERR_MODEL, self::_t( 'Model not set. Nothing to query.' ) );
            return false;
        }

        if( !($scope_arr = $this->get_scope())
         or !is_array( $scope_arr ) )
            $scope_arr = array();

        if( !($filters_arr = $this->get_filters())
         or !is_array( $filters_arr ) )
            $filters_arr = array();

        $list_arr = array();
        $list_arr['extra_sql'] = '';

        foreach( $filters_arr as $filter_arr )
        {
            if( empty( $filter_arr ) or !is_array( $filter_arr )
             or empty( $filter_arr['var_name'] )
             or empty( $filter_arr['record_field'] )
             or !isset( $scope_arr[$filter_arr['var_name']] )
             or ($filter_arr['default'] !== false and $scope_arr[$filter_arr['var_name']] == $filter_arr['default']) )
                continue;

            if( !empty( $filter_arr['record_check'] )
            and is_array( $filter_arr['record_check'] ) )
            {
                if( isset( $filter_arr['record_check']['value'] )
                and strstr( $filter_arr['record_check']['value'], '%s' ) !== false )
                {
                    if( is_array( $scope_arr[$filter_arr['var_name']] ) )
                        $final_value = implode( ',', $scope_arr[$filter_arr['var_name']] );
                    else
                        $final_value = $scope_arr[$filter_arr['var_name']];

                    $check_value = $filter_arr['record_check'];
                    $check_value['value'] = sprintf( $filter_arr['record_check']['value'], $final_value );
                }

                // more complex linkage...
                elseif( isset( $filter_arr['record_check']['fields'] )
                and ($linkage_params = $model_obj->get_query_fields( $filter_arr['record_check'] ))
                and !empty( $linkage_params['extra_sql'] ) )
                {
                    if( is_array( $scope_arr[$filter_arr['var_name']] ) )
                        $final_value = implode( ',', $scope_arr[$filter_arr['var_name']] );
                    else
                        $final_value = $scope_arr[$filter_arr['var_name']];

                    while( strstr( $linkage_params['extra_sql'], '%s' ) !== false )
                        $linkage_params['extra_sql'] = @sprintf( $linkage_params['extra_sql'], $final_value );

                    $list_arr['extra_sql'] .= $linkage_params['extra_sql'];

                    continue;
                }
            }

            if( empty( $check_value )
             or empty( $filter_arr['record_check'] ) )
                $check_value = $scope_arr[$filter_arr['var_name']];

            $list_arr['fields'][$filter_arr['record_field']] = $check_value;
        }

        $this->flow_param( 'did_query_database', true );

        if( !($records_count = $model_obj->get_count( $list_arr )) )
        {
            // Set count of total records to 0
            $this->set_records_count( 0 );

            return true;
        }

        $this->set_records_count( $records_count );

        $list_arr['offset'] = $this->pagination_params( 'offset' );
        $list_arr['enregs_no'] = $this->pagination_params( 'records_per_page' );

        $sort = $this->pagination_params( 'sort' );
        if( ($sort_by = $this->pagination_params( 'sort_by' ))
        and is_string( $sort_by ) )
            $list_arr['order_by'] = ((strstr( $sort_by, '.' ) === false )?'`'.$model_obj->get_table_name().'`.':'').$sort_by.' '.(empty( $sort )?'ASC':'DESC');

        if( !($records_arr = $model_obj->get_list( $list_arr )) )
            $records_arr = array();

        $this->set_records( $records_arr );

        return true;
    }

    public function get_listing_buffer()
    {
        if( empty( $this->_originals ) )
            $this->extract_filters_scope();

        if( !($records_arr = $this->get_records()) )
            $records_arr = array();

        if( empty( $records_arr )
        and $this->get_model()
        and !$this->flow_param( 'did_query_database' ) )
        {
            if( !$this->query_model_for_records() )
            {
                if( $this->has_error() )
                    $listing_buffer = $this->get_error_message();
                else
                    $listing_buffer = self::_t( 'Error obtaining listing buffer.' );

                return $listing_buffer;
            }
        }

        // If records was provided from outside paginator class and no records count was provided just assume these are all records...
        if( !empty( $records_arr )
        and is_array( $records_arr )
        and $this->pagination_params( 'total_records' ) == -1 )
            $this->set_records_count( count( $records_arr ) );

        if( !($listing_buffer = $this->render_template( 'paginator_list' )) )
        {
            if( $this->has_error() )
                $listing_buffer = $this->get_error_message();
            else
                $listing_buffer = self::_t( 'Error obtaining listing buffer.' );
        }

        return $listing_buffer;
    }

    public function get_full_buffer()
    {
        return $this->get_filters_buffer().$this->get_listing_buffer();
    }

    final public function render_template( $template, $template_data = false )
    {
        $this->reset_error();

        if( empty( $template_data ) or !is_array( $template_data ) )
            $template_data['paginator'] = $this;

        $view_params = array();
        $view_params['action_obj'] = false;
        $view_params['controller_obj'] = false;
        $view_params['plugin'] = false;
        $view_params['template_data'] = $template_data;

        if( !($view_obj = PHS_View::init_view( $template, $view_params )) )
        {
            if( self::st_has_error() )
                $this->copy_static_error();

            return false;
        }

        if( !($buffer = $view_obj->render()) )
        {
            if( $view_obj->has_error() )
                $this->copy_error( $view_obj );
            else
                $this->set_error( self::ERR_RENDER, self::_t( 'Error rendering template [%s].', $view_obj->get_template() ) );

            return false;
        }

        return $buffer;
    }
}
