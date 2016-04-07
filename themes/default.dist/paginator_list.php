<?php
    /** @var \phs\system\core\views\PHS_View $this */

    use \phs\PHS;

    use \phs\libraries\PHS_params;

    /** @var \phs\libraries\PHS_Paginator $paginator_obj */
    if( !($paginator_obj = $this->context_var( 'paginator' )) )
    {
        echo $this::_t( 'Rendered from outside of paginator.' );
        return;
    }

    if( !($model_obj = $paginator_obj->get_model()) )
        $model_obj = false;

    if( !($base_url = $paginator_obj->base_url()) )
        $base_url = '#';
    if( !($full_filters_url = $paginator_obj->get_full_url( array( 'include_filters' => false ) )) )
        $full_filters_url = '#';

    if( !($flow_params_arr = $paginator_obj->flow_params()) )
        $flow_params_arr = $paginator_obj->default_flow_params();
    if( !($pagination_arr = $paginator_obj->pagination_params()) )
        $pagination_arr = $paginator_obj->default_pagination_params();

    if( !($filters_arr = $paginator_obj->get_filters()) )
        $filters_arr = array();
    if( !($columns_arr = $paginator_obj->get_columns()) )
        $columns_arr = array();
    if( !($records_arr = $paginator_obj->get_records()) )
        $records_arr = array();

    if( !($scope_arr = $paginator_obj->get_scope()) )
        $scope_arr = array();
