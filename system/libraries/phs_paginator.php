<?php
namespace phs\libraries;

use \phs\PHS;
use \phs\PHS_Scope;
use \phs\system\core\views\PHS_View;

class PHS_Paginator extends PHS_Registry
{
    const ERR_FILTERS = 1, ERR_BULK_ACTIONS = 2, ERR_COLUMNS = 3, ERR_MODEL = 4, ERR_RENDER = 5;

    const DEFAULT_PER_PAGE = 20;

    const CHECKBOXES_COLUMN_ALL_SUFIX = '_all';
    const ACTION_PARAM_NAME = 'pag_act', ACTION_PARAMS_PARAM_NAME = 'pag_act_params', ACTION_RESULT_PARAM_NAME = 'pag_act_result';

    const CELL_RENDER_HTML = 1, CELL_RENDER_TEXT = 2, CELL_RENDER_JSON = 3;

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

    // Array with records to be displayed (limit set based on paginator parameters from model or provided array of records from external source)
    private $_records_arr = array();
    // If exporting records we store query result here so we can iterate results rather than obtaining a huge (maybe) array with all records
    private $_query_id = false;

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

    public static function valid_render_type( $render_type )
    {
        if( empty( $render_type )
         or !in_array( $render_type, array( self::CELL_RENDER_HTML, self::CELL_RENDER_TEXT, self::CELL_RENDER_JSON ) ) )
            return false;

        return true;
    }

    public function default_api_listing_response()
    {
        return array(
            'offset' => 0,
            'records_per_page' => 0,
            'total_records' => 0,
            'listing_records_count' => 0,
            'page' => 0,
            'max_pages' => 0,
            'filters' => array(
                'sort' => 1,
                'sort_by' => '',
            ),
            'list' => array(),
        );
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
            'request_render_type' => self::CELL_RENDER_HTML,
            'page_index' => 0,
            'list_index' => 0,
            'columns_count' => 0,
            'record' => false,
            'column' => false,
            'table_field' => false,
            'preset_content' => '',
            'model_obj' => false,
            'paginator_obj' => false,
            'extra_callback_params' => false,
            'for_scope' => false,
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
            'initial_list_arr' => array(),
            'initial_count_list_arr' => array(),

            // Tells if we did query database to get records already
            'did_query_database' => false,
            // Tells if current records are obtained from querying model or they were provided
            'records_from_model' => false,

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
            'after_full_list_callback' => false,

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
            'db_sort_by' => '',
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

    public function pretty_date_independent( $date, $params = false )
    {
        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['date_format'] ) )
            $params['date_format'] = false;
        if( empty( $params['request_render_type'] ) )
            $params['request_render_type'] = false;

        if( empty( $date )
         or !($date_time = is_db_date( $date ))
         or empty_db_date( $date ) )
            return false;

        if( !empty( $params['date_format'] ) )
            $date_str = @date( $params['date_format'], parse_db_date( $date_time ) );
        else
            $date_str = $date;

        if( !empty( $params['request_render_type'] ) )
        {
            switch( $params['request_render_type'] )
            {
                case self::CELL_RENDER_JSON:
                case self::CELL_RENDER_TEXT:
                    return $date_str;
                    break;
            }
        }

        // force indexes for language xgettext parser
        self::_t( 'in %s' );
        self::_t( '%s ago' );

        if( ($seconds_ago = seconds_passed( $date_time )) < 0 )
            // date in future
            $lang_index = 'in %s';
        else
            // date in past
            $lang_index = '%s ago';

