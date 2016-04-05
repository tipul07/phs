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

    if( !($columns_arr = $paginator_obj->get_columns()) )
        $columns_arr = array();
    if( !($records_arr = $paginator_obj->get_records()) )
        $records_arr = array();

    if( !($scope_arr = $paginator_obj->get_scope()) )
        $scope_arr = array();
?>
<div class="triggerAnimation animated fadeInRight" data-animate="fadeInRight" style="width:100%;min-width:1000px;margin: 0 auto;">
    <form id="<?php echo $flow_params_arr['form_prefix']?>paginator_list_form" name="<?php echo $flow_params_arr['form_prefix']?>paginator_list_form" action="<?php echo $full_filters_url?>" method="post" class="wpcf7">
    <input type="hidden" name="foobar" value="1" />

    <div class="form_container responsive">

        <section class="heading-bordered">
            <h3><?php echo (!empty( $flow_params_arr['listing_title'] )?$flow_params_arr['listing_title']:$this::_t( 'Listing %s %s...', $pagination_arr['total_records'], ($pagination_arr['total_records']==1?$flow_params_arr['term_singular']:$flow_params_arr['term_plural']) ))?></h3>
        </section>

        <div>
        <table style="width:100%" class="tgrid">
        <?php
        $columns_count = 0;
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

                if( !empty( $column_arr['sortable'] ) )
                {
                    ?><a href="#"><?php
                }

                echo $column_arr['column_title'];

                if( !empty( $column_arr['sortable'] ) )
                {
                    ?></a><?php
                }

                ?></th><?php
            }
            ?>
            </tr>
            </thead>
            <?php
        }

        ?><tbody><?php

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

                    elseif( !empty( $column_arr['display_callback'] )
                    and @is_callable( $column_arr['display_callback'] ) )
                    {
                        if( !isset( $record_arr[$column_arr['record_field']] )
                         or !($field_details = $model_obj->table_field_details( $column_arr['record_field'] ))
                         or !is_array( $field_details ) )
                            $field_details = false;

                        $cell_callback_params = $paginator_obj->default_cell_render_call_params();
                        $cell_callback_params['page_index'] = $knti;
                        $cell_callback_params['list_index'] = $offset + $knti;
                        $cell_callback_params['record'] = $record_arr;
                        $cell_callback_params['column'] = $column_arr;
                        $cell_callback_params['table_field'] = $field_details;

                        if( ($cell_content = @call_user_func( $column_arr['display_callback'], $cell_callback_params )) === false
                         or $cell_content === null )
                            $cell_content = '['.$this::_t( 'Render cell call failed.' ).']';
                    }

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

                    ?><td class="<?php echo $column_arr['extra_records_classes']?>" style="<?php echo $column_arr['extra_records_style']?>"><?php echo $cell_content?></td><?php
                }

                ?></tr><?php

                $knti++;
            }
        }

        ?>
        </tbody>
        </table>
        </div>

    </div>
    </form>
</div>