?>
<div class="triggerAnimation animated fadeInRight" data-animate="fadeInRight" style="width:97%;min-width:900px;margin: 0 auto;">
    <form id="<?php echo $flow_params_arr['form_prefix']?>paginator_list_form" name="<?php echo $flow_params_arr['form_prefix']?>paginator_list_form" action="<?php echo form_str( $full_filters_url )?>" method="post" class="wpcf7">
    <input type="hidden" name="foobar" value="1" />

    <div class="form_container responsive">

        <section class="heading-bordered">
            <h3><?php echo (!empty( $flow_params_arr['listing_title'] )?$flow_params_arr['listing_title']:$this::_t( 'Listing %s %s...', $pagination_arr['total_records'], ($pagination_arr['total_records']==1?$flow_params_arr['term_singular']:$flow_params_arr['term_plural']) ))?></h3>
        </section>

        <?php

        if( !empty( $columns_count )
        and !empty( $flow_params_arr['before_table_callback'] )
        and is_callable( $flow_params_arr['before_table_callback'] ) )
        {
            $callback_params = $paginator_obj->default_others_render_call_params();
            $callback_params['columns'] = $columns_arr;
            $callback_params['filters'] = $filters_arr;

            if( ($cell_content = @call_user_func( $flow_params_arr['before_table_callback'], $callback_params )) === false
             or $cell_content === null )
                $cell_content = '[' . $this::_t( 'Render before table call failed.' ) . ']';

            echo $cell_content;
        }

        ?>

        <div>
        <table style="width:100%" class="tgrid">
        <?php
        $columns_count = 0;
        $checkboxes_update_arr = array();
        if( !empty( $columns_arr ) and is_array( $columns_arr ) )
        {
            $columns_count = count( $columns_arr );
            ?>
            <thead>
            <tr>
            <?php
            foreach( $columns_arr as $column_arr )
            {
                ?><th class="<?php echo (!empty( $column_arr['sortable'] )?'sortable':'').' '.$column_arr['extra_classes']?>" style="text-align:center;<?php echo $column_arr['extra_style']?>"><?php

                if( ($checkbox_column_name = $paginator_obj->get_checkbox_name_for_column( $column_arr )) )
                {
                    $checkbox_name_all = $checkbox_column_name.$paginator_obj::CHECKBOXES_COLUMN_ALL_SUFIX;
                    $checkbox_checked = false;
                    if( !empty( $scope_arr ) and is_array( $scope_arr )
                    and !empty( $scope_arr[$checkbox_name_all] ) )
                        $checkbox_checked = true;

                    ?><span style="width:100%;">
                    <span style="float:left;"><input type="checkbox" value="1" name="<?php echo $checkbox_name_all?>" id="<?php echo $checkbox_name_all?>" class="wpcf7-text" rel="skin_checkbox" <?php echo ($checkbox_checked?'checked="checked"':'')?> onchange="phs_paginator_update_list_checkboxes( '<?php echo $this::_e( $checkbox_column_name, '\'' )?>', '<?php echo $this::_e( $checkbox_name_all, '\'' )?>' )" /></span>
                    <?php

                    $checkboxes_update_arr[] = array(
                            'checkbox_name' => $checkbox_column_name,
                            'checkbox_name_all' => $checkbox_name_all,
                    );
                }

                if( !empty( $column_arr['sortable'] ) )
                {
                    ?><a href="#"><?php
                }

                echo $column_arr['column_title'];

                if( !empty( $column_arr['sortable'] ) )
                {
                    ?></a><?php
                }

                if( !empty( $checkbox_column_name ) )
                {
                    ?></span><?php
                }

                ?></th><?php
            }
            ?>
            </tr>
            </thead>
            <?php
        }

        ?><tbody><?php

        if( !empty( $columns_count )
        and !empty( $flow_params_arr['table_after_headers_callback'] )
        and is_callable( $flow_params_arr['table_after_headers_callback'] ) )
        {
            ?>
            <tr>
                <td colspan="<?php echo $columns_count?>"><?php

                $callback_params = $paginator_obj->default_others_render_call_params();
                $callback_params['columns'] = $columns_arr;
                $callback_params['filters'] = $filters_arr;

                if( ($cell_content = @call_user_func( $flow_params_arr['table_after_headers_callback'], $callback_params )) === false
                 or $cell_content === null )
                    $cell_content = '[' . $this::_t( 'Render after headers call failed.' ) . ']';

                echo $cell_content;

                ?></td>
            </tr>
            <?php
        }

        if( empty( $records_arr ) or !is_array( $records_arr ) )
        {
            if( $columns_count )
            {
                ?>
                <tr>
                    <td colspan="<?php echo $columns_count?>" style="text-align: center;"><p><?php

                    if( !empty( $scope_arr ) )
                        echo $this::_t( 'No %s found with specified filters.', $paginator_obj->flow_param( 'term_plural' ) );
                    else
                        echo $this::_t( 'No %s found in database.', $paginator_obj->flow_param( 'term_plural' ) );

                    ?></p></td>
                </tr>
                <?php
            }
        } elseif( $columns_count )
        {
            $knti = 0;
            $offset = $paginator_obj->pagination_params( 'offset' );
            foreach( $records_arr as $record_arr )
            {
                ?><tr><?php

                foreach( $columns_arr as $column_arr )
                {
                    $cell_content = false;
                    if( empty( $column_arr['record_field'] )
                    and empty( $column_arr['display_callback'] ) )
                        $cell_content = '['.$this::_t( 'Bad column setup' ).']';

                    elseif( !empty( $column_arr['display_key_value'] )
                        and is_array( $column_arr['display_key_value'] )
                        and !empty( $column_arr['record_field'] )
                        and isset( $record_arr[$column_arr['record_field']] )
                        and isset( $column_arr['display_key_value'][$record_arr[$column_arr['record_field']]] ) )
                        $cell_content = $column_arr['display_key_value'][$record_arr[$column_arr['record_field']]];

                    elseif( !empty( $model_obj )
                        and !empty( $column_arr['record_field'] )
                        and isset( $record_arr[$column_arr['record_field']] )
                        and ($field_details = $model_obj->table_field_details( $column_arr['record_field'] ))
                        and is_array( $field_details ) )
                    {
                        switch( $field_details['type'] )
                        {
                            case $model_obj::FTYPE_DATETIME:
                            case $model_obj::FTYPE_DATE:
                                if( empty_db_date( $record_arr[$column_arr['record_field']] ) )
                                    $cell_content = $this::_t( 'N/A' );
                                elseif( !empty( $column_arr['date_format'] ) )
                                    $cell_content = @date( $column_arr['date_format'], parse_db_date( $record_arr[$column_arr['record_field']] ) );
                            break;
                        }
                    }

                    if( $cell_content === false
                    and !empty( $column_arr['record_field'] )
                    and isset( $record_arr[$column_arr['record_field']] ) )
                        $cell_content = $record_arr[$column_arr['record_field']];

                    if( $cell_content === false )
                    {
                        if( !empty( $column_arr['invalid_value'] ) )
                            $cell_content = $column_arr['invalid_value'];
                        else
                            $cell_content = '(???)';
                    }

                    if( empty( $column_arr['display_callback'] )
                    and $paginator_obj->get_checkbox_name_for_column( $column_arr ) )
                        $column_arr['display_callback'] = array( $paginator_obj, 'display_checkbox_column' );

                    if( !empty( $column_arr['display_callback'] ) )
                    {
                        if( !@is_callable( $column_arr['display_callback'] ) )
                            $cell_content = '[' . $this::_t( 'Cell callback failed.' ) . ']';

                        else
                        {
                            if( !isset( $record_arr[$column_arr['record_field']] )
                             or !($field_details = $model_obj->table_field_details( $column_arr['record_field'] ))
                             or !is_array( $field_details ) )
                                $field_details = false;

                            $cell_callback_params                   = $paginator_obj->default_cell_render_call_params();
                            $cell_callback_params['page_index']     = $knti;
                            $cell_callback_params['list_index']     = $offset + $knti;
                            $cell_callback_params['record']         = $record_arr;
                            $cell_callback_params['column']         = $column_arr;
                            $cell_callback_params['table_field']    = $field_details;
                            $cell_callback_params['preset_content'] = $cell_content;

                            if( ($cell_content = @call_user_func( $column_arr['display_callback'], $cell_callback_params )) === false
                             or $cell_content === null )
                                $cell_content = '[' . $this::_t( 'Render cell call failed.' ) . ']';
                        }
                    }

                    ?><td class="<?php echo $column_arr['extra_records_classes']?>" style="<?php echo $column_arr['extra_records_style']?>"><?php echo $cell_content?></td><?php
                }

                ?></tr><?php

                $knti++;
            }
        }

        if( !empty( $columns_count )
        and !empty( $flow_params_arr['table_bofore_footer_callback'] )
        and is_callable( $flow_params_arr['table_bofore_footer_callback'] ) )
        {
            ?>
            <tr>
                <td colspan="<?php echo $columns_count?>"><?php

                $callback_params = $paginator_obj->default_others_render_call_params();
                $callback_params['columns'] = $columns_arr;
                $callback_params['filters'] = $filters_arr;

                if( ($cell_content = @call_user_func( $flow_params_arr['table_bofore_footer_callback'], $callback_params )) === false
                 or $cell_content === null )
                    $cell_content = '[' . $this::_t( 'Render before footer call failed.' ) . ']';

                echo $cell_content;

                ?></td>
            </tr>
            <?php
        }

        ?>
        </tbody>
        </table>
        </div>

    </div>
    </form>