        return '<span title="'.self::_t( $lang_index, PHS_utils::parse_period( $seconds_ago, array( 'only_big_part' => true ) ) ).'">'.$date_str.'</span>';
    }

    public function pretty_date( $params )
    {
        if( !($params = self::validate_array( $params, $this->default_cell_render_call_params() ))
         or !is_array( $params )
         or empty( $params['record'] ) or !is_array( $params['record'] )
         or empty( $params['column'] ) or !is_array( $params['column'] )
         or (empty( $params['column']['record_field'] ) and empty( $params['column']['record_db_field'] )) )
            return false;

        if( !($field_name = $this->get_column_name( $params['column'], $params['for_scope'] )) )
            $field_name = false;

        if( empty( $field_name )
         or !array_key_exists( $field_name, $params['record'] ) )
            return false;

        $pretty_params = array();
        $pretty_params['date_format'] = (!empty( $params['column']['date_format'] )?$params['column']['date_format']:false);
        $pretty_params['request_render_type'] = (!empty( $params['request_render_type'] )?$params['request_render_type']:false);

        if( !($date_str = $this->pretty_date_independent( $params['record'][$field_name], $pretty_params )) )
            return (!empty($params['column']['invalid_value']) ? $params['column']['invalid_value'] : self::_t( 'N/A' ));

        return $date_str;
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

        return $flow_params_arr['form_prefix'].'paginator_filters_form';
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

        if( empty( $params['preset_content'] ) )
            $params['preset_content'] = '';

        if( !empty( $params['request_render_type'] ) )
        {
            switch( $params['request_render_type'] )
            {
                case self::CELL_RENDER_JSON:
                case self::CELL_RENDER_TEXT:
                    return $params['preset_content'];
                break;
            }
        }

        if( !($scope_arr = $this->get_scope()) )
            $scope_arr = array();

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
                   rel="skin_checkbox" <?php echo ($checkbox_checked?'checked="checked"':'')?>
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

    public function get_action_parameter_names()
    {
        if( !($flow_params = $this->flow_params()) )
            return false;

        $action_key = $flow_params['form_prefix'].self::ACTION_PARAM_NAME;
        $action_params_key = $flow_params['form_prefix'].self::ACTION_PARAMS_PARAM_NAME;
        $action_result_key = $flow_params['form_prefix'].self::ACTION_RESULT_PARAM_NAME;

        return array(
            'action' => $action_key,
            'action_params' => $action_params_key,
            'action_result' => $action_result_key,
        );
    }

    /**
     * @param string|array|bool $action array with 'action' key saying what action should be taken and 'action_params' key action parameters
     *
     * @return array|bool Array with parameters to be passed in get for action or false if no action
     */
    public function parse_action_parameter( $action )
    {
        if( empty( $action )
         or !($action_parameter_names = $this->get_action_parameter_names()) )
            return false;

        $action_key = $action_parameter_names['action'];
        $action_params_key = $action_parameter_names['action_params'];
        $action_result_key = $action_parameter_names['action_result'];

        $action_args = array();
        $action_args[$action_key] = '';
        $action_args[$action_params_key] = null;
        $action_args[$action_result_key] = null;

        if( is_string( $action ) )
            $action_args[$action_key] = $action;

        elseif( is_array( $action ) )
        {
            $action_args[$action_key] = (!empty( $action['action'] )?$action['action']:'');
            if( !empty( $action['action_params'] ) )
            {
                // try sending arrays as parameters (although not recommended)
                if( !is_string( $action['action_params'] ) )
                    $action['action_params'] = urlencode( @json_encode( $action['action_params'] ) );

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
        if( !isset( $params['include_action_params'] ) )
            $params['include_action_params'] = true;
        if( !isset( $params['include_filters'] ) )
            $params['include_filters'] = true;

        if( empty( $params['extra_params'] ) or !is_array( $params['extra_params'] ) )
            $params['extra_params'] = array();

        if( empty( $params['action'] ) )
            $params['action'] = false;

        if( !isset( $params['force_scope'] ) or !is_array( $params['force_scope'] ) )
            $params['force_scope'] = $this->_scope;

        if( empty( $params['include_action_params'] )
         or !($action_params = $this->parse_action_parameter( $params['action'] ))
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

        $query_string = '';

        // Don't run $action_params through http_build_query as values will be rawurlencoded and we might add javascript code in parameters
        // eg. action_params might be an id passed as javascript function parameter
        if( !empty( $params['include_action_params'] )
        and !empty( $action_params ) and is_array( $action_params ) )
        {
            foreach( $action_params as $key => $val )
            {
                if( $val === null )
                    continue;

                if( isset( $query_arr[$key] ) )
                    unset( $query_arr[$key] );

                $query_string .= ($query_string!=''?'&':'').$key.'='.$val;
            }
        }

        $query_string .= ($query_string!=''?'&':'').array_to_query_string( $query_arr );

        $url .= '&'.$query_string;

        return $url;
    }

    private function reset_records()
    {
        $this->_records_arr = array();
        $this->_query_id = false;
    }

    public function get_records()
    {
        return $this->_records_arr;
    }

    public function get_query_id()
    {
        return $this->_query_id;
    }

    public function set_query_id( $qid )
    {
        $this->reset_records();

        if( $qid
        and !($qid instanceof \mysqli_result) )
            return false;

        if( empty( $qid ) )
            $qid = false;

        $this->_query_id = $qid;

        $records_count = 0;
        if( $qid
        and !($records_count = @mysqli_num_rows( $qid )) )
            $records_count = 0;

        $this->pagination_params( 'listing_records_count', $records_count );

        return true;
    }

    public function set_records( $records_arr )
    {
        $this->reset_records();

        if( !is_array( $records_arr ) )
            return;

        $this->pagination_params( 'listing_records_count', count( $records_arr ) );

        $this->_records_arr = $records_arr;
    }

    public function format_api_export( $value, $column_arr, $for_scope = false )
    {
        if( empty( $column_arr ) or !is_array( $column_arr )
         or empty( $column_arr['api_export'] ) or !is_array( $column_arr['api_export'] ) )
            return false;

        $api_export = self::validate_array( $column_arr['api_export'], $this->default_api_export_fields() );

        if( ($new_value = PHS_params::set_type( $value, $api_export['type'], $api_export['type_extra'] )) !== null )
            $value = $new_value;

        elseif( $api_export['invalid_value'] !== null )
            $value = $api_export['invalid_value'];

        if( !empty( $api_export['field_name'] ) )
            $field_name = $api_export['field_name'];
        else
            $field_name = $this->get_column_name( $column_arr, $for_scope );

        return array(
            'key' => $field_name,
            'value' => $value,
        );
    }

    public function default_api_export_fields()
    {
        return array(
            // if left empty, resulting field name will be used
            'field_name' => '',
            // if left empty, resulting field name will be used
            'invalid_value' => null,
            // to what should be the value formatted
            'type' => PHS_params::T_ASIS,
            'type_extra' => false,
        );
    }

    public function default_column_fields()
    {
        return array(
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
            'extra_style' => '',
            'extra_classes' => '',
            // Raw attributes to be added to header td
            'raw_attrs' => '',
            // If column will be custom rendered and will span over more columns, provide the number of columns span here
            'column_colspan' => 1,
            // Record lines styling
            'extra_records_style' => '',
            'extra_records_classes' => '',
            // Raw attributes to be added to record td
            'raw_records_attrs' => '',
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
        $default_column_fields = $this->default_column_fields();
        foreach( $columns_arr as $column )
        {
            if( empty( $column )
             or !is_array( $column )
             or !($new_column = self::validate_array_to_new_array_recursive( $column, $default_column_fields )) )
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

    public function get_columns_for_scope( $scope = false )
    {
        $columns_arr = $this->get_columns();

        if( $scope === false )
            $scope = PHS_Scope::current_scope();

        if( !PHS_Scope::valid_scope( $scope ) )
            return $columns_arr;

        $scope_columns_arr = $columns_arr;
        if( !empty( $columns_arr ) and is_array( $columns_arr ) )
        {
            $scope_columns_arr = array();
            foreach( $columns_arr as $column_arr )
            {
                if( (!empty( $column_arr['hide_for_scopes'] ) and is_array( $column_arr['hide_for_scopes'] )
                        and in_array( $scope, $column_arr['hide_for_scopes'] ))
                    or
                    (!empty( $column_arr['show_for_scopes'] ) and is_array( $column_arr['show_for_scopes'] )
                        and !in_array( $scope, $column_arr['show_for_scopes'] ))
                )
                    continue;

                $scope_columns_arr[] = $column_arr;
            }
        }

        return $scope_columns_arr;
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
            // In case record_check parameter refers to other model, provide model to be used
            'record_check_model' => false,
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
            // In case there are more filters using a single field how should these be linked logically in sql
            // last filter will overwrite linkage_func of previous filters
            'linkage_func' => '',
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
            'display_in_top' => true,
            'display_in_bottom' => true,
            'display_name' => '',
            'action' => '',
            'js_callback' => '',
            // name of column which holds the checkboxes that matter for this bulk action ('record_field'/'record_db_field' key in columns array)
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

        // Allow filters even on hidden columns
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
            $db_sort_by = '';

            if( !empty( $columns_arr ) )
            {
                $default_sort_by = false;
                $default_db_sort_by = false;
                $default_sort = false;
                $sort_by_valid = false;
                foreach( $columns_arr as $column_arr )
                {
                    if( empty( $column_arr['record_field'] ) )
                        continue;

                    if( !empty( $column_arr['record_db_field'] ) )
                        $field_name = $column_arr['record_db_field'];
                    else
                        $field_name = $column_arr['record_field'];

                    if( $column_arr['default_sort'] !== false )
                    {
                        $default_sort_by = $field_name;
                        $default_db_sort_by = $column_arr['record_field'];
                        $default_sort = (!empty($column_arr['default_sort']) ? 1 : 0);

                        if( $sort_by === null )
                        {
                            $sort_by = $default_sort_by;
                            $db_sort_by = $default_db_sort_by;
                            if( $sort === null )
                                $sort = $default_sort;

                            break;
                        }
                    }

                    if( $sort_by == $field_name )
                    {
                        $sort_by_valid = true;
                        $db_sort_by = $column_arr['record_field'];
                        break;
                    }
                }

                if( !$sort_by_valid and $default_sort_by !== false )
                {
                    $sort_by = $default_sort_by;
                    $db_sort_by = $default_db_sort_by;
                }
                if( $sort === null and $default_sort !== false )
                    $sort = $default_sort;
            }

            if( empty( $sort ) or strtolower( $sort ) === 'asc' )
                $sort = 0;
            else
                $sort = 1;

            if( empty( $sort_by ) )
            {
                $sort_by = '';
                $db_sort_by = '';
            }

            $this->pagination_params( 'sort', $sort );
            $this->pagination_params( 'sort_by', $sort_by );
            $this->pagination_params( 'db_sort_by', $db_sort_by );
        }

        return true;
    }

    public function get_filters_result()
    {
        if( empty( $this->_originals ) )
            $this->extract_filters_scope();

        if( !($filters_buffer = $this->render_template( 'paginator_filters' )) )
        {
            if( $this->has_error() )
                $filters_buffer = self::_t( 'Error obtaining filters buffer.' ).' - '.$this->get_error_message();

            else
            {
                // Allow empty buffer for listing (for scopes which don't need an output buffer)
                if( PHS_Scope::current_scope() == PHS_Scope::SCOPE_API )
                    $filters_buffer = array();
                else
                    $filters_buffer = '';
            }
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

    public function reset_record_data( $record_data )
    {
        if( empty( $record_data ) or !is_array( $record_data ) )
            return $this->default_export_record_data();

        $record_data['record_arr'] = array();
        $record_data['record_buffer'] = '';

        return $record_data;
    }

    public function default_export_record_data()
    {
        return array(
            // Tells if current "record" to be parsed is the actual header of export
            'is_header' => false,
            // Index of record in records array
            'record_index' => 0,
            // Counter of current record in export list
            'record_count' => 0,
            // Actual record as array after "rendering" contents
            'record_arr' => array(),
            // Record after parsing its content for output
            'record_buffer' => '',
        );
    }

    public function export_result_array()
    {
        return array(
            'export_file_dir' => '',
            'export_file_name' => '',
            // Full location to export file
            'export_full_file_path' => '',
            // How many successful exports
            'exports_successful' => 0,
            'exports_failed' => 0,
        );
    }

    public function do_export_records( $params = false)
    {
        $this->reset_error();

        $export_action_scope = PHS_Scope::SCOPE_WEB;

        if( !($columns_arr = $this->get_columns_for_scope( $export_action_scope ))
         or !is_array( $columns_arr ) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'No columns defined for paginator. Export failed.' ) );
            return false;
        }

        $columns_count = 0;
        if( !empty( $columns_arr ) and is_array( $columns_arr ) )
            $columns_count = count( $columns_arr );

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['filter_records_fields'] ) or !is_array( $params['filter_records_fields'] ) )
            $params['filter_records_fields'] = false;

        if( empty( $params['ignore_headers'] ) )
            $params['ignore_headers'] = false;
        else
            $params['ignore_headers'] = true;

        if( empty( $params['model_query_params'] ) )
            $params['model_query_params'] = false;

        if( empty( $params['request_render_type'] )
         or !self::valid_render_type( $params['request_render_type'] ) )
            $params['request_render_type'] = self::CELL_RENDER_TEXT;

        if( empty( $params['exporter_library_params'] ) or !is_array( $params['exporter_library_params'] ) )
            $params['exporter_library_params'] = false;

        if( empty( $params['exporter_library'] ) )
        {
            $exporter_library_params = array();
            $exporter_library_params['full_class_name'] = '\\phs\\system\\core\\libraries\\PHS_Paginator_exporter_csv';
            $exporter_library_params['init_params'] = $params['exporter_library_params'];

            if( !($params['exporter_library'] = PHS::load_core_library( 'phs_paginator_exporter_csv', $exporter_library_params )) )
            {
                if( self::st_has_error() )
                    $this->copy_static_error( self::ERR_FUNCTIONALITY );
                else
                    $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error loading default CSV export library.' ) );
                return false;
            }
        }

        /** @var \phs\libraries\PHS_Paginator_exporter_library $exporter_library_obj */
        if( !($exporter_library_obj = $params['exporter_library'])
         or !($exporter_library_obj instanceof PHS_Paginator_exporter_library) )
        {
            $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Provided library is not a paginator export library.' ) );
            return false;
        }

        $exporter_library_obj->paginator_obj( $this );

        if( !$this->query_records_for_export( $params['model_query_params'] ) )
        {
            if( !$this->has_error() )
                $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error querying model for records to export.' ) );

            return false;
        }

        if( !$exporter_library_obj->start_output() )
        {
            if( $exporter_library_obj->has_error() )
                $this->copy_error( $exporter_library_obj );
            else
                $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error calling exporting library output start method.' ) );

            return false;
        }

        $record_data = $this->default_export_record_data();

        if( empty( $params['ignore_headers'] )
        and ($header_arr = $this->get_columns_header_as_array( $export_action_scope )) )
        {
            $record_data['is_header'] = true;
            $record_data['record_arr'] = $header_arr;

            if( !($record_data['record_buffer'] = $exporter_library_obj->record_to_buffer( $record_data )) )
                $record_data['record_buffer'] = '';

            if( !$exporter_library_obj->record_to_output( $record_data ) )
            {
                $exporter_library_obj->finish_output();

                if( $exporter_library_obj->has_error() )
                    $this->copy_error( $exporter_library_obj );
                else
                    $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error outputing header for export.' ) );

                return false;
            }
        }

        $record_data['is_header'] = false;

        $records_arr = $this->get_records();
        $query_id = $this->get_query_id();

        $return_arr = $this->export_result_array();
        $return_arr['export_file_name'] = $exporter_library_obj->export_registry( 'export_file_name' );
        $return_arr['export_file_dir'] = $exporter_library_obj->export_registry( 'export_file_dir' );
        $return_arr['export_full_file_path'] = $exporter_library_obj->export_registry( 'export_full_file_path' );

        // sanity check
        if( empty( $records_arr ) or !is_array( $records_arr ) )
            $records_arr = false;
        if( empty( $query_id ) or !($query_id instanceof \mysqli_result) )
            $query_id = false;

        if( empty( $records_arr ) and empty( $query_id ) )
        {
            $exporter_library_obj->finish_output();

            return $return_arr;
        }

        // Records have query fields in keys (usually unique ids, but not necessary consecutive)
        $records_keys_arr = false;
        $records_keys_index = 0;
        if( !empty( $records_arr ) and is_array( $records_arr ) )
            $records_keys_arr = array_keys( $records_arr );

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
        $cell_render_params['columns_count'] = count( $columns_arr );
        $cell_render_params['record'] = false;
        $cell_render_params['column'] = false;
        $cell_render_params['table_field'] = false;
        $cell_render_params['preset_content'] = '';
        $cell_render_params['model_obj'] = $this->get_model();
        $cell_render_params['paginator_obj'] = $this;
        $cell_render_params['for_scope'] = $export_action_scope;

        $fields_filters = false;

        while( true )
        {
            $record_data = $this->reset_record_data( $record_data );
            $record_count++;

            if( !empty( $records_keys_arr ) )
            {
                // get record from $records_arr array
                if( !isset( $records_keys_arr[$records_keys_index] )
                 or !($db_record_arr = $records_arr[$records_keys_arr[$records_keys_index]]) )
                    $db_record_arr = false;

                else
                {
                    $record_index = $records_keys_arr[$records_keys_index];
                    $records_keys_index++;
                }
            } else
            {
                // get record from query
                if( !($db_record_arr = @mysqli_fetch_assoc( $query_id )) )
                    $db_record_arr = false;
            }

            if( empty( $db_record_arr ) )
                break;

            $cell_render_params['page_index'] = $record_count;
            $cell_render_params['list_index'] = $record_count;
            $cell_render_params['record'] = $db_record_arr;
            $cell_render_params['table_field'] = false;
            $cell_render_params['preset_content'] = '';

            $record_arr = array();
            foreach( $columns_arr as $column_arr )
            {
                if( empty( $column_arr ) or !is_array( $column_arr ) )
                    continue;

                $cell_render_params['column'] = $column_arr;

                if( !($field_name = $this->get_column_name( $column_arr, PHS_Scope::SCOPE_WEB )) )
                    $field_name = false;

                if( ($cell_content = $this->render_column_for_record( $cell_render_params )) === null )
                    $cell_content = '!'.$this::_t( 'Failed rendering cell' ).'!';

                if( $field_name !== false )
                    $record_arr[$field_name] = $cell_content;
                else
                    $record_arr[] = $cell_content;
            }

            // Validate selection filters based on record field keys
            if( $fields_filters === false
            and !empty( $params['filter_records_fields'] ) )
            {
                $fields_filters = array();
                foreach( $params['filter_records_fields'] as $field_key => $filter_values )
                {
                    if( !isset( $record_arr[$field_key] )
                     or empty( $filter_values ) or !is_array( $filter_values ) )
                        continue;

                    $fields_filters[$field_key] = $filter_values;
                }
            }

            if( !empty( $fields_filters ) )
            {
                $should_continue = false;
                foreach( $fields_filters as $field_key => $filter_values )
                {
                    if( isset( $record_arr[$field_key] )
                    and !in_array( $record_arr[$field_key], $filter_values ) )
                    {
                        $should_continue = true;
                        break;
                    }
                }

                if( $should_continue )
                    continue;
            }

            $record_data['record_count'] = $record_count;
            $record_data['record_index'] = $record_index;
            $record_data['record_arr'] = $record_arr;

            if( !($record_data['record_buffer'] = $exporter_library_obj->record_to_buffer( $record_data )) )
            {
                $return_arr['exports_failed']++;
                continue;
            }

            if( !$exporter_library_obj->record_to_output( $record_data ) )
            {
                $return_arr['exports_failed']++;
                continue;
            }
        }

        if( !$exporter_library_obj->finish_output() )
        {
            if( $exporter_library_obj->has_error() )
                $this->copy_error( $exporter_library_obj );
            else
                $this->set_error( self::ERR_FUNCTIONALITY, self::_t( 'Error calling exporting library output finish method.' ) );

            return false;
        }

        return $return_arr;
    }

    public function get_columns_header_as_array( $scope = false )
    {
        if( $scope === false )
            $scope = PHS_Scope::current_scope();

        if( !($columns_arr = $this->get_columns_for_scope( $scope ))
         or !is_array( $columns_arr ) )
            return array();

        $return_arr = array();
        foreach( $columns_arr as $column_array_index => $column_arr )
        {
            if( empty( $column_arr ) or !is_array( $column_arr ) )
                continue;

            $return_arr[$column_array_index] = (!empty( $column_arr['column_title'] )?$column_arr['column_title']:'');
        }

        return $return_arr;
    }

    protected function query_records_for_export( $params = false )
    {
        $this->reset_error();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        // In case we want to export all records or only records from current page...
        if( empty( $params['export_all_records'] ) )
            $params['export_all_records'] = false;
        else
            $params['export_all_records'] = true;

        $records_params = array();
        if( !empty( $params['export_all_records'] ) )
        {
            $records_params['store_query_id'] = true;
            $records_params['ignore_query_limit'] = true;
        } else
        {
            $records_params['store_query_id'] = false;
            $records_params['ignore_query_limit'] = false;
        }

        return $this->query_model_for_records( $records_params );
    }

    public function query_model_for_records( $params = false )
    {
        $this->reset_error();

        if( empty( $params ) or !is_array( $params ) )
            $params = array();

        if( empty( $params['force'] ) )
            $params['force'] = false;
        if( empty( $params['store_query_id'] ) )
            $params['store_query_id'] = false;
        else
            $params['store_query_id'] = true;

        if( !empty( $params['store_query_id'] ) )
        {
            if( empty( $params['ignore_query_limit'] ) )
                $params['ignore_query_limit'] = false;
            else
                $params['ignore_query_limit'] = true;
        }

        $current_records = $this->get_records();

        if( empty( $params['force'] )
        and (!empty( $current_records )
                or $this->flow_param( 'did_query_database' )) )
            return true;

        if( !empty( $params['force'] )
         && !empty( $current_records )
         && !$this->flow_param( 'records_from_model' ) )
            return true;

        $this->reset_records();

        if( !($model_obj = $this->get_model()) )
        {
            $this->set_error( self::ERR_MODEL, self::_t( 'Model not set. Nothing to query.' ) );
            return false;
        }

        if( !($scope_arr = $this->get_scope())
         || !is_array( $scope_arr ) )
            $scope_arr = array();

        if( !($filters_arr = $this->get_filters())
         || !is_array( $filters_arr ) )
            $filters_arr = array();

        $initial_fields = array();
        if( !($list_arr = $this->flow_param( 'initial_list_arr' ))
         || !is_array( $list_arr ) )
            $list_arr = array();

        elseif( !empty( $list_arr['fields'] )
             && is_array( $list_arr['fields'] ) )
        {
            foreach( $list_arr['fields'] as $field_name => $field_val )
            {
                $initial_fields[$field_name] = true;
            }
        }

        if( !($count_list_arr = $this->flow_param( 'initial_count_list_arr' ))
         || !is_array( $count_list_arr ) )
            $count_list_arr = $list_arr;

        $list_arr['extra_sql'] = '';
        $count_list_arr['extra_sql'] = '';

        foreach( $filters_arr as $filter_arr )
        {
            if( empty( $filter_arr ) || !is_array( $filter_arr )
             || empty( $filter_arr['var_name'] )
             || empty( $filter_arr['record_field'] )
             || !isset( $scope_arr[$filter_arr['var_name']] )
             || ($filter_arr['default'] !== false && $scope_arr[$filter_arr['var_name']] == $filter_arr['default']) )
                continue;

            if( !empty( $filter_arr['record_check'] )
             && is_array( $filter_arr['record_check'] ) )
            {
                if( isset( $filter_arr['record_check']['value'] )
                 && strpos( $filter_arr['record_check']['value'], '%s' ) !== false )
                {
                    if( is_array( $scope_arr[$filter_arr['var_name']] ) )
                        $final_value = implode( ',', $scope_arr[$filter_arr['var_name']] );
                    else
                        $final_value = $scope_arr[$filter_arr['var_name']];

                    $check_value = $filter_arr['record_check'];

                    // Convert all %s into filter value... Also make sure %% won't be also replaced
                    $check_value['value'] = self::sprintf_all( $filter_arr['record_check']['value'], $final_value );
                }

                // more complex linkage...
                elseif( isset( $filter_arr['record_check']['fields'] ) )
                {
                    $check_model_obj = $model_obj;
                    if( !empty( $filter_arr['record_check_model'] )
                     && ($filter_arr['record_check_model'] instanceof PHS_Model) )
                        $check_model_obj = $filter_arr['record_check_model'];

                    if( ($linkage_params = $check_model_obj->get_query_fields( $filter_arr['record_check'] ))
                     && !empty( $linkage_params['extra_sql'] ) )
                    {
                        if( is_array( $scope_arr[$filter_arr['var_name']] ) )
                            $final_value = implode( ',', $scope_arr[$filter_arr['var_name']] );
                        else
                            $final_value = $scope_arr[$filter_arr['var_name']];

                        $linkage_params['extra_sql'] = self::sprintf_all( $linkage_params['extra_sql'], $final_value );

                        // In case we have complex linkages...
                        $list_arr['extra_sql'] .= '('.$linkage_params['extra_sql'].')';
                        $count_list_arr['extra_sql'] .= '('.$linkage_params['extra_sql'].')';

                        continue;
                    }
                }
            }

            if( empty( $check_value )
             || empty( $filter_arr['record_check'] ) )
                $check_value = $scope_arr[$filter_arr['var_name']];

            // If in initial list we were passed predefined filters and now we have an end-user filter,
            // discard predefined filter and use what end-user passed us
            if( !empty( $initial_fields )
             && !empty( $initial_fields[$filter_arr['record_field']] )
             && isset( $list_arr['fields'][$filter_arr['record_field']] ) )
            {
                unset( $list_arr['fields'][$filter_arr['record_field']] );
                unset( $initial_fields[$filter_arr['record_field']] );

                if( empty( $initial_fields ) )
                    $initial_fields = array();
            }

            // 'record_field' is always what we send to database...
            if( isset( $list_arr['fields'][$filter_arr['record_field']] ) )
            {
                if( !is_array( $list_arr['fields'][$filter_arr['record_field']] )
                 || empty( $list_arr['fields'][$filter_arr['record_field']][0] ) )
                {
                    $list_arr['fields'][$filter_arr['record_field']] = array( $list_arr['fields'][$filter_arr['record_field']] );
                    $count_list_arr['fields'][$filter_arr['record_field']] = array( $count_list_arr['fields'][$filter_arr['record_field']] );
                }

                $list_arr['fields'][$filter_arr['record_field']][] = $check_value;
                $count_list_arr['fields'][$filter_arr['record_field']][] = $check_value;

                if( !empty( $filter_arr['linkage_func'] ) )
                {
                    $list_arr['fields'][$filter_arr['record_field']]['linkage_func'] = $filter_arr['linkage_func'];
                    $count_list_arr['fields'][$filter_arr['record_field']]['linkage_func'] = $filter_arr['linkage_func'];
                }
            } else
            {
                $list_arr['fields'][$filter_arr['record_field']] = $check_value;
                $count_list_arr['fields'][$filter_arr['record_field']] = $check_value;
            }
        }

        $this->flow_param( 'did_query_database', true );
        $this->flow_param( 'records_from_model', true );

        $model_flow_params = $model_obj->fetch_default_flow_params( $list_arr );

        if( !($records_count = $model_obj->get_count( $count_list_arr )) )
        {
            // Set count of total records to 0
            $this->set_records_count( 0 );

            return true;
        }

        $this->set_records_count( $records_count );

        if( !empty( $params['ignore_query_limit'] ) )
        {
            $list_arr['offset'] = 0;
            $list_arr['enregs_no'] = 10000000000;
        } else
        {
            $list_arr['offset'] = $this->pagination_params( 'offset' );
            $list_arr['enregs_no'] = $this->pagination_params( 'records_per_page' );
        }

        $sort = $this->pagination_params( 'sort' );

        $sort_type_added = false;
        if( ($db_sort_by = $this->pagination_params( 'db_sort_by' ))
        and is_string( $db_sort_by ) )
        {
            if( strpos( $db_sort_by, '%s' ) !== false )
            {
                $db_sort_by = str_replace( '%s', (empty($sort) ? 'ASC' : 'DESC'), $db_sort_by );
                $sort_type_added = true;
            }

            $list_arr['order_by'] = $db_sort_by;
        }

        elseif( ($sort_by = $this->pagination_params( 'sort_by' ))
        and is_string( $sort_by ) )
            $list_arr['order_by'] = ((strpos( $sort_by, '.' ) === false)?'`'.$model_obj->get_flow_table_name( $model_flow_params ).'`.':'').$sort_by;

        if( !empty( $list_arr['order_by'] )
        and empty( $sort_type_added ) )
            $list_arr['order_by'] .= ' '.(empty( $sort )?'ASC':'DESC');

        if( !empty( $params['store_query_id'] ) )
            $list_arr['get_query_id'] = true;

        if( !($query_result = $model_obj->get_list( $list_arr )) )
            $query_result = false;

        if( !empty( $params['store_query_id'] ) )
            $this->set_query_id( $query_result );

        else
        {
            if( empty( $query_result ) )
                $query_result = array();

            $this->set_records( $query_result );
        }

        return true;
    }

    public function get_column_name( $column_arr, $for_scope = false )
    {
        if( empty( $column_arr ) or !is_array( $column_arr ) )
            return false;

        if( $for_scope === false )
            $for_scope = PHS_Scope::current_scope();

        $column_name = false;
        if( $for_scope == PHS_Scope::SCOPE_API
        and !empty( $column_arr['record_api_field'] ) )
            $column_name = $column_arr['record_api_field'];

        elseif( !empty( $column_arr['record_field'] ) or !empty( $column_arr['record_db_field'] ) )
        {
            if( !empty( $column_arr['record_db_field'] ) )
                $column_name = $column_arr['record_db_field'];
            else
                $column_name = $column_arr['record_field'];
        }

        return $column_name;
    }

    public function render_column_for_record( $render_params )
    {
        if( empty( $render_params ) or !is_array( $render_params ) )
            $render_params = array();

        if( empty( $render_params['request_render_type'] )
         or !self::valid_render_type( $render_params['request_render_type'] ) )
            $render_params['request_render_type'] = self::CELL_RENDER_HTML;

        if( empty( $render_params['record'] ) or !is_array( $render_params['record'] )
         or empty( $render_params['column'] ) or !is_array( $render_params['column'] ) )
            return '!'.self::_t( 'Unkown column or invalid record' ).'!';

        $column_arr = $render_params['column'];
        $record_arr = $render_params['record'];

        if( !($model_obj = $this->get_model()) )
            $model_obj = false;

        if( !($field_name = $this->get_column_name( $column_arr, $render_params['for_scope'] )) )
            $field_name = false;

        $field_exists_in_record = false;
        if( !empty( $field_name )
        and array_key_exists( $field_name, $record_arr ) )
            $field_exists_in_record = true;

        $cell_content = null;
        if( empty( $column_arr['record_field'] )
        and empty( $column_arr['record_db_field'] )
        and empty( $column_arr['record_api_field'] )
        and empty( $column_arr['display_callback'] ) )
            $cell_content = '!'.self::_t( 'Bad column setup' ).'!';

        elseif( $render_params['for_scope'] != PHS_Scope::SCOPE_API
             or empty( $field_exists_in_record ) )
        {
            if( !empty( $column_arr['display_key_value'] )
            and is_array( $column_arr['display_key_value'] )
            and !empty( $field_name )
            and isset( $record_arr[$field_name] ) )
            {
                if( isset( $column_arr['display_key_value'][$record_arr[$field_name]] ) )
                    $cell_content = $column_arr['display_key_value'][$record_arr[$field_name]];
                elseif( $column_arr['invalid_value'] !== null )
                    $cell_content = $column_arr['invalid_value'];
            }

            elseif( !empty( $model_obj )
                and !empty( $field_name )
                and $field_exists_in_record
                and ($field_details = $model_obj->table_field_details( $field_name ))
                and is_array( $field_details ) )
            {
                switch( $field_details['type'] )
                {
                    case $model_obj::FTYPE_DATETIME:
                    case $model_obj::FTYPE_DATE:
                        if( empty_db_date( $record_arr[$field_name] ) )
                        {
                            $cell_content = null;
                            if( empty( $column_arr['invalid_value'] ) )
                                $column_arr['invalid_value'] = self::_t( 'N/A' );
                        } elseif( !empty( $column_arr['date_format'] ) )
                            $cell_content = @date( $column_arr['date_format'], parse_db_date( $record_arr[$field_name] ) );
                    break;
                }
            }
        }

        if( $cell_content === null
        and !empty( $field_name )
        and $field_exists_in_record )
            $cell_content = $record_arr[$field_name];

        if( ($cell_content === null or $render_params['for_scope'] != PHS_Scope::SCOPE_API)
        and !empty( $column_arr['display_callback'] ) )
        {
            if( !@is_callable( $column_arr['display_callback'] ) )
                $cell_content = '!'.self::_t( 'Cell callback failed.' ).'!';

            else
            {
                if( empty( $field_name )
                 or !$field_exists_in_record
                 or !($field_details = $model_obj->table_field_details( $field_name ))
                 or !is_array( $field_details ) )
                    $field_details = false;

                $cell_callback_params = $render_params;
                $cell_callback_params['table_field'] = $field_details;
                $cell_callback_params['preset_content'] = ($cell_content === null?'':$cell_content);
                $cell_callback_params['extra_callback_params'] = (!empty( $column_arr['extra_callback_params'] )?$column_arr['extra_callback_params']:false);

                if( ($cell_content = @call_user_func( $column_arr['display_callback'], $cell_callback_params )) === false
                 or $cell_content === null )
                    $cell_content = '!' . $this::_t( 'Render cell call failed.' ) . '!';
            }
        }

        // Allow display_callback parameter on checkbox fields...
        if( $render_params['for_scope'] != PHS_Scope::SCOPE_API
        and $this->get_checkbox_name_for_column( $column_arr ) )
        {
            if( empty( $field_name )
             or !isset( $record_arr[$field_name] )
             or !($field_details = $model_obj->table_field_details( $field_name ))
             or !is_array( $field_details ) )
                $field_details = false;

            $cell_callback_params = $render_params;
            $cell_callback_params['table_field'] = $field_details;
            $cell_callback_params['preset_content'] = ($cell_content === null?'':$cell_content);

            if( ($checkbox_content = $this->display_checkbox_column( $cell_callback_params )) !== false
            and $checkbox_content !== null and is_string( $checkbox_content ) )
                $cell_content = $checkbox_content;
        }

        //if( empty( $cell_content )
        if( $cell_content === null )
        {
            if( $column_arr['invalid_value'] !== null )
                $cell_content = $column_arr['invalid_value'];
        }

        return $cell_content;
    }

    public function get_listing_result()
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
                $listing_buffer = self::_t( 'Error obtaining listing buffer.' ).' - '.$this->get_error_message();

            else
            {
                // Allow empty buffer for listing (for scopes which don't need an output buffer)
                if( PHS_Scope::current_scope() == PHS_Scope::SCOPE_API )
                    $listing_buffer = array();
                else
                    $listing_buffer = '';
            }
        }

        return $listing_buffer;
    }

    public function get_full_buffer()
    {
        if( !($listing_buffer = $this->get_listing_result())
         or !is_string( $listing_buffer ) )
            $listing_buffer = '';

        if( !($filters_buffer = $this->get_filters_result())
         or !is_string( $filters_buffer ) )
            $filters_buffer = '';

        return $filters_buffer.$listing_buffer;
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

        $rendering_params = false;
        if( PHS_Scope::current_scope() == PHS_Scope::SCOPE_API )
            $rendering_params = array( 'only_string_result' => false );

        // in API scope return could be an array to place in response
        if( ($buffer = $view_obj->render( false, false, $rendering_params )) === false )
        {
            if( $view_obj->has_error() )
                $this->copy_error( $view_obj );
            else
                $this->set_error( self::ERR_RENDER, self::_t( 'Error rendering template [%s].', $view_obj->get_template() ) );

            return false;
        }

        if( empty( $buffer ) )
            $buffer = '';

        return $buffer;
    }
}