</div>
<div class="clearfix"></div>
<?php
display_js_functionality( $this, $checkboxes_update_arr );
function display_js_functionality( $this_object, $checkboxes_update_arr = false )
{
    /** @var \phs\system\core\views\PHS_View $this_object */
    static $js_displayed = false;

    if( empty( $js_displayed ) )
    {
        $js_displayed = true;
        ?>
        <script type="text/javascript">
        function phs_paginator_update_list_checkboxes( checkbox_name, checkbox_name_all )
        {
            checkbox_all_obj = $( '#' + checkbox_name_all );

            if( !checkbox_all_obj )
                return;

            var should_be_checked = checkbox_all_obj.is(':checked');

            checkbox_all_obj.closest('form').find('input:checkbox').each(function(){
                var my_name = $(this).attr('name');

                if( my_name == checkbox_name + '[]' )
                {
                    $(this).prop('checked', should_be_checked );
                }
            });
        }
        </script>
        <?php
    }

    if( !empty( $checkboxes_update_arr ) and is_array( $checkboxes_update_arr ) )
    {
        ?><script type="text/javascript">
        <?php
        foreach( $checkboxes_update_arr as $checkbox_column )
        {
            if( !is_array( $checkbox_column ) )
                continue;

            ?>
            phs_paginator_update_list_checkboxes( '<?php echo $this_object::_e( $checkbox_column['checkbox_name'], '\'' )?>', '<?php echo $this_object::_e( $checkbox_column['checkbox_name_all'], '\'' )?>' );
            <?php
        }
        ?></script><?php
    }
}

if( !empty( $columns_count )
and !empty( $flow_params_arr['after_table_callback'] )
and is_callable( $flow_params_arr['after_table_callback'] ) )
{
    $callback_params = $paginator_obj->default_others_render_call_params();
    $callback_params['columns'] = $columns_arr;
    $callback_params['filters'] = $filters_arr;

    if( ($cell_content = @call_user_func( $flow_params_arr['after_table_callback'], $callback_params )) === false
     or $cell_content === null )
        $cell_content = '[' . $this::_t( 'Render after table call failed.' ) . ']';

    echo $cell_content;
}
